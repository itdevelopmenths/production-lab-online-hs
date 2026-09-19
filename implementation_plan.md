# Enterprise Implementation Plan: HPP Precision, Stock Opname UI, Log Ordering & Master Audit Cleanup

```text
┌──────────────────────────────────────────────┐
│  FASE 1 - 3: Architecture & Sprint Planning  │
│  Status: 🔄 Ready for Review & Execution     │
├──────────────────────────────────────────────┤
│  ✅ Scope & Requirements Identified          │
│  ✅ HPP Max 2 Desimal Architecture Designed   │
│  ✅ Log Pergerakan Descending Sort Formulated│
│  ✅ Opname Centered Layout & Live Stock Spec │
│  ✅ Master Data Audit Deprecation Mapped     │
│  ⚠️ Zero-regression on Operational Audits    │
└──────────────────────────────────────────────┘
```

## 1. Discovery & Business Context (FASE 1)

Berdasarkan instruksi pengguna dengan rujukan `@[.agent/enterprise-task-framework.md]`, terdapat 4 fokus peningkatan sistem:

1. **HPP Desimal Max 2 Digit:** Di modul Purchasing dan Stok Mutasi, seluruh representasi nilai HPP (Harga Pokok Penjualan / Perolehan) dibatasi maksimal 2 digit di belakang koma (contoh: `Rp 15.000` untuk bulat, `Rp 526,35` untuk pecahan, atau `Rp 1.000,5`), mengeliminasi pecahan mikro 4 digit (`106.666,6667`).
2. **Log Pergerakan Stok Sorting:** Di [resources/views/stok/pergerakan-log.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/pergerakan-log.blade.php), data mutasi fisik harus diurutkan berdasarkan tanggal & waktu terbaru secara default (paling atas adalah transaksi termutakhir).
3. **Penyempurnaan Stock Opname Fisik:** Di [resources/views/stok/opname.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/opname.blade.php):
    - Menampilkan stok tercatat di sistem saat ini secara jelas dan transparan saat form dibuka/diisi.
    - Mengadopsi tata letak halaman yang berposisi di tengah (_centered layout_) persis seperti [resources/views/uom/create.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/uom/create.blade.php) (`flex justify-center w-full` dengan kontainer `w-full max-w-4xl space-y-4`).
4. **Pembersihan Audit Master Data:** Menghapus seluruh tabel audit dan hak akses (permissions) audit pada modul-modul Master Data (Produk, Kategori, Varian, Gudang, Supplier, UOM, BOM, Divisi), namun **mempertahankan** audit operasional pada modul inti (`purchasing.audit`, `batch.audit`, `rt.audit`, dan `audit.view`).

---

## 2. System Architecture & Component Design (FASE 2)

### A. Format HPP Max 2 Digit

- **Backend Model & Controllers:**
    - [app/Models/PurchaseOrderItem.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Models/PurchaseOrderItem.php): Perbarui `formattedHpp()` agar membatasi pecahan hingga maksimal 2 digit desimal (`number_format(round($val, 2), 2, ',', '.')` dengan pembersihan trailing zero atau standard currency format).
    - [app/Http/Controllers/StokController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/StokController.php): Perbarui fungsi `formatHpp(float $val)` di Stok DataTables agar membatasi maksimal 2 digit di belakang koma.
- **Frontend Alpine.js:**
    - [resources/views/purchasing/create.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/purchasing/create.blade.php) dan [resources/views/purchasing/edit.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/purchasing/edit.blade.php): Update method `formatHpp(val)` pada kalkulator live PO dari `maximumFractionDigits: 4` menjadi `maximumFractionDigits: 2`.

### B. Urutan Log Pergerakan Stok (Descending Timestamp)

- [app/Http/Controllers/StokController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/StokController.php) (`pergerakanLogData`):
    - Tambahkan order bawaan query: `->orderByDesc('kartu_stok.created_at')->orderByDesc('kartu_stok.id')`.
    - Tambahkan custom column ordering untuk kolom `tanggal`:
        ```php
        ->orderColumn('tanggal', function ($query, $order) {
            $query->orderBy('kartu_stok.created_at', $order)->orderBy('kartu_stok.id', $order);
        })
        ```
