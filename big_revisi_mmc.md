# BIG REVISI MMC — Analisa, Rekomendasi & Todolist

> Status: **DRAFT ANALISA v0.9** — belum ada kode yang diubah. Semua keputusan K1–K24 sudah dijawab; tidak ada pertanyaan terbuka. **Aplikasi sudah PRODUKSI — lihat aturan keselamatan di Bagian 3C.**
> v0.2 sudah diperiksa terhadap kode lengkap (`Archive.zip`: app/, routes, views, migrations). Isi v0.1 yang meleset sudah dikoreksi.

## CARA KERJA (disepakati)

1. **Dikerjakan bertahap** — satu tahap pada satu waktu sesuai Bagian 4; tahap berikutnya baru jalan setelah tahap sebelumnya dicek.
2. **Setiap tahap selesai, kirim hanya file yang dibutuhkan** (yang berubah/baru saja), masing-masing sebagai file card yang bisa diunduh.
3. **Bukan zip, dan tidak ditempel di chat.**
4. **Todolist (Bagian 4) dan Log Progres (Bagian 9) selalu di-update** setiap tahap selesai, dan file `big_revisi_mmc.md` terbaru ikut dikirim.

---

## 0. Sumber & cakupan analisa

- v0.1 dibuat dari zip parsial (hanya views/routes/migrations). **v0.2 memakai `Archive.zip` lengkap**: 40+ controller, 39 class Export, 8 service/support, model, middleware, migrations.
- Belum terbaca penuh dan akan dibaca saat tahapnya tiba: `OutletLaporanService` (710 baris), `LaporanController` (895 baris), `CashierSaleService`, isi `routes/auth.php`, `composer.json` & `config/` (tidak ada di zip — versi paket & timezone diasumsikan, lihat K14).
- Tidak ada kode yang diubah sampai todolist disetujui.

---

## 1. Ringkasan 5 revisi

| # | Revisi | Ukuran | Risiko | Catatan singkat |
|---|--------|--------|--------|-----------------|
| 1 | Dashboard = rekap penjualan, hapus tombol Min Stok | Sedang | Rendah | Library Highcharts sudah terpasang, tinggal data penjualan |
| 2 | Role **admin** + hapus-pending (butuh persetujuan superadmin) | **Besar** | **Tinggi** | Menyentuh 20+ modul hapus, 43 titik cek role, dan efek samping stok/kas |
| 3 | `/penjualan` default hari ini + filter advance + DataTable rapi + cetak rekap | Sedang | Sedang | Halaman sekarang memuat SEMUA penjualan tanpa paginasi |
| 4 | Menu Laporan jadi treeview + index rapi + modal daterange + sembunyikan tombol PDF | Sedang | Rendah | Dua halaman punya kode modal & JS ganda, bisa disatukan |
| 5 | Excel formal rapi + query ringan | **Besar** | Sedang | Beririsan dengan `todolist_export_gudang.md` yang sudah berjalan |

**Urutan pengerjaan yang kuunsulkan:** Fondasi → #4 (menu Laporan, cepat & aman) → #3 (Penjualan) → #1 (Dashboard) → #2 (Role + approval hapus) → #5 (Excel, per kelompok laporan).
Alasan: #2 paling berisiko dan menyentuh paling banyak file, jadi dikerjakan setelah fondasi role rapi dan setelah halaman lain stabil, supaya tidak bentrok. #5 paling banyak file tapi mekanis, cocok dikerjakan bertahap.

---

## 2. Analisa per item

### 2.1 Dashboard — rekap penjualan di awal

**Kondisi sekarang** (`dashboard/index.blade.php`, 638 baris)
- Isinya 100% sudut pandang gudang: Total Stok Gudang, Pending Order, Order Terkirim, Stok Kurang, Inventory Top 5, "Produk Terlaris" (**ini sebenarnya top barang terkirim ke outlet, bukan penjualan**), Pesanan Terbaru, Slow Moving, Deadline PO Supplier.
- Judul "Selamat Datang di **Gudang**".
- Tombol "Pengaturan Min Stok" ada di header (baris 9–15) + modal `#modalMinStockAdj` (baris ±417–497) + JS-nya (baris ±600) — untuk role `admin-gudang` dan `superadmin`.
- Ada cabang khusus `staff-outlet` (2 kartu saja).
- Highcharts 10.3.3 sudah di-load dari CDN.

**Temuan dari kode (v0.2)**
- `DashboardController@index` hanya punya dua cabang: `staff-outlet` (2 kartu) dan semua role lain = dashboard gudang. **Tidak ada satu pun data penjualan** di controller maupun view saat ini.
- Hitungan "Stok Kurang" dilakukan di PHP: seluruh produk ber-`min_stock` di-`get()` lalu di-`map`/`filter` — makin banyak produk makin berat; sebaiknya dipindah ke SQL atau di-cache.
- Stok mendekati kedaluwarsa memakai `whereDate()` (tidak memakai index).
- `App\Support\ReportQuery::betweenDates()` sudah ada (filter tanggal tanpa `whereDate`) → pakai untuk semua query dashboard baru.

**Rekomendasi**
1. Untuk **superadmin (dan admin baru, dan owner — lihat K8)**: urutan dashboard jadi
   - **Baris A – Kartu penjualan:** Omzet hari ini, Omzet bulan ini, Jumlah transaksi hari ini, Rata-rata nilai transaksi, Total diskon+voucher+promo, (opsional) Estimasi laba kotor (butuh HPP per item — cek kolom).
   - **Baris B – Grafik:** Tren penjualan 30 hari (line), Penjualan per outlet (bar), Komposisi metode pembayaran (pie/donut), Penjualan per jam (heatmap sederhana/bar) untuk tahu jam ramai.
   - **Baris C – Tabel/ranking:** Top 10 produk terjual (qty & omzet), Top kasir, Transaksi terbaru, Perbandingan vs kemarin/bulan lalu (panah naik/turun).
   - **Baris D – Gudang (dipindah ke bawah, bisa collapse):** kartu stok/pending order/stok kurang/deadline PO seperti sekarang.
2. Filter periode kecil di atas dashboard (Hari ini / 7 hari / Bulan ini / Custom) + filter outlet. Default: **bulan berjalan** untuk grafik, **hari ini** untuk kartu.
3. **Aturan hitung:** penjualan yang diretur tidak dihitung sebagai penjualan, barang pengganti tetap dihitung (aturan ini sudah tertulis di halaman Laporan Outlet — dashboard harus konsisten dengan laporan, kalau tidak angkanya akan beda dan jadi sumber komplain).
4. Hapus tombol & modal Min Stok dari dashboard. **Rutenya jangan dihapus dulu** (`product.minimum-adjustment.store`) — fiturnya perlu dipindah/tetap ada di tempat lain (usulan: tombol di halaman `/product`). Lihat K9.
5. Performa: agregasi dihitung di SQL (`SUM/COUNT/GROUP BY`) dengan range `created_at >= ? AND < ?`, **bukan** `whereDate()` (mematikan index) dan **bukan** load collection lalu dijumlah di PHP. Hasil boleh di-cache 1–5 menit.

**Usulan terbaik:** Dashboard dipecah jadi partial per blok (`dashboard/_sales.blade.php`, `_gudang.blade.php`) dan datanya diambil lewat satu `DashboardSalesService` supaya mudah dites dan dipakai ulang oleh rekap Penjualan & Laporan. Kartu dirender langsung, grafik di-load via AJAX supaya halaman pertama cepat.

**Risiko:** angka dashboard ≠ angka laporan jika rumus beda → satukan rumus di satu service.

---

### 2.2 Role baru **admin** + hapus berstatus pending

**Kondisi sekarang**
- Role disimpan di kolom string `users.role` (default `customer`), jadi **tidak perlu ubah enum/skema** untuk menambah role.
- Role yang dipakai: `superadmin`, `admin-gudang`, `owner`, `staff-outlet`, `kasir`.
- ⚠️ **Jebakan penamaan:** migration `2026_05_12_133816_rename_user_roles` mengubah role lama `admin` → `admin-gudang`, dan `down()`-nya mengembalikan `admin-gudang` → `admin`. Memakai nama `admin` untuk role baru sah secara teknis (saat ini tidak ada baris `admin`), tetapi: (a) membingungkan dengan `admin-gudang`, (b) jika migration itu pernah di-rollback, user `admin-gudang` akan bertabrakan dengan `admin` baru. Lihat K1.
- Pengecekan role tersebar: **±43 titik di Blade** (`in_array($role, [...])`) + string middleware `role:...` di `routes/web.php` + `AdminController` (daftar `$roles` di form create/edit).
- Rute `/admin` (manajemen user) hanya `role:superadmin`.
- Tombol "Hapus" ada di **±20 view** (kas, bank, kategori, customer, outlet, payment, pengeluaran, penjualan, produk, promo, voucher, retur, salesman, slider, supplier, outlet-prices, admin, dst.) + hapus lewat resource `destroy` di PO, request-orders, delivery-orders, picking-lists, stock, dll.
- ⚠️ `GET /pembelian/{id}/destroy` (`stockDestroy`) — **menghapus lewat GET**. Rentan terpicu tak sengaja (link/prefetch/CSRF). Sebaiknya jadi `DELETE`/`POST`.
- ⚠️ Di `penjualan/index` tombol Hapus tampil untuk **semua** role yang punya akses halaman (termasuk `kasir` & `staff-outlet`), tidak ada `@if role`. Controller (`destroy`) tidak membatasinya — terkonfirmasi (lihat temuan v0.2 di bawah). Ini relevan untuk desain approval (K4).
- Sudah ada pola approval di PO (`owner_approval_status`: pending/approved/rejected + `approved_by/at/note`) → pola data & UI-nya bisa jadi acuan gaya.
- Tabel `activity_log` (spatie activitylog) sudah ada → bisa dipakai untuk jejak audit.
- Tabel `jobs` / `job_batches` sudah ada → queue tersedia.

