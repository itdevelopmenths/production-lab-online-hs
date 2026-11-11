# QA STLC Excel Test Case Suite (PRD v2.4 & Current System) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun Master Workbook Test Case QA berstandar STLC (ISTQB / ISO-29119 / IEEE 829) dalam bentuk file Microsoft Excel (`.xlsx`) asli dengan 12 worksheet terstruktur, >200 skenario uji detail, Requirements Traceability Matrix (RTM), rumus dashboard otomatis, dan dropdown status eksekusi yang mengacu 100% pada `PRD_SC_Online_v2.4.md` serta implementasi kode sistem saat ini.

**Architecture:** Kita akan menambahkan skill lokal `.agent/skills/qa-stlc-excel/SKILL.md` dan membangun generator OpenXML `.xlsx` mandiri menggunakan PHP 8.4 native (`ZipArchive` + `XMLWriter`) di `scripts/generate-qa-test-cases-xlsx.php` yang mengambil definisi skenario modular dari `scripts/qa_test_cases/` lalu merakit file `QA_Test_Cases_SC_Online_PRD_v2.4.xlsx` (beserta salinan di `docs/qa/QA_Test_Cases_SC_Online_PRD_v2.4.xlsx`) dan memverifikasi integritasnya melalui automated test/script check.

**Tech Stack:** PHP 8.4 CLI (`ZipArchive`, `XMLWriter`, `DOMDocument`), Office Open XML (`.xlsx` Specification), Superpowers Workflow.

**Spec:** `docs/superpowers/specs/2026-11-11-qa-stlc-excel-test-cases-design.md`

## Global Constraints
- **100% Kepatuhan PRD v2.4 & Override Operasional Terkunci:**
  1. Presisi satuan **hari** Lead Time wajib menggunakan toleransi **1 angka di belakang koma (`NUMERIC(10,1)`)** dengan pembulatan *half-up* (digit ke-2 $< 5 \to 0$, $\ge 5 \to 1$; contoh $1.485\text{ menit} = 1,03125\text{ hari} \to 1,0\text{ hari}$; $1.512\text{ menit} = 1,05\text{ hari} \to 1,1\text{ hari}$), sedangkan jam ditampilkan 2 desimal dan perhitungan internal memakai menit bulat ($1\text{ hari} = 1.440\text{ menit}$).
  2. Modul **Transfer** wajib memvalidasi invarian eksak $\text{qty\_dikirim} = \text{qty\_baik} + \text{qty\_rusak}$ saat penerimaan, hanya memasukkan `qty_baik` ke stok gudang tujuan, menyimpan `qty_rusak` + `keterangan_rusak` secara permanen, serta menampilkan **Dashboard & Rekap Matriks Bulanan Barang Rusak per Gudang** di halaman Index Transfer (`/transfers`).
- **100% Keterlacakan Lampiran B (`TC-01` s/d `TC-42`) + Fitur Sistem Saat Ini:** Seluruh 42 skenario Lampiran B PRD v2.4 wajib dipetakan di sheet RTM dan dijabarkan menjadi langkah uji rinci bersama fitur aktual codebase (RBAC, Warehouse Location Scope, Quick Role Switcher, Session Heartbeat, BOM Excel Grid Import, Konversi UOM, Privacy Harga Gudang, Master & Operational Audit).
- **Standar Format Excel Native (`.xlsx`):** File harus berupa arsip OOXML `.xlsx` valid (bukan CSV atau HTML), memiliki 12 worksheet, *freeze panes* pada baris header, *auto-filter*, *text wrapping*, *column width* proporsional, *Data Validation Dropdown* untuk kolom Status (`Not Executed`, `Pass`, `Fail`, `Blocked`, `In Progress`, `Deferred`), serta rumus `COUNTIF`/`SUM` aktif di sheet `02_DASHBOARD_SUMMARY`.
- **Zero External Network Dependency:** Menggunakan ekstensi native PHP 8.4 `zip` dan `xmlwriter` yang sudah terinstal di lingkungan kerja, ditambah skill lokal `.agent/skills/qa-stlc-excel/SKILL.md`.