- [resources/views/stok/pergerakan-log.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/pergerakan-log.blade.php):
    - Pastikan DataTables inisialisasi menggunakan `order: [[6, 'desc']]`.

### C. Layout & Saldo Sistem Stock Opname

- [resources/views/stok/opname.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/opname.blade.php):
    - Bungkus `x-page-header`, box panduan info, dan form dalam wrapper center:
        ```blade
        <div class="flex justify-center w-full">
            <div class="w-full max-w-4xl space-y-4">
                ...
            </div>
        </div>
        ```
    - Perjelas indikator saldo sistem: ketika komoditas dan lokasi gudang dipilih, tampilkan secara menonjol banner saldo sistem tercatat (`X [Satuan]`), status keterisian, serta helper text pada input kuantitas fisik.

### D. Deprekasi Audit Master Data

- **Database & Permissions:**
    - Buat migration `2026_09_18_000004_remove_master_data_audit_permissions.php` untuk menghapus permission: `produk.audit`, `gudang.audit`, `supplier.audit`, `uom.audit`, `bom.audit`, `divisi.audit`.
    - Bersihkan permission tersebut dari [app/Services/Authorization/PermissionCatalogService.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Services/Authorization/PermissionCatalogService.php).
- **Views Cleanup:**
    - Hapus tab Riwayat Audit dan kembalikan ke layout single card pada:
        - [resources/views/uom/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/uom/index.blade.php)
        - [resources/views/supplier/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/supplier/index.blade.php)
        - [resources/views/gudang/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/gudang/index.blade.php)
        - [resources/views/divisi/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/divisi/index.blade.php)
        - [resources/views/bom/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/bom/index.blade.php)
        - [resources/views/kategori/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/kategori/index.blade.php)
        - [resources/views/varian/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/varian/index.blade.php)
        - [resources/views/produk/index.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/produk/index.blade.php) (hapus tab audit, pertahankan tab filter kategori: Semua, Bahan, Kemas, Jadi).
- **Backend Route & Controller:**
    - Hapus route `master-data-audit/data` di [routes/web.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/routes/web.php) dan controller `MasterDataAuditController.php` beserta component `master-audit-tab.blade.php`.
- **Testing Update:**
    - Perbarui `OperationalAuditTest.php` untuk memvalidasi audit operasional (`purchasing`, `batch`, `rt`) tanpa bergantung pada audit master data.

---

## 3. Sprint Planning & Task Breakdown (FASE 3)

| Task ID    | Item Pekerjaan                                                 | Prioritas   | Estimasi | Komponen Terdampak                                                                  |
| ---------- | -------------------------------------------------------------- | ----------- | -------- | ----------------------------------------------------------------------------------- |
| **TSK-01** | HPP Format Max 2 Desimal di Purchase & Stok Mutasi             | P0 (Must)   | 15m      | `PurchaseOrderItem.php`, `StokController.php`, `create.blade.php`, `edit.blade.php` |
| **TSK-02** | Pengurutan Log Pergerakan Stok Berdasarkan Waktu Terbaru       | P0 (Must)   | 10m      | `StokController.php` (`pergerakanLogData`), `pergerakan-log.blade.php`              |
| **TSK-03** | Redesain Layout Stock Opname (Centered) & Tampilan Stok Sistem | P0 (Must)   | 15m      | `opname.blade.php`                                                                  |
| **TSK-04** | Hapus Permission & Seeder Audit Master Data                    | P0 (Must)   | 10m      | Migration baru, `PermissionCatalogService.php`                                      |
| **TSK-05** | Pembersihan View Tab Audit di Seluruh Modul Master Data        | P0 (Must)   | 20m      | 8 file index view master data                                                       |
| **TSK-06** | Cleanup MasterDataAuditController & Route                      | P1 (Should) | 10m      | `routes/web.php`, `MasterDataAuditController.php`, component                        |
| **TSK-07** | Automated Regression Test & Build Verification                 | P0 (Must)   | 15m      | `OperationalAuditTest.php`, `php artisan test`, `npm run build`                     |

