# Blueprint & Rancangan Arsitektur: Revamp Stok, Produksi, Transfer & Wewenang Lokasi
**Proyek:** Heaven Scent Enterprise — Production Lab Online  
**Target Dokumen:** `.agent/inventory-production-revamp.md`  
**Standar Rekayasa:** SOLID Principles, Separation of Duties (SoD), Location-Based Access Control (LBAC), Multi-Output Manufacturing, Financial Integrity  

---

## 1. Executive Summary & Latar Belakang Kebutuhan

Peningkatan skala operasional pabrik manufaktur parfum dan jaringan distribusi fulfillment Heaven Scent Enterprise menuntut peningkatan arsitektur sistem pada modul **Stok & Mutasi**, **Batch Produksi**, **Master Produk**, dan **Request & Transfer Antar Gudang**.

Sistem saat ini memiliki keterbatasan mendasar:
1. **Penyajian Data Kuantitas:** Masih menggunakan format desimal dua angka di belakang koma (`20.00 pcs`), yang membingungkan pengguna operasional karena desimal dua angka seharusnya dikhususkan untuk nominal moneter / Harga.
2. **Kalkulasi Kolom Rencana:** Pemahaman logika ketersediaan stok (*Available-to-Promise*) belum terbedakan secara tegas antara bahan baku (berkurang karena terikat alokasi) dan produk jadi (bertambah karena rencana output batch).
3. **Penyaringan Data & Valuasi:** Belum ada filter terpadu berbasis lokasi dan kategori barang di stok, serta belum ada valuasi total nilai persediaan berbasis HPP.
4. **Model Produksi Tunggal (Single-Output):** Satu batch produksi saat ini terkunci hanya bisa menghasilkan 1 jenis produk jadi, padahal proses *maceration/mixing* di lab parfum riil sering dibotolkan menjadi beberapa varian/ukuran sekaligus (misal: 50ml, 30ml, dan 10ml travel spray).
5. **Pemisahan Wewenang Logistik (Separation of Duties - SoD):** Saat Gudang Operasional mengirim produk jadi ke Gudang Fulfillment, belum ada proteksi wewenang lokasi. Staf pengirim saat ini masih bisa mengonfirmasi penerimaannya sendiri, serta belum ada kontrol kualitas penerimaan (*Good vs Defect*).
6. **Data Scoping Berbasis Lokasi (LBAC):** Staf gudang/fulfillment masih dapat melihat transaksi gudang lain yang bukan area tugasnya, alih-alih terisolasi berdasarkan lokasi fasilitas penugasan.

---

### Analisis Kebutuhan & Matriks Solusi Arsitektural (Traceability Matrix)

