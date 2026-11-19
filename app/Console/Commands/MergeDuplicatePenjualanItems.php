<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Merapikan nota lama yang produknya tampil ganda (akibat baris keranjang kembar).
 *
 * Aturan keamanan:
 *  - Hanya menggabungkan baris dalam nota yang SAMA dengan product, batch (owner_stock_id),
 *    stock, harga, harga dasar, HPP, dan serial yang IDENTIK. Baris beda batch/harga tidak disentuh.
 *  - Baris yang memakai promo (promotion_discount > 0 / promotion_details) dilewati.
 *  - Baris duplikat di-soft-delete (bukan dihapus permanen); retur yang menunjuk ke
 *    baris duplikat dipindahkan ke baris yang dipertahankan.
 *  - Total qty dan total subtotal per nota diverifikasi sebelum & sesudah; kalau beda, dibatalkan.
 *  - Stok / stock_movements TIDAK diubah (qty total tetap sama, jadi stok sudah benar).
 *
 * Pemakaian:
 *   php artisan penjualan:merge-duplicate-items                       (dry-run, semua nota)
 *   php artisan penjualan:merge-duplicate-items --penjualan=184       (dry-run, 1 nota)
 *   php artisan penjualan:merge-duplicate-items --penjualan=184 --apply
 *   php artisan penjualan:merge-duplicate-items --from=2026-11-01 --apply
 */
class MergeDuplicatePenjualanItems extends Command
{
    protected $signature = 'penjualan:merge-duplicate-items
        {--apply : Jalankan perubahan sungguhan (tanpa ini hanya simulasi)}
        {--penjualan=* : ID penjualan tertentu (boleh diulang)}
        {--from= : Hanya nota dengan created_at >= tanggal ini (YYYY-MM-DD)}
        {--to= : Hanya nota dengan created_at <= tanggal ini (YYYY-MM-DD)}';

