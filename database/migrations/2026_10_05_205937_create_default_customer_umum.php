<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const USERNAME = 'customer-umum';

    /**
     * Buat "Customer Umum" sebagai customer bawaan, lalu pindahkan penjualan
     * lama yang customer-nya kosong ke customer ini agar ikut terhitung.
     */
    public function up()
    {
        $now = now();

        $umum = DB::table('users')
            ->where('role', 'customer')
            ->where('username', self::USERNAME)
            ->first();

        if ($umum) {
            $id = $umum->id;
            DB::table('users')->where('id', $id)->update(['deleted_at' => null]);
        } else {
            $id = DB::table('users')->insertGetId([
                'name' => 'Umum',
                'username' => self::USERNAME,
                'role' => 'customer',
                'status' => 'active',
                'password' => Hash::make(Str::random(40)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $query = DB::table('penjualans')
            ->where(function ($q) {
                $q->whereNull('customer_id')->orWhere('customer_id', '');
            });

        // Penjualan marketplace (punya transaction) bukan transaksi kasir, jangan disentuh.
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'penjualan_id')) {
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('transactions')
                    ->whereColumn('transactions.penjualan_id', 'penjualans.id');
            });
        }

        $query->update(['customer_id' => (string) $id]);
    }

    public function down()
    {
        $umum = DB::table('users')
            ->where('role', 'customer')
            ->where('username', self::USERNAME)
            ->first();

        if ($umum) {
            DB::table('penjualans')
                ->where('customer_id', (string) $umum->id)
                ->update(['customer_id' => null]);

            DB::table('users')->where('id', $umum->id)->delete();
        }
    }
};