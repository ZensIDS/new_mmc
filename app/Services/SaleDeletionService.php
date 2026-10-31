<?php

namespace App\Services;

use App\Models\CashierSession;
use App\Models\OwnerStock;
use App\Models\Penjualan;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Tahap 4C.0 — hapus Penjualan (tetap soft delete, K18) DENGAN koreksi:
 *   1. stok outlet dikembalikan per batch (OwnerStock) + movement `in` di Kartu Stok Toko,
 *   2. sesi kasir yang sudah closing dihitung ulang (K22) dan nilai sebelum/sesudah dicatat,
 *   3. semuanya dalam SATU transaksi: gagal di tengah -> tidak ada yang berubah.
 *
 * Dipakai bersama oleh hapus langsung superadmin dan eksekusi ACC (controller yang sama).
 */
class SaleDeletionService
{
    public function __construct(private OutletStockService $stock)
    {
    }

    public function delete(Penjualan $penjualan, ?User $by = null): void
    {
        $by ??= auth()->user();

        DB::transaction(function () use ($penjualan, $by) {
            // Kunci nota supaya dua eksekusi bersamaan tidak mengembalikan stok dua kali.
            $sale = Penjualan::whereKey($penjualan->id)->lockForUpdate()->first();
            if (! $sale) {
                throw new RuntimeException('Nota sudah terhapus.');
            }

            if ($sale->refundPenjualans()->exists()) {
                throw new RuntimeException(
                    "Nota {$sale->code} punya retur/ganti barang sehingga stoknya tidak bisa dibalik otomatis. "
                    .'Tolak permintaan ini dan koreksi lewat Stock Opname, atau hubungi pengembang.'
                );
            }

            $restored = $this->restoreStock($sale, $by);
            $sessionChange = $this->recalculateClosedSession($sale);

            $sale->delete();

            $this->log($sale, $by, $restored, $sessionChange);
        });
    }

    /** @return array<int, array{owner_stock_id:int, product_id:int, qty:int}> */
    private function restoreStock(Penjualan $sale, ?User $by): array
    {
        // Sumber kebenaran = ledger toko: yang keluar dikurangi yang sudah pernah dikembalikan
        // dengan referensi nota yang sama (aman bila dijalankan ulang).
        $rows = StockMovement::query()
            ->where('reference_type', Penjualan::class)
            ->where('reference_id', $sale->id)
            ->whereNotNull('owner_stock_id')
            ->selectRaw('owner_stock_id, product_id, SUM(qty_out) - SUM(qty_in) AS net')
            ->groupBy('owner_stock_id', 'product_id')
            ->get()
            ->map(fn ($r) => ['owner_stock_id' => (int) $r->owner_stock_id, 'product_id' => (int) $r->product_id, 'qty' => (int) $r->net]);

        // Nota lama tanpa movement: pakai item nota.
        if ($rows->isEmpty()) {
            $rows = $sale->items()
                ->whereNotNull('owner_stock_id')
                ->get()
                ->groupBy('owner_stock_id')
                ->map(fn ($g, $id) => ['owner_stock_id' => (int) $id, 'product_id' => (int) $g->first()->product_id, 'qty' => (int) $g->sum('qty')])
                ->values();
        }

        $done = [];
        foreach ($rows->where('qty', '>', 0) as $row) {
            $ownerStock = OwnerStock::withTrashed()->find($row['owner_stock_id']);
            if (! $ownerStock || $ownerStock->trashed()) {
                throw new RuntimeException("Batch stok outlet #{$row['owner_stock_id']} sudah tidak ada, stok tidak bisa dikembalikan.");
            }

            $this->stock->restore(
                $ownerStock,
                $row['qty'],
                Penjualan::class,
                $sale->id,
                $by,
                "Pembatalan penjualan {$sale->code} (dihapus)"
            );
            $done[] = $row;
        }

        return $done;
    }

    /** @return array{session_id:int, before:array, after:array}|null */
    private function recalculateClosedSession(Penjualan $sale): ?array
    {
        if (! $sale->cashier_session_id) {
            return null;
        }

        $session = CashierSession::whereKey($sale->cashier_session_id)->lockForUpdate()->first();
        if (! $session || $session->status !== 'closed') {
            return null; // sesi masih terbuka: ringkasan dihitung langsung saat closing
        }

        $before = $this->snapshot($session);

        // Hitung tanpa nota ini (ringkasan memakai relasi ber-soft-delete; nota ini dihapus sesudahnya).
        $cashSales = (float) $session->cashSales()
            ->where('penjualans.id', '!=', $sale->id)
            ->sum(DB::raw('COALESCE(grand_total, total, 0)'));
        $expected = $cashSales + (float) $session->cash_in - (float) $session->cash_out;

        // Uang fisik/closing tidak diubah; hanya hasil hitungan sistem & selisihnya.
        $session->update([
            'cash_sales' => $cashSales,
            'expected_cash' => $expected,
            'discrepancy' => (float) $session->closing_cash - $expected,
        ]);

        return ['session_id' => $session->id, 'before' => $before, 'after' => $this->snapshot($session->fresh())];
    }

    private function snapshot(CashierSession $s): array
    {
        return [
            'cash_sales' => (float) $s->cash_sales,
            'expected_cash' => (float) $s->expected_cash,
            'discrepancy' => (float) $s->discrepancy,
        ];
    }

    private function log(Penjualan $sale, ?User $by, array $restored, ?array $sessionChange): void
    {
        if (! function_exists('activity')) {
            return;
        }

        try {
            activity('Penjualan')
                ->causedBy($by)
                ->performedOn($sale)
                ->withProperties([
                    'code' => $sale->code,
                    'grand_total' => (float) $sale->grand_total,
                    'stok_dikembalikan' => $restored,
                    'closing_kasir' => $sessionChange,
                ])
                ->log('Penjualan dihapus, stok dikembalikan'.($sessionChange ? ' & closing kasir dihitung ulang' : ''));
        } catch (Throwable $e) {
            report($e); // jejak audit tidak boleh menggagalkan proses utama
        }
    }
}