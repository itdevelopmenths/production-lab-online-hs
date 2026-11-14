# Master Klasifikasi ABC, Dynamic ADU Engine, & Analisa Impor Revamp (PRD v2.2)
## Enterprise Sprint Planning & Architectural Specification

- **File Path:** `.agent/sprint-planning-analisa-impor-v2-2.md`
- **Created Date:** 2026-10-05
- **Standard Reference:** `.agent/enterprise-task-framework.md`
- **Baseline Document:** PRD SC Online Addendum Pembaruan Terbaru (v2.2) — 5 Oktober 2026
- **Status:** 🚀 In Progress (Fase 1 - 3 Completed, Ready for Fase 4 Execution)
- **Architect & Lead:** Senior Software Architect & Enterprise Project Lead

---

## 1. Executive Summary & Root Cause Audit (DEV-01 s/d DEV-08)

Dokumen ini menjadi cetak biru teknis (*blueprint*) perbaikan fundamental terhadap modul **Analisa Stok Impor** dan standarisasi **Master Klasifikasi ABC** sesuai adendum PRD SC Online v2.2.

Berdasarkan audit langsung pada source code aktif per 5 Oktober 2026, teridentifikasi bahwa ke-8 temuan developer (DEV-01 s/d DEV-08) adalah **100% valid** sebagai cacat logika dan deviasi sistem:

1. **DEV-01 (Pembagi ADU Statis)**:
   - Frontend membagi `/ 120` di `modal-manual-impor.blade.php:67` dan `index.blade.php:917`.
   - Backend membagi `/ 122` di `AnalisaService.php:276, 397, 444`.
   - *Target v2.2*: Pembagi dihitung dinamis dari rentang `periode_mulai` s/d `periode_akhir` ($N$ hari kalender inklusif).
2. **DEV-02 (ADU ETA Tidak Diimplementasikan di Simulasi Modal)**:
   - Javascript modal menghitung Safety Stock & Target langsung dari `ADU Base`, bukan `ADU ETA`.
   - *Target v2.2*: $\text{ADU ETA} = \text{ADU Base} + (\text{Lead Time} / \text{Review Period})$. Menjadi variabel acuan di frontend dan backend.
3. **DEV-03 (Dropdown Klasifikasi ABC Tidak Berpengaruh di Simulasi)**:
   - Javascript modal mengabaikan `klasifikasi_abc` (`bufferDays = maxLt - avgLt`).
   - *Target v2.2*: Relasi dinamis ke tabel master `klasifikasi_abc`, membaca kolom `tambahan_buffer_hari`.
4. **DEV-04 (Rekomendasi Order Melompati Logika Proyeksi)**:
   - Modal menghitung $\max(0, \text{Target} - \text{Stok} - \text{Inbound})$ yang melompati $\text{Proyeksi} = \text{Stok} + \text{Inbound} - \text{Minimum Stock}$.
   - *Target v2.2*: Standarisasi formula proyeksi & selisih.
5. **DEV-05 (Rekomendasi Order Tanpa Pembulatan MOQ)**:
   - Modal menampilkan nilai float (misal 45.157,40 pcs).
   - *Target v2.2*: Pembulatan ke atas dengan kelipatan MOQ ($\lceil \text{Kebutuhan} / \text{MOQ} \rceil \times \text{MOQ}$).
6. **DEV-06 (Data Varian Bernilai 0 di Modal Distribusi)**:
   - Modal membaca properti yang salah (`v.adu` vs `v.adu_base`) dan stok fisik varian tidak tersinkron.
   - *Target v2.2*: Sinkronisasi skema data varian.
7. **DEV-07 (Deviasi Arsitektur Multi-Varian)**:
   - Standarisasi penanganan baris analitik varian dengan sinkronisasi agregasi induk.
8. **DEV-08 (Koreksi Rumus Target Stock Satuan Hari vs Qty)**:
   - Target Stock = Minimum Stock + Safety Stock + Review Stock = $\text{ADU ETA} \times (\text{Lead Time} + \text{Buffer Days} + \text{Review Period})$.