| No | Modul | Kebutuhan Pengguna (User Request) | Masalah / Keterbatasan Saat Ini | Solusi Arsitektural & Best Practice |
|---|---|---|---|---|
| **1** | **Stok & Mutasi** | Kolom `qty` dan `saldo` tidak memakai dua angka belakang koma (dua angka belakang koma khusus Harga). | Format kuantitas di DataTables dan ledger masih menggunakan `number_format(..., 2)` atau string raw DB `20.00`. | Buat helper/metode pemformatan kuantitas pintar (`formatQty`): angka bulat ditampilkan murni (`20`, `1.500`), sedangkan pecahan hanya menampilkan desimal jika ada nilai riil non-nol. Khusus kolom harga/HPP tetap berformat mata uang Rupiah. |
| **2** | **Stok & Mutasi** | Kolom rencana: untuk bahan baku adalah sisa stok setelah perencanaan produksi, untuk produk jadi bertambah. | Formula kolom rencana sebelumnya belum sepenuhnya memisahkan pipeline proyeksi bahan baku vs produk jadi secara transparan. | **Bahan Baku:** $\text{Proyeksi Sisa} = \text{Stok Fisik} + \text{Inbound} - \text{Alokasi Rencana Batch Aktif}$.<br>**Produk Jadi:** $\text{Proyeksi Ketersediaan} = \text{Stok Fisik} + \text{Inbound RT} + \text{Rencana Hasil Batch Aktif}$. |
| **3** | **Stok & Mutasi** | Diberikan filter lokasi (gudang) dan filter jenis (bahan baku vs produk jadi). | Filter saat ini statis dan bercampur dalam satu tabel tanpa pemisahan kategori stok. | Tambahkan dropdown filter Fasilitas Gudang (scoped wewenang) dan filter kategori (`Bahan Baku & Kemas` vs `Produk Jadi`) yang memicu reload DataTables secara asinkron. |
| **4** | **Stok & Mutasi** | Tambah kolom HPP dan Nilai Stok. | Stok hanya menampilkan saldo kuantitas tanpa valuasi nominal persediaan uang berjalan. | Tambahkan kolom `HPP` per satuan (dari landed cost PO terakhir atau referensi master) dan `Nilai Stok` ($\text{Qty Fisik} \times \text{HPP}$). Diberikan proteksi permission finansial (`purchasing.price.view`). |
| **5** | **Batch Produksi / Stok** | Index ditambahkan kolom: `baik` \| `rusak` \| `yield` \| `status`. | Tabel index batch produksi hanya menampilkan `Rencana`, `Status`, `Yield`, tanpa kolom kuantitas riil `Baik` dan `Rusak`. | Lengkapi tabel DataTables `batches/index.blade.php` dengan kolom: `Rencana`, `Baik`, `Rusak`, `Yield (%)`, dan `Status`. Pada tabel stok, tampilkan status alert buffer (*Aman*, *Order*, *Kritis*). |
| **6** | **Produksi** | Dalam satu batch bisa menghasilkan lebih dari 1 produk jadi (*Multi-Output Batch*). | Tabel `batch_produksi` hanya memiliki 1 `produk_id` di header, tidak mendukung *joint production / co-products*. | Buat entitas relasional baru `batch_produksi_outputs`. Satu dokumen batch dapat memiliki banyak baris produk jadi luaran lengkap dengan qty rencana, qty baik, qty rusak, dan kalkulasi BOM gabungan. |
| **7** | **Produksi & Transfer** | Gudang Operasional kirim ke Fulfillment: yang bisa konfirmasi terima **hanya role Fulfillment**. | Aksi `receive` pada request transfer saat ini hanya memeriksa permission `rt.receive` global tanpa validasi kepemilikan gudang tujuan. | Terapkan *Enforced Separation of Duties (SoD)*: Endpoint dan UI tombol `receive` divalidasi ketat. User pengirim dilarang menerima kirimannya sendiri. Hanya user dengan role `fulfillment` dan ditugaskan di gudang tujuan yang dapat mengeksekusi konfirmasi penerimaan. |
| **8** | **Produksi & Transfer** | Konfirmasi terima produk jadi oleh fulfillment dilengkapi kolom `baik berapa` dan `rusak berapa`. | Form penerimaan saat ini hanya memiliki input tunggal `qty_diterima`, barang rusak langsung tercampur ke stok aktif. | Perluas skema `request_transfer_items` dengan kolom `qty_baik`, `qty_rusak`, dan `keterangan_rusak`. Hanya `qty_baik` yang masuk ke saldo stok aktif fulfillment; `qty_rusak` dicatat ke audit defect transit. |
| **9** | **Gudang** | Role Gudang bisa membuat request dan transfer (`rt.create`, `rt.submit`). | Permission `rt.create` dan `rt.submit` pada `PermissionSeeder` belum diberikan ke role `gudang`. | Sinkronisasi permission: tambahkan `rt.create` dan `rt.submit` ke role `gudang` di seeder dan catalog wewenang. |
| **10** | **Master Produk** | Tab page berdasarkan tipe produk di halaman index. | Halaman produk menampilkan seluruh tipe barang dalam satu tabel panjang tanpa tabulasi. | Implementasikan *Tabs Interface* modern di `produk/index.blade.php`: Tab **Semua**, Tab **Bahan Baku**, Tab **Kemasan**, dan Tab **Produk Jadi** dengan state filter DataTables instan. |
| **11** | **Wewenang & Lokasi** | Menu Stok & Mutasi dan Request & Transfer berisikan data berdasarkan role dan lokasi akses user. Data All hanya untuk Manager / divisi berwewenang global. | Query index mengambil seluruh data gudang tanpa memperhitungkan penugasan fisik user. | Implementasikan *Location-Based Access Control (LBAC)*: Model penugasan user ke fasilitas gudang (`gudang_user`). Query otomatis ter-scope ke gudang penugasan, sementara role `manager` memiliki akses global (*All Warehouses*). |

---

## 2. Prinsip Rekayasa Perangkat Lunak & Tata Kelola Arsitektur

### A. SOLID Principles dalam Domain Logistik & Manufaktur
1. **Single Responsibility Principle (SRP):**
   - `LocationScopeService`: Layanan independen untuk mengevaluasi hak akses fasilitas gudang user saat ini (apakah global/manager, atau terisolasi pada gudang tertentu).
   - `BatchOutputManager`: Layanan khusus untuk mengelola multi-output produk jadi, perhitungan kebutuhan agregat BOM, dan penerbitan produk ke stok.
   - `TransferReceiptService`: Mengatur alur penerimaan transfer antar gudang, pemisahan fisik barang baik vs barang rusak saat transit, dan mutasi kartu stok tujuan.
