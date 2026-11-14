# Blueprint & Rancangan Arsitektur: Purchasing & Accounts Payable (AP) Revamp
**Proyek:** Heaven Scent Enterprise — Production Lab Online  
**Target Dokumen:** `.agent/purchasing-revamp.md`  
**Standar Rekayasa:** SOLID Principles, Domain-Driven Clean Architecture, Role-Based Privacy (Separation of Concerns), High Precision Financial Integrity  

---

## 1. Executive Summary & Latar Belakang Kebutuhan

Siklus pengadaan bahan baku (*Purchasing*) dan penerimaan gudang (*Inbound Logistics*) pada sistem Heaven Scent Enterprise memerlukan peningkatan arsitektur agar dapat mengakomodasi proses bisnis riil yang kompleks, akurat secara finansial, dan aman dari sisi wewenang data.

### Analisis Kebutuhan & Matriks Solusi Arsitektural (Traceability Matrix)

| No | Kebutuhan Pengguna (User Request) | Masalah / Keterbatasan Saat Ini | Solusi Arsitektural & Best Practice |
|---|---|---|---|
| **1** | Input harga bahan baku cukup Total Harga Kotor, sistem otomatis menghitung **Harga per unit (HPP)** dengan memperhitungkan biaya tambahan (diskon, PPN, ongkir, adjustment). | Saat ini hanya ada input tunggal `harga_total` tanpa rincian komponen biaya logistik/pajak, sehingga HPP satuan belum mencerminkan *landed cost*. | Implementasi formulasi *Landed Cost Allocation*: $\text{Net Total} = \text{Total} - \text{Diskon} + \text{PPN} + \text{Ongkir} + \text{Adjustment}$, lalu $\text{HPP/Unit} = \frac{\text{Net Total}}{\text{Qty}}$. Dihitung real-time di UI (Alpine.js) dan divalidasi presisi di Service Layer. |
| **3** | Nomor Invoice / Dokumen bisa diisi manual (nomor vendor/internal). Jika kosong, sistem membuat otomatis. | Nomor PO saat ini wajib auto-generated (`nextNoPo()`), tidak ada kolom nomor invoice vendor eksternal. | Tambahkan field `no_invoice` (nullable). Jika kosong di form, trigger generator sekuensial terproteksi *pessimistic lock*. Jika diisi, divalidasi keunikan formatnya. |
| **4** | Pembayaran termin: jumlah periode, jatuh tempo tiap termin, dan status (*lunas*, *parsial*, *belum lunas*, *overdue*). | Pembayaran hanya mencatat transaksi bayar historis di tabel `payments`, tanpa jadwal/rencana termin tagihan (*payment schedules*). | Buat entitas `PurchaseOrderTermin` (Jadwal Termin Tagihan) yang melacak target nominal, jatuh tempo, akumulasi bayar, dan status dinamis (termasuk deteksi otomatis `overdue`). |
| **5** | Seluruh input nominal memakai *separator thousand* (titik ribuan Rupiah). | Sebelumnya input bertipe `number` polos tanpa pemisah ribuan. | Perluas arsitektur helper thousand separator (`formatThousand`, `updateHarga`, `handleKeyDown`) menjadi komponen/direktif Blade/Alpine yang dapat digunakan di semua kolom nominal biaya, termin, dan pembayaran. |
| **6** | Pada halaman detail (`show.blade.php`), tambahkan *Progress Status Layout*. | Saat ini status hanya berupa *badge* statis di pojok atas tanpa visualisasi alur siklus hidup dokumen. | Buat komponen UI **Workflow Stepper** interaktif yang memvisualisasikan tahapan: *Draft* $\rightarrow$ *Diajukan* $\rightarrow$ *Disetujui* $\rightarrow$ *Kirim ke Gudang* $\rightarrow$ *Diterima Gudang* $\rightarrow$ *Selesai / Lunas*. |
| **7** | Role Gudang ketika status "dikirim ke gudang" hanya boleh akses konfirmasi barang datang. **TIDAK BOLEH melihat harga dan status pembayaran** di seluruh halaman purchasing. | DataTables dan halaman detail saat ini menampilkan nilai rupiah dan panel AP kepada siapa pun yang memegang `purchasing.view`. | Terapkan *Data Masking & Conditional Authorization*: backend memotong/menyembunyikan field finansial pada response DataTables dan Blade view jika user tidak memiliki wewenang finansial (`purchasing.price.view`). Role gudang hanya menerima metadata produk dan kuantitas fisik. |
| **8** | Tab khusus pembayaran untuk role Purchasing (berisi semua data pembayaran yang lunas & belum lunas). | Seluruh data PO bercampur dalam satu tabel DataTables tanpa kategorisasi fokus kerja tim purchasing/keuangan. | Implementasikan sistem **Dual-Tab Interface** di `purchasing/index.blade.php`: Tab 1 "Daftar PO Pengadaan", Tab 2 "Jadwal & Status Tagihan (AP)", dilengkapi filter status pembayaran (*Belum Lunas*, *Parsial*, *Lunas*, *Overdue*). |
| **9** | Di tabel index: tambah kolom Tanggal PO, Tanggal Aktual Tiba, serta fitur edit tanggal PO dan ETA. | Tabel index hanya memiliki `tanggal` dan `status`. ETA dan tanggal fisik tiba dari `barang_datang` belum ditampilkan dan tidak bisa diedit cepat. | Tambahkan kolom `tanggal_po`, `eta`, dan `tgl_aktual_tiba` (hasil relasi `barang_datang.tanggal_terima`). Buat endpoint aman `PUT /purchasing/{po}/quick-dates` dengan UI Quick-Edit Modal. |
| **12** | Tambahkan Lokasi PO (Gudang Tujuan Pengiriman). | `gudang_id` baru dipilih saat barang tiba di gudang. PO tidak memiliki rekam jejak fasilitas gudang mana yang menjadi destinasi pesanan. | Tambahkan relasi `gudang_id` pada `purchase_orders` (Lokasi PO Tujuan). Dipilih saat pembuatan PO, ditampilkan di detail & index, serta otomatis menjadi default saat konfirmasi penerimaan. |
| **13** | Konfirmasi Barang Datang: jika ada selisih, tampilkan kolom selisih dan wajibkan kolom keterangan selisih. | Saat ini form penerimaan hanya ada field `qty_diterima` tanpa perbandingan deviasi atau catatan audit alasan barang kurang/rusak. | Hitung diferensial $\Delta = \text{qty\_diterima} - \text{qty\_po}$. Jika $\Delta \neq 0$, UI menampilkan badge selisih (Kurang/Lebih) dan membuka field *Keterangan Selisih* (wajib diisi jika terdapat deviasi). |
| **14** | Validasi & error message yang presisi pada pembayaran yang lebih atau kurang, terutama status lunas saat masih ada sisa. | Validasi saat ini hanya `numeric, gt:0` tanpa membandingkan nominal bayar dengan sisa tagihan, membuka celah *overpayment* dan status lunas fiktif. | Terapkan validasi bisnis ketat di `PurchasingPaymentService`: tolak pembayaran yang melebihi sisa tagihan, tolak skema pelunasan jika nominal kurang dari sisa, dan cegah penutupan dokumen jika sisa tagihan $> 0$. |

