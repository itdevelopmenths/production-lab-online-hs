# Batch Alokasi Selesai & Kesiapan Bahan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (chosen inline execution) to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mencegah alokasi bahan berstatus `dilepas`/`dibatalkan` memicu alert `Stok Kurang` pada card **Kesiapan Bahan**, serta mengubah tabel **Alokasi Bahan Baku (BOM Explode)** khusus saat batch berstatus `selesai` menjadi tampilan realisasi historis (`Di-Issue (BOM)`, `Saldo Pasca-Issue`, `Waktu Dilepas`, `Aktual Opname`, `Dilepas · Selesai`).

**Architecture:** [`BatchController::show()`](app/Http/Controllers/BatchController.php#L190-L297) hanya menghitung pemicu `$hasDeficitFisik` dan `$hasDeficitRencana` pada alokasi `'aktif'` di batch non-final, membentuk `$readinessSummary` berbasis status siklus batch (`rencana`, `release`, `selesai`, `dibatalkan`), dan saat `$batch->status === 'selesai'` mengambil log `out` `KartuStok` (`referensi_tipe = 'batch_produksi'`) serta menggabungkannya dengan `$batch->opname` ke `$selesaiAudits`. View [`batches/show.blade.php`](resources/views/batches/show.blade.php) me-render `$readinessSummary` pada KPI card `Kesiapan Bahan` dan merender kolom audit realisasi hanya ketika `$batch->status === 'selesai'`.

**Tech Stack:** Laravel 12, PHP 8.x, Blade, Tailwind CSS v4, PHPUnit (`RefreshDatabase` + `Model::preventLazyLoading(true)`).

**Spec:** `docs/superpowers/specs/2026-11-11-batch-alokasi-kesiapan-selesai-design.md`

## Global Constraints
- Patuhi `Model::preventLazyLoading(true)` di `ProductionFlowTest` dan `ViewRenderingSafetyTest` (tidak memicu lazy loading pada `KartuStok` atau relasi baru di view).
- Gunakan `\App\Helpers\NumberHelper::formatQty()` untuk seluruh angka kuantitas bahan dan saldo agar pemformatan konsisten dengan view eksisting.
- Jika `KartuStok` `out` tidak ditemukan pada batch `selesai` (misal data seeding langsung), tampilkan `-` untuk `Saldo Pasca-Issue` dan fallback `Waktu Dilepas` ke `$a->updated_at` hanya jika status `'dilepas'`, selainnya `-`.
- Pertahankan struktur kolom, label, dan status badge card `Alokasi Bahan Baku (BOM Explode)` pada status `rencana`, `release`, dan `dibatalkan`.

## Review Focus
1. Batch berstatus `release` dengan stok GD Operasional pasca-issue = `0` (`< kebutuhan`) dan semua alokasi `'dilepas'` -> `Kesiapan Bahan` tampil **`Bahan Di-Issue`**, TIDAK memicu `Stok Kurang` maupun tombol `Buat Request Bahan (Kekurangan Stok)` jika tidak ada kekurangan pemenuhan request.
2. Batch berstatus `selesai` dengan stok GD Operasional pasca-issue = `0` -> `Kesiapan Bahan` tampil **`Selesai Dipakai`**, card `Alokasi Bahan Baku` menampilkan kolom realisasi historis (`Di-Issue (BOM)`, `Saldo Pasca-Issue`, `Waktu Dilepas`, `Aktual Opname`, `Dilepas · Selesai`) tanpa memunculkan teks `Stok Kurang`.
3. Batch `selesai` tanpa baris `KartuStok` (`referensi_tipe = 'batch_produksi'`) -> `Saldo Pasca-Issue` menampilkan `-`, halaman merender `200 OK` tanpa error maupun `LazyLoadingViolationException`.
4. Variasi `StockOpname` pada batch `selesai`: item dengan selisih `0` (`Sesuai BOM`), item boros (`+selisih`), item hemat (`-selisih`), dan item yang belum diopname (`Belum opname`) ter-render akurat.
5. Batch `dibatalkan` dengan stok GD Operasional `0` dan alokasi `'dibatalkan'` -> `Kesiapan Bahan` menampilkan **`Alokasi Dilepas`**, tidak menampilkan `Stok Kurang`.

---

### Task 1: Tambah Feature Tests untuk Seluruh Edge Case di `ProductionFlowTest`

**Files:**
- Modify: `tests/Feature/ProductionFlowTest.php`

**Interfaces:**
- Consumes routes: `batches.store`, `batches.release`, `batches.complete`, `batches.opname`, `batches.cancel`, `batches.show`
- Verifies view contract:
  - KPI Card `Kesiapan Bahan`: `'Bahan Di-Issue'` (`release`), `'Selesai Dipakai'` (`selesai`), `'Alokasi Dilepas'` (`dibatalkan`), tidak mengandung `'Stok Kurang'` atau `'Perlu transfer dari GD Pusat'`
  - Tabel `Alokasi Bahan Baku` state `selesai`: header `'Di-Issue (BOM)'`, `'Saldo Pasca-Issue'`, `'Waktu Dilepas'`, `'Aktual Opname'`, badge `'Dilepas · Selesai'`, teks variance `'Sesuai BOM'` / `'+5'` / `'Belum opname'`, dan fallback `-` saat tanpa `KartuStok`

- [ ] **Step 1: Write failing tests in `tests/Feature/ProductionFlowTest.php`**
  Tambahkan test:
  - `test_released_and_completed_batch_does_not_trigger_stok_kurang_and_shows_completion_audit_columns()`
  - `test_completed_batch_without_kartu_stok_and_cancelled_batch_render_safely_without_shortage_alert()`
- [ ] **Step 2: Run test to verify it fails (RED)**
  Run: `php artisan test tests/Feature/ProductionFlowTest.php --filter=released_and_completed_batch`
  Expected: FAIL (`Stok Kurang` masih muncul / header kolom `Saldo Pasca-Issue` belum ada).

---

### Task 2: Perbaiki Logika Defisit di `BatchController::show()` & Update View `batches/show.blade.php`

**Files:**
- Modify: `app/Http/Controllers/BatchController.php:252-297`
- Modify: `resources/views/batches/show.blade.php:93-98,154-242`

**Interfaces:**
- Produces view variables from `BatchController::show()`:
  - `$readinessSummary`: `array{value: string, subtitle: string, variant: string}`
  - `$selesaiAudits`: `array<string, array{qty_issued: float, saldo_pasca_issue: ?float, released_at: ?\Illuminate\Support\Carbon, opname_aktual: ?float, opname_variance: ?float, has_opname: bool}>` keyed by `$alokasi->id`

- [ ] **Step 1: Update `BatchController::show()`**
  - Tambah `use App\Models\KartuStok;` jika belum di-import di `BatchController.php` (verifikasi baris import dulu).
  - Cek `$isActiveAlokasi = ($a->status === 'aktif') && ! in_array($batch->status, ['selesai', 'dibatalkan'], true);` sebelum menghitung `$kurangFisik` dan men-set `$hasDeficitFisik` / `$hasDeficitRencana`.
  - Susun `$readinessSummary` berdasarkan `$batch->status`, `$hasDeficitFisik`, `$hasDeficitRencana`.
  - Ketika `$batch->status === 'selesai'`, query `KartuStok` (`referensi_tipe = 'batch_produksi'`, `referensi_id = $batch->id`, `tipe = 'out'`) dan bangun `$selesaiAudits` per `$a->id` dengan `$batch->opname->keyBy('bahan_id')`.
- [ ] **Step 2: Update `resources/views/batches/show.blade.php`**
  - Update KPI `<x-stat-box title="Kesiapan Bahan" ...>` memakai `$readinessSummary['value']`, `$readinessSummary['subtitle']`, `$readinessSummary['variant']`.
  - Update Card `Alokasi Bahan Baku (BOM Explode)`:
    - Subtitle dinamis saat `$batch->status === 'selesai'`.
    - Tambah `tabindex="0" role="region" aria-label="Tabel Alokasi Bahan Baku"` pada wrapper `overflow-x-auto`.
    - Pada `$batch->status === 'selesai'`: render kolom `Bahan Baku`, `Di-Issue (BOM)`, `Saldo Pasca-Issue`, `Waktu Dilepas`, `Aktual Opname`, `Status` (`Dilepas · Selesai`), `Aksi`.
    - Pada `$batch->status !== 'selesai'`: pertahankan kolom pra-selesai eksisting (`Kebutuhan`, `Stok Fisik Ops`, `Kolom Rencana`, `Stok GD BB Pusat`, `Status`, `Aksi`).
- [ ] **Step 3: Run targeted tests to verify GREEN**
  Run: `php artisan test tests/Feature/ProductionFlowTest.php tests/Feature/ViewRenderingSafetyTest.php`
  Expected: PASS seluruh test tanpa failure.

---

### Task 3: Full Verification & Final Diff Review

- [ ] **Step 1: Run `php artisan test`**
  Expected: 100% suite pass (0 failures).
- [ ] **Step 2: Run `npm run build` (jika ada class CSS Tailwind baru)** & inspect `git diff` across `BatchController.php`, `batches/show.blade.php`, dan `ProductionFlowTest.php`.

