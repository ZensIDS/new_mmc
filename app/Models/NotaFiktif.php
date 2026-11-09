<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nota fiktif: hanya arsip dokumen. Tidak terhubung ke penjualan, stok, kas, maupun laporan.
 */
class NotaFiktif extends Model
{
    use SoftDeletes;

    protected $table = 'nota_fiktifs';

    protected $fillable = [
        'code', 'tanggal', 'outlet_nama', 'outlet_alamat', 'customer_nama', 'kasir_nama',
        'metode_bayar', 'referensi', 'subtotal', 'diskon', 'total', 'bayar', 'kembali',
        'catatan', 'footer', 'created_by',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'subtotal' => 'float',
        'diskon' => 'float',
        'total' => 'float',
        'bayar' => 'float',
        'kembali' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(NotaFiktifItem::class, 'nota_fiktif_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}