---

## 2. Prinsip Rekayasa Perangkat Lunak (Architecture Principles)

### A. SOLID Principles dalam Domain Pengadaan & Keuangan
1. **Single Responsibility Principle (SRP):**
   - `PurchasingCostCalculator`: Layanan khusus kalkulasi matematika biaya tambahan, alokasi landed cost, dan HPP satuan per bahan baku.
   - `PurchasingPaymentService`: Bertanggung jawab penuh atas transaksi pembayaran, alokasi termin, validasi batas tagihan, dan update status pelunasan.
   - `PurchasingController`: Bertindak murni sebagai koordinator HTTP, mendelegasikan logika bisnis ke Service layer.
2. **Open/Closed Principle (OCP):**
   - Komponen biaya tambahan (PPN, Diskon, Ongkir, Adjustment) dirancang terstruktur dalam skema atribut yang dapat diperluas di masa depan (misal: bea masuk cukai atau asuransi kargo) tanpa merombak tabel transaksi utama.
3. **Interface Segregation Principle (ISP):**
   - Pemisahan otorisasi wewenang logistik dan finansial: `purchasing.receive` (khusus fisik barang) terisolasi dari `purchasing.pay` dan `purchasing.price.view` (khusus finansial).
4. **Dependency Inversion Principle (DIP):**
   - Controller bergantung pada abstraksi layanan (`StokService`, `PurchasingPaymentService`), memastikan kode dapat diuji (*testable*) dengan unit test mock.

### B. Prinsip Pemisahan Tugas (Separation of Duties - SoD) & Privasi Data
Dalam standar akuntansi dan ERP industri (SAP, Odoo):
- **Staf Gudang** bertanggung jawab atas *kuantitas fisik* dan *kondisi material* (Quality & Quantity Control). Mereka tidak boleh mengetahui harga beli dari supplier untuk mencegah potensi manipulasi, konflik kepentingan vendor, atau kebocoran data rahasia HPP lab.
- **Staf Purchasing / Keuangan** bertanggung jawab atas negosiasi komersial, biaya pengiriman, perpajakan, dan pencatatan kas/bank.
- **Arsitektur Proteksi:** Proteksi harga dilakukan di tingkat **Backend Payload** (DataTables API tidak mengirimkan kolom harga jika user tidak berwenang), bukan sekadar manipulasi tampilan CSS `display:none` yang masih rentan diintip via Network Console.