2. **Open/Closed Principle (OCP):**
   - Penambahan skema multi-output produk jadi dirancang tetap menjaga kompatibilitas ke belakang (*backward compatibility*) dengan field header `batch_produksi`, sehingga fitur analisa lama tidak mengalami *breaking change*.
3. **Interface Segregation Principle (ISP) & Separation of Duties (SoD):**
   - Pemisahan tegas wewenang: Divisi pengirim (`operasional`) hanya berhak melakukan `ship`, dan divisi penerima (`fulfillment`) hanya berhak melakukan `receive`. Pihak yang mengirim dilarang memverifikasi barangnya sendiri untuk mencegah *fraud* kuantitas.
4. **Least Privilege Principle:**
   - Staff di gudang cabang (misal: Fulfillment Surabaya) hanya berhak melihat stok dan mutasi barang di fasilitas Surabaya, tidak dapat mengintip gudang Solo atau Pusat kecuali Manajer.

---

## 3. Desain Model Data & Skema Database

```
┌────────────────────────────────────────────────────────┐
│                         users                          │
├────────────────────────────────────────────────────────┤
│ id: BIGINT (PK)                                        │
│ name, email, password...                               │
└──────────┬─────────────────────────────────────────────┘
           │ 1
           │ N
┌──────────▼──────────────────────────┐
│             gudang_user             │ ◄── NEW PIVOT (LBAC)
├─────────────────────────────────────┤
│ id: BIGINT (PK)                     │
│ user_id: BIGINT (FK -> users)       │
│ gudang_id: BIGINT (FK -> gudang)    │
│ is_primary: BOOLEAN                 │
└─────────────────────────────────────┘
           ▲ N
           │ 1
┌──────────┴──────────────────────────┐
│                gudang               │
├─────────────────────────────────────┤
│ id: BIGINT (PK)                     │
│ kode, nama, tipe, status...         │
└──────────┬──────────────────────────┘
           │ 1
           │ N
┌──────────▼──────────────────────────┐   ┌──────────────────────────────────────────┐
│            batch_produksi           │   │          batch_produksi_outputs          │
├─────────────────────────────────────┤   ├──────────────────────────────────────────┤
│ id: UUID (PK)                       │   │ id: UUID (PK)                  ◄── NEW   │
│ no_batch: VARCHAR(30)               │   │ batch_id: UUID (FK)                      │
│ gudang_operasional_id: BIGINT (FK)  │1 N│ produk_id: BIGINT (FK -> produk)         │
│ gudang_tujuan_rencana_id: BIGINT(FK)├───┤ qty_rencana: DECIMAL(15,2)               │
│ status: VARCHAR(20)                 │   │ qty_baik: DECIMAL(15,2) [NULLABLE]       │
│ tanggal: DATE                       │   │ qty_rusak: DECIMAL(15,2) [NULLABLE]      │
│ created_by: BIGINT (FK)             │   │ catatan: VARCHAR(255)                    │
└─────────────────────────────────────┘   └──────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│                    request_transfers                   │
├────────────────────────────────────────────────────────┤
│ id: UUID (PK)                                          │
│ no_transaksi: VARCHAR(30)                              │
│ jenis: VARCHAR(30)                                     │
│ gudang_asal_id: BIGINT (FK -> gudang)                  │
│ gudang_tujuan_id: BIGINT (FK -> gudang)                │
│ status: VARCHAR(20)                                    │
└──────────┬─────────────────────────────────────────────┘
           │ 1
           │ N
┌──────────▼──────────────────────────┐
│        request_transfer_items       │
├─────────────────────────────────────┤
│ id: UUID (PK)                       │
│ request_transfer_id: UUID (FK)      │
│ produk_id: BIGINT (FK -> produk)    │
│ qty_diminta: DECIMAL(15,2)          │
│ qty_dikirim: DECIMAL(15,2)          │
│ qty_diterima: DECIMAL(15,2)         │
│ qty_baik: DECIMAL(15,2)     ◄── NEW │
│ qty_rusak: DECIMAL(15,2)    ◄── NEW │
│ keterangan_rusak: TEXT      ◄── NEW │
└─────────────────────────────────────┘
```

---

### Migrasi Database Terinci

