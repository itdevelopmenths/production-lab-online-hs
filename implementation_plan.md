# Implementasi Enterprise Audit Table: Modul Purchasing, Produksi, Request & Transfer

Dokumen ini merinci rencana teknis arsitektur, pemodelan data, observer/trait, integrasi kontroler, dan antarmuka UI untuk sistem pencatatan jejak audit (*Audit Trail & Observability*) pada 4 modul operasional utama: **Purchasing**, **Produksi (Batch)**, **Request Bahan**, dan **Transfer Antar Gudang**.

Rencana ini disusun berdasarkan panduan **Enterprise Architecture & Task Execution Framework** (`.agent/enterprise-task-framework.md`).

---

## 1. Discovery & Requirements

### Latar Belakang & Masalah
Saat ini, aplikasi telah memiliki `MasterDataAudit` untuk data master statis (`produk`, `kategori`, `varian`, `uom`, `supplier`, `gudang`, `divisi`, `bom`). Namun, modul-modul transaksi inti (`purchasing`, `produksi`, `request`, dan `transfer`) belum memiliki sistem pencatatan audit komprehensif:
1. Model-model transaksi (`PurchaseOrder`, `BatchProduksi`, `RequestTransfer`) menggunakan **UUID** (`HasUuids`), sehingga tidak dapat menggunakan tabel `master_data_audits` yang kolom `auditable_id`-nya berupa `unsignedBigInteger`.
2. Modul transaksi memiliki alur siklus hidup dinamis (*workflow state machines*): pengajuan, persetujuan (*approval*), pencatatan penerimaan fisik gudang, alokasi/pemotongan bahan baku, penyelesaian batch, dan pencatatan pembayaran termin.
3. Kebutuhan transparansi, akuntabilitas, dan tata kelola laboratorium farmasi/kosmetik mewajibkan pelacakan siapa (*actor*), kapan (*timestamp*), pada dokumen mana (*document reference*), status apa yang berubah (*state transition*), rincian perubahan kuantitas/biaya (*diff*), serta dari IP/perangkat mana aksi tersebut dilakukan.

### Modul Terdampak & Batasan Bisnis
1. **Purchasing (`purchasing`)**:
   - Pelacakan PO baru (draft), pembaruan data/harga PO, pengajuan approval, approval oleh manager/direksi, penerimaan barang sebagian/lengkap di gudang, pencatatan pembayaran cicilan/lunas, dan pembatalan PO.
2. **Produksi (`batch_produksi`)**:
   - Pelacakan perencanaan batch baru, pelepasan & alokasi resep BOM (*release & issue*), penyelesaian batch dengan kuantitas baik/rusak/yield, opname batch, pengiriman produk jadi ke fulfillment, dan pembatalan batch.
3. **Request Bahan (`request_transfer` dengan `jenis = req_bahan`)**:
   - Pelacakan alur 5-tahap: Draft &rarr; Diajukan &rarr; Disetujui &rarr; Diproses &rarr; Selesai (atau Dibatalkan).
4. **Transfer Antar Gudang (`request_transfer` dengan `jenis != req_bahan`)**:
   - Pelacakan transfer antar gudang: Draft &rarr; Kirim Barang (Surat Jalan) &rarr; Selesai Diterima (atau Dibatalkan).

---

## 2. System Architecture & Data Modeling

### 2.1 Skema Database (`operational_audits`)
Tabel baru yang dioptimasi untuk transaksi bervolume tinggi dengan dukungan UUID:

```sql
CREATE TABLE operational_audits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auditable_type VARCHAR(100) NOT NULL,       -- App\Models\PurchaseOrder, App\Models\BatchProduksi, App\Models\RequestTransfer
    auditable_id VARCHAR(64) NOT NULL,          -- Mendukung UUID maupun Integer
    module VARCHAR(30) NOT NULL,                -- purchasing, produksi, request, transfer
    nomor_referensi VARCHAR(100) NOT NULL,      -- no_po, no_batch, no_transaksi
    event VARCHAR(50) NOT NULL,                 -- created, updated, submitted, approved, received, completed, paid, cancelled, deleted
    action_title VARCHAR(255) NOT NULL,         -- Ringkasan manusiawi (misal: "Approval PO senilai Rp 15.000.000")
    user_id BIGINT UNSIGNED NULL,              -- Relasi ke users (nullOnDelete)
    user_name VARCHAR(150) NULL,               -- Snapshot nama pengguna
    user_role VARCHAR(100) NULL,               -- Snapshot role/jabatan saat transaksi dilakukan
    old_values JSON NULL,                       -- Nilai atribut sebelum perubahan
    new_values JSON NULL,                       -- Nilai atribut setelah perubahan
    changed_fields JSON NULL,                   -- Daftar atribut yang berubah
    metadata JSON NULL,                         -- Snapshot kontekstual (jumlah item, termin bayar, gudang asal/tujuan)
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_auditable (auditable_type, auditable_id),
    INDEX idx_module_created (module, created_at),
    INDEX idx_referensi (nomor_referensi)
);
```

