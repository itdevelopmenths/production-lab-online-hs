# Analisa Impor: Lead Time Tab, Periode Global, Kolom Analisa — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native, chosen by user; model Sonnet) to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Analisa Stok Impor gets a Lead Time sub-tab (8 stages, like Lokal), one global OUT period for all SKUs, the new ADU ETA formula without ADU Pivot, and an Analisa tab showing ABC dropdown | Avg LT | Buffer Days | Total OUT | ADU Base | ADU ETA | Review Period | Safety Stock.

**Architecture:** Formula changes live in `AnalisaService` (`generateImpor`, `impor`, `buildImporSupplierAduRows`, `hitungAduEta`). The global period is a key/value row in a new `analisa_setting` table read by `AnalisaService::imporWindow()` and passed to `DemandAduService::aduBahanPusat()`. Impor stages get their own table/model mirroring `lead_time_lokal_stage`; the existing stage table markup becomes a shared partial used by both tabs.

**Tech Stack:** Laravel 13 / PHP 8.3, Blade + Alpine.js, Tailwind 4, PHPUnit on in-memory SQLite (prod PostgreSQL).

**Spec:** `docs/superpowers/specs/2026-11-28-analisa-impor-leadtime-periode-design.md`

## Global Constraints

- ADU ETA = ADU Base × (1 + Avg LT ÷ Review Period); Review Period ≤ 0 → 30.
- ADU Base = OUT ÷ jumlah hari periode impor; OUT = kartu stok `tipe='out'`, `referensi_tipe='batch_produksi'` (+ existing BOM fallback) inside the period.
- Safety Stock (hari) = (Max LT − Avg LT) + Buffer Days = existing `analisa_impor.buffer_days`. Buffer Days = `analisa_impor.tambahan_buffer_hari` (ABC).
- ADU Pivot removed from formula and UI; DB columns stay; `adu_pivot` is written as 0.
- Default period (no setting) = current rolling 4-month window (`DemandAduService::window()`). Lokal must not change.
- Migrations portable across SQLite and PostgreSQL. Indonesian identifiers/UI copy (`periode`, `tahap`, `hari`).
- `Model::preventLazyLoading` is on in tests: eager-load every relation touched.
- No Claude attribution in commits (user CLAUDE.md overrides).
- Before any Blade/UI edit in Task 4: invoke skill `impeccable:impeccable` and read its `reference/craft-floor.md`. Mode = Operate: follow incumbent styles in `table-lokal.blade.php` exactly (classes, `tbl-modern` cards, `btn-soft`/`btn-glossy`), no new visual language.
- After code changes run `graphify update .`.

## Review Focus

1. Period where `periode_akhir < periode_mulai` → 422 with Indonesian message, nothing saved. (Task 2 test)
2. Only one of the two dates sent → 422 (`required_with`). (Task 2 test)
3. SKU without PO history and without stage rows → Lead Time tab still lists it with manual zeros, Edit visible; saving creates both scenarios. (Task 3 test)
4. Stage max total < avg total → stored `lead_time_max` clamps to avg (never negative safety days). (Task 3 test)
5. Review Period 0/null on a row → ADU ETA uses 30, no division by zero. (Task 1 test)

---

### Task 1: ADU ETA formula, remove ADU Pivot

**Files:**
- Modify: `app/Services/AnalisaService.php` (`hitungAduEta` ~L1314, delete `hitungAduPivot` ~L1304 and `imporGrupPivotOutTotals` ~L1276; `generateImpor` ~L1340-1441; `impor()` ~L1152-1250; `buildImporSupplierAduRows` ~L601-726)
- Modify: `app/Http/Controllers/AnalisaController.php` (`imporData` ~L708 `$grupOutTotals`, row keys `adu_pivot`/`grup_out_total`; `updateManualImpor` ~L497-660 pivot validation, pivot assignment, regroup loop, `$grupSebelum`)
- Delete: `tests/Feature/AnalisaImporPivotTest.php`
- Create: `tests/Feature/AnalisaImporAduEtaTest.php`
- Modify tests: `tests/Feature/AnalisaImporEngineTest.php` (~L91-99, L294-300), `tests/Feature/AnalisaMultiSupplierLeadTimeTest.php` (~L531-578)

**Interfaces:**
- Produces: `public static function hitungAduEta(float $aduBase, float $ltAvg, int $reviewPeriod): float`

