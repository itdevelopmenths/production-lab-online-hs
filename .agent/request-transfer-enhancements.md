# Spesifikasi Arsitektur: Request & Transfer Enterprise Enhancements
**Proyek:** Heaven Scent Enterprise — Production Lab Online  
**Dokumen:** `.agent/request-transfer-enhancements.md`  
**Fitur:** State Priority Status Color, Surat Jalan Standar Cetak, Stok Tersedia Realtime Gudang Asal, Pembatalan Pra-Approval dengan Modal & Alasan Batal, Fitur Edit Dokumen Draft, Anti-Fraud Quality Receipt Invariant (Bilangan Bulat Murni), dan Location-Based Access Control (LBAC) Matriks Mutasi.

---

## 1. Problem Statement & User Requirements
1. **Status Priority Colors pada Index (`resources/views/request-transfer/index.blade.php`)**:
   - Kolom status dengan *state priority badges* berstyling kontras dan konsisten (Gray untuk Draft, Amber untuk Diajukan, Blue untuk Disetujui, Indigo untuk Diproses, Purple/Gold untuk Dikirim, Emerald untuk Selesai, Rose/Red untuk Dibatalkan).
2. **Dokumen Surat Jalan (Printable Layout)**:
   - Dokumen pengiriman resmi dengan layout terstandarisasi A4 print media.
3. **Informasi Qty Tersedia dari Gudang Asal di Formulir Input (`create.blade.php` & `edit.blade.php`)**:
   - Menampilkan stok fisik riil yang tersedia di gudang asal dan peringatan visual seketika jika permintaan melebihi saldo.
4. **Pembatalan Dokumen Transfer dengan Modal UI & Form Input Alasan (`index.blade.php` & `show.blade.php`)**:
   - Menggantikan native browser popup `confirm()` dengan DevScore Modal UI `<x-modal name="openCancelModal">`.
   - Menambahkan input wajib `alasan` (textarea, min 5 karakter, max 500 karakter) dengan live counter.
   - Kolom `alasan_batal` disimpan pada tabel `request_transfers` dan dicatat ke riwayat `OperationalAudit`.
   - Alert banner khusus ditampilkan pada halaman detail jika dokumen telah dibatalkan.
5. **Fitur Edit Dokumen pada Tahap Draft**:
   - Mengizinkan revisi gudang asal, gudang tujuan, jenis mutasi, catatan, dan daftar barang sebelum dokumen disetujui.
6. **Anti-Fraud QC Receipt Guard & Bilangan Bulat Murni (`show.blade.php` & `RequestTransferController::doReceive`)**:
   - Menegakkan invarian mutlak: `qty_baik + qty_rusak = qty_dikirim`.
   - Mencegah fraud pelaporan (misal dikirim 1000, diisi bagus 800 rusak 0 lolos).
   - Seluruh kuantitas di modul Request & Transfer wajib integer bilangan bulat murni (`step="1" min="0"`), menolak input desimal/pecahan pada sisi frontend maupun backend.
7. **Location-Based Access Control (LBAC) Matriks Mutasi (`create.blade.php`, `edit.blade.php`, `LocationScopeService`)**:
   - Memvalidasi wewenang inisiasi transfer berdasarkan gudang yang ditugaskan ke user (LBAC).
   - Mengunci dropdown jenis mutasi dan memfilter gudang asal/tujuan secara reaktif sesuai wewenang peran (contoh: staff Gudang bahan baku tidak dapat membuat transfer antar fulfillment).
   - Memproteksi direct URL access pada `show`, `edit`, `update`, `suratJalan`, dan `transition` agar user restricted tidak dapat mengakses dokumen di luar gudang wewenangnya.

---

## 2. Matriks Otorisasi Mutasi & LBAC (JENIS_MATRIX)

| Jenis Mutasi | Label | Tipe Asal | Tipe Tujuan | Aturan Inisiator LBAC | Peran yang Diizinkan |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `req_bahan` | Request Bahan Baku | `bahan_baku` | `operasional` | `either` (Lab atau Gudang Bahan Baku) | Lab / Operasional, Gudang Bahan Baku, Manager |
| `retur_bahan` | Retur Bahan Baku | `operasional` | `bahan_baku` | `asal` (Wajib pemilik Gudang Operasional) | Lab / Operasional, Manager |
| `kirim_produk_jadi` | Kirim Produk Jadi | `operasional` | `fulfillment` | `asal` (Wajib pemilik Gudang Operasional) | Lab / Operasional, Manager |
| `antar_fulfillment` | Transfer Antar Fulfillment | `fulfillment` | `fulfillment` | `asal` (Wajib pemilik Gudang Fulfillment Asal) | Staff Fulfillment, Manager |
| `retur_produk_jadi` | Retur Produk Jadi | `fulfillment` | `operasional` | `asal` (Wajib pemilik Gudang Fulfillment Asal) | Staff Fulfillment, Manager |