---

## 3. Desain Model Data & Skema Database

```
┌────────────────────────────────────────────────────────┐
│                    purchase_orders                     │
├────────────────────────────────────────────────────────┤
│ id: UUID (PK)                                          │
│ no_po: VARCHAR(30) [UNIQUE]                            │
│ no_invoice: VARCHAR(50) [NULLABLE, INDEX]  ◄── NEW     │
│ supplier_id: BIGINT (FK -> supplier)                   │
│ gudang_id: BIGINT (FK -> gudang)           ◄── NEW     │
│ tanggal: DATE (Tanggal PO)                             │
│ eta: DATE [NULLABLE] (Estimasi Tiba)                   │
│ sumber_dana: VARCHAR(50)                               │
│ status: VARCHAR(25)                                    │
│ skema_bayar: VARCHAR(20) [cash/tempo/termin] ◄── NEW   │
│ subtotal_produk: DECIMAL(18,2)             ◄── NEW     │
│ diskon_total: DECIMAL(18,2)                ◄── NEW     │
│ ppn_nominal: DECIMAL(18,2)                 ◄── NEW     │
│ ongkos_kirim: DECIMAL(18,2)                ◄── NEW     │
│ adjustment: DECIMAL(18,2)                  ◄── NEW     │
│ grand_total: DECIMAL(18,2)                 ◄── NEW     │
│ status_pembayaran: VARCHAR(20)             ◄── NEW     │
│ (belum_lunas, parsial, lunas, overdue)                 │
└──────────┬───────────────────────────────┬─────────────┘
           │ 1                             │ 1
           │ N                             │ N
┌──────────▼────────────────────┐   ┌──────▼──────────────────────────┐
│     purchase_order_items      │   │     purchase_order_termins      │
├───────────────────────────────┤   ├─────────────────────────────────┤
│ id: UUID (PK)                 │   │ id: UUID (PK)       ◄── NEW TBL │
│ po_id: UUID (FK)              │   │ po_id: UUID (FK)                │
│ produk_id: BIGINT (FK)        │   │ termin_ke: INT (1, 2, ..)       │
│ qty: DECIMAL(15,2)            │   │ tanggal_tempo: DATE             │
│ harga_total: DECIMAL(18,2)    │   │ nominal_tagihan: DECIMAL(18,2)  │
│ diskon: DECIMAL(18,2)  ◄── NEW│   │ nominal_dibayar: DECIMAL(18,2)  │
│ ppn: DECIMAL(18,2)     ◄── NEW│   │ status: VARCHAR(20)             │
│ ongkir: DECIMAL(18,2)  ◄── NEW│   │ (belum_lunas,parsial,lunas,over)│
│ adjustment: DECIMAL(18)◄── NEW│   │ keterangan: VARCHAR(255)        │
│ hpp_per_satuan: DECIMAL(18,4) │   └─────────────────────────────────┘
└──────────┬────────────────────┘
           │ 1
           │ N
┌──────────▼──────────────────────────┐
│         barang_datang_items         │
├─────────────────────────────────────┤
│ id: UUID (PK)                       │
│ bardat_id: UUID (FK)                │
│ po_item_id: UUID (FK)               │
│ qty_diterima: DECIMAL(15,2)         │
│ selisih: DECIMAL(15,2)      ◄── NEW │
│ keterangan_selisih: TEXT    ◄── NEW │
└─────────────────────────────────────┘
```

### 1. Migrasi 1: Penambahan Kolom pada `purchase_orders`
```php
Schema::table('purchase_orders', function (Blueprint $table) {
    $table->string('no_invoice', 50)->nullable()->after('no_po')->index();
    $table->foreignId('gudang_id')->nullable()->after('supplier_id')->constrained('gudang')->nullOnDelete();
    $table->string('skema_bayar', 20)->default('cash')->after('sumber_dana'); // cash, tempo, termin
    $table->decimal('subtotal_produk', 18, 2)->default(0)->after('skema_bayar');
    $table->decimal('diskon_total', 18, 2)->default(0)->after('subtotal_produk');
    $table->decimal('ppn_nominal', 18, 2)->default(0)->after('diskon_total');
    $table->decimal('ongkos_kirim', 18, 2)->default(0)->after('ppn_nominal');
    $table->decimal('adjustment', 18, 2)->default(0)->after('ongkos_kirim');
    $table->decimal('grand_total', 18, 2)->default(0)->after('adjustment');
    $table->string('status_pembayaran', 20)->default('belum_lunas')->after('grand_total')->index();
});
```

