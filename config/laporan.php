<?php

/*
|--------------------------------------------------------------------------
| Katalog Laporan (satu sumber kebenaran)
|--------------------------------------------------------------------------
| Dipakai oleh: sidebar, halaman index Laporan Gudang & Laporan Outlet,
| modal laporan, dan hak akses per role. Menambah/mengubah laporan
| cukup dilakukan di file ini.
|
| Field per laporan:
|   title, desc, icon (Font Awesome 4), roles, xls (nama route Excel),
|   pdf (nama route PDF), fields: date | outlet | kasir | rafaksi,
|   max_days (opsional; null = tanpa batas, kosong = ikut default global).
*/

$gudangFull = ['superadmin', 'admin', 'admin-gudang', 'owner'];
$pusat      = ['superadmin', 'admin', 'owner'];
$outletRole = ['superadmin', 'admin', 'admin-gudang', 'staff-outlet', 'owner'];

return [

    // PDF disembunyikan sementara (K12). Route & view PDF TIDAK dihapus.
    // Nyalakan lagi lewat .env: LAPORAN_SHOW_PDF=true
    'show_pdf' => env('LAPORAN_SHOW_PDF', false),

    // Batas rentang tanggal (hari) di modal. null = tanpa batas.
    // Sengaja longgar dulu; diperketat setelah Excel dibuat ringan (Tahap 5).
    'max_range_days' => 366,

    'kinds' => [
        'gudang' => [
            'title'    => 'Laporan Gudang',
            'subtitle' => 'Pembelian, stok, pengiriman, dan retur gudang',
            'icon'     => 'fa-building',
            'route'    => 'laporan.index',
        ],
        'outlet' => [
            'title'    => 'Laporan Outlet',
            'subtitle' => 'Penjualan, stok, retur, dan rafaksi outlet',
            'icon'     => 'fa-home',
            'route'    => 'laporan.outlet.index',
        ],
    ],

    'groups' => [

        'gudang' => [
            'pembelian' => [
                'title' => 'Pembelian & Penerimaan', 'icon' => 'fa-shopping-cart', 'color' => 'blue',
                'reports' => [
                    'po' => [
                        'title' => 'Laporan PO', 'icon' => 'fa-file-text-o',
                        'desc'  => 'Daftar Purchase Order (PO) gudang pada periode terpilih.',
                        'roles' => ['superadmin', 'admin', 'admin-gudang'],
                        'xls'   => 'laporan.export.po', 'pdf' => 'laporan.pdf.po', 'fields' => ['date'],
                    ],
                    'pembelian' => [
                        'title' => 'Laporan Pembelian Barang', 'icon' => 'fa-shopping-cart',
                        'desc'  => 'Rekap pembelian barang dari supplier pada periode terpilih.',
                        'roles' => $pusat,
                        'xls'   => 'laporan.export.pembelian', 'pdf' => 'laporan.pdf.pembelian', 'fields' => ['date'],
                    ],
                    'penerimaan' => [
                        'title' => 'Laporan Penerimaan Barang', 'icon' => 'fa-inbox',
                        'desc'  => 'Barang yang diterima gudang dari supplier pada periode terpilih.',
                        'roles' => $gudangFull,
                        'xls'   => 'laporan.export.penerimaan', 'pdf' => 'laporan.pdf.penerimaan', 'fields' => ['date'],
                    ],
                ],
            ],
            'stok' => [
                'title' => 'Stok & Opname', 'icon' => 'fa-cubes', 'color' => 'green',
                'reports' => [
                    'stok' => [
                        'title' => 'Laporan Stok Barang', 'icon' => 'fa-cubes',
                        'desc'  => 'Posisi stok barang gudang saat ini.',
                        'roles' => $gudangFull,
                        'xls'   => 'laporan.stock', 'pdf' => 'laporan.pdf.stok', 'fields' => [],
                    ],
                    'opname' => [
                        'title' => 'Laporan Stok Opname & Adjusment', 'icon' => 'fa-balance-scale',
                        'desc'  => 'Hasil stok opname dan penyesuaian (adjustment) stok pada periode terpilih.',
                        'roles' => $gudangFull,
                        'xls'   => 'laporan.stock-opname', 'pdf' => 'laporan.pdf.opname', 'fields' => ['date'],
                    ],
                    'masuk' => [
                        'title' => 'Laporan Barang Masuk', 'icon' => 'fa-arrow-down',
                        'desc'  => 'Barang yang masuk ke gudang pada periode terpilih.',
                        'roles' => $pusat,
                        'xls'   => 'laporan.export.barang-masuk', 'pdf' => 'laporan.pdf.barang-masuk', 'fields' => ['date'],
                    ],
                    'keluar' => [
                        'title' => 'Laporan Barang Keluar', 'icon' => 'fa-arrow-up',
                        'desc'  => 'Barang yang keluar dari gudang pada periode terpilih.',
                        'roles' => $pusat,
                        'xls'   => 'laporan.export.barang-keluar', 'pdf' => 'laporan.pdf.barang-keluar', 'fields' => ['date'],
                    ],
                    'pergerakan' => [
                        'title' => 'Laporan Pergerakan & Kebutuhan Stok', 'icon' => 'fa-line-chart',
                        'desc'  => 'Pergerakan stok dan perkiraan kebutuhan stok per barang.',
                        'roles' => $pusat,
                        'xls'   => 'laporan.export.pergerakan', 'pdf' => 'laporan.pdf.pergerakan', 'fields' => [],
                    ],
                ],
            ],
            'distribusi' => [
                'title' => 'Permintaan & Pengiriman', 'icon' => 'fa-truck', 'color' => 'orange',
                'reports' => [
                    'pr' => [
                        'title' => 'Laporan PR (Permintaan Outlet)', 'icon' => 'fa-file-text-o',
                        'desc'  => 'Permintaan barang (PR) dari outlet ke gudang pada periode terpilih.',
                        'roles' => ['superadmin', 'admin', 'staff-outlet'],
                        'xls'   => 'laporan.export.pr', 'pdf' => 'laporan.pdf.pr', 'fields' => ['date'],
                    ],
                    'pengiriman' => [
                        'title' => 'Laporan Pengiriman Barang', 'icon' => 'fa-truck',
                        'desc'  => 'Pengiriman barang dari gudang ke outlet pada periode terpilih.',
                        'roles' => $gudangFull,
                        'xls'   => 'laporan.export.pengiriman', 'pdf' => 'laporan.pdf.pengiriman', 'fields' => ['date'],
                    ],
                    'picking' => [
                        'title' => 'Laporan Picking & Packing', 'icon' => 'fa-list-ol',
                        'desc'  => 'Daftar picking dan packing barang untuk pengiriman pada periode terpilih.',
                        'roles' => $gudangFull,
                        'xls'   => 'laporan.export.picking', 'pdf' => 'laporan.pdf.picking', 'fields' => ['date'],
                    ],
                ],
            ],
            'retur' => [
                'title' => 'Retur', 'icon' => 'fa-undo', 'color' => 'red',
                'reports' => [
                    'retur_supplier' => [
                        'title' => 'Laporan Retur Ke Supplier', 'icon' => 'fa-undo',
                        'desc'  => 'Retur barang dari gudang ke supplier pada periode terpilih.',
                        'roles' => $gudangFull,
                        'xls'   => 'laporan.retur-supplier', 'pdf' => 'laporan.pdf.retur-supplier', 'fields' => ['date'],
                    ],
                    'retur_outlet' => [
                        'title' => 'Laporan Retur Outlet', 'icon' => 'fa-undo',
                        'desc'  => 'Retur barang dari outlet ke gudang pada periode terpilih.',
                        'roles' => ['superadmin', 'admin', 'staff-outlet', 'owner'],
                        'xls'   => 'laporan.retur-outlet', 'pdf' => 'laporan.pdf.retur-outlet', 'fields' => ['date'],
                    ],
                ],
            ],
            'aktivitas' => [
                'title' => 'Aktivitas & Analisis', 'icon' => 'fa-tasks', 'color' => 'purple',
                'reports' => [
                    'aktifitas' => [
                        'title' => 'Laporan Aktivitas Gudang', 'icon' => 'fa-tasks',
                        'desc'  => 'Rekap aktivitas operasional gudang pada periode terpilih.',
                        'roles' => $pusat,
                        'xls'   => 'laporan.export.aktifitas', 'pdf' => 'laporan.pdf.aktifitas', 'fields' => ['date'],
                    ],
                ],
            ],
        ],

        'outlet' => [
            'penjualan' => [
                'title' => 'Penjualan', 'icon' => 'fa-shopping-cart', 'color' => 'green',
                'reports' => [
                    'penjualan' => [
                        'title' => 'Laporan Penjualan', 'icon' => 'fa-shopping-cart',
                        'desc'  => 'Filter periode, outlet, dan akun kasir, dengan rekap payment method, bon, dan setoran akhir.',
                        'roles' => $outletRole,
                        'xls'   => 'laporan.outlet.penjualan', 'pdf' => 'laporan.pdf.outlet.penjualan',
                        'fields' => ['date', 'outlet', 'kasir'],
                    ],
                ],
            ],
            'stok' => [
                'title' => 'Stok', 'icon' => 'fa-cubes', 'color' => 'blue',
                'reports' => [
                    'minimal_stock' => [
                        'title' => 'Minimal Stock & Alokasi PO', 'icon' => 'fa-line-chart',
                        'desc'  => 'Rata-rata penjualan 3 bulan x faktor frekuensi PO. Menampilkan status out_of_stock dan saran qty PO.',
                        'roles' => $outletRole,
                        'xls'   => 'laporan.outlet.minimal-stock', 'pdf' => 'laporan.pdf.outlet.minimal-stock',
                        'fields' => ['outlet'],
                    ],
                    'all_stock' => [
                        'title' => 'Laporan All Stock', 'icon' => 'fa-cubes',
                        'desc'  => 'Stock berdasarkan barcode produk, bukan SKU, termasuk HPP, HPP + Pajak, dan rekap persediaan.',
                        'roles' => $outletRole,
                        'xls'   => 'laporan.outlet.all-stock', 'pdf' => 'laporan.pdf.outlet.all-stock',
                        'fields' => ['outlet'],
                    ],
                ],
            ],
            'retur' => [
                'title' => 'Retur & Rafaksi', 'icon' => 'fa-exchange', 'color' => 'orange',
                'reports' => [
                    'rafaksi' => [
                        'title' => 'Laporan Rafaksi', 'icon' => 'fa-tags',
                        'desc'  => 'Penjualan produk pada periode rafaksi dengan diskon per unit dan total diskon.',
                        'roles' => $outletRole,
                        'xls'   => 'laporan.outlet.rafaksi', 'pdf' => 'laporan.pdf.outlet.rafaksi',
                        'fields' => ['date', 'outlet', 'rafaksi'],
                    ],
                    'retur' => [
                        'title' => 'Laporan Retur', 'icon' => 'fa-exchange',
                        'desc'  => 'Transaksi retur serta barang yang diretur dan barang penggantinya.',
                        'roles' => $outletRole,
                        'xls'   => 'laporan.outlet.retur', 'pdf' => 'laporan.pdf.outlet.retur',
                        'fields' => ['date', 'outlet'],
                    ],
                ],
            ],
        ],
    ],
];