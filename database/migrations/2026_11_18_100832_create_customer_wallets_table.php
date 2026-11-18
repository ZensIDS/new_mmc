<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo customer (catatan mutasi, hanya dikelola superadmin).
     * Sengaja di tabel terpisah dari `users` supaya saldo tidak ikut terkirim
     * ke halaman lain yang membaca data customer (mis. popup customer di kasir).
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_wallets')) {
            return;
        }

        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: customer yang punya catatan saldo tidak bisa dihapus permanen.
            $table->foreignId('customer_id')->unique()->constrained('users')->restrictOnDelete();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallets');
    }
};