### 2.2 Model & Trait Arsitektur
1. **`App\Models\OperationalAudit`**:
   - Relasi: `auditable()` (MorphTo), `user()` (BelongsTo).
   - Method pembantu: `getFormattedDiffAttribute()`, `getEventBadgeAttribute()`, `getModuleBadgeAttribute()`.
2. **`App\Traits\AuditableOperation`**:
   - Menangani *auto-recording* pada event Eloquent `created`, `updated`, `deleted`.
   - Menyediakan method manual berkonteks tinggi:
     ```php
     $model->recordAudit(
         event: 'approved',
         actionTitle: 'Persetujuan PO oleh Manager',
         customChanges: ['status' => ['diajukan', 'disetujui']],
         metadata: ['approved_by' => auth()->user()->name]
     );
     ```
3. **Penerapan Trait pada Model**:
   - `PurchaseOrder`
   - `BatchProduksi`
   - `RequestTransfer`

### 2.3 Controller & Endpoints
- **`App\Http\Controllers\OperationalAuditController`**:
  - Endpoint DataTables: `GET /operational-audit/data` (Route: `operational-audit.data`).
  - Menerima parameter filter: `module` (`purchasing`, `produksi`, `request`, `transfer`, `all`), `auditable_id`, `search`, `date_start`, `date_end`.
  - Dilindungi middleware otorisasi berbasis permission (`purchasing.view`, `batch.view`, `rt.view`).

### 2.4 Desain Antarmuka UI/UX
1. **Blade Component `<x-operational-audit-tab :module="$module" />`**:
   - Terintegrasi dengan styling sistem yang elegan, modern, dan konsisten dengan tata letak aplikasi.
   - Kolom:
     - **Waktu Transaksi**: Format tanggal dan jam akurat dengan font mono.
     - **No. Dokumen / Referensi**: Nomor PO / Batch / Dokumen Mutasi yang dapat diklik langsung membuka detail dokumen terkait.
     - **Modul & Jenis Transaksi**: Badge identitas modul (Purchasing, Produksi, Request Bahan, Transfer Antar Gudang).
     - **Aksi & Event**: Badge status aksi visual (Dibuat, Diajukan, Disetujui, Diterima, Selesai, Bayar, Dibatalkan).
     - **Rincian Aktivitas & Nilai Berubah**: Deskripsi jelas + perbandingan nilai lama &rarr; nilai baru.
     - **Pelaksana (Actor)**: Nama pengguna + badge peran (*role*).
     - **Alamat IP & Perangkat**: IP audit dan perangkat browser.
     - Tombol **Segarkan Log** real-time dengan DataTables AJAX reload.
2. **Blade Component `<x-operational-audit-history :auditable="$model" />`**:
   - Kartu riwayat / timeline khusus yang ditaruh langsung di halaman detail (`show.blade.php`) masing-masing dokumen.

---

## 3. Proposed Changes

### Database Layer
#### [NEW] [2026_09_18_000002_create_operational_audits_table.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/database/migrations/2026_09_18_000002_create_operational_audits_table.php)
- Membuat tabel `operational_audits` dengan indeks performa tinggi.

---

### Domain & Model Layer
#### [NEW] [OperationalAudit.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Models/OperationalAudit.php)
- Model Eloquent lengkap dengan casting JSON, relasi polymorphic, dan visual badge formatter.

#### [NEW] [AuditableOperation.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Traits/AuditableOperation.php)
- Trait lifecycle logging untuk model transaksi ber-UUID.

#### [MODIFY] [PurchaseOrder.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Models/PurchaseOrder.php)
- Menggunakan trait `AuditableOperation`.
- Menentukan `getAuditReferenceNumber()` (`no_po`) dan `getAuditModuleName()` (`purchasing`).

#### [MODIFY] [BatchProduksi.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Models/BatchProduksi.php)
- Menggunakan trait `AuditableOperation`.
- Menentukan `getAuditReferenceNumber()` (`no_batch`) dan `getAuditModuleName()` (`produksi`).