---

## 2. Benchmark Kasus Uji E2E: Botol 50ml (PRD v2.2 Bab 6.3.5)

| Parameter | Nilai Uji PRD | Keterangan |
| :--- | :--- | :--- |
| **Periode OUT** | 01/10/2024 s/d 31/01/2025 | $31 + 30 + 31 + 31 = 123\text{ hari}$ |
| **Total OUT** | $55.099\text{ pcs}$ | Pengeluaran riil 4 bulan |
| **ADU Base** | $\mathbf{447,96}\text{ pcs/hari}$ | $55.099 / 123 = 447,9593$ |
| **Lead Time Average** | $60\text{ hari}$ | Lead time rata-rata supplier impor |
| **Lead Time Max** | $92\text{ hari}$ | Lead time skenario terburuk |
| **Klasifikasi ABC** | $\text{A}$ ($+4\text{ hari}$) | Mengambil dari Master Klasifikasi ABC |
| **Buffer Days** | $\mathbf{36}\text{ hari}$ | $(92 - 60) + 4 = 36$ |
| **Review Period** | $30\text{ hari}$ | Siklus evaluasi purchasing bulanan |
| **ADU ETA** | $\mathbf{449,96}\text{ pcs/hari}$ | $447,96 + (60 / 30) = 447,96 + 2,00$ |
| **Safety Stock** | $\mathbf{16.198,56}\text{ pcs}$ | $449,96 \times 36$ |
| **Minimum Stock** | $\mathbf{26.997,60}\text{ pcs}$ | $449,96 \times 60$ |
| **Target Stock** | $\mathbf{56.694,96}\text{ pcs}$ | $26.997,60 + 16.198,56 + (449,96 \times 30)$ |
| **Stok Saat Ini** | $1.200\text{ pcs}$ | Saldo gudang aktif |
| **Inbound Before ETA** | $0\text{ pcs}$ | PO dalam perjalanan |
| **Proyeksi** | $\mathbf{-25.797,60}\text{ pcs}$ | $1.200 + 0 - 26.997,60$ |
| **Selisih** | $\mathbf{-82.492,56}\text{ pcs}$ | $-25.797,60 - 56.694,96$ |
| **Kebutuhan PO** | $\mathbf{82.492,56}\text{ pcs}$ | $|\text{Selisih}|$ |
| **MOQ** | $10.000\text{ pcs}$ | Ketentuan minimum order supplier |
| **Rekomendasi PO (Bulat)**| $\mathbf{90.000}\text{ pcs}$ | $\lceil 82.492,56 / 10.000 \rceil \times 10.000 = 9 \times 10.000$ |

---

## 3. Database Schema & Data Modeling

### 3.1 Tabel Baru: `klasifikasi_abc`
```sql
CREATE TABLE klasifikasi_abc (
    id BIGSERIAL PRIMARY KEY,
    kode VARCHAR(20) NOT NULL UNIQUE,       -- 'wajib_a', 'a', 'b', 'c'
    nama VARCHAR(100) NOT NULL,              -- 'Wajib A (Buffer +4)', 'A (Fast Moving)', dll.
    tambahan_buffer_hari INTEGER NOT NULL DEFAULT 0,
    deskripsi TEXT NULL,
    warna_badge VARCHAR(30) NOT NULL DEFAULT 'gray', -- 'red', 'amber', 'blue', 'gray'
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 3.2 Modifikasi Tabel `analisa_impor`
```sql
ALTER TABLE analisa_impor
    ADD COLUMN periode_mulai DATE NULL,
    ADD COLUMN periode_akhir DATE NULL,
    ADD COLUMN jumlah_hari_periode INTEGER NOT NULL DEFAULT 120,
    ADD COLUMN klasifikasi_abc_id BIGINT NULL REFERENCES klasifikasi_abc(id) ON DELETE SET NULL;