**Temuan dari kode (v0.2) — hal yang mengubah rencana**
- **Kata `'admin'` sudah dipakai di kode lama:** `PenjualanController@create` berisi `role == 'kasir' | role == 'admin'` (operator bitwise `|`, bukan `||`) yang me-redirect ke `outlet.show` dengan `auth()->user()->outlet_id`. Admin baru **tidak punya outlet** → redirect bakal rusak. Harus diperbaiki di Tahap 4A.
- **`RoleMiddleware`** punya peta halaman utama per role (`homeRoutes`) yang belum memuat `admin` (dan sudah tidak memuat `kasir`). Role baru yang gagal akses akan jatuh ke `login` → loop. Harus ditambah.
- **`AdminController`:** daftar role di-hardcode di `create()` dan `edit()` (4 role); `AdminRequest` hanya `'role' => 'required'` tanpa daftar yang diizinkan; `update()` memakai `$request->all()` (role bisa diganti ke apa saja, termasuk `superadmin`) dan `destroy()` tanpa penjagaan; `show()` berisi `dd($user)`. Artinya **tanpa perbaikan, admin baru bisa mengangkat dirinya jadi superadmin** dan menghapus user — sehingga larangan hapus jadi percuma.
- **Hapus penjualan hanya soft delete:** `PenjualanController@destroy` = `$penjualan->delete()` dan model `Penjualan` memakai `SoftDeletes`. Tidak ada pembalikan stok, kas, atau closing shift. Jadi risiko "efek samping hapus" untuk penjualan lebih kecil dari dugaan v0.1, **tetapi** data penjualan yang di-soft-delete langsung hilang dari laporan dan stok/kas tidak dikoreksi (perilaku yang sudah ada, bukan bagian revisi ini — lihat K15).
- `User` model belum punya helper role; role hanya string di kolom `role`.
- Class `OutletAccess` sudah ada untuk memaksa `staff-outlet`/`kasir` hanya ke outletnya → dipakai ulang di Penjualan.

**Rekomendasi — bagian A: role**
1. Satukan definisi role di **satu tempat** (mis. `config/roles.php` atau konstanta di `User`), dengan grup:
   - `FULL_ACCESS = [superadmin, admin]` → dipakai di semua tempat yang tadinya `superadmin` saja.
   - `CAN_DELETE_DIRECT = [superadmin]`.
   - Method `User::isSuperadmin()`, `isAdmin()`, `hasFullAccess()`, `canDeleteDirectly()`.
2. Ganti semua `in_array($role, ['superadmin', ...])` di Blade & routes ke helper itu. Ini sekaligus bersih-bersih dan menutup risiko "ada satu menu lupa ditambah admin".
3. **Admin = superadmin minus**: tidak bisa hapus langsung. Tambahan pengaman yang kusarankan (K2): admin **tidak boleh** membuat/mengubah/menghapus akun `superadmin` & tidak boleh menaikkan role siapa pun ke `superadmin` (kalau tidak, admin bisa mengangkat dirinya sendiri → pembatasan hapus jadi percuma).

**Rekomendasi — bagian B: hapus pending**

Skema usulan, tabel `deletion_requests`:

| Kolom | Fungsi |
|-------|--------|
| id | PK |
| requested_by | FK users (admin pemohon) |
| target_type, target_id | model yang akan dihapus (polimorfik) |
| target_label | snapshot teks mudah dibaca (mis. "Produk: Indomie Goreng (8991…)") supaya tetap terbaca bila data berubah |
| target_snapshot | JSON ringkas data penting saat diajukan |
| module | nama modul (Produk, Penjualan, Supplier…) untuk filter di halaman superadmin |
| reason | alasan dari admin (wajib diisi) |
| status | `pending` / `approved` / `rejected` / `failed` / `cancelled` |
| reviewed_by, reviewed_at, review_note | keputusan superadmin |
| executed_at, error_message | hasil eksekusi hapus |
| route_name, route_params | cara menjalankan hapus yang sama persis dengan hapus normal |
| timestamps | — |

Aturan: **satu target hanya boleh punya satu request `pending`** (unique parsial/cek di service).

Alur:
1. Admin klik **Hapus** → modal konfirmasi + kolom **alasan** → request dibuat, data **tetap ada** (diberi badge "⏳ Menunggu persetujuan hapus" di baris itu, tombol Hapus diganti "Batalkan pengajuan").
2. Superadmin melihat badge angka di **sidebar** (menu baru "Permintaan Hapus") + kartu di dashboard — karena superadmin kadang tidak login, request menunggu di antrean tanpa batas waktu (opsional notifikasi WA/Telegram/email, lihat K7).
3. Halaman **Permintaan Hapus** (khusus superadmin): tab Pending / Disetujui / Ditolak, filter modul/pemohon/tanggal, detail snapshot, tombol **Setujui** (satu & massal), **Tolak** (+ catatan).
4. **Setujui → otomatis terhapus** dalam transaksi DB; hasil dicatat (`executed_at`) dan ke `activity_log`. Jika gagal (mis. data sudah dipakai di transaksi lain/FK) status jadi `failed` + pesan error, bukan hilang diam-diam.
5. Tolak → admin melihat status ditolak + catatan.

**Cara menerapkannya tanpa mengobrak-abrik 20 modul — usulan terbaik:**
Pasang **middleware `deletion.guard`** pada semua rute `DELETE` (dibungkus satu `Route::group`). Middleware ini:
- jika user `superadmin` (atau role yang `canDeleteDirectly`) → lanjut normal;
- jika `admin` → ambil model dari route-model-binding, buat `deletion_requests`, kembalikan redirect dengan pesan "Permintaan hapus dikirim ke superadmin", **controller tidak pernah dipanggil**;
- role lain → 403.

Saat superadmin menyetujui, eksekusi dilakukan dengan memanggil **action `destroy` controller yang sama** (lewat sub-request internal ke rute tersimpan, dengan konteks superadmin) sehingga seluruh efek samping hapus yang sudah ada (balikin stok, kas, batch, dll.) ikut jalan dan **logika hapus tidak diduplikasi**. Keuntungan: modul baru yang punya rute DELETE otomatis ikut terlindungi. Kekurangan: perlu dites per modul; untuk modul yang `destroy`-nya rumit (penjualan, PO, retur, stok) sebaiknya nanti dipindah ke class Action tersendiri.

Selain itu, **sembunyikan tombol Hapus** untuk role yang tidak berhak lewat komponen Blade `<x-delete-button>` yang tahu role (UI) — tapi **penegakan tetap di middleware** (server), bukan hanya menyembunyikan tombol.

**Yang tidak perlu di-gate:** hapus item draft/sementara (hapus baris item di PO draft, keranjang POS, item catatan, wishlist). Itu bukan penghapusan dokumen/data master. Yang di-gate = hapus **dokumen/master** (kecuali kamu ingin lebih ketat — K4).

**Risiko utama**
- Hapus penjualan/PO/retur punya efek samping stok & kas; request yang disetujui lama setelah diajukan bisa menghapus data yang statusnya sudah berubah. → Saat approve, cek ulang kondisi target (belum final/closing shift?), dan tampilkan peringatan jika `updated_at` target lebih baru dari saat diajukan.
- Penjualan yang sudah masuk **closing kasir / cashier_session** — hapus setelahnya mengubah setoran. Perlu aturan (K4).
- Aman dari manipulasi: validasi `target_type` dengan **allow-list** model, jangan menerima class name dari input user.

---

### 2.3 `/penjualan` — default hari ini, filter advance, DataTable rapi, cetak rekap

**Kondisi sekarang** (`penjualan/index.blade.php`)
- Memuat `$penjualan` (collection penuh) dengan `@foreach` — **tanpa paginasi/filter**; tiap baris membuat **tabel bersarang** berisi semua item + 6 baris total. Selama data kecil tidak terasa; di data ribuan nota halaman jadi berat dan DataTables client-side lambat.
- Kolom: No, Kode Invoice, Outlet, Kassa, Nama kasir shift, Detail (tabel bersarang), Aksi (Show / Re-Print / Hapus).
- Header: Buat Penjualan Baru, **Lihat Stock Toko (akan dihapus)**, History Kasir, Retur/Ganti Barang.
- Potensi N+1: `outlet`, `kasir`, `cashierShift`, `items.product`, `voucher` diakses per baris (tergantung eager-load di controller — belum terlihat).
- Sudah ada rute `/penjualan-detail/{id}/items` → bisa dipakai untuk **detail per baris yang dimuat saat dibuka**.
- Laporan penjualan outlet sudah mendukung parameter `tanggal_mulai`, `tanggal_selesai`, `outlet_id`, `kasir_id` (PDF & Excel) → **cetak rekap bisa memakai ulang**.
- `daterangepicker` + `moment` sudah dimuat di `layouts/master`.

**Temuan dari kode (v0.2)**
- `PenjualanController@index` memang `get()` seluruh penjualan (non-marketplace) dengan eager-load `outlet, kasir, cashierShift, items.product` → **tidak ada N+1, tetapi tanpa batas**; beratnya naik lurus dengan jumlah nota.
- **Tidak ada pembatasan outlet** di `index` — `staff-outlet` melihat penjualan **semua outlet** (perlu dikonfirmasi apakah disengaja). `OutletAccess::id()`/`outlets()` sudah siap dipakai untuk memaksa outlet sendiri.
- `getPenjualan($outlet_id)` dan `getItems($id)` (rute JSON) tidak memeriksa outlet/kepemilikan → role operasional mana pun bisa membaca penjualan outlet lain.
- Migration `penjualans` yang terlihat hanya punya index `(cashier_session_id, created_at)`; **tidak terlihat index `created_at` atau `(outlet_id, created_at)`** → query filter tanggal & outlet akan scan penuh.

**Rekomendasi**
1. **Server-side DataTables** (`GET /penjualan/data`) — halaman hanya mengirim kerangka; data diambil per halaman (25/50/100), search & sort di server.
2. **Filter bar (collapse "Filter Lanjutan")**:
   - Rentang tanggal dengan preset: **Hari ini (default)**, Kemarin, 7 hari terakhir, 30 hari terakhir, Bulan ini, Bulan lalu, Custom.
   - Outlet (select2), Akun kasir, Shift, Metode pembayaran, Status (normal/ada retur), pencarian invoice/produk/customer.
   - Tombol Terapkan / Reset; filter tersimpan di URL (bisa di-bookmark & dibagikan).
