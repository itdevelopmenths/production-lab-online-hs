<?php

declare(strict_types=1);

use App\QaExcel\XlsxWorkbookBuilder;

/**
 * Standard 19-column STLC Test Case header definition used across Sheets 05 to 12.
 *
 * Column Mapping:
 * A (1): TC ID
 * B (2): Ref PRD / FR / BR / Lampiran B
 * C (3): Module & Sub-Module (Route)
 * D (4): Test Scenario (High-Level Objective)
 * E (5): Test Case Title (Specific Condition)
 * F (6): Test Type
 * G (7): Priority
 * H (8): Severity
 * I (9): Actor / Role Persona
 * J (10): Pre-Conditions
 * K (11): Test Data / Input Parameters
 * L (12): Test Steps (Step-by-Step)
 * M (13): Expected Result (UI, Validation, Stock & DB State)
 * N (14): Post-Conditions
 * O (15): Actual Result
 * P (16): Status (Dropdown: Not Executed, Pass, Fail, Blocked, In Progress, Deferred)
 * Q (17): Executed By
 * R (18): Execution Date
 * S (19): Defect ID / Evidence / Remarks
 *
 * @return array<int, array{header: string, width: int|float}>
 */
function stlcTestCaseColumns(): array
{
    return [
        ['header' => 'TC ID', 'width' => 15],
        ['header' => 'Ref PRD / FR / BR / Lampiran B', 'width' => 24],
        ['header' => 'Module & Sub-Module (Route)', 'width' => 28],
        ['header' => 'Test Scenario (High-Level)', 'width' => 30],
        ['header' => 'Test Case Title', 'width' => 36],
        ['header' => 'Test Type', 'width' => 18],
        ['header' => 'Priority', 'width' => 15],
        ['header' => 'Severity', 'width' => 15],
        ['header' => 'Actor / Role Persona', 'width' => 22],
        ['header' => 'Pre-Conditions', 'width' => 36],
        ['header' => 'Test Data / Input Parameters', 'width' => 38],
        ['header' => 'Test Steps (Actionable Sequence)', 'width' => 52],
        ['header' => 'Expected Result (UI, Validation, Stok & DB)', 'width' => 54],
        ['header' => 'Post-Conditions', 'width' => 32],
        ['header' => 'Actual Result', 'width' => 28],
        ['header' => 'Status', 'width' => 16],
        ['header' => 'Executed By', 'width' => 16],
        ['header' => 'Execution Date', 'width' => 15],
        ['header' => 'Defect ID / Evidence / Remarks', 'width' => 28],
    ];
}

/**
 * Helper to construct a 19-column STLC Test Case row.
 *
 * @return array<int, mixed>
 */
function tcRow(
    string $tcId,
    string $prdRef,
    string $moduleRoute,
    string $scenario,
    string $title,
    string $testType,
    string $priority,
    string $severity,
    string $actor,
    string $preConditions,
    string $testData,
    string $steps,
    string $expectedResult,
    string $postConditions,
    string $remarks = ''
): array {
    return [
        $tcId,
        $prdRef,
        $moduleRoute,
        $scenario,
        $title,
        $testType,
        $priority,
        $severity,
        $actor,
        $preConditions,
        $testData,
        $steps,
        $expectedResult,
        $postConditions,
        '',
        'Not Executed',
        '',
        '',
        $remarks,
    ];
}

/**
 * Helper to construct a full-width section divider row.
 *
 * @return array<string, mixed>
 */
function sectionRow(string $label): array
{
    return ['_section' => true, 'label' => $label];
}

/**
 * Build Sheets 01 to 04:
 * - 01_STLC_TEST_PLAN
 * - 02_DASHBOARD_SUMMARY
 * - 03_TEST_DATA_PERSONA
 * - 04_RTM_TRACEABILITY
 *
 * @return array<int, array<string, mixed>>
 */
