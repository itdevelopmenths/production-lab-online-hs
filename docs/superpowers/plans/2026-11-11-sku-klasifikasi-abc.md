# Pemilihan & Penambahan SKU ke Klasifikasi ABC serta Profil Analisa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable users to assign, add, and unassign SKUs to Klasifikasi ABC and choose between Analisa Impor (with ABC) or Analisa Lokal directly from Master Produk, Master Klasifikasi ABC, and Analisa Stok without duplicate rows or profile mismatches.

**Architecture:** Add a nullable `klasifikasi_abc_id` FK on `produk` synced two-way with `analisa_impor` and `analisa_impor_meta` via centralized methods in `AnalisaService`. Normalize `'impor'` vs `'bahan_impor'`, support true unassignment (`klasifikasi_abc_id = null` without jumping to Class C), clean up and isolate working tables when a SKU switches between `lokal`, `impor`, and `produk_jadi`, and align `klasifikasi-abc.*` RBAC to `analisa.view` / `analisa.manage`.

**Tech Stack:** Laravel 12 (PHP 8.4), Eloquent ORM, Spatie Laravel Permission, Yajra DataTables, Alpine.js, Tailwind CSS / Vite, PHPUnit (SQLite `:memory:` for tests, PostgreSQL in production).

**Spec:** `docs/superpowers/specs/2026-11-11-sku-klasifikasi-abc-design.md`

## Global Constraints

- Database: PostgreSQL in production (`LOWER(...) LIKE`, avoid nested `whereHas` where `whereIn` works) and SQLite `:memory:` in PHPUnit (`phpunit.xml`). Migrations must run and roll back cleanly on both.
- Strict Eloquent: `Model::preventLazyLoading(true)` is enabled in tests; eager-load `klasifikasiAbc` and use `withCount` / subqueries cleanly.
- View test constraint: `tests/Feature/ViewRenderingSafetyTest.php` asserts `route('produk.create')` does not contain `max-w-4xl`.
- Backward compatibility: Accept both `'impor'` and `'bahan_impor'` in `AnalisaService::isImporProfile()`, and auto-promote `profil_analisa = null` in `updateManualImpor()` so existing tests (`AnalisaImporEngineTest`, `AnalisaMultiSupplierLeadTimeTest`, `Prd24EdgeCaseAndBugAnalysisTest`) continue passing.
- RBAC: `ProdukController` uses `produk.*`; `KlasifikasiAbcController` and `AnalisaController` use `analisa.view` / `analisa.manage`.

## Review Focus

- Unassigning a SKU from Class A/B/C (`klasifikasi_abc_id = null`) must keep `produk.klasifikasi_abc_id = null` and `analisa_impor.klasifikasi_abc_id = null` even after `generateImpor()` runs again, never re-attaching it to Class C in Master Klasifikasi ABC.
- Switching a SKU from `Lokal` (with existing `analisa_lokal`/`rekomendasi_order_lokal`) to `Impor` (or vice versa / `produk_jadi`) must remove stale working rows and exclude the SKU from `lokalData()`, `imporData()`, `finalisasi()`, `getBatasMinimumMap()`, `getItemPerluOrderSummary()`, and `createPoForm()` of the old profile.
- Assigning a `produk_jadi` or inactive SKU via Klasifikasi ABC / Analisa endpoints must be rejected (`422`), and `ProdukController` must coerce `klasifikasi_abc_id` to `null` for `produk_jadi` or non-impor products.
- A custom role with `analisa.view` + `analisa.manage` (without `produk.view`) must be able to access `klasifikasi-abc.*`, while `gudang`/`operasional` (`produk.view` without `analisa.view`) must receive `403` and not see the sidebar menu.
- Quick-changing or clearing `klasifikasi_abc_id` via `updateManualImpor` (`{ produk_id, klasifikasi_abc_id }`) must preserve existing `out`, `review_period`, `stok_saat_ini`, and `harga_per_satuan`, and sync `produk.klasifikasi_abc_id` (including when `klasifikasi_abc_id` is explicitly `null`).

---

### Task 1: Schema Migration & Model Relations (`produk.klasifikasi_abc_id`)

**Files:**
- Create: `database/migrations/2026_11_11_000001_add_klasifikasi_abc_id_to_produk_table.php`
- Modify: `app/Models/Produk.php:17-39,97-111`
- Modify: `app/Models/KlasifikasiAbc.php:1-44`
- Test: `tests/Feature/SkuKlasifikasiAbcTest.php`