- [ ] **Step 1: Write failing tests** in `AnalisaImporAduEtaTest` (setUp like `AnalisaImporEngineTest`: `$this->seed()`, `use SeedsDemandRequests`, product `BTL-P50`):
  - `test_rumus_helper_adu_eta`: `hitungAduEta(10.0, 60.0, 30) == 30.0`; `hitungAduEta(10.0, 45.0, 0) == 25.0` (RP 0 → 30); `hitungAduEta(0.0, 60.0, 30) == 0.0`.
  - `test_generate_impor_adu_eta_pakai_lead_time_dan_review_period`: `seedPusatDemand($botol, 10)`, `generateImpor($botol->id)`; assert `adu_eta ≈ adu_base * (1 + lead_time / review_period)` (delta 0.001) and `minimum_stock == round(adu_eta * lead_time)`.
  - `test_grup_pivot_tidak_lagi_mempengaruhi_adu_eta`: set `grup_pivot='P50'`, `persentase_pivot=0.5` on the AnalisaImpor row, regenerate; assert `adu_pivot == 0` and `adu_eta` still matches the formula above.
- [ ] **Step 2: Run** `php artisan test --filter=AnalisaImporAduEtaTest` → FAIL (signature mismatch).
- [ ] **Step 3: Implement.** New `hitungAduEta` signature above. In `generateImpor` drop `$grupOutTotals`/`$aduPivot`, compute `$aduEta = self::hitungAduEta($aduBase, $ltAvg, $reviewPeriod)`, write `'adu_pivot' => 0`. In `impor()` (varian path) replace `$aduEta = $aduBase` with the same helper using `$ltAvg`, `$reviewPeriod`. In `buildImporSupplierAduRows` use `hitungAduEta($aduRaw, $avgLt, $reviewPeriod)` per supplier and drop the `adu_pivot` key. Remove pivot code from controller (`imporData` + `updateManualImpor`: validator keys `grup_pivot`/`persentase_pivot`, assignments, regroup loop). Update docblocks that cite "ADU Pivot × LT / 30".
- [ ] **Step 4: Delete** `AnalisaImporPivotTest.php`. Fix old assertions to the new formula: `AnalisaImporEngineTest` L297 (`adu_base == adu_eta`) → formula assertion; L300 safety stock → `round(adu_eta * buffer_days)`; `AnalisaMultiSupplierLeadTimeTest` L545/L578 adu_eta and L564/L567 safety stock → recompute from the formula with each case's LT and review period (keep the comments truthful).
- [ ] **Step 5: Run** `php artisan test --filter='AnalisaImpor|AnalisaMultiSupplier|AnalisaStok'` → PASS.
- [ ] **Step 6: Commit** `feat(analisa-impor): ADU ETA = ADU Base × (1 + LT/RP), hapus ADU Pivot`

### Task 2: Global impor period

**Files:**
- Create: `database/migrations/2026_11_28_000001_create_analisa_setting_table.php` (`id`, `key` string(100) unique, `value` text nullable, timestamps)
- Create: `app/Models/AnalisaSetting.php`
- Modify: `app/Services/DemandAduService.php` (`window`, `aduBahanPusat`)
- Modify: `app/Services/AnalisaService.php` (`generateImpor` window block ~L1393-1397, `impor()` ~L1161-1162, new methods)
- Modify: `app/Http/Controllers/AnalisaController.php` (new `updateImporPeriode`, `imporData` meta, `updateManualImpor` drop periode handling incl. meta sync)
- Modify: `routes/web.php` (analisa group, next to `generate-impor`)
- Create: `tests/Feature/AnalisaImporPeriodeTest.php`

**Interfaces:**
- Produces: `AnalisaSetting::getValue(string $key, ?string $default = null): ?string`, `AnalisaSetting::setValue(string $key, ?string $value): void`
- Produces: `DemandAduService::windowBetween(CarbonInterface $start, CarbonInterface $end): array{start: Carbon, end: Carbon, days: int}`; `aduBahanPusat(?array $produkIds = null, ?CarbonInterface $end = null, ?array $window = null): array` (uses `$window` when given)
- Produces: `AnalisaService::SETTING_IMPOR_PERIODE_MULAI = 'impor_periode_mulai'`, `SETTING_IMPOR_PERIODE_AKHIR = 'impor_periode_akhir'`; `imporWindow(): array{start: Carbon, end: Carbon, days: int, custom: bool}`; `pusatDemandImpor(int $produkId): array{total: float, adu: float}`
- Produces: route `POST analisa/impor-periode` → `AnalisaController::updateImporPeriode`, name `analisa.impor-periode.update`, middleware `can:analisa.manage`
- Produces: `imporData` JSON `meta.periode = {mulai: 'Y-m-d', akhir: 'Y-m-d', jumlah_hari: int, custom: bool}`