### 2. Migrasi 2: Penambahan Kolom Biaya & HPP pada `purchase_order_items`
```php
Schema::table('purchase_order_items', function (Blueprint $table) {
    $table->decimal('diskon', 18, 2)->default(0)->after('harga_total');
    $table->decimal('ppn', 18, 2)->default(0)->after('diskon');
    $table->decimal('ongkir', 18, 2)->default(0)->after('ppn');
    $table->decimal('adjustment', 18, 2)->default(0)->after('ongkir');
    $table->decimal('hpp_per_satuan', 18, 4)->default(0)->after('adjustment');
});
```

### 3. Migrasi 3: Pembuatan Tabel `purchase_order_termins`
```php
Schema::create('purchase_order_termins', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('po_id')->constrained('purchase_orders')->cascadeOnDelete();
    $table->unsignedSmallInteger('termin_ke');
    $table->date('tanggal_tempo');
    $table->decimal('nominal_tagihan', 18, 2);
    $table->decimal('nominal_dibayar', 18, 2)->default(0);
    // belum_lunas / parsial / lunas / overdue
    $table->string('status', 20)->default('belum_lunas');
    $table->string('keterangan', 255)->nullable();
    $table->timestamps();

    $table->index(['po_id', 'termin_ke']);
    $table->index('status');
    $table->index('tanggal_tempo');
});
```

### 4. Migrasi 4: Penambahan Selisih pada `barang_datang_items`
```php
Schema::table('barang_datang_items', function (Blueprint $table) {
    $table->decimal('selisih', 15, 2)->default(0)->after('qty_diterima');
    $table->text('keterangan_selisih')->nullable()->after('selisih');
});
```

---

## 4. Logika Bisnis & Formula Perhitungan (Domain Logic)

### A. Kalkulasi Landed Cost & HPP per Satuan
Setiap baris bahan baku memiliki input:
- $Q$ = Qty Bahan
- $H_{kotor}$ = Total Harga Kotor (Gross Total)
- $D$ = Diskon Baris Bahan
- $P$ = PPN Baris Bahan
- $O$ = Alokasi Ongkos Kirim
- $A$ = Alokasi Adjustment (Biaya Lain / Pembulatan)

**Formula Perhitungan:**
$$\text{Harga Total Bersih (Net Total)} = H_{kotor} - D + P + O + A$$
$$\text{Harga Per Satuan (HPP)} = \frac{\text{Net Total}}{Q}$$

*Aturan Tambahan:*
- Jika diinput di tingkat Header PO (misal ongkos kirim gelondongan Rp 500.000 untuk 3 bahan berbeda), sistem menyediakan opsi *Prorata Otomatis* berdasarkan rasio bobot subtotal masing-masing produk:
$$\text{Ongkir}_i = \text{Ongkir Total} \times \left( \frac{H_{kotor, i}}{\sum H_{kotor}} \right)$$

### B. Penomoran Dokumen (Invoice & PO)
- **Input Manual:** Jika pengguna menginput `no_invoice`, lakukan pengecekan format (`string, max:50`) dan keunikan jika diperlukan.
- **Auto-Generated:** Jika kosong, buat otomatis dengan format standar akuntansi:
  $$\text{Format:} \quad \text{INV/PO/}\{YYYY\}\{MM\}/\{000X\}$$
- **Pessimistic Lock:** Gunakan transaksi database atomik agar tidak terjadi *race condition* (nomor ganda) saat dua staf membuat PO bersamaan.

### C. State Machine & Status Pembayaran (AP Aging)
Status pembayaran dievaluasi berdasarkan relasi pembayaran dan jadwal termin:

```
                  ┌──────────────────────────────┐
                  │    PO Baru Dibuat            │
                  │   Status: BELUM LUNAS        │
                  └──────────────┬───────────────┘
                                 │
           ┌─────────────────────┴─────────────────────┐
           ▼                                           ▼
┌──────────────────────────────┐             ┌──────────────────────────────┐
│  Terjadi Pembayaran Sebagian │             │  Lewat Jatuh Tempo & Belum   │
│      Status: PARSIAL         │             │        Lunas Penuh           │
└──────────┬───────────────────┘             │       Status: OVERDUE        │
           │                                 └──────────────┬───────────────┘
           │                                                │
           └─────────────────────┬──────────────────────────┘
                                 │
                                 ▼ (Sisa Tagihan == 0)
                  ┌──────────────────────────────┐
                  │       Status: LUNAS          │
                  └──────────────────────────────┘
```