#### 1. Migrasi Penugasan Gudang User (`gudang_user` Table)
```php
Schema::create('gudang_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
    $table->boolean('is_primary')->default(false);
    $table->timestamps();

    $table->unique(['user_id', 'gudang_id'], 'uq_gudang_user');
});
```

#### 2. Migrasi Multi-Output Batch Produksi (`batch_produksi_outputs` Table)
```php
Schema::create('batch_produksi_outputs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('batch_id')->constrained('batch_produksi')->cascadeOnDelete();
    $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
    $table->decimal('qty_rencana', 15, 2);
    $table->decimal('qty_baik', 15, 2)->nullable();
    $table->decimal('qty_rusak', 15, 2)->nullable();
    $table->string('catatan', 255)->nullable();
    $table->timestamps();

    $table->index(['batch_id', 'produk_id']);
});
```

#### 3. Migrasi Kolom Kualitas Penerimaan Transfer (`request_transfer_items`)
```php
Schema::table('request_transfer_items', function (Blueprint $table) {
    $table->decimal('qty_baik', 15, 2)->nullable()->after('qty_diterima');
    $table->decimal('qty_rusak', 15, 2)->nullable()->after('qty_baik');
    $table->text('keterangan_rusak')->nullable()->after('qty_rusak');
});
```

#### 4. Migrasi Kolom HPP Referensi pada Master Produk (`produk`)
```php
Schema::table('produk', function (Blueprint $table) {
    $table->decimal('harga_hpp', 18, 2)->default(0)->after('satuan_order_moq');
});
```

---

## 4. Logika Bisnis & Formula Perhitungan (Domain Logic)

### A. Formula Pemformatan Kuantitas (Tanpa Desimal Statis .00)
Untuk semua kolom kuantitas stok fisik, saldo mutasi, batas minimum, dan kuantitas transfer:
$$\text{Output Teks} = \begin{cases} 
\text{number\_format}(Q, 0, ',', '.') & \text{jika } Q = \lfloor Q \rfloor \\
\text{rtrim}(\text{rtrim}(\text{number\_format}(Q, 2, ',', '.'), \text{'0'}), \text{','}) & \text{jika } Q \neq \lfloor Q \rfloor
\end{cases}$$
*Contoh:* 
- Nilai `20.00` $\rightarrow$ Tampil **20 pcs** (Bukan `20.00`)
- Nilai `1500.00` $\rightarrow$ Tampil **1.500 pcs**
- Nilai `12.50` $\rightarrow$ Tampil **12,5 pcs** (tetap mempertahankan presisi jika memang pecahan)
- *Catatan:* Kolom **Harga, HPP, dan Total Nilai Rupiah** tetap menggunakan format mata uang standar dengan pemisah ribuan titik (`Rp 25.000`).

---

### B. Formula Presisi Kolom Rencana (*Available-to-Promise Projections*)
Di tabel `stok/index.blade.php`:
1. **Untuk Bahan Baku & Kemasan (`tipe in ['bahan', 'kemas']`):**
   Rencana menunjukkan **sisa fisik yang benar-benar bebas** setelah dikurangi kebutuhan produksi yang sudah dijadwalkan:
   $$\text{Kolom Rencana}_{\text{Bahan}} = (\text{Stok Fisik} + \text{Inbound PO/RT}) - \sum \text{Qty Alokasi Aktif}$$
   *Makna Bisnis:* Jika stok fisik 100 kg dan batch release membutuhkan 30 kg, Kolom Rencana = **70 kg**. Jika hasilnya $\le \text{Batas Minimum}$, sistem memicu badge merah **ORDER**.

2. **Untuk Produk Jadi (`tipe == 'produk_jadi'`):**
   Rencana menunjukkan **proyeksi ketersediaan barang jadi di masa depan** yang siap dijual setelah batch aktif selesai:
   $$\text{Kolom Rencana}_{\text{Produk Jadi}} = (\text{Stok Fisik} + \text{Inbound RT}) + \sum \text{Qty Rencana Batch Selesai/Aktif}$$
   *Makna Bisnis:* Jika stok gudang fulfillment saat ini 50 botol dan sedang ada batch produksi berjalan sebanyak 200 botol, Kolom Rencana = **250 botol**.

---

### C. Valuasi Persediaan Stok (HPP & Nilai Total)
1. **Penentuan HPP Satuan:**
   - **Bahan Baku / Kemas:** Diambil dari nilai `hpp_per_satuan` pada penerimaan PO terakhir (*Landed Cost*), atau fallback ke `produk.harga_hpp`.
   - **Produk Jadi:** Dihitung dari akumulasi biaya resep BOM:
     $$\text{HPP}_{\text{Produk Jadi}} = \sum (\text{Qty Bahan}_i \times \text{HPP Bahan}_i) + \text{Biaya Konversi/Kemasan}$$
     atau fallback ke `produk.harga_hpp`.
