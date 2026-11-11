<?php

declare(strict_types=1);

use App\QaExcel\XlsxWorkbookBuilder;

/**
 * Returns Sheet definitions for Tabs 01-03:
 * 1. Ringkasan (Executive Dashboard with live Excel formulas)
 * 2. Petunjuk (Testing Guide, Seeder Accounts, Execution Order, Locked Overrides)
 * 3. Matriks Akses (RBAC Permission Matrix across 5 Roles)
 *
 * @return array<int, array<string, mixed>>
 */
function getTabsRingkasanPetunjukMatriks(): array
{
    // =========================================================================
    // TAB 1: Ringkasan (Executive Dashboard)
    // =========================================================================
    $ringkasanCols = [
        ['header' => 'Sheet', 'width' => 20],
        ['header' => 'Role / Lingkup', 'width' => 22],
        ['header' => 'Total TC', 'width' => 11],
        ['header' => 'Pass', 'width' => 10],
        ['header' => 'Fail', 'width' => 10],
        ['header' => 'Blocked', 'width' => 10],
        ['header' => 'N/A', 'width' => 10],
        ['header' => 'Not Run', 'width' => 11],
        ['header' => 'Progress Eksekusi', 'width' => 18],
        ['header' => 'Pass Rate', 'width' => 15],
        ['header' => 'Critical', 'width' => 11],
        ['header' => 'High', 'width' => 11],
    ];

    // Rows 4..11 in Excel (since Row 1=Title, Row 2=Subtitle, Row 3=Header)
    $summaryTargets = [
        ['sheet' => 'TC-Umum',        'scope' => 'Semua Role',  'type' => 'role_tc'],
        ['sheet' => 'TC-Purchasing',  'scope' => 'Purchasing',  'type' => 'role_tc'],
        ['sheet' => 'TC-Manager',     'scope' => 'Manager',     'type' => 'role_tc'],
        ['sheet' => 'TC-Gudang',      'scope' => 'Gudang',      'type' => 'role_tc'],
        ['sheet' => 'TC-Operasional', 'scope' => 'Operasional', 'type' => 'role_tc'],
        ['sheet' => 'TC-Fulfillment', 'scope' => 'Fulfillment', 'type' => 'role_tc'],
        ['sheet' => 'TC-E2E',         'scope' => 'Lintas Role', 'type' => 'e2e_tc'],
        ['sheet' => 'Matriks Akses',  'scope' => 'Hak Akses',   'type' => 'rbac_matrix'],
    ];

    $ringkasanRows = [];
    foreach ($summaryTargets as $idx => $t) {
        $excelRow = $idx + 4;
        $sh = $t['sheet'];
        if ($t['type'] === 'role_tc') {
            // 18-col Role TC: B=TC ID, H=Prioritas, O=Status
            $totalFormula = "COUNTIF('{$sh}'!B4:B250,\"TC-*\")";
            $passFormula = "COUNTIF('{$sh}'!O4:O250,\"Pass\")";
            $failFormula = "COUNTIF('{$sh}'!O4:O250,\"Fail\")";
            $blockFormula = "COUNTIF('{$sh}'!O4:O250,\"Blocked\")";
            $naFormula = "COUNTIF('{$sh}'!O4:O250,\"N/A\")";
            $notRunFormula = "COUNTIF('{$sh}'!O4:O250,\"Not Run\")";
            $critCell = ['formula' => "COUNTIF('{$sh}'!H4:H250,\"Critical\")"];
            $highCell = ['formula' => "COUNTIF('{$sh}'!H4:H250,\"High\")"];
        } elseif ($t['type'] === 'e2e_tc') {
            // 14-col E2E TC: B=TC ID, K=Status
            $totalFormula = "COUNTIF('{$sh}'!B4:B250,\"TC-*\")";
            $passFormula = "COUNTIF('{$sh}'!K4:K250,\"Pass\")";
            $failFormula = "COUNTIF('{$sh}'!K4:K250,\"Fail\")";
            $blockFormula = "COUNTIF('{$sh}'!K4:K250,\"Blocked\")";
            $naFormula = "COUNTIF('{$sh}'!K4:K250,\"N/A\")";
            $notRunFormula = "COUNTIF('{$sh}'!K4:K250,\"Not Run\")";
            $critCell = '-';
            $highCell = '-';
        } else {
            // 10-col Matriks Akses: C=Permission, I=Status Uji
            $totalFormula = "COUNTA('{$sh}'!C4:C120)";
            $passFormula = "COUNTIF('{$sh}'!I4:I120,\"Pass\")";
            $failFormula = "COUNTIF('{$sh}'!I4:I120,\"Fail\")";
            $blockFormula = "COUNTIF('{$sh}'!I4:I120,\"Blocked\")";
            $naFormula = "COUNTIF('{$sh}'!I4:I120,\"N/A\")";
            $notRunFormula = "COUNTIF('{$sh}'!I4:I120,\"Not Run\")";
            $critCell = '-';
            $highCell = '-';
        }

        $ringkasanRows[] = [
            ['value' => $sh, 'style' => XlsxWorkbookBuilder::STYLE_DATA_BOLD],
            $t['scope'],
            ['formula' => $totalFormula],
            ['formula' => $passFormula, 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS],
            ['formula' => $failFormula, 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P0],
            ['formula' => $blockFormula, 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P1],
            ['formula' => $naFormula],
            ['formula' => $notRunFormula],
            ['formula' => "IF(C{$excelRow}>0,(C{$excelRow}-H{$excelRow})/C{$excelRow},0)", 'style' => XlsxWorkbookBuilder::STYLE_PERCENT],
            ['formula' => "IF((D{$excelRow}+E{$excelRow})>0,D{$excelRow}/(D{$excelRow}+E{$excelRow}),0)", 'style' => XlsxWorkbookBuilder::STYLE_PERCENT],
            $critCell,
            $highCell,
        ];
    }

    // Row 12: TOTAL
    $ringkasanRows[] = [
        ['value' => 'TOTAL', 'style' => XlsxWorkbookBuilder::STYLE_DATA_BOLD],
        ['value' => 'Seluruh Lingkup Uji', 'style' => XlsxWorkbookBuilder::STYLE_DATA_BOLD],
        ['formula' => 'SUM(C4:C11)'],
        ['formula' => 'SUM(D4:D11)', 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS],
        ['formula' => 'SUM(E4:E11)', 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P0],
        ['formula' => 'SUM(F4:F11)', 'style' => XlsxWorkbookBuilder::STYLE_PRIORITY_P1],
        ['formula' => 'SUM(G4:G11)'],
        ['formula' => 'SUM(H4:H11)'],
        ['formula' => 'IF(C12>0,(C12-H12)/C12,0)', 'style' => XlsxWorkbookBuilder::STYLE_PERCENT],
        ['formula' => 'IF((D12+E12)>0,D12/(D12+E12),0)', 'style' => XlsxWorkbookBuilder::STYLE_PERCENT],
        ['formula' => 'SUM(K4:K9)'],
        ['formula' => 'SUM(L4:L9)'],
    ];

    $ringkasanRows[] = [
        '_section' => true,
        'label' => 'Catatan: Matriks Akses dihitung per baris aksi permission. Progress Eksekusi = (Total TC - Not Run) / Total TC. Pass Rate = Pass / (Pass + Fail).',
    ];

    // =========================================================================
    // TAB 2: Petunjuk (Testing Guide & Setup)
    // =========================================================================
    $petunjukCols = [
        ['header' => 'Kategori / Bagian', 'width' => 28],
        ['header' => 'Item / Parameter', 'width' => 30],
        ['header' => 'Detail Spesifikasi & Panduan Eksekusi QA', 'width' => 88],
    ];

    $petunjukRows = [
        ['_section' => true, 'label' => '1. INFORMASI DOKUMEN & SUMBER ACUAN'],
        ['Identitas Dokumen', 'Nama Proyek', 'SC ONLINE (Supply Chain Online) — CV. Heaven Scent'],
        ['Identitas Dokumen', 'Versi Dokumen Uji', 'v2.4 — Disusun dari PRD_SC_Online_v2.4.md, DOKUMENTASI_FLOW_BISNIS.md, PRD v2.0, Rancangan Analisa Stok (Skema Generate), Addendum Analisa Impor v2.2, dan kode Laravel production-lab-online-hs'],
        ['Identitas Dokumen', 'Tanggal Disusun', '2026-11-11'],
        ['Identitas Dokumen', 'Sumber Dokumen Bisnis', 'PRD_SC_Online_v2.4.md; DOKUMENTASI_FLOW_BISNIS.md; PRD SC Online v3 (1).docx; Menu Analisa Stok & Master Produk.docx; Perubahan Analisa Impor.docx'],
        ['Identitas Dokumen', 'Sumber Kode Aktual', 'routes/web.php, app/Http/Controllers/* (termasuk PurchasingStageController, RequestController, TransferController), app/Services/* (PurchasingStageService, SupplierLeadTimeService, TransferService, AnalisaService), database/seeders/PermissionSeeder.php & UserSeeder.php'],

        ['_section' => true, 'label' => '2. URUTAN EKSEKUSI PENGUJIAN (STLC EXECUTION SEQUENCE)'],
        ['Urutan Eksekusi', '1. TC-Umum', 'Login, sesi, heartbeat (/session/ping), CSRF 419, role switcher (local), navigasi sidebar — jalankan untuk tiap role'],
        ['Urutan Eksekusi', '2. TC-Purchasing', 'Master data pembelian, Analisa Stok (Lokal/Impor/Multi-Supplier), PO Manual & 9-Stage Pipeline (Stage 1, 3, 4 Dibayarkan/Lanjut Tempo, 5, 6, 7), Direct Edit vs Usulan Revisi Komitmen (PoRevisi), Pembayaran AP'],
        ['Urutan Eksekusi', '3. TC-Manager', 'Approval Stage 2 PO, Reject Stage 2 (Putaran Baru Stage 1), Approval/Reject Revisi Komitmen, Invalidasi Transaksi Lead Time, Approval/Reject Request (/requests), Pembatalan PO & Transfer, Master Data penuh, Admin User/Role'],
        ['Urutan Eksekusi', '4. TC-Gudang', 'Privasi harga PO, Barang Datang legacy & 9-Stage (Stage 8 Unloading belum posting vs Stage 9 Input Sistem posting stok + rekam Lead Time), Transfer (/transfers) Pemenuhan Request Bahan 1:1, Kirim & Terima Retur Bahan, Stok bahan baku'],
        ['Urutan Eksekusi', '5. TC-Operasional', 'BOM, Batch Produksi (Rencana, Release & Issue, Selesaikan, Opname Batch, Kirim Produk Jadi), Permintaan Bahan (/requests), Penerimaan Transfer Bahan (qty_baik + qty_rusak = qty_dikirim), Retur Bahan, Opname Gudang'],
        ['Urutan Eksekusi', '6. TC-Fulfillment', 'Permintaan Produk Jadi (/requests), Penerimaan Transfer Produk Jadi (qty_baik + qty_rusak = qty_dikirim), Distribusi Antar Fulfillment (SoD anti self-dealing), Retur Produk Jadi, Analisa Produk Jadi (per gudang & ALL), Mutasi Customer'],
        ['Urutan Eksekusi', '7. TC-E2E', 'Alur lintas role end-to-end (dijalankan setelah TC per role lulus: Procure-to-Pay 9-Stage + Lead Time 1 Desimal, Revisi Komitmen, Request-to-Transfer + Defect Bulanan, Plan-to-Produce-to-Deliver, Konsistensi Kartu Stok)'],
        ['Urutan Eksekusi', '8. Matriks Akses', 'Verifikasi 58 permission Spatie per role + LocationScopeService + aturan Separation of Duties (SoD)'],
        ['Urutan Eksekusi', '9. Kalkulasi Analisa', 'Kalkulator rumus Analisa Lokal / Impor / Fulfillment / Lead Time NUMERIC(10,1) Half-Up / Defect Rate Bulanan untuk mencocokkan angka aplikasi'],
        ['Urutan Eksekusi', '10. Gap Kode vs Dokumen', 'Daftar perbedaan perilaku kode terhadap dokumen PRD v2.4 & Analisa — perlu konfirmasi/perbaikan sebelum UAT Sign-Off'],

        ['_section' => true, 'label' => '3. AKUN UJI STANDAR (database/seeders/UserSeeder.php — Password Default: "password")'],
        ['Akun Uji (UserSeeder)', 'Admin / Super Manager', 'admin@heavenscent.id | Password: password | Role: manager | Divisi: manajemen | Cakupan Gudang: global (seluruh gudang)'],
        ['Akun Uji (UserSeeder)', 'Manager (Andyka)', 'manager@heavenscent.id | Password: password | Role: manager | Divisi: manajemen | Cakupan Gudang: global (seluruh gudang & approval penuh)'],
        ['Akun Uji (UserSeeder)', 'Purchasing (Rina)', 'purchasing@heavenscent.id | Password: password | Role: purchasing | Divisi: purchasing | Cakupan Gudang: global (pengadaan, analisa, PO, pembayaran)'],
        ['Akun Uji (UserSeeder)', 'Gudang (Budi)', 'gudang@heavenscent.id | Password: password | Role: gudang | Divisi: gudang | Cakupan Gudang: restricted (gudang tipe bahan_baku / GD-PUSAT)'],
        ['Akun Uji (UserSeeder)', 'Operasional (Dedi)', 'operasional@heavenscent.id | Password: password | Role: operasional | Divisi: produksi | Cakupan Gudang: restricted (gudang tipe operasional / GD-OPS)'],
        ['Akun Uji (UserSeeder)', 'Fulfillment (Sari)', 'fulfillment@heavenscent.id | Password: password | Role: fulfillment | Divisi: fulfillment | Cakupan Gudang: restricted (gudang fulfillment FF Pusat; siapkan 1 user fulfillment tambahan akses FF SBY untuk uji SoD)'],

        ['_section' => true, 'label' => '4. CARA PENGISIAN & STANDAR STATUS / PRIORITAS'],
        ['Cara Pengisian', 'Kolom yang Diisi Tester', 'Hasil Aktual, Status (dropdown), Tester, Tgl Uji, Catatan / Bug ID — ditandai sel berlatar kuning muda (#FEF9C3)'],
        ['Cara Pengisian', 'Pilihan Status (Dropdown)', 'Not Run (default) · Pass · Fail · Blocked · N/A — otomatis mengubah warna sel dan menghitung progres di tab Ringkasan'],
        ['Cara Pengisian', 'Contoh Pengisian', "Hasil Aktual: 'Stage 9 selesai, stok bertambah, PoLeadTime tercatat 1.1 hari' · Status: Pass · Tester: QA-1 · Tgl Uji: 2026-11-11"],
        ['Cara Pengisian', 'Tipe Test', 'Positif (Happy Path) · Negatif (Validasi form, transisi ilegal, race condition/double-click) · Akses (RBAC permission 403) · Keamanan (SoD, idempotensi, audit) · Kalkulasi (Rumus matematis)'],
        ['Cara Pengisian', 'Prioritas', 'Critical (Blocker alur utama / integritas stok & uang) · High (Fitur utama modul) · Medium (Validasi & edge case) · Low (UI/UX & kosmetik)'],

        ['_section' => true, 'label' => '5. DUA OVERRIDE OPERASIONAL TERKUNCI PRD v2.4 (WAJIB LULUS 100%)'],
        ['Override Terkunci #1', 'Toleransi 1 Desimal Lead Time', 'Kalkulasi durasi internal memakai satuan menit utuh (1 hari = 1.440 menit). Seluruh tampilan & kolom DB nilai hari menggunakan NUMERIC(10,1) dengan pembulatan half-up pada digit desimal ke-2 (< 5 menjadi 0; >= 5 menjadi +0.1). Contoh: 1.485 menit = 1,03125 hari -> 1,0 hari; 1.512 menit = 1,05 hari -> 1,1 hari.'],
        ['Override Terkunci #2', 'Invarian QC Transfer & Defect Bulanan', 'Pada penerimaan Transfer (/transfers/{transfer}/receive), wajib berlaku invarian eksak qty_dikirim = qty_baik + qty_rusak per item. Hanya qty_baik yang menambah saldo STOK gudang tujuan. Jika qty_rusak > 0, keterangan_rusak wajib diisi dan terakumulasi otomatis di Dashboard & Laporan Defect Bulanan per Gudang.'],
    ];

    // =========================================================================
    // TAB 3: Matriks Akses (RBAC Matrix — 58 Baris Permission)
    // =========================================================================
    $matriksCols = [
        ['header' => 'Menu', 'width' => 22],
        ['header' => 'Aksi', 'width' => 38],
        ['header' => 'Permission', 'width' => 24],
        ['header' => 'Purchasing', 'width' => 12],
        ['header' => 'Gudang', 'width' => 12],
        ['header' => 'Operasional', 'width' => 12],
        ['header' => 'Fulfillment', 'width' => 12],
        ['header' => 'Manager', 'width' => 12],
        ['header' => 'Status Uji', 'width' => 14],
        ['header' => 'Catatan', 'width' => 32],
    ];

    // Exact mapping from database/seeders/PermissionSeeder.php
    $matriksRows = [
        ['Dashboard', 'Lihat dashboard sesuai role', '(auth)', '✓', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Purchasing', 'Lihat daftar & detail PO + 9-Stage', 'purchasing.view', '✓', '✓', '✗', '✗', '✓', 'Not Run', 'Gudang lihat tanpa harga'],
        ['Purchasing', 'Lihat harga, total, hutang (AP)', 'purchasing.price.view', '✓', '✗', '✗', '✗', '✓', 'Not Run', 'Privasi harga PO untuk Gudang'],
        ['Purchasing', 'Buat PO manual', 'purchasing.create', '✓', '✗', '✗', '✗', '✓', 'Not Run', 'Otomatis inisiasi Stage 1'],
        ['Purchasing', 'Edit PO / Edit tanggal / Complete Stage 3, 5, 6, 7 / Direct Edit / Submit Revisi', 'purchasing.edit', '✓', '✗', '✗', '✗', '✓', 'Not Run', 'Stage 7 juga bisa oleh purchasing.receive'],
        ['Purchasing', 'Ajukan PO / Complete Stage 1 (Perencanaan -> Approval)', 'purchasing.submit', '✓', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Purchasing', 'Setujui/Tolak Stage 2 PO, Setujui/Tolak Revisi Komitmen, Invalidasi Lead Time', 'purchasing.approve', '✗', '✗', '✗', '✗', '✓', 'Not Run', 'Khusus Manager'],
        ['Purchasing', 'Input Barang Datang / Complete Stage 7, 8 (Unloading) & Stage 9 (Input Sistem)', 'purchasing.receive', '✗', '✓', '✗', '✗', '✓', 'Not Run', 'Stage 8 belum posting, Stage 9 posting stok'],
        ['Purchasing', 'Catat pembayaran / Stage 4 Dibayarkan & Lanjut Tempo', 'purchasing.pay', '✓', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Purchasing', 'Batalkan PO (sebelum Stage 8 selesai)', 'purchasing.cancel', '✓', '✗', '✗', '✗', '✓', 'Not Run', 'Alasan batal wajib diisi'],
        ['Purchasing', 'Audit log perubahan PO', 'purchasing.audit', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Produksi (Batch)', 'Lihat daftar & detail batch produksi', 'batch.view', '✗', '✗', '✓', '✗', '✓', 'Not Run', ''],
        ['Produksi (Batch)', 'Buat rencana batch (1 output / multi-output)', 'batch.create', '✗', '✗', '✓', '✗', '✓', 'Not Run', 'Alokasi bahan aktif dari BOM'],
        ['Produksi (Batch)', 'Release & Issue bahan baku batch', 'batch.release', '✗', '✗', '✓', '✗', '✓', 'Not Run', 'Potong stok Gudang Operasional'],
        ['Produksi (Batch)', 'Selesaikan batch (qty_baik + qty_rusak = rencana)', 'batch.complete', '✗', '✗', '✓', '✗', '✓', 'Not Run', 'Tambah stok produk jadi'],
        ['Produksi (Batch)', 'Batalkan batch berstatus Rencana', 'batch.cancel', '✗', '✗', '✓', '✗', '✓', 'Not Run', 'Lepas alokasi bahan'],
        ['Produksi (Batch)', 'Stock opname pemakaian aktual batch', 'batch.opname', '✗', '✗', '✓', '✗', '✓', 'Not Run', ''],
        ['Produksi (Batch)', 'Kirim Produk Jadi dari batch selesai', 'rt.create', '✗', '✓', '✓', '✓', '✓', 'Not Run', 'Membuat dokumen transfer kirim_produk_jadi'],
        ['Permintaan (/requests)', 'Lihat daftar & detail Permintaan Barang (PRD v2.4)', 'request.view', '✗', '✓', '✓', '✓', '✓', 'Not Run', 'Route memakai middleware can:rt.view'],
        ['Permintaan (/requests)', 'Buat & edit draft Permintaan Barang (Bahan / Produk Jadi)', 'request.create', '✗', '✗', '✓', '✓', '✓', 'Not Run', 'Operasional=request_bahan, Fulfillment=request_produk_jadi'],
        ['Permintaan (/requests)', 'Ajukan Permintaan Barang ke Manager', 'request.submit', '✗', '✗', '✓', '✓', '✓', 'Not Run', ''],
        ['Permintaan (/requests)', 'Setujui / Tolak Permintaan Barang (/requests)', 'request.approve', '✗', '✗', '✗', '✗', '✓', 'Not Run', 'Khusus Manager'],
        ['Permintaan (/requests)', 'Batalkan Permintaan Barang', 'request.cancel', '✗', '✗', '✓', '✓', '✓', 'Not Run', ''],
        ['Transfer (/transfers)', 'Lihat daftar, detail & Surat Jalan Transfer (PRD v2.4)', 'transfer.view', '✗', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Transfer (/transfers)', 'Buat Transfer (Pemenuhan Request 1:1 / Retur / Antar Fulfillment)', 'transfer.create', '✗', '✓', '✗', '✓', '✓', 'Not Run', 'Route memakai can:rt.create'],
        ['Transfer (/transfers)', 'Kirim Transfer (In Transit — potong stok gudang asal)', 'transfer.ship', '✗', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Transfer (/transfers)', 'Terima Transfer & QC (qty_baik + qty_rusak = qty_dikirim + SoD)', 'transfer.receive', '✗', '✓', '✓', '✓', '✓', 'Not Run', 'Penerima != Pembuat kecuali Manager'],
        ['Transfer (/transfers)', 'Batalkan Draft Transfer (kembalikan Request ke disetujui)', 'transfer.cancel', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Transfer (/transfers)', 'Lihat Dashboard Analitik Defect Bulanan (/transfers/defect-analytics)', 'defect.view', '✗', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Request & Transfer (Legacy)', 'Lihat dokumen (/request-transfer)', 'rt.view', '✗', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Request & Transfer (Legacy)', 'Buat / edit draft (/request-transfer)', 'rt.create', '✗', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Request & Transfer (Legacy)', 'Ajukan Request Bahan', 'rt.submit', '✗', '✓', '✓', '✗', '✓', 'Not Run', ''],
        ['Request & Transfer (Legacy)', 'Setujui Request Bahan', 'rt.approve', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Request & Transfer (Legacy)', 'Proses & Kirim Request Bahan', 'rt.process', '✗', '✓', '✗', '✗', '✓', 'Not Run', ''],
        ['Request & Transfer (Legacy)', 'Kirim transfer langsung', 'rt.ship', '✗', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Request & Transfer (Legacy)', 'Terima transfer (+ aturan SoD gudang tujuan)', 'rt.receive', '✗', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Request & Transfer (Legacy)', 'Batalkan dokumen apa pun', 'rt.cancel', '✗', '✗', '✗', '✗', '✓', 'Not Run', 'Pembuat boleh batalkan sebelum disetujui'],
        ['Analisa Stok', 'Lihat analisa (Lokal, Impor, Produk Jadi, Perbandingan Supplier, Riwayat)', 'analisa.view', '✓', '✗', '✗', '✓', '✓', 'Not Run', ''],
        ['Analisa Stok', 'Generate / edit input / lead time / pilih supplier / Klasifikasi ABC', 'analisa.manage', '✓', '✗', '✗', '✓', '✓', 'Not Run', 'Lihat Gap G-11 untuk Fulfillment'],
        ['Analisa Stok', 'Snapshot / Finalisasi ke Riwayat Analisa', 'analisa.snapshot', '✓', '✗', '✗', '✓', '✓', 'Not Run', ''],
        ['Analisa Stok', 'Buat PO dari hasil Analisa Stok', 'analisa.create_po', '✓', '✗', '✗', '✗', '✓', 'Not Run', 'Fulfillment tidak bisa Buat PO'],
        ['Stok & Mutasi', 'Lihat stok (sesuai cakupan gudang LocationScopeService)', 'stok.view', '✓', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Stok & Mutasi', 'Mutasi manual in/out', 'stok.mutasi', '✓', '✓', '✗', '✓', '✓', 'Not Run', ''],
        ['Stok & Mutasi', 'Stock opname gudang', 'stok.opname', '✗', '✗', '✓', '✗', '✓', 'Not Run', ''],
        ['Stok & Mutasi', 'Kartu stok (ledger)', 'stok.ledger.view', '✓', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Master Data', 'Lihat produk / kategori / varian', 'produk.view', '✓', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Master Data', 'Tambah produk / kategori / varian', 'produk.create', '✓', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Edit produk / kategori / varian', 'produk.edit', '✓', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Hapus produk / kategori / varian', 'produk.delete', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Lihat gudang', 'gudang.view', '✓', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Master Data', 'Tambah / edit / hapus gudang', 'gudang.create', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Lihat supplier', 'supplier.view', '✓', '✓', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Tambah / edit supplier', 'supplier.create', '✓', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Hapus supplier', 'supplier.delete', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Lihat UOM (Satuan)', 'uom.view', '✓', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Master Data', 'Tambah / edit UOM', 'uom.create', '✓', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Lihat BOM', 'bom.view', '✓', '✓', '✓', '✓', '✓', 'Not Run', ''],
        ['Master Data', 'Edit BOM', 'bom.manage', '✗', '✗', '✓', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Import BOM (CSV & Bulk Grid)', 'bom.import', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Master Data', 'Kelola Divisi', 'divisi.view', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Laporan', 'Lihat 7 laporan (Produksi, Bahan, Defect, Low Stock, Purchasing, Lead Time, Fulfillment)', 'report.view', '✓', '✓', '✓', '✓', '✓', 'Not Run', 'Termasuk /laporan/lead-time & /laporan/defect'],
        ['Laporan', 'Export laporan', 'report.export', '✓', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Admin', 'Kelola user & cakupan akses gudang', 'user.manage', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
        ['Admin', 'Kelola role & permission kustom', 'role.manage', '✗', '✗', '✗', '✗', '✓', 'Not Run', ''],
    ];

    return [
        [
            'name' => 'Ringkasan',
            'columns' => $ringkasanCols,
            'rows' => $ringkasanRows,
            'options' => [
                'title' => 'RINGKASAN EKSEKUSI TEST — SC ONLINE CV. HEAVEN SCENT (PRD v2.4)',
                'subtitle' => 'Dihitung otomatis dari kolom Status di setiap tab pengujian menggunakan rumus COUNTIF & SUM.',
                'freezeRow' => 3,
                'autoFilter' => 'A3:L11',
            ],
        ],
        [
            'name' => 'Petunjuk',
            'columns' => $petunjukCols,
            'rows' => $petunjukRows,
            'options' => [
                'title' => 'TEST CASE & UAT SUITE — SC ONLINE (Supply Chain Online) CV. Heaven Scent',
                'subtitle' => 'Disusun dari PRD SC Online v2.4, DOKUMENTASI_FLOW_BISNIS.md, Rancangan Analisa Stok, Addendum Impor v2.2 & Kode Laravel production-lab-online-hs',
                'freezeRow' => 3,
                'autoFilter' => 'A3:C30',
            ],
        ],
        [
            'name' => 'Matriks Akses',
            'columns' => $matriksCols,
            'rows' => $matriksRows,
            'options' => [
                'title' => 'MATRIKS HAK AKSES PER ROLE (RBAC & LOCATION SCOPE MATRIX)',
                'subtitle' => '✓ = diizinkan · ✗ = harus ditolak (403 / tombol tidak tampil). Sumber: database/seeders/PermissionSeeder.php + LocationScopeService.',
                'freezeRow' => 3,
                'sheetType' => 'rbac_matrix',
                'isTestCaseSheet' => true,
            ],
        ],
    ];
}