3. **Kartu ringkasan di atas tabel** (mengikuti filter): Jumlah transaksi, Total penjualan, Diskon+promo+voucher, Grand total, Rincian per metode bayar. Dihitung via satu query agregat.
4. **Tabel rapi:** kolom Tanggal/Jam, Invoice, Outlet, Kasir, Shift, Jumlah Item, Metode Bayar, Grand Total, Status, Aksi. Detail item = **baris yang bisa di-expand** (child row) atau modal, bukan tabel bersarang. Angka rata kanan, badge untuk status/metode.
5. **Hapus** tombol "Lihat Stock Toko".
6. **Tombol "Cetak Rekap"**: membuka PDF di tab baru memakai filter aktif. Dua mode: *Ringkas* (rekap per metode bayar, per kasir, per outlet, total, bon, voucher, setoran akhir — mirip bagian Rekap pada laporan outlet) dan *Detail* (daftar transaksi). Usulan: pakai ulang `laporan.pdf.outlet.penjualan` agar angkanya identik dengan Laporan, tambahkan mode ringkas bila belum ada.
7. **Default "hari ini" harus berdasarkan zona waktu aplikasi** (Asia/Jakarta). Kasus tepi: shift kasir yang melewati tengah malam — apakah "hari ini" = tanggal kalender atau hari kerja kasir/shift? (K10)
8. Index database: `penjualans(created_at)`, `(outlet_id, created_at)`, `(cashier_session_id)`, `penjualan_items(penjualan_id)` jika belum ada (cek di migration yang ada; hanya sebagian terlihat).
9. Tombol Hapus mengikuti aturan role baru (superadmin langsung, admin → pending, role lain → disembunyikan/ditolak server). Lihat 2.2.

**Usulan terbaik:** bangun satu `PenjualanFilter` (class query-scope) yang dipakai bersama oleh **tabel, kartu ringkasan, cetak rekap, dan Laporan Penjualan Outlet** — satu definisi filter, satu definisi angka.

**Risiko:** staff-outlet/kasir seharusnya hanya melihat outlet-nya sendiri; pastikan filter outlet dipaksa di server untuk role itu, bukan hanya disembunyikan di UI.

---

### 2.4 Laporan — treeview, index rapi, modal daterange, tombol PDF disembunyikan

**Kondisi sekarang**
- Sidebar: satu link "Laporan" (`/laporan`). Di dalam halaman ada **tab** "Laporan Gudang" & "Laporan Outlet" (`laporan/index.blade.php` 285 baris, `laporan/outlet.blade.php` 132 baris).
- Index Gudang: 14 kartu hampir identik (judul + 1 tombol "Lihat Laporan"), tanpa deskripsi, tanpa pengelompokan; tiap kartu dibungkus `@if role` sendiri-sendiri. Index Outlet: 5 kartu dengan deskripsi singkat (lebih informatif).
- Modal: dibuat lewat loop `$modals`/`$reports` — **dua implementasi terpisah** (markup & JS duplikat). Input tanggal memakai `<input type="date">` dua kolom (default awal bulan–hari ini), tanpa preset, tanpa validasi.
- Tombol "Preview PDF" membuka tab baru; "Export Excel" memicu navigasi `window.location` (tidak ada indikator loading — untuk laporan besar user mengira macet dan klik berulang).
- Teks info Outlet masih menyebut "dicetak/preview PDF".

**Rekomendasi**
1. **Sidebar:** `Laporan` jadi **treeview** dengan 2 submenu: **Laporan Gudang** (`/laporan`) dan **Laporan Outlet** (`/laporan/outlet`); logika `active` pada treeview & submenu; role yang boleh melihat tiap submenu mengikuti matriks role (nanti dipisah per role seperti rencana sebelumnya). Hapus tab di dalam halaman (redundan) atau ganti menjadi breadcrumb.
2. **Index baru (kedua halaman satu template `laporan/_index-layout`)**:
   - Kartu dikelompokkan per kategori dengan judul seksi (Gudang: *Pembelian & Penerimaan*, *Stok & Opname*, *Pengiriman & Permintaan*, *Retur*, *Aktivitas & Analisis*; Outlet: *Penjualan*, *Stok*, *Retur & Rafaksi*).
   - Tiap kartu: ikon berwarna, judul, **deskripsi 1–2 baris**, badge "Excel", tombol utama "Buat Laporan".
   - **Kotak pencarian laporan** di atas + chip filter kategori.
   - Strip info ringkas di atas (mis. periode default, jumlah laporan tersedia untuk role ini).
3. **Satu komponen modal** (`<x-report-modal>`), konfigurasi per laporan dari satu array (id, judul, deskripsi, route excel, route pdf, field yang dipakai: tanggal / outlet / kasir / rafaksi / supplier…), dipakai kedua halaman.
4. **Daterange yang rapi:** satu input `daterangepicker` (library sudah ada di master) dengan preset: Hari ini, Kemarin, 7 hari, 30 hari, Bulan ini, Bulan lalu, Tahun ini, Custom. Validasi: tanggal mulai ≤ selesai; batas maksimum rentang untuk laporan berat (usul 92 hari, bisa diatur per laporan) + pesan jelas.
5. **Tombol Excel dengan status loading** (spinner + disabled sampai unduhan mulai) untuk mencegah klik ganda.
6. **"Hilangkan hidden semua tombol pdfnya"** — kuartikan sebagai: **semua tombol PDF/Preview PDF di popup disembunyikan**. Usulan: bukan dihapus, tapi lewat flag `config('laporan.show_pdf', false)` sehingga mudah dinyalakan lagi kapan saja dan route/blade PDF tidak dibuang. Konfirmasi di K12. Catatan: "Cetak Rekap" di halaman Penjualan (2.3) tetap memakai PDF dan tidak terkena aturan ini.
7. Perbaiki teks keterangan Outlet sesuai (tidak lagi menyebut PDF).

**Usulan terbaik:** jadikan array konfigurasi laporan sebagai *single source of truth* untuk: kartu index, isi modal, URL, **dan hak akses per role** — sehingga pemisahan hak akses per role nanti cukup mengubah satu file.

**Risiko:** rendah. Hati-hati dengan `Route::currentRouteName()` untuk status active, karena ada beberapa route turunan (`laporan.outlet.*`).

---

### 2.5 Excel — rapi, formal, query ringan

**Kondisi sekarang (dari view; gambaran lengkap per class ada di "Temuan dari kode v0.2" di bawah)**
- 12 export dibuat dari **tabel HTML Blade** (pola *from view*): hanya `<table>` polos — tanpa judul perusahaan, periode, tanggal cetak, lebar kolom, format angka, freeze header, border, atau area print.
- Contoh `laporan-stock`: kolom sederhana, memakai `@currency(...)` → nilai jadi **teks "Rp 10.000"**, bukan angka → **tidak bisa di-SUM/filter/sort numerik** di Excel.
- `laporan-penjualan`: `$penjualan->customer->name` tanpa `?->` → error bila customer null; `laporan-stock`: `$stock->pembelian->code`, `$stock->product->name` → **N+1 query** dan error bila relasi null.
- `outlet/penjualan` memakai **rowspan** per invoice/outlet/kasir (rapi dibaca, tapi merusak filter/pivot Excel dan membuat file berat).
- Export model penuh via relasi lazy (`$row->relation->...`) → banyak query & memori; pola ini memuat seluruh hasil sekaligus.
- Tidak ada baris total/SUM, tidak ada pemisahan header laporan vs data.
- PDF memakai warna header `#4472C4` dan `exports/pdf/_header` (logo+nama perusahaan) → **identitas visual sudah ada, bisa dijadikan acuan Excel**.

**Temuan dari kode (v0.2) — koreksi gambaran v0.1**
- Ada **39 class Export**: 12 `FromView`, 27 `FromCollection`.
- **12 class sudah "formal"** (`WithStyles` + `WithDrawings` logo + `WithCustomStartCell`): dokumen tunggal (Pembelian, Penerimaan, Picking, Request Order, Delivery Order, Retur ×2, Stock Opname + template, Kartu Stok). Plus Retur Supplier & Retur Outlet (`WithStyles`+`ShouldAutoSize`). **Ini jadi acuan template, bukan dibuat dari nol.**
- **Belum rapi:** 10 laporan gudang (`LaporanPO/PR/BarangMasuk/BarangKeluar/PembelianBarang/PenerimaanBarang/Pengiriman/PickingPacking/Aktifitas/Pergerakan`) = hanya `WithHeadings` + `WithTitle`, tanpa style/logo/format angka; plus `StockExport`, `ProductsExport`, dan **12 `FromView`** (5 Outlet, Penjualan ×3, Pembelian ×2, Pengeluaran, Laba Rugi).
- Pola berat yang terbukti di kode: `whereDate()` + `Model::with(...)->get()` memuat semua baris ke memori (contoh `LaporanPOExport`); `LaporanController` masih punya banyak `whereDate`/`->get()` di versi PDF dan opname.
- Tersedia `App\Support\ReportQuery` (`betweenDates`, `resolveReferences` batch, batas PDF 1500 baris) — **sebagian pekerjaan "query ringan" sudah dimulai tapi belum dipakai merata**. Dipakai ulang dan diperluas.
- Tabel `jobs`/`job_batches` + `Jobs/ProcessProductImportChunk` sudah ada → pola antrean sudah dikenal di proyek.

**Rekomendasi — tampilan formal**
Satu **kerangka standar** untuk semua laporan:
- Baris 1–4: **nama perusahaan, alamat, judul laporan, periode, filter aktif (outlet/kasir), dicetak oleh & waktu cetak** (merge & center).
- Header tabel: warna konsisten (`#4472C4`, teks putih, tebal, rata tengah, wrap), tinggi baris tetap.
- Body: border tipis, zebra opsional, angka **numerik asli** dengan format `#,##0` (uang) / `#,##0.##` (qty), tanggal format `dd/mm/yyyy [HH:mm]` (tipe tanggal Excel, bukan teks).
- **Freeze pane** di bawah header, **autofilter**, lebar kolom ditentukan/auto-size terkontrol.
- **Baris TOTAL** memakai rumus `SUM/SUBTOTAL` (tetap benar saat difilter).
- **Print setup:** landscape, fit-to-width 1 halaman, ulangi baris header tiap halaman, margin & footer nomor halaman.
- Nama sheet & nama file konsisten: `Laporan-Penjualan-Outlet_20261001-20261026.xlsx`.
- Untuk laporan multi-bagian (data + rekap) → **sheet terpisah** ("Data", "Rekap") ketimbang tabel ditumpuk di satu sheet.