**Interfaces:**
- Produces:
  - DB column `produk.klasifikasi_abc_id` (nullable FK to `klasifikasi_abc.id`, `nullOnDelete()`, index `idx_produk_klasifikasi_abc_id`)
  - `Produk::klasifikasiAbc(): BelongsTo`
  - `KlasifikasiAbc::produks(): HasMany`

- [ ] **Step 1: Write the failing test in `tests/Feature/SkuKlasifikasiAbcTest.php`**

Create `tests/Feature/SkuKlasifikasiAbcTest.php` with `RefreshDatabase`, `$this->seed()`, `Model::preventLazyLoading(true)`, and test `test_produk_and_klasifikasi_abc_relations_and_migration_column_exist`:
- Assert `Schema::hasColumn('produk', 'klasifikasi_abc_id')` is true.
- Create/update a `Produk` with `klasifikasi_abc_id` pointing to `KlasifikasiAbc` (`kode = 'a'`), load `Produk::with('klasifikasiAbc')` and `KlasifikasiAbc::with('produks')`, assert `$produk->klasifikasiAbc->kode === 'a'` and `$abc->produks->contains('id', $produk->id)`.
- Assert `MasterDataAudit` records `klasifikasi_abc_id` change on `Produk`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php --filter=test_produk_and_klasifikasi_abc_relations_and_migration_column_exist`
Expected: FAIL (`Schema::hasColumn` false / undefined relation `klasifikasiAbc`).

- [ ] **Step 3: Create migration `database/migrations/2026_11_11_000001_add_klasifikasi_abc_id_to_produk_table.php`**

Implement `up()`:
- Check `! Schema::hasColumn('produk', 'klasifikasi_abc_id')` and add nullable `foreignId('klasifikasi_abc_id')->nullable()->after('profil_analisa')->constrained('klasifikasi_abc')->nullOnDelete()` plus `$table->index('klasifikasi_abc_id', 'idx_produk_klasifikasi_abc_id')`.
- Idempotent backfill from non-null `analisa_impor.klasifikasi_abc_id`, then `analisa_impor_meta.klasifikasi_abc_id`, updating `produk.klasifikasi_abc_id` where `produk.klasifikasi_abc_id IS NULL` and `produk.tipe != 'produk_jadi'`.
Implement `down()`:
- Check `Schema::hasColumn('produk', 'klasifikasi_abc_id')`, drop foreign key (`dropForeign(['klasifikasi_abc_id'])`), drop index `idx_produk_klasifikasi_abc_id`, and drop column.

- [ ] **Step 4: Update `app/Models/Produk.php` and `app/Models/KlasifikasiAbc.php`**

- In `Produk`: add `'klasifikasi_abc_id'` to `$fillable`, `'klasifikasi_abc_id' => 'integer'` to `$casts`, and `public function klasifikasiAbc(): BelongsTo`.
- In `KlasifikasiAbc`: add `public function produks(): HasMany`.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php --filter=test_produk_and_klasifikasi_abc_relations_and_migration_column_exist`
Expected: PASS (1 test passing).

---

### Task 2: Domain Service (`AnalisaService` Normalization, Two-Way ABC Sync & Profile Isolation)

**Files:**
- Modify: `app/Services/AnalisaService.php:50-65,220-290,310-325,410-435,810-960,975-1085,1190-1291`
- Test: `tests/Feature/SkuKlasifikasiAbcTest.php`

**Interfaces:**
- Consumes: `Produk::klasifikasiAbc`, `KlasifikasiAbc::produks` (Task 1)
- Produces:
  - `AnalisaService::isImporProfile(?string $profilAnalisa): bool`
  - `AnalisaService::cleanupWorkingTablesForProfile(Produk $produk, ?string $targetProfil): void`
  - `AnalisaService::syncProdukKlasifikasiAbc(Produk $produk, ?int $klasifikasiAbcId, array $options = []): void`
  - `AnalisaService::assignKlasifikasiAbcToSkus(int $klasifikasiAbcId, array $produkIds, bool $ensureAnalisaRow = false, ?int $userId = null): int`
  - `AnalisaService::addSkuToAnalisa(Produk $produk, string $targetProfil, ?int $klasifikasiAbcId = null, array $initialParams = [], ?int $userId = null): void`

