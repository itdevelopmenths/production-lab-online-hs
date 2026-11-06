  # Design Document: Role-Adaptive Supply Chain Dashboard Engine

## 1. Intent & The Why
Tujuan dari rancangan ini adalah mentransformasi halaman Beranda (`/`) dari tampilan dashboard umum yang statis menjadi sebuah **Supply Chain Operational Cockpit Berbasis Peran (*Role-Adaptive*)**. Setiap peran di rantai pasok laboratorium Heaven Scent (Manager, Purchasing, Gudang, Operasional Lab, dan Fulfillment) memiliki fokus harian dan tanggung jawab yang sangat berbeda. Dashboard baru ini dirancang sebagai pusat kendali aksi cepat (*action-oriented*), memprioritaskan pekerjaan yang sedang terhambat (*blocked*) pada peran tersebut, memangkas waktu navigasi, mengedepankan densitas data tinggi (*high density*), fluid layout (`w-full`), dan tema monokrom hitam-putih tanpa ornamen visual tidak berguna (*zero AI-slop*).

---

## 2. Goals, Non-Goals, & Anti-Goals

### Goals
- **Role-Adaptive Data & UI:** Menyajikan KPI, antrean pekerjaan mendesak (*urgent queue*), dan feed aktivitas yang 100% relevan untuk peran pengguna yang aktif (Manager, Purchasing, Gudang, Operasional, Fulfillment).
- **Fast 3-Second Actionability:** Pengguna dapat langsung mengetahui pekerjaan apa yang butuh tindakannya dalam 3 detik pertama setelah login dan dapat membuka layar tindak lanjut dengan 1 klik.
- **Kueri Terisolasi & Efisien:** `DashboardController` mengeksekusi kueri terpisah per peran melalui dedicated role builders (`buildManagerData()`, `buildPurchasingData()`, dll.) guna mencegah N+1 query dan menghindari pemborosan kueri data yang tidak ditampilkan.
- **Strict B&W Minimalist Supply Chain UX:** Mempertahankan palet monokrom neutral/zinc yang konsisten dengan desain aplikasi yang telah direfaktor, menggunakan aksen warna semantik (*danger*, *warning*, *success*) hanya untuk penanda urgensi/SLA.
- **100% Fluid Layout:** Memanfaatkan seluruh lebar layar secara fleksibel (`w-full max-w-none`) baik pada layar laptop maupun ultrawide desktop.

### Non-Goals
- Tidak mengubah alur kerja atau mesin status backend dari modul Purchasing, Produksi, Stok, atau Logistik yang sudah stabil.
- Tidak melakukan migrasi skema database baru (memanfaatkan seluruh tabel, kolom, dan relasi yang sudah ada di sistem).
- Tidak menyediakan fitur drag-and-drop widget customizer (agar antarmuka tetap standar, terstruktur, dan ringan).

### Anti-Goals
- Tidak ada grafik hiasan atau chart ilustratif yang tidak memberi nilai aksi operasional.
- Tidak ada teks sambutan basa-basi, avatar ilustrasi kartun, atau kartu kosong dengan placeholder generic.
- Tidak ada pemuatan seluruh data global untuk kemudian hanya disaring dengan `display: none` di CSS.

---

## 3. Constraints
- **Framework & Libraries:** Laravel 11/12 Blade, Alpine.js, Tailwind CSS v4 (`resources/css/app.css`), DataTables / Heroicons SVG.
- **Role Identification:** Memanfaatkan `auth()->user()->roleName()`, `$user->hasRole()`, dan `$user->can()`.
- **Database Safety:** Semua kueri relasi wajib menggunakan `with([...])` spesifik kolom untuk mematuhi larangan `LazyLoadingViolationException` yang diuji di `ViewRenderingSafetyTest`.
- **Test Compatibility:** Semua pengujian yang memeriksa Beranda (seperti `ViewRenderingSafetyTest::test_authenticated_routes_render_safely`) harus tetap lulus 100%.

---

## 4. Approaches Considered