    protected $description = 'Gabungkan baris item nota yang duplikat (produk, batch, dan harga sama) dalam satu nota';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'MODE: APPLY (perubahan akan disimpan)' : 'MODE: DRY-RUN (tidak ada yang diubah)');

        $query = DB::table('penjualan_items')
            ->join('penjualans', 'penjualans.id', '=', 'penjualan_items.penjualan_id')
            ->whereNull('penjualan_items.deleted_at')
            ->groupBy('penjualan_items.penjualan_id', 'penjualan_items.product_id')
            ->havingRaw('COUNT(*) > 1');

        if ($ids = array_filter((array) $this->option('penjualan'))) {
            $query->whereIn('penjualan_items.penjualan_id', $ids);
        }
        if ($from = $this->option('from')) {
            $query->whereDate('penjualans.created_at', '>=', $from);
        }
        if ($to = $this->option('to')) {
            $query->whereDate('penjualans.created_at', '<=', $to);
        }

        $penjualanIds = $query->pluck('penjualan_items.penjualan_id')->unique()->values();

        if ($penjualanIds->isEmpty()) {
            $this->info('Tidak ada nota dengan produk ganda.');

            return self::SUCCESS;
        }

        $this->line("Nota dengan produk ganda: {$penjualanIds->count()}");

        $logFile = storage_path('logs/merge-duplicate-items-'.now()->format('Ymd_His').'.jsonl');
        $mergedGroups = 0;
        $skippedGroups = 0;
        $differentGroups = 0;
        $failed = 0;

        foreach ($penjualanIds as $penjualanId) {
            try {
                $result = $apply
                    ? DB::transaction(fn () => $this->processInvoice((int) $penjualanId, true, $logFile))
                    : $this->processInvoice((int) $penjualanId, false, $logFile);

                $mergedGroups += $result['merged'];
                $skippedGroups += $result['skipped'];
                $differentGroups += $result['different'];
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Nota #{$penjualanId} DIBATALKAN: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Grup digabung: {$mergedGroups} | Dilewati (promo): {$skippedGroups} | Tidak identik (tidak digabung): {$differentGroups} | Nota gagal: {$failed}");
        if ($apply && $mergedGroups > 0) {
            $this->line("Log rollback: {$logFile}");
        }
        if (! $apply) {
            $this->warn('Ini hanya simulasi. Tambahkan --apply untuk menjalankan.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processInvoice(int $penjualanId, bool $apply, string $logFile): array
    {
        $rowsQuery = DB::table('penjualan_items')
            ->where('penjualan_id', $penjualanId)
            ->whereNull('deleted_at')
            ->orderBy('id');
        if ($apply) {
            $rowsQuery->lockForUpdate();
        }
        $rows = $rowsQuery->get();

        $beforeQty = (float) $rows->sum('qty');
        $beforeSubtotal = (float) $rows->sum('subtotal');
        $code = DB::table('penjualans')->where('id', $penjualanId)->value('code');

        $groups = $rows->groupBy(fn ($row) => $this->groupKey($row))->filter(fn ($group) => $group->count() > 1);

        $merged = 0;
        $skipped = 0;
        $different = 0;

        // Produk yang muncul >1 baris tetapi tidak ada satu pun pasangan yang identik:
        // tampilkan field mana yang berbeda supaya bisa dinilai manual.
        foreach ($rows->groupBy('product_id')->filter(fn ($g) => $g->count() > 1) as $productRows) {
            $identical = $productRows->groupBy(fn ($row) => $this->groupKey($row))->filter(fn ($g) => $g->count() > 1);
            if ($identical->isNotEmpty()) {
                continue;
            }
            $different++;
            $label = DB::table('products')->where('id', $productRows->first()->product_id)->value('name') ?? 'product #'.$productRows->first()->product_id;
            $this->warn("  {$code}: TIDAK IDENTIK, tidak digabung - {$label}");
            foreach ($productRows as $row) {
                $this->line(sprintf(
                    '     item#%d qty=%s owner_stock_id=%s stock_id=%s price=%s base_price=%s hpp=%s serial=%s promo=%s',
                    $row->id,
                    $row->qty,
                    $row->owner_stock_id ?? 'null',
                    $row->stock_id ?? 'null',
                    $row->price,
                    $row->base_price ?? 'null',
                    $row->hpp ?? 'null',
                    $row->serial_number ?? 'null',
                    $this->usesPromotion($row) ? 'ya' : 'tidak'
                ));
            }
        }

        foreach ($groups as $group) {
            $first = $group->first();
            $label = DB::table('products')->where('id', $first->product_id)->value('name') ?? "product #{$first->product_id}";

            if ($group->contains(fn ($row) => $this->usesPromotion($row))) {
                $skipped++;
                $this->warn("  {$code}: DILEWATI (ada promo) - {$label}");

                continue;
            }

            $keep = $group->first();
            $duplicates = $group->slice(1);
            $newQty = (int) $group->sum('qty');
            $newSubtotal = $group->sum('subtotal');
            $newBaseSubtotal = $group->every(fn ($row) => is_null($row->base_subtotal))
                ? null
                : $group->sum(fn ($row) => (float) $row->base_subtotal);

            $this->line(sprintf(
                '  %s: %s | %d baris (qty %s) -> 1 baris (qty %d)',
                $code,
                $label,
                $group->count(),
                $group->pluck('qty')->implode(' + '),
                $newQty
            ));

            if ($apply) {
                File::append($logFile, json_encode([
                    'penjualan_id' => $penjualanId,
                    'code' => $code,
                    'keep_id' => $keep->id,
                    'original_rows' => $group->values()->all(),
                    'merged_at' => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE).PHP_EOL);

                DB::table('penjualan_items')->where('id', $keep->id)->update([
                    'qty' => $newQty,
                    'subtotal' => $newSubtotal,
                    'base_subtotal' => $newBaseSubtotal,
                    'updated_at' => now(),
                ]);

                $duplicateIds = $duplicates->pluck('id')->all();

                if (Schema::hasTable('refund_penjualan_items')) {
                    DB::table('refund_penjualan_items')
                        ->whereIn('penjualan_item_id', $duplicateIds)
                        ->update(['penjualan_item_id' => $keep->id]);
                }

                DB::table('penjualan_items')->whereIn('id', $duplicateIds)->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $merged++;
        }

        if ($apply && $merged > 0) {
            $after = DB::table('penjualan_items')
                ->where('penjualan_id', $penjualanId)
                ->whereNull('deleted_at')
                ->selectRaw('SUM(qty) AS q, SUM(subtotal) AS s')
                ->first();

            if (abs((float) $after->q - $beforeQty) > 0.0001 || abs((float) $after->s - $beforeSubtotal) > 0.01) {
                throw new RuntimeException(
                    "Total tidak cocok setelah merge (qty {$beforeQty}->{$after->q}, subtotal {$beforeSubtotal}->{$after->s})"
                );
            }
        }

        return ['merged' => $merged, 'skipped' => $skipped, 'different' => $different];
    }

    private function groupKey(object $row): string
    {
        return implode('|', [
            $row->product_id,
            $row->owner_stock_id ?? 'null',
            $row->stock_id ?? 'null',
            number_format((float) $row->price, 4, '.', ''),
            number_format((float) ($row->base_price ?? 0), 4, '.', ''),
            number_format((float) ($row->hpp ?? 0), 4, '.', ''),
            $row->serial_number ?? 'null',
        ]);
    }

    private function usesPromotion(object $row): bool
    {
        if ((float) ($row->promotion_discount ?? 0) > 0) {
            return true;
        }

        $details = $row->promotion_details ?? null;

        return ! in_array($details, [null, '', '[]', 'null'], true);
    }
}