function getMetaAndRtmSheets(): array
{
    // =========================================================================
    // SHEET 01: 01_STLC_TEST_PLAN
    // =========================================================================
    $sheet1Cols = [
        ['header' => 'Kode / Bagian STLC', 'width' => 26],
        ['header' => 'Kategori / Fase STLC', 'width' => 28],
        ['header' => 'Parameter / Standar QA', 'width' => 34],
        ['header' => 'Spesifikasi Detail & Kriteria Operasional (PRD v2.4 & Sistem Berjalan)', 'width' => 68],
        ['header' => 'PIC / Owner', 'width' => 22],
    ];

    $sheet1Rows = [
        sectionRow('A. IDENTITAS DOKUMEN & KONTROL VERSI (DOCUMENT CONTROL)'),
        ['DOC-01', 'Identitas Sistem', 'Nama Aplikasi', 'Sistem Supply Chain Online (SC Online) — CV. Heaven Scent', 'Divisi IT & QA'],
        ['DOC-02', 'Acuan Spesifikasi', 'Dokumen Referensi Utama', 'PRD_SC_Online_v2.4.md (4 Nov 2026) + DOKUMENTASI_FLOW_BISNIS.md + Codebase Laravel 12/13 Berjalan', 'System Analyst & Senior QA'],
        ['DOC-03', 'Standar Metodologi', 'Framework Pengujian', 'STLC (Software Testing Life Cycle) berbasis ISTQB, ISO/IEC/IEEE 29119, dan IEEE 829 Standard', 'Senior QA Lead'],
        ['DOC-04', 'Versi Master Test Case', 'Versi & Tanggal Rilis', 'v2.4.0-QA (11 November 2026) — Mencakup 9-Stage Purchasing, 2-Level Lead Time, Multi-Supplier, Request/Transfer 1:1, QC Kerusakan Transit Bulanan, & Batch Multi-Produk', 'Senior QA Lead'],

        sectionRow('B. ENAM FASE STLC (SOFTWARE TESTING LIFE CYCLE PHASES)'),
        ['STLC-P1', '1. Requirement Analysis', 'Analisis Kebutuhan & Invarian Bisnis', 'Membedah seluruh Functional Requirements (FR-DASH, FR-PUR, FR-LT, FR-PRD, FR-REQ, FR-TRF, FR-ANL, FR-STK, FR-MST, FR-LAP), Business Rules (BR-01 s/d BR-11), Override Operasional #1 & #2, dan NC Default Behavior.', 'Senior QA & System Analyst'],
        ['STLC-P2', '2. Test Planning', 'Perencanaan Strategi & Cakupan Uji', 'Menetapkan cakupan pengujian fungsional & non-fungsional, matriks risiko, pembagian 12 worksheet, definisi Priority/Severity, serta Entry/Exit/Suspension Criteria.', 'Senior QA Lead'],
        ['STLC-P3', '3. Test Case Development', 'Perancangan Skenario & Data Uji', 'Menyusun >200 Test Cases detail lengkap dengan Pre-Conditions, Test Data numerik eksak dari Bab 6 PRD v2.4, langkah bernomor, Expected Result (UI + DB + Kartu Stok), serta RTM.', 'Senior QA Engineer'],
        ['STLC-P4', '4. Environment Setup', 'Penyiapan Lingkungan & Seed Data', 'Menyiapkan database bersih dengan `php artisan migrate:fresh --seed`, memverifikasi 5 akun Persona + Admin, Quick Role Switcher (`/switch-role`), 5 gudang, master SKU, dan BOM.', 'QA & DevOps / Developer'],
        ['STLC-P5', '5. Test Execution', 'Eksekusi Skenario & Pelacakan Defect', 'Menjalankan seluruh Test Case P0–P3 dari Sheet 05 s/d 12, mengisi kolom Actual Result & Status (`Pass`/`Fail`/`Blocked`), serta mencatat bug pada Defect Log (Sheet 12).', 'QA Tester & UAT User'],
        ['STLC-P6', '6. Test Cycle Closure', 'Evaluasi Metrik & Sign-Off Rilis', 'Mengevaluasi persentase kelulusan pada `02_DASHBOARD_SUMMARY` (Target: 100% P0 Pass, >=98% P1 Pass, 0 Blocker/Critical Open Defect) sebelum Go-Live.', 'QA Lead & Stakeholder'],

        sectionRow('C. ENTRY CRITERIA, EXIT CRITERIA, SUSPENSION & RESUMPTION CRITERIA'),
        ['CRIT-01', 'Entry Criteria', 'Syarat Mulai Pengujian (SIT/UAT)', '1. PRD v2.4 berstatus Acuan Utama.\n2. Seluruh migrasi DB dan seeder (`MasterDataSeeder`, `PermissionSeeder`, `UserSeeder`) berjalan tanpa error.\n3. Unit & Feature automated test suite (`php artisan test`) dapat dijalankan.\n4. Lingkungan pengujian memiliki akses Quick Role Switcher untuk 5 persona.', 'QA Lead'],
        ['CRIT-02', 'Exit Criteria', 'Syarat Selesai & Lulus Pengujian', '1. 100% Test Case telah dieksekusi (`Not Executed = 0`).\n2. 100% Test Case berprioritas `P0 - Critical` berstatus `Pass`.\n3. Minimal 95% Test Case berprioritas `P1 - High` berstatus `Pass`.\n4. Tidak ada defect terbuka dengan tingkat keparahan `S1 - Blocker` atau `S2 - Critical`.\n5. Seluruh 42 skenario penerimaan Lampiran B PRD v2.4 (`TC-01` s/d `TC-42`) lulus verifikasi.', 'QA Lead & Manager'],
        ['CRIT-03', 'Suspension Criteria', 'Kondisi Penghentian Sementara Uji', '1. Terjadi kegagalan fatal pada alur utama (mis. login gagal, transaksi DB korup, atau perhitungan stok tidak konsisten).\n2. Lebih dari 20% skenario P0 gagal pada siklus smoke test awal.\n3. Perubahan skema database mendadak tanpa pembaruan seeder.', 'QA Lead'],
        ['CRIT-04', 'Resumption Criteria', 'Syarat Melanjutkan Kembali Uji', '1. Bug Blocker penyebab suspensi telah diperbaiki oleh Developer.\n2. Smoke test dan regression test pada modul terdampak telah lulus 100%.', 'Developer & QA Lead'],

        sectionRow('D. KLASIFIKASI PRIORITY (P0–P3) DAN DEFECT SEVERITY SLA (S1–S4)'),
        ['SLA-P0', 'Priority: P0 - Critical', 'Jalur Kritis Bisnis & Integritas Data', 'Alur utama pengadaan 9-stage, akurasi saldo `STOK` & `KARTU_STOK`, kalkulasi rumus Analisa Stok & Lead Time 1 desimal half-up, invarian QC Transfer (`qty_dikirim = qty_baik + qty_rusak`), dan keamanan RBAC/SoD. Wajib diuji pertama kali.', 'Wajib 100% Pass'],
        ['SLA-P1', 'Priority: P1 - High', 'Validasi Aturan Bisnis & Alur Alternatif', 'Revisi PO pasca-approval (`PO_REVISI`), putaran penolakan Stage 2/3, cicilan/tempo pembayaran, invalidasi LT oleh Manager, relasi multi-Request ke Batch, dan dashboard defect bulanan per gudang.', 'Target >= 98% Pass'],
        ['SLA-P2', 'Priority: P2 - Medium', 'Validasi Form, Boundary & Edge Cases', 'Validasi input negatif/kosong, batas tanggal masa depan, filter DataTables, cetak Surat Jalan, dan log perubahan operasional (`PO_LOG_PERUBAHAN`).', 'Target >= 95% Pass'],
        ['SLA-P3', 'Priority: P3 - Low', 'Kosmetik UI/UX & Kemudahan Pakai', 'Tata letak badge warna indikator stage, kerapian format ribuan/desimal di tabel, dan pesan empty-state.', 'Target >= 90% Pass'],
        ['SLA-S1', 'Severity: S1 - Blocker', 'Sistem Crash / Data Stok Korup / Security Breach', 'Aplikasi error 500 pada alur utama, stok bertambah/berkurang ganda, transaksi gagal tidak rollback, atau role tanpa izin dapat mengakses/mengubah data rahasia. SLA Perbaikan: <= 4 Jam.', 'Hotfix Segera'],
        ['SLA-S2', 'Severity: S2 - Critical', 'Rumus Salah / Fitur Utama Gagal Tanpa Workaround', 'Perhitungan Batas Minimum, Target Stock, MOQ, atau pembulatan hari LT (1 desimal half-up) meleset dari PRD Bab 6; stage PO bisa melompat; atau Request mengubah stok.', 'SLA <= 24 Jam'],
        ['SLA-S3', 'Severity: S3 - Major', 'Fitur Terganggu Namun Ada Workaround', 'Filter laporan tidak merespons pada kombinasi tertentu, atau validasi form menampilkan pesan kurang spesifik.', 'SLA <= 2 Hari Kerja'],
        ['SLA-S4', 'Severity: S4 - Minor', 'Masalah Visual / Typo / Kosmetik', 'Ketidaksesuaian label minor, alignment kolom, atau ikon yang tidak memengaruhi logika bisnis maupun data.', 'SLA Sebelum Rilis / Backlog'],

        sectionRow('E. OVERRIDE OPERASIONAL TERKUNCI PRD v2.4 (WAJIB DIVERIFIKASI KETAT OLEH QA)'),
        ['OVR-01', 'Override Operasional #1', 'Presisi Hari Lead Time 1 Desimal Half-Up', 'Seluruh kolom satuan HARI pada modul Lead Time menggunakan `NUMERIC(10,1)` (toleransi 1 angka di belakang koma) dengan pembulatan digit ke-2 mengikuti aturan half-up (<5 menjadi 0, >=5 menjadi +0,1). Contoh: 1.485 menit = 24,75 jam = 1,03125 hari -> dibulatkan menjadi 1,0 hari; 1.512 menit = 1,05 hari -> dibulatkan menjadi 1,1 hari. Perhitungan internal tetap dalam menit bulat (1 hari = 1.440 menit).', 'QA & Developer'],
        ['OVR-02', 'Override Operasional #2', 'Invarian QC Kerusakan Transit & Dashboard Bulanan Transfer', 'Saat penerimaan Transfer, penerima wajib mengisi `qty_baik`, `qty_rusak`, dan `keterangan_rusak` per item dengan invarian eksak `qty_dikirim = qty_baik + qty_rusak`. Hanya `qty_baik` yang menambah saldo `STOK` gudang tujuan. Nilai `qty_rusak` direkap permanen dan ditampilkan pada Dashboard & Tabel Matriks Kerusakan Bulanan per Gudang di halaman Index Transfer (`/transfers`).', 'QA & Developer'],
    ];

    // =========================================================================
    // SHEET 02: 02_DASHBOARD_SUMMARY (WITH LIVE EXCEL FORMULAS)
    // =========================================================================
    $sheet2Cols = [
        ['header' => 'No / Kode', 'width' => 14],
        ['header' => 'Nama Worksheet / Modul Uji', 'width' => 32],
        ['header' => 'Fokus Pengujian Utama', 'width' => 42],
        ['header' => 'Total TC', 'width' => 13],
        ['header' => 'P0 (Critical)', 'width' => 14],
        ['header' => 'P1 (High)', 'width' => 13],
        ['header' => 'Pass', 'width' => 12],
        ['header' => 'Fail', 'width' => 12],
        ['header' => 'Blocked', 'width' => 12],
        ['header' => 'In Progress', 'width' => 13],
        ['header' => 'Not Executed', 'width' => 15],
        ['header' => '% Executed', 'width' => 14],
        ['header' => '% Pass Rate', 'width' => 14],
    ];

    $tcSheetsMeta = [
        ['M-01', '05_TC_AUTH_RBAC_DASH', 'Autentikasi, Session Security, Role Switcher, RBAC, Location Scope & Dashboard 5 Role'],
        ['M-02', '06_TC_MASTER_BOM', 'Master Produk, Kategori, Varian, Klasifikasi ABC, UOM, Gudang, Multi-Supplier & Import BOM Excel'],
        ['M-03', '07_TC_ANALISA_STOK', 'Analisa Bahan Lokal (9 Stage), Bahan Impor (Varian/Non-Varian), Fulfillment ALL, Multi-Supplier & Buat PO'],
        ['M-04', '08_TC_PURCHASING_9STAGE', 'Workflow PO 9 Stage, Reject Loop, Payment Dibayarkan/Tempo, Revisi Komitmen, Unloading/Input & Privacy Gudang'],
        ['M-05', '09_TC_LEAD_TIME_ENGINE', 'Mesin Lead Time Waktu Aktual, Pembulatan Hari 1 Desimal Half-Up, Akumulasi 2-Level & Invalidasi LT'],
        ['M-06', '10_TC_REQUEST_TRANSFER', 'Pemisahan Request & Transfer, Relasi 1:1, 5 Jenis Transfer, SoD, Invarian QC Rusak & Dashboard Defect Bulanan'],
        ['M-07', '11_TC_PRODUKSI_STOK_LAP', 'Batch Multi-Produk, Auto-Explode BOM, Multi-Request ke Batch, Alert Stock 2 Kolom, Kartu Stok & 7 Laporan'],
        ['M-08', '12_TC_E2E_UAT_DEFECT_LOG', 'Skenario End-to-End (E2E) Lintas 5 Persona (Hulu ke Hilir) & UAT Acceptance Flows'],
    ];

    $sheet2Rows = [
        sectionRow('A. RINGKASAN EKSEKUSI TEST CASE PER MODUL (OTOMATIS DARI SHEET 05 S/D 12)'),
    ];

    // Note: Row 1 = Banner Title, Row 2 = Banner Subtitle, Row 3 = Table Header, Row 4 = Section A Header
    // So Module rows start at Row 5 through Row 12!
    $startModRow = 5;
    foreach ($tcSheetsMeta as $idx => [$code, $sheetName, $focus]) {
        $r = $startModRow + $idx;
        $sheet2Rows[] = [
            ['value' => $code, 'style' => XlsxWorkbookBuilder::STYLE_CODE_ID],
            ['value' => $sheetName, 'style' => XlsxWorkbookBuilder::STYLE_DATA_BOLD],
            $focus,
            ['formula' => "COUNTIF('{$sheetName}'!A4:A250,\"TC-*\")", 'style' => XlsxWorkbookBuilder::STYLE_METRIC_NUMBER],
            ['formula' => "COUNTIF('{$sheetName}'!G4:G250,\"P0*\")", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P0],
            ['formula' => "COUNTIF('{$sheetName}'!G4:G250,\"P1*\")", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P1],
            ['formula' => "COUNTIF('{$sheetName}'!P4:P250,\"Pass\")", 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS],
            ['formula' => "COUNTIF('{$sheetName}'!P4:P250,\"Fail\")", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P0],
            ['formula' => "COUNTIF('{$sheetName}'!P4:P250,\"Blocked\")", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P1],
            ['formula' => "COUNTIF('{$sheetName}'!P4:P250,\"In Progress\")", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P2],
            ['formula' => "COUNTIF('{$sheetName}'!P4:P250,\"Not Executed\")", 'style' => XlsxWorkbookBuilder::STYLE_METRIC_NUMBER],
            ['formula' => "IF(D{$r}>0,ROUND(((D{$r}-K{$r})/D{$r})*100,1),0)", 'style' => XlsxWorkbookBuilder::STYLE_METRIC_NUMBER],
            ['formula' => "IF((D{$r}-K{$r})>0,ROUND((G{$r}/(D{$r}-K{$r}))*100,1),0)", 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS],
        ];
    }

    $endModRow = $startModRow + count($tcSheetsMeta) - 1; // Row 12
    $totalRow = $endModRow + 1; // Row 13
    $sheet2Rows[] = [
        ['value' => 'TOTAL', 'style' => XlsxWorkbookBuilder::STYLE_CODE_ID],
        ['value' => 'GRAND TOTAL SELURUH MODUL', 'style' => XlsxWorkbookBuilder::STYLE_DATA_BOLD],
        ['value' => 'Akumulasi Seluruh Skenario Uji STLC (Sheet 05 s/d Sheet 12)', 'style' => XlsxWorkbookBuilder::STYLE_DATA_BOLD],
        ['formula' => "SUM(D{$startModRow}:D{$endModRow})", 'style' => XlsxWorkbookBuilder::STYLE_METRIC_NUMBER],
        ['formula' => "SUM(E{$startModRow}:E{$endModRow})", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P0],
        ['formula' => "SUM(F{$startModRow}:F{$endModRow})", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P1],
        ['formula' => "SUM(G{$startModRow}:G{$endModRow})", 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS],
        ['formula' => "SUM(H{$startModRow}:H{$endModRow})", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P0],
        ['formula' => "SUM(I{$startModRow}:I{$endModRow})", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P1],
        ['formula' => "SUM(J{$startModRow}:J{$endModRow})", 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P2],
        ['formula' => "SUM(K{$startModRow}:K{$endModRow})", 'style' => XlsxWorkbookBuilder::STYLE_METRIC_NUMBER],
        ['formula' => "IF(D{$totalRow}>0,ROUND(((D{$totalRow}-K{$totalRow})/D{$totalRow})*100,1),0)", 'style' => XlsxWorkbookBuilder::STYLE_METRIC_NUMBER],
        ['formula' => "IF((D{$totalRow}-K{$totalRow})>0,ROUND((G{$totalRow}/(D{$totalRow}-K{$totalRow}))*100,1),0)", 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS],
    ];

    $sheet2Rows[] = sectionRow('B. PANDUAN PENGGUNAAN DASHBOARD EKSEKUSI QA');
    $sheet2Rows[] = [
        'GUIDE-01',
        'Cara Update Status Uji',
        'Buka salah satu Sheet Test Case (05 s/d 12), lalu ubah nilai pada Kolom P (Status) menggunakan dropdown: Pass, Fail, Blocked, In Progress, atau Not Executed. Angka pada Dashboard ini akan terhitung ulang secara otomatis.',
        '-', '-', '-', '-', '-', '-', '-', '-', '-', '-',
    ];
    $sheet2Rows[] = [
        'GUIDE-02',
        'Kriteria Lulus Rilis (Go-Live)',
        '1. Kolom Not Executed (K13) = 0 (100% Executed).\n2. Kolom Fail (H13) untuk seluruh test case P0 = 0.\n3. % Pass Rate (M13) >= 98%.',
        '-', '-', '-', '-', '-', '-', '-', '-', '-', '-',
    ];

    // =========================================================================
    // SHEET 03: 03_TEST_DATA_PERSONA
    // =========================================================================
    $sheet3Cols = [
        ['header' => 'Kategori Data Uji', 'width' => 24],
        ['header' => 'Kode / Identifier', 'width' => 24],
        ['header' => 'Nama / Label di Sistem', 'width' => 30],
        ['header' => 'Atribut / Parameter Konfigurasi (Seeder & PRD v2.4)', 'width' => 48],
        ['header' => 'Kewenangan / Penggunaan dalam Skenario Uji', 'width' => 54],
    ];

    $sheet3Rows = [
        sectionRow('A. AKUN PENGGUNA & 5 PERSONA ROLE (UserSeeder.php — Password Default: "password")'),
        ['Persona Role', 'manager@heavenscent.id', 'Andyka (Manager)', 'Role: `manager` | Divisi: `manajemen` | Warehouse Scope: `global` | Password: `password`', 'Akses penuh seluruh menu; Approval PO (Stage 2), Approval Revisi PO (`PO_REVISI`), Pembatalan PO, Invalidasi Lead Time, Approval Request Bahan & Produk Jadi, Kelola User & Role.'],
        ['Persona Role', 'purchasing@heavenscent.id', 'Rina (Purchasing)', 'Role: `purchasing` | Divisi: `purchasing` | Warehouse Scope: `global` | Password: `password`', 'Analisa Stok Lokal/Impor, Buat PO (dari Analisa / Manual), selesaikan Stage 1 (Perencanaan), Stage 3 (Supplier Confirm), Stage 4 (Payment: Dibayarkan / Lanjut Tempo), Stage 5 (PO), Stage 6 (Pengemasan), isi data Stage 7 (Pengiriman), Edit Langsung & Ajukan Revisi PO, Catat Pembayaran.'],
        ['Persona Role', 'gudang@heavenscent.id', 'Budi (Gudang)', 'Role: `gudang` | Divisi: `gudang` | Warehouse Scope: `restricted` (GD-PUSAT) | Password: `password`', 'Akses terbatas Purchasing (hanya PO yang sudah melewati Stage 5, tanpa melihat harga/nominal PO), Konfirmasi Tiba (selesaikan Stage 7), Unloading (Stage 8), Input/Posting Stok (Stage 9), Buat & Kirim Transfer Pemenuhan Request Bahan, Terima Retur Bahan.'],
        ['Persona Role', 'operasional@heavenscent.id', 'Dedi (Operasional)', 'Role: `operasional` | Divisi: `produksi` | Warehouse Scope: `restricted` (GD-OPS) | Password: `password`', 'Buat & kelola Batch Produksi Multi-Produk (Rencana -> Release & Issue -> Selesai / Batal), Stock Opname Batch, Buat Request Bahan ke Gudang Pusat, Terima Transfer Bahan, Buat & Kirim Transfer Produk Jadi ke Fulfillment Pusat, Terima Retur Produk Jadi.'],
        ['Persona Role', 'fulfillment@heavenscent.id', 'Sari (Fulfillment)', 'Role: `fulfillment` | Divisi: `fulfillment` | Warehouse Scope: `restricted` (FF-PUSAT, FF-SBY, FF-SOLO) | Password: `password`', 'Analisa Stok Produk Jadi (Fulfillment per cabang & ALL), Buat Request Produk Jadi ke Operasional, Terima Transfer Kirim Produk Jadi dari Operasional, Buat/Kirim/Terima Transfer Antar Gudang Fulfillment & Retur Produk Jadi.'],
        ['Persona Role', 'admin@heavenscent.id', 'Administrator', 'Role: `manager` | Divisi: `manajemen` | Warehouse Scope: `global` | Password: `password`', 'Akun super-admin cadangan untuk pengujian manajemen User, Role & Permission, serta konfigurasi sistem.'],

        sectionRow('B. MASTER GUDANG & HIERARKI INDUK-CABANG (MasterDataSeeder.php)'),
        ['Master Gudang', 'GD-PUSAT', 'Gudang Bahan Baku Pusat', 'Tipe: `bahan_baku` | `is_pusat = true` | Parent: `NULL` | Status: `aktif`', 'Gudang tujuan default penerimaan PO bahan/kemas (`NC-17`) dan gudang asal Transfer `pemenuhan_request_bahan`.'],
        ['Master Gudang', 'GD-OPS', 'Gudang Operasional', 'Tipe: `operasional` | `is_pusat = false` | Parent: `NULL` | Status: `aktif`', 'Gudang tujuan Request Bahan, tempat penarikan bahan saat Batch `Release & Issue`, dan asal `retur_bahan`.'],
        ['Master Gudang', 'FF-PUSAT', 'Fulfillment Pusat', 'Tipe: `fulfillment` (`fulfillment_pusat`) | `is_pusat = true` | Parent: `NULL` | Status: `aktif`', 'Gudang penerima langsung `kirim_produk_jadi` dari Operasional; satu-satunya gudang yang nilai `akan_datang`-nya dihitung pada agregasi Fulfillment `ALL`.'],
        ['Master Gudang', 'FF-SBY', 'Fulfillment SBY', 'Tipe: `fulfillment` (`fulfillment_cabang`) | `is_pusat = false` | Parent: `FF-PUSAT` | Status: `aktif`', 'Gudang cabang Surabaya untuk pengujian distribusi `antar_fulfillment` dan Analisa Stok Fulfillment cabang.'],
        ['Master Gudang', 'FF-SOLO', 'Fulfillment Solo', 'Tipe: `fulfillment` (`fulfillment_cabang`) | `is_pusat = false` | Parent: `FF-PUSAT` | Status: `aktif`', 'Gudang cabang Solo untuk pengujian distribusi `antar_fulfillment` dan rekap kerusakan transit bulanan lintas gudang.'],

        sectionRow('C. MASTER SUPPLIER, PRODUK (SKU), MOQ, SALDO AWAL & RESEP BOM'),
        ['Master Supplier', 'SUP-LOKAL-01', 'PT Alkohol Nusantara', 'Kategori: `lokal` | Termin Default: `termin` | Kontak: `081234500001` | Alamat: `Sidoarjo`', 'Supplier default untuk bahan lokal `ALK-01` dan `OIL-GOH`. Digunakan pada pengujian Analisa Lokal & PO Skema Termin/Pelunasan.'],
        ['Master Supplier', 'SUP-IMPOR-01', 'Glass Bottle Import Co.', 'Kategori: `impor` | Termin Default: `tempo` | Kontak: `081234500002`', 'Supplier default untuk bahan kemas impor `BTL-P50` dan `CAP-01`. Digunakan pada pengujian Analisa Impor & PO Skema Tempo (`Lanjut Tempo`).'],
        ['Master Produk', 'ALK-01', 'Alkohol 96%', 'Tipe: `bahan` | Satuan: `ml` | MOQ: `5.000` | Profil: `lokal` | Saldo Awal GD-PUSAT: `500.000 ml`', 'Bahan baku utama untuk pengujian Analisa Lokal (Bab 6.2), PO 9 Stage, Request Bahan, dan BOM `GOH-P50` (40 ml/pcs).'],
        ['Master Produk', 'OIL-GOH', 'Oil Concentrate GOH', 'Tipe: `bahan` | Satuan: `ml` | MOQ: `100` | Profil: `lokal` | Saldo Awal GD-PUSAT: `20.000 ml`', 'Bibit parfum untuk pengujian multi-item PO, penggabungan BOM multi-produk, dan Opname Batch (8 ml/pcs).'],
        ['Master Produk', 'BTL-P50', 'Botol 50ml', 'Tipe: `kemas` | Satuan: `pcs` | MOQ: `1.000` | Profil: `impor` | Saldo Awal GD-PUSAT: `8.000 pcs`', 'Bahan kemas impor untuk pengujian Analisa Impor Ber-Varian (Varian Bening 60%) dan BOM `GOH-P50` (1 pcs/unit).'],
        ['Master Produk', 'CAP-01', 'Tutup Botol', 'Tipe: `kemas` | Satuan: `pcs` | MOQ: `1.000` | Profil: `impor` | Saldo Awal GD-PUSAT: `8.000 pcs`', 'Bahan kemas impor untuk pengujian Analisa Impor Tanpa Varian (`NC-V2-7`) dan BOM `GOH-P50` (1 pcs/unit).'],
        ['Master Produk', 'GOH-P50', 'Parfum GOH 50ml', 'Tipe: `produk_jadi` | Satuan: `pcs` | MOQ: `12` | BOM: `40 ml ALK-01 + 8 ml OIL-GOH + 1 BTL-P50 + 1 CAP-01`', 'Produk jadi utama untuk pengujian Batch Produksi, Request Produk Jadi, Kirim Produk Jadi, dan Analisa Fulfillment.'],

        sectionRow('D. PARAMETER ANGKA ACUAN UJI KALKULASI RUMUS (PRD v2.4 BAB 6)'),
        ['Acuan Rumus Bab 6.2', 'ANL-LOKAL-ALK01', 'Analisa Bahan Lokal ALK-01', 'Terjual 4 Bln = `39.303,25` | Review Period = `15` hr | Stok Saat Ini = `8.000` | Akan Datang = `0` | Harga = `Rp 526` | Avg LT 9 Stage = `13,0` hr (atau `12,0` hr) | Max LT 9 Stage = `22,0` hr (atau `18,0` hr di contoh Bab 6.6.3) | Tambahan Buffer = `2` hr', 'Pada contoh Bab 6.6.3 (Avg=12/13, Max=18, Tambahan=2): ADU = `1.310,11`/hr, Total Buffer = `8` hr (saat Avg=12) atau `7` hr (saat Avg=13), Batas Minimum tetap `26.202,17`, Target Stock tetap `45.853,79`.'],
        ['Acuan Rumus Bab 6.3', 'ANL-IMPOR-BTLP50', 'Analisa Bahan Impor BTL-P50 (Varian Bening)', 'Out 4 Bln = `47.312` | Persentase Varian Bening = `0,6` (60%) | LT Avg = `81,8` (`81,75`) hr | LT Max = `114` hr | Klasifikasi ABC = `A` (+4 hr) | Review Period = `30` hr | Stok = `1.573` | Inbound = `10.044`', 'Hasil wajib sesuai Tabel Bab 6.6.4: Out Varian = `28.387,2`, ADU Base = `232,68`, ADU ETA = `866,7`, Buffer Days = `36,25`, Minimum Stock = `70.856,0`, Target Stock = `128.277,6`, Selisih = `-187.516,6`, Status = `PO`.'],
        ['Acuan Rumus Bab 6.4', 'ANL-FF-GOHP50', 'Analisa Produk Jadi GOH-P50 (Lintas Cabang)', 'FF-PUSAT: Terjual=`60.000` (ADU=2.000), Stok=`500`, Akan Datang=`20.000` | FF-SBY: Terjual=`90.000` (ADU=3.000), Stok=`4.000` | FF-SOLO: Terjual=`45.000` (ADU=1.500), Stok=`2.000` | LT Distribusi=`2` hr, Buffer=`1` hr, Review=`15` hr', 'Per Gudang: Min Stock = ADU x 3, Target = ADU x 18. Level ALL: Total ADU=`6.500`, Total Batas Min=`19.500`, Total Target Stock=`117.000`, Stok Saat Ini Total=`6.500`, Akan Datang ALL=`20.000` (hanya dari FF-PUSAT), Stok All=`26.500` <= `117.000` -> Status `ORDER`, Selisih=`-90.500`.'],
        ['Acuan Rumus Bab 6.5.2', 'LT-9STAGE-SAMPLE', 'Linimasa 9 Stage PO (3 Nov 13.00 s/d 12 Nov 14.00)', 'S1: 1.485 mnt (24,75 jam | 1,03->1,0 hr) | S2: 1.170 mnt (19,50 jam | 0,81->0,8 hr) | S3: 1.845 mnt (30,75 jam | 1,28->1,3 hr) | S4: 1.110 mnt (18,50 jam | 0,77->0,8 hr) | S5: 210 mnt (3,50 jam | 0,1458->0,1 hr) | S6: 4.140 mnt (69,00 jam | 2,88->2,9 hr) | S7: 2.730 mnt (45,50 jam | 1,90->1,9 hr) | S8: 165 mnt (2,75 jam | 0,11->0,1 hr) | S9: 165 mnt (2,75 jam | 0,11->0,1 hr)', 'Total LT = `13.020` menit = `217,00` jam = `9,0416` hari mentah -> dibulatkan 1 desimal half-up menjadi **`9,0` hari**. Subtotal Internal (S1,2,4,5,8,9) = `4.305` mnt; Supplier (S3,6) = `5.985` mnt; Logistik (S7) = `2.730` mnt.'],
    ];

    // =========================================================================
    // SHEET 04: 04_RTM_TRACEABILITY (REQUIREMENTS TRACEABILITY MATRIX)
    // =========================================================================
    $sheet4Cols = [
        ['header' => 'ID Requirement / Rule', 'width' => 20],
        ['header' => 'Sumber di PRD v2.4 / Sistem', 'width' => 24],
        ['header' => 'Domain / Modul', 'width' => 22],
        ['header' => 'Deskripsi Persyaratan Bisnis / Aturan Sistem', 'width' => 58],
        ['header' => 'Worksheet Tujuan', 'width' => 24],
        ['header' => 'ID Test Case Terkait (Traceability)', 'width' => 32],
        ['header' => 'Status Cakupan', 'width' => 16],
    ];

    $sheet4Rows = [
        sectionRow('A. FUNCTIONAL REQUIREMENTS — DASHBOARD & AUTENTIKASI / RBAC (BAB 2, BAB 4, BAB 5.1)'),
        ['FR-DASH-01', 'PRD Bab 5.1', 'Dashboard', 'Menampilkan kartu ringkasan KPI yang relevan dengan role yang sedang login.', '05_TC_AUTH_RBAC_DASH', 'TC-DASH-015 s/d TC-DASH-019', '100% Covered'],
        ['FR-DASH-02', 'PRD Bab 5.1', 'Dashboard', 'Menampilkan daftar transaksi (PO/Request/Transfer) yang menunggu tindakan dari role login beserta tautan langsung.', '05_TC_AUTH_RBAC_DASH', 'TC-DASH-020, TC-DASH-021', '100% Covered'],
        ['FR-DASH-03', 'PRD Bab 5.1', 'Dashboard', 'Daftar tindakan mencakup PO pada stage PIC, stage berindikator Terlambat, dan revisi data PO yang menunggu approval Manager.', '05_TC_AUTH_RBAC_DASH', 'TC-DASH-021, TC-DASH-022', '100% Covered'],
        ['SYS-AUTH-RBAC', 'PRD Bab 2 & Bab 4 + Codebase', 'Auth, RBAC & Scope', 'Login/Logout, Session Ping Heartbeat, Quick Role Switcher 5 Persona, Pembatasan Menu & Gudang (LocationScopeService), Custom Role & Divisi.', '05_TC_AUTH_RBAC_DASH', 'TC-AUTH-001 s/d TC-RBAC-014', '100% Covered'],

        sectionRow('B. FUNCTIONAL REQUIREMENTS & BUSINESS RULES — PURCHASING 9 STAGE & REVISI (BAB 5.2)'),
        ['FR-PUR-01', 'PRD Bab 5.2.1', 'Purchasing', 'Satu PO dapat memuat banyak item (header-detail): SKU, qty, dan harga dicatat per item.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-001, TC-PUR-002', '100% Covered'],
        ['FR-PUR-02 / BR-01', 'PRD Bab 5.2.1 & 5.2.4', 'Purchasing', 'PO melalui 9 stage berurutan (1 s/d 9) dan tidak boleh melompat; status dokumen Draft, Berjalan, Selesai, Dibatalkan.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-004, TC-PUR-005 (TC-01, TC-02)', '100% Covered'],
        ['FR-PUR-03', 'PRD Bab 5.2.1', 'Purchasing', 'Barang Datang dicatat pada stage Unloading dan diposting pada stage Input; qty diterima dapat berbeda dari qty PO.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-023 s/d TC-PUR-027', '100% Covered'],
        ['FR-PUR-04 / BR-09', 'PRD Bab 5.2.1 & 5.2.4', 'Purchasing', 'Payment dicatat per PO (Tempo/Termin/Pelunasan); sistem menghitung status Lunas/Belum Lunas dan sisa tagihan secara terpisah dari stage.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-013 s/d TC-PUR-017 (TC-26..TC-29)', '100% Covered'],
        ['FR-PUR-05', 'PRD Bab 5.2.1', 'Purchasing', 'Harga per satuan (HPP) dihitung otomatis dari harga total item dibagi qty.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-001, TC-PUR-040', '100% Covered'],
        ['FR-PUR-06', 'PRD Bab 5.2.2 Stage 1', 'Purchasing', 'Stage 1 Perencanaan: validasi supplier wajib, setiap item wajib terdaftar aktif pada PRODUK_SUPPLIER, qty & harga > 0, gudang tujuan wajib.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-001 s/d TC-PUR-004 (TC-32)', '100% Covered'],
        ['FR-PUR-07 / BR-06', 'PRD Bab 5.2.2 Stage 2', 'Purchasing', 'Stage 2 Approval oleh Manager: Setujui membuka Stage 3; Tolak (wajib alasan) menandai Dikembalikan dan membuka putaran baru Stage 1.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-006 s/d TC-PUR-009 (TC-12)', '100% Covered'],
        ['FR-PUR-08 / BR-05', 'PRD Bab 5.2.2 Stage 3', 'Purchasing', 'Stage 3 Supplier Confirm: catat waktu konfirmasi, ref supplier, qty & harga konfirmasi, ETA supplier wajib; selisih wajib lewat Ajukan Perubahan.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-010 s/d TC-PUR-012', '100% Covered'],
        ['FR-PUR-09', 'PRD Bab 5.2.2 Stage 4', 'Purchasing', 'Stage 4 Payment: tombol Dibayarkan aktif bila ada PAYMENT > 0 (walau baru DP); skema Tempo memakai tombol Lanjut Tempo tanpa pembayaran.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-013 s/d TC-PUR-017 (TC-26..TC-29)', '100% Covered'],
        ['FR-PUR-10', 'PRD Bab 5.2.2 Stage 5', 'Purchasing', 'Stage 5 PO: menerbitkan PO resmi ke supplier, membuka visibilitas PO untuk Gudang (dengan harga disembunyikan dari Gudang).', '08_TC_PURCHASING_9STAGE', 'TC-PUR-018, TC-PUR-019', '100% Covered'],
        ['FR-PUR-11 / BR-11', 'PRD Bab 5.2.2 Stage 6', 'Purchasing', 'Stage 6 Pengemasan: mencatat waktu barang siap/diserahkan ke ekspedisi, koli, berat, catatan; terpisah dari Pengiriman.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-020, TC-PUR-021 (TC-07, TC-08)', '100% Covered'],
        ['FR-PUR-12', 'PRD Bab 5.2.2 Stage 7', 'Purchasing', 'Stage 7 Pengiriman: Purchasing isi ekspedisi (wajib), moda, resi, kontainer, ETA tiba; Gudang menyelesaikan dengan Konfirmasi Tiba.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-021, TC-PUR-022 (TC-07, TC-08)', '100% Covered'],
        ['FR-PUR-13 / BR-08', 'PRD Bab 5.2.2 Stage 8', 'Purchasing', 'Stage 8 Unloading oleh Gudang: simpan BARANG_DATANG & BARANG_DATANG_ITEM belum diposting; STOK dan KARTU_STOK belum berubah.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-023, TC-PUR-024 (TC-09, TC-10)', '100% Covered'],
        ['FR-PUR-14', 'PRD Bab 5.2.2 Stage 9', 'Purchasing', 'Stage 9 Input oleh Gudang: posting penerimaan dalam 1 transaksi DB atomik (tambah STOK, tulis KARTU_STOK in, tutup PO Selesai, tulis PO_LEAD_TIME & LT summary).', '08_TC_PURCHASING_9STAGE', 'TC-PUR-025 s/d TC-PUR-027 (TC-01, TC-11)', '100% Covered'],
        ['FR-PUR-15 & 16', 'PRD Bab 5.2.1', 'Purchasing & LT', 'Setiap stage menyimpan waktu mulai, selesai, durasi menit, PIC, pencatat; ditampilkan dalam jam (2 desimal) dan hari (1 desimal half-up).', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-001 s/d TC-LT-009 (TC-03..TC-06)', '100% Covered'],
        ['FR-PUR-17 / BR-07', 'PRD Bab 5.2.1 & 5.2.4', 'Purchasing', 'Manager dapat membatalkan PO sebelum stage Unloading selesai dengan alasan wajib; PO Dibatalkan tidak masuk LT.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-037, TC-PUR-038 (TC-15)', '100% Covered'],
        ['FR-PUR-18 / NC-08', 'PRD Bab 5.2.1 & Bab 10', 'Purchasing', 'Penanganan penerimaan barang bertahap (partial delivery) dan penutupan PO sebagian.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-039', '100% Covered'],
        ['FR-PUR-19..21', 'PRD Bab 5.2.1 & 5.2.5', 'Purchasing', 'Perubahan pasca-approval: edit langsung operasional tercatat di PO_LOG_PERUBAHAN; perubahan komitmen lewat Ajukan Perubahan (PO_REVISI) dengan approval Manager.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-028 s/d TC-PUR-036 (TC-33..TC-37)', '100% Covered'],

        sectionRow('C. FUNCTIONAL REQUIREMENTS & OVERRIDE #1 — LEAD TIME SUPPLIER & SUPPLIER-PRODUK (BAB 5.3 & BAB 6.5)'),
        ['FR-LT-01 & OVR-01', 'PRD Bab 5.3 & Bab 6.5.1', 'Lead Time', 'Perhitungan durasi menit bulat (1 hari = 1.440 mnt), jam 2 desimal, dan Override Operasional #1: satuan HARI bertoleransi 1 angka di belakang koma (NUMERIC(10,1)) dengan pembulatan half-up.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-004 s/d TC-LT-009 (TC-03)', '100% Covered'],
        ['FR-LT-02 & BR-10', 'PRD Bab 5.3 & Bab 6.5.4', 'Lead Time', 'Akumulasi kumulatif Average & Max LT pada 2 level sekaligus: level supplier (SUPPLIER_LT_*) dan level supplier-produk (SUPPLIER_PRODUK_LT_*).', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-010 s/d TC-LT-014 (TC-13, TC-30)', '100% Covered'],
        ['FR-LT-03 & 06', 'PRD Bab 5.3 & Bab 6.5.6', 'Lead Time', 'Tampilan ringkasan LT per supplier & per supplier-produk beserta subtotal performa Internal (S1,2,4,5,8,9), Supplier (S3,6), dan Logistik (S7).', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-015 s/d TC-LT-018', '100% Covered'],
        ['FR-LT-04 & 05', 'PRD Bab 5.3 & Bab 6.5.5', 'Lead Time', 'Manager dapat menandai transaksi LT tidak valid dengan alasan wajib; sistem menghitung ulang Average & Max serta menulis baris append-only di SUPPLIER_LT_HISTORY.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-019 s/d TC-LT-022 (TC-14)', '100% Covered'],

        sectionRow('D. FUNCTIONAL REQUIREMENTS — PRODUKSI BATCH MULTI-PRODUK (BAB 5.4)'),
        ['FR-PRD-01 & 04', 'PRD Bab 5.4', 'Produksi (Batch)', 'Satu batch dapat memuat banyak produk jadi (BATCH_PRODUKSI_ITEM); sistem menggabungkan kebutuhan bahan BOM seluruh produk ke ALOKASI_BAHAN (Aktif) tanpa mengurangi stok fisik.', '11_TC_PRODUKSI_STOK_LAP', 'TC-PRD-001 s/d TC-PRD-004 (TC-40)', '100% Covered'],
        ['FR-PRD-02', 'PRD Bab 5.4', 'Produksi (Batch)', 'Siklus Batch: Rencana -> Release & Issue (potong stok bahan Operasional riil & lepas alokasi) -> Selesai (catat qty_baik, qty_rusak, yield % per produk) atau Dibatalkan.', '11_TC_PRODUKSI_STOK_LAP', 'TC-PRD-007 s/d TC-PRD-013', '100% Covered'],
        ['FR-PRD-03', 'PRD Bab 5.4', 'Produksi (Batch)', 'Batch berstatus Selesai dapat langsung diajukan sebagai Transfer Kirim Produk Jadi multi-produk ke Fulfillment Pusat.', '11_TC_PRODUKSI_STOK_LAP', 'TC-PRD-015, TC-PRD-016', '100% Covered'],
        ['FR-PRD-05', 'PRD Bab 5.4', 'Produksi (Batch)', 'Satu batch dapat terkait dua Request Bahan atau lebih; detail batch menampilkan kebutuhan BOM, total diminta, dan total diterima per bahan.', '11_TC_PRODUKSI_STOK_LAP', 'TC-PRD-005, TC-PRD-006 (TC-41)', '100% Covered'],

        sectionRow('E. FUNCTIONAL REQUIREMENTS & OVERRIDE #2 — REQUEST & TRANSFER (BAB 5.5 & BAB 5.6)'),
        ['FR-REQ-01, 02, 06', 'PRD Bab 5.5.1', 'Request', 'Header-detail multi-item untuk 2 jenis Request (Request Bahan & Request Produk Jadi) dengan alur Draft -> Diajukan -> Disetujui/Ditolak -> Diproses -> Selesai/Dibatalkan.', '10_TC_REQUEST_TRANSFER', 'TC-RT-001 s/d TC-RT-006 (TC-19, TC-38)', '100% Covered'],
        ['FR-REQ-03', 'PRD Bab 5.5.1', 'Request', 'Invarian mutlak: Request tidak pernah mengubah STOK maupun KARTU_STOK pada status apa pun.', '10_TC_REQUEST_TRANSFER', 'TC-RT-003 (TC-18)', '100% Covered'],
        ['FR-REQ-04, 05, 07, 08', 'PRD Bab 5.5.1', 'Request', 'Hanya Request Disetujui yang dapat dibuatkan 1 Transfer; otomatis Selesai saat Transfer Selesai; edit Request Disetujui sebelum ada Transfer mengembalikan status ke Diajukan.', '10_TC_REQUEST_TRANSFER', 'TC-RT-007 s/d TC-RT-012 (TC-20, TC-24, TC-39)', '100% Covered'],
        ['FR-TRF-01..07', 'PRD Bab 5.6.1', 'Transfer', '5 Jenis Transfer dengan pipeline Draft -> Dikirim (stok asal berkurang) -> Selesai (stok tujuan bertambah) / Dibatalkan; validasi role/SoD & relasi 1:1 Request.', '10_TC_REQUEST_TRANSFER', 'TC-RT-013 s/d TC-RT-023 (TC-21, TC-23, TC-25)', '100% Covered'],
        ['FR-TRF-08 & OVR-02', 'PRD Bab 5.6.1', 'Transfer (QC Transit)', 'Override Operasional #2: Penerimaan Transfer wajib menyimpan qty_baik, qty_rusak, dan keterangan_rusak per item dengan invarian eksak qty_dikirim = qty_baik + qty_rusak; hanya qty_baik menambah stok tujuan.', '10_TC_REQUEST_TRANSFER', 'TC-RT-024 s/d TC-RT-027 (TC-22)', '100% Covered'],
        ['FR-TRF-09 & OVR-02', 'PRD Bab 5.6.1', 'Transfer (Defect Analytics)', 'Override Operasional #2: Halaman Index Transfer wajib menampilkan Dashboard KPI & Tabel Matriks Rekap Bulanan Barang Rusak (qty_rusak) per Gudang dengan filter bulan/tahun & gudang.', '10_TC_REQUEST_TRANSFER', 'TC-RT-028 s/d TC-RT-031 (TC-42)', '100% Covered'],

        sectionRow('F. FUNCTIONAL REQUIREMENTS — ANALISA STOK, STOK & MUTASI, MASTER DATA & LAPORAN (BAB 5.7, 5.8, BAB 6)'),
        ['FR-ANL-01..09', 'PRD Bab 5.7 & Bab 6', 'Analisa Stok', '3 Profil Analisa (Lokal, Impor Varian/Non-Varian, Fulfillment), pemilihan pasangan barang-supplier, perbandingan multi-supplier, fallback LT manual vs historis, Simpan Snapshot, dan Buat PO otomatis.', '07_TC_ANALISA_STOK', 'TC-ANL-001 s/d TC-ANL-030 (TC-16, TC-17, TC-31)', '100% Covered'],
        ['FR-STK-01..03', 'PRD Bab 5.8', 'Stok & Mutasi', 'Alert Stock 2 Kolom (Kolom Stok Fisik+Inbound vs Kolom Rencana), Mutasi Manual, Kartu Stok gabungan append-only, dan Stock Opname.', '11_TC_PRODUKSI_STOK_LAP', 'TC-STK-017 s/d TC-STK-024', '100% Covered'],
        ['FR-MST-01', 'PRD Bab 5.8 + Codebase', 'Master Data & BOM', 'CRUD Produk, Kategori, Varian, Klasifikasi ABC, UOM, Divisi, Gudang Induk-Cabang, Supplier & PRODUK_SUPPLIER, BOM CRUD & Bulk Excel Grid Import, Master Audit.', '06_TC_MASTER_BOM', 'TC-MST-001 s/d TC-MST-026', '100% Covered'],
        ['FR-LAP-01', 'PRD Bab 5.8 + Codebase', 'Laporan & Audit', '7 Sub-Modul Laporan (Produksi, Pemakaian Bahan, Defect Produksi & Transit Bulanan, Low Stock, Purchasing/Hutang, Lead Time Supplier, Pergerakan Stok Fulfillment) & Operational Audit.', '11_TC_PRODUKSI_STOK_LAP', 'TC-LAP-025 s/d TC-LAP-030', '100% Covered'],

        sectionRow('G. KETERLACAKAN 100% LAMPIRAN B PRD v2.4 (ACCEPTANCE TEST CASES TC-01 S/D TC-42)'),
        ['Lampiran B TC-01', 'PRD Lampiran B', 'Purchasing 9 Stage', 'Alur normal 9 stage: Selesaikan stage 1 sampai 9 -> PO Selesai, stok bertambah sekali, PO_LEAD_TIME terbentuk.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-004, TC-PUR-025', '100% Covered'],
        ['Lampiran B TC-02', 'PRD Lampiran B', 'Purchasing 9 Stage', 'Melompati stage: Selesaikan stage 3 saat stage 2 berjalan -> Sistem menolak.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-005', '100% Covered'],
        ['Lampiran B TC-03', 'PRD Lampiran B', 'Lead Time Engine', 'Hitung durasi & pembulatan hari 1 desimal: 3 Nov 13.00 s/d 4 Nov 13.45 = 1.485 mnt, 24,75 jam, 1,0 hari; 1.512 mnt (1,05 hr) -> 1,1 hari.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-004, TC-LT-005, TC-LT-006', '100% Covered'],
        ['Lampiran B TC-04', 'PRD Lampiran B', 'Lead Time Engine', 'Waktu mundur valid: Isi waktu selesai kemarin setelah waktu mulai -> Tersimpan; dicatat_pada berisi waktu entri.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-002', '100% Covered'],
        ['Lampiran B TC-05', 'PRD Lampiran B', 'Lead Time Engine', 'Waktu tidak valid: Isi waktu selesai di masa depan atau sebelum waktu mulai -> Sistem menolak.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-003', '100% Covered'],
        ['Lampiran B TC-06', 'PRD Lampiran B', 'Lead Time Engine', 'Stage tersambung: Selesaikan stage 6 -> Waktu mulai stage 7 sama dengan waktu selesai stage 6.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-001', '100% Covered'],
        ['Lampiran B TC-07', 'PRD Lampiran B', 'Purchasing 9 Stage', 'Pengemasan dan Pengiriman terpisah: Selesaikan stage 6 dan 7 -> Dua durasi tersimpan pada dua baris PO_STAGE.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-020, TC-PUR-022', '100% Covered'],
        ['Lampiran B TC-08', 'PRD Lampiran B', 'Purchasing 9 Stage', 'Konfirmasi tiba terlalu dini: Gudang konfirmasi tiba saat stage 6 berjalan -> Sistem menolak.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-021', '100% Covered'],
        ['Lampiran B TC-09', 'PRD Lampiran B', 'Purchasing 9 Stage', 'Hak akses stage: Purchasing mencoba menyelesaikan stage 8 -> Sistem menolak.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-023', '100% Covered'],
        ['Lampiran B TC-10', 'PRD Lampiran B', 'Purchasing 9 Stage', 'Unloading tidak mengubah stok: Selesaikan stage 8 -> STOK dan KARTU_STOK tetap.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-024', '100% Covered'],
        ['Lampiran B TC-11', 'PRD Lampiran B', 'Purchasing 9 Stage', 'Input gagal sebagian: Gagalkan penulisan PO_LEAD_TIME saat posting -> Seluruh transaksi batal, stok tetap.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-026', '100% Covered'],
        ['Lampiran B TC-12', 'PRD Lampiran B', 'Purchasing 9 Stage', 'Penolakan Approval: Manager menolak -> Putaran baru stage 1; waktu putaran lama tetap dihitung.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-007, TC-PUR-008', '100% Covered'],
        ['Lampiran B TC-13', 'PRD Lampiran B', 'Lead Time Engine', 'Average kumulatif: 5 transaksi 60 hari, tambah 18 hari -> Average 13,0 hari, jumlah 6, Max diperbarui.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-010, TC-LT-011', '100% Covered'],
        ['Lampiran B TC-14', 'PRD Lampiran B', 'Lead Time Engine', 'Invalidasi transaksi: Tandai transaksi terlama tidak valid -> Average dihitung ulang, Max dicari ulang, riwayat bertambah.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-019, TC-LT-020', '100% Covered'],
        ['Lampiran B TC-15', 'PRD Lampiran B', 'Purchasing 9 Stage', 'PO dibatalkan: Batalkan PO sebelum Unloading selesai -> Tidak masuk akumulasi LT.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-037, TC-PUR-038', '100% Covered'],
        ['Lampiran B TC-16', 'PRD Lampiran B', 'Analisa Stok', 'Sumber LT: Pasangan supplier-produk belum punya riwayat cukup -> Analisa memakai parameter manual dengan label manual.', '07_TC_ANALISA_STOK', 'TC-ANL-022', '100% Covered'],
        ['Lampiran B TC-17', 'PRD Lampiran B', 'Analisa Stok', 'Rumus tetap: Ubah Average dari 12 ke 13, Max tetap 18 (Lokal) -> Batas Minimum tetap 26.202,17.', '07_TC_ANALISA_STOK', 'TC-ANL-004', '100% Covered'],
        ['Lampiran B TC-18', 'PRD Lampiran B', 'Request', 'Request tidak mengubah stok: Ajukan dan setujui Request -> STOK dan KARTU_STOK tetap.', '10_TC_REQUEST_TRANSFER', 'TC-RT-003', '100% Covered'],
        ['Lampiran B TC-19', 'PRD Lampiran B', 'Request', 'Request ditolak: Tolak tanpa alasan, lalu dengan alasan -> Pertama ditolak sistem; kedua status Ditolak.', '10_TC_REQUEST_TRANSFER', 'TC-RT-004, TC-RT-005', '100% Covered'],
        ['Lampiran B TC-20', 'PRD Lampiran B', 'Request & Transfer', 'Transfer dari Request: Buat Transfer dari Request Diajukan -> Sistem menolak; hanya Disetujui yang boleh.', '10_TC_REQUEST_TRANSFER', 'TC-RT-009', '100% Covered'],
        ['Lampiran B TC-21', 'PRD Lampiran B', 'Transfer', 'Kirim Transfer: Kirim 2.000 dari stok 8.000 -> Stok asal 6.000, KARTU_STOK out, referensi transfer.', '10_TC_REQUEST_TRANSFER', 'TC-RT-013', '100% Covered'],
        ['Lampiran B TC-22', 'PRD Lampiran B', 'Transfer (QC Rusak)', 'Selisih & QC Rusak penerimaan: Terima qty_baik = 1.950 dan qty_rusak = 50 dari 2.000 dikirim -> Stok tujuan bertambah 1.950, qty_rusak = 50 + keterangan_rusak tersimpan permanen.', '10_TC_REQUEST_TRANSFER', 'TC-RT-024', '100% Covered'],
        ['Lampiran B TC-23', 'PRD Lampiran B', 'Transfer', 'Stok tidak cukup: Kirim melebihi stok asal -> Sistem menolak.', '10_TC_REQUEST_TRANSFER', 'TC-RT-014', '100% Covered'],
        ['Lampiran B TC-24', 'PRD Lampiran B', 'Request & Transfer', 'Request selesai: Transfer pemenuhan Selesai -> Request otomatis Selesai.', '10_TC_REQUEST_TRANSFER', 'TC-RT-016', '100% Covered'],
        ['Lampiran B TC-25', 'PRD Lampiran B', 'Transfer', 'Transfer langsung: Buat Retur Bahan tanpa Request -> Tersimpan tanpa request_id.', '10_TC_REQUEST_TRANSFER', 'TC-RT-018', '100% Covered'],
        ['Lampiran B TC-26', 'PRD Lampiran B', 'Purchasing Stage 4', 'Payment cicilan: Catat DP 30%, tekan Dibayarkan -> Stage 4 selesai, status Belum Lunas, PO lanjut ke stage 5.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-014', '100% Covered'],
        ['Lampiran B TC-27', 'PRD Lampiran B', 'Purchasing Stage 4', 'Tombol Dibayarkan: Belum ada pembayaran -> Tombol tidak aktif / ditolak sistem.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-013', '100% Covered'],
        ['Lampiran B TC-28', 'PRD Lampiran B', 'Purchasing Stage 4', 'Lanjut Tempo: PO skema Tempo, tekan Lanjut Tempo -> Stage 5 terbuka tanpa PAYMENT.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-015', '100% Covered'],
        ['Lampiran B TC-29', 'PRD Lampiran B', 'Purchasing Payment', 'Cicilan berikutnya: Catat pembayaran setelah stage 4 selesai -> Durasi stage 4 tetap, sisa tagihan berkurang.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-016', '100% Covered'],
        ['Lampiran B TC-30', 'PRD Lampiran B', 'Lead Time 2-Level', 'LT dua level: PO Selesai berisi 3 produk -> Level supplier +1 transaksi; 3 pasangan supplier-produk masing-masing +1.', '09_TC_LEAD_TIME_ENGINE', 'TC-LT-012', '100% Covered'],
        ['Lampiran B TC-31', 'PRD Lampiran B', 'Analisa Stok', 'Pilih supplier: Pilih supplier A lalu B untuk produk sama -> Hasil berganti mengikuti LT pasangan; tidak ada dua hasil aktif.', '07_TC_ANALISA_STOK', 'TC-ANL-020, TC-ANL-021', '100% Covered'],
        ['Lampiran B TC-32', 'PRD Lampiran B', 'Purchasing Stage 1', 'Item bukan milik supplier: Buat PO dengan produk yang tidak terdaftar pada supplier -> Sistem menolak.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-003', '100% Covered'],
        ['Lampiran B TC-33', 'PRD Lampiran B', 'Purchasing Direct Edit', 'Edit langsung: Ubah no. resi di stage 7 -> Tersimpan tanpa approval; log tertulis di PO_LOG_PERUBAHAN.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-028', '100% Covered'],
        ['Lampiran B TC-34', 'PRD Lampiran B', 'Purchasing Revisi', 'Revisi qty: Ubah qty di stage 4 -> Data aktif tetap; stage tidak dapat selesai sampai diputuskan.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-030, TC-PUR-031', '100% Covered'],
        ['Lampiran B TC-35', 'PRD Lampiran B', 'Purchasing Revisi', 'Revisi disetujui: Manager menyetujui -> Data aktif berubah, revisi_ke +1, waktu stage tidak berubah.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-032', '100% Covered'],
        ['Lampiran B TC-36', 'PRD Lampiran B', 'Purchasing Revisi', 'Revisi ditolak: Manager menolak tanpa alasan, lalu dengan alasan -> Pertama ditolak sistem; kedua data tetap.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-033, TC-PUR-034', '100% Covered'],
        ['Lampiran B TC-37', 'PRD Lampiran B', 'Purchasing Revisi', 'Data terkunci: Ajukan perubahan qty di stage 8 -> Sistem menolak.', '08_TC_PURCHASING_9STAGE', 'TC-PUR-036', '100% Covered'],
        ['Lampiran B TC-38', 'PRD Lampiran B', 'Request & Transfer', 'Request Produk Jadi: Fulfillment membuat Request, Manager menyetujui, Operasional membuat Transfer -> Kirim Produk Jadi merujuk request_id.', '10_TC_REQUEST_TRANSFER', 'TC-RT-006, TC-RT-019', '100% Covered'],
        ['Lampiran B TC-39', 'PRD Lampiran B', 'Request & Transfer', 'Request 1:1: Buat Transfer kedua untuk Request yang sama -> Sistem menolak.', '10_TC_REQUEST_TRANSFER', 'TC-RT-010', '100% Covered'],
        ['Lampiran B TC-40', 'PRD Lampiran B', 'Produksi Multi-Produk', 'Batch multi-produk: Buat batch 4 produk dengan BOM yang berbagi bahan -> Kebutuhan digabung per bahan; qty_baik dan qty_rusak dicatat per produk.', '11_TC_PRODUKSI_STOK_LAP', 'TC-PRD-001, TC-PRD-010', '100% Covered'],
        ['Lampiran B TC-41', 'PRD Lampiran B', 'Produksi & Request', 'Batch dan Request: Hubungkan 2 Request ke 1 batch -> Detail batch menampilkan kebutuhan, diminta, dan diterima per bahan.', '11_TC_PRODUKSI_STOK_LAP', 'TC-PRD-005, TC-PRD-006', '100% Covered'],
        ['Lampiran B TC-42', 'PRD Lampiran B', 'Transfer Defect Dashboard', 'Dashboard & Kalkulasi Rusak Bulanan per Gudang: Selesaikan beberapa Transfer dengan qty_rusak > 0 lintas gudang dan bulan -> Dashboard menampilkan KPI kerusakan bulan berjalan serta matriks bulanan per gudang secara akurat.', '10_TC_REQUEST_TRANSFER', 'TC-RT-028 s/d TC-RT-031', '100% Covered'],
    ];

    return [
        [
            'name' => '01_STLC_TEST_PLAN',
            'columns' => $sheet1Cols,
            'rows' => $sheet1Rows,
            'options' => [
                'title' => 'MASTER TEST PLAN & STANDAR STLC — SISTEM SUPPLY CHAIN ONLINE (SC ONLINE) PRD v2.4',
                'subtitle' => 'CV. Heaven Scent — Divisi IT & Quality Assurance | Standar: ISTQB / ISO-29119 / IEEE 829 | Tanggal: 11 November 2026',
                'freezeRow' => 3,
                'autoFilter' => 'A3:E' . (3 + count($sheet1Rows)),
            ],
        ],
        [
            'name' => '02_DASHBOARD_SUMMARY',
            'columns' => $sheet2Cols,
            'rows' => $sheet2Rows,
            'options' => [
                'title' => 'EXECUTIVE QA TEST EXECUTION DASHBOARD & COVERAGE METRICS (LIVE FORMULAS)',
                'subtitle' => 'Metrik otomatis dihitung secara real-time dari kolom Priority (G) dan Status (P) pada Worksheet 05 s/d 12',
                'freezeRow' => 3,
            ],
        ],
        [
            'name' => '03_TEST_DATA_PERSONA',
            'columns' => $sheet3Cols,
            'rows' => $sheet3Rows,
            'options' => [
                'title' => 'BASELINE DATA UJI, KREDENSIAL 5 PERSONA, MASTER GUDANG/SKU/BOM & ANGKA ACUAN RUMUS BAB 6',
                'subtitle' => 'Sumber Kebenaran Data Uji: DatabaseSeeder (UserSeeder, PermissionSeeder, MasterDataSeeder) & PRD_SC_Online_v2.4.md Bab 6',
                'freezeRow' => 3,
                'autoFilter' => 'A3:E' . (3 + count($sheet3Rows)),
            ],
        ],
        [
            'name' => '04_RTM_TRACEABILITY',
            'columns' => $sheet4Cols,
            'rows' => $sheet4Rows,
            'options' => [
                'title' => 'REQUIREMENTS TRACEABILITY MATRIX (RTM) — PEMETAAN PRD v2.4, BUSINESS RULES & LAMPIRAN B KE TEST CASE',
                'subtitle' => 'Memastikan 100% Functional Requirements (FR-*), Business Rules (BR-01..11), Override Operasional #1 & #2, dan TC-01..42 teruji tanpa celah',
                'freezeRow' => 3,
                'autoFilter' => 'A3:G' . (3 + count($sheet4Rows)),
            ],
        ],
    ];
}
