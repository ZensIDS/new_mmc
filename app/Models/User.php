<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use SoftDeletes;

    /** Username tetap untuk customer bawaan "Customer Umum" (default penjualan). */
    public const DEFAULT_CUSTOMER_USERNAME = 'customer-umum';

    protected $fillable = [
        'name',
        'username',
        'role',
        'status',
        'email',
        'alamat',
        'no_telp',
        'password',
        'limit_discount',
        'outlet_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function cart()
    {
        return $this->belongsToMany(Product::class, 'user_cart')
            ->withPivot('qty', 'serial_number', 'stock_id', 'owner_stock_id', 'outlet_id')
            ->withTimestamps();
    }

    public function wishlist()
    {
        return $this->belongsToMany(Product::class, 'user_wishlist')
            ->withPivot('qty', 'name', 'customer_id', 'outlet_id', 'stock_id', 'owner_stock_id')
            ->withTimestamps();
    }

    // public function reviews()
    // {
    //     return $this->hasMany(Review::class);
    // }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    /** Transaksi penjualan milik customer ini (penjualans.customer_id bertipe string). */
    public function penjualans()
    {
        return $this->hasMany(Penjualan::class, 'customer_id');
    }

    public function isDefaultCustomer(): bool
    {
        return $this->role === 'customer' && $this->username === self::DEFAULT_CUSTOMER_USERNAME;
    }

    /**
     * Customer Umum: dibuat otomatis kalau belum ada, supaya penjualan tanpa
     * customer yang dipilih selalu tercatat ke satu customer yang sama.
     */
    public static function defaultCustomer(): self
    {
        return static::withTrashed()
            ->where('role', 'customer')
            ->where('username', self::DEFAULT_CUSTOMER_USERNAME)
            ->first()
            ?->restoreIfTrashed()
            ?? static::create([
                'name' => 'Umum',
                'username' => self::DEFAULT_CUSTOMER_USERNAME,
                'role' => 'customer',
                'status' => 'active',
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40)),
            ]);
    }

    protected function restoreIfTrashed(): self
    {
        if ($this->trashed()) {
            $this->restore();
        }

        return $this;
    }
}