# TODOLIST — Akun Customer, Saldo Top Up & Split Payment (MMC)

> Status: **v0.1 — analisa selesai, menunggu keputusan K1–K14 (semua sudah punya rekomendasi default) lalu ACC todolist.** Belum ada kode yang diubah.
> Todolist sebelumnya (`big_revisi_mmc.md`) dinyatakan **selesai** oleh user (2026-10-31).
> Aplikasi sudah **PRODUKSI** → semua tahap memakai aturan aman: backup dulu, migration tidak merusak data lama, uji di lokal/staging dulu.

## CARA KERJA (disepakati)

1. Dikerjakan **bertahap**, satu tahap pada satu waktu; tahap berikutnya jalan setelah tahap sebelumnya dicek.
2. Setiap tahap selesai, kirim **hanya file yang dibutuhkan** (baru/berubah), masing-masing sebagai file terpisah sesuai path project. **Bukan zip, tidak ditempel di chat.**
3. Perintah terminal ditulis dalam bentuk `docker-compose exec ...`.
4. Todolist (Bagian 5) dan Log Progres (Bagian 8) di-update tiap tahap selesai, file ini ikut dikirim.

---

## 1. Ringkasan fitur (permintaan user)

1. **Customer yang sudah ada bisa punya akun.** Default: username = nomor telepon, password = nomor telepon.
2. Di akun ada **riwayat belanja**, **saldo**, **riwayat top up**.
3. **Hanya superadmin** yang bisa menambah saldo (top up). User lain hanya **melihat** sisa saldo, riwayat transaksi, dan riwayat top up.
4. Di kasir, jika customer dipilih dan punya saldo → saldo **bisa dipakai membayar**.
5. **Split payment**: kasir **bebas** menentukan berapa dari saldo dan berapa dari tunai/metode lain.

---

## 2. Temuan analisa (dari kode di `Archive.zip`)

### 2.1 Yang sudah mendukung
- Customer = baris `users` dengan `role = 'customer'` (kolom `no_telp`, `alamat`, `username`). Sudah ada `Customer Umum` bawaan.
- Login sudah menerima **username** (`LoginRequest`: email / username), jadi login pakai nomor telepon tidak butuh sistem login baru.
- Role `customer` sudah dikenal (`config/roles.php`, `RoleMiddleware` mengarahkan ke `market.index`).
- Checkout kasir sudah di dalam `DB::transaction` (`CashierSaleService::checkout`) → cocok untuk potong saldo dengan kunci baris.
- Penghapusan nota sudah punya service sendiri (`SaleDeletionService`) → tempat pengembalian saldo.

### 2.2 Yang BELUM cocok / harus diperhatikan
| # | Temuan | Dampak |
|---|--------|--------|
| T1 | **Satu nota = satu metode bayar.** `penjualans` hanya punya `payment_method_id/name/reference`, `paid_amount`, `change_amount`. | Split payment butuh **tabel baru `penjualan_payments`** (banyak baris per nota). Ini perubahan terbesar. | -> lakukan sesuai rekomendasi mu tidak masalah ada perubahan besar yang penting data lama tetap aman.
| T2 | **Laci kasir** (`CashierSession::cashSales()`) menghitung **seluruh `grand_total`** nota yang metodenya tunai. | Dengan split, hanya **porsi tunai bersih** (diterima − kembalian) yang boleh masuk laci. Kalau tidak diubah, **closing kasir selisih**. Juga mempengaruhi `SaleDeletionService::recalculateClosedSession`. | -> ini sesuaikan dengan benar agar tidak terjadi selisih.
| T3 | Laporan per metode bayar membaca satu kolom: `DashboardSalesService` (`METHOD`), `OutletLaporanService::paymentMethod()`, `PenjualanFilter` (filter metode + `summary()` per metode), `PenjualanRekapExport`, dan 5 view (`print`, `print-faktur`, `show`, `_detail`, `rekap-pdf`). | Semuanya harus membaca `penjualan_payments` (dengan cadangan ke kolom lama untuk nota lama). | -> sesuaikan yang paling terbaik ya
| T4 | `CashierSaleService` menolak jika `paid_amount < grand_total`. | Bayar penuh dengan saldo (tanpa uang tunai) harus diizinkan → aturan validasi diubah. |
| T5 | `Transaction` dibuat setiap `payment_method_id` terisi. (Inilah akar bug nota non-tunai hilang di `/penjualan` yang baru diperbaiki.) | Pembayaran saldo **tidak boleh** menciptakan baris `transactions` baru yang menyamar sebagai marketplace. |
| T6 | Customer dibuat lewat **dua jalur berbeda**: form admin (password = no. telp, tanpa cek unik) dan popup kasir (no. telp opsional, username acak `cust-xxxx` bila kosong, password acak). Nomor telepon **tidak dinormalisasi** (`0812…`, `+62812…`, `62812…` dianggap beda). | Username = no. telp bisa **ganda/bentrok**, riwayat/saldo satu orang bisa terpecah. Perlu normalisasi + unik + laporan duplikat sebelum akun dibuat. |
| T7 | `User::canAccess()` meloloskan **admin** otomatis (`hasFullAccess`). | Top up **tidak boleh** memakai `canAccess`; harus cek `hasRole('superadmin')` langsung, supaya admin benar-benar tidak bisa. |
| T8 | Retur (`RefundPenjualan`, tukar barang + selisih) dan hapus nota belum tahu soal saldo. | Perlu aturan: hapus nota yang memakai saldo → saldo dikembalikan; retur dengan uang kembali → pilihan masuk saldo. |
| T9 | Customer yang login saat ini diarahkan ke `market.index` (marketplace lama). | Perlu halaman **Akun Saya** (read-only) dan route yang dikunci `role:customer` + query selalu dibatasi `auth()->id()` (cegah melihat data customer lain). |
| T10 | **View kasir (POS)** tidak ada di zip (hanya controller/service-nya). | Untuk Tahap E saya perlu file view/JS kasir (kemungkinan di `resources/views/outlets/*` / `public/js` / komponen). Mohon kirim saat tahapnya tiba. |

