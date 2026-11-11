# QA STLC Master Test Plan & Excel Test Case Workbook — Design Specification

**Project:** Sistem Supply Chain Online (SC Online) — CV. Heaven Scent  
**Acuan Utama:** `PRD_SC_Online_v2.4.md` (Versi 2.4 — Purchasing 9 Stage, Lead Time, Request, Transfer & Produksi) & Codebase Sistem Berjalan (Laravel 12 / PHP 8.4)  
**Tanggal:** 11 November 2026  
**Peran Penyusun:** Senior Quality Assurance Engineer (ISTQB / ISO-29119 / IEEE 829 Standard)

---

## 1. Tujuan & Latar Belakang (Intent & Why)

Dokumen spesifikasi ini merancang **Master Test Case Workbook dalam format Microsoft Excel (`.xlsx`)** dengan standar **STLC (Software Testing Life Cycle)** kelas enterprise untuk tim Quality Assurance (QA), System Analyst, Developer, dan Stakeholder UAT di CV. Heaven Scent.

Penyusunan test case tidak hanya menyalin 42 skenario uji tingkat tinggi pada Lampiran B `PRD_SC_Online_v2.4.md`, melainkan **membedah seluruh fungsionalitas sistem yang sudah terimplementasi saat ini** beserta seluruh aturan bisnis (`BR-01` s/d `BR-11`), *Functional Requirements* (`FR-*`), *Override Operasional Terkunci* (#1 presisi hari Lead Time 1 desimal *half-up*, #2 QC kerusakan transit Transfer & dashboard bulanan per gudang), rumus matematika Analisa Stok (Lokal, Impor Varian/Non-Varian, Fulfillment), keamanan RBAC & *Location Scope*, serta fitur eksisting sistem (BOM Excel Grid Import, Konversi UOM, Privacy Harga Gudang, Master & Operational Audit Trail, Custom Role & Divisi, Session Heartbeat, dan Quick Role Switcher).

---

## 2. Evaluasi Kebutuhan `skills.sh` & Tooling

1. **Status Tooling Lokal:**
   - Lingkungan kerja memiliki **PHP 8.4.27 (CLI)** dengan ekstensi native `zip` (`ZipArchive`), `xmlwriter` (`XMLWriter`), `dom` (`DOMDocument`), dan `SimpleXML` yang aktif.
   - Paket Superpowers (`using-superpowers`, `brainstorming`, `writing-plans`, `executing-plans`, `verification-before-completion`) telah aktif dan digunakan sebagai proses kerja utama.
2. **Strategi Skill (`skills.sh` / Local Workspace Skill):**
   - Pembuatan file `.xlsx` native **tidak memerlukan instalasi paket eksternal dari internet** yang rawan terblokir sandbox, karena kita dapat membangun mesin OpenXML `.xlsx` generator murni menggunakan PHP `ZipArchive` + `XMLWriter` yang mendukung penuh multi-worksheet, styling warna/font/border, *freeze panes*, *auto-filter*, *data validation dropdown*, dan *live Excel formulas* (`COUNTIF`, `SUM`, persentase eksekusi).
   - Sebagai tambahan agar keahlian ini permanen di repositori sesuai instruksi *"jika memerlukan skills tambahkan terlebih dahulu"*, kita akan membuat dan mendaftarkan **Workspace Skill lokal** di `.agent/skills/qa-stlc-excel/SKILL.md` yang mendokumentasikan standar STLC QA Workbook Generator di proyek ini.

---

## 3. Pendekatan Arsitektur Pembuatan Excel (Approaches)

| Pendekatan | Deskripsi | Kelebihan | Kekurangan | Keputusan |
| :--- | :--- | :--- | :--- | :---: |
| **A. Native PHP 8.4 OpenXML `.xlsx` Builder (`ZipArchive` + `XMLWriter`)** | Membangun generator PHP mandiri (`scripts/generate-qa-test-cases-xlsx.php`) yang menyusun struktur OOXML `.xlsx` lengkap dengan stylesheet, rumus dinamis, dropdown validasi, *freeze panes*, dan lebar kolom terukur. | 100% berjalan di dalam sandbox tanpa dependensi eksternal, mendukung multi-sheet (12 tab), formula Excel hidup, styling tabel QA profesional, dan dapat dijalankan ulang kapan saja (`php scripts/generate-qa-test-cases-xlsx.php`). | Perlu menulis helper XML OpenXML secara rapi di script generator. | **DIPILIH (Recommended)** |
| **B. CSV / HTML-Table `.xls` palsu** | Mengekspor teks CSV atau tabel HTML yang diberi ekstensi `.xls`. | Sangat cepat dibuat. | Hanya mendukung 1 sheet, memicu peringatan *"File format and extension don't match"* di Excel modern, tidak mendukung dropdown validasi dan multi-tab STLC. | Ditolak |
| **C. Instalasi Library Pihak Ketiga via Composer/NPM (`phpspreadsheet` / `exceljs`)** | Mengunduh library eksternal dari internet. | API tingkat tinggi. | Membutuhkan akses jaringan di luar sandbox dan menambah beban `vendor/` atau `node_modules/` untuk tugas yang bisa diselesaikan native oleh PHP `ZipArchive`. | Ditolak |

---

## 4. Standar STLC (Software Testing Life Cycle) yang Diterapkan

Workbook Excel dirancang mengikuti 6 Fase STLC standar industri:

1. **Phase 1 — Requirement Analysis:**
   - Keterlacakan (*Traceability*) 100% terhadap `FR-DASH`, `FR-PUR`, `FR-LT`, `FR-PRD`, `FR-REQ`, `FR-TRF`, `FR-ANL`, `FR-STK`, `FR-MST`, `FR-LAP`, `BR-01`..`BR-11`, `TC-01`..`TC-42`, serta fitur kode sistem saat ini.
2. **Phase 2 — Test Planning:**
   - Cakupan uji (*In-Scope* & *Out-of-Scope*), strategi uji (*Functional Positive*, *Negative/Validation*, *Boundary Value Analysis / BVA*, *Equivalence Partitioning*, *Role/RBAC & SoD Security*, *Database Transaction Atomicity*, *Formula Accuracy*, *End-to-End Integration*), *Entry/Exit Criteria*, *Suspension/Resumption Criteria*, dan *Defect Severity & Priority SLA*.
3. **Phase 3 — Test Case Design & Development:**
   - Setiap Test Case wajib memiliki atribut standar Senior QA:
     - `TC_ID` (mis. `TC-PUR-001`)
     - `Ref PRD / FR / BR / Lampiran B` (mis. `FR-PUR-06, BR-01, TC-01`)
     - `Modul & Sub-Modul / Menu Route` (mis. `Purchasing > Buat PO (/purchasing/create)`)
     - `Test Scenario (High-Level Objective)`
     - `Test Case Title (Specific Condition)`
     - `Test Type` (`Positive`, `Negative`, `Boundary/BVA`, `Security/RBAC`, `Formula/Calculation`, `Integration/DB Atomicity`, `UI/UX`)
     - `Priority` (`P0 - Critical`, `P1 - High`, `P2 - Medium`, `P3 - Low`)
     - `Severity` (`S1 - Blocker`, `S2 - Critical`, `S3 - Major`, `S4 - Minor`)
     - `Actor / Persona Role` (`Manager - Andyka`, `Purchasing - Rina`, `Gudang - Budi`, `Operasional - Dedi`, `Fulfillment - Sari`)
     - `Pre-Conditions` (Kondisi awal sistem/database sebelum langkah dijalankan)
     - `Test Data` (Input spesifik: SKU, Gudang, Supplier, Qty, Harga, Timestamp, dsb.)
     - `Test Steps (Numbered Actionable Steps)`
     - `Expected Result (UI, Status, Database & Ledger Impact)`
     - `Post-Conditions`
     - `Actual Result` *(Kolom input QA)*
     - `Status` *(Dropdown: `Not Executed`, `Pass`, `Fail`, `Blocked`, `In Progress`, `Deferred`)*
     - `Executed By`, `Execution Date`, `Environment`, `Defect ID / Link`, `QA Notes / Evidence`
4. **Phase 4 — Test Environment & Test Data Setup:**
   - Panduan lengkap *seed data* (`php artisan migrate:fresh --seed`), kredensial 5 Persona + Admin, penggunaan *Quick Role Switcher*, dan parameter angka uji baku dari Bab 6 PRD v2.4.
5. **Phase 5 — Test Execution & Defect Tracking:**
   - Dilengkapi sheet `12_TC_E2E_UAT_DEFECT_LOG` berisi skenario End-to-End lintas divisi dan tabel pelacakan temuan bug (*Defect Log*).
6. **Phase 6 — Test Cycle Closure:**
   - Dilengkapi sheet `02_DASHBOARD_SUMMARY` dengan rumus Excel otomatis (`COUNTIF` & persentase kelulusan per modul dan per prioritas) untuk laporan penutupan siklus uji (*Test Sign-Off*).

---

## 5. Struktur 12 Worksheet dalam File Excel (`QA_Test_Cases_SC_Online_PRD_v2.4.xlsx`)

| No | Nama Sheet | Isi & Cakupan Utama | Estimasi Jumlah Baris / TC |
| :---: | :--- | :--- | :---: |
| **1** | `01_STLC_TEST_PLAN` | Kontrol Dokumen, Strategi STLC 6 Fase, Entry/Exit/Suspension Criteria, Definisi Priority (P0–P3) & Severity (S1–S4), Lingkungan Uji | Dokumen Standar STLC |
| **2** | `02_DASHBOARD_SUMMARY` | Executive QA Dashboard dengan **Formula Excel Otomatis** (`COUNTIF`, `SUM`, `% Pass Rate`, `% Test Coverage`) per Modul & per Priority | Tabel Metrik Otomatis |
| **3** | `03_TEST_DATA_PERSONA` | Kredensial 5 Persona + Admin, Matriks Hak Akses Menu & Permission, 5 Gudang (Induk-Cabang), Master SKU, BOM `GOH-P50`, Parameter Angka Uji Bab 6 | Referensi Data Uji |
| **4** | `04_RTM_TRACEABILITY` | **Requirements Traceability Matrix (RTM)**: Pemetaan 1:N dari seluruh `FR-*`, `BR-01`..`BR-11`, Override Operasional #1 & #2, dan `TC-01`..`TC-42` Lampiran B ke ID Test Case detail | ~85 Baris Pemetaan RTM |
| **5** | `05_TC_AUTH_RBAC_DASH` | Login/Logout, Session Security Ping (`/session/ping`), Quick Role Switcher (`/switch-role`), Custom Roles & Permissions (`/roles`), User & Divisi Management (`/users`, `/divisi`), Warehouse Location Scope (`global` vs `restricted`), Dashboard Role-Adaptive 5 Persona (`/`) | ~22 Test Cases |
| **6** | `06_TC_MASTER_BOM` | Master Produk (`/produk`), Kategori (`/kategori`), Varian (`/varian`), Klasifikasi ABC (`/klasifikasi-abc`), UOM & Konversi (`/uom`), Gudang & Hierarki Cabang (`/gudang`), Supplier & Multi-Supplier per Produk (`/supplier`, `PRODUK_SUPPLIER`), BOM CRUD & **Excel Grid Bulk Import** (`/bom/import`), Master Data Audit Trail | ~26 Test Cases |
| **7** | `07_TC_ANALISA_STOK` | Analisa Bahan Lokal (9 Stage LT, ADU, Buffer Hari, Batas Minimum, Target Stock, MOQ Ceiling), Bahan Impor (Ber-Varian vs Tanpa Varian `NC-V2-7`, Klasifikasi ABC Wajib A/A/B/C, Proyeksi, Status `PO`), Produk Jadi Fulfillment (Per Cabang & Agregasi `ALL`), Pilih & Bandingkan Multi-Supplier (`compare-suppliers`), Sumber LT (`historis` vs `manual`), Simpan Snapshot & Finalisasi, Buat PO Otomatis (`dari_analisa`) | ~30 Test Cases |
| **8** | `08_TC_PURCHASING_9STAGE` | Header-Detail Multi-SKU PO, Validasi `PRODUK_SUPPLIER` aktif, HPP Otomatis, Transisi Berurutan Stage 1–9 (`Perencanaan`, `Approval`, `Supplier Confirm`, `Payment`, `PO`, `Pengemasan`, `Pengiriman`, `Unloading`, `Input`), Putaran Penolakan Stage 2 & 3, Tombol `Dibayarkan` (DP/Cicilan) vs `Lanjut Tempo`, Visibilitas & **Privacy Harga Gudang** pasca Stage 5, Unloading (`BARANG_DATANG` belum posting, stok tetap) vs Input (Posting atomik), Edit Langsung (`PO_LOG_PERUBAHAN`) vs Revisi Komitmen (`PO_REVISI` di Stage 3–5, 6–7, 8–9), Pembatalan PO & Penerimaan Bertahap | ~42 Test Cases |
| **9** | `09_TC_LEAD_TIME_ENGINE` | Validasi Waktu Aktual (Mundur Valid vs Masa Depan / Sebelum Mulai), Kontinuitas Waktu Stage $N-1 \to N$ (`BR-03`), Kalkulasi Menit ($1\text{ hari}=1.440\text{ mnt}$), Jam (2 desimal), **Override Operasional #1: Presisi Hari 1 Desimal Half-Up** (`NUMERIC(10,1)`: $1.485\text{ mnt} \to 1,0\text{ hari}$; $1.512\text{ mnt} \to 1,1\text{ hari}$), Akumulasi 2-Level (`SUPPLIER_LT_*` & `SUPPLIER_PRODUK_LT_*`), Subtotal Internal/Supplier/Logistik, Indikator Stage `Terlambat`, Invalidasi Transaksi LT oleh Manager & Audit `SUPPLIER_LT_HISTORY` | ~22 Test Cases |
| **10** | `10_TC_REQUEST_TRANSFER` | Pemisahan Menu Request (`/requests`) & Transfer (`/transfers`), 2 Jenis Request (`request_bahan` & `request_produk_jadi`), Siklus Status Request, Edit Request `Disetujui` kembali ke `Diajukan`, Invarian Request Tidak Mengubah Stok, Relasi 1:1 Request–Transfer (`UNIQUE(request_id)`), 5 Jenis Transfer, Separation of Duties (Pengirim $\ne$ Penerima), Validasi Saldo Stok Asal, **Override Operasional #2: Invarian QC Transit** ($\text{qty\_dikirim} = \text{qty\_baik} + \text{qty\_rusak}$, hanya `qty_baik` masuk stok tujuan), **Dashboard & Rekap Matriks Bulanan Barang Rusak per Gudang** (`/transfers/defect-analytics`), Cetak Surat Jalan | ~32 Test Cases |
| **11** | `11_TC_PRODUKSI_STOK_LAP` | Batch Multi-Produk Jadi (`BATCH_PRODUKSI_ITEM`), Auto-Explode & Penggabungan BOM ke `ALOKASI_BAHAN`, Relasi N:1 Banyak Request ke 1 Batch (Kebutuhan vs Diminta vs Diterima), Release & Issue (Potong Stok Riil & Validasi Kurang Stok), Selesaikan Batch (`qty_baik`, `qty_rusak`, Yield % per produk), Pembatalan Batch (Lepas Alokasi), Stock Opname Batch (Teoritis vs Aktual), Ajukan Kirim Produk Jadi dari Batch, **Alert Stock 2 Kolom** (Kolom Stok Fisik vs Kolom Rencana), Mutasi Manual & Affiliate/KOL, Kartu Stok (*Append-Only Ledger*), Stock Opname Gudang, 7 Sub-Modul Laporan (`/laporan/*`) & Operational Audit | ~30 Test Cases |
| **12** | `12_TC_E2E_UAT_DEFECT_LOG` | 6 Skenario End-to-End (E2E) Lintas 5 Persona (Hulu ke Hilir: Analisa $\to$ PO 9 Stage $\to$ LT Update $\to$ Request Bahan $\to$ Transfer & QC Rusak $\to$ Batch Multi-Produk $\to$ Distribusi Fulfillment Cabang) + Template **Defect / Bug Report Log** siap pakai | ~6 E2E Flows + Defect Tracker |

**Total Cakupan:** **> 200 Skenario Test Case Detail** (mencakup 100% dari 42 skenario Lampiran B PRD v2.4 ditambah >160 skenario positif, negatif, *boundary value*, RBAC/SoD, kalkulasi rumus, dan integrasi sistem berjalan).

---

## 6. Lokasi Output File

- **File Workbook Utama:** `docs/qa/QA_Test_Cases_SC_Online_PRD_v2.4.xlsx` (dan salinan di root proyek `QA_Test_Cases_SC_Online_PRD_v2.4.xlsx` agar mudah diakses langsung oleh user).
- **Script Generator Reproducible:** `scripts/generate-qa-test-cases-xlsx.php`
- **Local Workspace Skill:** `.agent/skills/qa-stlc-excel/SKILL.md`
