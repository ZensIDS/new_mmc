<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buku mutasi saldo customer. Baris tidak pernah di-edit atau dihapus
     * (tanpa softDeletes); salah input dikoreksi dengan entri baru.
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_wallet_entries')) {
            return;
        }

        Schema::create('customer_wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('customer_wallets')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20); // topup | pengurangan
            $table->decimal('amount', 15, 2); // selalu positif
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->date('tanggal'); // tanggal mutasi (default hari ini, boleh diubah)
            $table->text('note'); // catatan wajib
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'tanggal', 'id']);
            $table->index(['wallet_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_entries');
    }
};