**Rekomendasi — query ringan**
1. Ganti export "from view" dengan **FromQuery / FromGenerator + WithChunkReading-style streaming** (`->cursor()` / `chunk(1000)`), memakai `select()` kolom yang diperlukan dan **join** untuk nama relasi, bukan eager-load model penuh.
2. Eliminasi N+1: ambil label (produk, outlet, kasir, supplier) lewat join atau satu query lookup berindeks.
3. Rekap/total dihitung **di database** (`SUM ... GROUP BY`), bukan di PHP.
4. Filter tanggal dengan `>= start 00:00:00 AND < end+1day 00:00:00` (jangan `whereDate`).
5. Index database pada kolom filter yang sering dipakai (tanggal, outlet_id, kasir_id, supplier_id, status).
6. **Batas rentang & antrean:** rentang kecil → unduh langsung; rentang besar (ambang usul > 31 hari / > ±20 ribu baris) → **diproses via queue** (`jobs` sudah ada) dan file muncul di halaman "Unduhan Saya"/notifikasi saat selesai. Ini mencegah timeout & memori habis.
7. Null-safe di semua relasi (`?->`, `??`), tak ada lagi error karena data referensi terhapus.

**Usulan terbaik:** buat satu kelas dasar `BaseReportExport` (judul, periode, filter, style, format kolom, total) + trait style; setiap laporan hanya mendefinisikan **query + daftar kolom + format**. Sekali kerangka jadi, 17+ laporan tinggal "mengisi kolom" — konsisten dan murah dirawat.

**Beririsan dengan pekerjaan sebelumnya:** ada `todolist_export_gudang.md` (redesain 12 Excel gudang bertahap) dan rencana redesain Excel Laporan Toko. Di zip ini 10 export gudang masih polos, jadi kemungkinan perubahan itu belum masuk ke zip ini atau belum sampai tahap tersebut. Tahap #5 kubuat **melanjutkan, bukan mengulang**; sebelum mulai aku perlu tahu status terakhirnya (K13).

---

## 3. Keputusan yang perlu dikonfirmasi

Tiap poin ada **default usulanku**; balas "ikut rekomendasi" bila setuju semua.

| Kode | Pertanyaan | Default usulan |
|------|-----------|----------------|
| K1 | Nama nilai role baru? | `admin` sesuai permintaan, tetapi label tampilan "Admin (Pusat)" supaya beda dari `admin-gudang`; catat bahwa rollback migration rename role akan bentrok |
    -> ya admin ini memang beda dengan admin-gudang. jadi coba berikan yang terbaik ya.
| K2 | "Sama persis superadmin" — termasuk menu Setting, kelola user Admin, Payment Method? | Ya semuanya, **kecuali** admin tidak bisa membuat/ubah/hapus akun superadmin dan tidak bisa mengubah role ke superadmin |
    -> ya benar
| K3 | Siapa yang boleh menyetujui hapus? | Hanya `superadmin` (owner tidak) |
    -> hanya superadmin
| K4 | Lingkup hapus yang di-pending | Semua hapus **dokumen/data master**; item draft/keranjang/baris sementara tidak. Hapus Penjualan saat ini terbuka untuk semua role operasional (terkonfirmasi dari rute & view); usul: hanya superadmin (langsung) dan admin (pending), role lain ditolak server |
    -> semuanya
| K5 | Penjualan yang sudah masuk closing kasir | Request tetap boleh, tapi saat approve muncul peringatan + wajib catatan (hapus penjualan = soft delete, lihat K15) |
    -> ya boleh tapi harus koreksi stock dan sepertinya aku tidak cocok dengan softdelete disini karena harusnya dihapus ya hilang karena sudah lewat persetujuan
| K6 | Request pending bisa dibatalkan pemohon? | Ya, selama masih pending |
    -> yang bisa membatalkan hanya superadmin dengan cara menolaknya.
| K7 | Notifikasi ke superadmin | Fase 1: badge sidebar + kartu dashboard + halaman khusus. Fase 2 (opsional): WA/Telegram/email |
    -> hanya fase 1 saja
| K8 | Dashboard penjualan berlaku untuk role apa? | superadmin, admin, owner. `admin-gudang` tetap dashboard gudang |
    -> all kecuali admin gudang.
| K9 | Fitur Min Stok setelah tombol di dashboard dihapus | Pindah jadi tombol di halaman `/product` (route tetap) |
    -> oke yang rapi ya
| K10 | "Hari ini" di Penjualan | Tanggal kalender zona Asia/Jakarta; sediakan filter shift untuk kasus lewat tengah malam |
    -> betul, tidak perlu filter shift nya.
| K11 | Isi "Cetak Rekap" | Dua mode: Ringkas (default) & Detail |
    -> oke berikan yang terbaik ya
| K12 | "Hilangkan hidden tombol PDF" | Disembunyikan lewat flag config (tidak dihapus), di semua popup Laporan Gudang & Outlet |
    -> betul hanya sembunyikan saja sementara sampai kemudian hari akan kita kembangkan lagi belum untuk saat ini.
| K13 | Status `todolist_export_gudang.md` & redesain Excel Toko — 10 export gudang di zip ini masih polos | Mohon info tahap yang sudah selesai; bila belum ada yang masuk, Tahap 5 mulai dari awal memakai 12 export "formal" sebagai template |
    -> ya ini untuk semua export excel yang ada. dan yang jelas disini belum pernah dikerjakan sama sekali bagian ini. tidak hanya toko ya tapi gudang juga. dan masing masing indexnya buat semenarik dan serapi mungkin
| K14 | Aplikasi sudah dipakai produksi atau masih uji coba? Timezone & versi paket (`config/`, `composer.json`) tidak ada di zip | Dianggap uji coba → boleh tambah index & migration; timezone kuasumsikan Asia/Jakarta dan akan kucek saat Tahap 0 |
    -> timzone jakarta
| K15 | Hapus penjualan sekarang hanya soft delete (stok/kas tidak dibalikkan). Tetap begitu? | Tetap (di luar lingkup revisi ini), tapi saat superadmin meng-ACC muncul peringatan "stok & kas tidak dikoreksi"; perbaikan penuh jadi usulan terpisah |
    -> untuk bagian bagian yang berkaitan dengan stock penghapusan harus sekaligus mengkoreksi stock ya. apapun.
| K16 | `staff-outlet` melihat penjualan semua outlet di `/penjualan` — disengaja? | Dibatasi ke outlet sendiri lewat `OutletAccess`, kecuali kamu bilang sebaliknya |
    -> oke setuju

### 3A. Keputusan tambahan (v0.3)

| Kode | Keputusan |
|------|-----------|
| K17 | **Semua** hapus wajib lewat ACC superadmin bila dilakukan admin — termasuk item draft, baris item PO/RO, catatan, keranjang. Tidak ada pengecualian. |
| K18 | Hapus Penjualan **tetap soft delete**. Yang wajib: **semua hapus yang berkaitan dengan stok (soft delete maupun bukan) harus mengoreksi stok.** |
| K19 | Penjualan yang sudah masuk closing kasir boleh dihapus (lewat ACC), dengan syarat stok terkoreksi dan rekap/laporan ikut benar (lihat 3B-1). |
| K20 | `StockController@destroy` **tidak diaktifkan** (hapus stok tidak dipakai). `RefundPembelianController@destroy` dibiarkan seperti sekarang. `RefundController@destroy` dan `PembelianController@destroy` **harus dikoreksi stoknya.** |
| K21 | Identitas di header Excel (nama perusahaan, alamat, telp, email, website, logo) **diambil dari menu Setting** (`settings.json` di disk `public`, dibaca `DashboardController@setting`). |

### 3A2. Keputusan teknis Tahap 1 (diambil saat membangun; bisa diubah)

| Kode | Keputusan |
|------|-----------|
| K25 | Batas rentang tanggal di modal laporan dibuat **longgar: 366 hari** (bukan 92) agar preset "Tahun Ini" dan laporan tahunan yang sekarang dipakai tidak terblokir di produksi. Diperketat per laporan lewat `max_days` di `config/laporan.php` setelah Excel ringan + antrean (Tahap 5). |
| K26 | Export lama **di luar katalog** (Penjualan umum/kasir/supplier, Pembelian, Pembelian-Supplier, Pengeluaran, Laba Rugi, Products, Suppliers, Categories) masih polos dan tidak tampil di menu Laporan. **Keputusan (2026-10-26): dibiarkan, tidak ikut dirapikan** — item 5.8b dihapus dari todolist. |
| K26 | Tombol PDF disembunyikan lewat `LAPORAN_SHOW_PDF` (default `false`). **Rute PDF tidak dihapus dan tidak diblokir** — hanya tombolnya yang hilang (sesuai K12). |
| K27 | Hak akses kartu laporan **sama seperti sebelumnya** (daftar role per laporan dipindah ke `config/laporan.php`); role `admin` sudah disertakan di samping `superadmin`. Menu Laporan di sidebar tampil untuk role yang punya ≥1 laporan (kasir tetap tidak melihat). |

### 3B. Keputusan akhir untuk pertanyaan terbuka

| Kode | Keputusan |
|------|-----------|
| K22 | **Closing kasir:** saat hapus penjualan disetujui, kolom hasil sesi yang sudah closed (`cash_sales`, `expected_cash`, `discrepancy`, dst.) **dihitung ulang** dan perubahannya dicatat di `activity_log` (nilai sebelum & sesudah). |
| K23 | **Dashboard penjualan untuk `staff-outlet` & `kasir`:** hanya penjualan **outlet sendiri** (dipaksa di server lewat `OutletAccess`). |
| K24 | **Aplikasi sudah produksi** → berlaku aturan keselamatan 3C. |