---

## 3. Arsitektur Teknis Solusi

### A. Database & Model
- Migration: `database/migrations/2026_10_29_000001_add_alasan_batal_to_request_transfers_table.php` menambahkan kolom `alasan_batal` (text, nullable).
- Model `RequestTransfer`:
  - `$fillable`: menambahkan `alasan_batal`.
  - `public const JENIS_MATRIX`: metadata tipe asal, tujuan, inisiator, dan label.

### B. Service & Authorization Layer
- `LocationScopeService`:
  - `getAllowedTransferJenis(User $user): array`: menyaring jenis mutasi yang diizinkan untuk inisiator.
  - `validateTransferAuthority(User $user, string $jenis, Gudang $asal, Gudang $tujuan): ?string`: memvalidasi validitas pasangan gudang dan akses LBAC.
  - `canViewTransfer(User $user, RequestTransfer $rt): bool`: memeriksa apakah user memiliki wewenang pada gudang asal atau gudang tujuan dokumen.

### C. Controller & Transactional Validation
- `RequestTransferController`:
  - `create` & `edit`: menginjeksi `allowedJenis`, `allGudang`, `accessibleIds`, dan `jenisMatrix` ke view. Menolak akses jika user tidak memiliki akses LBAC ke gudang manapun.
  - `store` & `update`: validasi `gudang_asal_id` dan `gudang_tujuan_id` sebagai `required`, `exists:gudang,id`, `different:gudang_asal_id`. Memanggil `validateTransferAuthority` (abort 403 untuk pelanggaran LBAC).
  - `doReceive`: mewajibkan semua item terdaftar disubmit, memvalidasi integer murni (`ctype_digit`), dan menegakkan `(int)$qtyBaik + (int)$qtyRusak === (int)$qtyKirim`.
  - `doProcessOrShip`: memvalidasi integer murni pada kuantitas pengiriman.
  - `doCancel`: membatasi pembatalan pada status pra-pengiriman (`draft`, `diajukan`, `disetujui`), menyimpan `alasan_batal`, dan mencatat ke `OperationalAudit` dengan metadata `alasan_pembatalan`.
  - `suratJalan`, `show`, `transition`: diproteksi dengan `canViewTransfer`.

### D. Frontend Layer (Blade + Alpine.js)
- **show.blade.php**:
  - Modal pembatalan `<x-modal name="openCancelModal">` dengan input alasan, live counter karakter (min 5, max 500), dan tombol konfirmasi destruktif.
  - Banner alert merah berpenjelasan alasan pembatalan jika dokumen berstatus `dibatalkan`.
  - Alpine form state validasi reaktif: indikator status pas/selisih real-time, tombol "Hitung Rusak Otomatis", dan penguncian tombol submit jika total tidak pas.
  - Input bilangan bulat murni (`step="1" min="0"`) dan pencegahan tombol `.` dan `,`.
- **create.blade.php & edit.blade.php**:
  - Dropdown `jenis` hanya menampilkan opsi yang diizinkan berdasarkan LBAC user.
  - Dropdown `gudang_asal_id` dan `gudang_tujuan_id` reaktif tersaring sesuai tipe gudang dan LBAC inisiator.
  - Input `qty_diminta` berupa integer murni (`step="1" min="1" placeholder="0"`).
- **index.blade.php**:
  - Event-driven modal pembatalan terintegrasi dengan DataTable actions via CustomEvent `open-cancel-modal`.

---

## 3. Delapan (8) Fase Eksekusi
1. **Discovery & Requirements**: Analisis 5 poin kebutuhan user dan spesifikasi Surat Jalan sesuai lampiran gambar.
2. **System Architecture**: Perancangan rute `edit`, `update`, `surat-jalan`, `stok-tersedia`, dan relasi data.
3. **Sprint Planning**: Breakdown implementasi backend controller, Blade views, CSS print, dan UI reactivity.
4. **Development Execution**: Implementasi kode pada controller, routes, Blade templates, dan styling.
5. **Testing & QA**: Penulisan feature tests PHPUnit untuk edit draft, pembatalan pra-approval, render Surat Jalan, dan query stok gudang.
6. **Security & Compliance**: Validasi wewenang RBAC, pembatasan edit strictly-draft, proteksi CSRF.
7. **Deployment**: Asset build (`npm run build`) dan verifikasi kelayakan cetak.
8. **Documentation**: Pembaruan walkthrough dan laporan eksekutif.