### Approach A: Dynamic Multi-Section Cockpit with Role-Builder Engine (SELECTED)
- **Deskripsi:** `DashboardController` mendeteksi peran utama pengguna dan memanggil fungsi builder data spesifik peran tersebut. View `dashboard.blade.php` menyusun informasi dalam struktur terpadu 3-Tier:
  - **Tier 1:** Contextual KPI Strip (4-5 metrik kartu padat).
  - **Tier 2:** Action Center / Urgent Queue (Antrean prioritas selebar layar yang butuh tindakan segera).
  - **Tier 3:** Operational Activity Streams (Grid 2 kolom untuk monitoring proses yang berjalan).
- **Trade-offs:** Memerlukan pemisahan logika controller yang rapi, namun menghasilkan waktu muat tercepat dan pengalaman pengguna operasional terbaik.

### Approach B: Completely Separate Blade Files per Role
- **Deskripsi:** Memecah view menjadi `dashboard/manager.blade.php`, `dashboard/purchasing.blade.php`, dst.
- **Trade-offs:** Menyebabkan duplikasi kode markup kartu dan komponen tabel antar file yang menyulitkan pemeliharaan jangka panjang.

### Approach C: User-Configurable Drag-and-Drop Widget Grid
- **Deskripsi:** Pengguna bebas menambah dan menggeser widget sesuka hati.
- **Trade-offs:** *Over-engineered*, memperlambat database, dan bertentangan dengan prinsip kesederhanaan dan kepatuhan SOP rantai pasok industri.

---

## 5. Builder Decisions & Technical Clarifications (Resolved from Review)