### 3C. Aturan keselamatan karena sudah produksi

1. **Backup database penuh** sebelum setiap tahap yang memuat migration; catat nama/waktu backup di Log Progres.
2. **Migration hanya menambah** (tabel/kolom/index baru); tidak ada drop/rename kolom lama. Kolom baru `nullable`/ber-default agar data lama tidak terganggu.
3. **Index** pada tabel besar dibuat di jam sepi (cek ukuran tabel dulu; bila besar, pakai `ALGORITHM=INPLACE, LOCK=NONE` atau jalankan manual). Cek index yang sudah ada agar tidak dobel.
4. **Role `admin` baru** dibuat tanpa mengubah role user yang ada; pengalihan cek role ke helper dilakukan dengan hasil yang sama untuk role lama (uji sebelum rilis).
5. **Hapus-pending dirilis bertahap per kelompok modul (4C)**; modul belum dipasang tetap berperilaku lama. Sebelum modul dipasang, rute DELETE tidak boleh berubah perilakunya bagi superadmin.
6. **Koreksi stok saat hapus (4C.0)** diuji dulu di salinan data produksi (staging/lokal) dengan skenario nyata; tidak ada koreksi massal terhadap data lama.
7. **Fitur hapus lama yang berbahaya** (`/optimize-clear`, `/storage-link`, `GET /pembelian/{id}/destroy`) ditangani lebih awal karena terbuka di produksi.
8. **Rilis per tahap, satu tahap satu waktu**, ada rencana mundur (rollback) tertulis untuk tiap migration; cache/config dibersihkan sesuai kebutuhan setelah deploy.
9. **Excel/antrean (Tahap 5):** uji beban di data salinan; ambang antrean dimulai konservatif; pastikan worker queue berjalan di hosting (dan batas `max_user_connections` = 25 diperhatikan).

---

## 4. Todolist bertahap

Aturan kerja (sama seperti revisi sebelumnya): tiap tahap kirim **hanya file yang berubah**, tanpa zip, dan todolist ini diperbarui setelah tahap selesai.

### TAHAP 0 — Fondasi
- [x] 0.1 Terima & baca kode lengkap (`Archive.zip`) — selesai, analisa dikoreksi ke v0.2
- [x] 0.2 Buat `config/roles.php` + method helper di `User` (`hasRole`, `isSuperadmin`, `isAdmin`, `hasFullAccess`, `canDeleteDirectly`, `canApproveDeletion`, `mustRequestDeletion`, `roleLabel`) — **tidak mengubah perilaku apa pun** (belum dipakai); menunggu dicek
- [ ] 0.1b **Backup database produksi** + catat di Log Progres (3C-1)
- [x] 0.3 Migration index — hasil cek: `penjualans (outlet_id, created_at)`, `(cashier_session_id, created_at)`, `(cashier_shift_id, created_at)` dan `penjualan_items (penjualan_id, owner_stock_id)` **sudah ada** (koreksi catatan v0.2). Hanya ditambah **`penjualans (created_at)`**, dengan pengecekan agar tidak dobel. Index untuk pembelian/stok ditunda ke Tahap 5 setelah query-nya dibaca (jangan menebak di produksi). Menunggu dijalankan (setelah backup 0.1b, jam sepi)
- [x] 0.4 Timezone aplikasi = `Asia/Jakarta` — dikonfirmasi user (`config/app.php`). Zona waktu koneksi database (`config/database.php`) belum dikonfirmasi; dicek lagi di Tahap 2 saat filter "hari ini" dibangun
- [x] 0.5 Tutup rute berbahaya: `/optimize-clear` & `/storage-link` (lihat Bagian 6) — **prioritas tertinggi karena sudah produksi**; kerjakan paling awal — selesai: kini di belakang `auth` + `role:superadmin`; menunggu dicek

### TAHAP 1 — Menu Laporan (item 4, bagian sidebar & index)
- [x] 1.1 Sidebar: `Laporan` → treeview (Laporan Gudang, Laporan Outlet) + logika active
- [x] 1.2 Konfigurasi laporan terpusat: `config/laporan.php` + `app/Support/LaporanCatalog.php` (kategori, deskripsi, route, field, role, `show_pdf`, `max_range_days`)
- [x] 1.3 Template index bersama (`laporan/_index-layout`) + pengelompokan kategori + pencarian laporan
- [x] 1.4 Komponen modal laporan tunggal (`<x-report-modal>`) + daterangepicker + preset + validasi
- [x] 1.5 Flag `show_pdf=false` (`LAPORAN_SHOW_PDF` di `.env`) → semua tombol PDF disembunyikan; perbaiki teks info
- [x] 1.6 Indikator loading pada tombol Excel (unduh via fetch + pesan error jelas)
- [x] 1.7 **Perbaikan bug tab kategori:** pindah tab lalu kembali (atau ke "Semua") membuat kartu hilang. Penyebab: grup dihitung dengan `.rpt-item:visible`, padahal item di dalam grup yang sedang disembunyikan selalu dianggap tidak terlihat. Kini jumlah item per grup dihitung dari hasil filter, dan sembunyi/tampil memakai class `is-hidden` (bukan `.toggle()`). Ditambah tombol "Tampilkan semua laporan" saat hasil kosong — menunggu dicek
- [x] 1.8 **Index tampil modern:** hero bergradien + pencarian besar, tab kategori berbentuk pill dengan ikon & jumlah, grid kartu (CSS grid) dengan ikon gradien per kategori, efek hover, animasi muncul, responsif mobile; modal laporan ikut bergaya (header gradien sesuai kategori). Berlaku untuk Laporan Gudang & Outlet sekaligus — menunggu dicek

### TAHAP 2 — Halaman Penjualan (item 3)
- [x] 2.1 `App\Support\PenjualanFilter` (satu definisi filter: periode, outlet, kasir, metode bayar, status, pencarian; dipakai tabel, kartu ringkasan, dan Cetak Rekap) + endpoint server-side `GET /penjualan/data` — menunggu dicek
- [x] 2.2 View index baru: filter bar (preset **Hari ini = default**, Kemarin, 7/30 hari, Bulan ini/lalu, Pilih tanggal) + **Filter Lanjutan** (outlet, kasir, metode, status) + pencarian invoice/kasir/customer/produk; filter tersimpan di URL (bisa di-bookmark). **Filter shift tidak dibuat** (K10: tidak diperlukan) — menunggu dicek
- [x] 2.3 Kartu ringkasan (tampilan kartu dipoles modern: ikon, kartu Grand Total bergradien, chip metode bayar, efek loading) mengikuti filter (`GET /penjualan/summary`, satu query agregat): jumlah transaksi, total penjualan, diskon+promo+voucher, grand total, rincian per metode bayar, dan baris info retur/ganti barang. **Grand Total = nilai nota sebelum retur**; retur ditampilkan terpisah, tidak dicampur diam-diam — menunggu dicek
- [x] 2.4 DataTable server-side (25/50/100 per halaman) dengan kolom Tanggal, Invoice, **Customer**, Shift, Item, Metode, Grand Total, Aksi (**kolom Outlet, Kasir, Status dihilangkan** — revisi 2026-10-26); detail item = baris yang bisa di-expand (`GET /penjualan/{id}/detail`; daftar retur tetap tampil di detail) — menunggu dicek
- [x] 2.5 Tombol "Lihat Stock Toko" dihapus
- [x] 2.6 Tombol **Download Rekap** (hijau, di samping Retur/Ganti Barang) — **Excel, bukan PDF** (PDF memicu ERR_EMPTY_RESPONSE): 2 sheet, `Ringkasan` (total, per metode, per kasir = dikelompokkan per nama shift, retur, bon, setoran; bagian per outlet dihapus; kolom Nilai bertipe Rupiah, kosong diisi Rp 0) dan `Transaksi` (satu baris per nota, TOTAL berumus); memakai filter aktif, maks. 50.000 nota; `GET /penjualan/rekap` — menunggu dicek
- [x] 2.7 Batasan outlet dipaksa di server untuk `staff-outlet`/`kasir` (K16): tabel, kartu ringkasan, cetak rekap, `getPenjualan`, `getItems`, serta `show`, `print`, `destroy`, dan `detail` (403 bila nota milik outlet lain) — menunggu dicek
- [~] 2.8 Uji: **belum bisa dijalankan di sini** (tidak ada PHP/database di lingkungan kerja ini). Checklist uji ada di Log Progres; perlu dijalankan di lokal/staging sebelum rilis (data besar, lintas tengah malam, nota dengan retur)

