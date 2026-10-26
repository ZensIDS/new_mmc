<?php

/*
|--------------------------------------------------------------------------
| Definisi Role (satu sumber kebenaran)
|--------------------------------------------------------------------------
| Role disimpan sebagai string di kolom users.role. File ini hanya
| mengelompokkan role supaya pengecekan di Blade, route, dan controller
| tidak lagi menulis daftar role satu per satu.
|
| Catatan: `admin` (Admin Pusat) BERBEDA dengan `admin-gudang`.
*/

return [

    // Semua role yang dikenal sistem.
    'all' => [
        'superadmin',
        'admin',
        'admin-gudang',
        'owner',
        'staff-outlet',
        'kasir',
        'customer',
    ],

    // Label tampilan (dropdown, badge, dll).
    'labels' => [
        'superadmin'   => 'Superadmin',
        'admin'        => 'Admin (Pusat)',
        'admin-gudang' => 'Admin Gudang',
        'owner'        => 'Owner',
        'staff-outlet' => 'Staff Outlet',
        'kasir'        => 'Kasir',
        'customer'     => 'Customer',
    ],

    // Akses penuh ke seluruh menu/fitur (admin = superadmin, kecuali hal-hal di bawah).
    'full_access' => ['superadmin', 'admin'],

    // Boleh menghapus data langsung tanpa persetujuan.
    'delete_direct' => ['superadmin'],

    // Boleh menyetujui/menolak permintaan hapus.
    'delete_approver' => ['superadmin'],

    // Role yang boleh dibuat/diubah/dihapus oleh `admin` lewat menu kelola user.
    // `superadmin` sengaja tidak ada di sini (admin tidak boleh mengelola superadmin).
    'manageable_by_admin' => [
        'admin',
        'admin-gudang',
        'owner',
        'staff-outlet',
        'kasir',
    ],

    // Role yang melihat dashboard penjualan (admin-gudang tetap dashboard gudang).
    'sales_dashboard' => [
        'superadmin',
        'admin',
        'owner',
        'staff-outlet',
        'kasir',
    ],
];