---

## 3. Rancangan data (usulan)

### 3.1 `customer_wallets` (satu baris per customer)
`id`, `customer_id` (unik, → `users.id`), `balance` (decimal 15,2, default 0, **tidak boleh negatif**), timestamps.
Dipakai sebagai cache saldo + titik kunci (`lockForUpdate`) agar dua kasir di dua outlet tidak memotong saldo yang sama bersamaan.

### 3.2 `wallet_transactions` (buku besar / ledger — **hanya tambah, tidak pernah diedit/dihapus**)
`id`, `customer_id`, `type` (`topup` | `payment` | `refund` | `adjustment` | `reversal`), `amount` (bertanda: + masuk, − keluar), `balance_after`, `penjualan_id` (nullable), `outlet_id` (nullable), `user_id` (pelaku: superadmin/kasir), `method` (cara terima uang top up: Tunai/Transfer/…), `reference`, `note`, `created_at`.
Saldo = jumlah seluruh baris. Salah input top up dikoreksi dengan baris lawan (`adjustment`) + alasan wajib, bukan hapus.

### 3.3 `penjualan_payments` (rincian bayar per nota)
`id`, `penjualan_id`, `type` (`cash` | `method` | `wallet`), `payment_method_id` (nullable), `payment_method_name`, `amount` (porsi nilai nota yang dibayar lewat baris ini), `received_amount` (khusus tunai: uang diterima), `reference`, timestamps.
- Total `amount` semua baris = `grand_total` nota.
- Kembalian hanya dari porsi tunai.
- Kolom lama di `penjualans` **tetap diisi** (kompatibilitas): `payment_method_name` = nama metode tunggal, atau `Split` bila lebih dari satu baris.
- Nota lama di-**backfill** satu baris per nota lewat command (dengan mode dry-run); laporan tetap punya cadangan baca kolom lama sampai backfill terverifikasi.

### 3.4 Tambahan di `users`
`must_change_password` (boolean, default false) — untuk memaksa ganti password pertama kali. Nomor telepon disimpan dalam format baku lokal (`08xxxxxxxxxx`).

---

## 4. Keputusan (semua ada rekomendasi; jika tidak dijawab, rekomendasi dipakai)

