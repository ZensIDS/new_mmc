<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Saldo terkini satu customer. Nilai `balance` hanya boleh diubah lewat
 * App\Services\CustomerWalletService (selalu bersama entri mutasinya).
 */
class CustomerWallet extends Model
{
    protected $fillable = [
        'customer_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function entries()
    {
        return $this->hasMany(CustomerWalletEntry::class, 'wallet_id')
            ->orderBy('tanggal')
            ->orderBy('id');
    }
}