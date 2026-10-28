<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Product;
use App\Models\Outlet;
use App\Models\ProductMinimumAdjustment;
use App\Models\RefundPembelian;
use App\Models\RequestOrder;
use App\Models\RequestOrderItem;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\DashboardSalesService;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // K8: dashboard penjualan untuk semua role kecuali admin-gudang.
        // K23: staff-outlet & kasir hanya melihat outlet sendiri (dipaksa di server).
        $showSales  = in_array($user->role, config('roles.sales_dashboard', []), true);
        $showGudang = in_array($user->role, ['superadmin', 'admin', 'owner', 'admin-gudang'], true);

        $salesOptions = [
            'showSales'    => $showSales,
            'showGudang'   => $showGudang,
            // admin-gudang: blok gudang tampil langsung; role lain: ringkas (dibuka manual)
            'gudangOpen'   => $user->role === 'admin-gudang',
            'outletBound'  => in_array($user->role, ['staff-outlet', 'kasir'], true),
            'outlets'      => $showSales ? OutletAccess::outlets() : collect(),
        ];

        if (! $showGudang) {
            // staff-outlet & kasir: kartu penjualan outlet sendiri (+ kartu permintaan untuk staff-outlet)
            $data = ['isStaffOutletDashboard' => $user->role === 'staff-outlet'] + $salesOptions;

            if ($user->role === 'staff-outlet') {
                $requestOrdersBase = RequestOrder::where('owner_id', $user->outlet_id);
                $data['outletRequestTotal']   = (clone $requestOrdersBase)->count();
                $data['outletRequestPending'] = (clone $requestOrdersBase)->where('status', 'pending')->count();
            }

            return view('dashboard.index', $data);
        }

        $urgentSuppliers = Supplier::whereNotNull('deadline_days')
            ->whereNotNull('deadline_interval_weeks')
            ->with(['pembelians' => fn ($q) => $q->where('created_at', '>=', now()->subWeeks(4))])
            ->get()
            ->filter(function ($s) {
                $next = $s->nextDeadlineDate();
                if (! $next) {
                    return false;
                }
                if (Carbon::today()->diffInDays($next, false) > 3) {
                    return false;
                }
                if ($s->hasPembelianInCurrentInterval($next)) {
                    return false;
                }
                $s->next_deadline = $next;

                return true;
            })
            ->sortBy('next_deadline')
            ->values();

        // Semua angka stok di dashboard memakai SUM(stocks.qty) = stok fisik gudang
        // (sama dengan menu Stok, Produk, dan Laporan).
        $nearExpiryStocks = Stock::with('product:id,name,code')
            ->where('qty', '>', 0)
            ->whereNotNull('expired_at')
            ->whereDate('expired_at', '>=', now()->toDateString())
            ->whereDate('expired_at', '<=', now()->addDays(60)->toDateString())
            ->orderBy('expired_at')
            ->get(['id', 'product_id', 'qty', 'expired_at', 'batch_number', 'sku']);

        $activeAdjustments = ProductMinimumAdjustment::query()
            ->activeOn()
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id');

        $lowVelocityProducts = Product::select('id', 'code', 'name', 'min_stock')
            ->withSum('stocks', 'qty')
            ->where('min_stock', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($activeAdjustments) {
                $adj          = $activeAdjustments->get($product->id)?->first();
                $effectiveMin = $adj
                    ? (int) ceil($product->min_stock * (1 + $adj->adjustment_percentage / 100))
                    : (int) $product->min_stock;
                $currentStock = (int) ($product->stocks_sum_qty ?? 0);

                $product->effective_min         = $effectiveMin;
                $product->current_stock         = $currentStock;
                $product->adjustment_percentage = $adj?->adjustment_percentage ?? 0;
                $product->deficit               = max(0, $effectiveMin - $currentStock);

                return $product;
            })
            ->filter(fn ($p) => $p->current_stock <= $p->effective_min)
            ->sortByDesc('deficit')
            ->values();

        // Stat cards
        $totalStock        = (int) Stock::sum('qty');
        $pendingOrdersCount = RequestOrder::where('status', 'pending')->count();
        $deliveredCount    = DeliveryOrder::where('status', 'delivered')->count();
        $refundCount       = RefundPembelian::count();
        $pendingOwnerApprovals = Pembelian::with(['supplier'])
            ->where('owner_approval_status', 'pending')
            ->latest()
            ->limit(5)
            ->get();

        // Top 5 products by stok gudang (inventory chart)
        $inventoryChart = Stock::selectRaw('product_id, SUM(qty) as total_qty')
            ->with('product:id,name,code')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Top 5 most requested products (status order donut chart)
        $statusOrderChart = RequestOrderItem::selectRaw('product_id, SUM(qty_requested) as total_qty')
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Top 5 products most delivered to outlets
        $topProducts = DeliveryOrderItem::selectRaw('product_id, SUM(qty_sent) as total_qty')
            ->with('product:id,name,code')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // 5 most recent request orders
        $recentOrders = RequestOrder::with(['owner:id,name'])
            ->latest()
            ->limit(5)
            ->get();

        // Slow moving: products with stock but not delivered in last 90 days
        $recentlyDeliveredIds = DeliveryOrderItem::where('created_at', '>=', now()->subDays(90))
            ->distinct()
            ->pluck('product_id');

        $slowMovingProducts = Product::select('id', 'code', 'name')
            ->withSum('stocks', 'qty')
            ->whereNotIn('id', $recentlyDeliveredIds)
            ->orderByDesc('stocks_sum_qty')
            ->limit(5)
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'bestBuyProducts'  => [],
                'bestBuySuppliers' => [],
                'salesGraph'       => [],
                'productGraph'     => [],
                'monthlyRevenue'   => [],
            ]);
        }

        return view('dashboard.index', [
            'isStaffOutletDashboard' => false,
            ...$salesOptions,
            'products'           => Product::count(),
            'stocks'             => Stock::sum('qty'),
            'penjualans'         => Penjualan::count(),
            'pembelianTerkirim'  => Pembelian::where('is_published', true)->count(),
            'totalRevenue'       => 0,
            // Stat cards
            'totalStock'         => $totalStock,
            'pendingOrdersCount' => $pendingOrdersCount,
            'deliveredCount'     => $deliveredCount,
            'refundCount'        => $refundCount,
            'lowStockCount'      => $lowVelocityProducts->count(),
            'pendingOwnerApprovalCount' => $pendingOwnerApprovals->count(),
            // Charts
            'inventoryChart'     => $inventoryChart,
            'statusOrderChart'   => $statusOrderChart,
            'topProducts'        => $topProducts,
            // Tables
            'recentOrders'       => $recentOrders,
            'slowMovingProducts' => $slowMovingProducts,
            // Existing widgets
            'urgentSuppliers'    => $urgentSuppliers,
            'nearExpiryStocks'   => $nearExpiryStocks,
            'lowVelocityProducts' => $lowVelocityProducts,
            'pendingOwnerApprovals' => $pendingOwnerApprovals,
        ]);
    }

    /**
     * Data penjualan dashboard (JSON, dimuat via AJAX supaya halaman pertama cepat).
     * Parameter: tanggal_mulai, tanggal_selesai (default: bulan berjalan), outlet_id, refresh.
     */
    public function salesData(Request $request, DashboardSalesService $service)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, config('roles.sales_dashboard', []), true), 403, 'Tidak punya akses dashboard penjualan.');

        $today = now()->startOfDay();
        $start = $this->parseDate($request->input('tanggal_mulai')) ?? $today->copy()->startOfMonth();
        $end   = $this->parseDate($request->input('tanggal_selesai')) ?? $today->copy();
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }
        abort_if(
            (int) abs($start->diffInDays($end)) + 1 > DashboardSalesService::MAX_RANGE_DAYS,
            422,
            'Rentang tanggal maksimal '.DashboardSalesService::MAX_RANGE_DAYS.' hari.'
        );

        // Outlet dipaksa di server untuk role terikat outlet (K23), bukan hanya disembunyikan di UI.
        if (in_array($user->role, ['staff-outlet', 'kasir'], true)) {
            $outletId = OutletAccess::id($request);
        } elseif ($request->filled('outlet_id')) {
            $outletId = (int) $request->input('outlet_id');
            abort_unless(Outlet::whereKey($outletId)->exists(), 404, 'Outlet tidak ditemukan.');
        } else {
            $outletId = null;
        }

        return response()->json(
            $service->payload($start->copy()->startOfDay(), $end->copy()->endOfDay(), $outletId, $request->boolean('refresh'))
        );
    }

    protected function parseDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setting()
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        return view('dashboard.setting', [
            'name'    => $settings['name'] ?? '',
            'email'   => $settings['email'] ?? '',
            'telp'    => $settings['telp'] ?? '',
            'address' => $settings['address'] ?? '',
            'website' => $settings['website'] ?? '',
            'logo'    => $settings['logo'] ?? '',
            'returnPinConfigured' => filled($settings['return_pin_hash'] ?? null),
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'name'    => 'required',
            'email'   => 'required|email',
            'telp'    => 'required',
            'address' => 'required',
            'website' => 'nullable|url',
            'logo'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
        if (auth()->user()?->role === 'superadmin') {
            $rules['return_pin'] = 'nullable|digits_between:4,8';
        }

        $this->validate($request, $rules, [
            'logo.image' => 'File yang diunggah harus berupa gambar.',
            'logo.mimes' => 'Logo harus bertipe: jpeg, png, jpg, atau gif.',
            'logo.max'   => 'Ukuran logo maksimal 2 MB.',
        ]);

        $settings = json_decode(Storage::disk('public')->get('settings.json') ?? '{}', true) ?? [];
        $data = array_merge($settings, [
            'name'    => $request->name,
            'email'   => $request->email,
            'telp'    => $request->telp,
            'address' => $request->address,
            'website' => $request->website,
        ]);

        if (auth()->user()?->role === 'superadmin' && $request->filled('return_pin')) {
            $data['return_pin_hash'] = Hash::make((string) $request->input('return_pin'));
        }
        unset($data['return_pin']);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo'] = $path;
        }

        Storage::disk('public')->put('settings.json', json_encode($data));

        return redirect(route('setting'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }
}