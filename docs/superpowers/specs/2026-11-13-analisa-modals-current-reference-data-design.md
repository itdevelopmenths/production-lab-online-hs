# Analisa Modals Current Reference Data Design

**Date:** 2026-11-13  
**Status:** Approved by User  
**Target Files:**
- `app/Http/Controllers/AnalisaController.php`
- `resources/views/analisa/index.blade.php`
- `resources/views/analisa/partials/modal-manual-lokal.blade.php`
- `resources/views/analisa/partials/modal-manual-impor.blade.php`
- `resources/views/analisa/partials/modal-add-sku.blade.php`
- `resources/views/analisa/partials/modal-stage-lead-time.blade.php`
- `tests/Feature/AnalisaModalCurrentReferenceTest.php`

---

## 1. Intent & Why

In the Analisa Stok module, users open modals (`modal-manual-lokal`, `modal-manual-impor`, `modal-add-sku`, and `modal-stage-lead-time`) to review and adjust parameters such as unit price / HPP (`harga_ml_pcs` / `harga_per_satuan`), physical stock (`stok_saat_ini`), and incoming stock (`inbound_before_eta`).

Previously, these modals only bound to existing working-table values without exposing the live system baselines (HPP from `StokService::resolveHppMap()` / master product, actual physical warehouse stock from the `stok` table, and empirical PO lead times). Users had to navigate away to the Buku Saldo Stok or Master Produk to verify whether their manual entries matched current system reality.

This change surfaces live system reference data as informative pills/badges directly underneath relevant modal input fields, paired with a one-click **"Gunakan Nilai Sistem"** action to quickly populate or reset the field to the system value while immediately triggering reactive live recalculations.

---

## 2. Goals, Non-Goals & Anti-Goals

### Goals
- Surface live system reference baselines directly in the UI for:
  - **HPP:** `current_hpp` resolved via `StokService::resolveHppMap()` (with fallback to `produk.harga_hpp`).
  - **Stok Aktual:** `current_stok_aktual` (sum of physical stock in warehouse from `stok` table).
  - **Inbound PO:** `current_inbound` (unfulfilled quantities from active running purchase orders).
  - **Lead Time Empiris:** `current_avg_lt` and `current_max_lt` from historical completed purchase orders.
- Provide a clean, accessible pill/badge under each corresponding input displaying the baseline value (e.g. `HPP Sistem: Rp 45.000` or `Stok Aktual Sistem: 120 Pcs`).
- Provide an adjacent quick action button `[Gunakan Nilai Sistem]` that copies the baseline into the active input model and dispatches the corresponding simulation calculation function (`simulasiLokal*()` or `simulasiImpor*()`).
- In `modal-add-sku`, display an info banner when a SKU is selected showing its current stock and HPP before adding to the working table.

### Non-Goals
- No alterations to database schema or migrations.
- No changes to underlying ROP/Target Stock calculation formulas.
- No forced overrides of existing manual working table data unless the user clicks "Gunakan Nilai Sistem" or submits new values.

### Anti-Goals
- No asynchronous loading spinners or network lag on modal open.
- No visual clutter or layout shifting; pills must maintain the clean enterprise light theme (`bg-muted`, `border-border`, monospace tabular figures, compact size).

---

## 3. Constraints
- Must use existing Tailwind CSS v4 design tokens and Alpine.js component scope (`analisaData()`).
- Data must be delivered without extra per-modal network roundtrips.
- Price display must respect permissions where applicable (`canSeePrice` or general purchasing authority).

---

## 4. Approaches Considered

1. **Approach 1: Payload Enrichment on Existing Endpoints *(Selected & Approved)***  
   - Include `current_hpp`, `current_stok_aktual`, and `current_inbound` directly in `lokalData()`, `imporData()`, and `availableSkus()`.
   - **Trade-off:** Minimal increase in JSON payload size (~3-4 numeric fields per row); in return, modals open instantly with zero network delay, zero loading spinners, and full offline/in-memory responsiveness.
2. **Approach 2: On-Demand Fetch via Dedicated Endpoint (`/analisa/current-reference`)**  
   - Fetch live data via AJAX only when a modal opens.
   - **Trade-off:** Introduces network latency, loading spinners, and layout shifting inside the modal every time a user clicks "Edit", degrading user experience.

