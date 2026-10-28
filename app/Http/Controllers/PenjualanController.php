<?php

namespace App\Http\Controllers;

use App\Http\Requests\PenjualanRequest;
use App\Models\Kas;
use App\Models\Outlet;
use App\Models\Penjualan;
use App\Models\Stock;
use App\Models\Voucher;
use App\Services\CashierSaleService;
use App\Exports\PenjualanRekapExport;
use App\Services\OutletLaporanService;
use App\Support\OutletAccess;
use App\Support\PenjualanFilter;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PenjualanController extends Controller
{
    /**
     * staff-outlet / kasir hanya boleh mengakses penjualan outlet sendiri (K16).
     * Dipaksa di server, bukan hanya disembunyikan di tampilan.
     */
    protected function authorizeOutlet($outletId): void
    {
        $user = auth()->user();
        if (PenjualanFilter::isOutletBound($user)) {
            abort_unless(
                $user->outlet_id && (int) $outletId === (int) $user->outlet_id,
                403,
                'Anda tidak memiliki akses ke penjualan outlet lain.'
            );
        }
    }

    public function getPenjualan($outlet_id)
    {
        $this->authorizeOutlet($outlet_id);

        $penjualans = Penjualan::where('outlet_id', $outlet_id)->get();

        return response()->json($penjualans);
    }

    public function getItems($penjualan_id)
    {
        $penjualan = Penjualan::find($penjualan_id);
        if ($penjualan) {
            $this->authorizeOutlet($penjualan->outlet_id);
            $items = $penjualan->items;

            return response()->json($items);
        } else {
            return response()->json([], 404);
        }
    }

    public function marketplace()
    {
        return view('penjualan.marketplace', [
            'penjualan' => Penjualan::has('transaction')->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Halaman hanya mengirim kerangka + pilihan filter. Data tabel & kartu ringkasan
     * diambil lewat AJAX (data() & summary()) memakai PenjualanFilter yang sama.
     */
    public function index(Request $request)
    {
        $filter = PenjualanFilter::fromRequest($request);

        return view('penjualan.index', [
            'filter'  => $filter,
            'options' => $filter->options($request->user()),
            'presets' => PenjualanFilter::PRESETS,
            'bound'   => PenjualanFilter::isOutletBound($request->user()),
        ]);
    }

    /** Server-side DataTables: hanya satu halaman data yang dimuat. */
    public function data(Request $request)
    {
        $filter = PenjualanFilter::fromRequest($request);

        $draw = (int) $request->input('draw');
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 100) : 25;

        $grandSql = 'COALESCE(penjualans.grand_total, penjualans.total - COALESCE(penjualans.discount, 0), 0)';
        $methodSql = "COALESCE(payment_methods.name, penjualans.payment_method_name, 'Tunai')";

        // kolom tabel => [ekspresi urut, tabel join yang dibutuhkan]
        $sortable = [
            1 => ['penjualans.created_at', null],
            2 => ['penjualans.code', null],
            3 => ['users.name', 'users'],
            4 => ['cashier_shifts.name', 'cashier_shifts'],
            6 => [$methodSql, 'payment_methods'],
            7 => [$grandSql, null],
        ];
        $orderCol = (int) $request->input('order.0.column', 1);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        [$orderExpr, $joinTable] = $sortable[$orderCol] ?? $sortable[1];

        $base = $filter->query();
        $recordsFiltered = (clone $base)->count();

        // Join hanya bila dipakai untuk mengurutkan, supaya query tetap ringan.
        switch ($joinTable) {
            case 'users': // kolom Customer
                $base->leftJoin('users', 'users.id', '=', 'penjualans.customer_id');
                break;
            case 'cashier_shifts':
                $base->leftJoin('cashier_shifts', 'cashier_shifts.id', '=', 'penjualans.cashier_shift_id');
                break;
            case 'payment_methods':
                $base->leftJoin('payment_methods', 'payment_methods.id', '=', 'penjualans.payment_method_id');
                break;
        }

        $pageIds = $base
            ->orderByRaw($orderExpr.' '.$orderDir)
            ->orderBy('penjualans.id', 'desc')
            ->offset($start)
            ->limit($length)
            ->pluck('penjualans.id')
            ->all();

        // Relasi hanya dimuat untuk baris di halaman ini.
        $rows = Penjualan::query()
            ->with(['customer', 'cashierShift', 'paymentMethod'])
            ->withCount(['items'])
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $pageIds))
            ->values();

        $data = $rows->map(function (Penjualan $p) {
            $grand = $p->grand_total ?? ((float) $p->total - (float) $p->discount);

            // Tombol Hapus mengikuti aturan role baru di Tahap 4 (superadmin langsung,
            // admin -> pending). Untuk saat ini perilakunya sama seperti halaman lama.
            $aksi = '<a class="btn btn-xs btn-info" href="'.e(route('penjualan.show', $p->id)).'"><i class="fa fa-eye"></i> Show</a> '
                .'<a class="btn btn-xs btn-warning" href="'.e(route('penjualan.print', $p->id)).'"><i class="fa fa-print"></i> Re-Print</a> '
                .'<form action="'.e(route('penjualan.destroy', $p->id)).'" method="post" style="display:inline;">'
                .csrf_field().method_field('delete')
                .'<button type="submit" class="btn btn-xs btn-danger" onclick="return confirm(\'Yakin hapus penjualan ini?\')"><i class="fa fa-trash"></i> Hapus</button>'
                .'</form>';

            return [
                'id'          => $p->id,
                'tanggal'     => $p->created_at?->format('d/m/Y H:i') ?? '-',
                'code'        => $p->code,
                'customer'    => $p->customer?->name ?? 'Umum',
                'shift'       => $p->cashierShift?->name ?? '-',
                'item_count'  => (int) $p->items_count,
                'metode'      => $p->paymentMethod?->name ?? $p->payment_method_name ?? 'Tunai',
                'grand'       => (float) $grand,
                'aksi_html'   => $aksi,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsFiltered, // total & terfilter sama: hindari COUNT penuh tiap draw
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data->values(),
        ]);
    }

    /** Kartu ringkasan: angka dari filter yang sama dengan tabel. */
    public function summary(Request $request)
    {
        $filter = PenjualanFilter::fromRequest($request);

        return response()->json($filter->summary() + ['periode' => $filter->periodLabel()]);
    }

    /** Detail item satu nota (dibuka saat baris di-expand). */
    public function detail(Penjualan $penjualan)
    {
        $this->authorizeOutlet($penjualan->outlet_id);

        $penjualan->load([
            // withTrashed: produk yang sudah di-soft-delete tetap tampil di riwayat penjualan
            'items.product' => fn ($q) => $q->withTrashed(),
            'cashierShift', 'kasir', 'customer', 'paymentMethod', 'refundPenjualans',
        ]);

        return response()->json([
            'html' => view('penjualan._detail', ['penjualan' => $penjualan])->render(),
        ]);
    }

    /**
     * Download Rekap (Excel) memakai filter aktif: sheet Ringkasan + sheet Transaksi.
     */
    public function rekap(Request $request, OutletLaporanService $laporan)
    {
        $filter = PenjualanFilter::fromRequest($request);

        $count = (clone $filter->query())->count();
        abort_if(
            $count > PenjualanRekapExport::MAX_ROWS,
            422,
            "Data terlalu banyak untuk satu unduhan ({$count} nota, maksimal ".PenjualanRekapExport::MAX_ROWS.'). Persempit rentang tanggal.'
        );

        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        // Bon & setoran memakai definisi yang sama dengan Laporan Penjualan Outlet
        // (periode + outlet + kasir; tidak mengikuti filter metode/status/pencarian).
        $export = new PenjualanRekapExport(
            $filter,
            $laporan->bonTotal($filter->start, $filter->end, $filter->outletId, $filter->kasirId),
            $laporan->setoranTotal($filter->start, $filter->end, $filter->outletId, $filter->kasirId),
        );

        return Excel::download($export, $export->fileName());
    }

    public function create()
    {
        if (auth()->user()->role == 'kasir' | auth()->user()->role == 'admin'){
            return redirect()->route('outlet.show', auth()->user()->outlet_id);
        }

        return view('penjualan.create', [
            'outlets' => Outlet::get(),
        ]);
    }

    public function store(Request $request, CashierSaleService $sales)
    {
        // Total, subtotal, diskon, promo, dan kembalian dihitung server dari keranjang
        // (CashierSaleService), jadi tidak diterima dari client. customer_id boleh kosong
        // (pelanggan Umum).
        $data = $request->validate([
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'customer_id' => 'nullable|exists:users,id',
            'salesman_id' => 'nullable',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'payment_method_name' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'voucher_codes' => 'nullable|array',
            'voucher_codes.*' => 'string|max:100',
            'promotion_codes' => 'nullable|array',
            'promotion_codes.*' => 'string|max:100',
        ]);

        // Kasir / staff outlet hanya boleh bertransaksi di outletnya sendiri.
        $data['outlet_id'] = OutletAccess::id($request);

        try {
            $order = $sales->checkout($request->user(), $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat.',
            'redirect' => route('outlet.show', $order->outlet_id),
            'print' => route('penjualan.print', [$order]),
            'order' => $order,
        ], 201);
    }

    public function show(Penjualan $penjualan)
    {
        $this->authorizeOutlet($penjualan->outlet_id);

        // dd($penjualan->load(['kasir', 'customer', 'items.product'])->toArray());
        // $pdf = PDF::loadView('penjualan.penjualan_pdf', ['penjualan' => $penjualan]);

        // return $pdf->download('penjualan_'.$penjualan->id.'.pdf');
        return view('penjualan.show', [
            'penjualan' => $penjualan,
        ]);
    }

    public function print(Penjualan $penjualan)
    {
        $this->authorizeOutlet($penjualan->outlet_id);

        // paper=faktur -> faktur penjualan continuous form 9,5 x 11 inch (24,13 x 27,94 cm)
        $view = request('paper') === 'faktur' ? 'penjualan.print-faktur' : 'penjualan.print';

        return view($view, [
            'penjualan' => $penjualan,
        ]);
    }

    // public function edit(Penjualan $penjualan)
    // {
    //     return view('penjualan.edit', [
    //         'penjualan' => $penjualan,
    //     ]);
    // }

    // public function update(PenjualanRequest $request, Penjualan $penjualan)
    // {
    //     $data = $request->validated();

    //     $penjualan->update($data);

    //     return redirect(route('penjualan.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    // }

    public function destroy(Penjualan $penjualan)
    {
        $this->authorizeOutlet($penjualan->outlet_id);

        $penjualan->delete();

        return redirect(route('penjualan.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}