- [ ] **Step 1: Write failing tests for service behavior in `tests/Feature/SkuKlasifikasiAbcTest.php`**

Add tests:
1. `test_generate_impor_includes_produk_with_profil_analisa_impor_and_uses_master_abc`:
   - Create `Produk` (`tipe = 'kemas'`, `profil_analisa = 'impor'`, `klasifikasi_abc_id = Class B`), run `$service->generateImpor(null, $user->id)`, assert `AnalisaImpor` exists with `klasifikasi_abc = 'b'`, `klasifikasi_abc_id = Class B`, `tambahan_buffer_hari = 2`.
2. `test_unassigning_abc_keeps_klasifikasi_abc_id_null_after_regenerate`:
   - Call `syncProdukKlasifikasiAbc($produk, null, ['ensure_analisa_row' => true])`, then call `$service->generateImpor($produk->id, $user->id)`, assert `produk.klasifikasi_abc_id` is `null` AND `analisa_impor.klasifikasi_abc_id` is `null` (with `klasifikasi_abc = 'c'` and `tambahan_buffer_hari = 0`).
3. `test_switching_profile_cleans_stale_working_tables_and_isolates_summaries`:
   - Create SKU with `profil_analisa = 'lokal'`, run `generateLokal($p->id)`, assert in `AnalisaLokal` / `RekomendasiOrderLokal`.
   - Call `addSkuToAnalisa($p, 'impor', $abcA->id)` → assert removed from `AnalisaLokal` / `RekomendasiOrderLokal` / `LeadTimeLokal`, present in `AnalisaImpor` with `klasifikasi_abc = 'a'`, and `finalisasi('bahan_lokal')` does NOT include it while `finalisasi('bahan_impor')` DOES include it; `getBatasMinimumMap()` and `getItemPerluOrderSummary()` reflect Impor only.
   - Call `addSkuToAnalisa($p, 'lokal')` → assert removed from `AnalisaImpor` / `LeadTimeImpor`, `produk.klasifikasi_abc_id === null`, present in `AnalisaLokal`.
4. `test_assign_klasifikasi_abc_rejects_produk_jadi`:
   - Create `produk_jadi`, call `syncProdukKlasifikasiAbc($pj, $abcA->id)`, expect `ValidationException`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php`
Expected: FAIL (undefined methods on `AnalisaService` and missing `'impor'` query match).

- [ ] **Step 3: Implement `AnalisaService` methods & profile normalization**

In `app/Services/AnalisaService.php`:
- Add `isImporProfile(?string $profilAnalisa): bool` (`in_array($profilAnalisa, ['impor', 'bahan_impor'], true)`).
- Update `generateImpor()` query (`whereIn('tipe', ['bahan', 'kemas'])` and `whereIn('profil_analisa', ['impor', 'bahan_impor'])` or legacy `whereNull('profil_analisa')` with existing `analisaImpor`/`leadTimeImpor`/`analisaImporMeta`).
- Update `compareSuppliersForProduct()` and `selectSupplierForProduct()` to use `self::isImporProfile($produk->profil_analisa)`.
- Update ABC resolution inside `generateImpor()` so an existing `AnalisaImpor` with `klasifikasi_abc_id === null` (when `$p->klasifikasi_abc_id === null` and `$meta?->klasifikasi_abc_id === null`) preserves `klasifikasi_abc_id = null`, `klasifikasi_abc = 'c'`, `tambahan_buffer_hari = 0`, while reading `$p->klasifikasi_abc_id` on new generation when set.
- Implement `cleanupWorkingTablesForProfile()`, `syncProdukKlasifikasiAbc()`, `assignKlasifikasiAbcToSkus()`, and `addSkuToAnalisa()`.
- Add strict profile/active/type filtering on `finalisasi()`, `getBatasMinimumMap()`, and `getItemPerluOrderSummary()`.

- [ ] **Step 4: Run tests (new + existing engine tests) to verify pass**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php tests/Feature/AnalisaImporEngineTest.php tests/Feature/AnalisaLokalEngineTest.php tests/Feature/AnalisaMultiSupplierLeadTimeTest.php tests/Feature/AnalisaSupplierRelationTest.php`
Expected: All PASS.

---

### Task 3: Master Produk Integration (`ProdukController` & Blade Views)

