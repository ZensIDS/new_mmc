# TODOLIST — Saldo / Top Up Customer (Catatan Mutasi)

Status: **Tahap A–D selesai (form C sudah jadi popup); E1 selesai, E2–E5 belum.** Diperbarui 18 Nov 2026 (ditambah aturan pengerjaan & rencana path file).

## 1. Kebutuhan (dari user)

- Saldo customer hanyalah **catatan sementara**, **tidak dipakai untuk transaksi di kasir**.
- **Top up** dan **pengurangan saldo** dilakukan **manual oleh superadmin**, masing-masing dengan **catatan**.
- Ada **report per customer seperti mutasi rekening**.
- Hanya **superadmin** yang boleh melihat dan memakai fitur ini; role lain tidak boleh melihat.

## 2. Temuan dari kode (yang relevan)

1. **Customer = tabel `users` dengan `role = 'customer'`.** Customer Umum (`username = customer-umum`) adalah bawaan penjualan.
2. **Route `/customer` (resource) terbuka untuk semua role staff** karena dipakai popup customer di kasir. Hanya *menu sidebar*-nya yang superadmin-only. Menyembunyikan menu tidak cukup: route saldo harus dijaga middleware sendiri.
3. **Kasir memuat `GET /customer` sebagai JSON** (`Cart.jsx`) dan `CustomerController@index` mengembalikan semua kolom `users`. Kalau saldo ditaruh sebagai kolom di `users`, saldo ikut terkirim ke browser kasir. Karena itu saldo disimpan di tabel terpisah.
4. Karena saldo tidak dipakai di kasir, **tidak ada perubahan** pada `CashierSaleService`, laci kas, `SaleDeletionService`, retur, maupun laporan penjualan. Fitur ini berdiri sendiri.
5. Sudah ada pola serupa di app ini: **Nota Fiktif** (superadmin-only, tidak menyentuh stok/kas/laporan, riwayat tersimpan). Saldo customer mengikuti pola yang sama: route `auth` + `role:superadmin`.

## 3. Rekomendasi desain

### 3.1 Tabel baru (tidak menyentuh tabel `users`)

**`customer_wallets`**
- `id`, `customer_id` (unique, FK users), `balance` (decimal 15,2 sesuai konvensi tabel lain di app, default 0), timestamps

**`customer_wallet_entries`** (buku mutasi, **tidak pernah di-edit atau dihapus**)
- `id`, `wallet_id`, `customer_id`
- `type`: `topup | pengurangan`
- `amount` (selalu positif, decimal 15,2), `balance_before`, `balance_after`
- `tanggal` (tanggal mutasi; default hari ini, boleh diubah, tidak boleh masa depan)
- `note` (**wajib**), `created_by`, timestamps

Salah input dikoreksi dengan **entri baru** (top up atau pengurangan), bukan edit/hapus, supaya mutasi tetap bisa diaudit.

### 3.2 Service `CustomerWalletService`
`topUp()` dan `kurangi()` di `DB::transaction` + `lockForUpdate` pada baris wallet (aman bila dua superadmin input bersamaan). Pengurangan yang membuat saldo minus ditolak.

### 3.3 Akses & menu
- Menu terpisah **"Saldo Customer"** di sidebar, khusus superadmin, route di grup `auth` + `role:superadmin` sendiri (bukan di resource `/customer`).
- Tombol "Saldo" di halaman detail customer hanya dirender untuk superadmin (`@if(auth()->user()->role === 'superadmin')`), menuju halaman Saldo Customer tadi.
- Alasan dipisah, bukan digabung ke `customer.index`: halaman customer dipakai banyak role, satu `@if` yang terlewat berarti saldo bocor.

### 3.4 Report mutasi per customer
Tampilan seperti mutasi rekening:

| Tanggal | Keterangan (catatan) | Top Up (masuk) | Pengurangan (keluar) | Saldo |
|---|---|---|---|---|

- Filter periode; baris pertama **Saldo Awal** periode, baris terakhir **Saldo Akhir** + total top up + total pengurangan.
- Cetak (halaman print) dan **export Excel** (dibuat rapi seperti Laporan Toko).
- Halaman daftar menampilkan saldo terkini semua customer + total saldo.

