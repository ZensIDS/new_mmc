<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Satu baris mutasi saldo customer (top up / pengurangan).
 * Bersifat append-only: tidak boleh di-update maupun dihapus.
 */
class CustomerWalletEntry extends Model
{
    public const TYPE_TOPUP = 'topup';

    public const TYPE_PENGURANGAN = 'pengurangan';

    protected $fillable = [
        'wallet_id',
        'customer_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'tanggal',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
        'tanggal' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Mutasi saldo tidak boleh diubah. Koreksi dengan entri baru.');
        });

        static::deleting(function () {
            throw new LogicException('Mutasi saldo tidak boleh dihapus. Koreksi dengan entri baru.');
        });
    }

    public function wallet()
    {
        return $this->belongsTo(CustomerWallet::class, 'wallet_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isTopup(): bool
    {
        return $this->type === self::TYPE_TOPUP;
    }

    /** Nominal bertanda: top up positif, pengurangan negatif. */
    public function getSignedAmountAttribute(): float
    {
        return $this->isTopup() ? (float) $this->amount : -(float) $this->amount;
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->isTopup() ? 'Top Up' : 'Pengurangan';
    }
}