2. **Nilai Persediaan Gudang:**
   $$\text{Nilai Stok} = \text{Stok Fisik} \times \text{HPP}$$
3. **Proteksi Privasi Finansial:** Kolom HPP dan Nilai Stok dibungkus oleh gate authorization `@can('purchasing.price.view')`. Role gudang fisik murni tidak diperlihatkan valuasi nominal persediaan.

---

### D. Multi-Output Batch Produksi & Agregasi BOM
Saat membuat batch baru dengan banyak produk jadi (misal: Batch "GOH-BATCH-01"):
- Output 1: Parfum GOH 50ml (Qty Rencana: 100 pcs)
- Output 2: Parfum GOH 30ml (Qty Rencana: 200 pcs)

**Perhitungan Total Alokasi Bahan Baku yang Disedot:**
$$\text{Total Kebutuhan Bahan}_k = \sum_{j \in \text{Outputs}} (\text{Qty Per Unit}_{k, j} \times \text{Qty Rencana}_j)$$
Saat status menjadi `release`, seluruh alokasi bahan terpotong atomik dari gudang operasional.  
Saat status diselesaikan (`complete`), pengguna menginput:
- Output 1: Baik = 98 pcs, Rusak = 2 pcs $\rightarrow$ Masuk ke stok GOH 50ml di gudang operasional/tujuan sebanyak 98 pcs.
- Output 2: Baik = 195 pcs, Rusak = 5 pcs $\rightarrow$ Masuk ke stok GOH 30ml sebanyak 195 pcs.
- Yield dihitung per output dan rata-rata batch:
  $$\text{Yield Total Batch} = \frac{\sum \text{Qty Baik}_j}{\sum \text{Qty Rencana}_j} \times 100\%$$

---

### E. Kebijakan Privasi & Batas Wewenang Lokasi (Location-Based Access Control)
1. **Evaluasi Hak Akses Fasilitas (`LocationScopeService`):**
   ```php
   public function getAccessibleWarehouseIds(User $user): ?array
   {
       // Role Manager atau wewenang global memiliki akses ke SELURUH gudang
       if ($user->hasRole('manager') || $user->can('stok.view.all')) {
           return null; // null = wildcard/all
       }
       
       $assigned = $user->gudangs()->pluck('gudang.id')->toArray();
       if (!empty($assigned)) {
           return $assigned;
       }
       
       // Fallback berbasis peran standar jika belum dipetakan di pivot:
       return match($user->roleName()) {
           'gudang' => Gudang::where('tipe', 'bahan_baku')->pluck('id')->toArray(),
           'operasional' => Gudang::where('tipe', 'operasional')->pluck('id')->toArray(),
           'fulfillment' => Gudang::fulfillment()->pluck('id')->toArray(),
           default => [],
       };
   }
   ```
2. **Scoping Query DataTables:**
   - Di `StokController::data`: Jika `$accessibleIds !== null`, tambahkan `->whereIn('stok.gudang_id', $accessibleIds)`.
   - Di `RequestTransferController::data`: Jika `$accessibleIds !== null`, tambahkan:
     ```php
     $query->where(function($q) use ($accessibleIds) {
         $q->whereIn('gudang_asal_id', $accessibleIds)
           ->orWhereIn('gudang_tujuan_id', $accessibleIds);
     });
     ```

---

### F. Separation of Duties (SoD) pada Request & Transfer
Saat pengiriman barang dari **Gudang Operasional** ke **Fulfillment**:
1. **Pembuat & Pengirim (`ship`):** Staf Gudang Operasional. Stok berkurang di Gudang Operasional. Status dokumen berubah menjadi `dikirim` (*In Transit*).
2. **Penerima (`receive`):**
   - Staf Gudang Operasional yang mengirim **DILARANG** menekan tombol konfirmasi terima. Tombol di-hide dan backend melempar HTTP 403 Forbidden:  
     `"Aksi Ditolak: Anda merupakan pihak pengirim. Konfirmasi penerimaan wajib dilakukan oleh petugas Gudang Fulfillment tujuan."`
   - Hanya staf yang memiliki wewenang pada `gudang_tujuan_id` (role `fulfillment` atau manager) yang dapat mengonfirmasi penerimaan.
