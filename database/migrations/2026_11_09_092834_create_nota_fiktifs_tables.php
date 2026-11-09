<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Nota Fiktif (khusus superadmin) — hanya MENAMBAH 2 tabel baru yang berdiri sendiri.
 * Tidak ada foreign key ke tabel penjualan/stok/kas, jadi tidak mempengaruhi laporan apa pun.
 * Rollback: php artisan migrate:rollback --step=1
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nota_fiktifs')) {
            Schema::create('nota_fiktifs', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->dateTime('tanggal');
                $table->string('outlet_nama');
                $table->text('outlet_alamat')->nullable();
                $table->string('customer_nama')->nullable();
                $table->string('kasir_nama')->nullable();
                $table->string('metode_bayar')->nullable();
                $table->string('referensi')->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('diskon', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->decimal('bayar', 15, 2)->default(0);
                $table->decimal('kembali', 15, 2)->default(0);
                $table->text('catatan')->nullable();
                $table->text('footer')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('nota_fiktif_items')) {
            Schema::create('nota_fiktif_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('nota_fiktif_id')->index();
                $table->unsignedBigInteger('product_id')->nullable(); // hanya referensi, TANPA foreign key
                $table->string('nama');
                $table->string('satuan', 40)->nullable();
                $table->decimal('qty', 12, 2)->default(1);
                $table->decimal('harga', 15, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_fiktif_items');
        Schema::dropIfExists('nota_fiktifs');
    }
};