# Spesifikasi Arsitektur: Dual-Format (Excel & CSV) Interactive Grid Importer untuk BOM
**Proyek:** Heaven Scent Enterprise — Production Lab Online  
**Dokumen:** `.agent/bom-excel-grid-import.md`  
**Fitur:** Impor Formula BOM Dual-Format (Excel .xlsx/.xls & CSV .csv/.txt) dengan Column & Row Inputable Data Grid  

---

## 1. Problem Statement & User Need
1. **Dukungan Dual-Format (Excel & CSV)**:
   - Pengguna membutuhkan fleksibilitas penuh: dapat mengunduh dan mengunggah format Microsoft Excel asli (`.xlsx`, `.xls`) MAUPUN format teks CSV (`.csv`, `.txt`).
2. **Column & Row Inputable (Interactive Spreadsheet Grid)**:
   - Data dari berkas yang diunggah (baik Excel maupun CSV) tidak langsung disimpan secara buta (*blind upload*), melainkan seketika dimuat ke dalam tabel baris & kolom di layar.
   - Pengguna dapat mengetik langsung (*inline editing*), mengganti SKU, mengubah kuantitas per unit, menambah baris manual (`+ Tambah Baris`), atau menghapus baris.
   - Mendukung *copy-paste* langsung dari tabel Microsoft Excel / Google Sheets via clipboard (`Ctrl + C` $\to$ `Ctrl + V`).
   - Validasi visual realtime sebelum data disimpan.

---

## 2. Arsitektur Teknis Solusi

### A. Frontend Layer (AlpineJS + SheetJS)
- **Engine Parsing Dual-Format**: Menggunakan SheetJS (`xlsx.full.min.js`) via CDN:
  - Membaca berkas `.xlsx`, `.xls`, `.csv`, dan `.txt` secara client-side.
  - Menghasilkan berkas template `.xlsx` terformat rapi dan template `.csv`.
- **State Management (Alpine.js `bomDualGridImporter`)**:
  - `rows`: Array reaktif `[{ id, sku_produk_jadi, sku_bahan, qty_per_unit, valid, message }]`.
  - `downloadTemplate(format)`: Menghasilkan berkas `.xlsx` atau `.csv`.
  - `handleFileUpload(event)`: Mengurai file Excel/CSV ke dalam `rows`.
  - `handlePaste(event)`: Mengurai clipboard text tabular menjadi baris grid.
  - `addRow()`: Menambah baris inputable kosong.
  - `removeRow(index)`: Menghapus baris tertentu.
  - `validateRows()`: Memvalidasi SKU terhadap katalog produk yang disediakan backend.

### B. Backend Layer (Laravel & Eloquent)
- **Endpoint**:
  - `GET /bom/import` $\to$ Menampilkan antarmuka grid inputable, menyertakan daftar katalog SKU produk jadi dan bahan aktif untuk autocomplete/lookup.
  - `GET /bom/template` $\to$ Mengunduh template resmi (mendukung `?format=excel` dan `?format=csv`).
  - `POST /bom/import-bulk` $\to$ Menyimpan array baris formula BOM dalam satu `DB::transaction`.
- **Audit Logging**:
  - Merekam setiap batch impor ke dalam `master_data_audits` dengan event `imported` dan rincian formula yang diproses.

---

## 3. Delapan Fase Eksekusi
1. **Discovery & Requirements**: Spesifikasi kolom (SKU Produk Jadi, SKU Bahan, Qty per Unit) dan dukungan dual-format (Excel & CSV).
2. **System Architecture**: Integrasi SheetJS parser + Alpine reactive grid + Bulk API Controller.
3. **Sprint Planning**: Pengerjaan backend API `importBulk`, pembuatan view grid inputable, dan generator template Excel & CSV.
4. **Development Execution**: Implementasi kode di `BomController`, routes, dan `resources/views/bom/import.blade.php`.
5. **Testing & QA**: Automated tests untuk `importBulk`, verifikasi parsing Excel & CSV, dan test validasi baris.
6. **Security & Compliance**: Validasi wewenang `@can('bom.import')` dan sanitasi input.
7. **Deployment**: Asset build & verifikasi UX interaktif.
8. **Documentation**: Pembaruan walkthrough dan referensi `.agent/`.
