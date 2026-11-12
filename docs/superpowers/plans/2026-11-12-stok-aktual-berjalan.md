# Stok Aktual, Stok Berjalan & Badge Evaluasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memisahkan kolom tabel `Stok & Mutasi` menjadi **`Stok Aktual`** (saldo fisik murni `qty_saat_ini` yang sinkron dengan Kartu Stok/Ledger), **`Stok Berjalan`** (hanya inbound PO/Transfer aktif & batch produksi berjalan tanpa mencampur saldo fisik), dan kolom **`Status`** tunggal dengan badge `AMAN`/`ORDER` berbasis `(stok_aktual + stok_berjalan) - batas_min_analisa`.

**Architecture:** [`StokController::data()`](app/Http/Controllers/StokController.php#L52-L300) menghapus subquery `alokasi_aktif` dan mengganti definisi kolom `kolom_stok`/`kolom_rencana` menjadi `stok_aktual`, `stok_berjalan`, dan `status_stok`. View [`resources/views/stok/index.blade.php`](resources/views/stok/index.blade.php#L52-L68) memperbarui `<thead>` dan konfigurasi kolom DataTables agar sejajar 1:1 dengan payload JSON baru.

**Tech Stack:** Laravel 12, PHP 8.x, Yajra DataTables, Blade, Tailwind CSS v4, PHPUnit (`RefreshDatabase` + `Model::preventLazyLoading(true)`).

**Spec:** `docs/superpowers/specs/2026-11-12-stok-aktual-berjalan-design.md`

## Global Constraints
- `Stok Aktual` wajib murni membaca `(float) $s->qty_saat_ini` tanpa `+ $inbound` dan diformat dengan `$this->formatQty(...) . ' ' . $s->produk?->satuan`.
- `Stok Berjalan` untuk `bahan`/`kemas` hanya menjumlahkan inbound PO aktif (`$inboundPo` pada `$gudangPusatId`) + inbound Transfer/Request Transfer aktif (`$inboundRtMap`); untuk `produk_jadi` menjumlahkan `$inboundRtMap` + rencana output Batch Produksi aktif (`rencana`/`release` di `$rencanaBatchMap`) tanpa mencampur saldo fisik `qty_saat_ini`.
- Subquery `alokasi_aktif` dihapus dari `StokController::data()` (tidak mengubah [`batches/show.blade.php`](resources/views/batches/show.blade.php), `AnalisaService`, maupun Kartu Stok/Ledger).
- Evaluasi badge di `status_stok`: `proyeksi = stok_aktual + stok_berjalan`, `selisih = proyeksi - batas_min`, dan `$isOrder = $batasMin > 0 && $selisih <= 0` (`ORDER` merah bila true, `AMAN` hijau bila false).
- Patuhi `Model::preventLazyLoading(true)` (digunakan di `StockAlertTest`, `StokRencanaAndFormattingTest`, dan `BladeUiPrd24AlignmentTest`).

## Review Focus
1. Item `bahan`/`kemas` dengan `Stok Aktual < Batas Min` tetapi memiliki PO berjalan sehingga `stok_aktual + stok_berjalan - min_analisa > 0` -> `Stok Aktual` tetap tampil nilai fisik murni, `Stok Berjalan` tampil qty PO, dan `Status` tampil **`AMAN`**.
2. Boundary condition tepat sama (`stok_aktual + stok_berjalan - min_analisa == 0`, dengan `min_analisa > 0`) dan defisit (`< 0`) -> `Status` tampil **`ORDER`**.
3. Item dengan `Batas Min (Analisa) = 0` (`-`) walaupun `Stok Aktual = 0` dan `Stok Berjalan = 0` -> `Status` tampil **`AMAN`**, tidak memicu `ORDER` palsu.
4. `produk_jadi` dengan multiple output batch (`batch_produksi_outputs`) + legacy batch (`batch_produksi`) + transfer `dikirim` -> `Stok Aktual` murni saldo fisik dan `Stok Berjalan` hanya menjumlahkan output batch berjalan + transfer masuk (tanpa saldo fisik).
5. `bahan` di Gudang Operasional dengan alokasi batch aktif (`alokasi_bahan` status `aktif`) -> tidak lagi memotong `Stok Aktual`, `Stok Berjalan`, maupun `Status`.

---

### Task 1: Perbarui Feature Tests sesuai Kontrak Baru (`RED`)

**Files:**
- Modify: `tests/Feature/StockAlertTest.php:1-81`
- Modify: `tests/Feature/StokRencanaAndFormattingTest.php:26-76`
- Modify: `tests/Feature/BladeUiPrd24AlignmentTest.php:35-115`

**Interfaces:**
- Consumes endpoint: `GET /stok/data` (`route('stok.data')`) & `GET /stok` (`route('stok.index')`)
- Verifies JSON columns:
  - `stok_aktual`: string qty fisik terformat (misal `'50 pcs'`, `'1.000 ml'`)
  - `stok_berjalan`: string HTML span qty berjalan terformat (misal `'750 pcs'`, `'0 ml'`, `'200 pcs'`)
  - `status_stok`: string HTML badge `'AMAN'` / `'ORDER'`

- [ ] **Step 1: Perbarui `tests/Feature/StockAlertTest.php` untuk menguji `stok_aktual`, `stok_berjalan`, dan rumus badge `stok_aktual + stok_berjalan - min_analisa`**
  - Ubah skenario test `test_two_column_stock_alert_reflects_physical_and_planned_availability` (atau ganti nama/tambah case sejelas mungkin) untuk `ALK-01` di `GD-PUSAT` (`batas_minimum` dari `MasterDataSeeder` ~`31.442,6`):
    1. Saat stok fisik diubah ke `10.000` (`< 31.442,6`) tanpa PO berjalan -> `stok_aktual` mengandung `'10.000 ml'`, `stok_berjalan` mengandung `'0 ml'`, `status_stok` mengandung `'ORDER'`.
    2. Saat PO berjalan (`status = 'berjalan'`) ditambahkan dengan item `ALK-01` qty `25.000` (`10.000 + 25.000 - 31.442,6 > 0`) -> `stok_aktual` tetap `'10.000 ml'`, `stok_berjalan` mengandung `'25.000 ml'`, `status_stok` menjadi `'AMAN'`.
    3. Saat stok fisik + berjalan tepat sama dengan batas minimum (`stok_aktual + stok_berjalan - min_analisa == 0` atau defisit `10.000 + 20.000 - 31.442,6 <= 0`) -> `status_stok` menjadi `'ORDER'`.
    4. Produk tanpa batas minimum (`Batas Min = 0 / '-'`) dengan `stok_aktual = 0` dan `stok_berjalan = 0` -> `status_stok` tetap `'AMAN'`.
    5. Cek halaman `GET /stok` (`stok.index`) merender header `Stok Aktual`, `Stok Berjalan`, `Status`, dan tidak lagi menampilkan `Kolom Rencana`.

- [ ] **Step 2: Perbarui `tests/Feature/StokRencanaAndFormattingTest.php` (`test_stok_data_formatting_and_rencana_calculation_for_materials_and_finished_goods`)**
  - Untuk bahan `ALK-01` di `GD-OPS` (fisik `1.000`, alokasi batch `100`, tanpa transfer masuk): pastikan `stok_aktual` mengandung `'1.000 ml'` (tidak terpotong alokasi, tidak mengandung `'1000.00'`) dan `stok_berjalan` mengandung `'0 ml'`.
  - Untuk produk jadi `GOH-P50` di `GD-OPS` (fisik `50`, rencana batch `10`): pastikan `stok_aktual` mengandung `'50 pcs'` dan `stok_berjalan` mengandung `'10 pcs'` (tidak mencampur `50 + 10 = 60`), serta tambahkan kasus transfer `dikirim` ke `GD-OPS` untuk `ALK-01` agar terverifikasi menambah `stok_berjalan` tanpa mengubah `stok_aktual`.

- [ ] **Step 3: Perbarui `tests/Feature/BladeUiPrd24AlignmentTest.php:35-115`**
  - Pada `test_kolom_rencana_in_stok_data_counts_multi_product_outputs_and_legacy_fallback` (perbarui docstring/nama atau assertion): verifikasi untuk `PRD-MULTI-01` dan `PRD-MULTI-02` bahwa `stok_aktual` tetap `'10 pcs'` (tidak tercampur rencana produksi), sementara `stok_berjalan` untuk `PRD-MULTI-01` = `'200 pcs'` (150 multi-output + 50 legacy fallback) dan `PRD-MULTI-02` = `'250 pcs'`.

- [ ] **Step 4: Run targeted tests untuk memastikan kondisi FAIL (`RED`) sebelum implementasi**
  - Run: `php artisan test tests/Feature/StockAlertTest.php tests/Feature/StokRencanaAndFormattingTest.php tests/Feature/BladeUiPrd24AlignmentTest.php`
  - Expected: FAIL karena key `stok_aktual`, `stok_berjalan`, `status_stok` belum ada di response `StokController::data()`.

---

### Task 2: Implementasi Kolom `Stok Aktual`, `Stok Berjalan`, & `Status` (`GREEN`)

**Files:**
- Modify: `app/Http/Controllers/StokController.php:159-299`
- Modify: `resources/views/stok/index.blade.php:51-71,225-240`

**Interfaces:**
- Produces from [`StokController::data()`](app/Http/Controllers/StokController.php#L52):
  - DataTables columns: `sku`, `nama`, `gudang_nama`, `batas_minimum`, `stok_aktual`, `stok_berjalan`, `status_stok`, `hpp`, `nilai_stok`, `action`
- Consumed by [`resources/views/stok/index.blade.php`](resources/views/stok/index.blade.php):
  - `<thead>` columns dan DataTables `cols` mapping `stok_aktual`, `stok_berjalan`, `status_stok`

- [ ] **Step 1: Cek panduan table accessibility via `modern-web-guidance` sebelum menyentuh HTML/Blade**
  - Run: `npx.cmd -y modern-web-guidance@latest search "responsive data table"` (serta `retrieve` bila ada panduan relevan untuk accessible data table / badges).

- [ ] **Step 2: Update [`StokController::data()`](app/Http/Controllers/StokController.php#L159-L299)**
  - Hapus `.selectRaw(...)` subquery `alokasi_aktif` (baris 165).
  - Buat helper privat ringkas atau closure terukur di dalam `StokController` (untuk menghindari duplikasi perhitungan `$berjalan` antara `stok_berjalan` dan `status_stok`):
    `private function resolveStokBerjalan($s, ?int $gudangPusatId, $inboundPo, array $inboundRtMap, array $rencanaBatchMap): float`
    - Jika `in_array($s->produk?->tipe, ['bahan', 'kemas'], true) && $gudangPusatId && (int) $s->gudang_id === (int) $gudangPusatId`, tambahkan `(float) ($inboundPo[$s->produk_id] ?? 0)`.
    - Tambahkan `(float) ($inboundRtMap["{$s->produk_id}_{$s->gudang_id}"] ?? 0)`.
    - Jika `$s->produk?->tipe === 'produk_jadi'`, tambahkan `(float) ($rencanaBatchMap["{$s->produk_id}_{$s->gudang_id}"] ?? 0)`.
  - Ganti `->addColumn('kolom_stok', ...)` dan `->addColumn('kolom_rencana', ...)` menjadi:
    - `->addColumn('stok_aktual', ...)`: `$fisik = (float) $s->qty_saat_ini; return $this->formatQty($fisik) . ' ' . $s->produk?->satuan;`
    - `->addColumn('stok_berjalan', ...)`: hitung `$berjalan = $this->resolveStokBerjalan(...)`; format `$val = $this->formatQty($berjalan) . ' ' . $s->produk?->satuan;` — render `<span class="text-muted-foreground">` bila `$berjalan <= 0`, atau `<span>` normal bila `$berjalan > 0`.
    - `->addColumn('status_stok', ...)`:
      - `$aktual = (float) $s->qty_saat_ini;`
      - `$berjalan = $this->resolveStokBerjalan(...);`
      - `$batasMin = $this->analisa->getBatasMinimumForStok($s->produk_id, $s->gudang_id, $batasMinMap);`
      - `$selisih = ($aktual + $berjalan) - $batasMin;`
      - `$isOrder = $batasMin > 0 && $selisih <= 0;`
      - Tooltip `title`: `Stok Aktual (...) + Stok Berjalan (...) - Min Analisa (...) = ...` (atau `Batas minimum analisa belum dikonfigurasi` jika `$batasMin <= 0`).
      - Badge HTML `ORDER` (merah) / `AMAN` (hijau).
  - Ubah `->rawColumns(['stok_berjalan', 'status_stok', 'action'])`.

- [ ] **Step 3: Update [`resources/views/stok/index.blade.php`](resources/views/stok/index.blade.php#L51-L71,L225-L240)**
  - Bungkus container `overflow-x-auto` dengan `tabindex="0" role="region" aria-label="Tabel Buku Saldo & Status Ketersediaan Stok"` jika sesuai panduan table accessibility.
  - Perbarui `<thead>`:
    - `SKU`, `Produk`, `Gudang`, `Batas Min (Analisa)`, `Stok Aktual`, `Stok Berjalan`, `Status`, (`HPP`, `Nilai Stok`), `Aksi`.
  - Perbarui array `cols` DataTables:
    - `{ data: 'stok_aktual', className: 'text-right font-mono font-semibold text-foreground', orderable: false, searchable: false }`
    - `{ data: 'stok_berjalan', className: 'text-right font-mono', orderable: false, searchable: false }`
    - `{ data: 'status_stok', className: 'text-center', orderable: false, searchable: false }`

- [ ] **Step 4: Run targeted Stok & PRD tests untuk verifikasi `GREEN`**
  - Run: `php artisan test tests/Feature/StockAlertTest.php tests/Feature/StokRencanaAndFormattingTest.php tests/Feature/BladeUiPrd24AlignmentTest.php tests/Feature/StokEnhancementTest.php`
  - Expected: PASS seluruh test tanpa failure.

---

### Task 3: Full Verification Suite & Diff Inspection

**Files:**
- Verify all modified files via `git diff`

- [ ] **Step 1: Jalankan full PHPUnit suite**
  - Run: `php artisan test`
  - Expected: 100% seluruh suite PASS (0 failures, 0 errors).

- [ ] **Step 2: Jalankan `npm run build` (jika ada class utility CSS baru yang dipakai di HTML response PHP) & periksa `git diff`**
  - Run: `npm run build`
  - Run: `git diff -- app/Http/Controllers/StokController.php resources/views/stok/index.blade.php tests/Feature/`
  - Pastikan tidak ada komentar usang, duplikasi kalkulasi `$berjalan`, maupun lazy loading.

