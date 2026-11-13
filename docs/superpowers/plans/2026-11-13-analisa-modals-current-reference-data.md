# Analisa Modals Current Reference Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Surface current live system baselines (HPP, actual warehouse stock, inbound POs, empirical lead times) as reference pills with one-click "Gunakan Nilai Sistem" sync actions across all Analisa modals (`modal-manual-lokal`, `modal-manual-impor`, `modal-add-sku`, `modal-stage-lead-time`).

**Architecture:** Enrich backend JSON responses (`lokalData`, `imporData`, `availableSkus`) in `AnalisaController` with `current_hpp`, `current_stok_aktual`, and `current_inbound` queried in batch without extra network roundtrips. Update Alpine.js forms in `index.blade.php` to hold these baselines and render compact, accessible pill badges with `@click` sync triggers in each modal view.

**Tech Stack:** Laravel 12 Blade, Tailwind CSS v4, Alpine.js, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-13-analisa-modals-current-reference-data-design.md`

## Global Constraints

- Never alter database schemas, migrations, or core calculation formulas (ADU, ROP, Target Stock).
- Zero asynchronous network latency or layout shifting when opening modals; all baseline data must travel with the initial row payload.
- Follow the existing enterprise light theme (`bg-muted`, `border-border`, monospace tabular figures, compact pill size).
- Prevent horizontal cramping on 13–14" laptop screens by applying flex wrapping and proper truncation where labels meet values.

## Review Focus

- **Null / Zero baselines:** When a product has no historical POs or zero stock, the pill must display gracefully (e.g. `HPP Sistem: Rp 0` or `—`, `Stok Aktual Sistem: 0 [satuan]`) and not break Alpine arithmetic or formatters.
- **Immediate reactive simulation update:** Clicking "Gunakan Nilai Sistem" must update the Alpine model AND immediately recalculate live simulation values (`simulasiLokal*` and `simulasiImpor*`).
- **Precision and number formatting:** Currency values must format via `formatRupiah` or `Intl.NumberFormat`, and quantities via `formatNumber`.
- **Permission scoping:** Pricing data (HPP) display should respect `canSeePrice` if user lacks price view permissions.
- **Clean staging:** Only stage files belonging to the task; never stage temporary directories.

---

### Task 1: Backend Payload Enrichment (`AnalisaController.php`)

**Files:**
- Modify: `app/Http/Controllers/AnalisaController.php:95-135, 344-392, 630-677`
- Test: `tests/Feature/AnalisaModalCurrentReferenceTest.php`

**Interfaces:**
- Produces in `lokalData()` JSON item: `current_hpp`, `current_stok_aktual`, `current_terjual_sistem`
- Produces in `imporData()` JSON item: `current_hpp`, `current_stok_aktual`, `current_inbound`
- Produces in `availableSkus()` JSON item: `current_hpp`, `current_stok_aktual`

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/AnalisaModalCurrentReferenceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AnalisaLokal;
use App\Models\AnalisaImpor;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Stok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaModalCurrentReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_analisa_endpoints_include_current_system_reference_baselines(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $produk = Produk::where('sku', 'ALK-01')->firstOrFail();

        $produk->update(['harga_hpp' => 45000]);
        Stok::updateOrCreate(
            ['produk_id' => $produk->id, 'gudang_id' => $gudangPusat->id],
            ['qty_saat_ini' => 125.5]
        );

        // 1. Cek endpoint lokal-data
        $resLokal = $this->actingAs($manager)->getJson(route('analisa.lokal-data'));
        $resLokal->assertOk();
        $lokalItem = collect($resLokal->json('data'))->firstWhere('produk_id', $produk->id);
        if ($lokalItem) {
            $this->assertArrayHasKey('current_hpp', $lokalItem);
            $this->assertArrayHasKey('current_stok_aktual', $lokalItem);
            $this->assertEquals(45000, $lokalItem['current_hpp']);
            $this->assertEquals(125.5, $lokalItem['current_stok_aktual']);
        }

        // 2. Cek endpoint available-skus
        $resAvail = $this->actingAs($manager)->getJson(route('analisa.available-skus', ['q' => 'ALK-01']));
        $resAvail->assertOk();
        $availItem = collect($resAvail->json('data'))->firstWhere('id', $produk->id);
        $this->assertNotNull($availItem);
        $this->assertArrayHasKey('current_hpp', $availItem);
        $this->assertArrayHasKey('current_stok_aktual', $availItem);
        $this->assertEquals(45000, $availItem['current_hpp']);
        $this->assertEquals(125.5, $availItem['current_stok_aktual']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php`  