Berikut adalah ketetapan teknis yang diputuskan pengembang (*developer's call*) untuk memastikan integritas data dan performa tanpa mengubah skema tabel database:

1. **Definisi "SKU Kritis Produk Jadi" (Fulfillment):**
   - *Ketetapan:* Menggunakan produk berjenis `tipe = 'produk_jadi'` dengan saldo stok fisik di bawah atau sama dengan `min_stok` (`saldo <= min_stok`), diurutkan berdasarkan defisit kuantitas terbesar. Tidak memerlukan kolom klasifikasi baru.
2. **Definisi "Batch Segera Masuk" (Fulfillment):**
   - *Ketetapan:* Menggunakan jumlah batch laboratorium yang sedang berstatus `release` (aktif diracik/maserasi di lab), karena batch ini merupakan pasokan fisik yang akan segera masuk ke fulfillment setelah selesai.
3. **Warehouse Scoping (Gudang & Fulfillment):**
   - *Ketetapan:* Memanfaatkan `LocationScopeService` bawaan aplikasi. Jika pengguna memiliki hak akses terbatas (*restricted*), data pergerakan, antrean bongkar muat, dan stok otomatis dibatasi ke gudang yang ditugaskan kepada pengguna (`$user->gudangs`). Jika berakses global (*manager*), data mencakup seluruh fasilitas.
4. **Formula "Komitmen Tagihan / Kewajiban Pembayaran":**
   - *Ketetapan:* Dihitung berdasarkan sisa liabilitas keuangan riil yang belum terbayar dari PO yang belum berstatus lunas (`SUM(grand_total - nominal_terbayar)`), sehingga menyajikan angka akurat kewajiban kas keluar (*cash outflow liability*).
5. **Kesiapan Bahan BOM (Operasional Lab):**
   - *Ketetapan:* Dihitung per batch rencana terhadap ketersediaan saldo bahan di gudang lab. Pada StatBox Tier 1, nilai utama menampilkan jumlah batch rencana yang seluruh bahan formulanya sudah cukup dan siap rilis (`X Batch Siap`), dengan subteks `Y Kurang Bahan`.

---

## 6. Detailed Architecture & Role Specifications

### 6.1 Peran: Manager (`manager`)
- **Fokus:** Kontrol otorisasi, persetujuan biaya, mitigasi risiko keterlambatan dan kerusakan stok.
- **Tier 1 - KPI Strip:**
  1. *PO Menunggu Approval:* Jumlah PO pada stage `approval`, status `diajukan`, atau memiliki revisi komitmen menunggu persetujuan.
  2. *Request Transfer Pending:* Permintaan mutasi barang yang menunggu persetujuan manager.
  3. *Batch Produksi Aktif:* Jumlah batch yang sedang dalam proses pencampuran / rilis di lab.
  4. *Defect Rate Mutasi Bulan Ini:* Rasio kerusakan barang saat penerimaan transfer bulan berjalan.
  5. *Item Perlu Order Kritis:* Rekomendasi restock bahan baku berstatus ORDER dari Analisa Stok.
- **Tier 2 - Action Center (Approval & Otorisasi):**
  - Antrean gabungan yang membutuhkan tanda tangan pimpinan:
    - PO approval & revisi komitmen harga/qty.
    - Permintaan transfer antar gudang yang diajukan.
  - Kolom: Dokumen, Tanggal, Pengaju / Supplier, Nilai / Volume, Jenis Tindakan, Tombol Review Instan.
- **Tier 3 - Activity Streams:**
  - *Kolom Kiri: Komitmen Pembayaran PO:* Ringkasan PO terbit yang membutuhkan pembayaran/DP dengan sisa tagihan.
  - *Kolom Kanan: Peringatan Stok Kritis:* Bahan baku & produk jadi yang menipis di bawah safety stock.

### 6.2 Peran: Purchasing (`purchasing`)
- **Fokus:** Pengadaan bahan baku, manajemen vendor, kepatuhan lead time, dan jadwal kedatangan.
- **Tier 1 - KPI Strip:**
  1. *Item Perlu Order (Lokal):* Bahan baku lokal yang mencapai titik pemesanan ulang (ROP).
  2. *Item Perlu Order (Impor):* Bahan kimia / konsentrat impor yang memerlukan lead time panjang.
  3. *PO Dalam Proses:* PO aktif dalam tanggung jawab purchasing (`perencanaan` s.d. `pengemasan`).
  4. *PO Terlambat SLA:* PO aktif yang durasi tahapnya melampaui rata-rata lead time historis supplier.
  5. *Total Komitmen Tagihan:* Sisa kewajiban pembayaran dari PO aktif yang belum lunas.
- **Tier 2 - Action Center (Antrean PO Mendesak):**
  - PO yang dikembalikan untuk revisi oleh manager, PO yang butuh konfirmasi supplier, dan PO siap rilis resmi.
- **Tier 3 - Activity Streams:**
  - *Kolom Kiri: Rekomendasi Restock Teratas:* Daftar prioritas SKU dari analisa stok dengan tombol cepat Buat PO.
  - *Kolom Kanan: Pemantauan Lead Time Vendor:* Pengiriman berjalan dengan status waktu tahapan vs benchmark.

### 6.3 Peran: Gudang (`gudang`)
- **Fokus:** Penerimaan fisik barang, bongkar muat, inspeksi defect, dan pengiriman surat jalan mutasi.
- **Tier 1 - KPI Strip:**
  1. *Kedatangan Siap Unloading:* PO yang sedang dikirim / tiba di gudang untuk dibongkar fisik.
  2. *Menunggu Input Sistem:* Barang datang yang telah dibongkar tapi belum diposting ke saldo stok.
  3. *Transfer Perlu Dikirim:* Dokumen transfer draft dari gudang terkait yang siap diberangkatkan.
  4. *Transfer In-Transit Masuk:* Mutasi antar gudang yang sedang dalam perjalanan menuju gudang terkait.
  5. *Stok Menipis Lokal:* Item di gudang yang berada di bawah stok minimum.
- **Tier 2 - Action Center (Antrean Bongkar Muat & Surat Jalan):**
  - Kedatangan ekspedisi vendor tiba + transfer barang keluar yang harus segera diterbitkan surat jalan.
- **Tier 3 - Activity Streams:**
  - *Kolom Kiri: Antrean Unloading & QC Barang Datang:* Rincian nomor PO, vendor, jumlah colly/volume.
  - *Kolom Kanan: Log Penerimaan Barang Rusak (Defect Monitoring):* Riwayat pencatatan barang rusak terkini.

### 6.4 Peran: Operasional Lab (`operasional`)
- **Fokus:** Kesiapan formula BOM, pencampuran batch laboratorium, dan permintaan bahan baku.
- **Tier 1 - KPI Strip:**
  1. *Batch Rencana:* Batch baru yang sedang dirumuskan.
  2. *Batch Rilis (In-Progress):* Batch aktif yang sedang dalam proses pencampuran / maserasi di lab.
  3. *Kesiapan Bahan BOM:* Jumlah batch rencana yang seluruh bahan formulanya sudah cukup di lab.
  4. *Permintaan Bahan Aktif:* Request bahan ke gudang pusat yang sedang diajukan/diproses.
  5. *Batch Selesai Siap Kirim:* Batch laboratorium yang telah selesai dan siap didistribusikan.
- **Tier 2 - Action Center (Kesiapan Batch & Alokasi Bahan):**
  - Batch rencana yang bahan bakunya telah lengkap dan siap dirilis untuk pencampuran laboratorium.
- **Tier 3 - Activity Streams:**
  - *Kolom Kiri: Progress Batch Produksi Lab:* Rincian batch, produk wangi, volume liter/pcs, status pengerjaan.
  - *Kolom Kanan: Status Permintaan Bahan ke Gudang:* Tiket permintaan bahan baku lab ke gudang pusat.

### 6.5 Peran: Fulfillment (`fulfillment`)
- **Fokus:** Ketersediaan stok produk jadi toko, penerimaan batch dari lab, dan transfer antar cabang/hub.
- **Tier 1 - KPI Strip:**
  1. *Total Saldo Produk Jadi:* Akumulasi kuantitas fisik produk jadi di hub fulfillment.
  2. *Transfer Masuk dari Lab:* Pengiriman batch produk jadi dari lab yang sedang dalam perjalanan/tiba.
  3. *Transfer Antar Toko:* Mutasi distribusi produk antar gerai/channel.
  4. *SKU Kritis Fast-Moving:* Produk wangi dengan stok mendekati buffer batas bawah (`saldo <= min_stok`).
  5. *Batch Segera Selesai:* Batch laboratorium yang sedang berstatus `release` (pasokan yang akan segera masuk).
- **Tier 2 - Action Center (Penerimaan Produk Jadi & Pemenuhan Hub):**
  - Penerimaan fisik produk jadi dari lab dan antrean transfer antar toko.
- **Tier 3 - Activity Streams:**
  - *Kolom Kiri: Ketersediaan Produk Jadi:* Level stok terkini vs safety stock per botol/varian.
  - *Kolom Kanan: Monitoring Distribusi Barang Selesai:* Riwayat mutasi produk jadi masuk.

---

## 7. Implementation Technical Architecture

### Controller Architecture (`app/Http/Controllers/DashboardController.php`)
```php
class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalisaService $analisa,
        private readonly StokService $stokService
    ) {}

    public function index()
    {
        $user = auth()->user()->loadMissing(['roles', 'gudangs']);
        $role = $user->roleName() ?? 'operasional';

        $data = match ($role) {
            'manager' => $this->buildManagerData($user),
            'purchasing' => $this->buildPurchasingData($user),
            'gudang' => $this->buildGudangData($user),
            'operasional' => $this->buildOperasionalData($user),
            'fulfillment' => $this->buildFulfillmentData($user),
            default => $this->buildDefaultData($user),
        };

        return view('dashboard', array_merge([
            'user' => $user,
            'role' => $role,
        ], $data));
    }
}
```

### View Architecture (`resources/views/dashboard.blade.php`)
- Contextual `<x-page-header>` dengan aksi tombol relevan per peran.
- Komponen `<x-stat-box>` adaptif pada Tier 1.
- Komponen kartu antrean aksi cepat pada Tier 2 (`<x-card title="..." :noPadding="true">`).
- Grid 2 kolom `<x-card>` pada Tier 3.
- Menjaga dropdown *Ganti Peran (Dev)* di sidebar untuk pengujian instan antar peran.

---

## 8. Verification Strategy
1. **PHPUnit Automated Tests:**
   - Menambahkan pengujian di `tests/Feature/ViewRenderingSafetyTest.php` untuk login sebagai kelima peran (`manager`, `purchasing`, `gudang`, `operasional`, `fulfillment`) dan memverifikasi dashboard berhasil me-render data role spesifik tanpa error atau *LazyLoadingViolationException*.
   - Menjalankan seluruh test suite (`php artisan test`) untuk memastikan 355/355 pengujian tetap lulus.
2. **Kompilasi & Build:**
   - `php artisan view:cache` (0 syntax errors).
   - `npm run build` (Vite build sukses).
3. **Manual Verifikasi:**
   - Verifikasi antarmuka visual monokrom untuk kelima peran melalui Dev Role Switcher.