**Files:**
- Modify: `app/Http/Controllers/ProdukController.php:15-115,180-250,330-382`
- Modify: `resources/views/produk/_form.blade.php:59-145,244-252`
- Modify: `resources/views/produk/index.blade.php:63-80,138-150`
- Test: `tests/Feature/SkuKlasifikasiAbcTest.php`

**Interfaces:**
- Consumes: `AnalisaService::isImporProfile`, `cleanupWorkingTablesForProfile`, `syncProdukKlasifikasiAbc` (Task 2)
- Produces: Form inputs `profil_analisa` & `klasifikasi_abc_id` in Master Produk + `klasifikasi_abc_badge` column in `produk.data`.

- [ ] **Step 1: Write failing tests for Master Produk CRUD & ABC syncing in `tests/Feature/SkuKlasifikasiAbcTest.php`**

Add tests:
1. `test_master_produk_create_and_edit_with_klasifikasi_abc_syncs_analisa_impor`:
   - `GET route('produk.create')` returns 200, contains `klasifikasi_abc_id`, does NOT contain `max-w-4xl`.
   - `POST route('produk.store')` with `tipe = 'bahan'`, `profil_analisa = 'impor'`, `klasifikasi_abc_id = Class A`: saves `produk.klasifikasi_abc_id = A`, does NOT eagerly create an empty `analisa_impor` row (`assertDatabaseMissing('analisa_impor', ['produk_id' => $p->id])`).
   - Then run `generateImpor()`, verify `analisa_impor` picks up Class A (`+4` buffer).
   - `PUT route('produk.update', $p)` changing `klasifikasi_abc_id` to Class B: updates both `produk` and existing `analisa_impor` (`tambahan_buffer_hari = 2`).
   - `PUT route('produk.update', $p)` switching `profil_analisa` to `'lokal'` (or `tipe` to `'produk_jadi'`): clears `produk.klasifikasi_abc_id = null` and removes stale `analisa_impor` row.
2. `test_produk_datatable_endpoint_eager_loads_klasifikasi_abc_without_lazy_loading`:
   - Call `GET route('produk.data')` under `Model::preventLazyLoading(true)`, assert 200 and JSON contains `klasifikasi_abc_badge`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php --filter=test_master_produk`
Expected: FAIL (`klasifikasi_abc_id` not persisted / `klasifikasi_abc_badge` missing).

- [ ] **Step 3: Update `ProdukController` and `resources/views/produk/`**

- Update `ProdukController::create()`, `edit()`, `validated()`, `store()`, `update()`, `data()` per spec Section 5.4.A.
- Update `resources/views/produk/_form.blade.php` with Alpine state `profilAnalisa` & `selectedKlasifikasiAbcId` and conditional `Klasifikasi ABC (Impor)` select (must NOT add `max-w-4xl`).
- Update `resources/views/produk/index.blade.php` table header & DataTables column `klasifikasi_abc_badge`.

- [ ] **Step 4: Run tests to verify pass**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php tests/Feature/ViewRenderingSafetyTest.php tests/Feature/ProdukMultiSupplierTest.php tests/Feature/KategoriVarianProdukTest.php`
Expected: All PASS.

---

### Task 4: Master Klasifikasi ABC SKU Management & RBAC Alignment

**Files:**
- Modify: `routes/web.php:50-66`
- Modify: `resources/views/components/app-layout.blade.php:299-308`
- Modify: `app/Http/Controllers/KlasifikasiAbcController.php:1-159`
- Modify: `resources/views/klasifikasi-abc/index.blade.php:1-321`
- Test: `tests/Feature/SkuKlasifikasiAbcTest.php`

**Interfaces:**
- Consumes: `AnalisaService::assignKlasifikasiAbcToSkus`, `syncProdukKlasifikasiAbc` (Task 2)
- Produces:
  - `GET route('klasifikasi-abc.available-skus')`
  - `GET route('klasifikasi-abc.skus', $klasifikasiAbc)`
  - `POST route('klasifikasi-abc.assign-skus', $klasifikasiAbc)`
  - `DELETE route('klasifikasi-abc.unassign-sku', [$klasifikasiAbc, $produk])`

- [ ] **Step 1: Write failing tests for Klasifikasi ABC endpoints & RBAC in `tests/Feature/SkuKlasifikasiAbcTest.php`**