**Kriteria Status Tiap Termin & Header PO:**
1. **Belum Lunas:** `nominal_dibayar == 0` dan `now() <= tanggal_tempo`.
2. **Parsial:** `0 < nominal_dibayar < nominal_tagihan` dan `now() <= tanggal_tempo`.
3. **Overdue:** `nominal_dibayar < nominal_tagihan` dan `now() > tanggal_tempo`.
4. **Lunas:** `nominal_dibayar >= nominal_tagihan`.

### D. Validasi Finansial Ketat (Payment Guardrails)
1. **Tolak Overpayment:**
   Jika $(\text{Nominal Bayar Baru} + \text{Total Dibayar}) > \text{Grand Total}$, lempar exception validasi:
   `"Nominal pembayaran (Rp X) melebihi sisa tagihan PO (Rp Y). Kelebihan bayar tidak diizinkan."`
2. **Tolak Underpayment pada Skema Pelunasan:**
   Jika skema dipilih adalah `'pelunasan'`, namun nominal bayar yang diinput $< \text{Sisa Tagihan}$:
   `"Skema 'Pelunasan Penuh' harus melunasi sisa tagihan tepat sebesar Rp Y. Gunakan skema 'Termin' jika membayar sebagian."`
3. **Pencegahan Sisa Tagihan saat Ditandai Lunas:**
   Jika ada aksi/trigger yang menandai dokumen lunas secara eksplisit tetapi sisa tagihan masih $> 0$, lemparkan error fatal:
   `"Tidak dapat menandai status Lunas. Masih terdapat sisa kewajiban pembayaran sebesar Rp Y."`

### E. Kebijakan Privasi & Masking Data untuk Role Gudang
- Buat permission baru: `purchasing.price.view`.
- Role `manager` dan `purchasing` mendapatkan wewenang ini.
- Role `gudang` **TIDAK** mendapatkan wewenang ini.
- **Di Backend Controller (`PurchasingController::data`):**
  ```php
  $canSeePrice = auth()->user()->can('purchasing.price.view');
  
  return DataTables::eloquent($query)
      ->addColumn('total_nilai', fn($po) => $canSeePrice ? number_format($po->grand_total, 0, ',', '.') : '—')
      ->addColumn('sisa', fn($po) => $canSeePrice ? number_format($po->sisaTagihan(), 0, ',', '.') : '—')
      ->addColumn('status_pembayaran', fn($po) => $canSeePrice ? $po->status_pembayaran_badge : '—');
  ```
- **Di Blade View (`purchasing/show.blade.php`):**
  Seluruh kolom `Harga Total`, `HPP / Unit`, `Total Nilai`, panel `Status Pembayaran (AP)`, dan form bayar dibungkus dengan `@can('purchasing.price.view')`.
  Role gudang hanya melihat: Data PO, Supplier, Gudang Tujuan, Tabel Item (Produk, Qty PO, Qty Diterima), dan Form Konfirmasi Barang Datang.

---

## 5. Rancangan Komponen Antarmuka Pengguna (UI/UX Specification)

### A. Progress Status Stepper di `purchasing/show.blade.php`
Visual modern horizontal stepper dengan status penanda:
1. **Draft PO** (Icon Dokumen)
2. **Diajukan** (Icon Pengajuan / Pesawat Kertas)
3. **Disetujui** (Icon Checklist / Stampel Approval)
4. **Dikirim ke Gudang** (Icon Truk Ekspedisi)
5. **Diterima Fisik** (Icon Gudang / Box Checklist)
6. **Selesai / Lunas** (Icon Centang Emas & Badge Finansial)

*Kondisi Khusus:* Jika status `'dibatalkan'`, stepper berubah menjadi warna rose/merah dengan penanda dokumen dihentikan.

### B. Dual-Tab Navigation di `purchasing/index.blade.php`
Dua tab interaktif tanpa reload halaman:
- **Tab 1: Pengadaan PO (Procurement View)**
  - Kolom: No PO / No Inv, Supplier, Gudang Lokasi, Tanggal PO, ETA, Tanggal Aktual Tiba, Status PO, Nilai Tagihan (Masked), Sisa Tagihan (Masked), Aksi.
  - Quick Edit Modal untuk Tanggal PO & ETA.
- **Tab 2: Tagihan & Pembayaran AP (Accounts Payable View)** — *Khusus wewenang finansial*
  - Kartu Ringkasan AP: Total Utang Berjalan, Jatuh Tempo Minggu Ini, Tagihan Overdue, Total Sudah Terbayar.
  - Kolom: No PO, Supplier, Skema Bayar, Total Tagihan, Terbayar, Sisa, Jatuh Tempo Terdekat, Status AP (*Belum Lunas / Parsial / Lunas / Overdue*), Aksi Bayar Cepat.