### TAHAP 3 — Dashboard (item 1)
- [x] 3.1 `App\Services\DashboardSalesService` (agregat SQL `SUM/COUNT/GROUP BY`, rentang `created_at >= ? AND <= ?` tanpa `whereDate`, cache 2 menit per periode+outlet, `?refresh=1` melewati cache). Rumus: omzet bersih = nilai nota paid/null − `returned_total` retur atas nota di periode (retur dibuat s/d akhir periode) + `replacement_total` retur yang dibuat di periode; transaksi = nota + retur ber-pengganti (sama dengan `OutletLaporanService::sales`) — menunggu dicek
- [x] 3.2 Kartu penjualan + filter periode (daterangepicker: Hari ini … Tahun ini, maks. 366 hari, default bulan berjalan) + filter outlet. Kartu: Omzet Hari Ini (+ vs kemarin), Transaksi Hari Ini, Omzet Periode (+ vs periode sebelumnya), Transaksi Periode, Rata-rata/Transaksi, Total Diskon+Promo+Voucher (+ info retur & pengganti). Tampil untuk semua role kecuali `admin-gudang` (K8, `config/roles.php` → `sales_dashboard`); `staff-outlet` & `kasir` dipaksa ke outlet sendiri di server lewat `OutletAccess` (K23); endpoint `GET /dashboard/sales-data` (403 untuk role lain) — menunggu dicek
- [x] 3.3 Grafik (Highcharts, dimuat via AJAX satu panggilan): Tren (omzet + jumlah transaksi; per bulan bila rentang > 62 hari), Metode Pembayaran (donut), Penjualan per Outlet (disembunyikan bila hanya satu outlet), Jam Ramai — menunggu dicek
- [x] 3.4 Ranking: Top 10 Produk Terjual (qty dikurangi retur yang tertaut item nota), Top Kasir, 10 Transaksi Terbaru. *Estimasi laba kotor (opsional di analisa) tidak dibuat — butuh keputusan HPP per item.* — menunggu dicek
- [x] 3.5 Blok gudang dipisah ke `dashboard/_gudang.blade.php` dan dipindah ke bawah; untuk role penjualan tersembunyi dengan tombol "Tampilkan Info Gudang" (grafik gudang dibuat saat pertama dibuka), `admin-gudang` langsung tampil. Sapaan jadi "Selamat Datang, {nama}!" (bukan "…di Gudang"). Kartu "Produk Terlaris" diganti judul **Top 5 Barang Dikirim ke Outlet** karena sebenarnya berbasis pengiriman gudang→outlet. Query `adjustmentProducts` (memuat semua produk) dihapus dari dashboard sehingga dashboard lebih ringan — menunggu dicek
- [x] 3.6 Tombol & modal **Pengaturan Min Stok** dipindah ke `/product` (rute `product.minimum-adjustment.store` tidak berubah); datanya hanya dihitung untuk `superadmin`, `admin`, `admin-gudang` — menunggu dicek
- [~] 3.7 Cocokkan angka dengan Laporan Penjualan Outlet: **belum bisa dijalankan di sini** (tidak ada database). Checklist uji di Log Progres (butir 10 di bawah). Selisih kecil yang diketahui: retur pada nota ber-voucher (Laporan mengalokasikan voucher per item, dashboard memakai `returned_total`) dan retur tanpa `penjualan_item_id` tidak mengurangi qty di Top Produk

### TAHAP 4 — Role Admin + Hapus Pending (item 2)
**4A. Role**
- [ ] 4A.1 Ganti semua cek role Blade & routes ke helper (43 titik + middleware)
- [ ] 4A.2 `AdminController` + `AdminRequest`: tambah role `admin` ke daftar; daftar role yang diizinkan (allow-list) di validasi; ganti `$request->all()` di `update()` dengan data tervalidasi; admin tidak boleh membuat/mengubah/menghapus akun superadmin atau menaikkan role; hapus `dd()` di `show()`
- [ ] 4A.2b Perbaiki `PenjualanController@create` (cek legacy `'kasir' | 'admin'`) agar admin pusat tidak ter-redirect ke outlet kosong
- [ ] 4A.2c `RoleMiddleware`: tambah `admin` ke `homeRoutes`
- [ ] 4A.3 Sidebar/menu untuk admin = superadmin
**4B. Infrastruktur approval**
- [ ] 4B.0 Aturan: **semua** rute hapus admin → pending, tanpa pengecualian (K17), termasuk `destroyItem`/`destroyNote` PO & RO, `cart.destroy`; yang bisa langsung hapus hanya superadmin
- [ ] 4B.1 Migration + model `DeletionRequest` (status: pending/approved/rejected/failed — **tanpa `cancelled`**, K6)
- [ ] 4B.2 `DeletionRequestService` (buat, setujui, tolak, eksekusi, log) — pembatalan hanya lewat **tolak oleh superadmin** (K6)
- [ ] 4B.3 Middleware `deletion.guard` pada rute DELETE (allow-list model)
- [ ] 4B.4 Komponen `<x-delete-button>` (modal alasan + badge "menunggu persetujuan"; admin **tidak** punya tombol batalkan)
- [ ] 4B.5 Halaman superadmin **Permintaan Hapus** (tab, filter, detail snapshot, setujui/tolak, massal)
- [ ] 4B.6 Badge sidebar + kartu dashboard superadmin
**4C. Penerapan per modul** (ceklis per modul, uji hapus normal & pending)
- [ ] 4C.0 **Koreksi stok saat hapus (K18–K20)** — satu `DeleteExecutor`/Action per modul, dipanggil sama oleh hapus langsung superadmin dan eksekusi ACC:
  - [ ] Penjualan (soft delete tetap): kembalikan stok outlet per item lewat `OutletStockService` (memakai `stock_id`/`owner_stock_id` dari pivot item), catat pergerakan stok/kartu stok, dalam satu transaksi
  - [ ] Penjualan yang sudah closing: **hitung ulang** kolom hasil sesi (K22) + catat nilai sebelum/sesudah di `activity_log`
  - [ ] Pastikan semua laporan/rekap/dashboard mengecualikan penjualan terhapus (audit query mentah/join tanpa filter `deleted_at`)
  - [ ] `PembelianController@destroy`: sekarang hanya `stocks()->delete()` tanpa koreksi → koreksi stok/batch & rekap
  - [ ] `RefundController@destroy`: sekarang hanya `delete()` → koreksi stok sesuai jenis retur
  - [ ] `RefundPembelianController@destroy`: dibiarkan (K20); catat bahwa koreksi hanya untuk tipe `gudang_ke_supplier`
  - [ ] `StockController@destroy`: tidak diaktifkan (K20); `dd()` diganti penolakan aman, rute tidak dipasang tombol
  - [ ] Hapus item PO/RO/draft (`destroyItem`): cek apakah menyentuh stok; bila ya, koreksi
  - [ ] Uji di salinan data produksi dulu (3C-6)
  - [ ] Tes: hapus lalu cek stok, kartu stok, laporan, dan rekap kembali tepat
- [ ] 4C.1 Master: kategori, produk, supplier, customer, salesman, bank, kas, payment, outlet, slider, pengeluaran
- [ ] 4C.2 Harga & promosi: outlet-prices, voucher, promotion
- [ ] 4C.3 Transaksi: penjualan, pembelian/PO, request-orders, delivery-orders, picking-lists, retur (pembelian/penjualan/refund), stok
- [ ] 4C.4 Ubah `GET /pembelian/{id}/destroy` → DELETE/POST
- [ ] 4C.5 Sembunyikan "hapus akun" profil untuk admin
**4D. Uji**
- [ ] 4D.1 Skenario: admin ajukan → superadmin setujui → terhapus; tolak; batal; target berubah sebelum disetujui; request ganda ditolak; (pembatalan = tolak oleh superadmin); admin tembak endpoint DELETE langsung (harus tetap pending, bukan terhapus); hapus yang menyentuh stok → stok & rekap terkoreksi

### TAHAP 5 — Excel formal & ringan (item 5) — **semua export gudang DAN toko** (K13)
**Dimulai setelah Tahap 1 (termasuk 1.7–1.8) dicek.** Urutan pengerjaan: 5.1 → 5.1b → 5.2 (kerangka) dulu, lalu migrasi per kelompok laporan (5.4–5.8), satu kelompok satu pengiriman.
Acuan: `todolist_export_gudang.md` & `todolist_export_toko.md` (belum pernah dikerjakan di kode ini). Identitas header diambil dari menu Setting (K21). Index laporan Gudang & Outlet dibuat semenarik dan serapi mungkin (digarap di Tahap 1.3, dipakai ulang di sini).
- [x] 5.1 Audit export: katalog Laporan hanya memuat **19 laporan** (14 gudang, 5 outlet). Export lama di luar katalog (`PenjualanExport`, `PenjualanKasirExport`, `PenjualanSupplierExport`, `PembelianExport`, `PembelianSupplierExport`, `PengeluaranExport`, `LabaRugiExport`, `ProductsExport`, dll.) tidak tampil di menu Laporan → **tidak dikerjakan** (keputusan K26; item 5.8b dihapus dari todolist). Status per laporan di tabel **5.1c**
- [x] 5.1b `App\Support\ReportIdentity`: baca `settings.json` (name, address, telp, email, website, logo) dengan fallback bila kosong/logo hilang — menunggu dicek
- [x] 5.2 `App\Exports\Support\BaseReportExport` (kerangka 1 sheet: logo + identitas, judul, periode, filter, dicetak oleh; header biru `#4472C4`; angka & tanggal asli; format `#,##0`; zebra; freeze pane; autofilter; baris TOTAL berumus `SUBTOTAL`; kolom kode/barcode dipaksa teks; print A4 landscape fit-to-width, ulang header, nomor halaman) + `TableReportSheet` (sheet generik: judul + kolom + sumber baris) — menunggu dicek
- [~] 5.3 Pola query ringan: filter tanggal memakai `ReportQuery::betweenDates` (sudah ada); dikerjakan **per laporan saat dimigrasi** (join/select kolom, bukan `whereDate` + `->get()` model penuh). Untuk Penjualan Outlet, query tetap di `OutletLaporanService::sales()` (memuat aturan retur) supaya angkanya sama persis — optimasi query-nya ditunda ke 5.10 setelah dibandingkan sebelum/sesudah
- [x] 5.4 Migrasi **Penjualan Outlet** (satu-satunya laporan penjualan di katalog): 2 sheet — `Penjualan` (satu baris per item, tanpa rowspan agar bisa difilter/pivot, TOTAL Qty & Subtotal) dan `Rekap` (total, per metode bayar, bon, voucher, setoran, jumlah transaksi); nama file `Laporan-Penjualan-Outlet_YYYYMMDD-YYYYMMDD.xlsx` — menunggu dicek
- [x] 5.5 **Styling Excel** laporan **Stok Gudang**, **Outlet: All Stock**, **Outlet: Minimal Stock** (Stock Opname sudah formal — tidak diubah, lihat 5.1c) — menunggu dicek
- [x] 5.6 **Styling Excel** laporan **PO / Pembelian Barang / Penerimaan Barang / Barang Masuk / Barang Keluar** — menunggu dicek
- [x] 5.7 **Styling Excel** laporan **PR / Pengiriman / Picking & Packing / Aktivitas Gudang / Pergerakan Stok** — menunggu dicek
- [x] 5.8 **Styling Excel** laporan **Retur Supplier / Retur Outlet (gudang) / Outlet: Rafaksi / Outlet: Retur** — menunggu dicek
  - Fokus **tampilan saja** (disepakati 2026-10-26): kerangka `BaseReportExport` dipakai semua laporan; **query & rumus perhitungan tidak diubah** (migrasi/optimasi query ditunda — lihat 5.3, 5.9, 5.10).
  - Kolom qty berisi teks seperti `10 (1 DUS)` dipecah jadi **Qty (angka asli)** + **Konversi (teks)** supaya bisa difilter/di-SUM.
  - Status stok diberi warna teks (Aman hijau / Kritis oranye / Habis & Expired merah); `Selisih` stok tampil dengan tanda +/−.
  - Export Outlet FromView (All Stock, Minimal Stock, Rafaksi, Retur) tidak lagi memakai rowspan → satu baris penuh per data, bisa difilter/pivot. View Blade lamanya (`exports/outlet/*.blade.php`) **tidak dihapus** (tidak terpakai Excel lagi, aman dibersihkan nanti).
  - Perbaikan kecil: kolom **Expired** di Laporan Penerimaan Barang sebelumnya membaca `expired_date` (kolom tidak ada di model `Stock`, selalu kosong) → kini membaca `expired_at`.
  - Nama file seragam, mis. `Laporan-PO_20261001-20261026.xlsx`, `Laporan-Stok-Gudang_20261026.xlsx`.