---

## 4. Proposed Changes Detailed Spec (FASE 4)

### [Component: HPP Formatting]

#### [MODIFY] [PurchaseOrderItem.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Models/PurchaseOrderItem.php)

- Ubah `formattedHpp()`: jika ada desimal, batasi maksimal 2 angka desimal (`round($val, 2)`).

#### [MODIFY] [StokController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/StokController.php)

- Ubah `formatHpp(float $val)`: batasi maksimal 2 angka desimal.

#### [MODIFY] [create.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/purchasing/create.blade.php) & [edit.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/purchasing/edit.blade.php)

- Ubah `formatHpp(val)`: ganti `maximumFractionDigits: 4` menjadi `maximumFractionDigits: 2`.

---

### [Component: Log Pergerakan Stok]

#### [MODIFY] [StokController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/StokController.php)

- Tambahkan ordering default `created_at desc, id desc` dan orderColumn untuk `tanggal`.

#### [MODIFY] [pergerakan-log.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/pergerakan-log.blade.php)

- Pastikan konfigurasi order DataTables mengurutkan kolom index 6 secara descending.

---

### [Component: Stock Opname]

#### [MODIFY] [opname.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/opname.blade.php)

- Selaraskan layout kontainer menjadi `flex justify-center w-full` dengan wrapper `w-full max-w-4xl space-y-4` seperti `uom/create.blade.php`.
- Tampilkan indikator status stok sistem aktif secara persisten dan informatif saat produk & gudang dipilih.

---

### [Component: Master Data Audit Removal]

#### [NEW] [database/migrations/2026_09_18_000004_remove_master_data_audit_permissions.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/database/migrations/2026_09_18_000004_remove_master_data_audit_permissions.php)

- Menghapus 6 master data audit permissions dari tabel `permissions`.

#### [MODIFY] [PermissionCatalogService.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Services/Authorization/PermissionCatalogService.php)

- Hapus entri audit master data dari katalog perizinan peran.

#### [MODIFY] [Master Data Views (8 files)](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/)

- `uom/index.blade.php`, `supplier/index.blade.php`, `gudang/index.blade.php`, `divisi/index.blade.php`, `bom/index.blade.php`, `kategori/index.blade.php`, `varian/index.blade.php`, `produk/index.blade.php`.
- Hapus tab navigation audit dan tag `<x-master-audit-tab />`.

#### [DELETE] [MasterDataAuditController.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/MasterDataAuditController.php) & [master-audit-tab.blade.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/master-audit-tab.blade.php)

- Hapus controller dan view komponen yang sudah tidak digunakan.

#### [MODIFY] [routes/web.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/routes/web.php)

- Hapus route `master-data-audit/data`.

#### [MODIFY] [OperationalAuditTest.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/tests/Feature/OperationalAuditTest.php)

- Sesuaikan test suite untuk fokus penuh pada operational audit (`purchasing`, `batch`, `rt`).

---

## 5. Verification & Testing Plan (FASE 5 & 6)

### Automated Tests:

1. `php artisan test --filter=OperationalAuditTest` (verifikasi audit operasional tetap aman 100%)
2. `php artisan test --filter=StokEnhancementTest` (verifikasi opname dan stok mutasi)
3. `php artisan test --filter=PurchasingFlowTest` (verifikasi kalkulasi HPP dan purchasing)
4. `php artisan test` (keseluruhan test suite)
5. `npm run build` (verifikasi asset frontend Vite)

### Manual Verification:

1. Cek tampilan HPP di Purchase Order Detail, Form Create/Edit PO, dan Halaman Stok & Mutasi: desimal maksimal 2 angka di belakang koma.
2. Cek halaman Log Pergerakan Stok: data teratas adalah transaksi paling baru.
3. Cek halaman Stock Opname Fisik: halaman berada tepat di tengah (center), dan stok di sistem tampil jelas.
4. Cek modul Master Data (UOM, Supplier, Gudang, Divisi, BOM, Kategori, Varian, Produk): tab audit sudah bersih dan kembali ke tampilan tabel master murni.
