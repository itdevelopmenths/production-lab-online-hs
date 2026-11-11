# Operational Bahan Baku & Produk Jadi Granular Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement granular, UI-customizable Request & Transfer permissions split by product category (`bahan` vs `produk_jadi`) so role `operasional` can request Bahan Baku and receive incoming Bahan Baku transfers without creating/shipping Bahan Baku transfers, while retaining permission to create and ship Produk Jadi transfers.

**Architecture:** Replace shared `rt.*` gates on active routes, controllers, and Blade views with split `request.*` and `transfer.*` gates backed by category-specific permissions (`request.{bahan,produk_jadi}.create` and `transfer.{bahan,produk_jadi}.{create,ship,receive}`). Centralize payload and model category resolution in a new `InventoryTransferAuthorizationService`, keep nested Gate evaluation safe in `AppServiceProvider`, and sync permissions via both a database migration and `PermissionSeeder`.

**Tech Stack:** Laravel 12, Spatie Laravel Permission, Blade + Alpine.js + Vite/Tailwind, PHPUnit (`RefreshDatabase` on SQLite `:memory:`, production target PostgreSQL).

**Spec:** [docs/superpowers/specs/2026-11-11-operasional-bahan-produk-jadi-permissions-design.md](file:///c:/Users/User/HS%20Project/production-lab-online-hs/docs/superpowers/specs/2026-11-11-operasional-bahan-produk-jadi-permissions-design.md)

## Global Constraints

- **Database parity:** Migrations must run idempotently on both PostgreSQL (production) and SQLite `:memory:` (tests).
- **Strict lazy loading:** `Model::preventLazyLoading(true)` runs in tests; always call `loadMissing(['items.produk:id,tipe', 'stockRequest:id,jenis'])` before inspecting model relations during authorization.
- **No static role hardcoding:** Remove `operasional`, `fulfillment`, and `gudang` role-name checks from `RequestController`, `TransferController`, `BatchController`, and `RequestService`; custom roles configured in `/roles` must obey the exact same rules.
- **Product classification:** `produk.tipe === 'produk_jadi'` maps to category `'produk_jadi'`; any other type (`'bahan'`, `'kemas'`, `'bahan_baku'`, `'kemasan'`, etc.) maps to category `'bahan'`.
- **TransferService domain invariance:** Do not inject HTTP/category gate checks inside `TransferService` directly so existing domain invariance tests (`RequestAndTransferSeparationTest`) remain valid; enforce HTTP authorization at routes, controllers, `InventoryTransferAuthorizationService`, and Blade UI.
- **Cancel permission default:** Keep `transfer.cancel` restricted to `manager` by default (opt-in via Edit Role); never grant `transfer.cancel`, `request.create`, `transfer.create`, `transfer.ship`, `transfer.receive`, or `rt.create` directly to `operasional` in seeder/migration.
- **Backward-compatible aliases:** Preserve legacy `request.create` -> `rt.create` (`Prd24EdgeCaseAndBugAnalysisTest`) and `rt.create` -> `request.create` / `transfer.create` (`RbacAndPermissionAliasTest`), while ensuring `request.create` or `request.bahan.create` NEVER grants `transfer.create`.

## Review Focus

1. **Nested Gate evaluation re-entrancy (`AppServiceProvider`):** Evaluating `can('transfer.bahan.create')` internally inspects global/legacy fallbacks (`transfer.create`, `rt.create`); a single `static bool $checking` would corrupt sibling nested Gate calls — use a per-ability stack array and non-reentrant permission lookup (`$user->hasPermissionTo` wrapped cleanly or `getAllPermissions()`).
2. **Item smuggling under `kirim_produk_jadi` or `antar_fulfillment` (`TransferController::store`):** Sending `jenis = kirim_produk_jadi` or `antar_fulfillment` with a `bahan`/`kemas`/`bahan_baku` item ID must resolve categories from BOTH `jenis`/`stockRequest` AND every `items.*.produk_id`, aborting `403` BEFORE `DB::beginTransaction()` when the actor lacks `transfer.bahan.create`.
3. **Unauthorized `?batch_id` prefill on `requests.create`:** Passing `?batch_id=...` as a user who only holds `request.produk_jadi.create` (e.g. `fulfillment` or a custom role) must abort `403` rather than prefilling raw materials for an unauthorized request type.
4. **Shipping/receiving category enforcement on existing draft/in-transit transfers:** Even if a Bahan Baku draft transfer exists in `transfers`, `operasional` must get `403` on `POST /transfers/{transfer}/ship` and NOT see the ship button in `transfers.show`, yet MUST pass `POST /transfers/{transfer}/receive` and see the receive button (subject to Segregation of Duties).
5. **Pre-existing production/staging database sync:** Fresh `PermissionSeeder` does not run during deploys on existing environments — a dedicated migration must sync `PermissionCatalogService` keys and update `operasional`, `gudang`, `fulfillment`, and `manager` system role permissions automatically.

---

### Task 1: Permission Catalog, Gate Resolution, Migration, and Seeder

**Files:**
- Modify: `app/Services/Authorization/PermissionCatalogService.php:112-149`
- Modify: `app/Providers/AppServiceProvider.php:22-74`
- Create: `database/migrations/2026_11_11_000001_add_granular_bahan_produk_jadi_permissions.php`
- Modify: `database/seeders/PermissionSeeder.php:14-135`
- Test: `tests/Feature/RbacAndPermissionAliasTest.php`
- Test: `tests/Feature/CustomRoleManagementTest.php`

**Interfaces:**
- Produces: Permission catalog keys `request.bahan.create`, `request.produk_jadi.create`, `transfer.bahan.create`, `transfer.bahan.ship`, `transfer.bahan.receive`, `transfer.produk_jadi.create`, `transfer.produk_jadi.ship`, `transfer.produk_jadi.receive` synced to DB.
- Produces: Laravel `Gate` behavior via `AppServiceProvider::boot()` for granular keys, coarse keys (`request.create`, `transfer.create`, `transfer.ship`, `transfer.receive`), and legacy `rt.*` keys.

- [ ] **Step 1: Write failing RBAC and custom role tests in `tests/Feature/RbacAndPermissionAliasTest.php` and `tests/Feature/CustomRoleManagementTest.php`**

Add assertions verifying:
- `RbacAndPermissionAliasTest::test_operasional_role_has_request_capabilities_and_cannot_approve_requests`: `operasional` has `request.bahan.create`, `transfer.bahan.receive`, `transfer.produk_jadi.create`, `transfer.produk_jadi.ship`; `can('request.create') === true`; `can('transfer.create') === true`; `can('transfer.ship') === true`; `can('transfer.receive') === true`; `can('request.produk_jadi.create') === false`; `can('transfer.bahan.create') === false`; `can('transfer.bahan.ship') === false`; `can('transfer.produk_jadi.receive') === false`; `can('transfer.cancel') === false`.
- `RbacAndPermissionAliasTest::test_request_bahan_permission_does_not_leak_transfer_permission`: user given only `request.bahan.create` passes `can('request.create')` and `can('rt.create')`, but fails `can('transfer.create')`, `can('transfer.bahan.create')`, `can('transfer.produk_jadi.create')`.
- `RbacAndPermissionAliasTest::test_nested_gate_checks_do_not_suffer_reentrancy_bypass`: checking `can('transfer.bahan.create')` and `can('transfer.produk_jadi.create')` on users holding only one granular key or `transfer.create` yields consistent results in any call order.
- `RbacAndPermissionAliasTest::test_migration_syncs_new_permissions_and_strips_rt_create_from_existing_operasional_role`: simulates an existing `operasional` role holding legacy `rt.create`/`transfer.ship`, executes the migration's `up()` method idempotently, and asserts the 8 granular permissions exist and `operasional` has `request.bahan.create` / `transfer.bahan.receive` / `transfer.produk_jadi.create` / `transfer.produk_jadi.ship` without `rt.create` or `transfer.bahan.create`.
- `CustomRoleManagementTest::test_granular_bahan_and_produk_jadi_permissions_visible_in_role_ui_and_assignable`: `GET roles.create` displays all 8 new permission keys; storing a role with `['request.bahan.create', 'transfer.bahan.receive', 'transfer.produk_jadi.create']` syncs them without `rt.create`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/RbacAndPermissionAliasTest.php tests/Feature/CustomRoleManagementTest.php`
Expected: FAIL because new permission keys and Gate rules do not exist yet.

- [ ] **Step 3: Add new granular permission keys to `PermissionCatalogService::getGroupedCatalog()`**

In `app/Services/Authorization/PermissionCatalogService.php` under `'Operasional & Logistik'`:
- Under `'request'`: add `'request.bahan.create' => 'Buat Request Bahan Baku & Kemasan'` and `'request.produk_jadi.create' => 'Buat Request Produk Jadi'`. Update `'request.create'` label to `'Buat Semua Jenis Permintaan Barang (Global)'`.
- Under `'transfer'`: add `'transfer.bahan.create'`, `'transfer.bahan.ship'`, `'transfer.bahan.receive'`, `'transfer.produk_jadi.create'`, `'transfer.produk_jadi.ship'`, `'transfer.produk_jadi.receive'`. Update `'transfer.create'`, `'transfer.ship'`, `'transfer.receive'` labels to clarify global scope across all categories.

- [ ] **Step 4: Update `Gate::before()` resolution in `AppServiceProvider::boot()`**

In `app/Providers/AppServiceProvider.php`:
- Replace `static $checking = false` with `static array $activeChecks = []` keyed by `$ability` so nested Gate lookups for different abilities never short-circuit each other.
- Helper checking `$user->hasRole('manager')` stays at top returning `true`.
- Helper evaluating permission membership via `$user->getAllPermissions()->pluck('name')->all()` (cached per-request by Spatie, or checking `in_array($key, $granted, true)` directly without re-entering `Gate`) so Gate resolution checks exact keys, coarse keys, and legacy keys deterministically:
  - `request.bahan.create` / `request.produk_jadi.create` <= exact OR `request.create` OR `rt.create`
  - `transfer.bahan.create` / `transfer.produk_jadi.create` <= exact OR `transfer.create` OR `rt.create`
  - `transfer.bahan.ship` / `transfer.produk_jadi.ship` <= exact OR `transfer.ship` OR `rt.ship`
  - `transfer.bahan.receive` / `transfer.produk_jadi.receive` <= exact OR `transfer.receive` OR `rt.receive`
  - `request.create` <= `request.create` OR `request.bahan.create` OR `request.produk_jadi.create` OR `rt.create`
  - `transfer.create` <= `transfer.create` OR `transfer.bahan.create` OR `transfer.produk_jadi.create` OR `rt.create`
  - `transfer.ship` <= `transfer.ship` OR `transfer.bahan.ship` OR `transfer.produk_jadi.ship` OR `rt.ship`
  - `transfer.receive` <= `transfer.receive` OR `transfer.bahan.receive` OR `transfer.produk_jadi.receive` OR `rt.receive`
  - Legacy `rt.*`: map cleanly per §5.4 of spec (`rt.create` <= any `request.{,bahan.,produk_jadi.}create` or `transfer.{,bahan.,produk_jadi.}create` or `rt.create`; never bridge `request.*` into `transfer.*`).
- Wait cleanly for Spatie fallback (`return $granted ? true : null;` only returning `true` when matched so explicit Spatie denials or standard permissions keep working).

- [ ] **Step 5: Update `PermissionSeeder.php` and create migration `2026_11_11_000001_add_granular_bahan_produk_jadi_permissions.php`**

- Sync system roles identically in both `PermissionSeeder::run()` and the new migration's `up()`:
  - `operasional`: `'request.view'`, `'request.bahan.create'`, `'request.submit'`, `'request.cancel'`, `'transfer.view'`, `'transfer.bahan.receive'`, `'transfer.produk_jadi.create'`, `'transfer.produk_jadi.ship'`, `'defect.view'`, `'rt.view'`, `'rt.submit'` (+ existing non-transfer master/stok/batch/report perms in `PermissionSeeder`). Notice `rt.create`, `rt.ship`, `rt.receive`, `request.create`, `transfer.create`, `transfer.ship`, `transfer.receive`, `transfer.cancel` are NOT in `operasional`.
  - `gudang`: replace generic `rt.create`/`transfer.create`/`transfer.ship`/`transfer.receive` with explicit `'transfer.bahan.create'`, `'transfer.bahan.ship'`, `'transfer.bahan.receive'`, `'transfer.produk_jadi.create'`, `'transfer.produk_jadi.ship'`, `'transfer.produk_jadi.receive'` (plus `'request.view'`, `'transfer.view'`, `'defect.view'`, `'rt.view'`, `'rt.submit'`, `'rt.process'`).
  - `fulfillment`: `'request.view'`, `'request.produk_jadi.create'`, `'request.submit'`, `'request.cancel'`, `'transfer.view'`, `'transfer.produk_jadi.create'`, `'transfer.produk_jadi.ship'`, `'transfer.produk_jadi.receive'`, `'defect.view'`, `'rt.view'`.
  - `manager`: sync `Permission::all()`.
- In migration: call `app(PermissionCatalogService::class)->syncCatalogToDatabase()`, update existing `roles` if present (`Role::where('name', ...)->first()`), and forget cached permissions; in `down()`, remove the 8 new permission keys cleanly if present.

- [ ] **Step 6: Run tests and migration check to verify they pass**

Run: `php artisan test tests/Feature/RbacAndPermissionAliasTest.php tests/Feature/CustomRoleManagementTest.php tests/Feature/Prd24EdgeCaseAndBugAnalysisTest.php tests/Feature/AuthorizationTest.php`
Expected: PASS (0 failures). Also test migration on fresh SQLite via `php artisan test` / explicit migration verification test in `RbacAndPermissionAliasTest`.

- [ ] **Step 7: Commit Task 1**

```bash
git add app/Services/Authorization/PermissionCatalogService.php app/Providers/AppServiceProvider.php database/migrations/2026_11_11_000001_add_granular_bahan_produk_jadi_permissions.php database/seeders/PermissionSeeder.php tests/Feature/RbacAndPermissionAliasTest.php tests/Feature/CustomRoleManagementTest.php
git commit -m "feat(rbac): add granular bahan and produk_jadi request/transfer permissions"
```

---

### Task 2: `InventoryTransferAuthorizationService` Domain Helper

**Files:**
- Create: `app/Services/Authorization/InventoryTransferAuthorizationService.php`
- Test: `tests/Feature/GranularOperationalPermissionsTest.php`

**Interfaces:**
- Consumes: Laravel `User::can()`, `Produk::tipe`, `StockRequest::jenis`, `Transfer`, `BatchProduksi`.
- Produces (`App\Services\Authorization\InventoryTransferAuthorizationService`):
  - `public function isProdukJadiTipe(?string $tipe): bool`
  - `public function allowedBahanProductTipes(): array` (`['bahan', 'kemas', 'bahan_baku', 'kemasan']`)
  - `public function resolveProductCategories(iterable $produkIds): array` (subset of `['bahan', 'produk_jadi']`)
  - `public function resolveCategoriesForTransferPayload(string $jenis, ?StockRequest $stockRequest, array $produkIds): array`
  - `public function resolveCategoriesForTransfer(Transfer $transfer): array`
  - `public function resolveExplicitJenisItemMismatch(string $jenis, array $produkIds): ?string`
  - `public function resolveRequestItemCategoryViolations(string $jenis, array $produkIds): ?string`
  - `public function allowedRequestTypes(User $user): array`
  - `public function allowedRequestProductTipes(User $user): ?array`
  - `public function canCreateRequestForType(User $user, string $jenis): bool`
  - `public function canCreateRequestWithCategories(User $user, array $categories): bool`
  - `public function allowedTransferJenis(User $user): array`
  - `public function allowedTransferProductTipes(User $user): ?array`
  - `public function canCreateTransferCategory(User $user, string $category): bool`
  - `public function canCreateTransferForRequest(User $user, StockRequest $stockRequest): bool`
  - `public function canCreateTransferForBatch(User $user, BatchProduksi $batch): bool`
  - `public function canCreateTransferWithCategories(User $user, array $categories): bool`
  - `public function canShipCategories(User $user, array $categories): bool`
  - `public function canShipTransfer(User $user, Transfer $transfer): bool`
  - `public function canReceiveTransfer(User $user, Transfer $transfer): bool`
  - `public function canCancelTransfer(User $user, Transfer $transfer): bool`

- [ ] **Step 1: Write failing unit/feature tests in `tests/Feature/GranularOperationalPermissionsTest.php`**

Test all service contract methods under `Model::preventLazyLoading(true)`:
- `test_authorization_service_classifies_transfer_payload_with_smuggled_bahan_item`: `resolveCategoriesForTransferPayload('kirim_produk_jadi', null, [$bahan->id])` returns `['produk_jadi', 'bahan']`, `canCreateTransferWithCategories($operasional, ...)` returns `false`, and `resolveExplicitJenisItemMismatch('kirim_produk_jadi', [$bahan->id])` returns a non-null error string.
- `test_authorization_service_resolves_antar_fulfillment_by_items`: `antar_fulfillment` with `produk_jadi` items returns `['produk_jadi']` (allowed for `operasional`); with `bahan` items returns `['bahan']` (`false` for `operasional`, `true` for `gudang`); empty items fallback returns `['produk_jadi']`.
- `test_authorization_service_ship_receive_cancel_without_lazy_loading_violation`: freshly loaded `Transfer` models (`Transfer::find($trf->id)`) evaluated by `canShipTransfer`, `canReceiveTransfer`, `canCancelTransfer` do not throw `LazyLoadingViolationException`: `operasional` cannot ship Bahan Baku draft, can receive Bahan Baku `dikirim`, cannot cancel draft even if creator without `transfer.cancel`, and custom role with `transfer.cancel` + `transfer.produk_jadi.create` can cancel its own Produk Jadi draft while blocked from cancelling Bahan Baku drafts.
- `test_authorization_service_validates_request_item_categories`: `resolveRequestItemCategoryViolations('request_bahan', [$produkJadi->id])` and `resolveRequestItemCategoryViolations('request_produk_jadi', [$bahan->id])` return clear validation strings, matching payloads return `null`.

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test tests/Feature/GranularOperationalPermissionsTest.php`
Expected: FAIL with `Class "App\Services\Authorization\InventoryTransferAuthorizationService" not found`.

- [ ] **Step 3: Implement `InventoryTransferAuthorizationService.php`**

Create `app/Services/Authorization/InventoryTransferAuthorizationService.php` implementing every signature in **Interfaces** above cleanly with a shared helper `checkAllCategories(User $user, array $categories, string $action)` (no duplicated loops across `create`/`ship`/`receive`). Handle both `StockRequest`, `Transfer`, `BatchProduksi`, and `Produk::whereIn` lookups safely using `loadMissing(['items.produk:id,tipe', 'stockRequest:id,jenis'])`.

- [ ] **Step 4: Run tests to verify pass**

Run: `php artisan test tests/Feature/GranularOperationalPermissionsTest.php`
Expected: PASS (0 failures).

- [ ] **Step 5: Commit Task 2**

```bash
git add app/Services/Authorization/InventoryTransferAuthorizationService.php tests/Feature/GranularOperationalPermissionsTest.php
git commit -m "feat(rbac): add InventoryTransferAuthorizationService for category checks"
```

---

### Task 3: Request Authorization, Category Enforcement & UI Updates

**Files:**
- Modify: `routes/web.php:168-190`
- Modify: `app/Services/RequestService.php:36-109,200-245`
- Modify: `app/Http/Controllers/RequestController.php:17-458`
- Modify: `resources/views/requests/index.blade.php:25-34`
- Modify: `resources/views/requests/show.blade.php:60-76`
- Modify: `resources/views/requests/create.blade.php:55-70,228-242,291-300`
- Modify: `resources/views/requests/edit.blade.php:239-248`
- Modify: `resources/views/batches/show.blade.php:654,691`
- Test: `tests/Feature/GranularOperationalPermissionsTest.php`

**Interfaces:**
- Consumes: `InventoryTransferAuthorizationService` (`allowedRequestTypes`, `allowedRequestProductTipes`, `canCreateRequestForType`, `canCreateRequestWithCategories`, `resolveProductCategories`, `resolveRequestItemCategoryViolations`, `canCreateTransferForRequest`).
- Produces: Request routes gated on `request.{view,create,submit,approve,cancel}`; `RequestController::store/update` and `RequestService::validateCreatorRole` enforcing permission + item category integrity; `requests.data`, `requests.show`, `batches.show` respecting category permissions.

- [ ] **Step 1: Add failing HTTP & service tests to `tests/Feature/GranularOperationalPermissionsTest.php`**

Write tests verifying:
- `test_operasional_can_create_request_bahan_and_sees_batch_request_button_but_not_create_transfer_button`:
  - `GET batches.show` sees `Buat Request Bahan`.
  - `POST requests.store` (`request_bahan`) succeeds for `operasional`; `POST requests.store` (`request_produk_jadi`) returns `403`.
  - `GET requests.create?batch_id=...` as `fulfillment` (or custom role with only `request.produk_jadi.create`) returns `403`.
  - `POST requests.store` as `operasional` submitting `request_bahan` with a `produk_jadi` item returns `403` (missing `request.produk_jadi.create`), and as `manager` returns session validation error (`items`).
  - Once `request_bahan` is approved: `GET requests.show` as `operasional` does NOT contain `Buat Transfer`, `GET requests.data` JSON row for that request does NOT contain `Buat Transfer`, while `GET requests.show` & `requests.data` as `gudang` DO contain `Buat Transfer`.
- `test_custom_role_with_request_produk_jadi_create_works_without_role_name`:
  - Custom role `store_planner` with `request.view`, `request.produk_jadi.create`, `request.submit` can open `GET requests.create`, sees only `request_produk_jadi`, and succeeds `POST requests.store` with `produk_jadi` item.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/GranularOperationalPermissionsTest.php`
Expected: FAIL (operasional still sees `Buat Transfer`, `?batch_id` does not 403 for `fulfillment`, custom role fails `validateCreatorRole`, etc.).

- [ ] **Step 3: Update `routes/web.php` (`requests.*` group) and `RequestService`**

- In `routes/web.php` lines 169-189: replace `can:rt.view` -> `can:request.view`, `can:rt.create` -> `can:request.create`, `can:rt.submit` -> `can:request.submit`, `can:rt.approve` -> `can:request.approve`, `can:rt.cancel` -> `can:request.cancel`.
- In `app/Services/RequestService.php`:
  - Update `validateCreatorRole(User $user, string $jenis)` to check `$user->can('request.bahan.create')` / `$user->can('request.produk_jadi.create')` (via `InventoryTransferAuthorizationService::canCreateRequestForType`) instead of hardcoded role names `operasional` / `fulfillment`.
  - In `create()` and `update()`, check `resolveRequestItemCategoryViolations($jenis, $produkIds)` and throw `ValidationException::withMessages(['items' => $msg])` if violated.

- [ ] **Step 4: Update `RequestController.php` and Blade views (`requests/*`, `batches/show`)**

- In `RequestController.php`: inject `InventoryTransferAuthorizationService $authz`. Replace `rt.*` checks with `request.*`.
  - `data()` & `show()`: compute transfer button visibility via `$this->authz->canCreateTransferForRequest($user, $r)` and approval flag via `$user->can('request.approve')`.
  - `create()`: use `$allowedJenis = $this->authz->allowedRequestTypes($user)` (`abort_if(empty($allowedJenis), 403, ...)`). If `$request->filled('batch_id') && ! $this->authz->canCreateRequestForType($user, 'request_bahan')`, `abort(403, ...)`. Filter initial `$produk` by `$this->authz->allowedRequestProductTipes($user)` and only load `$activeBatches` / `$batchPrefills` when `in_array('request_bahan', $allowedJenis, true)`.
  - `store()`: BEFORE `DB::beginTransaction()`/`try`: call `abort_unless($this->authz->canCreateRequestForType($user, $validated['jenis']), 403, ...)`, and check `resolveProductCategories` via `canCreateRequestWithCategories($user, $categories)` (`abort_unless(..., 403, ...)` when user is non-manager lacking category permission).
  - `edit()` & `update()`: check `$this->authz->canCreateRequestForType($user, $stockRequest->jenis)` (`403`), check `canCreateRequestWithCategories` (`403`) in `update()`, and filter `$produk` in `edit()`.
- In `requests/index.blade.php` & `requests/show.blade.php`: change `@can('rt.create')` -> `@can('request.create')`, change `@if($canCreateTransfer && auth()->user()->can('rt.create'))` -> `@if($canCreateTransfer)`.
- In `requests/create.blade.php` & `requests/edit.blade.php`: ensure `filterTipe` uses `['bahan', 'kemas', 'bahan_baku', 'kemasan']` when `jenis === 'request_bahan'` and hide Batch Produksi picker block when `!in_array('request_bahan', $allowedJenis, true)` (`requests/create`).
- In `batches/show.blade.php` lines 654 & 691: change `@can('rt.create')` -> `@can('request.bahan.create')`.

- [ ] **Step 5: Run tests to verify pass**

Run: `php artisan test tests/Feature/GranularOperationalPermissionsTest.php tests/Feature/RequestAndTransferHttpTest.php tests/Feature/RequestAndTransferSeparationTest.php`
Expected: PASS (0 failures).

- [ ] **Step 6: Commit Task 3**

```bash
git add routes/web.php app/Services/RequestService.php app/Http/Controllers/RequestController.php resources/views/requests/index.blade.php resources/views/requests/show.blade.php resources/views/requests/create.blade.php resources/views/requests/edit.blade.php resources/views/batches/show.blade.php tests/Feature/GranularOperationalPermissionsTest.php
git commit -m "feat(rbac): separate request permissions by bahan and produk_jadi"
```

---

### Task 4: Transfer & Batch Dispatch Category Authorization, Route Separation, & Reactive Dropdown Filtering

**Files:**
- Modify: `routes/web.php:191-208,261-262`
- Modify: `app/Http/Controllers/TransferController.php:18-422`
- Modify: `app/Http/Controllers/BatchController.php:510-568`
- Modify: `resources/views/transfers/index.blade.php:8,63`
- Modify: `resources/views/transfers/create.blade.php:100-158,411-416,501-510`
- Modify: `resources/views/batches/show.blade.php:53,728`
- Modify: `resources/js/app.js:15-89`
- Test: `tests/Feature/GranularOperationalPermissionsTest.php`
- Test: `tests/Feature/RequestAndTransferHttpTest.php`

**Interfaces:**
- Consumes: `InventoryTransferAuthorizationService` (`allowedTransferJenis`, `allowedTransferProductTipes`, `canCreateTransferForRequest`, `canCreateTransferForBatch`, `resolveCategoriesForTransferPayload`, `resolveExplicitJenisItemMismatch`, `canCreateTransferWithCategories`, `canShipCategories`, `canShipTransfer`, `canReceiveTransfer`, `canCancelTransfer`).
- Produces: Split `transfers.*` (`transfer.view/create/ship/receive/cancel`) & `batches.kirim` (`transfer.produk_jadi.create`) route enforcement, strict category guard on all transfer actions, filtered `transfers/create` jenis + reactive product search dropdown, and full `RequestAndTransferHttpTest` & `GranularOperationalPermissionsTest` coverage.

- [ ] **Step 1: Add failing Transfer & Batch Dispatch HTTP permission tests to `tests/Feature/GranularOperationalPermissionsTest.php`**

Write tests verifying all remaining Review Focus and Spec §6 cases:
- `test_operasional_is_blocked_from_creating_or_shipping_bahan_transfers_via_every_entry_point`:
  - `GET transfers.create?request_id=<approved_request_bahan>` -> `403`.
  - `POST transfers.store` with `request_id=<approved_request_bahan>` -> `403`.
  - `POST transfers.store` with `jenis=pemenuhan_request_bahan` or `jenis=retur_bahan` -> `403`.
  - `POST transfers.store` with `jenis=kirim_produk_jadi` or `jenis=antar_fulfillment` + `items` containing a `bahan`/`kemas`/`bahan_baku` product -> `403`.
  - `GET transfers.create` as `operasional` renders `kirim_produk_jadi`/`antar_fulfillment`/`retur_produk_jadi` options, does NOT render `pemenuhan_request_bahan` or `retur_bahan` options, and configures `filterTipe` to exclude bahan.
  - Draft Bahan Baku transfer created by Gudang: `GET transfers.show` as `operasional` hides `Kirim Sekarang`, `POST transfers.ship` returns `403`.
- `test_operasional_can_receive_incoming_bahan_transfer_and_cannot_cancel_transfer_by_default`:
  - In-transit (`dikirim`) Bahan Baku transfer created by Gudang: `GET transfers.show` as `operasional` shows `Konfirmasi Penerimaan Barang`, `POST transfers.receive` succeeds and adds stock.
  - Draft `kirim_produk_jadi` transfer created by `operasional`: `POST transfers.cancel` returns `403` by default (since `transfer.cancel` is manager-only) and `Kirim Sekarang` IS visible (`$canShip = true`) while `POST transfers.ship` succeeds.
- `test_operasional_can_dispatch_finished_goods_from_batch_and_manual_transfer`:
  - `GET batches.show` (completed batch) as `operasional` sees `Kirim ke Fulfillment` & `Buat Transfer Produk Jadi`.
  - `POST batches.kirim` succeeds, creates draft `kirim_produk_jadi`.
  - `GET transfers.create?jenis=kirim_produk_jadi&batch_id=...` -> `200`.
  - `POST transfers.store` (`kirim_produk_jadi`, `produk_jadi` item, `action=ship`) -> succeeds and transitions transfer to `dikirim`.
- `test_custom_role_permissions_for_transfer_categories_and_scoped_cancel`:
  - Custom role granted `['transfer.view', 'transfer.bahan.create', 'transfer.bahan.ship', 'transfer.cancel']` can create/ship/cancel its own Bahan Baku draft, receives `403` on Produk Jadi transfer creation, and receives `403` attempting to cancel a Produk Jadi draft or another user's draft.
  - Custom role granted `['request.view', 'request.bahan.create']` only (no transfer permissions) gets `403` on `GET transfers.create`, `POST transfers.store`, and `POST batches.kirim`.

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test tests/Feature/GranularOperationalPermissionsTest.php`
Expected: FAIL (`operasional` currently gets `200`/`302` instead of `403` on Bahan Baku transfer routes and sees Bahan Baku options).

- [ ] **Step 3: Update `routes/web.php` (`transfers.*` & `batches.kirim`) and `BatchController::kirim`**

- In `routes/web.php`:
  - Lines 192-208 (`transfers.*`): replace `can:rt.view` -> `can:transfer.view`, `can:rt.create` -> `can:transfer.create`, `can:rt.ship` -> `can:transfer.ship`, `can:rt.receive` -> `can:transfer.receive`, `can:rt.cancel` -> `can:transfer.cancel`.
  - Line 262 (`batches.kirim`): replace `can:rt.create` -> `can:transfer.produk_jadi.create`.
- In `BatchController::kirim`: change `$this->authorize('rt.create')` -> `$this->authorize('transfer.produk_jadi.create')` and check output items via `InventoryTransferAuthorizationService`.

- [ ] **Step 4: Update `TransferController.php`**

Inject `InventoryTransferAuthorizationService $authz`. Replace all `$this->authorize('rt.*')` with `transfer.*` (`view`, `create`, `ship`, `receive`, `cancel`). Implement per §5.6.4:
- `create()`:
  - Guard `request_id` via `canCreateTransferForRequest($user, $stockRequest)` (`403`).
  - Guard `batch_id` via `canCreateTransferForBatch($user, $batch)` (`403`).
  - Guard explicit `jenis` param against `allowedTransferJenis($user)` (`403`, mapping legacy `'distribusi'` -> `'antar_fulfillment'`).
  - Pass `$allowedJenis`, `$canTransferBahan`, `$canTransferProdukJadi`, `$allowedProductTipes` to `transfers.create`, filter `$products` when `$allowedProductTipes !== null`, and choose `$defaultJenis` safely from `$allowedJenis`.
- `store()`:
  - Normalize `$jenis`, load optional `$stockReq`, compute `$produkIds` and `$categories = $this->authz->resolveCategoriesForTransferPayload($jenis, $stockReq, $produkIds)`.
  - **Before `DB::beginTransaction()`:** `abort_unless($this->authz->canCreateTransferWithCategories($user, $categories), 403, ...)`; if `action === 'ship'`, `abort_unless($this->authz->canShipCategories($user, $categories), 403, ...)`.
  - Inside transaction/try, check `resolveExplicitJenisItemMismatch($jenis, $produkIds)` and throw `ValidationException::withMessages(['items' => $mismatch])` if inconsistent.
- `show()`: compute `$canShip = $transfer->status === 'draft' && $this->authz->canShipTransfer($user, $transfer)`, `$canReceive = $transfer->status === 'dikirim' && $this->authz->canReceiveTransfer($user, $transfer) && ($isManager || (int) $transfer->created_by !== (int) $user->id)`, `$canCancelDraft = $this->authz->canCancelTransfer($user, $transfer)`.
- `ship()`, `receive()`, `cancel()`: enforce `canShipTransfer`, `canReceiveTransfer`, `canCancelTransfer` with `403`.

- [ ] **Step 5: Update `transfers/*`, `batches/show.blade.php`, and `resources/js/app.js`**

- In `resources/js/app.js`: inside `productSearchSelect::toggle()`, track `this._lastFilterSignature` from `filterTipe` and `supplierId` so if `filterTipe` changes (when switching `jenis` in `transfers/create` or `requests/create`), `this.items` resets and re-fetches immediately instead of serving cached options of the wrong product category.
- In `transfers/index.blade.php` lines 8 & 63: `@can('rt.create')` -> `@can('transfer.create')`.
- In `batches/show.blade.php` lines 53 & 728: `@can('rt.create')` -> `@can('transfer.produk_jadi.create')`.
- In `transfers/create.blade.php`:
  - Render only `$allowedJenis` options in `#transfer-jenis`.
  - Pass `canTransferBahan` & `canTransferProdukJadi` into `transferCreateData()`, add `getFilterTipe()` helper returning `['produk_jadi']` when `!canTransferBahan` (or when `jenis` is `kirim_produk_jadi`/`retur_produk_jadi`), `['bahan', 'kemas', 'bahan_baku', 'kemasan']` when `!canTransferProdukJadi` (or when `jenis` is `pemenuhan_request_bahan`/`retur_bahan`), or `null` when both are allowed under `antar_fulfillment`.
  - Wire `filterTipe: () => getFilterTipe()` into `productSearchSelect(...)`, plus reset mismatched rows when `jenis` changes.
  - Keep `items: @js($initialItems)` untouched so `RequestAndTransferHttpTest:271` regex match continues passing.

- [ ] **Step 6: Run tests and Vite build to verify pass**

Run: `npm run build`
Expected: PASS (`vite build` completes with exit code 0).
Run: `php artisan test tests/Feature/GranularOperationalPermissionsTest.php tests/Feature/RequestAndTransferHttpTest.php tests/Feature/RequestAndTransferSeparationTest.php tests/Feature/ProductionFlowTest.php tests/Feature/BatchMultiProductAndRequestLinkTest.php`
Expected: PASS (0 failures).

- [ ] **Step 7: Commit Task 4**

```bash
git add routes/web.php app/Http/Controllers/TransferController.php app/Http/Controllers/BatchController.php resources/views/transfers/index.blade.php resources/views/transfers/create.blade.php resources/views/batches/show.blade.php resources/js/app.js tests/Feature/GranularOperationalPermissionsTest.php
git commit -m "feat(rbac): enforce bahan vs produk_jadi permissions on transfer and batch dispatch"
```

---

### Task 5: Full Regression Suite Verification

**Files:**
- Verify: `tests/` (full suite) & `public/build/` (`npm run build`)

**Interfaces:**
- Consumes: All changes from Tasks 1–4.
- Produces: Clean passing build and full PHPUnit suite verification.

- [ ] **Step 1: Run `npm run build`**

Run: `npm run build`
Expected: Exit code 0.

- [ ] **Step 2: Run full PHPUnit test suite**

Run: `php artisan test`
Expected: All tests PASS with 0 failures and 0 errors.

- [ ] **Step 3: Check `git status` and review `git diff` summary**

Run: `git status` and `git log -n 6 --oneline`
Expected: Clean working tree with commits for Tasks 1–4.
