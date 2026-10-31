<?php

/*
| Pengaturan hapus-dengan-persetujuan (Tahap 4B).
| Admin (pusat) yang menghapus -> dibuat permintaan, data tetap ada sampai
| superadmin menyetujui. Superadmin menghapus langsung. Role lain tidak dicegat
| (tetap diatur middleware `role:` masing-masing rute).
*/

return [

    // Alasan wajib diisi admin. Nyalakan (true) setelah <x-delete-button> (4B.4) terpasang
    // di semua tombol Hapus; sebelum itu tombol lama tidak punya kolom alasan.
    'require_reason' => env('DELETION_REQUIRE_REASON', false),

    // Rute NON-DELETE yang juga harus dicegat (menghapus lewat POST/GET).
    'extra_routes' => [
        'cart.destroy',
        'pembelian.stock.destroy',   // GET /pembelian/{id}/destroy (diganti DELETE di 4C.4)
    ],

    // Rute yang tidak boleh dilakukan admin sama sekali (bukan diajukan).
    'forbidden_routes' => [
        'profile.destroy',           // hapus akun sendiri
    ],

    // Rute yang tidak dicegat sama sekali.
    'exempt_routes' => [],

    // Nama modul di halaman Permintaan Hapus: awalan nama rute => label.
    'modules' => [
        'admin'            => 'User / Admin',
        'category-product' => 'Kategori',
        'product'          => 'Produk',
        'supplier'         => 'Supplier',
        'customer'         => 'Customer',
        'salesman'         => 'Salesman',
        'bank'             => 'Bank',
        'kas'              => 'Kas',
        'payment'          => 'Payment Method',
        'outlet'           => 'Outlet',
        'slider'           => 'Slider',
        'pengeluaran'      => 'Pengeluaran',
        'outlet-prices'    => 'Master Harga Jual',
        'voucher'          => 'Voucher',
        'promotion'        => 'Promosi',
        'campaign'         => 'Campaign',
        'penjualan'        => 'Penjualan',
        'pembelian'        => 'Pembelian / PO',
        'request-orders'   => 'Outlet Minta Gudang',
        'delivery-orders'  => 'Pengiriman Toko',
        'picking-lists'    => 'Picking List',
        'refund'           => 'Retur',
        'refundPembelian'  => 'Retur Pembelian',
        'refundPenjualan'  => 'Ganti Barang Penjualan',
        'stock'            => 'Stok Gudang',
        'owner-stocks'     => 'Stok Toko',
        'outlet-purchases' => 'Belanja Langsung',
        'cart'             => 'Keranjang',
    ],

    // Kolom yang dipakai untuk membentuk label & snapshot (urutan prioritas).
    'label_fields' => ['code', 'invoice', 'invoice_number', 'name', 'title', 'username', 'barcode'],
];