<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Tahap 0.3 — hanya MENAMBAH satu index, tidak mengubah/menghapus apa pun.
 *
 * Sudah ada sebelumnya (tidak dibuat ulang):
 *   penjualans (outlet_id, created_at)
 *   penjualans (cashier_session_id, created_at)
 *   penjualans (cashier_shift_id, created_at)
 *
 * Ditambah: penjualans (created_at) — untuk query lintas outlet
 * (dashboard & halaman Penjualan saat filter outlet = semua).
 *
 * PRODUKSI: backup database dulu, jalankan di jam sepi.
 */
return new class extends Migration
{
    private string $indexName = 'penjualans_created_at_index';

    public function up(): void
    {
        if (! Schema::hasTable('penjualans') || $this->indexExists()) {
            return;
        }

        Schema::table('penjualans', function (Blueprint $table) {
            $table->index('created_at', $this->indexName);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('penjualans') && $this->indexExists()) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->dropIndex($this->indexName);
            });
        }
    }

    private function indexExists(): bool
    {
        return count(DB::select(
            'SHOW INDEX FROM `penjualans` WHERE Key_name = ?',
            [$this->indexName]
        )) > 0;
    }
};