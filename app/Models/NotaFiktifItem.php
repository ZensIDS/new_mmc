<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaFiktifItem extends Model
{
    protected $table = 'nota_fiktif_items';

    protected $fillable = ['nota_fiktif_id', 'product_id', 'nama', 'satuan', 'qty', 'harga', 'subtotal'];

    protected $casts = [
        'qty' => 'float',
        'harga' => 'float',
        'subtotal' => 'float',
    ];

    public function nota()
    {
        return $this->belongsTo(NotaFiktif::class, 'nota_fiktif_id');
    }
}