- [ ] **Step 1: Write failing tests** (`AnalisaImporPeriodeTest`, act as `purchasing@heavenscent.id`):
  - `test_default_periode_mengikuti_jendela_4_bulan`: `imporWindow()['days'] == app(DemandAduService::class)->window()['days']`, `custom === false`.
  - `test_update_periode_global_berlaku_untuk_semua_sku`: POST `impor-periode` `{periode_mulai:'2026-07-01', periode_akhir:'2026-09-28'}` → 200 `success:true`; every `AnalisaImpor` row has `jumlah_hari_periode == 90`, `periode_mulai == '2026-07-01'`; GET `analisa.impor.data` → `meta.periode.custom === true`, `meta.periode.jumlah_hari == 90`.
  - `test_out_hanya_dihitung_dalam_periode`: `seedPusatDemand($botol, 10, Carbon::parse('2026-08-15'))` and another with `Carbon::parse('2026-05-01')`; set period 2026-07-01..2026-09-28, regenerate; `out` equals only the in-range quantity (read the seeded `KartuStok->qty` for the expected value).
  - `test_reset_periode_kembali_ke_default`: POST both null → 200, `imporWindow()['custom'] === false`.
  - `test_periode_akhir_sebelum_mulai_ditolak`: → 422, setting unchanged.
  - `test_hanya_satu_tanggal_ditolak`: only `periode_mulai` → 422.
  - `test_user_tanpa_analisa_manage_ditolak`: as `gudang@heavenscent.id` → 403.
- [ ] **Step 2: Run** `php artisan test --filter=AnalisaImporPeriodeTest` → FAIL.
- [ ] **Step 3: Implement.** `window()` delegates to `windowBetween`. `imporWindow()` reads both settings; if both parse → `windowBetween` + `custom true`, else `window()` + `custom false`. `generateImpor` and `impor()` use `imporWindow()` and `pusatDemandImpor()` instead of `demand()->window()` / `pusatDemand()`. Lokal keeps `pusatDemand`. Controller validation: `periode_mulai` `nullable|date|required_with:periode_akhir`, `periode_akhir` `nullable|date|required_with:periode_mulai|after_or_equal:periode_mulai`; Indonesian messages; save both, then `generateImpor(null, auth()->id())`; respond `{success, message, periode}`. In `updateManualImpor` remove periode validation/assignment and meta periode sync (generateImpor overwrites them anyway).
- [ ] **Step 4: Run** `php artisan test --filter='AnalisaImporPeriode|AnalisaLokalEngine|AnalisaImpor'` → PASS.
- [ ] **Step 5: Commit** `feat(analisa-impor): periode OUT global untuk seluruh SKU impor`

### Task 3: Impor lead time stages (backend)

**Files:**
- Create: `database/migrations/2026_11_28_000002_create_lead_time_impor_stage_table.php` (same columns/types as `lead_time_lokal_stage` in `2026_11_04_000003_create_dual_level_lead_time_tables.php` minus `pengemasan`; `produk_id` FK cascade; unique `[produk_id, skenario]`)
- Create: `app/Models/LeadTimeImporStage.php` (`extends LeadTimeLokalStage`, `protected $table = 'lead_time_impor_stage'`)
- Modify: `app/Http/Controllers/AnalisaController.php` (new `updateLeadTimeStagesImpor`; `imporData` adds `tables.stages`; extract `private function stagePayload(array $stg): array` reused by `updateLeadTimeStages`)
- Modify: `routes/web.php`
- Create: `tests/Feature/AnalisaImporLeadTimeStageTest.php`