### C. Form Konfirmasi Barang Datang dengan Selisih Otomatis di `show.blade.php`
- Form input `qty_diterima` menghitung selisih real-time via Alpine.js:
  - Jika $\text{qty\_diterima} < \text{qty\_po}$: Tampil label `Kurang: -X kg` warna amber/rose.
  - Jika $\text{qty\_diterima} > \text{qty\_po}$: Tampil label `Lebih: +X kg` warna blue.
  - Jika $\text{qty\_diterima} == \text{qty\_po}$: Tampil label `Sesuai: Pas` warna emerald.
- Jika ada selisih, muncul kolom `Keterangan Alasan Selisih` yang wajib diisi (misal: "Barang rusak di ekspedisi", "Stok vendor habis").

---

## 6. Work Breakdown Structure (WBS) & Task Roadmap

Untuk memastikan implementasi berjalan mulus tanpa kehilangan konteks, perombakan ini dipecah menjadi **9 Task terisolasi**:

```
┌────────────────────────────────────────────────────────────────────────┐
│                      ROADMAP IMPLEMENTASI PURCHASING                   │
├────────────────────────────────────────────────────────────────────────┤
│  [Task 1] Database Migrations & Model Blueprint                        │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 2] PurchasingCostCalculator & Calculation Engine                │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 3] Form Pembuatan PO (create.blade.php) & Biaya Tambahan/Invoice│
│     │                                                                  │
│     ▼                                                                  │
│  [Task 4] Sistem Termin & Payment Service (Validasi Ketat & Overdue)   │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 5] Proteksi Privasi Role Gudang (Backend Masking & Permissions) │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 6] Detail PO (show.blade.php) & Progress Status Stepper         │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 7] Konfirmasi Barang Datang Gudang (Selisih & Keterangan)       │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 8] Index PO: Dual-Tab AP, Tanggal Aktual, & Quick-Edit Date/ETA │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 9] Testing Komprehensif (Unit & Feature Test) & Regresi Safety  │
└────────────────────────────────────────────────────────────────────────┘
```

---

### Detail Spesifikasi Tiap Task

#### Task 1: Database Migrations & Model Blueprint
- **Tujuan:** Menyiapkan struktur data untuk menampung seluruh atribut baru pengadaan, termin, selisih barang, dan lokasi PO.
- **Perubahan Berkas:**
  - `database/migrations/2026_09_12_000001_add_financial_and_location_to_purchase_orders_table.php` [NEW]
  - `database/migrations/2026_09_12_000002_add_cost_breakdown_to_purchase_order_items_table.php` [NEW]
  - `database/migrations/2026_09_12_000003_create_purchase_order_termins_table.php` [NEW]
  - `database/migrations/2026_09_12_000004_add_discrepancy_to_barang_datang_items_table.php` [NEW]
  - `app/Models/PurchaseOrder.php` [MODIFY]
  - `app/Models/PurchaseOrderItem.php` [MODIFY]
  - `app/Models/PurchaseOrderTermin.php` [NEW]
  - `app/Models/BarangDatangItem.php` [MODIFY]
- **Definition of Done (DoD):**
  - Migrasi berjalan sukses di PostgreSQL/SQLite tanpa merusak data yang sudah ada.
  - Relasi Eloquent `PurchaseOrder::gudang()`, `PurchaseOrder::termins()`, dan casts terdefinisi lengkap.

---

#### Task 2: Service Layer — Kalkulasi Biaya & HPP (Landed Cost Engine)
- **Tujuan:** Membuat class layanan mandiri untuk menghitung HPP per unit bahan baku berdasarkan total harga kotor dan komponen biaya tambahan.
- **Perubahan Berkas:**
  - `app/Services/Purchasing/PurchasingCostCalculator.php` [NEW]
  - `tests/Unit/PurchasingCostCalculatorTest.php` [NEW]
- **Kriteria Logika:**
  - Menghitung Net Total dan HPP satuan per baris.
  - Prorata biaya tambahan jika diinput pada level header.
  - Nilai pembulatan mata uang Rupiah terjamin presisi hingga 2 tempat desimal tanpa floating point drift.
- **Definition of Done (DoD):**
  - Unit test kalkulator biaya lulus 100% dengan berbagai kasus uji (diskon nol, ongkir bertingkat, desimal ganjil).

---