---

## 5. Detailed Design & UI Specifications

### 5.1 Backend Payload Enrichment (`AnalisaController.php`)
- In `lokalData()`:
  - Batch resolve HPP map: `$hppMap = $this->stok->resolveHppMap($lokalIds->all());`
  - Batch calculate current warehouse stock: `$actualStockMap = Stok::whereIn('produk_id', $lokalIds)->groupBy('produk_id')->select('produk_id', DB::raw('SUM(qty_saat_ini) as total_qty'))->pluck('total_qty', 'produk_id');`
  - In response array, include:
    - `'current_hpp' => (float) ($hppMap[$rek->produk_id] ?? $rek->produk?->harga_hpp ?? 0)`
    - `'current_stok_aktual' => (float) ($actualStockMap[$rek->produk_id] ?? 0)`
- In `imporData()`:
  - Batch resolve `$hppMap` and `$actualStockMap` for `$imporIds`.
  - Batch calculate current active inbound POs for impor items.
  - In response array, include `current_hpp`, `current_stok_aktual`, and `current_inbound`.
- In `availableSkus()`:
  - Include `current_hpp` and `current_stok_aktual` for each matching item.

### 5.2 Modal Implementations

#### A. Modal Input Bahan Baku Lokal (`modal-manual-lokal.blade.php`)
1. **Harga Satuan Pakai (Rp):**
   - Under the input: Pill with `HPP Sistem: Rp [formatted]` + button `@click="manualLokalForm.harga_ml_pcs = manualLokalForm.current_hpp; simulasiLive()"` labeled `Gunakan Nilai Sistem`.
2. **Stok Fisik Saat Ini:**
   - Under the input: Pill with `Stok Aktual Sistem: [formatted] [satuan]` + button `@click="manualLokalForm.stok_saat_ini = manualLokalForm.current_stok_aktual; simulasiLive()"`.
3. **Terjual 4 Bulan Terakhir:**
   - Under the input: Pill with `Riwayat Terjual Sistem: [formatted] [satuan]` + button to sync if working value was modified.

#### B. Modal Input Bahan Baku Impor (`modal-manual-impor.blade.php`)
1. **Harga per Satuan (Rp):**
   - Under the input: Pill with `HPP Sistem: Rp [formatted]` + button `Gunakan Nilai Sistem`.
2. **Stok Fisik Saat Ini:**
   - Under the input: Pill with `Stok Aktual Sistem: [formatted] [satuan]` + button `Gunakan Nilai Sistem`.
3. **Inbound Sebelum ETA:**
   - Under the input: Pill with `Inbound PO Sistem: [formatted] [satuan]` + button `Gunakan Nilai Sistem`.
4. **Lead Time Average & Max:**
   - Under inputs: Pill with empirical lead time from system (if available) + button `Gunakan Nilai Sistem`.

#### C. Modal Tambah SKU ke Analisa (`modal-add-sku.blade.php`)
- When a SKU is selected, render a summary card:
  - `Stok Aktual di Gudang: [formatted]`
  - `HPP Master: Rp [formatted]`
- Under the `harga_per_satuan` inputs (for both Lokal and Impor):
  - Pill showing `HPP Master/Stok: Rp [formatted]` + button `Gunakan HPP`.

#### D. Modal 9 Tahap Lead Time (`modal-stage-lead-time.blade.php`)
- When empirical PO data is available (`empirical_average` & `empirical_max`), render a reference indicator per stage showing historical PO benchmark days with quick-sync capabilities.

---

## 6. Verification Plan
- Unit & Feature test `tests/Feature/AnalisaModalCurrentReferenceTest.php`:
  - Assert that `lokalData` and `imporData` JSON responses contain `current_hpp`, `current_stok_aktual`, and `current_inbound`.
  - Assert that opening the modals renders the reference pills and "Gunakan Nilai Sistem" buttons.
  - Assert that clicking/submitting preserves and persists updated values correctly.
- Asset build: `npm run build`.
- PHPUnit test suite verification.