## Review Focus
- **Formula Reference Integrity:** Rumus `COUNTIF` pada sheet `02_DASHBOARD_SUMMARY` harus merujuk tepat ke kolom `Status` (Kolom P) dan kolom `Priority` (Kolom G) pada sheet test case `05` s/d `12` tanpa menghasilkan `#REF!` atau `#NAME?`.
- **XML Escaping & UTF-8 Safety:** Seluruh karakter khusus di rumus dan teks test case (`<`, `>`, `&`, `"`, `'`, simbol matematika $\le, \ge, \times, \div$) harus di-escape dengan benar melalui `XMLWriter` agar Excel tidak menampilkan peringatan *Corrupted / Repaired Workbook*.
- **Sheet Name Length Limit:** Seluruh nama tab worksheet wajib $\le 31$ karakter dan bebas dari karakter terlarang Excel (`: \ / ? * [ ]`).
- **Exact Numeric Match with PRD Chapter 6:** Skenario uji Analisa Stok Lokal (`ALK-01`), Impor (`BTL-P50` varian Bening), Fulfillment (`GOH-P50`), dan Lead Time 9 Stage harus menggunakan angka eksak dari Bab 6 PRD v2.4 (mis. ADU `1.310,11`, Batas Minimum `26.202,17`, Target Stock `45.853,79`, Durasi `13.020` menit = `9,0` hari).
- **Separation of Duties (SoD) & Privacy Coverage:** Skenario uji harus menguji penolakan sistem saat user yang sama mencoba mengirim sekaligus menerima Transfer (SoD), atau saat role Gudang mencoba melihat harga PO (`purchasing.price.view`).

---

### Task 1: Pembuatan Skill Lokal QA STLC Excel & Core OpenXML `.xlsx` Engine

**Files:**
- Create: `.agent/skills/qa-stlc-excel/SKILL.md`
- Create: `scripts/lib/XlsxWorkbookBuilder.php`

**Interfaces:**
- Consumes: PHP 8.4 native `ZipArchive` and `XMLWriter`.
- Produces: Class `XlsxWorkbookBuilder` with methods:
  - `addSheet(string $name, array $columns, array $rows, array $options = []): void`
  - `save(string $outputPath): void`

- [ ] **Step 1: Buat dokumentasi skill lokal `.agent/skills/qa-stlc-excel/SKILL.md`**
  Mendokumentasikan standar pembuatan Test Plan & Test Case Excel berbasis STLC (ISTQB/IEEE 829) serta penggunaan `XlsxWorkbookBuilder` untuk menghasilkan workbook `.xlsx` multi-tab di proyek SC Online.

- [ ] **Step 2: Implementasikan `scripts/lib/XlsxWorkbookBuilder.php`**
  Membangun class generator OOXML `.xlsx` yang mendukung:
  - Pembuatan `[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/_rels/workbook.xml.rels`, `xl/styles.xml`, dan `xl/worksheets/sheet{N}.xml`.
  - Gaya sel (`styleId`): Header Utama Navy/Slate, Sub-Header, Data Cell Wrap Text, Monospace Code/ID Cell, Badge Priority (`P0`–`P3`), Status Cell, dan Summary Metric Card.
  - Dukungan sel bertipe formula (`<f>...</f>`) maupun teks/angka, *Freeze Panes* (`<pane ySplit="..." state="frozen"/>`), *AutoFilter*, pengaturan lebar kolom (`<col min="..." max="..." width="..."/>`), dan *Data Validation List* (`Not Executed,Pass,Fail,Blocked,In Progress,Deferred`).

- [ ] **Step 3: Verifikasi `XlsxWorkbookBuilder` dengan smoke test CLI**
  Run: `php -l scripts/lib/XlsxWorkbookBuilder.php`
  Expected: `No syntax errors detected in scripts/lib/XlsxWorkbookBuilder.php`

---

### Task 2: Penyusunan Sheet 1–4 (STLC Test Plan, Dashboard Summary, Test Data & Persona, dan RTM)

**Files:**
- Create: `scripts/qa_test_cases/sheets_meta_and_rtm.php`