## 4. Keputusan yang perlu dikonfirmasi (rekomendasi di kolom kanan)

| # | Pertanyaan | Rekomendasi |
|---|---|---|
| K1 | Saldo boleh minus? | **Tidak.** Pengurangan yang melebihi saldo ditolak |
| K2 | Entri boleh di-edit/hapus? | **Tidak.** Koreksi lewat entri baru |
| K3 | Tanggal mutasi | Default hari ini, **boleh diubah** (untuk mencatat top up yang terjadi kemarin). Atau dikunci selalu hari ini? |
| K4 | Catatan | **Opsional** (boleh kosong) untuk top up dan pengurangan |
| K5 | Customer Umum | **Tidak punya saldo** (tidak muncul di daftar) |
| K6 | Hapus customer yang masih punya saldo | **Diblokir** selama saldo > 0 |
| K7 | `outlet:reset-data` | **Tidak menghapus** saldo (data customer, bukan data toko) |
| K8 | Export | Cukup **Excel + halaman print** |

## 5. Aturan pengerjaan

1. **Kirim hanya file yang dipakai** pada tahap itu (file baru + file yang berubah), bukan seluruh project.
2. **Setiap file dikirim sebagai file card sendiri-sendiri** (satu file satu card, ada tombol Download).
3. **Jangan di-zip** dan **jangan ditempel kodenya di chat**.
4. **Setiap file disebut path-nya** (relatif dari root project Laravel) di pesan pengantar, supaya jelas ditaruh di mana. Contoh: `app/Services/CustomerWalletService.php`.
5. File yang sudah ada dikirim **utuh** (siap timpa), bukan potongan.
6. Dikerjakan **bertahap sesuai urutan tahap**; satu tahap selesai dan dicek dulu sebelum lanjut.

### Rencana file per tahap (path bisa bergeser saat pengerjaan)

| Tahap | File | Status |
|---|---|---|
| A | `database/migrations/2026_11_18_000001_create_customer_wallets_table.php` | baru |
| A | `database/migrations/2026_11_18_000002_create_customer_wallet_entries_table.php` | baru |
| A | `app/Models/CustomerWallet.php` | baru |
| A | `app/Models/CustomerWalletEntry.php` | baru |
| A | `app/Models/User.php` | ubah (relasi `wallet()`) |
| A | `app/Services/CustomerWalletService.php` | baru |
| B | `app/Http/Controllers/CustomerWalletController.php` | baru |
| B | `routes/web.php` | ubah (grup superadmin `saldo-customer`) |
| B | `resources/views/layouts/sidebar.blade.php` | ubah (menu Saldo Customer) |
| B | `resources/views/saldo-customer/index.blade.php` | baru |
| B | `resources/views/customers/show.blade.php` | ubah (tombol Saldo, superadmin saja) |
| C | `app/Http/Requests/CustomerWalletEntryRequest.php` | baru |
| C | `resources/views/saldo-customer/form.blade.php` | **dibatalkan** — diganti popup (modal) di `index.blade.php` (revisi 18 Nov 2026); file ini dihapus |
| C | `app/Http/Controllers/CustomerWalletController.php` | ubah (simpan; alamat `/topup` & `/kurangi` kini membuka popup) |
| C | `resources/views/saldo-customer/index.blade.php` | ubah (popup Top Up / Pengurangan, 2 langkah: isi → konfirmasi) |
| C | `routes/web.php` | ubah (route topup/kurangi) |
| D | `resources/views/saldo-customer/mutasi.blade.php` | baru |
| D | `app/Services/CustomerWalletService.php` | ubah (`statement()`) |
| D | `app/Http/Controllers/CustomerWalletController.php` | ubah (mutasi, print, export) |
| D | `routes/web.php` | ubah (route mutasi, print, export) |
| D | `resources/views/saldo-customer/print.blade.php` | baru |
| D | `app/Exports/CustomerWalletMutasiExport.php` | baru |
| E | `app/Http/Controllers/CustomerController.php` | ubah (blokir hapus bila saldo > 0) |
| E | `app/Console/Commands/ResetOutletData.php` | ubah/cek (saldo tidak ikut terhapus) |