#### [MODIFY] [RequestTransfer.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Models/RequestTransfer.php)
- Menggunakan trait `AuditableOperation`.
- Menentukan `getAuditReferenceNumber()` (`no_transaksi`) dan `getAuditModuleName()` (`$this->jenis === 'req_bahan' ? 'request' : 'transfer'`).

---

### Controller & Workflow Interception Layer
#### [NEW] [OperationalAuditController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/OperationalAuditController.php)
- Mengelola data feed DataTables untuk tab audit pada semua modul operasional dengan kontrol otorisasi ketat.

#### [MODIFY] [PurchasingController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/PurchasingController.php)
- Menambahkan pencatatan event audit eksplisit pada method: `submit()`, `approve()`, `receive()`, `pay()`, `cancel()`.

#### [MODIFY] [BatchController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/BatchController.php)
- Menambahkan pencatatan event audit eksplisit pada method: `release()`, `complete()`, `cancel()`, `opname()`, `kirim()`.

#### [MODIFY] [RequestTransferController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/RequestTransferController.php)
- Menambahkan pencatatan event audit eksplisit pada alur transisi status: `doSubmit()`, `doApprove()`, `doProcessOrShip()`, `doReceive()`, `doCancel()`.

#### [MODIFY] [routes/web.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/routes/web.php)
- Menambahkan route `Route::get('operational-audit/data', [OperationalAuditController::class, 'data'])->name('operational-audit.data');`.

---

### Presentation / Blade Layer
#### [NEW] [operational-audit-tab.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/operational-audit-tab.blade.php)
- Reusable Blade component yang memuat tab DataTables riwayat audit dengan filter dan styling modern.

#### [NEW] [operational-audit-history.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/operational-audit-history.blade.php)
- Reusable Blade component untuk menampilkan riwayat audit spesifik dokumen pada halaman `show.blade.php`.

#### [MODIFY] [purchasing/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/purchasing/index.blade.php)
- Menambahkan tab "Riwayat Audit Purchasing" berdampingan dengan tab PO, Tagihan AP, dan Approval.

#### [MODIFY] [purchasing/show.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/purchasing/show.blade.php)
- Menambahkan bagian Riwayat Audit & Jejak Aktivitas Dokumen PO.

#### [MODIFY] [batches/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/batches/index.blade.php)
- Menambahkan navigasi Tab: "Daftar Batch Produksi" dan "Riwayat Audit Produksi".

#### [MODIFY] [batches/show.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/batches/show.blade.php)
- Menambahkan bagian Riwayat Audit Batch Produksi.

#### [MODIFY] [request-transfer/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/request-transfer/index.blade.php)
- Menambahkan navigasi Tab: "Daftar Dokumen Mutasi & Transfer" dan "Riwayat Audit Mutasi".

#### [MODIFY] [request-transfer/show.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/request-transfer/show.blade.php)
- Menambahkan bagian Riwayat Audit Dokumen Mutasi/Transfer.

---

## 4. Verification Plan

### Automated Tests
1. **Migration & Model Verification**:
   - `php artisan migrate` berjalan sukses tanpa hambatan.
   - Test unit pada `OperationalAudit` model dan `AuditableOperation` trait.
2. **Controller & Workflow Feature Tests**:
   - Menguji pembuatan PO baru mencatat baris audit `created`.
   - Menguji approval PO mencatat baris audit `approved` beserta nama approver.
   - Menguji release dan completion batch produksi mencatat audit `release` dan `complete`.
   - Menguji transisi Request Bahan dan Transfer mencatat audit `submitted`, `approved`, `received`.
3. **Execution Command**:
   - `php artisan test --filter="OperationalAudit|Purchasing|Batch|RequestTransfer"`

### Manual Verification
1. Masuk ke halaman Purchasing (`/purchasing`), klik Tab "Riwayat Audit Purchasing" & pastikan log PO muncul dan dapat disegarkan.
2. Buka salah satu detail PO (`/purchasing/{id}`), verifikasi riwayat jejak audit muncul di bawah detail.
3. Masuk ke halaman Produksi (`/batch`), beralih ke Tab "Riwayat Audit Produksi" & pastikan DataTables memuat data secara asinkron.
4. Masuk ke halaman Request & Transfer (`/request-transfer`), buka Tab "Riwayat Audit Mutasi" & uji filter Request Bahan vs Transfer.