**Interfaces:**
- Consumes: Spesifikasi `PRD_SC_Online_v2.4.md` Bab 1–10 & Lampiran B (`TC-01`..`TC-42`), `MasterDataSeeder.php`, `UserSeeder.php`, `PermissionSeeder.php`.
- Produces: Fungsi `getMetaAndRtmSheets(): array` yang mengembalikan konfigurasi lengkap untuk Sheet 1 s/d Sheet 4:
  - `01_STLC_TEST_PLAN`: Kontrol Dokumen, Ruang Lingkup, 6 Fase STLC, Strategi Uji, Entry/Exit Criteria, Suspension/Resumption Criteria, Definisi Priority & Severity SLA, Environment Setup.
  - `02_DASHBOARD_SUMMARY`: Ringkasan Eksekutif Eksekusi QA dengan rumus Excel dinamis (`COUNTIF`, `SUM`, `% Progress`, `% Pass Rate`) per Modul (Sheet 05–12) dan per Priority (`P0`–`P3`).
  - `03_TEST_DATA_PERSONA`: Tabel 5 Persona + Admin (`manager@heavenscent.id`, `purchasing@heavenscent.id`, `gudang@heavenscent.id`, `operasional@heavenscent.id`, `fulfillment@heavenscent.id`, password `password`), 5 Gudang (`GD-PUSAT`, `GD-OPS`, `FF-PUSAT`, `FF-SBY`, `FF-SOLO`), Master SKU & MOQ (`ALK-01`, `OIL-GOH`, `BTL-P50`, `CAP-01`, `GOH-P50`), Resep BOM, dan Parameter Angka Uji Bab 6.
  - `04_RTM_TRACEABILITY`: Matriks Keterlacakan Persyaratan (*Requirements Traceability Matrix*) yang memetakan seluruh `FR-DASH-01`..`03`, `FR-PUR-01`..`21`, `FR-LT-01`..`06`, `FR-PRD-01`..`05`, `FR-REQ-01`..`08`, `FR-TRF-01`..`09`, `FR-ANL-01`..`09`, `FR-STK-01`..`03`, `FR-MST-01`, `FR-LAP-01`, `BR-01`..`BR-11`, Override Operasional #1 & #2, dan `TC-01`..`TC-42` Lampiran B ke ID Test Case spesifik.

- [ ] **Step 1: Tulis modul data `scripts/qa_test_cases/sheets_meta_and_rtm.php`**
- [ ] **Step 2: Cek sintaks PHP**
  Run: `php -l scripts/qa_test_cases/sheets_meta_and_rtm.php`
  Expected: `No syntax errors detected`

---

### Task 3: Penyusunan Sheet 5–7 (Auth/RBAC/Dashboard, Master Data & BOM, dan Analisa Stok)

**Files:**
- Create: `scripts/qa_test_cases/sheets_auth_master_analisa.php`

**Interfaces:**
- Consumes: Routes & Controllers untuk Auth, Role Switcher, Dashboard, Master Data, BOM Import, dan Analisa Stok (`AnalisaService.php`).
- Produces: Fungsi `getAuthMasterAnalisaSheets(): array` berisi >75 Test Cases detail dengan 19 kolom standar STLC:
  - `05_TC_AUTH_RBAC_DASH` (`TC-AUTH-001` s/d `TC-DASH-022`): Login valid/invalid, Session Heartbeat (`/session/ping`), Quick Role Switcher 5 persona, Pembatasan Menu per Role (PRD Bab 4), Warehouse Location Scope (`global` vs `restricted`), Custom Role & Permission CRUD (`/roles`), User & Divisi CRUD (`/users`, `/divisi`), serta Dashboard Role-Adaptive & Action Center (`FR-DASH-01`..`03`).
  - `06_TC_MASTER_BOM` (`TC-MST-001` s/d `TC-MST-026`): CRUD Produk, Kategori, Varian, Klasifikasi ABC (`Wajib A`, `A`, `B`, `C`), UOM & Konversi Satuan, Gudang Induk-Cabang, Supplier & Multi-Supplier per Produk (`PRODUK_SUPPLIER` aktif/nonaktif), CRUD BOM, Download Template & **Bulk Excel Grid Import BOM** (`/bom/import-bulk`), serta Master Data Audit Trail.
  - `07_TC_ANALISA_STOK` (`TC-ANL-001` s/d `TC-ANL-030`):
    - **Bahan Lokal (`ALK-01`)**: Perhitungan 9 Stage LT Average & Max, Total Buffer, ADU ($39.303,25 \div 30 = 1.310,11$), Batas Minimum ($26.202,17$), Target Stock ($45.853,79$), invariansi Batas Minimum saat Average LT berubah dari 12 ke 13 hari sedangkan Max tetap 18 hari (`TC-17`), pembulatan Qty Order ke atas kelipatan MOQ (`5.000`).
    - **Bahan Impor (`BTL-P50`)**: Perhitungan item ber-varian (Bening 60%, Out `47.312`, Avg LT `81,8` hari, Max LT `114` hari, Klasifikasi A = `4` hari, ADU Base, ADU ETA `866,7`, Buffer Days `36,25`, Safety Stock, Minimum Stock `70.856,0`, Target Stock `128.277,6`, Proyeksi, Selisih `-187.516,6`, Status `PO`) dan item tanpa varian (`NC-V2-7`).
    - **Produk Jadi Fulfillment (`GOH-P50`)**: Kalkulasi per gudang (`FF-PUSAT`, `FF-SBY`, `FF-SOLO`) dan agregasi level `ALL` (`Akan Datang` hanya dari `FF-PUSAT`).
    - **Multi-Supplier & Integrasi**: Pemilihan pasangan barang-supplier (`TC-31`), panel perbandingan antarsupplier (`compare-suppliers`), fallback LT `manual` vs `historis` (`TC-16`), Simpan Snapshot ke `RIWAYAT_ANALISA`, dan tombol **Buat PO** otomatis (`dari_analisa = true`).