**Interfaces:**
- Consumes: `AnalisaService::resolveLokalStageBreakdown(Collection $manual, Collection $empirical): array` (generic; reuse as-is)
- Produces: route `POST analisa/stages-impor` → `updateLeadTimeStagesImpor(Request): JsonResponse`, name `analisa.stages-impor.update`, middleware `can:analisa.manage`. Body: `{produk_id, stages: [{skenario: 'average'|'max', perencanaan, supplier_confirm, approval, payment, po, pengiriman, unloading, input}]}`
- Produces: `imporData` `tables.stages[]` rows with exactly the keys of lokal `formattedStages` (`produk_id, sku, nama, satuan, supplier_id, supplier_nama, sumber_lt, jumlah_transaksi, tambahan_buffer_hari, safety_stock, total_average_lead_time, total_max_lead_time, average, max, manual_average, manual_max`), where `safety_stock` = `analisa_impor.buffer_days`, `tambahan_buffer_hari` = `analisa_impor.tambahan_buffer_hari`, totals = `lead_time_impor` avg/max.

- [ ] **Step 1: Write failing tests** (pick a seeded impor product with no `supplier_produk_lt_summary` rows; assert that precondition):
  - `test_simpan_stage_impor_mengisi_lead_time_impor`: POST stages avg totalling 40 and max totalling 55 → 200; `LeadTimeImpor` avg 40, max 55; `AnalisaImpor.lead_time == 40`, `sumber_lt == 'manual'`; two `LeadTimeImporStage` rows.
  - `test_stage_max_lebih_kecil_dari_avg_diklem`: avg 40, max 30 → `lead_time_max == 40`.
  - `test_impor_data_mengembalikan_tabel_stages`: after saving, GET `analisa.impor.data` → `tables.stages` contains the produk with `average.total == 40`, `sumber_lt == 'manual'`.
  - `test_sku_tanpa_stage_tetap_muncul`: fresh impor SKU with no stage rows appears in `tables.stages` with `sumber_lt == 'manual'`.
  - `test_stage_impor_butuh_analisa_manage`: as gudang → 403.
- [ ] **Step 2: Run** `php artisan test --filter=AnalisaImporLeadTimeStageTest` → FAIL.
- [ ] **Step 3: Implement.** Upsert stages by `(produk_id, skenario)` in a transaction, compute totals via `totalHari()`, `LeadTimeImpor::updateOrCreate` with `lead_time_max = max(maxTotal, avgTotal)`, also mirror to `AnalisaImporMeta` LT fields if a meta row exists, then `generateImpor($produkId, auth()->id())`. In `imporData`, eager-load stage rows `LeadTimeImporStage::whereIn('produk_id', $imporIds)->get()->groupBy('produk_id')` and `SupplierProdukLtStageSummary` keyed `supplier_id:produk_id` (same query as `lokalData` ~L106).
- [ ] **Step 4: Run** `php artisan test --filter='AnalisaImporLeadTimeStage|AnalisaLokalEngine|AnalisaMultiSupplier'` → PASS.
- [ ] **Step 5: Commit** `feat(analisa-impor): rincian 8 tahap lead time impor`

### Task 4: Impor UI (period bar, Analisa columns, Lead Time tab, modals)

**Files:**
- Create: `resources/views/analisa/partials/table-stages.blade.php` (moved from `table-lokal.blade.php` L285-474, parameter `$tipe` = `'lokal'|'impor'`; Edit button calls `openStageModal(row.stg, '{{ $tipe }}')`)
- Modify: `resources/views/analisa/partials/table-lokal.blade.php` (replace L285-474 with `@include('analisa.partials.table-stages', ['tipe' => 'lokal'])`)
- Modify: `resources/views/analisa/partials/table-impor.blade.php`
- Modify: `resources/views/analisa/partials/modal-stage-lead-time.blade.php`
- Modify: `resources/views/analisa/partials/modal-manual-impor.blade.php` (remove periode block ~L85-104, pivot block ~L145-172, LT avg/max inputs)
- Modify: `resources/views/analisa/index.blade.php` (Alpine state/methods)

**Interfaces:**
- Consumes: `meta.periode`, `tables.stages`, routes `analisa.impor-periode.update`, `analisa.stages-impor.update` (Tasks 2-3); row keys from `buildImporSupplierAduRows`.
- Produces (Alpine): `imporSubTab ∈ {'rekomendasi','analisa','stages'}`; `isStagesView(): bool` (true for lokal `stages` or impor `stages`); `imporPeriode = {mulai, akhir, jumlah_hari, custom}`; `imporPeriodeForm = {mulai, akhir}`; `savingImporPeriode`; `saveImporPeriode()`, `resetImporPeriode()`; `openStageModal(item, tipe = 'lokal')` sets `stageForm.tipe`.