3. **Pencatatan Fisik Kualitas Inbound:**
   - Form penerimaan mewajibkan input: `qty_baik` dan `qty_rusak`.
   - Validasi: $\text{qty\_baik} + \text{qty\_rusak} == \text{qty\_dikirim}$.
   - **Efek Stok:** Sistem **hanya menambah saldo stok fisik aktif** sebesar `qty_baik`. Nilai `qty_rusak` dicatat ke audit log defect transit dan tidak boleh menambah saldo jual.

---

## 5. Rancangan Komponen Antarmuka Pengguna (UI/UX Specification)

### A. Tampilan Baru `stok/index.blade.php`
1. **Toolbar & Filter Header:**
   - Filter Dropdown **Lokasi Gudang:** Menampilkan gudang yang diizinkan (atau "Semua Gudang" bagi Manager).
   - Filter Tab/Pills **Kategori Produk:** `Semua Produk`, `Bahan Baku & Kemas`, `Produk Jadi`.
2. **Format Kolom Angka Bersih:**
   - `Batas Min`: `1.000 pcs`
   - `Kolom Stok`: `2.450 pcs` + Badge Status (*AMAN*)
   - `Kolom Rencana`: `1.850 pcs` + Badge Status
   - `HPP`: `Rp 12.500` (terlindungi wewenang finansial)
   - `Nilai Stok`: `Rp 30.625.000` (terlindungi wewenang finansial)

### B. Tabulasi Kategori di `produk/index.blade.php`
Navigasi tab modern tanpa reload penuh:
- Tab 1: **Semua Produk**
- Tab 2: **Bahan Baku** (`tipe: bahan`)
- Tab 3: **Komponen Kemasan** (`tipe: kemas`)
- Tab 4: **Produk Jadi** (`tipe: produk_jadi`)

### C. Antarmuka Multi-Output di `batches/create.blade.php` & `show.blade.php`
- Form dinamis Alpine.js yang memungkinkan menambah beberapa baris produk jadi luaran dalam satu batch.
- Tabel ringkasan kebutuhan bahan otomatis menghitung total agregat dari seluruh BOM produk jadi yang dipilih.
- Form penyelesaian batch (`complete`) menampilkan tabel output baris-per-baris dengan input `Qty Baik` dan `Qty Rusak` masing-masing.

### D. Kolom Lengkap di `batches/index.blade.php`
Kolom DataTables:
`No Batch` | `Daftar Produk Luaran` | `Rencana` | `Baik` | `Rusak` | `Yield (%)` | `Status` | `Tanggal` | `Aksi`

### E. Form Konfirmasi Terima Transfer di `request-transfer/show.blade.php`
Saat status `dikirim`, jika user login berhak di gudang tujuan:
- Tampil kartu konfirmasi penerimaan barang.
- Setiap baris barang memiliki input: `Qty Diterima Baik`, `Qty Diterima Rusak`, dan `Keterangan Kerusakan Transit`.
- Tombol aksi jelas: *"Konfirmasi Penerimaan Barang di Fulfillment"*.

---

## 6. Work Breakdown Structure (WBS) & Task Roadmap

