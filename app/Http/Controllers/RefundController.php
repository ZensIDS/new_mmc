<?php

namespace App\Http\Controllers;

use App\Http\Requests\RefundRequest;
use App\Models\Kas;
use App\Models\Outlet;
use App\Models\Penjualan;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    public function index()
    {
        return view('refunds.index', [
            'refunds' => Refund::get(),
        ]);
    }

    public function create()
    {
        return view('refunds.create', [
            'outlets' => Outlet::whereHas('penjualan')->get(),
            'customers' => User::where('role', 'customer')->get(),
            'penjualans' => Penjualan::get(),
            'products' => Product::get(),
            'kas' => Kas::get(),
        ]);
    }

    public function edit(Refund $refund)
    {
        return view('refunds.edit', [
            'refund' => $refund,
            'outlets' => Outlet::get(),
            'customers' => User::where('role', 'customer')->get(),
            'penjualans' => Penjualan::get(),
            'products' => Product::get(),
            'kas' => Kas::get(),
        ]);
    }

    public function show(Refund $refund)
    {
        // dd($refund->load(['customer', 'outlet', 'penjualan', 'refundItems', 'refundItems.product'])->toArray());
        return view('refunds.show', [
            'refund' => $refund,
        ]);
    }

    //Menambah market Stocks
    public function store(RefundRequest $request)
    {
        $data = $request->validated();

        $data['total'] = (float) $data['total'];
        $data['user_id'] = auth()->user()->id;
        $refund = Refund::create($data);

        foreach ($request->product as $product) {
            RefundItem::create([
                'refund_id' => $refund->id,
                'product_id' => $product['product_id'],
                'qty' => $product['qty'],
                'alasan' => $product['alasan'],
            ]);

            $productModel = Product::find($product['product_id']);

            if ($productModel->is_serialized) {
                Stock::create([
                    'product_id' => $product['product_id'],
                    'serial_number' => $product['alasan'],
                    'qty' => 1,
                    'harga_beli' => $productModel->harga_beli,
                    'condition' => 'used',
                    'status' => 'free',

                ]);
            } else {
                $stock = Stock::where('product_id', $product['product_id'])->first();

                if ($stock) {
                    $stock->qty += $product['qty'];
                    $stock->save();
                } else {
                    Stock::create([
                        'product_id' => $product['product_id'],
                        'qty' => $product['qty'],
                        'harga_beli' => $productModel->harga_beli,
                        'status' => 'free',
                    ]);
                }
            }
        }

        $kas = Kas::find($request->kas_id);
        $kas->nominal += $data['total'];
        $kas->save();

        return redirect(route('refund.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function update(RefundRequest $request, Refund $refund)
    {
        $oldTotal = $refund->total;
        $data = $request->validated();
        $data['total'] = (float) $data['total'];
        $data['user_id'] = auth()->user()->id;
        $refund->update($data);
        RefundItem::where('refund_id', $refund->id)->delete();
        foreach ($request->product as $product) {
            RefundItem::create([
                'refund_id' => $refund->id,
                'product_id' => $product['product_id'],
                'qty' => $product['qty'],
                'alasan' => $product['alasan'],
            ]);
        }

        $kas = Kas::find($request->kas_id);
        $kas->nominal += $oldTotal != $data['total'] ? $data['total'] : 0;
        $kas->save();

        return redirect(route('refund.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    /**
     * Hapus refund (Tahap 4C.0, K20): membalik efek `store()` — stok gudang yang ditambahkan
     * dikurangi lagi (batch serial dihapus) dan nominal kas dikembalikan. Gagal (stok sudah
     * terpakai / tidak cukup) -> tidak ada yang berubah dan pesan jelas ditampilkan.
     */
    public function destroy(Refund $refund)
    {
        try {
            DB::transaction(function () use ($refund) {
                foreach ($refund->refundItems as $item) {
                    $product = Product::find($item->product_id);
                    if (! $product) {
                        continue;
                    }

                    if ($product->is_serialized) {
                        // store(): satu baris stok per unit (serial_number = alasan, qty 1, status free).
                        $stock = Stock::where('product_id', $item->product_id)
                            ->where('serial_number', $item->alasan)
                            ->whereNull('pembelian_id')
                            ->where('status', 'free')
                            ->lockForUpdate()
                            ->first();
                        if (! $stock || (int) $stock->qty < 1) {
                            throw new \RuntimeException("Unit serial {$item->alasan} ({$product->name}) sudah tidak ada/terpakai, refund tidak bisa dihapus.");
                        }
                        $stock->delete();
                        $qty = 1;
                    } else {
                        $qty = (int) $item->qty;
                        $stock = Stock::where('product_id', $item->product_id)->lockForUpdate()->first();
                        if (! $stock || (int) $stock->qty < $qty) {
                            throw new \RuntimeException("Stok {$product->name} tidak cukup untuk membalik refund (butuh {$qty}), refund tidak bisa dihapus.");
                        }
                        $stock->qty -= $qty;
                        $stock->save();
                    }

                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'user_id' => auth()->id(),
                        'type' => 'adjustment',
                        'reference_type' => Refund::class,
                        'reference_id' => $refund->id,
                        'qty_in' => 0,
                        'qty_out' => $qty,
                        'balance' => (int) Stock::where('product_id', $item->product_id)->sum('qty'),
                        'notes' => "Pembatalan refund {$refund->code} (dihapus) - {$product->name}",
                    ]);
                }

                if ($refund->kas_id && ($kas = Kas::lockForUpdate()->find($refund->kas_id))) {
                    $kas->nominal -= (float) $refund->total;
                    $kas->save();
                }

                $refund->refundItems()->delete();
                $refund->delete();
            });
        } catch (\RuntimeException $e) {
            return redirect(route('refund.index'))->with('toast_error', $e->getMessage());
        }

        return redirect(route('refund.index'))->with('toast_success', 'Refund dihapus, stok & kas dikoreksi.');
    }
}