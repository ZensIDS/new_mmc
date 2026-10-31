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

    /*
    |--------------------------------------------------------------------------
    | Helper role (daftar role ada di config/roles.php)
    |--------------------------------------------------------------------------
    */

    /** Cek apakah role user termasuk salah satu role yang diberikan. */
    public function hasRole(string|array ...$roles): bool
    {
        $roles = collect($roles)->flatten()->all();

        return in_array($this->role, $roles, true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /** Admin Pusat (berbeda dengan `admin-gudang`). */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Akses penuh: superadmin dan admin. */
    public function hasFullAccess(): bool
    {
        return $this->hasRole(config('roles.full_access', ['superadmin', 'admin']));
    }

    /** Boleh hapus langsung tanpa persetujuan. */
    public function canDeleteDirectly(): bool
    {
        return $this->hasRole(config('roles.delete_direct', ['superadmin']));
    }

    /** Boleh menyetujui/menolak permintaan hapus. */
    public function canApproveDeletion(): bool
    {
        return $this->hasRole(config('roles.delete_approver', ['superadmin']));
    }

    /** Admin yang hapusnya harus menunggu persetujuan superadmin. */
    public function mustRequestDeletion(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Boleh mengakses fitur yang diperuntukkan bagi role tertentu.
     * Akses penuh (superadmin & admin) selalu lolos; role lain harus ada di daftar.
     * Dipakai di Blade/controller sebagai pengganti in_array($role, ['superadmin', ...]).
     */
    public function canAccess(string|array ...$roles): bool
    {
        return $this->hasFullAccess() || $this->hasRole(...$roles);
    }

    /** Daftar role yang boleh DIBERIKAN user ini lewat menu kelola user. */
    public function assignableRoles(): array
    {
        if ($this->isSuperadmin()) {
            return array_values(array_diff(config('roles.all', []), ['customer']));
        }

        if ($this->isAdmin()) {
            return config('roles.manageable_by_admin', []);
        }

        return [];
    }

    /** Boleh membuat/mengubah/menghapus akun $target lewat menu kelola user. */
    public function canManageUser(self $target): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        // Admin tidak boleh menyentuh akun superadmin (cegah naik jabatan).
        return $this->isAdmin() && in_array($target->role, config('roles.manageable_by_admin', []), true);
    }

    public function roleLabel(): string
    {
        return config('roles.labels.'.$this->role, ucfirst((string) $this->role));
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