Untuk menjaga kebersihan implementasi dan mencegah hilangnya konteks, perombakan ini dipecah menjadi **7 Task modular terisolasi**:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   ROADMAP REVAMP STOK, PRODUKSI & SOD                  │
├────────────────────────────────────────────────────────────────────────┤
│  [Task 1] Database Migrations & Data Model Architecture                │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 2] RBAC, Penugasan Gudang User & Location Scoping Engine        │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 3] Stok & Mutasi Engine: Formatter, Formula Rencana, HPP/Nilai  │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 4] Master Produk: Tab Navigation & Filter Berdasarkan Tipe      │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 5] Multi-Output Batch Produksi & Index Table Baik/Rusak/Yield   │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 6] Request & Transfer: Enforced SoD & Quality Inbound Form      │
│     │                                                                  │
│     ▼                                                                  │
│  [Task 7] Comprehensive Testing (Unit & Feature Tests) & Safety Audit  │
└────────────────────────────────────────────────────────────────────────┘
```

---

### Rincian Spesifikasi & Definition of Done (DoD) Tiap Task

#### Task 1: Database Migrations & Data Model Architecture
- **Tujuan:** Menyiapkan skema basis data untuk relasi user-lokasi, multi-output batch, rincian kualitas transfer, dan HPP master produk.
- **Daftar Berkas:**
  - `database/migrations/2026_09_12_100001_create_gudang_user_table.php` [NEW]
  - `database/migrations/2026_09_12_100002_create_batch_produksi_outputs_table.php` [NEW]
  - `database/migrations/2026_09_12_100003_add_quality_to_request_transfer_items_table.php` [NEW]
  - `database/migrations/2026_09_12_100004_add_harga_hpp_to_produk_table.php` [NEW]
  - `app/Models/User.php` (tambah relasi `gudangs()`) [MODIFY]
  - `app/Models/Gudang.php` (tambah relasi `users()`) [MODIFY]
  - `app/Models/BatchProduksi.php` (tambah relasi `outputs()`) [MODIFY]
  - `app/Models/BatchProduksiOutput.php` [NEW]
  - `app/Models/RequestTransferItem.php` (tambah casts `qty_baik`, `qty_rusak`) [MODIFY]
  - `app/Models/Produk.php` (tambah field `harga_hpp`) [MODIFY]
- **Definition of Done (DoD):**
  - Seluruh migrasi database berhasil dieksekusi via `php artisan migrate`.
  - Relasi Eloquent terdefinisi lengkap dengan cascade delete dan index performa.

---

#### Task 2: RBAC, Penugasan Gudang User & Location Scoping Engine
- **Tujuan:** Mengatur wewenang role Gudang untuk membuat transfer, serta membangun service pendeteksi cakupan lokasi user (LBAC).
- **Daftar Berkas:**
  - `app/Services/Authorization/LocationScopeService.php` [NEW]
  - `database/seeders/PermissionSeeder.php` (tambah `rt.create` dan `rt.submit` pada role `gudang`) [MODIFY]
  - `app/Services/Authorization/PermissionCatalogService.php` [MODIFY]
  - `tests/Unit/LocationScopeServiceTest.php` [NEW]
- **Definition of Done (DoD):**
  - Role `gudang` memiliki wewenang `rt.create` dan `rt.submit`.
  - `LocationScopeService` mengembalikan `null` untuk Manager (akses semua gudang) dan mengembalikan array ID gudang yang valid sesuai penugasan user non-manager.

---

#### Task 3: Stok & Mutasi Engine: Formatter, Formula Rencana, HPP & Nilai Stok
- **Tujuan:** Memperbaiki pemformatan kuantitas tanpa desimal nol statis, mengimplementasikan logika ketersediaan kolom rencana (bahan baku berkurang, produk jadi bertambah), menyertakan kolom HPP dan Nilai Stok, serta filter lokasi & kategori.
- **Daftar Berkas:**
  - `app/Helpers/NumberHelper.php` atau metode helper pada `StokService` [NEW/MODIFY]
  - `app/Http/Controllers/StokController.php` (method `index`, `data`, `ledgerData`) [MODIFY]
  - `resources/views/stok/index.blade.php` [MODIFY]
  - `resources/views/stok/ledger.blade.php` [MODIFY]
- **Kriteria Tampilan:**
  - Kolom Qty & Saldo tidak ada `.00` lagi.
  - Dropdown filter Gudang (otomatis ter-filter berdasarkan lokasi user) & filter Kategori Produk.
  - Kolom HPP dan Nilai Stok tampil rapi (khusus wewenang `purchasing.price.view`).
- **Definition of Done (DoD):**
  - Tampilan tabel stok dan buku besar ledger terformat bersih, filter bekerja secara reaktif, dan formula rencana terbukti akurat pada unit test.

---

#### Task 4: Master Produk: Tab Navigation Berdasarkan Tipe Produk
- **Tujuan:** Menata ulang katalog master produk dengan navigasi tab terpisah per tipe produk.
- **Daftar Berkas:**
  - `resources/views/produk/index.blade.php` [MODIFY]
  - `app/Http/Controllers/ProdukController.php` (method `data`) [MODIFY]
- **Fitur Baru:**
  - Tab: Semua (`all`), Bahan Baku (`bahan`), Kemasan (`kemas`), Produk Jadi (`produk_jadi`).
  - Switching tab mengubah parameter filter DataTables secara instan tanpa reload halaman penuh.
- **Definition of Done (DoD):**
  - Pengguna dapat beralih antar tab produk dengan cepat dan data yang tampil terisolasi sesuai tipenya.

---

#### Task 5: Multi-Output Batch Produksi & Index Table (Baik, Rusak, Yield, Status)
- **Tujuan:** Memungkinkan 1 batch menghasilkan banyak produk jadi, mengagregasikan pemakaian BOM, dan menyempurnakan tabel index batch produksi.
- **Daftar Berkas:**
  - `app/Http/Controllers/BatchController.php` (method `index`, `data`, `create`, `store`, `release`, `complete`) [MODIFY]
  - `resources/views/batches/create.blade.php` [MODIFY]
  - `resources/views/batches/show.blade.php` [MODIFY]
  - `resources/views/batches/index.blade.php` [MODIFY]
- **Fitur Baru:**
  - Form create batch mendukung penambahan banyak varian produk jadi luaran.
  - Form complete batch mencatat `qty_baik` dan `qty_rusak` per produk jadi luaran.
  - Tabel index batch menampilkan kolom: `No Batch`, `Produk Luaran`, `Rencana`, `Baik`, `Rusak`, `Yield (%)`, `Status`, `Tanggal`, `Aksi`.
- **Definition of Done (DoD):**
  - Batch dengan 2 atau lebih produk jadi dapat dibuat, di-release, dan diselesaikan; stok fisik dari masing-masing produk jadi bertambah sesuai kuantitas baik.

---

#### Task 6: Request & Transfer: Enforced SoD & Quality Inbound Fulfillment
- **Tujuan:** Menerapkan pembatasan wewenang lokasi pada transfer barang (pengirim tidak bisa menerima sendiri) dan menyediakan input kualitas barang datang di gudang fulfillment.
- **Daftar Berkas:**
  - `app/Http/Controllers/RequestTransferController.php` (method `data`, `create`, `transition`, `doReceive`) [MODIFY]
  - `resources/views/request-transfer/index.blade.php` [MODIFY]
  - `resources/views/request-transfer/show.blade.php` [MODIFY]
- **Fitur Baru:**
  - Data transfer ter-scope ke gudang asal/tujuan yang menjadi hak akses user login.
  - Guardrail SoD: Jika transfer dari Operasional ke Fulfillment, tombol konfirmasi terima hanya muncul untuk role fulfillment tujuan.
  - Modal/Form Konfirmasi Penerimaan: Input `Qty Baik`, `Qty Rusak`, dan `Keterangan Rusak`. Saldo stok fulfillment hanya bertambah sebesar `Qty Baik`.
- **Definition of Done (DoD):**
  - Upaya konfirmasi terima oleh pihak pengirim menghasilkan error 403 Forbidden. Penerimaan oleh fulfillment memisahkan stok baik dan mencatat log kerusakan transit.

---

#### Task 7: Comprehensive Automated Testing & Regression Safety
- **Tujuan:** Menjamin keandalan sistem dengan rangkaian pengujian otomatis unit dan fitur komprehensif.
- **Daftar Berkas:**
  - `tests/Unit/LocationScopeServiceTest.php` [NEW]
  - `tests/Feature/MultiOutputBatchProductionTest.php` [NEW]
  - `tests/Feature/RequestTransferSodAndQualityTest.php` [NEW]
  - `tests/Feature/StokRencanaAndFormattingTest.php` [NEW]
- **Skenario Uji:**
  1. Formatting test: Qty integer tidak memiliki `.00`, angka pecahan mempertahankan desimal, harga tetap berformat Rupiah.
  2. Formula test: Kolom rencana bahan berkurang saat ada alokasi batch aktif; kolom rencana produk jadi bertambah saat ada batch berjalan.
  3. Multi-output test: 1 batch menghasilkan 2 varian produk jadi, stok masing-masing produk bertambah saat batch selesai.
  4. SoD test: Staff operasional dilarang mengonfirmasi terima pengiriman ke fulfillment (HTTP 403); staff fulfillment berhasil mengonfirmasi terima.
  5. Defect test: Barang rusak pada transfer tidak menambah saldo stok jual fulfillment.
  6. Scoping test: Staff gudang lokal hanya melihat data gudangnya sendiri; Manager melihat data seluruh gudang.
- **Definition of Done (DoD):**
  - Seluruh test suite (`php artisan test`) lulus 100% tanpa regresi pada test yang sudah ada.

---

## 7. Rekomendasi Eksekusi & Batasan Agen Pelaksana

1. **Jalankan Secara Berurutan (Task 1 sampai Task 7):** Jangan merombak Controller/Blade sebelum migrasi database pada Task 1 dijalankan.
2. **Backward Compatibility:** Pastikan kolom `batch_produksi.produk_id` dan `batch_produksi.qty_rencana` tetap diisi dengan output utama (atau agregat) agar query analitis lama tidak rusak.
3. **Patuhi Batasan UI Testing:** User telah menegaskan: *"untuk testing ui nanti saya manual jangan mbuka web sendiri"*. Seluruh verifikasi agen wajib dilakukan melalui unit/feature test (`php artisan test`) dan kompilasi build (`npm run build`), tanpa membuka browser otomatis.