#### Task 3: Revamp Form Create PO (`purchasing/create.blade.php`)
- **Tujuan:** Mengimplementasikan form pembuatan PO baru dengan input nomor invoice manual/auto, pemilihan Lokasi PO (Gudang Tujuan), biaya tambahan (diskon, PPN, ongkir, adjustment), live HPP calculation, dan penyiapan termin.
- **Perubahan Berkas:**
  - `resources/views/purchasing/create.blade.php` [MODIFY]
  - `app/Http/Controllers/PurchasingController.php` (method `create` & `store`) [MODIFY]
- **Fitur Baru UI:**
  - Input `no_invoice` opsional (placeholder: "Kosongkan untuk nomor otomatis").
  - Dropdown `gudang_id` (Lokasi Gudang Tujuan Pengadaan).
  - Kolom baris: Qty, Total Harga Kotor, Diskon, PPN, Ongkir, Adjustment.
  - Tampilan *Live HPP per Unit* terformat mata uang.
  - Opsi Skema Bayar: Tunai / Tempo / Termin. Jika memilih termin, muncul tabel input dinamis jumlah termin & tanggal tempo masing-masing.
  - Seluruh field nominal menggunakan thousand separator.
- **Definition of Done (DoD):**
  - User dapat membuat PO baik dengan no invoice manual maupun auto, data tersimpan rapi beserta rincian biaya dan termin ke database.

---

#### Task 4: Sistem Termin & Validasi Ketat Pembayaran (Payment Service)
- **Tujuan:** Mengelola pembayaran bertahap (termin), validasi batas tagihan, dan pencegahan status lunas fiktif.
- **Perubahan Berkas:**
  - `app/Services/Purchasing/PurchasingPaymentService.php` [NEW]
  - `app/Http/Controllers/PurchasingController.php` (method `pay`) [MODIFY]
- **Fitur Baru:**
  - Validasi error jika pembayaran melebihi sisa tagihan.
  - Validasi error jika skema pelunasan diinput kurang dari sisa tagihan.
  - Update otomatis akumulasi pembayaran pada termin terkait dan evaluasi status header PO (`belum_lunas` $\rightarrow$ `parsial` $\rightarrow$ `lunas`).
  - Penandaan status `overdue` jika tanggal tempo terlampaui.
- **Definition of Done (DoD):**
  - Validasi menolak input overpayment/underpayment dengan pesan error yang jelas dan ramah pengguna.

---

#### Task 5: Proteksi Privasi Finansial Role Gudang (Backend & Frontend Masking)
- **Tujuan:** Menjamin role gudang tidak dapat melihat nominal harga dan status pembayaran di seluruh modul purchasing.
- **Perubahan Berkas:**
  - `database/seeders/PermissionSeeder.php` [MODIFY: tambah `purchasing.price.view`]
  - `app/Services/Authorization/PermissionCatalogService.php` [MODIFY]
  - `app/Http/Controllers/PurchasingController.php` (method `data`, `show`) [MODIFY]
  - `resources/views/purchasing/index.blade.php` [MODIFY]
  - `resources/views/purchasing/show.blade.php` [MODIFY]
- **Fitur Baru:**
  - DataTables API hanya mengirimkan tanda strip `—` untuk kolom `total_nilai` dan `sisa` jika pengguna tidak memiliki `purchasing.price.view`.
  - Halaman `show.blade.php` menyembunyikan kolom harga, tfoot nilai PO, dan panel AP jika pengguna adalah role gudang.
- **Definition of Done (DoD):**
  - Saat login sebagai role `gudang`, tidak ada angka rupiah atau data pembayaran yang bocor baik di DOM HTML maupun response JSON Network request.

---

#### Task 6: Revamp Detail PO (`purchasing/show.blade.php`) & Progress Status Stepper
- **Tujuan:** Menambahkan layout alur progres dokumen serta menata ulang rincian landed cost, lokasi gudang, dan jadwal termin.
- **Perubahan Berkas:**
  - `resources/views/purchasing/show.blade.php` [MODIFY]
  - `resources/views/components/purchasing-progress-stepper.blade.php` [NEW]
- **Fitur Baru UI:**
  - Progress bar status: *Draft* $\rightarrow$ *Diajukan* $\rightarrow$ *Disetujui* $\rightarrow$ *Kirim ke Gudang* $\rightarrow$ *Diterima Gudang* $\rightarrow$ *Selesai / Lunas*.
  - Rincian metadata: No PO, No Invoice, Lokasi Gudang Tujuan, Supplier, Estimasi Tiba (ETA).
  - Tabel rincian bahan dengan breakdown: Harga Kotor, Diskon, PPN, Ongkir, Net Total, dan HPP Akhir.
  - Kartu Jadwal Termin & Riwayat Pembayaran (khusus yang berwenang).
- **Definition of Done (DoD):**
  - Tampilan detail PO rapi, modern, responsif, dan mencerminkan status terkini dengan jelas.

---