Expected: FAIL (`Failed asserting that an array has key 'current_hpp'`).

- [ ] **Step 3: Implement batch reference payload in `AnalisaController.php`**

In `app/Http/Controllers/AnalisaController.php`:
1. In `lokalData()`:
   - Query `$hppMap = $this->stok->resolveHppMap($lokalIds->all());`
   - Query `$actualStockMap = Stok::whereIn('produk_id', $lokalIds)->groupBy('produk_id')->select('produk_id', DB::raw('SUM(qty_saat_ini) as total_qty'))->pluck('total_qty', 'produk_id');`
   - In row mapper add:
     - `'current_hpp' => (float) ($hppMap[$rek->produk_id] ?? $rek->produk?->harga_hpp ?? 0),`
     - `'current_stok_aktual' => (float) ($actualStockMap[$rek->produk_id] ?? 0),`
     - `'current_terjual_sistem' => (float) round($al?->terjual_rata_rata_4bulan ?? 0),`
2. In `imporData()`:
   - Query `$hppMap = $this->stok->resolveHppMap($imporIds);`
   - Query `$actualStockMap = Stok::whereIn('produk_id', $imporIds)->groupBy('produk_id')->select('produk_id', DB::raw('SUM(qty_saat_ini) as total_qty'))->pluck('total_qty', 'produk_id');`
   - Query `$inboundMap` from active PO items.
   - In row mapper add `current_hpp`, `current_stok_aktual`, `current_inbound`.
3. In `availableSkus()`:
   - Include `$hppMap` and `$actualStockMap` for matched items and pass `'current_hpp'` and `'current_stok_aktual'`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit Task 1**

```bash
git add app/Http/Controllers/AnalisaController.php tests/Feature/AnalisaModalCurrentReferenceTest.php
git commit -m "feat(analisa): enrich payload with live system hpp and actual stock references"
```

---

### Task 2: UI Implementation in `modal-manual-lokal.blade.php` and Alpine Handlers

**Files:**
- Modify: `resources/views/analisa/index.blade.php` (around `openManualLokalModal`)
- Modify: `resources/views/analisa/partials/modal-manual-lokal.blade.php`
- Test: `tests/Feature/AnalisaModalCurrentReferenceTest.php`

**Interfaces:**
- Consumes: `manualLokalForm.current_hpp`, `manualLokalForm.current_stok_aktual`, `manualLokalForm.current_terjual_sistem`
- Produces: Pill badges and `@click` sync buttons under `harga_ml_pcs`, `stok_saat_ini`, and `terjual_rata_rata_4bulan`.

- [ ] **Step 1: Add view assertion to feature test**

Add to `tests/Feature/AnalisaModalCurrentReferenceTest.php`:

```php
    public function test_modal_manual_lokal_renders_system_reference_pills_and_sync_actions(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $response = $this->actingAs($manager)->get(route('analisa.index'));
        $response->assertOk();
        $response->assertSee('Gunakan Nilai Sistem');
        $response->assertSee('manualLokalForm.current_hpp', false);
        $response->assertSee('manualLokalForm.current_stok_aktual', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php --filter=test_modal_manual_lokal_renders_system_reference_pills_and_sync_actions`  
Expected: FAIL (`Failed asserting that ... contains "manualLokalForm.current_hpp"`).

- [ ] **Step 3: Update `index.blade.php` and `modal-manual-lokal.blade.php`**