## 6. Tahapan

### Tahap A — Database, model, service  ✅ SELESAI (18 Nov 2026)
- [x] A1. Migration `customer_wallets` + `customer_wallet_entries`
- [x] A2. Model `CustomerWallet`, `CustomerWalletEntry` (ledger tanpa SoftDeletes, tanpa update/delete)
- [x] A3. `User::wallet()` (hasOne). Jangan masuk `$fillable`/`$appends`/JSON
- [x] A4. `CustomerWalletService` (topUp, kurangi) + penguncian baris + validasi saldo tidak minus

### Tahap B — Akses superadmin & halaman daftar  ✅ SELESAI (18 Nov 2026)
- [x] B1. `CustomerWalletController` + route grup `auth` + `role:superadmin` (prefix `saldo-customer`)
- [x] B2. Menu sidebar "Saldo Customer" hanya superadmin
- [x] B3. Halaman daftar: customer (tanpa Customer Umum), saldo terkini, pencarian, filter "punya saldo", total saldo. Tombol Top Up / Kurangi / Mutasi muncul otomatis begitu route Tahap C & D ada
- [x] B4. Tombol "Saldo" di `customers/show` hanya untuk superadmin
- [x] B5. Pastikan `GET /customer` (JSON & view) tidak membawa data saldo

### Tahap C — Top up & pengurangan  ✅ SELESAI (18 Nov 2026)
- [x] C0. **Revisi:** form berubah dari halaman penuh menjadi **popup** (simpel, chip nominal cepat, pratinjau saldo, konfirmasi di dalam popup). Isi popup di `index.blade.php`, `form.blade.php` dihapus
- [x] C1. Form Top Up: customer, nominal (> 0), tanggal, catatan opsional
- [x] C2. Form Pengurangan: customer, nominal (> 0 dan <= saldo), tanggal, catatan opsional
- [x] C3. Konfirmasi sebelum simpan + proteksi klik ganda
- [x] C4. Setiap entri menyimpan `created_by`

### Tahap D — Report mutasi per customer  ✅ SELESAI (18 Nov 2026)
- [x] D1. Halaman mutasi per customer (format tabel di 3.4), filter periode (default bulan ini; tombol Bulan ini / Semua), saldo awal/akhir. Saldo berjalan dihitung dari jumlah nominal bertanda menurut urutan tanggal lalu id (`CustomerWalletService::statement()`), jadi tetap benar walau ada mutasi yang tanggalnya diundur
- [x] D2. Halaman print (A4, `saldo-customer.mutasi.print`)
- [x] D3. Export Excel (`saldo-customer.mutasi.export`, `CustomerWalletMutasiExport`): header perusahaan, customer, periode, saldo awal/akhir, baris TOTAL top up & pengurangan
- Setelah simpan top up/pengurangan, user diarahkan ke halaman mutasi (periode mulai dari awal bulan mutasi yang baru dicatat)

### Tahap E — Housekeeping & uji
- [x] E1. Blokir hapus customer bila saldo > 0 (`CustomerController@destroy`) ✅ 18 Nov 2026
- [ ] E2. `outlet:reset-data` tidak menyentuh tabel saldo
- [ ] E3. Uji tiap role (admin, owner, kasir, staff-outlet, admin-gudang): menu tidak tampil **dan** URL `/saldo-customer` langsung ditolak
- [ ] E4. Uji network tab kasir: respon `/customer` tidak berisi saldo
- [ ] E5. Uji top up, pengurangan, pengurangan melebihi saldo (ditolak), dua superadmin input bersamaan, angka mutasi cocok dengan saldo akhir

## 7. Risiko utama

1. **Kebocoran saldo lewat `/customer` JSON**: dicegah dengan tabel terpisah (A3, B5, E4).
2. **Saldo minus / input bersamaan**: dicegah `lockForUpdate` + validasi (A4).
3. **Mutasi tidak bisa diaudit**: dicegah dengan ledger tanpa edit/hapus (A2).

## 8. Urutan pengerjaan

A → B → C → D → E. Tidak ada yang menyentuh jalur penjualan/kasir, jadi risiko ke transaksi berjalan nol.