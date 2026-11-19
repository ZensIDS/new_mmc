<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1) Gabungkan baris keranjang ganda (user + outlet + produk yang sama) menjadi satu baris.
        $groups = DB::table('user_cart')
            ->select('user_id', 'outlet_id', 'product_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(qty) as sum_qty'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'outlet_id', 'product_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($groups as $group) {
            DB::transaction(function () use ($group) {
                DB::table('user_cart')->where('id', $group->keep_id)->update(['qty' => (int) $group->sum_qty]);
                DB::table('user_cart')
                    ->where('user_id', $group->user_id)
                    ->where('product_id', $group->product_id)
                    ->when(
                        $group->outlet_id === null,
                        fn ($query) => $query->whereNull('outlet_id'),
                        fn ($query) => $query->where('outlet_id', $group->outlet_id)
                    )
                    ->where('id', '!=', $group->keep_id)
                    ->delete();
            });
        }

        // 2) Kunci di level database: satu produk hanya boleh satu baris per user per outlet.
        Schema::table('user_cart', function ($table) {
            $table->unique(['user_id', 'outlet_id', 'product_id'], 'user_cart_user_outlet_product_unique');
        });
    }

    public function down()
    {
        Schema::table('user_cart', function ($table) {
            $table->dropUnique('user_cart_user_outlet_product_unique');
        });
    }
};