| # | Pertanyaan | **Rekomendasi (default)** |
|---|-----------|---------------------------|
| K1 | Format username/password default | Username = no. telp **format baku `08…`**; login juga menerima `+62…`/`62…` (otomatis diubah ke baku). Password awal = no. telp (sesuai permintaan). | -> oke setuju
| K2 | Password awal sama dengan no. telp itu lemah | **Wajib ganti password saat login pertama** (`must_change_password`). Akun customer hanya bisa **melihat** (tidak bisa transaksi), jadi risiko tetap rendah. | -> setuju juga
| K3 | Customer tanpa no. telp | **Tidak dibuatkan akun & tidak bisa top up** sampai no. telp diisi (no. telp = identitas unik). | -> setuju
| K4 | Customer Umum | **Tanpa akun dan tanpa saldo.** Panel saldo di kasir tidak muncul untuk Umum. | -> setuju
| K5 | Siapa boleh top up / koreksi | **Superadmin saja** (admin **tidak**), dicek langsung `hasRole('superadmin')`. Top up dua langkah (isi → konfirmasi nominal) + metode terima + catatan. | -> setuju hanya superadmin
| K6 | Top up tunai & kas laci | Top up **tidak masuk laci kasir/kas** dan **bukan penjualan**. Dicatat sendiri di laporan Saldo (uang diterima = kewajiban ke customer, baru jadi omzet saat dipakai belanja). | -> ya benar begitu
| K7 | Salah input top up | Tidak ada edit/hapus. Dikoreksi dengan **entri lawan + alasan wajib** (superadmin). | -> oke setuju
| K8 | Split payment | Struktur data mendukung banyak baris. **UI awal: saldo + 1 metode lain** (tunai / QRIS / dst.). Cukup untuk kebutuhan; tunai+QRIS dua-duanya bisa ditambah nanti tanpa ubah data. | -> oke aku setuju juga
| K9 | Aturan pakai saldo | Kasir **bebas** isi nominal saldo (0 s.d. min(saldo, total nota)); tidak boleh minus; kembalian **tidak pernah** keluar dari saldo. Tanpa PIN (skala kecil) — tapi tiap pemakaian tercatat di ledger (siapa, outlet, nota) dan terlihat di akun customer. PIN/OTP dicatat sebagai ide fase 2. | -> tidak perlu lagi ada pin dan otp karena tidak diperlukan
| K10 | Saldo kedaluwarsa / bisa ditarik tunai | **Tidak kedaluwarsa, tidak bisa ditarik tunai.** Pengecualian hanya lewat koreksi superadmin. | -> setuju
| K11 | Hapus nota yang memakai saldo | **Saldo otomatis dikembalikan** (baris `reversal`), sekaligus hitung ulang closing kasir (aturan K22 lama tetap berlaku). | -> setuju
| K12 | Retur dengan uang kembali | Tambah pilihan metode refund **"Saldo"** (kredit ke saldo customer); tidak otomatis. Bagian nota yang dibayar saldo tidak diproporsikan otomatis. | -> untuk retur sama seperti ketika hapus pennjualan
| K13 | Halaman akun customer | **Halaman baru `/akun`** (mobile-first, read-only): saldo, riwayat belanja, riwayat top up/mutasi. Customer yang login diarahkan ke sini; marketplace lama tetap ada. | -> oke sesuaikan ya.
| K14 | Hak lihat user internal | Semua role internal yang sudah bisa membuka data customer melihat saldo + riwayat (hanya baca). Kasir melihat saldo customer **hanya saat customer itu dipilih**. | -> oke setuju

---

## 5. TODOLIST (bertahap)

### Tahap 0 — Persiapan
- [ ] 0.1 User menjawab K1–K14 (atau menyetujui rekomendasi).
- [ ] 0.2 User mengirim **file view/JS kasir (POS)** (T10) sebelum Tahap E.
- [ ] 0.3 Backup database produksi sebelum tahap yang menyentuh migration (D, A-migrasi).

### Tahap A — Akun customer
- [ ] A.1 Helper normalisasi no. telp (`08…`) dipakai di form admin, popup kasir, dan login.
- [ ] A.2 Aturan **unik no. telp** di kedua jalur pembuatan customer (form admin + popup kasir); pesan galat jelas.
- [ ] A.3 Migration `users.must_change_password`.
- [ ] A.4 Customer baru otomatis: username = no. telp baku, password = no. telp, `must_change_password = true` (hanya bila no. telp ada — K3).
- [ ] A.5 Command `customers:provision-accounts` **--dry-run dulu**: normalisasi no. telp, set username/password default untuk customer lama, **laporan**: customer tanpa telp, no. telp ganda, bentrok dengan username user internal. Tidak menimpa apa pun yang bentrok. Jalankan nyata setelah laporan dicek.
- [ ] A.6 Login: input no. telp dinormalisasi; paksa halaman **ganti password** bila `must_change_password`.
- [ ] A.7 Route customer dikunci `role:customer`; customer **tidak bisa** menyentuh menu internal (uji akses).