1. In `resources/views/analisa/index.blade.php` inside `openManualLokalModal(item)`:
   - Store `current_hpp: Number(item.current_hpp) || Number(item.harga_hpp) || 0`
   - Store `current_stok_aktual: Number(item.current_stok_aktual) || 0`
   - Store `current_terjual_sistem: Number(item.current_terjual_sistem) || Number(item.terjual_rata_rata_4bulan) || 0`
2. In `resources/views/analisa/partials/modal-manual-lokal.blade.php`:
   - Under `harga_ml_pcs`:
     Add reference badge with icon, text `HPP Sistem: Rp [val]`, and inline button `@click="manualLokalForm.harga_ml_pcs = manualLokalForm.current_hpp"` text `Gunakan Nilai Sistem`.
   - Under `stok_saat_ini`:
     Add reference badge with icon, text `Stok Aktual Sistem: [val] [satuan]`, and inline button `@click="manualLokalForm.stok_saat_ini = manualLokalForm.current_stok_aktual"` text `Gunakan Nilai Sistem`.
   - Under `terjual_rata_rata_4bulan`:
     Add reference badge `Riwayat Terjual Sistem: [val] [satuan]` and button `@click="manualLokalForm.terjual_rata_rata_4bulan = manualLokalForm.current_terjual_sistem"` text `Gunakan Nilai Sistem`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit Task 2**

```bash
git add resources/views/analisa/index.blade.php resources/views/analisa/partials/modal-manual-lokal.blade.php tests/Feature/AnalisaModalCurrentReferenceTest.php
git commit -m "feat(ui): add system reference pills and sync buttons to modal manual lokal"
```

---

### Task 3: UI Implementation in `modal-manual-impor.blade.php` and Alpine Handlers

**Files:**
- Modify: `resources/views/analisa/index.blade.php` (around `openManualImporModal`)
- Modify: `resources/views/analisa/partials/modal-manual-impor.blade.php`
- Test: `tests/Feature/AnalisaModalCurrentReferenceTest.php`

**Interfaces:**
- Consumes: `manualImporForm.current_hpp`, `manualImporForm.current_stok_aktual`, `manualImporForm.current_inbound`, `manualImporForm.empirical_avg_lt`, `manualImporForm.empirical_max_lt`
- Produces: Reference pills under `harga_per_satuan`, `stok_saat_ini`, `inbound_before_eta`, `lead_time_average`, `lead_time_max`.

- [ ] **Step 1: Add view assertion to feature test**

Add to `tests/Feature/AnalisaModalCurrentReferenceTest.php`:

```php
    public function test_modal_manual_impor_renders_system_reference_pills_and_sync_actions(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $response = $this->actingAs($manager)->get(route('analisa.index'));
        $response->assertOk();
        $response->assertSee('manualImporForm.current_hpp', false);
        $response->assertSee('manualImporForm.current_stok_aktual', false);
        $response->assertSee('manualImporForm.current_inbound', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php --filter=test_modal_manual_impor_renders_system_reference_pills_and_sync_actions`  
Expected: FAIL.

- [ ] **Step 3: Update `index.blade.php` and `modal-manual-impor.blade.php`**

1. In `resources/views/analisa/index.blade.php` inside `openManualImporModal(item)`:
   - Store `current_hpp: Number(item.current_hpp) || 0`
   - Store `current_stok_aktual: Number(item.current_stok_aktual) || 0`
   - Store `current_inbound: Number(item.current_inbound) || 0`
   - Store `empirical_avg_lt: Number(item.empirical_avg_lt || item.lead_time_average) || 0`
   - Store `empirical_max_lt: Number(item.empirical_max_lt || item.lead_time_max) || 0`
2. In `resources/views/analisa/partials/modal-manual-impor.blade.php`:
   - Under `harga_per_satuan`: add pill `HPP Sistem: Rp [val]` + button `Gunakan Nilai Sistem`.
   - Under `stok_saat_ini`: add pill `Stok Aktual Sistem: [val] [satuan]` + button `Gunakan Nilai Sistem`.
   - Under `inbound_before_eta`: add pill `Inbound PO Sistem: [val] [satuan]` + button `Gunakan Nilai Sistem`.
   - Under `lead_time_average` & `lead_time_max`: add pill `LT Empiris: [val] Hari` + button `Gunakan Nilai Sistem`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit Task 3**