Add tests:
1. `test_klasifikasi_abc_assign_and_unassign_skus_end_to_end`:
   - `GET route('klasifikasi-abc.available-skus')` returns active `bahan`/`kemas` SKUs (excludes `produk_jadi`).
   - `POST route('klasifikasi-abc.assign-skus', $abcA)` with multiple `produk_ids` (including a `lokal` SKU): promotes to `impor`, sets `produk.klasifikasi_abc_id = $abcA->id`, cleans stale `analisa_lokal` if any, syncs `analisa_impor` when working table exists.
   - `GET route('klasifikasi-abc.skus', $abcA)` returns assigned SKUs.
   - `DELETE route('klasifikasi-abc.unassign-sku', [$abcA, $sku])` sets `produk.klasifikasi_abc_id = null` and `analisa_impor.klasifikasi_abc_id = null` and SKU no longer appears in `$abcA` OR `$abcC` `skus()` / `penggunaan_count`.
   - `DELETE route('klasifikasi-abc.destroy', $abcA)` returns 422 while a `Produk` still references it.
2. `test_klasifikasi_abc_rbac_uses_analisa_permissions`:
   - Custom role with `analisa.view` + `analisa.manage` (no `produk.view`) succeeds (`200`) on `klasifikasi-abc.index`, `options`, `available-skus`, and `assign-skus`.
   - `gudang` user (`produk.view` without `analisa.view`) receives `403` on `klasifikasi-abc.index` & `options`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php --filter=test_klasifikasi_abc`
Expected: FAIL (missing routes / `RouteNotFoundException`).

- [ ] **Step 3: Implement routes, sidebar RBAC, `KlasifikasiAbcController`, and `klasifikasi-abc/index.blade.php` modal**

- Update `routes/web.php` to move `klasifikasi-abc.*` under `can:analisa.view` (and mutations under `can:analisa.manage`) and register `available-skus`, `skus`, `assign-skus`, `unassign-sku`.
- Update `resources/views/components/app-layout.blade.php` line 300-308 to check `@can('analisa.view')` for Klasifikasi ABC menu item (placed cleanly in Master Data or visible to `analisa.view` users).
- Update `KlasifikasiAbcController` (`data`, `availableSkus`, `skus`, `assignSkus`, `unassignSku`, `destroy`).
- Update `resources/views/klasifikasi-abc/index.blade.php` with clickable `penggunaan_count`, `"Kelola SKU"` action button, and **Modal Kelola SKU** (search, profile filter, multi-select checkboxes, profile switch warning badge, assigned SKU list with `"Keluarkan"` button).

- [ ] **Step 4: Run tests to verify pass**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php tests/Feature/AnalisaImporEngineTest.php tests/Feature/AuthorizationTest.php`
Expected: All PASS.

---

### Task 5: Analisa Stok Entry Point (`+ Tambah SKU` Impor/Lokal, Quick ABC, & Strict Tab Isolation)

**Files:**
- Modify: `routes/web.php:222-252`
- Modify: `app/Http/Controllers/AnalisaController.php:56-185,344-525,666-812`
- Create: `resources/views/analisa/partials/modal-add-sku.blade.php`
- Modify: `resources/views/analisa/index.blade.php`
- Modify: `resources/views/analisa/partials/table-impor.blade.php`
- Modify: `resources/views/analisa/partials/modal-manual-impor.blade.php`
- Test: `tests/Feature/SkuKlasifikasiAbcTest.php`

**Interfaces:**
- Consumes: `AnalisaService::addSkuToAnalisa`, `syncProdukKlasifikasiAbc`, `isImporProfile` (Task 2)
- Produces:
  - `GET route('analisa.available-skus')`
  - `POST route('analisa.add-sku')`
  - Strict profile filtering in `lokalData()`, `imporData()`, `createPoForm()`

- [ ] **Step 1: Write failing tests for Analisa Stok SKU addition, quick ABC update, & tab isolation in `tests/Feature/SkuKlasifikasiAbcTest.php`**

Add tests:
1. `test_analisa_add_sku_allows_choosing_impor_with_abc_or_lokal_and_isolates_tabs`:
   - `GET route('analisa.available-skus')` returns active `bahan`/`kemas` SKUs with `profil_analisa`, `klasifikasi_abc_id`, `di_analisa_lokal`, `di_analisa_impor`.
   - `POST route('analisa.add-sku')` with `target_profil = 'impor'`, `klasifikasi_abc_id = $abcA->id`, `out = 5000`: sets `produk.profil_analisa = 'impor'`, `produk.klasifikasi_abc_id = $abcA->id`, creates `AnalisaImpor`, removes from `AnalisaLokal`/`RekomendasiOrderLokal`, appears in `GET route('analisa.impor.data')` and NOT in `GET route('analisa.lokal.data')`.
   - `POST route('analisa.add-sku')` on same SKU with `target_profil = 'lokal'`, `terjual_rata_rata_4bulan = 3000`: moves to `profil_analisa = 'lokal'`, clears `produk.klasifikasi_abc_id = null`, deletes `AnalisaImpor`, appears in `lokal.data` and NOT in `impor.data`.