### Tahap B — Dompet & ledger (inti saldo)
- [ ] B.1 Migration `customer_wallets` + `wallet_transactions` (+ indeks `customer_id, created_at`).
- [ ] B.2 Model `CustomerWallet`, `WalletTransaction` (ledger: blok update/delete di level model).
- [ ] B.3 `WalletService`: `topUp()`, `pay()`, `refund()`, `adjust()`, `reverse()` — semua dalam `DB::transaction` + `lockForUpdate` pada baris wallet, saldo tak boleh negatif, ledger terisi `balance_after`.
- [ ] B.4 `config/roles.php`: `wallet_topup => ['superadmin']` + helper `User::canTopUpWallet()` (cek langsung superadmin, bukan `canAccess`).
- [ ] B.5 Command `wallet:reconcile` (saldo cache vs jumlah ledger; hanya melaporkan).
- [ ] B.6 Activity log untuk semua mutasi saldo.

### Tahap C — Top up & tampilan saldo (sisi internal)
- [ ] C.1 `customers.show`: kartu **Saldo**, tab **Riwayat Top Up/Mutasi** (semua role internal, baca saja).
- [ ] C.2 `customers.show`: tombol **Top Up** + **Koreksi** hanya superadmin; form dua langkah (nominal → konfirmasi), metode terima, catatan; koreksi wajib alasan.
- [ ] C.3 `customers.index`: kolom **Saldo**.
- [ ] C.4 Server menolak top up dari non-superadmin (bukan sekadar menyembunyikan tombol).

### Tahap D — Struktur pembayaran split (fondasi data)
- [ ] D.1 Migration `penjualan_payments` + model `PenjualanPayment` + relasi `Penjualan::payments()`.
- [ ] D.2 Command `penjualan:backfill-payments` (dry-run → nyata, idempotent): satu baris per nota lama, nilai = `grand_total`, metode dari kolom lama. Verifikasi jumlah baris & total per metode **sama persis** dengan laporan lama.
- [ ] D.3 Helper pembaca terpadu (mis. `Penjualan::paymentLines()`): pakai `penjualan_payments`, cadangan ke kolom lama.

### Tahap E — Checkout split + UI kasir
- [ ] E.1 Endpoint JSON saldo customer (hanya untuk role kasir/internal, outlet dipaksa server) dipakai saat customer dipilih.
- [ ] E.2 `PenjualanController::store`: validasi baru (`wallet_amount`, metode lain, `paid_amount`) — total dihitung server, tidak percaya client.
- [ ] E.3 `CashierSaleService::checkout`: aturan T4 diubah → `wallet_amount ≤ saldo`, `wallet_amount ≤ grand_total`, sisa dibayar metode lain (`paid_amount ≥ sisa`), kembalian hanya dari tunai; tulis `penjualan_payments`, panggil `WalletService::pay()` di transaksi yang sama (gagal satu = gagal semua); kolom lama tetap diisi; **tidak** membuat `Transaction` palsu untuk porsi saldo (T5).
- [ ] E.4 UI kasir: panel **"Saldo Rp X"** hanya bila customer bukan Umum & saldo > 0; input nominal saldo (tombol *Pakai maksimal*), sisa otomatis, pilih metode untuk sisa, kembalian jelas. Bayar penuh saldo tanpa tunai diizinkan.
- [ ] E.5 Perlindungan: tombol bayar ganda ditahan, pesan galat saat saldo berubah di tengah jalan (mis. dipakai kasir lain).

### Tahap F — Laporan & cetak mengikuti split
- [ ] F.1 `CashierSession::cashSales()` & `summary()`: hanya **porsi tunai bersih** masuk laci; saldo/QRIS tidak.
- [ ] F.2 `SaleDeletionService::recalculateClosedSession` ikut aturan F.1.
- [ ] F.3 `PenjualanFilter`: filter metode & `summary()` per metode membaca `penjualan_payments`; kartu ringkasan menambah baris **Saldo**; jumlah nota tetap benar (tidak ganda karena join).
- [ ] F.4 `DashboardSalesService` & `OutletLaporanService` (metode bayar, setoran/bon): saldo bukan uang masuk laci.
- [ ] F.5 `PenjualanRekapExport` + `rekap-pdf`: kolom metode menampilkan rincian split.
- [ ] F.6 Struk & faktur (`print`, `print-faktur`, `show`, `_detail`): rincian bayar (Saldo / Tunai / QRIS), **sisa saldo**.

### Tahap G — Hapus nota & retur
- [ ] G.1 `SaleDeletionService`: kembalikan saldo (baris `reversal`) dalam satu transaksi dengan pengembalian stok; log mencatat nominal saldo yang dikembalikan.
- [ ] G.2 Retur penjualan: pilihan metode refund **Saldo** (kredit ke saldo), tercatat di ledger (`refund`).
- [ ] G.3 Uji: hapus nota split yang sesi kasirnya sudah closing → closing & saldo konsisten.