- [ ] **Step 1:** Load `impeccable:impeccable` and read `reference/craft-floor.md` (Global Constraints).
- [ ] **Step 2: Shared stage partial.** Move the lokal stages block into `table-stages.blade.php` unchanged except the `$tipe` parameter; wrap visibility with `x-show="isStagesView()"`. Replace every `lokalSubTab === 'stages'` check in `index.blade.php` (L692, L701 and stage helpers L638-690) with `isStagesView()`. Lokal stages tab must render identically.
- [ ] **Step 3: Impor sub-nav + Lead Time tab.** Add a third button `3. Lead Time` (`imporSubTab = 'stages'; page = 1`) with the same classes; include `table-stages` with `tipe='impor'` under `x-show="imporSubTab === 'stages'"`. Table title copy: `Tabel Rincian 8 Tahap Lead Time Impor (lead_time_impor_stage)`.
- [ ] **Step 4: Period bar.** Above the sub-nav in `table-impor.blade.php`: label `Periode OUT (semua SKU impor)`, two `type="date"` inputs bound to `imporPeriodeForm`, computed `N hari`, button `Terapkan` (`btn-glossy`), button `Reset ke 4 bulan terakhir` (`btn-soft`, shown when `imporPeriode.custom`), wrapped in `@can('analisa.manage')`; without permission show read-only `Periode OUT: {mulai} ~ {akhir} ({n} hari)`. `saveImporPeriode()` POSTs, toasts via SweetAlert like `quickChangeImporAbc`, then `load()`. `load()` for impor sets `imporPeriode`/`imporPeriodeForm` from `j.meta.periode`. Remove both per-row `Periode: …` lines (L72-74, L243-245).
- [ ] **Step 5: Analisa table columns** (TABEL 2), in this order: Bahan Baku Impor | Supplier | ABC | Avg LT | Buffer Days | Total OUT | ADU Base | ADU ETA | Review Period | Safety Stock (Hari) | Aksi. ABC cell = the `@can` select/badge markup from TABEL 1 L110-125 (calls `quickChangeImporAbc(r, …)`). Avg LT `formatLt(r.lead_time_average) + ' hr'`; Buffer Days `formatLt(r.tambahan_buffer_hari) + ' hr'`; Total OUT `formatNumber(r.out)`; ADU Base `formatNumber(r.adu_base)`; ADU ETA `formatNumber(r.adu_eta)` (highlighted cell like current ADU); Review Period `r.review_period + ' Hari'`; Safety Stock `formatLt(r.buffer_days) + ' hr'`. Drop Min/Target columns; empty-row `colspan="11"`. Aksi adds an `LT` button `openStageModal(r, 'impor')` like lokal. Subtitle: `ADU Base = Total OUT ÷ jumlah hari periode · ADU ETA = ADU Base × (1 + Avg LT ÷ Review Period) · Safety Stock = (Max LT − Avg LT) + Buffer Days`.
- [ ] **Step 6: Stage modal.** `saveStages()` posts to `analisa.stages-impor.update` when `stageForm.tipe === 'impor'` (payload stages carry `skenario`); hide the Tambahan Buffer input for impor and show `Buffer impor mengikuti Klasifikasi ABC (+N hari).` instead. Lokal behavior unchanged.
- [ ] **Step 7: Manual impor modal.** Remove periode inputs, pivot block, LT avg/max inputs; show LT read-only with link-button `Ubah di tab Lead Time` (closes modal, sets `imporSubTab='stages'`). Simulation: jumlah hari from `imporPeriode.jumlah_hari`; ADU ETA = base × (1 + avgLt / (rp > 0 ? rp : 30)); delete `simulasiImporAduPivotRaw` and pivot form fields/payload keys; stop sending `periode_*`, `grup_pivot`, `persentase_pivot`.
- [ ] **Step 8: Verify visually.** `npm run build`, run app (`composer dev`), log in as `purchasing@heavenscent.id`, open `/analisa?tab=impor`; check all three sub-tabs, period apply/reset, ABC change on Analisa tab, LT edit for impor and for lokal, at 1440px and 390px, light and dark. Capture with the impeccable `shot` command. Browser console must be clean.
- [ ] **Step 9: Run** `composer test` → all PASS; `vendor/bin/pint --dirty`.
- [ ] **Step 10: Commit** `feat(analisa-impor): UI periode global, kolom analisa, tab lead time`; then `graphify update .`.
