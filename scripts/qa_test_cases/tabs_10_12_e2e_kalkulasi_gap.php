<?php

declare(strict_types=1);

use App\QaExcel\XlsxWorkbookBuilder;

/**
 * Returns Sheet definitions for Tabs 10-12:
 * 10. TC-E2E (Lintas Role End-to-End — 55 Steps across 9 E2E Flows)
 * 11. Kalkulasi Analisa (Interactive Excel Calculator with live formulas & Cek column)
 * 12. Gap Kode vs Dokumen (Static & Dynamic Audit of PRD v2.4 & Analisa Docs vs Current Codebase)
 *
 * @return array<int, array<string, mixed>>
 */
function getTabsE2eKalkulasiGap(): array
{
    // =========================================================================
    // TAB 10: TC-E2E (14 Standard Columns)
    // =========================================================================
    $e2eCols = [
        ['header' => 'No', 'width' => 6],
        ['header' => 'TC ID', 'width' => 15],
        ['header' => 'Alur', 'width' => 36],
        ['header' => 'Langkah', 'width' => 9],
        ['header' => 'Role Pelaksana', 'width' => 18],
        ['header' => 'Aksi', 'width' => 38],
        ['header' => 'Data Uji', 'width' => 28],
        ['header' => 'Hasil yang Diharapkan', 'width' => 44],
        ['header' => 'Referensi', 'width' => 24],
        ['header' => 'Hasil Aktual', 'width' => 26],
        ['header' => 'Status', 'width' => 14],
        ['header' => 'Tester', 'width' => 12],
        ['header' => 'Tgl Uji', 'width' => 14],
        ['header' => 'Catatan / Bug ID', 'width' => 26],
    ];

    $e2e = static fn (
        int $no,
        string $tcId,
        string $alur,
        int $langkah,
        string $role,
        string $aksi,
        string $data,
        string $expected,
        string $ref
    ): array => [
        $no, $tcId, $alur, $langkah, $role, $aksi, $data, $expected, $ref, '', 'Not Run', '', '', '',
    ];

    $e2eRows = [
        // E2E-01
        $e2e(1, 'TC-E2E-01-01', 'E2E-01 Procure-to-Pay Bahan Lokal', 1, 'Purchasing', 'Generate Analisa Lokal ALK-01', 'Stok 8.000, terjual 39.303,25', 'Status ORDER, rekomendasi muncul', 'PRD 3.1 langkah 1–2'),
        $e2e(2, 'TC-E2E-01-02', 'E2E-01 Procure-to-Pay Bahan Lokal', 2, 'Purchasing', 'Buat PO dari Analisa (centang ALK-01 > Buat PO)', 'Supplier PT Alkohol Nusantara', "PO Draft 'Dari Analisa' qty ter-prefill", 'FR-ANL-03'),
        $e2e(3, 'TC-E2E-01-03', 'E2E-01 Procure-to-Pay Bahan Lokal', 3, 'Purchasing', 'Ajukan PO', '-', 'Status Diajukan; dashboard Manager +1', 'FR-PUR-02'),
        $e2e(4, 'TC-E2E-01-04', 'E2E-01 Procure-to-Pay Bahan Lokal', 4, 'Manager', 'Setujui PO', '-', 'Status Dikirim ke Gudang; dashboard Gudang +1', 'FR-PUR-02'),
        $e2e(5, 'TC-E2E-01-05', 'E2E-01 Procure-to-Pay Bahan Lokal', 5, 'Purchasing', 'Generate ulang Analisa Lokal', '-', 'Akan Datang ALK-01 = qty PO; status bisa berubah menjadi TIDAK', 'Menu Analisa 2.4'),
        $e2e(6, 'TC-E2E-01-06', 'E2E-01 Procure-to-Pay Bahan Lokal', 6, 'Gudang', 'Input Barang Datang qty sesuai PO', '-', 'Stok Gudang Pusat bertambah; PO Selesai; kartu stok referensi PO', 'FR-PUR-03'),
        $e2e(7, 'TC-E2E-01-07', 'E2E-01 Procure-to-Pay Bahan Lokal', 7, 'Purchasing', 'Bayar termin 1 lalu pelunasan', '50% + sisa', 'Status bayar Parsial → Lunas; sisa tagihan 0', 'FR-PUR-04'),
        $e2e(8, 'TC-E2E-01-08', 'E2E-01 Procure-to-Pay Bahan Lokal', 8, 'Purchasing', 'Generate ulang & Finalisasi', '-', 'Akan Datang kembali 0, stok naik; riwayat tersimpan dengan session ID', 'FR-ANL-04'),
        $e2e(9, 'TC-E2E-01-09', 'E2E-01 Procure-to-Pay Bahan Lokal', 9, 'Manager', 'Cek Laporan Purchasing/Hutang', '-', 'PO tercatat lunas, hutang berkurang', 'FR-LAP-01'),

        // E2E-02
        $e2e(10, 'TC-E2E-02-01', 'E2E-02 Procure-to-Pay Bahan Impor (Botol 50ml)', 1, 'Purchasing', 'Isi data impor BTL-P50 & Generate', 'Data kasus uji 6.3.5', 'PO = 80.000, ORDER, nominal Rp120.000.000', 'Addendum 6.3.5'),
        $e2e(11, 'TC-E2E-02-02', 'E2E-02 Procure-to-Pay Bahan Impor (Botol 50ml)', 2, 'Purchasing', 'Buat PO dari Analisa (tipe impor)', 'Supplier impor', 'Draft PO qty 80.000 dari_analisa = true', 'FR-ANL-03'),
        $e2e(12, 'TC-E2E-02-03', 'E2E-02 Procure-to-Pay Bahan Impor (Botol 50ml)', 3, 'Purchasing', 'Ajukan PO', '-', 'Diajukan', 'FR-PUR-02'),
        $e2e(13, 'TC-E2E-02-04', 'E2E-02 Procure-to-Pay Bahan Impor (Botol 50ml)', 4, 'Manager', 'Setujui PO', '-', 'Dikirim ke Gudang', 'FR-PUR-02'),
        $e2e(14, 'TC-E2E-02-05', 'E2E-02 Procure-to-Pay Bahan Impor (Botol 50ml)', 5, 'Gudang', 'Barang Datang 79.500 (selisih −500)', 'Kondisi rusak_sebagian', 'Stok +79.500, selisih tercatat, PO Selesai', 'FR-PUR-03'),
        $e2e(15, 'TC-E2E-02-06', 'E2E-02 Procure-to-Pay Bahan Impor (Botol 50ml)', 6, 'Purchasing', 'Bayar tempo', '-', 'Status bayar sesuai nominal', 'FR-PUR-04'),

        // E2E-03
        $e2e(16, 'TC-E2E-03-01', 'E2E-03 Plan-to-Produce-to-Deliver', 1, 'Operasional', 'Buat batch GOH-P50 qty 80.000 tujuan FF Pusat', '-', 'Batch Rencana, alokasi aktif, Kolom Rencana bahan berkurang & produk jadi bertambah', 'FR-PRD-01'),
        $e2e(17, 'TC-E2E-03-02', 'E2E-03 Plan-to-Produce-to-Deliver', 2, 'Operasional', 'Dari batch, buat Request Bahan kekurangan & Ajukan', '-', 'RT req_bahan Diajukan', 'PRD 3.3'),
        $e2e(18, 'TC-E2E-03-03', 'E2E-03 Plan-to-Produce-to-Deliver', 3, 'Manager', 'Setujui Request Bahan', '-', 'Disetujui', 'FR-RT-03'),
        $e2e(19, 'TC-E2E-03-04', 'E2E-03 Plan-to-Produce-to-Deliver', 4, 'Gudang', 'Proses & Kirim', '-', 'Diproses; stok Gudang Pusat berkurang; surat jalan tersedia', 'FR-RT-04/05'),
        $e2e(20, 'TC-E2E-03-05', 'E2E-03 Plan-to-Produce-to-Deliver', 5, 'Operasional', 'Terima bahan', '-', 'Selesai; stok Gudang Operasional bertambah', 'FR-RT-05'),
        $e2e(21, 'TC-E2E-03-06', 'E2E-03 Plan-to-Produce-to-Deliver', 6, 'Operasional', 'Release & Issue batch', '-', 'Release; stok bahan Operasional berkurang sebesar alokasi; alokasi Dilepas', 'FR-PRD-02'),
        $e2e(22, 'TC-E2E-03-07', 'E2E-03 Plan-to-Produce-to-Deliver', 7, 'Operasional', 'Selesaikan batch 76.000 baik / 4.000 rusak', '-', 'Selesai; stok produk jadi Operasional +76.000', 'FR-PRD-02'),
        $e2e(23, 'TC-E2E-03-08', 'E2E-03 Plan-to-Produce-to-Deliver', 8, 'Operasional', 'Stock Opname batch', 'Aktual ALK-01 4.050', 'Selisih 50 tercatat; tampil di Laporan Pemakaian Bahan', 'FR-STK-03'),
        $e2e(24, 'TC-E2E-03-09', 'E2E-03 Plan-to-Produce-to-Deliver', 9, 'Operasional', 'Kirim Produk Jadi (dari batch) & Kirim', '-', 'RT kirim_produk_jadi Dikirim; stok produk jadi Operasional −76.000', 'FR-PRD-03'),
        $e2e(25, 'TC-E2E-03-10', 'E2E-03 Plan-to-Produce-to-Deliver', 10, 'Fulfillment', 'Terima di FF Pusat', 'Baik 75.900, rusak 100', 'Selesai; stok FF Pusat +75.900', 'PRD 3.4'),
        $e2e(26, 'TC-E2E-03-11', 'E2E-03 Plan-to-Produce-to-Deliver', 11, 'Manager', 'Cek Laporan Produksi & Defect', '-', 'Yield 95%, defect 4.000 (+100 rusak kirim)', 'FR-LAP-01'),

        // E2E-04
        $e2e(27, 'TC-E2E-04-01', 'E2E-04 Distribusi Fulfillment & Retur', 1, 'Fulfillment', 'Cek Analisa Produk Jadi (ALL)', '-', 'Sinyal ORDER bila Stok All ≤ Target Total', 'PRD 6.4'),
        $e2e(28, 'TC-E2E-04-02', 'E2E-04 Distribusi Fulfillment & Retur', 2, 'Fulfillment (FF Pusat)', 'Buat & kirim Antar Fulfillment ke FF SBY', '10.000', 'Dikirim; stok FF Pusat −10.000', 'FR-RT-02'),
        $e2e(29, 'TC-E2E-04-03', 'E2E-04 Distribusi Fulfillment & Retur', 3, 'Fulfillment (FF SBY, user lain)', 'Terima transfer', '-', 'Selesai; stok FF SBY +10.000', 'FR-RT-05'),
        $e2e(30, 'TC-E2E-04-04', 'E2E-04 Distribusi Fulfillment & Retur', 4, 'Fulfillment (FF SBY)', 'Mutasi keluar ke customer', '500', 'Stok FF SBY 9.500', 'PRD 3.4'),
        $e2e(31, 'TC-E2E-04-05', 'E2E-04 Distribusi Fulfillment & Retur', 5, 'Fulfillment', 'Retur Produk Jadi cacat ke Operasional', '50', 'Dikirim; stok FF −50', 'FR-RT-02'),
        $e2e(32, 'TC-E2E-04-06', 'E2E-04 Distribusi Fulfillment & Retur', 6, 'Operasional', 'Terima retur produk jadi', '-', 'Selesai; stok Operasional +qty baik', 'FR-RT-05'),
        $e2e(33, 'TC-E2E-04-07', 'E2E-04 Distribusi Fulfillment & Retur', 7, 'Manager', 'Cek Laporan Pergerakan Stok Fulfillment', '-', 'Seluruh pergerakan tercatat', 'FR-LAP-01'),

        // E2E-05
        $e2e(34, 'TC-E2E-05-01', 'E2E-05 Retur Bahan Operasional → Gudang', 1, 'Operasional', 'Buat Retur Bahan & Kirim', 'ALK-01 100', 'Dikirim; stok Operasional −100', 'FR-RT-03'),
        $e2e(35, 'TC-E2E-05-02', 'E2E-05 Retur Bahan Operasional → Gudang', 2, 'Gudang', 'Terima retur (baik 95, rusak 5)', '-', 'Selesai; stok Gudang Pusat +95', 'FR-RT-05'),

        // E2E-06
        $e2e(36, 'TC-E2E-06-01', 'E2E-06 Pembatalan & Pelepasan Alokasi', 1, 'Operasional', 'Buat batch baru (alokasi aktif)', '-', 'Kolom Rencana bahan berkurang', 'FR-PRD-01'),
        $e2e(37, 'TC-E2E-06-02', 'E2E-06 Pembatalan & Pelepasan Alokasi', 2, 'Operasional', 'Batalkan batch', '-', 'Alokasi Dibatalkan; Kolom Rencana kembali = Kolom Stok; stok fisik tetap', 'Riwayat Keputusan 4'),
        $e2e(38, 'TC-E2E-06-03', 'E2E-06 Pembatalan & Pelepasan Alokasi', 3, 'Purchasing', 'Batalkan PO Diajukan', '-', 'PO Dibatalkan; tidak lagi dihitung Akan Datang', 'PurchasingController@cancel'),

        // E2E-07
        $e2e(39, 'TC-E2E-07-01', 'E2E-07 Konsistensi Kartu Stok', 1, 'Manager', 'Ambil saldo tiap produk-gudang setelah E2E-01..06', '-', 'Saldo STOK = Σ in − Σ out pada KARTU_STOK untuk setiap kombinasi produk-gudang', 'PRD 8.2'),
        $e2e(40, 'TC-E2E-07-02', 'E2E-07 Konsistensi Kartu Stok', 2, 'Manager', 'Telusuri referensi tiap baris kartu stok', '-', 'referensi_tipe/id mengarah ke dokumen PO / RT / Batch / mutasi manual yang benar', 'FR-RT-05; FR-STK-02'),

        // E2E-08 (PRD v2.4: 9-Stage PO, Reject Putaran 2, Revisi Komitmen, Stage 8-9, Lead Time 1 Desimal & Auto-Sync Analisa)
        $e2e(41, 'TC-E2E-08-01', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 1, 'Purchasing', 'Buat PO baru (Stage 1 otomatis aktif) & Selesaikan Stage 1', 'ALK-01 qty 5.000, skema Tempo', 'Stage 1 selesai (putaran 1); Stage 2 terbuka otomatis', 'PRD v2.4 TC-01, TC-06'),
        $e2e(42, 'TC-E2E-08-02', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 2, 'Manager', 'Reject Stage 2 dengan alasan negosiasi ulang', 'Alasan: Minta diskon tambahan', 'Stage 2 putaran 1 ditandai dikembalikan; Stage 1 baru terbuka pada putaran = 2', 'PRD v2.4 BR-06, TC-12'),
        $e2e(43, 'TC-E2E-08-03', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 3, 'Purchasing', 'Selesaikan Stage 1 putaran 2', '-', 'Stage 2 putaran 2 terbuka', 'PurchasingStageService@completeStage1'),
        $e2e(44, 'TC-E2E-08-04', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 4, 'Manager', 'Approve Stage 2 putaran 2', '-', 'Stage 2 selesai; Stage 3 (Konfirmasi Supplier) terbuka', 'PurchasingStageService@approveStage2'),
        $e2e(45, 'TC-E2E-08-05', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 5, 'Purchasing', 'Di Stage 3 ajukan Revisi Komitmen harga & coba selesaikan Stage 3', 'Usulan harga baru', 'Stage 3 terblokir selama po_revisi berstatus menunggu', 'PRD v2.4 TC-34'),
        $e2e(46, 'TC-E2E-08-06', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 6, 'Manager', 'Approve Revisi Komitmen', '-', 'Nilai komitmen PO ter-update, po_log_perubahan tercatat, blokir Stage 3 terbuka', 'PRD v2.4 TC-35'),
        $e2e(47, 'TC-E2E-08-07', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 7, 'Purchasing', 'Selesaikan Stage 3 -> Stage 4 (Lanjut Tempo) -> Stage 5 -> Stage 6 (Koli & Berat) -> Stage 7 (Ekspedisi)', 'Koli 10, Berat 100kg, Ekspedisi JNE', 'Stage 3..7 selesai berurutan; Stage 8 (Unloading) terbuka', 'PRD v2.4 TC-07, TC-08, TC-28'),
        $e2e(48, 'TC-E2E-08-08', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 8, 'Gudang', 'Selesaikan Stage 8 (Unloading Fisik) lalu cek saldo stok sebelum Stage 9', 'qty_fisik = 5.000', 'barang_datang tercatat status_posting = belum; saldo STOK belum bertambah; Stage 9 terbuka', 'PRD v2.4 BR-08, TC-10'),
        $e2e(49, 'TC-E2E-08-09', 'E2E-08 PO 9-Stage, Revisi & Lead Time 1 Desimal (PRD v2.4)', 9, 'Gudang', 'Selesaikan Stage 9 (Input Sistem & Posting Stok)', 'Total durasi 1.512 menit', 'Stok bertambah 5.000; PO Selesai; PoLeadTime & SupplierProdukLtSummary mencatat 1.1 hari (NUMERIC(10,1) half-up); Analisa Stok ALK-01 otomatis memakai sumber_lt = otomatis', 'PRD v2.4 Override #1, BR-10, TC-01, TC-16'),

        // E2E-09 (PRD v2.4: Siklus Utuh /requests -> /transfers -> QC Invarian qty_baik + qty_rusak -> Defect Bulanan)
        $e2e(50, 'TC-E2E-09-01', 'E2E-09 Siklus /requests -> /transfers & Defect Bulanan (PRD v2.4)', 1, 'Operasional', 'Buat & Ajukan Permintaan Bahan di /requests dari Batch aktif', 'ALK-01 qty 2.000', 'StockRequest (REQ-YYYYMM-NNNN) berstatus diajukan', 'PRD v2.4 TC-17'),
        $e2e(51, 'TC-E2E-09-02', 'E2E-09 Siklus /requests -> /transfers & Defect Bulanan (PRD v2.4)', 2, 'Manager', 'Setujui Permintaan Bahan di /requests', '-', 'StockRequest berstatus disetujui', 'PRD v2.4 TC-18'),
        $e2e(52, 'TC-E2E-09-03', 'E2E-09 Siklus /requests -> /transfers & Defect Bulanan (PRD v2.4)', 3, 'Gudang', 'Klik Buat Transfer dari Request & Kirim Transfer (In Transit)', 'qty_dikirim = 2.000', 'Transfer (TRF-YYYYMM-NNNN) berstatus dikirim; StockRequest berstatus diproses; stok Gudang Pusat −2.000', 'PRD v2.4 TC-20, TC-21'),
        $e2e(53, 'TC-E2E-09-04', 'E2E-09 Siklus /requests -> /transfers & Defect Bulanan (PRD v2.4)', 4, 'Operasional', 'Coba terima dengan qty_baik 1.900 + qty_rusak 50 (total 1.950 != 2.000)', '1.900 + 50 != 2.000', 'Ditolak Invarian QC Gagal; stok belum berubah', 'PRD v2.4 Override #2'),
        $e2e(54, 'TC-E2E-09-05', 'E2E-09 Siklus /requests -> /transfers & Defect Bulanan (PRD v2.4)', 5, 'Operasional', 'Terima dengan qty_baik 1.950 + qty_rusak 50 = 2.000 & isi keterangan_rusak', '1.950 + 50 = 2.000', 'Transfer selesai; StockRequest otomatis selesai; stok Gudang Operasional bertambah HANYA 1.950', 'PRD v2.4 Override #2, TC-22, TC-24'),
        $e2e(55, 'TC-E2E-09-06', 'E2E-09 Siklus /requests -> /transfers & Defect Bulanan (PRD v2.4)', 6, 'Manager', 'Periksa Dashboard Defect Bulanan di /transfers & /laporan/defect', '-', 'Tercatat 50 unit rusak pada Gudang Operasional di bulan berjalan dengan defect_rate_percent = 2,50%', 'PRD v2.4 Override #2, TC-42'),
    ];

    // =========================================================================
    // TAB 11: Kalkulasi Analisa (Interactive Excel Calculator with Live Formulas)
    // =========================================================================
    $kalkulasiCols = [
        ['header' => 'Variabel / Parameter', 'width' => 38],
        ['header' => 'Nilai (Input / Rumus)', 'width' => 20],
        ['header' => 'Acuan Dokumen', 'width' => 18],
        ['header' => 'Rumus / Keterangan Matematis', 'width' => 54],
        ['header' => 'Cek', 'width' => 12],
    ];

    $calcIn = static fn (int|float|string $v): array => ['value' => $v, 'style' => XlsxWorkbookBuilder::STYLE_CALC_INPUT];
    $calcFm = static fn (string $f): array => ['formula' => $f, 'style' => XlsxWorkbookBuilder::STYLE_DATA_BOLD];
    $cekFm = static fn (int $r): array => ['formula' => "IF(ROUND(B{$r},2)=ROUND(C{$r},2),\"OK\",\"SELISIH\")", 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS];

    // Note on exact Excel row numbers in Tab 11:
    // Row 1 = Title, Row 2 = Subtitle, Row 3 = Header
    $kalkulasiRows = [
        // Row 4
        ['_section' => true, 'label' => 'A. PROFIL BAHAN LOKAL — ALK-01 (Menu Analisa Bab 2)'],
        // Row 5..14 (Inputs)
        ['Terjual rata-rata 4 bulan', $calcIn(39303.25), '', 'Input manual (Sumber: contoh PRD 8.6)', ''],
        ['Total Average Lead Time (hari)', $calcIn(10), '', 'Σ 9 tahap skenario average / empiris 1 desimal (contoh uji)', ''],
        ['Total Max Lead Time (hari)', $calcIn(14), '', 'Σ 9 tahap skenario max / empiris 1 desimal (contoh uji)', ''],
        ['Tambahan buffer (hari)', $calcIn(2), '', 'Input manual', ''],
        ['Review period (hari)', $calcIn(15), '', 'Default 15', ''],
        ['Stok saat ini', $calcIn(8000), '', 'Contoh PRD 8.6', ''],
        ['Akan datang (PO berjalan)', $calcIn(0), '', 'Σ qty PO status ≠ Selesai/Dibatalkan', ''],
        ['MOQ (satuan_order_moq)', $calcIn(5000), '', 'Master produk (contoh PRD 8.1)', ''],
        ['Faktor konversi', $calcIn(1000), '', '1 jerigen = 1000 ml', ''],
        ['Harga per ml/pcs', $calcIn(526), '', 'Contoh PRD 8.6', ''],
        // Row 15..25 (Formulas)
        ['Safety Stock (hari)', $calcFm('MAX(0,ROUND((B7-B6)+B8,1))'), 6, '(Max LT − Avg LT) + tambahan buffer', $cekFm(15)],
        ['ADU', $calcFm('B5/30'), 1310.11, 'Terjual ÷ 30', $cekFm(16)],
        ['Batas Minimum', $calcFm('ROUND(B16*(B6+B15),0)'), 20962, 'ADU × (Avg LT + Safety Stock)', $cekFm(17)],
        ['Target Stock', $calcFm('ROUND(B16*(B6+B15+B9),0)'), 40613, 'ADU × (Avg LT + Safety Stock + Review Period)', $cekFm(18)],
        ['Selisih (dokumen)', $calcFm('(B10+B11)-B18'), -32613, 'Menu Analisa 2.4: (stok + akan datang) − Target Stock', $cekFm(19)],
        ['Status (dokumen)', $calcFm('IF((B10+B11)<B18,"ORDER","TIDAK")'), 'ORDER', 'Order jika (stok + akan datang) < Target Stock', ['formula' => 'IF(B20=C20,"OK","SELISIH")', 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS]],
        ['Rumus MOQ', $calcFm('IF(B20="ORDER",CEILING(ABS(B19),B12),0)'), 35000, 'CEILING(|selisih|, MOQ)', $cekFm(21)],
        ['Rekomendasi Order (satuan beli)', $calcFm('ROUND(B21/B13,0)'), 35, 'Rumus MOQ ÷ faktor konversi', $cekFm(22)],
        ['Total Nominal Order', $calcFm('ROUND(B21*B14,0)'), 18410000, 'Rumus MOQ (ml) × harga per ml — lihat Gap G-02', $cekFm(23)],
        ['Pembanding: Selisih versi kode', $calcFm('(B10+B11)-B17'), -12962, 'Kode memakai Batas Minimum (AnalisaService:595) — lihat Gap G-01', $cekFm(24)],
        ['Pembanding: Rumus MOQ versi kode', $calcFm('CEILING(ABS(B24),B12)'), 15000, 'Bila hasil aplikasi = 15.000 → kode memakai Batas Minimum', $cekFm(25)],

        // Row 26
        ['_section' => true, 'label' => 'B. PROFIL BAHAN IMPOR — Botol 50ml / BTL-P50 (Addendum v2.2 Bab 6.3.5)'],
        // Row 27..36 (Inputs)
        ['Out 4 bulan (pcs)', $calcIn(47312), '', 'Input manual (01-01-2026 s/d 30-04-2026)', ''],
        ['Jumlah hari periode', $calcIn(120), 120, '(akhir − mulai) + 1 — FR-ANL-05 / DEV-01', $cekFm(28)],
        ['Lead Time Average (hari)', $calcIn(82), '', 'LEAD_TIME_IMPOR / Empiris 1 desimal', ''],
        ['Lead Time Max (hari)', $calcIn(114), '', 'LEAD_TIME_IMPOR / Empiris 1 desimal', ''],
        ['Review Period (hari)', $calcIn(30), '', 'Default 30', ''],
        ['Tambahan hari Klasifikasi ABC (Kelas A)', $calcIn(4), 4, 'WAJIB_A=4, A=4, B=2, C=0 — DEV-03', $cekFm(32)],
        ['Stok saat ini', $calcIn(1573), '', 'Input', ''],
        ['Inbound (before ETA)', $calcIn(10044), '', 'Input', ''],
        ['MOQ', $calcIn(1000), '', 'Master produk', ''],
        ['Harga per pcs (Rp)', $calcIn(1500), '', 'Input', ''],
        // Row 37..47 (Formulas)
        ['ADU Base', $calcFm('B27/B28'), 394.27, 'Out ÷ jumlah hari', $cekFm(37)],
        ['ADU ETA', $calcFm('ROUND(B37+(B29/B31),0)'), 397, 'ROUND(ADU Base + (Lead Time ÷ Review Period), 0) — DEV-02', $cekFm(38)],
        ['Buffer Days', $calcFm('(B30-B29)+B32'), 36, '(LT Max − LT Avg) + tambahan hari ABC', $cekFm(39)],
        ['Safety Stock', $calcFm('B38*B39'), 14292, 'ADU ETA × Buffer Days', $cekFm(40)],
        ['Minimum Stock', $calcFm('B38*B29'), 32554, 'ADU ETA × Lead Time', $cekFm(41)],
        ['Target Stock', $calcFm('B38*(B29+B39+B31)'), 58756, 'ADU ETA × (LT + Buffer Days + RP) — DEV-08', $cekFm(42)],
        ['Proyeksi', $calcFm('(B33+B34)-(B38*B29)'), -20937, 'Stok + Inbound − (ADU ETA × LT)', $cekFm(43)],
        ['Qty Order', $calcFm('B43-B42'), -79693, 'Proyeksi − Target (negatif = kekurangan)', $cekFm(44)],
        ['Status', $calcFm('IF(B44<0,"ORDER","TIDAK")'), 'ORDER', 'ORDER jika Qty Order < 0', ['formula' => 'IF(B45=C45,"OK","SELISIH")', 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS]],
        ['PO (Rekomendasi Order)', $calcFm('IF(B44<0,CEILING(ABS(B44),B35),0)'), 80000, 'CEILING(|Qty Order|, MOQ) — DEV-04/05', $cekFm(46)],
        ['Total Nominal Order (Rp)', $calcFm('B46*B36'), 120000000, 'Rekomendasi × harga', $cekFm(47)],

        // Row 48
        ['_section' => true, 'label' => 'C. PROFIL PRODUK JADI (FULFILLMENT) — GOH-P50 (PRD Bab 6.4)'],
        // Row 49..58
        ['MOQ Produk Jadi (satuan order)', $calcIn(108), '', 'Master produk (contoh Menu Analisa 1.3)', ''],
        ['Batas Minimum (Total FF Pusat + FF SBY)', $calcIn(12000), 12000, '9.000 (FF Pusat) + 3.000 (FF SBY)', $cekFm(50)],
        ['Target Stock (Total FF Pusat + FF SBY)', $calcIn(72000), 72000, '54.000 (FF Pusat) + 18.000 (FF SBY)', $cekFm(51)],
        ['Stok Saat Ini (Total FF Pusat + FF SBY)', $calcIn(6000), 6000, '4.000 (FF Pusat) + 2.000 (FF SBY)', $cekFm(52)],
        ['Akan Datang (level ALL — hanya FF Pusat)', $calcIn(10000), 10000, 'Hanya dari FF Pusat (gudang penerima langsung Operasional)', $cekFm(53)],
        ['Stok All', $calcFm('B52+B53'), 16000, 'Stok total + akan datang', $cekFm(54)],
        ['Status', $calcFm('IF(B54<=B51,"ORDER","TIDAK")'), 'ORDER', 'ORDER jika Stok All ≤ Target Total (sinyal produksi, bukan PO)', ['formula' => 'IF(B55=C55,"OK","SELISIH")', 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS]],
        ['Selisih', $calcFm('B54-B51'), -56000, 'Stok All − Target Total', $cekFm(56)],
        ['Qty Order Produksi', $calcFm('IF(B55="ORDER",CEILING(ABS(B56),B49),0)'), 56052, 'CEILING(|selisih|, MOQ)', $cekFm(57)],

        // Row 58
        ['_section' => true, 'label' => 'D. KALKULATOR LEAD TIME 9-STAGE & OVERRIDE TERKUNCI #1 (NUMERIC(10,1) HALF-UP, 1 HARI = 1.440 MENIT)'],
        // Row 59..64
        ['Kasus Uji 1: Durasi 1.485 Menit (< 5 pada digit ke-2)', $calcIn(1485), '', 'Input durasi dalam menit utuh (1.485 ÷ 1.440 = 1,03125 hari)', ''],
        ['Hasil Hari Kasus 1 (NUMERIC(10,1) Half-Up)', $calcFm('ROUND(B59/1440,1)'), 1.0, 'SupplierLeadTimeService::minutesToDays(1485) -> 1.0 hari', $cekFm(60)],
        ['Kasus Uji 2: Durasi 1.512 Menit (= 5 pada digit ke-2)', $calcIn(1512), '', 'Input durasi dalam menit utuh (1.512 ÷ 1.440 = 1,05000 hari)', ''],
        ['Hasil Hari Kasus 2 (NUMERIC(10,1) Half-Up)', $calcFm('ROUND(B61/1440,1)'), 1.1, 'SupplierLeadTimeService::minutesToDays(1512) -> 1.1 hari (+0.1)', $cekFm(62)],
        ['Kasus Uji 3: Durasi 720 Menit (Setengah Hari)', $calcIn(720), '', 'Input durasi dalam menit utuh (720 ÷ 1.440 = 0,50000 hari)', ''],
        ['Hasil Hari Kasus 3 (NUMERIC(10,1) Half-Up)', $calcFm('ROUND(B63/1440,1)'), 0.5, 'SupplierLeadTimeService::minutesToDays(720) -> 0.5 hari', $cekFm(64)],

        // Row 65
        ['_section' => true, 'label' => 'E. KALKULATOR INVARIAN QC TRANSFER & DEFECT BULANAN (OVERRIDE TERKUNCI #2)'],
        // Row 66..71
        ['Qty Dikirim (Surat Jalan Transfer)', $calcIn(2000), '', 'Kuantitas fisik yang dikirim dari gudang asal', ''],
        ['Qty Baik (Diterima Bagus di Gudang Tujuan)', $calcIn(1950), '', 'Hanya Qty Baik yang masuk ke saldo STOK gudang tujuan', ''],
        ['Qty Rusak (Ditemukan Saat QC Penerimaan)', $calcIn(50), '', 'Wajib disertai keterangan_rusak bila > 0', ''],
        ['Validasi Invarian Anti-Fraud (qty_baik + qty_rusak = qty_dikirim)', $calcFm('IF((B67+B68)=B66,"VALID","REJECTED")'), 'VALID', 'TransferService::receive memblokir jika B67 + B68 != B66', ['formula' => 'IF(B69=C69,"OK","SELISIH")', 'style' => XlsxWorkbookBuilder::STYLE_STATUS_PASS]],
        ['Penambahan Saldo STOK Gudang Tujuan', $calcFm('IF(B69="VALID",B67,0)'), 1950, 'Hanya qty_baik (1.950) yang menambah STOK tujuan', $cekFm(70)],
        ['Defect Rate Bulanan (%)', $calcFm('ROUND((B68/B66)*100,2)'), 2.50, 'ROUND((qty_rusak ÷ qty_dikirim) × 100, 2) — tampil di Dashboard Defect', $cekFm(71)],
    ];

    // =========================================================================
    // TAB 12: Gap Kode vs Dokumen (10 Columns)
    // =========================================================================
    $gapCols = [
        ['header' => 'ID', 'width' => 8],
        ['header' => 'Area', 'width' => 20],
        ['header' => 'Ketentuan Dokumen', 'width' => 42],
        ['header' => 'Implementasi Kode Saat Ini', 'width' => 44],
        ['header' => 'Lokasi Kode', 'width' => 34],
        ['header' => 'Dampak', 'width' => 34],
        ['header' => 'Rekomendasi', 'width' => 36],
        ['header' => 'TC Terkait', 'width' => 20],
        ['header' => 'Keputusan', 'width' => 16],
        ['header' => 'PIC', 'width' => 14],
    ];

    $gapRows = [
        ['G-01', 'Analisa Lokal', 'Selisih = (stok + akan datang) − Target Stock; ORDER jika (stok + akan datang) < Target Stock (Menu Analisa 2.4)', 'Selisih & status dihitung terhadap Batas Minimum dengan operator ≤ ($tersedia <= $batasMinimum)', 'app/Services/AnalisaService.php:594-595', 'Qty order lebih kecil dari seharusnya (contoh ALK-01: 15.000 vs 35.000)', 'Selaraskan ke Target Stock atau revisi dokumen (PRD v2.0 6.2 masih memakai Batas Minimum)', 'TC-PUR-032; Kalkulasi A', '', ''],
        ['G-02', 'Analisa Lokal', 'Total Nominal Order = rekomendasi_order × harga_ml_pcs', 'Total nominal = rumus_moq (satuan dasar) × harga', 'app/Services/AnalisaService.php:605', 'Rumus dokumen akan menghasilkan nominal 1/1000 bila harga per ml', 'Perbaiki dokumen: rumus_moq × harga per satuan dasar', 'Kalkulasi A', '', ''],
        ['G-03', 'Purchasing (Legacy vs 9-Stage)', 'PRD v2.4 menerapkan 9-Stage Pipeline penuh (Stage 1..9); PRD v2.0 memakai Diajukan → Disetujui → Dikirim ke Gudang', 'Terdapat 2 jalur di kode: endpoint legacy (/approve, /receive) dan endpoint 9-Stage (/stages/{stage}/complete)', 'app/Http/Controllers/PurchasingController.php & PurchasingStageController.php', 'Jika user memakai tombol approve legacy, stage 2..7 dapat terlewati', 'Arahkan seluruh tombol UI detail PO ke endpoint PurchasingStageController', 'TC-MGR-004; TC-MGR-040', '', ''],
        ['G-04', 'Analisa Impor', 'Simulasi = Generate (FR-ANL-08) memakai satu fungsi hitung', 'generateImpor() memakai floor() untuk Target & ADU tidak dibulatkan; impor() membulatkan ADU Base/ETA dengan round()', 'app/Services/AnalisaService.php', 'Angka simulasi & hasil generate bisa beda ±1 atau lebih', 'Satukan ke satu fungsi kalkulasi', 'TC-PUR-042', '', ''],
        ['G-05', 'Analisa Impor', 'Tidak ada konstanta hari tetap (FR-ANL-05)', 'impor() masih fallback 122 hari bila jumlah_hari_periode kosong', 'app/Services/AnalisaService.php', 'Hasil salah bila periode belum diisi', 'Wajibkan periode atau hitung default 4 bulan riil', 'TC-PUR-038', '', ''],
        ['G-06', 'Analisa Impor', 'Tiap varian = SKU terpisah (DEV-07); ADU ETA = ADU Base + LT ÷ RP', 'Jalur varian mengalikan (LT ÷ RP) dengan persentase distribusi', 'app/Services/AnalisaService.php', 'ADU ETA per varian tidak sesuai rumus', 'Hapus jalur varian atau samakan rumus', 'TC-PUR-037', '', ''],
        ['G-07', 'Analisa Impor', 'Validasi periode akhir >= periode mulai', 'periode_akhir perlu divalidasi after_or_equal:periode_mulai', 'app/Http/Controllers/AnalisaController.php', 'Jumlah hari bisa ≤ 0 jika input terbalik', 'Pastikan rule after_or_equal:periode_mulai aktif', 'TC-PUR-040', '', ''],
        ['G-08', 'Riwayat Analisa', 'Riwayat menyimpan Batas Minimum & Target (FR-ANL-04)', 'Endpoint /analisa/snapshot menyimpan batas_minimum = 0 untuk profil impor, sedangkan /analisa/finalisasi menyimpan lengkap', 'app/Http/Controllers/AnalisaController.php', 'Jejak audit impor bisa tidak lengkap bila memakai endpoint snapshot lama', 'Gunakan /analisa/finalisasi sebagai satu-satunya penulis riwayat', 'TC-PUR-047', '', ''],
        ['G-09', 'Riwayat Analisa', 'Alur tunggal: Generate → Checking → Finalisasi', 'Masih ada 2 route aktif: POST /analisa/snapshot dan POST /analisa/finalisasi', 'routes/web.php:247-250', 'Isi riwayat bisa berbeda tergantung tombol yang dipakai', 'Deprecate route /analisa/snapshot dan pertahankan /analisa/finalisasi', 'TC-PUR-047', '', ''],
        ['G-10', 'Stok & Mutasi', 'Mutasi keluar customer dengan kategori terpisah Affiliate/KOL (PRD 3.4)', 'Form mutasi hanya punya tipe in/out + catatan, belum ada dropdown kategori mutasi', 'app/Http/Controllers/StokController.php', 'Tidak bisa memisahkan Affiliate/KOL untuk eksklusi ADU secara otomatis', 'Tambah field kategori mutasi jika dikonfirmasi pemilik proses', 'TC-FUL-017', '', ''],
        ['G-11', 'Hak Akses Analisa', 'Fulfillment hanya mengelola analisa produk jadi (PRD Bab 2)', 'Fulfillment memiliki permission analisa.manage sehingga dapat memicu generate Lokal/Impor & CRUD Klasifikasi ABC', 'database/seeders/PermissionSeeder.php:129', 'Fulfillment dapat mengubah parameter bahan baku', 'Konfirmasi; bila tidak boleh, pisahkan permission analisa.manage.fulfillment', 'TC-FUL-015', '', ''],
        ['G-12', 'Hak Akses Purchasing', 'Matriks menu PRD v2.0 Bab 4: Gudang tidak mengakses menu Purchasing', 'Di PRD v2.4 & kode saat ini Gudang memiliki purchasing.view & purchasing.receive (Stage 8 Unloading & Stage 9 Input Sistem tanpa melihat harga)', 'database/seeders/PermissionSeeder.php:96', 'Sudah sesuai PRD v2.4 (privasi harga dijaga oleh purchasing.price.view)', 'Dokumentasi PRD v2.4 sudah selaras; pastikan matriks lama diperbarui', 'TC-GDG-002; TC-GDG-035', '', ''],
        ['G-13', 'Pembayaran PO', 'Skema Tempo / Termin / Pelunasan (FR-PUR-04)', "Kode mendukung skema 'cash' (wajib melunasi penuh) selain tempo & termin", 'app/Services/Purchasing/PurchasingPaymentService.php', 'Fitur tambahan positif di kode', 'Pertahankan di kode & dokumentasi', 'TC-PUR-021', '', ''],
        ['G-14', 'Request & Transfer (Dual Route)', 'PRD v2.4 memisahkan entitas Permintaan (/requests) dan Pengiriman (/transfers)', 'Kode mempertahankan route legacy /request-transfer sekaligus menyediakan route baru /requests dan /transfers', 'routes/web.php:170-228', 'Kedua modul menggunakan tabel berbeda (request_transfers vs requests + transfers)', 'Pastikan navigasi utama mengarah ke /requests & /transfers sesuai PRD v2.4', 'TC-OPS-042; TC-GDG-038', '', ''],
        ['G-15', 'Middleware Route /requests & /transfers', 'PermissionSeeder mendefinisikan permission baru request.* dan transfer.*', 'Route group /requests dan /transfers di routes/web.php masih memakai middleware can:rt.view, can:rt.create, can:rt.ship, can:rt.receive', 'routes/web.php:189-228', 'Jika admin membuat custom role hanya dengan centang request.view / transfer.view tanpa rt.view, akses akan 403', 'Selaraskan middleware di routes/web.php agar menerima request.* / transfer.* atau gabungkan di PermissionCatalogService', 'TC-MGR-035; Matriks Akses', '', ''],
    ];

    return [
        [
            'name' => 'TC-E2E',
            'columns' => $e2eCols,
            'rows' => $e2eRows,
            'options' => [
                'title' => 'TEST CASE — END-TO-END LINTAS ROLE (SIKLUS BISNIS UTUH HULU KE HILIR)',
                'subtitle' => 'Jalankan berurutan per alur setelah TC per role lulus; jika satu langkah gagal maka alur dinyatakan Fail.',
                'freezeRow' => 3,
                'sheetType' => 'e2e_tc',
                'isTestCaseSheet' => true,
            ],
        ],
        [
            'name' => 'Kalkulasi Analisa',
            'columns' => $kalkulasiCols,
            'rows' => $kalkulasiRows,
            'options' => [
                'title' => 'KALKULATOR VERIFIKASI RUMUS ANALISA STOK, LEAD TIME 1 DESIMAL & DEFECT BULANAN',
                'subtitle' => 'Sel biru berlatar kuning = input yang boleh diubah; sel tebal = rumus Excel otomatis. Kolom Cek = OK bila hasil rumus sama dengan angka acuan dokumen.',
                'freezeRow' => 3,
                'autoFilter' => 'A3:E71',
            ],
        ],
        [
            'name' => 'Gap Kode vs Dokumen',
            'columns' => $gapCols,
            'rows' => $gapRows,
            'options' => [
                'title' => 'TEMUAN PERBEDAAN IMPLEMENTASI KODE VS DOKUMEN (TRACEABILITY & DISCREPANCIES)',
                'subtitle' => 'Hasil review statis kode terhadap PRD v2.4 & dokumen Analisa; verifikasi saat eksekusi TC terkait lalu putuskan: perbaiki kode atau revisi dokumen.',
                'freezeRow' => 3,
                'autoFilter' => 'A3:J18',
            ],
        ],
    ];
}