### Tahap H — Akun Saya (sisi customer)
- [ ] H.1 Route `/akun` (middleware `role:customer`); query **selalu** dibatasi `auth()->id()`.
- [ ] H.2 Halaman: saldo, riwayat belanja (rincian item & cara bayar), riwayat top up/mutasi; mobile-first, read-only.
- [ ] H.3 Halaman ganti password; `RoleMiddleware` home customer → `/akun`.

### Tahap I — Laporan Saldo
- [ ] I.1 Laporan **Saldo Beredar** (total saldo seluruh customer = kewajiban; per customer) + **Mutasi Top Up** per periode (superadmin/owner), unduh Excel dengan kerangka laporan yang ada.
- [ ] I.2 Tambah ke katalog `config/laporan.php` (menu Laporan).

### Tahap J — Uji menyeluruh & rilis
- [ ] J.1 Uji di lokal dengan salinan data produksi (`docker-compose exec app php artisan migrate`, command provision/backfill dry-run lalu nyata).
- [ ] J.2 Checklist uji (Bagian 7) lolos semua.
- [ ] J.3 Rilis produksi bertahap: backup → migrate → backfill → provision → cek laporan sebelum/sesudah sama.

**Urutan yang disarankan:** 0 → A → B → C → D → E → F → G → H → I → J.
Alasan: saldo (B–C) bisa dilihat & diisi dulu tanpa menyentuh kasir; struktur split (D) menyusul sebelum kasir (E); laporan/laci (F) **wajib** mengikuti E sebelum dipakai nyata supaya closing kasir tidak selisih.

---

## 6. Ide & rekomendasi tambahan (belum masuk tahap, tidak dikerjakan tanpa persetujuan)

1. **Ledger tidak bisa diedit** (sudah masuk rancangan) — paling penting untuk uang customer: semua angka bisa ditelusuri.
2. **Wajib ganti password pertama kali** (masuk K2) — menutup kelemahan password = no. telp.
3. **PIN saldo / OTP WhatsApp** saat kasir memotong saldo — fase 2, bila ada keluhan customer soal pemotongan tanpa sepengetahuan.
4. **Struk memuat sisa saldo** (masuk F.6) — transparansi tanpa biaya tambahan.
5. **Notifikasi WA** top up & pemakaian saldo — fase 2.
6. **Bonus top up** (mis. top up 500 rb bonus 25 rb) — bisa ditambah lewat tipe ledger `bonus` bila diperlukan; sengaja tidak dimasukkan dulu.
7. **Batas top up per transaksi** — opsional; saat ini pengaman = konfirmasi dua langkah.
8. Saldo sebagai **kewajiban** (bukan omzet): omzet diakui saat dipakai belanja (masuk K6 & Tahap I).

---

## 7. Checklist uji (ringkas; dilengkapi per tahap)

1. Customer lama → akun dibuat; login pakai `08…`, `+62…`, `62…` semuanya masuk; wajib ganti password; customer tanpa telp tidak dibuatkan akun; no. telp ganda masuk laporan.
2. Admin & kasir **tidak bisa** top up (tombol hilang **dan** POST ditolak 403); superadmin bisa; koreksi wajib alasan.
3. Top up Rp 100.000 → saldo 100.000; ledger 1 baris; customer melihat di `/akun`; user lain hanya baca.
4. Kasir: pilih customer saldo 100.000, belanja 150.000, pakai saldo 100.000 + tunai 50.000 → nota `paid`, saldo 0, laci hanya +50.000 (− kembalian bila ada).
5. Belanja 80.000 dibayar saldo penuh tanpa tunai → berhasil; belanja saldo 100.000 + QRIS 50.000 → laci tidak bertambah.
6. Pakai saldo melebihi saldo / melebihi total → ditolak; dua kasir memakai saldo yang sama bersamaan → salah satu ditolak, saldo tidak minus.
7. Hapus nota split → saldo kembali, stok kembali, closing kasir dihitung ulang & konsisten.
8. Retur dengan refund ke Saldo → ledger `refund`.
9. `/penjualan`, kartu ringkasan, dashboard, laporan outlet, rekap Excel: angka sama dengan sebelum fitur untuk periode lama; nota split tampil benar; tidak ada nota hilang/ganda.
10. Customer A tidak bisa membuka data customer B (ubah ID di URL → 403/404).
11. `docker-compose exec app php artisan wallet:reconcile` → tidak ada selisih.

---

## 8. Log progres

| Tanggal | Tahap | Status | File |
|---------|-------|--------|------|
| 2026-10-31 | Analisa & penyusunan todolist v0.1 | Menunggu keputusan & ACC | `todolist_saldo_customer.md` |