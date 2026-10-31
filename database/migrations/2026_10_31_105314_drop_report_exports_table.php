<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antrean unduhan laporan (Tahap 5.9) dibatalkan: tabel tidak dipakai lagi.
     * Aman dijalankan walau tabel belum pernah dibuat.
     */
    public function up()
    {
        Schema::dropIfExists('report_exports');
    }

    public function down()
    {
        // Sengaja kosong: fitur antrean unduhan sudah dihapus.
    }
};