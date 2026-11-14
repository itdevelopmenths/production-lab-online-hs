# Spesifikasi Arsitektur: Master Data Audit History & Dedicated BOM Import
**Proyek:** Heaven Scent Enterprise — Production Lab Online  
**Dokumen:** `.agent/master-data-audit-and-bom-import.md`  
**Standar Arsitektur:** Clean Architecture, Polymorphic Audit Logging, SOLID Principles, Data Integrity & Zero-Downtime Migration  

---

## 1. Executive Summary & Problem Statement

### Kebutuhan Bisnis:
1. **Audit History per Menu pada Semua Master Data**:
   - Master data (Produk, Gudang, Supplier, UOM, BOM, dan Divisi) adalah pondasi operasional lab produksi, purchasing, dan inventaris.
   - Perubahan data krusial (seperti perubahan nama bahan, satuan konversi, penonaktifan gudang, penambahan supplier, pembaruan formula BOM) saat ini belum memiliki histori perubahan terpusat per menu.
   - Diperlukan mekanisme audit trail otomatis yang merekam:
     - **Siapa** yang mengubah (`user_id`, `user_name`).
     - **Kapan** perubahan terjadi (`created_at`).
     - **Aksi apa** yang dilakukan (`created`, `updated`, `deleted`).
     - **Data apa** yang berubah (`old_values` vs `new_values`, per kolom).
     - **IP & User Agent** untuk kepatuhan tata kelola enterprise (*compliance*).
     - Tampilan tab/modal `Riwayat Audit` di setiap halaman menu Master Data.

2. **Dedicated Menu & Antarmuka Import BOM**:
   - Modul BOM saat ini hanya memiliki input file sederhana pada header index.
   - Diperlukan menu dan halaman khusus **Impor BOM** yang menyediakan:
     - Navigasi menu tersendiri di sidebar & tombol aksi pada halaman BOM.
     - Panduan format lengkap dan tombol **Unduh Template CSV**.
     - Area drag-and-drop file modern dengan validasi tipe file.
     - Dukungan multi-format (Format Sederhana CSV `;` dan format Ekspor POS Stamps).
     - Laporan eksekusi baris per baris (berhasil vs dilewati dengan alasan detail).
     - Pencatatan log audit impor secara otomatis.

---

## 2. Arsitektur Solusi Teknis

### A. Database Design (Polymorphic Audit Log)
Tabel: `master_data_audits`
- `id` (bigIncrements)
- `auditable_type` (string, indexed) — e.g. `App\Models\Produk`, `App\Models\Gudang`, dll.
- `auditable_id` (unsignedBigInteger, indexed)
- `user_id` (foreignId to `users`, nullable, indexed)
- `user_name` (string, nullable) — snapshot nama saat aksi terjadi
- `event` (enum/string: `created`, `updated`, `deleted`, `imported`)
- `old_values` (json, nullable) — state sebelum perubahan
- `new_values` (json, nullable) — state setelah perubahan
- `changed_fields` (json, nullable) — daftar key kolom yang berubah
- `ip_address` (string, nullable)
- `user_agent` (text, nullable)
- `created_at`, `updated_at` (timestamps, indexed)

### B. Polimorfisme & Otomatisasi (Trait & Observer)
- **Trait `App\Traits\AuditableMasterData`**:
  - Menyediakan relasi `audits(): MorphMany` ke `MasterDataAudit`.
  - Terpasang pada:
    - `App\Models\Produk`
    - `App\Models\Gudang`
    - `App\Models\Supplier`
    - `App\Models\Uom`
    - `App\Models\Bom`
    - `App\Models\Divisi`
- **Observer `App\Observers\MasterDataAuditObserver`**:
  - Mendeteksi event `created`, `updated`, `deleted`.
  - Menyaring kolom internal yang tidak relevan (seperti `remember_token`, `updated_at`).
  - Menghitung diff antara `$model->getOriginal()` dan `$model->getChanges()`.
  - Merekam data ke `MasterDataAudit` secara non-blocking dan aman transaksi.

### C. Antarmuka UI Audit History per Menu
Setiap menu Master Data (`produk`, `gudang`, `supplier`, `uom`, `bom`, `divisi`):
- Memiliki Tab / Panel **"Riwayat Perubahan (Audit)"** di samping tabel data utama.
- DataTables AJAX endpoint: `route('master-audit.data', ['model' => 'produk'])`.
- Kolom tabel audit:
  `Waktu | Item/Objek | Aksi | Perubahan (Sebelum -> Sesudah) | Dilakukan Oleh | IP`
- Badge visual untuk tipe aksi:
  - `created` -> Hijau (Dibuat)
  - `updated` -> Biru (Diperbarui)
  - `deleted` -> Merah (Dihapus)
  - `imported` -> Ungu (Diimpor)

### D. Fitur & Menu Dedicated Import BOM
1. **Routing**:
   - `GET /bom/import` -> `BomController::importPage` (Halaman upload & panduan).
   - `POST /bom/import` -> `BomController::import` (Eksekusi parsing & simpan).
   - `GET /bom/template` -> `BomController::downloadTemplate` (Unduh sample CSV siap pakai).
2. **Sidebar & Action**:
   - Navigasi sidebar: Sub-menu **Impor BOM** di bawah *BOM (Resep)* dengan `@can('bom.import')`.
   - Header tombol di `/bom`: Tombol aksi **Impor Resep BOM**.
3. **Komponen Halaman `resources/views/bom/import.blade.php`**:
   - Box info panduan tata letak kolom CSV.
   - Tombol unduh file contoh CSV resep.
   - Upload form dengan visual drag-and-drop, validasi ekstensi client-side & server-side.
   - Alert hasil impor dengan rincian ringkasan jumlah data berhasil dan daftar baris yang dilewati beserta alasannya.

---

## 3. Delapan Fase Eksekusi

1. **Discovery & Requirements**: Pemetaan skema kolom semua master data dan format CSV BOM.
2. **System Architecture**: Perancangan tabel `master_data_audits`, trait model, observer, dan service importer.
3. **Sprint Planning**: Pembagian 6 sprint teknis (Migration -> Trait/Observer -> Endpoint Controller -> UI Audit Tabs -> BOM Import Page & Template -> QA/Test).
4. **Development Execution**: Implementasi kode backend dan antarmuka Blade + Tailwind + AlpineJS.
5. **Testing & QA**: Automated tests untuk event audit tiap master data, test impor CSV BOM, dan Vite asset build.
6. **Security & Compliance**: Verifikasi otorisasi `@can`, proteksi CSV injection, dan validasi MIME file.
7. **Deployment & Verification**: Migrasi database dan verifikasi interaktif.
8. **Documentation**: Pembaruan walkthrough dan panduan operasional.
