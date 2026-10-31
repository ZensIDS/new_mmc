<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tahap 4B.1 — hanya MENAMBAH tabel baru; tidak menyentuh tabel lama.
 * PRODUKSI: backup database dulu. Rollback: php artisan migrate:rollback --step=1
 * (hanya membuang tabel ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deletion_requests')) {
            return;
        }

        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('module', 60)->index();
            $table->string('target_type')->nullable();          // class model (diambil server, bukan dari input)
            $table->string('target_id', 64)->nullable();
            $table->string('target_label', 255);                // snapshot teks mudah dibaca
            $table->json('target_snapshot')->nullable();        // data penting saat diajukan
            $table->text('reason')->nullable();
            // pending | approved | rejected | failed
            $table->string('status', 20)->default('pending')->index();
            // Terisi hanya saat pending -> unique = satu target hanya boleh punya satu request pending.
            $table->string('pending_key', 64)->nullable()->unique();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('route_name', 150);
            $table->json('route_params')->nullable();           // {param: {class, value}}
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_requests');
    }
};