#### Task 7: Konfirmasi Barang Datang Gudang (Selisih & Keterangan)
- **Tujuan:** Memperbaiki form konfirmasi penerimaan barang di gudang agar otomatis mendeteksi selisih fisik dan mewajibkan keterangan deviasi.
- **Perubahan Berkas:**
  - `resources/views/purchasing/show.blade.php` (form konfirmasi barang datang) [MODIFY]
  - `app/Http/Controllers/PurchasingController.php` (method `receive`) [MODIFY]
- **Fitur Baru:**
  - Default gudang penerima otomatis mengambil `po.gudang_id` (Lokasi PO).
  - Deteksi visual selisih real-time ($\text{qty\_diterima} - \text{qty\_po}$).
  - Kolom teks `Keterangan Selisih` muncul dan menjadi *required* bila ada deviasi kuantitas.
  - Tersimpan ke `barang_datang_items.selisih` dan `barang_datang_items.keterangan_selisih`.
- **Definition of Done (DoD):**
  - Staf gudang dapat mencatat penerimaan parsial/berlebih dengan keterangan audit trail yang tersimpan akurat ke kartu stok dan log gudang.

---

#### Task 8: Revamp Index PO: Dual-Tab, Tanggal Aktual Tiba, & Quick-Edit Date/ETA
- **Tujuan:** Menjadikan halaman index PO lebih fungsional dengan navigasi dua tab (Daftar PO & Tagihan AP), menampilkan tanggal aktual tiba, serta memungkinkan edit tanggal PO dan ETA secara cepat.
- **Perubahan Berkas:**
  - `resources/views/purchasing/index.blade.php` [MODIFY]
  - `resources/views/purchasing/_actions.blade.php` [MODIFY]
  - `app/Http/Controllers/PurchasingController.php` (method `data`, `dataAp`, `updateDates`) [MODIFY]
  - `routes/web.php` [MODIFY]
- **Fitur Baru:**
  - Tab 1: Daftar Purchase Order (menampilkan Tanggal PO, ETA, Tanggal Aktual Tiba, Lokasi Gudang).
  - Tombol Quick-Edit Tanggal PO & ETA (khusus wewenang `purchasing.edit` sebelum PO selesai).
  - Tab 2: Tagihan & Pembayaran AP (khusus wewenang finansial: ringkasan utang, filter status lunas/parsial/overdue).
- **Definition of Done (DoD):**
  - Staf purchasing dapat mengubah tanggal PO dan ETA via modal tanpa harus membuka ulang seluruh form, serta dapat memantau jadwal jatuh tempo AP di tab kedua.

---

#### Task 9: Pengujian Otomatis (Automated Testing) & Verifikasi Menyeluruh
- **Tujuan:** Memastikan seluruh fitur baru teruji secara otomatis tanpa ada regresi pada alur PO lama.
- **Perubahan Berkas:**
  - `tests/Feature/PurchasingRevampFlowTest.php` [NEW]
  - `tests/Feature/PurchasingPrivacyGudangTest.php` [NEW]
- **Skenario Uji:**
  1. Golden flow: Buat PO (no invoice manual + biaya tambahan) $\rightarrow$ Submit $\rightarrow$ Approve $\rightarrow$ Receive (dengan selisih) $\rightarrow$ Pay Termin (hingga lunas).
  2. Edge case: Pembayaran melebihi sisa ditolak (422).
  3. Edge case: Skema pelunasan dengan nominal kurang ditolak (422).
  4. Privacy check: Login sebagai role `gudang` $\rightarrow$ Akses index dan show $\rightarrow$ Pastikan harga dan status pembayaran tidak muncul.
  5. Quick edit dates: Ubah tanggal PO & ETA berhasil saat status draft/diajukan/disetujui.
- **Definition of Done (DoD):**
  - Seluruh pengujian PHPUnit (`php artisan test`) lolos dengan exit code 0.

---

## 7. Rekomendasi Eksekusi untuk Agent Pelaksana

1. **Jalankan Sesuai Urutan Task:** Jangan melompati Task (misal mengedit Blade sebelum migrasi database dijalankan), karena Blade mengandalkan relasi dan atribut baru dari Model.
2. **Pertahankan Validasi Thousand Separator:** Pastikan input angka terformat selalu mengirim angka integer/desimal bersih via hidden input agar controller tidak mengalami kegagalan validasi `numeric`.
3. **Patuhi Batasan UI Testing:** User telah menetapkan aturan: *"untuk testing ui nanti saya manual jangan mbuka web sendiri"*. Seluruh verifikasi agen wajib dilakukan melalui unit/feature test (`php artisan test`) dan pemeriksaan sintaks, tanpa memicu browser subagent otomatis.