**5.1c Status 19 laporan di katalog (per 2026-10-26)**

| # | Laporan | Export class | Tipe awal | Status |
|---|---------|--------------|-----------|--------|
| 1 | Penjualan Outlet | `OutletPenjualanExport` | FromView | ✅ selesai (5.4) |
| 2 | Laporan PO | `LaporanPOExport` | polos | ✅ styling (5.6) |
| 3 | Pembelian Barang | `LaporanPembelianBarangExport` | polos | ✅ styling (5.6) |
| 4 | Penerimaan Barang | `LaporanPenerimaanBarangExport` | polos | ✅ styling (5.6) |
| 5 | Barang Masuk | `LaporanBarangMasukExport` | polos | ✅ styling (5.6) |
| 6 | Barang Keluar | `LaporanBarangKeluarExport` | polos | ✅ styling (5.6) |
| 7 | Stok Gudang | `StockExport` | polos | ✅ styling (5.5) |
| 8 | Stock Opname | `StockOpnameExport` | formal | ✅ sudah formal (logo + identitas + dokumen opname) — tidak diubah |
| 9 | Pergerakan Stok | `LaporanPergerakanExport` | polos | ✅ styling (5.7) |
| 10 | PR | `LaporanPRExport` | polos | ✅ styling (5.7) |
| 11 | Pengiriman | `LaporanPengirimanExport` | polos | ✅ styling (5.7) |
| 12 | Picking & Packing | `LaporanPickingPackingExport` | polos | ✅ styling (5.7) |
| 13 | Aktivitas Gudang | `LaporanAktifitasExport` | polos | ✅ styling (5.7) |
| 14 | Retur Supplier | `ReturSupplierExport` | semi-formal | ✅ styling (5.8) |
| 15 | Retur Outlet (gudang) | `ReturOutletExport` | semi-formal | ✅ styling (5.8) |
| 16 | Outlet: Minimal Stock | `OutletMinimalStockExport` | FromView | ✅ styling (5.5) |
| 17 | Outlet: All Stock | `OutletAllStockExport` | FromView | ✅ styling (5.5) |
| 18 | Outlet: Rafaksi | `OutletRafaksiExport` | FromView | ✅ styling (5.8) |
| 19 | Outlet: Retur | `OutletReturExport` | FromView | ✅ styling (5.8) |

Catatan: nama kelompok 5.5–5.8 mengikuti isi laporan di katalog; pembagian di atas menggantikan perkiraan awal.

- [ ] 5.9 *(ditunda ke sesi berikutnya)* Mekanisme antrean untuk rentang besar + notifikasi unduhan siap
- [ ] 5.10 *(ditunda ke sesi berikutnya, bersama optimasi query semua laporan)* Uji beban (rentang panjang, data banyak), cek memori & waktu, bandingkan sebelum/sesudah

---

## 5. Dependensi antar tahap

```
Tahap 0 (fondasi role/config/index)
   ├─> Tahap 1 (Laporan menu)    ─┐
   ├─> Tahap 2 (Penjualan)        ├─> Tahap 5 (Excel) memakai filter & config yang sama
   ├─> Tahap 3 (Dashboard)        │
   └─> Tahap 4 (Role + Approval) ─┘  (menyentuh semua; dikerjakan setelah halaman lain stabil)
```
- Tahap 2 & 3 berbagi aturan hitung penjualan (retur tidak dihitung) → buat sekali di Tahap 2, pakai di Tahap 3 & 5.
- Tahap 4C menyentuh hampir semua index view; kerjakan setelah Tahap 2 & 1 agar tidak bentrok merge.

---

## 6. Temuan tambahan (di luar 5 permintaan, tapi sebaiknya ditangani)

1. 🔴 **Rute publik tanpa login:** `GET /optimize-clear` dan `GET /storage-link` berada di luar grup `auth` di `routes/web.php` — siapa pun yang tahu URL bisa memicu `optimize:clear`. Minimal pindahkan ke belakang `role:superadmin` atau hapus saat produksi.
2. 🟠 `GET /pembelian/{id}/destroy` menghapus lewat GET (lihat 2.2).
3. 🟠 Tombol/rute hapus penjualan terbuka untuk role operasional (lihat 2.2 & K4).
4. 🟡 `penjualan/index` & `laporan-penjualan` mengakses relasi tanpa null-safe (`customer->name`) → rawan error 500 bila data referensi hilang.
5. 🟡 Kartu "Produk Terlaris" di dashboard menyesatkan karena berbasis pengiriman gudang→outlet, bukan penjualan.
6. 🔴 `AdminController`: `update()` memakai `$request->all()` (role bisa diganti sembarang), validasi role tanpa allow-list, `destroy()` tanpa penjagaan, `show()` berisi `dd()` (lihat 2.2).
7. 🟠 `getPenjualan($outlet_id)` & `getItems($id)` tidak memeriksa outlet → data penjualan outlet lain bisa dibaca role operasional.
8. 🟠 `/penjualan` menampilkan semua outlet untuk `staff-outlet` (lihat 2.3).
9. 🟡 Hapus penjualan = soft delete tanpa koreksi stok/kas (K15).
10. 🟠 `StockController@destroy` berisi `dd()` (hapus stok tidak aktif, K20); `PembelianController@destroy` & `RefundController@destroy` menghapus tanpa koreksi stok (K18).
11. 🟡 Banyak view memakai `<input type="date">` & tabel client-side penuh; seragamkan lewat komponen filter bersama.

---

## 7. File yang akan diubah per tahap (perkiraan)

Kode lengkap sudah ada, jadi tidak perlu kirim apa pun lagi. Per tahap, yang kukirim kembali **hanya file di bawah ini** (masing-masing file card, tanpa zip).

| Tahap | Perkiraan file berubah/baru |
|-------|----------------------------|
| 0 | `config/roles.php` (baru), `app/Models/User.php`, migration index (baru), `routes/web.php` |
| 1 | `resources/views/layouts/sidebar.blade.php`, `laporan/index.blade.php`, `laporan/outlet.blade.php`, komponen modal laporan (baru), `config/laporan.php` (baru), `LaporanController` (hanya bagian index) |
| 2 | `PenjualanController.php`, `app/Support/PenjualanFilter.php` (baru), `penjualan/index.blade.php`, view/route cetak rekap, `routes/web.php`, migration index |
| 3 | `DashboardController.php`, `app/Services/DashboardSalesService.php` (baru), `dashboard/index.blade.php` + partial, `products/index.blade.php` (tombol Min Stok), `routes/web.php` |
| 4 | `RoleMiddleware.php`, `AdminController.php`, `AdminRequest.php`, `PenjualanController.php`, migration + model + service + middleware `DeletionRequest` (baru), halaman Permintaan Hapus (baru), `sidebar.blade.php`, komponen `delete-button` (baru), semua `*/index.blade.php` yang punya tombol Hapus, `Http/Kernel.php`, `routes/web.php` |
| 5 | `app/Exports/BaseReportExport.php` (baru) + class Export per kelompok laporan, `LaporanController.php`, `app/Support/ReportQuery.php`, view export terkait, migration index |

---

---

## 8. Catatan versi
- v0.1 — Draft analisa dari zip parsial (views/routes/migrations).
- v0.2 — Diperiksa terhadap kode lengkap. Koreksi: hapus penjualan hanya soft delete; kata `'admin'` sudah dipakai di `PenjualanController`; `AdminController` rawan eskalasi role; `ReportQuery` sudah ada; 12 export sudah formal sedangkan 10 export gudang masih polos; `/penjualan` tidak dibatasi per outlet. Ditambahkan Cara Kerja & Log Progres.

---

- v0.3 — Jawaban K1–K16 dan B1–B5 dimasukkan: hapus semua modul wajib ACC (tanpa pengecualian), tanpa pembatalan oleh admin, koreksi stok wajib untuk semua hapus terkait stok, `StockController@destroy` tidak diaktifkan, identitas Excel dari menu Setting, Tahap 5 mencakup gudang & toko. Tabel rusak di Bagian 7 dibersihkan. Sisa 3 pertanyaan di 3B.

---

- v0.4 — K22–K24: closing kasir dihitung ulang & dicatat; dashboard staff-outlet/kasir hanya outlet sendiri; aplikasi produksi → ditambah Bagian 3C (aturan keselamatan), backup 0.1b, rute berbahaya jadi prioritas 0.5. Tidak ada pertanyaan terbuka.


---

- v0.5 — Tahap 1 ditambah 1.7 (bug tab kategori) dan 1.8 (index modern). Tahap 5 (Excel semua laporan) dijadwalkan langsung setelah Tahap 1 dicek.

---

- v0.6 — Tahap 5 dimulai: kerangka Excel (`ReportIdentity`, `BaseReportExport`, `TableReportSheet`) + migrasi Penjualan Outlet. Audit 5.1 memperkecil cakupan: katalog hanya 19 laporan; export lama di luar katalog jadi K26.
- v0.7 — Tahap 5 dilanjutkan, **fokus styling saja**: semua laporan di katalog selain Penjualan Outlet (17 laporan; Stock Opname tidak diubah karena sudah formal) memakai kerangka `BaseReportExport`. Migrasi query/antrean (5.3, 5.9, 5.10) sengaja ditunda.