```

### 3.3 Modifikasi Tabel `analisa_impor_meta`
```sql
ALTER TABLE analisa_impor_meta
    ADD COLUMN periode_mulai DATE NULL,
    ADD COLUMN periode_akhir DATE NULL,
    ADD COLUMN jumlah_hari_periode INTEGER NOT NULL DEFAULT 120,
    ADD COLUMN klasifikasi_abc_id BIGINT NULL REFERENCES klasifikasi_abc(id) ON DELETE SET NULL;
```

---

## 4. Layer Architecture & Core Service Logic

### 4.1 Master Data CRUD: `KlasifikasiAbcController` & `KlasifikasiAbc` Model
- Endpoint master data terintegrasi ke navigasi admin/purchasing.
- Seeder otomatis mengisi nilai standar:
  - `wajib_a`: Buffer +4 hari, badge `red`
  - `a`: Buffer +4 hari, badge `amber`
  - `b`: Buffer +2 hari, badge `blue`
  - `c`: Buffer +0 hari, badge `gray`

### 4.2 Engine Kalkulasi: `AnalisaService::generateImpor()` & `impor()`
- Penghitungan hari kalender riil:
  `$jumlahHari = Carbon::parse($mulai)->diffInDays(Carbon::parse($akhir)) + 1;`
- Mengambil `tambahan_buffer_hari` dari relasi master `klasifikasi_abc`.
- Menghitung parameter berurutan: `ADU Base` $\rightarrow$ `ADU ETA` $\rightarrow$ `Buffer Days` $\rightarrow$ `Safety Stock` $\rightarrow$ `Minimum Stock` $\rightarrow$ `Target Stock` $\rightarrow$ `Proyeksi` $\rightarrow$ `Selisih` $\rightarrow$ `Rekomendasi PO (Bulat MOQ)`.

### 4.3 Sinkronisasi Frontend Simulasi Modal: `modal-manual-impor.blade.php` & `index.blade.php`
- Menghadirkan date picker `periode_mulai` dan `periode_akhir`.
- Menghitung hari dinamis di Javascript Alpine.js:
  `const diffDays = Math.round((new Date(end) - new Date(start)) / 86400000) + 1;`
- Dropdown membaca master Klasifikasi ABC via API / Blade passing data.
- Menghitung `aduEta`, `safetyStock`, `minStock`, `targetStock`, `proyeksi`, dan pembulatan `moq` secara live 100% konsisten dengan backend.
- Memperbaiki modal varian (`modal-varian.blade.php`) agar menampilkan properti `adu_base`, `adu_eta`, dan saldo stok yang benar.

---

## 5. Work Breakdown Structure (WBS) & Sprint Tasks

1. **Sprint Task 1: Migration & Seeder Master Data**
   - Migration `create_klasifikasi_abc_table`.
   - Migration `add_periode_and_abc_to_analisa_impor_tables`.
   - Seeder `KlasifikasiAbcSeeder` dengan default standard + relasi backfill.
2. **Sprint Task 2: Master Klasifikasi ABC CRUD & API**
   - Model `KlasifikasiAbc` dengan relationship ke `AnalisaImpor`.
   - Controller & Routes untuk manajemen Klasifikasi ABC.
3. **Sprint Task 3: Revamp Engine Kalkulasi Backend (`AnalisaService.php`)**
   - Implementasi perhitungan dinamis `periode_mulai` dan `periode_akhir`.
   - Standarisasi formula `ADU ETA`, `Safety Stock`, `Min Stock`, `Target Stock`, `Proyeksi`, dan `MOQ`.
4. **Sprint Task 4: Revamp Frontend Live Simulation & Modals**
   - Update `modal-manual-impor.blade.php` (date input, ABC select).
   - Update Alpine.js reactive simulation functions di `index.blade.php`.
   - Update `table-impor.blade.php` untuk menampilkan rounded integer & badge master ABC.
   - Fix `modal-varian.blade.php` data binding.
5. **Sprint Task 5: Zero-Defect Testing & Verification**
   - Feature Test `AnalisaImporEngineTest.php` memverifikasi angka pasti Botol 50ml (Bab 6.3.5).
   - Verifikasi migrasi, rollback safety, dan backward compatibility.
