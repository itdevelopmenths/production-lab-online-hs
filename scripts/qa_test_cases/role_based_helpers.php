<?php

declare(strict_types=1);

/**
 * 18 Standard Columns for Role-Based Test Case Tabs (TC-Umum, TC-Purchasing, TC-Manager, TC-Gudang, TC-Operasional, TC-Fulfillment):
 * A: No
 * B: TC ID
 * C: Role
 * D: Modul
 * E: Fitur
 * F: Skenario
 * G: Tipe (Positif / Negatif / Akses / Keamanan / Kalkulasi)
 * H: Prioritas (Critical / High / Medium / Low)
 * I: Prasyarat
 * J: Langkah Pengujian
 * K: Data Uji
 * L: Hasil yang Diharapkan
 * M: Referensi (FR / Kode)
 * N: Hasil Aktual (Yellow Tester Input)
 * O: Status (Yellow Tester Input Dropdown: Not Run, Pass, Fail, Blocked, N/A)
 * P: Tester (Yellow Tester Input)
 * Q: Tgl Uji (Yellow Tester Input)
 * R: Catatan / Bug ID (Yellow Tester Input)
 *
 * @return array<int, array{header: string, width: int|float}>
 */
function getRoleTcColumns18(): array
{
    return [
        ['header' => 'No', 'width' => 6],
        ['header' => 'TC ID', 'width' => 15],
        ['header' => 'Role', 'width' => 15],
        ['header' => 'Modul', 'width' => 22],
        ['header' => 'Fitur', 'width' => 22],
        ['header' => 'Skenario', 'width' => 36],
        ['header' => 'Tipe', 'width' => 13],
        ['header' => 'Prioritas', 'width' => 12],
        ['header' => 'Prasyarat', 'width' => 32],
        ['header' => 'Langkah Pengujian', 'width' => 42],
        ['header' => 'Data Uji', 'width' => 28],
        ['header' => 'Hasil yang Diharapkan', 'width' => 46],
        ['header' => 'Referensi (FR / Kode)', 'width' => 28],
        ['header' => 'Hasil Aktual', 'width' => 28],
        ['header' => 'Status', 'width' => 14],
        ['header' => 'Tester', 'width' => 12],
        ['header' => 'Tgl Uji', 'width' => 14],
        ['header' => 'Catatan / Bug ID', 'width' => 28],
    ];
}

/**
 * Helper to create an 18-column Role Test Case row.
 *
 * @return array<int, mixed>
 */
function rtc(
    int $no,
    string $tcId,
    string $role,
    string $modul,
    string $fitur,
    string $skenario,
    string $tipe,
    string $prioritas,
    string $prasyarat,
    string $langkah,
    string $dataUji,
    string $expected,
    string $referensi,
    string $actual = '',
    string $status = 'Not Run',
    string $tester = '',
    string $tglUji = '',
    string $catatan = ''
): array {
    return [
        $no,
        $tcId,
        $role,
        $modul,
        $fitur,
        $skenario,
        $tipe,
        $prioritas,
        $prasyarat,
        $langkah,
        $dataUji,
        $expected,
        $referensi,
        $actual,
        $status,
        $tester,
        $tglUji,
        $catatan,
    ];
}