2. `test_update_manual_impor_partial_abc_change_preserves_metrics_and_syncs_produk`:
   - With existing `AnalisaImpor` (`out = 5000`, `stok_saat_ini = 1200`, `klasifikasi_abc = 'a'`), send `POST route('analisa.update-manual-impor')` with `['produk_id' => $p->id, 'klasifikasi_abc_id' => $abcB->id]`: assert `out === 5000`, `stok_saat_ini === 1200`, `analisa_impor.klasifikasi_abc === 'b'`, `produk.klasifikasi_abc_id === $abcB->id`.
   - Send `['produk_id' => $p->id, 'klasifikasi_abc_id' => null]`: assert `produk.klasifikasi_abc_id === null` and `analisa_impor.klasifikasi_abc_id === null`.
3. `test_add_sku_requires_analisa_manage_permission`:
   - User with only `analisa.view` gets `403` on `POST route('analisa.add-sku')`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php --filter=test_analisa_add_sku`
Expected: FAIL (`Route [analisa.add-sku] not defined`).

- [ ] **Step 3: Implement AnalisaController endpoints, filtering, and Blade views**

- Add `analisa.available-skus` & `analisa.add-sku` routes in `routes/web.php`.
- Update `AnalisaController`:
  - Add `availableSkus()` and `addSku()`.
  - Update `updateManualImpor()` to handle `array_key_exists('klasifikasi_abc_id', $data)` including explicit `null`, promote non-impor `profil_analisa` to `'impor'`, and sync `produk.klasifikasi_abc_id` cleanly without overwriting omitted fields (`out`, `stok_saat_ini`, etc.).
  - Filter `lokalData()`, `imporData()`, and `createPoForm()` strictly by active `bahan`/`kemas` & profile (`lokal`/`null` vs `['impor','bahan_impor']`).
- Create `resources/views/analisa/partials/modal-add-sku.blade.php` and integrate into `resources/views/analisa/index.blade.php` (button `+ Tambah SKU` on `lokal` & `impor` tabs, modal choosing target profile Impor vs Lokal, quick-ABC change helper).
- Update `resources/views/analisa/partials/table-impor.blade.php` (distinguish assigned ABC badge vs `Default (C)` when `klasifikasi_abc_id === null`, plus inline `<select>` quick-change for `@can('analisa.manage')`) and `modal-manual-impor.blade.php` (add empty option `— Default (Tanpa Kelas / +0 Hari) —` so clearing ABC works from modal too).

- [ ] **Step 4: Run tests to verify pass**

Run: `php artisan test tests/Feature/SkuKlasifikasiAbcTest.php tests/Feature/AnalisaImporEngineTest.php tests/Feature/AnalisaLokalEngineTest.php tests/Feature/AnalisaSupplierRelationTest.php`
Expected: All PASS.

---

### Task 6: Asset Build, Full Suite Verification, Fresh Final Review, & `.agent/` Docs

**Files:**
- Create/Update: `.agent/sku-klasifikasi-abc-context.md` (FASE 8 documentation update)

- [ ] **Step 1: Build Vite frontend assets**

Run: `npm run build`
Expected: Exit code 0, Vite build succeeds without errors.

- [ ] **Step 2: Run full PHPUnit test suite**

Run: `php artisan test`
Expected: 100% tests pass, 0 failures, 0 skipped assertions introduced.

- [ ] **Step 3: Fresh Final Code Review**

Dispatch a fresh `research` subagent against `git diff` + `SkuKlasifikasiAbcTest.php` + spec + Review Focus list. Re-grade any findings and fix Critical/Important issues via TDD RED→GREEN, then re-run `npm run build` and `php artisan test`.

- [ ] **Step 4: Update `.agent/sku-klasifikasi-abc-context.md` & verify `git status`**

Document final architecture, endpoints, data flow, and verification summary in `.agent/`.