```bash
git add resources/views/analisa/index.blade.php resources/views/analisa/partials/modal-manual-impor.blade.php tests/Feature/AnalisaModalCurrentReferenceTest.php
git commit -m "feat(ui): add system reference pills and sync buttons to modal manual impor"
```

---

### Task 4: UI Implementation in `modal-add-sku.blade.php` and `modal-stage-lead-time.blade.php`

**Files:**
- Modify: `resources/views/analisa/index.blade.php` (around `selectSkuToAdd` and `openStageModal`)
- Modify: `resources/views/analisa/partials/modal-add-sku.blade.php`
- Modify: `resources/views/analisa/partials/modal-stage-lead-time.blade.php`
- Test: `tests/Feature/AnalisaModalCurrentReferenceTest.php`

**Interfaces:**
- Consumes: `addSkuForm.current_hpp`, `addSkuForm.current_stok_aktual`, `stageForm.empirical_average`, `stageForm.empirical_max`
- Produces: Selected SKU current baseline banner & HPP reference pill in `modal-add-sku`; empirical stage lead time reference indicators in `modal-stage-lead-time`.

- [ ] **Step 1: Add view assertion to feature test**

Add to `tests/Feature/AnalisaModalCurrentReferenceTest.php`:

```php
    public function test_modal_add_sku_and_stage_lead_time_render_reference_indicators(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $response = $this->actingAs($manager)->get(route('analisa.index'));
        $response->assertOk();
        $response->assertSee('addSkuForm.current_hpp', false);
        $response->assertSee('addSkuForm.current_stok_aktual', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php --filter=test_modal_add_sku_and_stage_lead_time_render_reference_indicators`  
Expected: FAIL.

- [ ] **Step 3: Update `index.blade.php`, `modal-add-sku.blade.php`, and `modal-stage-lead-time.blade.php`**

1. In `resources/views/analisa/index.blade.php`:
   - In `selectSkuToAdd(item)`: attach `current_hpp` and `current_stok_aktual` to `addSkuForm`.
   - In `openStageModal(item)`: ensure empirical stage averages/maxima are accessible as `empirical_average` & `empirical_max`.
2. In `resources/views/analisa/partials/modal-add-sku.blade.php`:
   - Display a clean summary card for selected SKU showing:
     - `Stok Aktual di Gudang: [formatted]`
     - `HPP Master / Stok: Rp [formatted]`
   - Under both `harga_per_satuan` inputs (Impor and Lokal):
     - Pill: `HPP Master/Stok: Rp [val]` + button `Gunakan HPP`.
3. In `resources/views/analisa/partials/modal-stage-lead-time.blade.php`:
   - Add column header / inline pill showing empirical PO history benchmark when available, with a quick-action to copy empirical values to manual inputs.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php`  
Expected: PASS.

- [ ] **Step 5: Commit Task 4**

```bash
git add resources/views/analisa/index.blade.php resources/views/analisa/partials/modal-add-sku.blade.php resources/views/analisa/partials/modal-stage-lead-time.blade.php tests/Feature/AnalisaModalCurrentReferenceTest.php
git commit -m "feat(ui): display current system baselines in modal add sku and stage lead time"
```

---

### Task 5: End-to-End Build and Verification

**Files:**
- Assets: `public/build/assets/*`
- Test: All Feature and Unit tests

- [ ] **Step 1: Build frontend assets**

Run: `npm run build`  
Expected: Successful Vite build with 0 errors.

- [ ] **Step 2: Run test suites**

Run: `php artisan test tests/Feature/AnalisaModalCurrentReferenceTest.php tests/Feature/StokEnhancementTest.php tests/Feature/PurchasingRevampFlowTest.php tests/Feature/ViewRenderingSafetyTest.php`  
Expected: All tests PASS with zero failures.

- [ ] **Step 3: Commit Task 5**

```bash
git add public/build/
git commit -m "build(assets): recompile frontend assets for analisa modal reference pills"
```

