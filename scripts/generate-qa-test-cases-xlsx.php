<?php

declare(strict_types=1);

/**
 * Master Generator & Automated Verifier for:
 * - QA_Test_Cases_SC_Online_PRD_v2.4.xlsx
 * - docs/qa/QA_Test_Cases_SC_Online_PRD_v2.4.xlsx
 *
 * Architecture: 12-Tab Role-Based & UAT Suite (Senior QA Lead / Test Architect Standard)
 * 1. Ringkasan (Executive Dashboard with live COUNTIF/SUM formulas)
 * 2. Petunjuk (Testing Guide, Seeder Accounts, Execution Sequence, Locked Overrides)
 * 3. Matriks Akses (64 RBAC Permission Matrix rows)
 * 4. TC-Umum (Semua Role — 15 TCs)
 * 5. TC-Purchasing (Role Purchasing — 85 TCs)
 * 6. TC-Manager (Role Manager — 52 TCs)
 * 7. TC-Gudang (Role Gudang — 48 TCs)
 * 8. TC-Operasional (Role Operasional — 52 TCs)
 * 9. TC-Fulfillment (Role Fulfillment — 32 TCs)
 * 10. TC-E2E (Lintas Role End-to-End — 55 Steps across 9 Flows)
 * 11. Kalkulasi Analisa (Interactive Excel Calculator with live formulas & Cek column)
 * 12. Gap Kode vs Dokumen (15 Discrepancy & Traceability items G-01..G-15)
 */

require_once __DIR__ . '/lib/XlsxWorkbookBuilder.php';
require_once __DIR__ . '/qa_test_cases/tabs_01_03_ringkasan_petunjuk_matriks.php';
require_once __DIR__ . '/qa_test_cases/tabs_04_06_umum_purchasing_manager.php';
require_once __DIR__ . '/qa_test_cases/tabs_07_09_gudang_operasional_fulfillment.php';
require_once __DIR__ . '/qa_test_cases/tabs_10_12_e2e_kalkulasi_gap.php';

use App\QaExcel\XlsxWorkbookBuilder;

$builder = new XlsxWorkbookBuilder();

$allSheets = array_merge(
    getTabsRingkasanPetunjukMatriks(),
    getTabsUmumPurchasingManager(),
    getTabsGudangOperasionalFulfillment(),
    getTabsE2eKalkulasiGap()
);

$totalTestCases = 0;
$totalRbacItems = 0;
$sheetStats = [];

foreach ($allSheets as $sheet) {
    $builder->addSheet(
        $sheet['name'],
        $sheet['columns'],
        $sheet['rows'],
        $sheet['options'] ?? []
    );

    $tcCount = 0;
    foreach ($sheet['rows'] as $row) {
        if (isset($row[1]) && is_string($row[1]) && str_starts_with($row[1], 'TC-')) {
            $tcCount++;
        } elseif ($sheet['name'] === 'Matriks Akses' && isset($row[2]) && is_string($row[2]) && $row[2] !== '') {
            $tcCount++;
            $totalRbacItems++;
        }
    }
    $totalTestCases += $tcCount;
    $sheetStats[] = [
        'name' => $sheet['name'],
        'rows' => count($sheet['rows']),
        'tc_count' => $tcCount,
    ];
}

$rootOutput = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'QA_Test_Cases_SC_Online_PRD_v2.4.xlsx';
$docsOutput = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'qa' . DIRECTORY_SEPARATOR . 'QA_Test_Cases_SC_Online_PRD_v2.4.xlsx';

$builder->save($rootOutput);
$builder->save($docsOutput);

// =========================================================================
// AUTOMATED VERIFICATION GATE (STLC VERIFICATION)
// =========================================================================
foreach ([$rootOutput, $docsOutput] as $path) {
    if (!file_exists($path) || filesize($path) < 10000) {
        fwrite(STDERR, "ERROR: File not created or too small: {$path}\n");
        exit(1);
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        fwrite(STDERR, "ERROR: Cannot open ZIP archive: {$path}\n");
        exit(1);
    }

    $requiredEntries = [
        '[Content_Types].xml',
        '_rels/.rels',
        'xl/workbook.xml',
        'xl/_rels/workbook.xml.rels',
        'xl/styles.xml',
    ];
    for ($i = 1; $i <= count($allSheets); $i++) {
        $requiredEntries[] = "xl/worksheets/sheet{$i}.xml";
    }

    foreach ($requiredEntries as $entry) {
        $xmlContent = $zip->getFromName($entry);
        if ($xmlContent === false) {
            fwrite(STDERR, "ERROR: Missing OOXML entry {$entry} in {$path}\n");
            exit(1);
        }
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($xmlContent)) {
            $errors = libxml_get_errors();
            fwrite(STDERR, "ERROR: Invalid XML in {$entry}: " . ($errors[0]->message ?? 'Unknown XML error') . "\n");
            exit(1);
        }
        libxml_clear_errors();
    }

    $allSheetXml = '';
    for ($i = 1; $i <= count($allSheets); $i++) {
        $sheetXml = (string) $zip->getFromName("xl/worksheets/sheet{$i}.xml");
        $allSheetXml .= $sheetXml;
        // Sheets 3..10 are Matriks Akses, TC-Umum, TC-Purchasing, TC-Manager, TC-Gudang, TC-Operasional, TC-Fulfillment, TC-E2E
        if ($i >= 3 && $i <= 10) {
            foreach (['state="frozen"', '<autoFilter', '<dataValidations', '<conditionalFormatting'] as $feature) {
                if (!str_contains($sheetXml, $feature)) {
                    fwrite(STDERR, "ERROR: Missing Excel feature {$feature} in sheet{$i}.xml\n");
                    exit(1);
                }
            }
        }
    }

    foreach (['NUMERIC(10,1)', 'qty_dikirim = qty_baik + qty_rusak', 'COUNTIF', 'CEILING'] as $token) {
        if (!str_contains($allSheetXml, $token)) {
            fwrite(STDERR, "ERROR: Missing required PRD v2.4 / Formula token: {$token}\n");
            exit(1);
        }
    }

    $zip->close();
}

echo "=========================================================================\n";
echo "QA & UAT EXCEL WORKBOOK (ROLE-BASED + PRD v2.4) GENERATED & VERIFIED OK\n";
echo "=========================================================================\n";
echo "Primary File : {$rootOutput} (" . number_format((float) filesize($rootOutput)) . " bytes)\n";
echo "Docs Archive : {$docsOutput} (" . number_format((float) filesize($docsOutput)) . " bytes)\n";
echo "Total Sheets : " . count($allSheets) . " Worksheets\n";
echo "Total Items  : {$totalTestCases} Total Test Items (" . ($totalTestCases - $totalRbacItems) . " TCs + {$totalRbacItems} RBAC Matrix Rows)\n";
echo "-------------------------------------------------------------------------\n";
foreach ($sheetStats as $idx => $st) {
    $num = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
    $name = str_pad($st['name'], 22);
    echo "Tab {$num} | {$name} | Rows: " . str_pad((string) $st['rows'], 4) . " | Test Items: {$st['tc_count']}\n";
}
echo "=========================================================================\n";