- v0.8 — Item 5.8b (styling export di luar katalog) **dihapus** dari todolist (K26: tidak dikerjakan). Tahap 2 (Halaman Penjualan) dikerjakan: 2.1–2.7 selesai, 2.8 menunggu uji.

- v0.9 — Tahap 3 (Dashboard) dikerjakan: 3.1–3.6 selesai, 3.7 menunggu uji dengan data nyata. Role `admin` belum bisa membuka `/dashboard` karena grup rute masih memakai daftar role lama — dibereskan di Tahap 4A.1/4A.2c.

---

## 9. Log progres (di-update tiap tahap selesai)

| Tanggal | Tahap | Status | File yang dikirim |
|---------|-------|--------|-------------------|
| 2026-10-26 | Analisa v0.2 | Selesai, menunggu keputusan K1–K16 | `big_revisi_mmc.md` |
| 2026-10-26 | Todolist v0.3 | Selesai (dokumen saja) | `big_revisi_mmc.md` |
| 2026-10-26 | Todolist v0.4 | Selesai (dokumen saja); siap mulai Tahap 0 | `big_revisi_mmc.md` |
| 2026-10-26 | Tahap 0 (0.2, 0.3, 0.5 kode siap; 0.1b & 0.4 menunggu kamu) | Menunggu dicek | `config/roles.php`, `app/Models/User.php`, `routes/web.php`, `database/migrations/2026_10_26_000001_add_created_at_index_to_penjualans_table.php`, `big_revisi_mmc.md` |
| 2026-10-26 | Tahap 1 (1.1–1.6) | Menunggu dicek | `config/laporan.php`, `app/Support/LaporanCatalog.php`, `app/Http/Controllers/LaporanController.php`, `resources/views/layouts/sidebar.blade.php`, `resources/views/laporan/index.blade.php`, `outlet.blade.php`, `_index-layout.blade.php`, `_styles.blade.php`, `_scripts.blade.php`, `resources/views/components/report-modal.blade.php`, `big_revisi_mmc.md` |
| 2026-10-26 | Tahap 1 (1.7 bug tab, 1.8 index modern) | Menunggu dicek | `resources/views/laporan/_scripts.blade.php`, `_styles.blade.php`, `_index-layout.blade.php`, `resources/views/components/report-modal.blade.php`, `app/Support/LaporanCatalog.php`, `big_revisi_mmc.md` |
| 2026-10-26 | Tahap 5 (5.1, 5.1b, 5.2, 5.3 sebagian, 5.4) | Menunggu dicek | `app/Support/ReportIdentity.php`, `app/Exports/Support/BaseReportExport.php`, `app/Exports/Support/TableReportSheet.php`, `app/Exports/OutletPenjualanExport.php`, `app/Http/Controllers/LaporanController.php`, `big_revisi_mmc.md` |
| 2026-10-26 | Tahap 5 (5.5–5.8 styling Excel, tanpa ubah query) | Menunggu dicek | `app/Exports/Support/ReportHelpers.php`, `app/Exports/Support/RequestReportExport.php`, `app/Exports/LaporanPOExport.php`, `LaporanPRExport.php`, `LaporanPembelianBarangExport.php`, `LaporanPenerimaanBarangExport.php`, `LaporanBarangMasukExport.php`, `LaporanBarangKeluarExport.php`, `LaporanPengirimanExport.php`, `LaporanPickingPackingExport.php`, `LaporanAktifitasExport.php`, `LaporanPergerakanExport.php`, `StockExport.php`, `OutletAllStockExport.php`, `OutletMinimalStockExport.php`, `OutletRafaksiExport.php`, `OutletReturExport.php`, `ReturSupplierExport.php`, `ReturOutletExport.php`, `app/Http/Controllers/LaporanController.php`, `big_revisi_mmc.md` |
| 2026-10-26 | Tahap 2 (2.1–2.7 Halaman Penjualan; 5.8b dihapus) | Menunggu dicek (2.8 perlu diuji) | `app/Support/PenjualanFilter.php` (baru), `app/Http/Controllers/PenjualanController.php`, `app/Services/OutletLaporanService.php` (hanya `bonTotal`/`setoranTotal` jadi `public`), `resources/views/penjualan/index.blade.php`, `resources/views/penjualan/_detail.blade.php` (baru), `resources/views/penjualan/rekap-pdf.blade.php` (baru), `routes/web.php`, `big_revisi_mmc.md` |

| 2026-10-26 | Tahap 2 revisi (kartu modern, kolom Customer, rekap Excel) | Menunggu dicek | `app/Exports/PenjualanRekapExport.php` (baru), `app/Http/Controllers/PenjualanController.php`, `resources/views/penjualan/index.blade.php`, `app/Exports/Support/BaseReportExport.php` (tipe kolom `rupiah` baru), `app/Support/PenjualanFilter.php` (breakdown per shift); **hapus** `resources/views/penjualan/rekap-pdf.blade.php` (tidak dipakai lagi), `big_revisi_mmc.md` |
| 2026-10-28 | Tahap 3 (3.1–3.6 Dashboard penjualan; Min Stok pindah ke /product) | Menunggu dicek (3.7 perlu diuji) | `app/Services/DashboardSalesService.php` (baru), `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/ProductController.php`, `resources/views/dashboard/index.blade.php`, `_sales.blade.php` (baru), `_sales-script.blade.php` (baru), `_gudang.blade.php` (baru), `resources/views/products/index.blade.php`, `routes/web.php`, `big_revisi_mmc.md` |

**Checklist uji Tahap 2 (2.8)** — jalankan di lokal/staging dulu:
1. Buka `/penjualan` tanpa parameter → hanya nota **hari ini** (Asia/Jakarta); URL tetap bersih. Ganti preset → tabel, kartu, dan URL ikut berubah; salin URL ke tab baru → filter sama.
2. Pilih "Pilih tanggal" melewati tengah malam (mis. kemarin 22:00 s/d hari ini 02:00 → pakai tanggal kemarin s/d hari ini) → nota jam 23:59 dan 00:01 masuk ke tanggal masing-masing.
3. Bandingkan **jumlah transaksi** & **Grand Total** kartu dengan jumlah baris/total tabel pada filter yang sama (outlet, kasir, metode, status, pencarian).
4. Nota yang pernah diretur: badge "Ada retur", filter Status "Ada retur" hanya menampilkan nota tsb, baris info retur di bawah kartu muncul, detail expand menampilkan daftar retur.
5. Login sebagai `staff-outlet`/`kasir`: dropdown outlet terkunci; coba `?outlet_id=<outlet lain>` di URL, `/penjualan/data`, `/penjualan/summary`, `/penjualan/rekap`, `/penjualan/<id nota outlet lain>`, `/penjualan/<id>/print`, `/get-penjualan/<outlet lain>` → semua harus 403 / tetap outlet sendiri.
6. Download Rekap dengan filter aktif → file `.xlsx` terunduh (tanpa tab kosong), 2 sheet rapi; rentang sangat panjang (>50.000 nota) menampilkan pesan batas.
7. Bandingkan Rekap Ringkas dengan **Laporan Penjualan Outlet** untuk periode/outlet yang sama: bon & setoran harus sama persis; total penjualan bisa berbeda **hanya** pada nota yang diretur (laporan mengurangi retur per item, halaman ini menampilkan nilai nota + retur terpisah).
8. Data besar: uji dengan rentang 30 hari / setahun → tabel tetap cepat (butuh index `penjualans(created_at)` dari 0.3 sudah dijalankan).
9. Alur retur (`/refundPenjualan/create`) tetap bisa memilih outlet → nota → item (memakai `getPenjualan`/`getItems`).

**Checklist uji Tahap 3 (3.7)** — jalankan di lokal/staging dulu:
1. Login `superadmin`: `/dashboard` menampilkan Rekap Penjualan di atas; blok gudang tersembunyi, tombol "Tampilkan Info Gudang" membuka blok + grafik gudang tampil normal; klik kartu "Stok Kurang" membuka blok lalu menggulir ke tabelnya.
2. Login `admin-gudang`: hanya dashboard gudang (tanpa rekap penjualan), langsung terbuka; `GET /dashboard/sales-data` → 403.
3. Login `kasir` dan `staff-outlet`: hanya outlet sendiri (dropdown outlet tidak ada, ada label outlet); coba `/dashboard/sales-data?outlet_id=<outlet lain>` → 403; staff-outlet tetap melihat 2 kartu permintaan gudang.
4. Bandingkan **Omzet Periode** dan **Transaksi Periode** dengan **Laporan Penjualan Outlet** (Excel, sheet Rekap) untuk periode & outlet yang sama: harus sama; selisih hanya boleh muncul pada retur atas nota ber-voucher.
5. Pilih rentang yang memuat nota yang diretur dan nota dengan barang pengganti → omzet turun sebesar retur, bertambah sebesar pengganti; "Retur dikurangkan"/"pengganti" di kartu terakhir cocok.
6. Rentang > 62 hari → tren berubah per bulan; rentang > 366 hari → pesan galat jelas; "Hari Ini" lintas tengah malam mengikuti zona Asia/Jakarta.
7. Klik tombol refresh → data dimuat ulang tanpa cache; buka dua kali berturut-turut (tanpa refresh) → kali kedua cepat (cache).
8. Nota yang dihapus (soft delete) tidak ikut terhitung di kartu, grafik, maupun ranking.
9. `/product`: tombol **Pengaturan Min Stok** muncul untuk `superadmin`/`admin-gudang` saja; simpan penyesuaian → halaman reload dan "Produk di Bawah Min Stok" di dashboard gudang ikut berubah.
10. Data besar (30 hari – setahun) → halaman tetap terbuka cepat (kerangka dulu, data menyusul); cek jumlah query & waktu respons `/dashboard/sales-data` (≈45 query saat cache kosong) terhadap batas `max_user_connections` = 25.