- [ ] **Step 1: Tulis modul data `scripts/qa_test_cases/sheets_auth_master_analisa.php`**
- [ ] **Step 2: Cek sintaks PHP**
  Run: `php -l scripts/qa_test_cases/sheets_auth_master_analisa.php`
  Expected: `No syntax errors detected`

---

### Task 4: Penyusunan Sheet 8–9 (Purchasing 9 Stage, Revisi, Payment & Lead Time 2-Level)

**Files:**
- Create: `scripts/qa_test_cases/sheets_purchasing_leadtime.php`

**Interfaces:**
- Consumes: `PurchasingController.php`, `PurchasingStageController.php`, `PRD_SC_Online_v2.4.md` Bab 5.2, 5.3, 6.5, 6.6, dan Lampiran B (`TC-01`..`TC-16`, `TC-26`..`TC-37`).
- Produces: Fungsi `getPurchasingAndLeadTimeSheets(): array` berisi >60 Test Cases detail:
  - `08_TC_PURCHASING_9STAGE` (`TC-PUR-001` s/d `TC-PUR-042`):
    - Pembuatan PO multi-item, perhitungan HPP otomatis, penolakan item yang tidak terdaftar di `PRODUK_SUPPLIER` (`TC-32`).
    - Eksekusi normal berurutan Stage 1 (`Perencanaan`) s/d Stage 9 (`Input`) (`TC-01`) dan penolakan lompat stage (`TC-02`).
    - Penolakan Stage 2 (`Approval`) oleh Manager dengan alasan wajib $\to$ status `Dikembalikan`, buka putaran ke-2 Stage 1 (`TC-12`).
    - Stage 3 (`Supplier Confirm`): konfirmasi normal, penolakan supplier kembali ke Stage 1, dan selisih harga/qty yang wajib memicu revisi (`BR-05`).
    - Stage 4 (`Payment`): validasi tombol `Dibayarkan` nonaktif jika belum ada pembayaran (`TC-27`), pencatatan DP 30% + klik `Dibayarkan` meloloskan PO ke Stage 5 walau `Belum Lunas` (`TC-26`), skema `Tempo` memakai tombol `Lanjut Tempo` tanpa pembayaran (`TC-28`), dan pencatatan cicilan/pelunasan berikutnya di sub-tab Pembayaran tanpa mengubah durasi Stage 4 (`TC-29`).
    - Stage 5 (`PO`): penerbitan PO resmi, visibilitas PO untuk Gudang, dan **proteksi privasi harga PO dari role Gudang**.
    - Stage 6 (`Pengemasan`) & Stage 7 (`Pengiriman`) terpisah (`TC-07`), penolakan konfirmasi tiba oleh Gudang saat masih di Stage 6 (`TC-08`), validasi ekspedisi wajib sebelum Konfirmasi Tiba.
    - Stage 8 (`Unloading`): hanya dapat diselesaikan oleh Gudang/Manager (`TC-09`), mencatat `qty_fisik` dan kondisi ke `BARANG_DATANG` (`status_posting = belum`) tanpa mengubah `STOK` & `KARTU_STOK` (`TC-10`).
    - Stage 9 (`Input`): posting penerimaan dalam 1 transaksi DB atomik (tambah `STOK`, tulis `KARTU_STOK` `in`, tutup PO `Selesai`, tulis `PO_LEAD_TIME` & akumulasi LT), rollback penuh jika terjadi kegagalan sebagian (`TC-11`), dan penanganan penerimaan bertahap (`NC-08`).
    - **Edit Langsung vs Revisi Pasca-Approval**: edit langsung `no_resi` di Stage 7 tanpa approval tercatat di `PO_LOG_PERUBAHAN` (`TC-33`); pengajuan revisi qty di Stage 4 menahan penyelesaian stage (`TC-34`); persetujuan Manager menaikkan `revisi_ke` $+1$ tanpa mereset waktu stage (`TC-35`); penolakan revisi wajib alasan (`TC-36`); penguncian revisi komitmen di Stage 8–9 (`TC-37`).
    - Pembatalan PO oleh Manager sebelum Unloading selesai dengan alasan wajib tidak dihitung dalam LT (`TC-15`).
  - `09_TC_LEAD_TIME_ENGINE` (`TC-LT-001` s/d `TC-LT-022`):
    - Kontinuitas waktu mulai stage $N$ = waktu selesai stage $N-1$ (`TC-06`, `BR-03`).
    - Validasi waktu aktual: mundur valid diterima & `dicatat_pada` merekam waktu entri (`TC-04`); waktu selesai di masa depan atau $<$ waktu mulai ditolak (`TC-05`).
    - **Override Operasional #1 (Presisi Hari 1 Desimal Half-Up)**: pengujian seluruh 9 baris contoh Tabel Bab 6.5.2 (`1.485` mnt = `24,75` jam = `1,0` hari dari `1,03125`; `1.512` mnt = `1,05` hari $\to$ `1,1` hari; `210` mnt = `0,1458` hari $\to$ `0,1` hari; Total LT `13.020` mnt = `217,00` jam = `9,0` hari dari `9,0416`) (`TC-03`).
    - Akumulasi kumulatif Average & Maximum LT (`TC-13`) pada **2 level** sekaligus: level supplier (`SUPPLIER_LT_SUMMARY` + `STAGE_SUMMARY`) dan level supplier-produk (`SUPPLIER_PRODUK_LT_SUMMARY` + `STAGE_SUMMARY`) untuk PO berisi 3 produk (`TC-30`).
    - Perhitungan subtotal waktu Internal, Supplier, dan Logistik (`FR-LT-06`), indikator merah `Terlambat` bila durasi berjalan $>$ Average stage supplier, serta Invalidasi Transaksi LT oleh Manager dengan alasan wajib yang menghitung ulang Average/Max dan menambah baris append-only di `SUPPLIER_LT_HISTORY` (`TC-14`).

