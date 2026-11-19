<?php

namespace App\Http\Controllers;

use App\Models\OwnerStock;
use App\Models\OutletPrice;
use App\Models\Product;
use App\Models\User;
use App\Services\PriceCalculator;
use App\Services\PromotionService;
use App\Support\OutletAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request, PriceCalculator $calculator, PromotionService $promotionService)
    {
        $outletId = OutletAccess::id($request);
        $cart = $request->user()->cart()
            ->wherePivot('outlet_id', (string) $outletId)
            ->withPivot('qty', 'serial_number', 'stock_id', 'owner_stock_id', 'outlet_id')
            ->get();

        $allocations = [];
        foreach ($cart as $cartIndex => $item) {
            $ownerStocks = $item->ownerStocks()
                ->where('owner_id', $outletId)
                ->where('qty', '>', 0)
                ->where(function ($expiryQuery) {
                    $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                })
                ->with('stock')
                ->orderBy('created_at')
                ->get();
            $item->availableStock = $item->ownerStocks()
                ->where('owner_id', $outletId)
                ->where('qty', '>', 0)
                ->where(function ($expiryQuery) {
                    $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                })
                ->sum('qty');

            $rule = OutletPrice::with('outlet')->where('outlet_id', $outletId)
                ->where('product_id', $item->id)
                ->currentlyActive()
                ->first();
            $remainingQty = max(1, (int) $item->pivot->qty);
            $firstPrice = null;
            // Harga memakai HPP terbaru untuk semua batch; stok tetap berkurang per batch (FIFO).
            $latestHpp = app(\App\Services\LatestHpp::class)->forProduct((int) $outletId, (int) $item->id);
            foreach ($ownerStocks as $ownerStock) {
                if ($remainingQty <= 0) {
                    break;
                }
                $price = $calculator->calculateItem(
                    $latestHpp,
                    $rule,
                    $item
                );
                $allocatedQty = min($remainingQty, (int) $ownerStock->qty);
                $firstPrice ??= $price;
                $allocations[] = [
                    'cart_index' => $cartIndex,
                    'product' => $item,
                    'ownerStock' => $ownerStock,
                    'qty' => $allocatedQty,
                    'price' => $price,
                    'base_line_total' => (int) ($price['price'] * $allocatedQty),
                    'line_total' => (int) ($price['price'] * $allocatedQty),
                ];
                $remainingQty -= $allocatedQty;
            }
        }

        $activePromotions = $promotionService->activeForOutlet($outletId);
        $promotionResult = $promotionService->apply($allocations, $activePromotions);
        $allocationsByCartItem = collect($promotionResult['allocations'])->groupBy('cart_index');
        foreach ($cart as $cartIndex => $item) {
            $itemAllocations = $allocationsByCartItem->get($cartIndex, collect());
            $baseSubtotal = (int) $itemAllocations->sum('base_line_total');
            $cashierSubtotal = (int) $itemAllocations->sum('line_total');
            $promotionDiscount = (int) $itemAllocations->sum('promotion_discount');
            $firstAllocation = $itemAllocations->first();
            $firstPrice = $firstAllocation['price'] ?? null;

            $item->cashierPrice = $firstPrice;
            $item->cashier_base_subtotal = $baseSubtotal;
            $item->cashier_unit_price = $calculator->money($baseSubtotal / max(1, (int) $item->pivot->qty));
            $item->cashier_subtotal = $cashierSubtotal;
            $item->cashier_promotion_discount = $promotionDiscount;
            $promotionDetails = $itemAllocations
                ->flatMap(fn ($allocation) => collect($allocation['promotion_details'] ?? []));
            $item->cashier_promotions = $promotionDetails
                ->pluck('promotion_name')
                ->unique()
                ->values()
                ->all();
            $item->cashier_promotion_breakdown = $promotionDetails
                ->groupBy(fn ($detail) => $detail['promotion_code'] ?? $detail['promotion_id'])
                ->map(fn ($details) => [
                    'promotion_id' => $details->first()['promotion_id'] ?? null,
                    'promotion_name' => $details->first()['promotion_name'] ?? null,
                    'promotion_code' => $details->first()['promotion_code'] ?? null,
                    'amount' => (int) $details->sum('amount'),
                ])
                ->values()
                ->all();
        }

        return response($cart);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'barcode' => 'required|exists:products,code',
                'outlet_id' => 'required|integer|exists:outlets,id',
            ]);
            $outletId = OutletAccess::id($request);
            $product = Product::where('code', $request->barcode)->firstOrFail();
            $user = $request->user();

            // Seluruh penulisan keranjang satu user diserialkan lewat lock baris user.
            // Tanpa ini, 2 scan berdekatan sama-sama melihat "belum ada di keranjang"
            // lalu sama-sama INSERT, sehingga produk yang sama muncul sebagai 2 baris.
            $error = DB::transaction(function () use ($user, $outletId, $product) {
                $this->lockUserCart($user);

                $stockQty = $this->availableOutletStock($outletId, $product->id);
                $rows = $this->cartRows($user, $outletId, $product->id);
                $currentQty = (int) $rows->sum('qty');

                if ($rows->isNotEmpty()) {
                    if ($stockQty <= $currentQty) {
                        return 'Stok outlet tersedia hanya: ' . $stockQty;
                    }
                    $this->mergeCartRows($rows, $currentQty + 1);

                    return null;
                }

                if ($stockQty < 1) {
                    return 'Produk tidak memiliki stok di outlet ini.';
                }
                $this->insertCartRow($user, $outletId, $product->id, 1);

                return null;
            });

            if ($error) {
                return response(['message' => $error], 400);
            }

            return response('success', 204);
        } catch (Exception $e) {
            report($e);

            return response(['message' => $e->getMessage()], 400);
        }
    }

    public function changeQty(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'qty' => 'required|integer|min:1',
                'outlet_id' => 'required|integer|exists:outlets,id',
            ]);
            $outletId = OutletAccess::id($request);
            $product = Product::findOrFail($request->product_id);
            $user = $request->user();

            $stockQty = $this->availableOutletStock($outletId, $product->id);
            if ($stockQty < $request->qty) {
                return response(['message' => 'Stok outlet tersedia hanya: ' . $stockQty], 400);
            }

            DB::transaction(function () use ($user, $outletId, $product, $request) {
                $this->lockUserCart($user);
                $rows = $this->cartRows($user, $outletId, $product->id);
                if ($rows->isNotEmpty()) {
                    // Sekaligus merapikan baris ganda lama (bila ada) menjadi satu baris.
                    $this->mergeCartRows($rows, (int) $request->qty);
                }
            });

            return response(['success' => true]);
        } catch (Exception $e) {
            report($e);

            return response(['message' => $e->getMessage()], 400);
        }
    }

    public function removeSerial(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'serial_number' => 'required|string',
            'outlet_id' => 'required|integer|exists:outlets,id',
        ]);
        $outletId = OutletAccess::id($request);
        $request->user()->cart()
            ->wherePivot('outlet_id', (string) $outletId)
            ->wherePivot('serial_number', $request->serial_number)
            ->detach($request->product_id);

        return response(['success' => true]);
    }

    public function addToWishlist(Request $request)
    {
        $request->validate([
            'cart' => 'required|array',
            'cart.*.id' => 'required|exists:products,id',
            'cart.*.pivot.qty' => 'required|integer|min:1',
            'cart.*.pivot.owner_stock_id' => 'nullable|exists:owner_stocks,id',
            'outlet_id' => 'required|integer|exists:outlets,id',
            'customer_id' => 'nullable',
            'name' => 'required',
        ]);
        $outletId = OutletAccess::id($request);

        foreach ($request->cart as $item) {
            $product = Product::findOrFail($item['id']);
            $ownerStockId = $item['pivot']['owner_stock_id'] ?? null;
            $request->user()->wishlist()->attach($product->id, [
                'qty' => $item['pivot']['qty'],
                'outlet_id' => $outletId,
                'customer_id' => $request->customer_id,
                'name' => $request->name,
                'owner_stock_id' => $ownerStockId,
            ]);
        }
        $request->user()->cart()->wherePivot('outlet_id', (string) $outletId)->detach();

        return response(['success' => true]);
    }

    public function getWishlist(Request $request, $outlet_id)
    {
        $request->merge(['outlet_id' => $outlet_id]);
        $outletId = OutletAccess::id($request);
        $wishlist = $request->user()->wishlist()
            ->wherePivot('outlet_id', (string) $outletId)
            ->withPivot('name', 'customer_id', 'outlet_id', 'owner_stock_id', 'qty')
            ->get();
        $grouped = $wishlist->groupBy(['pivot.name', 'pivot.customer_id']);

        return response($grouped);
    }

    public function moveToCart(Request $request)
    {
        $request->validate(['name' => 'required', 'customer_id' => 'nullable', 'outlet_id' => 'required']);
        $outletId = OutletAccess::id($request);
        $wishlistItems = $request->user()->wishlist()
            ->wherePivot('outlet_id', (string) $outletId)
            ->wherePivot('name', $request->name)
            ->wherePivot('customer_id', $request->customer_id)
            ->withPivot('owner_stock_id', 'qty')
            ->get();

        foreach ($wishlistItems as $item) {
            $request->user()->wishlist()
                ->wherePivot('product_id', $item->id)
                ->wherePivot('outlet_id', (string) $outletId)
                ->wherePivot('name', $request->name)
                ->detach();
            DB::transaction(function () use ($request, $item, $outletId) {
                $user = $request->user();
                $this->lockUserCart($user);
                $rows = $this->cartRows($user, $outletId, $item->id);
                if ($rows->isNotEmpty()) {
                    // Produk sudah ada di keranjang: gabungkan qty, jangan buat baris baru.
                    $this->mergeCartRows($rows, (int) $rows->sum('qty') + (int) $item->pivot->qty);
                } else {
                    $this->insertCartRow($user, $outletId, $item->id, (int) $item->pivot->qty, $item->pivot->owner_stock_id);
                }
            });
        }

        return response(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['product_id' => 'required|integer|exists:products,id', 'outlet_id' => 'required']);
        $outletId = OutletAccess::id($request);
        $request->user()->cart()
            ->wherePivot('outlet_id', (string) $outletId)
            ->detach($request->product_id);

        return response('success', 204);
    }

    public function empty(Request $request)
    {
        $outletId = OutletAccess::id($request);
        $request->user()->cart()->wherePivot('outlet_id', (string) $outletId)->detach();

        return response('success', 204);
    }

    /**
     * Serialkan semua perubahan keranjang milik satu user (harus dipanggil di dalam transaksi).
     */
    private function lockUserCart($user): void
    {
        User::whereKey($user->getKey())->lockForUpdate()->first();
    }

    private function cartRows($user, $outletId, int $productId)
    {
        return DB::table('user_cart')
            ->where('user_id', $user->getKey())
            ->where('outlet_id', (string) $outletId)
            ->where('product_id', $productId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Pertahankan baris pertama dengan qty baru, hapus baris duplikat lainnya.
     */
    private function mergeCartRows($rows, int $qty): void
    {
        $keep = $rows->first();
        DB::table('user_cart')->where('id', $keep->id)->update(['qty' => $qty, 'updated_at' => now()]);
        $duplicateIds = $rows->skip(1)->pluck('id')->all();
        if ($duplicateIds) {
            DB::table('user_cart')->whereIn('id', $duplicateIds)->delete();
        }
    }

    private function insertCartRow($user, $outletId, int $productId, int $qty, $ownerStockId = null): void
    {
        DB::table('user_cart')->insert([
            'user_id' => $user->getKey(),
            'product_id' => $productId,
            'outlet_id' => (string) $outletId,
            'owner_stock_id' => $ownerStockId,
            'qty' => $qty,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function availableOutletStock($outletId, int $productId): int
    {
        return (int) OwnerStock::where('owner_id', $outletId)
            ->where('product_id', $productId)
            ->where('qty', '>', 0)
            ->where(function ($expiryQuery) {
                $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
            })
            ->sum('qty');
    }
}