- [ ] **Step 1: Tulis modul data `scripts/qa_test_cases/sheets_purchasing_leadtime.php`**
- [ ] **Step 2: Cek sintaks PHP**
  Run: `php -l scripts/qa_test_cases/sheets_purchasing_leadtime.php`
  Expected: `No syntax errors detected`

---

### Task 5: Penyusunan Sheet 10–12 (Request & Transfer + QC Defect Dashboard, Produksi/Stok/Laporan, dan E2E + Defect Log)

**Files:**
- Create: `scripts/qa_test_cases/sheets_rt_produksi_stok_e2e.php`
- Create: `scripts/generate-qa-test-cases-xlsx.php`

**Interfaces:**
- Consumes: `RequestController.php`, `TransferController.php`, `BatchController.php`, `StokController.php`, `ReportController.php`, dan seluruh modul sheet Task 2–4.
- Produces:
  - Fungsi `getRtProduksiStokE2eSheets(): array` untuk Sheet 10 s/d Sheet 12.
  - File output `QA_Test_Cases_SC_Online_PRD_v2.4.xlsx` dan `docs/qa/QA_Test_Cases_SC_Online_PRD_v2.4.xlsx`.

- [ ] **Step 1: Tulis modul data `scripts/qa_test_cases/sheets_rt_produksi_stok_e2e.php`**
  Mencakup:
  - `10_TC_REQUEST_TRANSFER` (`TC-RT-001` s/d `TC-RT-032`):
    - Siklus Request Bahan (Operasional $\to$ Gudang Pusat) & Request Produk Jadi (Fulfillment $\to$ Operasional) (`TC-38`).
    - Invarian Request tidak mengubah `STOK` & `KARTU_STOK` (`TC-18`), penolakan wajib alasan (`TC-19`), edit Request `Disetujui` sebelum ada Transfer mengembalikan status ke `Diajukan` (`FR-REQ-08`).
    - Penolakan pembuatan Transfer dari Request yang masih `Diajukan` (`TC-20`), aturan 1:1 Request–Transfer (`UNIQUE(request_id)`, tolak Transfer ke-2 untuk Request yang sama `TC-39`), pembatalan Transfer `Draft` mengembalikan Request ke `Disetujui` (`FR-TRF-07`).
    - Pengiriman Transfer mengurangi stok asal & menulis `KARTU_STOK` `out` ref `transfer` (`TC-21`), penolakan kirim jika melebihi saldo stok asal (`TC-23`), Separation of Duties (pengirim tidak boleh menerima sendiri), dan penyelesaian Transfer otomatis mengubah status Request menjadi `Selesai` (`TC-24`).
    - 4 Jenis Transfer Langsung tanpa Request (`Retur Bahan`, `Kirim Produk Jadi`, `Antar Gudang Fulfillment`, `Retur Produk Jadi`) (`TC-25`).
    - **Override Operasional #2 (QC Kerusakan Transit & Dashboard Bulanan per Gudang)**: penerimaan `qty_dikirim = 2.000` dengan `qty_baik = 1.950` dan `qty_rusak = 50` hanya menambah stok tujuan `1.950` dan menyimpan `qty_rusak = 50` + `keterangan_rusak` (`TC-22`); penolakan jika $\text{qty\_baik} + \text{qty\_rusak} \ne \text{qty\_dikirim}$; serta verifikasi **Dashboard KPI & Matriks Rekap Bulanan Barang Rusak per Gudang** di `/transfers` dan `/transfers/defect-analytics` (`TC-42`).
  - `11_TC_PRODUKSI_STOK_LAP` (`TC-PRD-001` s/d `TC-LAP-030`):
    - Pembuatan Batch Multi-Produk Jadi (4 produk jadi dalam 1 batch) dengan penggabungan kebutuhan bahan BOM otomatis ke `ALOKASI_BAHAN` (`status = aktif`) tanpa mengurangi stok fisik (`TC-40`).
    - Keterkaitan $\ge 2$ Request Bahan ke 1 Batch dan tampilan perbandingan Kebutuhan BOM vs Total Diminta vs Total Diterima (`TC-41`).
    - `Release & Issue` memotong stok bahan Operasional secara riil & melepas alokasi (serta menolak jika stok Operasional kurang).
    - Penyelesaian Batch (`qty_baik` & `qty_rusak` per produk, kalkulasi Yield %, penambahan stok produk jadi), pembatalan Batch (melepas alokasi tanpa mutasi stok), Stock Opname Batch (Teoritis vs Aktual), dan tombol `Ajukan Kirim ke Fulfillment` multi-produk langsung dari detail batch.
    - **Stok & Mutasi**: verifikasi **Alert Stock 2 Kolom** (Kolom Stok Fisik+Inbound vs Kolom Rencana dengan pengaruh Alokasi Bahan sebagai pengurang bahan baku dan Batch Rencana sebagai penambah produk jadi), Mutasi Manual & kategori Affiliate/KOL, Kartu Stok *append-only*, dan Stock Opname Gudang.
    - **Laporan & Audit**: verifikasi keakuratan 7 sub-modul Laporan (`/laporan/produksi`, `pemakaian-bahan`, `defect`, `low-stock`, `purchasing`, `lead-time`, `fulfillment`) dan Operational Audit Log.
  - `12_TC_E2E_UAT_DEFECT_LOG` (`TC-E2E-001` s/d `TC-E2E-006` + Tabel Defect Tracker):
    - 6 Skenario End-to-End lintas 5 persona menggunakan Quick Role Switcher dari Analisa Stok hingga produk tiba di gudang cabang Fulfillment, dilengkapi tabel Defect / Bug Report Log berstandar STLC.

- [ ] **Step 2: Buat dan jalankan script utama `scripts/generate-qa-test-cases-xlsx.php`**
  Merakit ke-12 worksheet ke dalam `QA_Test_Cases_SC_Online_PRD_v2.4.xlsx` dan `docs/qa/QA_Test_Cases_SC_Online_PRD_v2.4.xlsx`, lalu menjalankan verifikasi otomatis (memeriksa keberadaan file, ukuran $>0$, validitas struktur ZIP & XML tiap sheet, jumlah sheet $= 12$, dan total Test Case $> 200$).
  Run: `php scripts/generate-qa-test-cases-xlsx.php`
  Expected: Output sukses menampilkan ringkasan 12 worksheet dan total >200 test cases tanpa error XML.
