# Role Audit & Report Permission Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the "Laporan & Audit" permissions edited in the role matrix (`roles/edit`) actually grant/revoke what they claim, and stop the edit form from silently misleading or corrupting permissions.

**Architecture:** Gate the two ungated audit-history cards with the same `@canany` pattern already used on batch/purchasing pages; align the Laporan menu gate with the route gate; remove a dead catalog permission; make `RoleService::updateRole` preserve non-catalog permissions and ignore edits to `manager`; make the edit view readonly for `manager`.

**Tech Stack:** Laravel 13, PHP 8.3, Spatie laravel-permission, Blade + Alpine, PHPUnit (SQLite in-memory).

**Spec:** Review findings from `/code-review` on `resources/views/roles/edit.blade.php` (no separate spec doc). Business rules: [DOKUMENTASI_FLOW_BISNIS.md](../../../DOKUMENTASI_FLOW_BISNIS.md) role matrix. Existing audit behavior: `app/Http/Controllers/OperationalAuditController.php`, `database/migrations/2026_09_18_000003_add_audit_permissions.php`.

## Global Constraints

- Tests run on in-memory SQLite; feature tests call `$this->seed()` and log in as seeded users (`manager@`, `purchasing@`, `gudang@`, `operasional@heavenscent.id`).
- `Model::preventLazyLoading` is on in tests: eager-load what views touch.
- New permission names go in both `PermissionCatalogService` and `PermissionSeeder`; aliases go in `AppServiceProvider::boot` `Gate::before`, never controllers.
- UI text is Indonesian. Match existing naming.
- Never add Co-Authored-By or any Claude attribution to commits (user's global CLAUDE.md).
- Run `graphify update .` after code changes (project CLAUDE.md).

## Review Focus

- Role with only `audit.view`: must see audit cards on request, transfer, batch, PO pages (global override).
- Role with `rt.audit` only: sees request + transfer cards, not batch/PO cards.
- Role with neither: sees no audit card on request/transfer pages (page itself still loads).
- Role holding non-catalog permission (`reports.view`, `gudang.audit`) saves the edit form: permission survives.
- Manager role edited via PUT with fewer permissions: stored permissions unchanged, no 500.
- Role holding legacy `reports.view` but not `report.view`: no Laporan menu.

## Dropped review findings (verified not real or not worth a change)

- Finding 5 (`operational-audit/data` open): controller builds `$allowedModules` from permissions and applies `whereRaw('1 = 0')` when empty, so a user with no audit permission gets zero rows. Already deny-by-default.
- Finding 8 (matrix repopulation): an all-unchecked submit has no `permissions` key, which is indistinguishable from "user unchecked everything"; falling back to DB state would resurrect permissions the user removed.
- Finding 7 (name slug vs unique) is a separate bug, not an audit-permission issue. Out of scope; raise separately.

## File Structure

- Modify `resources/views/requests/show.blade.php:261-264` and `resources/views/transfers/show.blade.php:338-339`: gate audit card.
- Modify `resources/views/components/app-layout.blade.php:424`: nav gate.
- Modify `app/Services/Authorization/PermissionCatalogService.php:225-227`: drop `report.export`, clarify `audit.view` label.
- Modify `app/Services/Authorization/RoleService.php:54-76`: preserve non-catalog permissions; skip permission sync for `manager`.
- Modify `resources/views/roles/edit.blade.php:10-14,46,50-62`: manager readonly + notice.
- Modify `database/seeders/PermissionSeeder.php`: keep default audit permissions on re-seed.
- Test: `tests/Feature/AuditPermissionVisibilityTest.php` (new), `tests/Feature/CustomRoleManagementTest.php` (extend).

---

### Task 1: Gate audit cards on request and transfer detail pages

**Files:**
- Modify: `resources/views/requests/show.blade.php:261-264`
- Modify: `resources/views/transfers/show.blade.php:338-339`
- Test: `tests/Feature/AuditPermissionVisibilityTest.php` (create)

**Interfaces:**
- Consumes: permissions `rt.audit`, `audit.view` (exist in catalog and DB); routes `requests.show`, `transfers.show`; factories/fixtures used by `tests/Feature/OperationalAuditTest.php` and `GranularOperationalPermissionsTest.php` for creating a `RequestTransfer`/transfer document.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write failing tests** in `AuditPermissionVisibilityTest` (seeded, `RefreshDatabase`). Create a request and a transfer document by copying the fixture setup from `GranularOperationalPermissionsTest`. Custom role helper: `Role::create([... 'is_system' => false])->syncPermissions([...])`, always include `request.view`, `transfer.view` so the page loads. Tests:
  - `test_request_page_hides_audit_card_without_audit_permission`: `assertOk()->assertDontSeeText('Jejak Riwayat Audit Permintaan')`
  - `test_request_page_shows_audit_card_with_rt_audit`: `assertSeeText('Jejak Riwayat Audit Permintaan')`
  - `test_request_page_shows_audit_card_with_global_audit_view` (role has only `audit.view`)
  - same three for transfer page with `'Jejak Riwayat Audit Transfer'`
  - `test_manager_sees_audit_cards`
- [ ] **Step 2: Run** `php artisan test --filter=AuditPermissionVisibilityTest`. Expected: the two "hides" tests FAIL, others pass.
- [ ] **Step 3: Wrap both `<x-operational-audit-history>` usages in `@canany(['rt.audit', 'audit.view']) ... @endcanany`**, same pattern as `batches/show.blade.php:903`. Keep the `mt-6` wrapper div on the request page inside the gate.
- [ ] **Step 4: Run** `php artisan test --filter=AuditPermissionVisibilityTest`. Expected: PASS.
- [ ] **Step 5: Commit** `git add resources/views/requests/show.blade.php resources/views/transfers/show.blade.php tests/Feature/AuditPermissionVisibilityTest.php && git commit -m "fix(audit): gate request/transfer audit history by rt.audit or audit.view"`

---

### Task 2: Align Laporan menu gate and clean the catalog

**Files:**
- Modify: `resources/views/components/app-layout.blade.php:424`
- Modify: `app/Services/Authorization/PermissionCatalogService.php:225-227`
- Modify: `database/seeders/PermissionSeeder.php` (remove `'reports.view'` and `'report.export'` from the explicit `$permissions` array near line 48 and from the purchasing list near line 87)
- Test: `tests/Feature/AuditPermissionVisibilityTest.php`, `tests/Feature/CustomRoleManagementTest.php`

**Interfaces:**
- Consumes: `report.view` route gate (`routes/web.php:293`).
- Produces: catalog without `report.export`; `audit.view` label reads `'Lihat Riwayat Audit Seluruh Modul (PO, Produksi, Request, Transfer)'`.

Decision (default, override if wrong): `report.export` has no export feature or enforcement anywhere (`ReportController` has no export action), so it is removed rather than enforced. Existing DB rows stay; Task 3 keeps them from being stripped.

- [ ] **Step 1: Write failing tests.**
  - In `AuditPermissionVisibilityTest`: `test_laporan_menu_hidden_for_legacy_reports_view_only` (role with only `reports.view` plus `produk.view`; GET `dashboard` route; `assertDontSeeText('Lead Time Supplier')`). `test_laporan_menu_shown_for_report_view` (same with `report.view`; `assertSeeText('Lead Time Supplier')`).
  - In `CustomRoleManagementTest`: `test_catalog_has_no_dead_report_export_and_audit_view_label_is_descriptive` asserting `getAllPermissionKeys()` does not contain `report.export`, and contains `audit.view`.
- [ ] **Step 2: Run** both filters. Expected: legacy-menu test and catalog test FAIL.
- [ ] **Step 3: Change nav to `@canany(['report.view'])`** (or `@can('report.view')` with matching `@endcan`), remove `report.export` entry, update `audit.view` label, edit seeder lists.
- [ ] **Step 4: Run** `php artisan test --filter=AuditPermissionVisibilityTest` and `--filter=CustomRoleManagementTest`, then `php artisan test --filter=PermissionTest`. Expected: PASS.
- [ ] **Step 5: Commit** `fix(rbac): gate Laporan menu on report.view; drop unenforced report.export`

---

### Task 3: Preserve non-catalog permissions and protect the manager role on update

**Files:**
- Modify: `app/Services/Authorization/RoleService.php` — `updateRole`
- Test: `tests/Feature/CustomRoleManagementTest.php`

**Interfaces:**
- Consumes: `PermissionCatalogService::getAllPermissionKeys(): array`, `Role::permissions`.
- Produces: `updateRole(Role $role, array $data, array $permissionNames = []): Role` with unchanged signature. New behavior: (a) final sync list = submitted names ∪ role's current permissions whose name is not in the catalog; (b) when `$role->name === 'manager'`, display_name/description may update but permissions are not synced.

- [ ] **Step 1: Write failing tests** in `CustomRoleManagementTest`:
  - `test_update_preserves_non_catalog_permissions`: custom role with `['purchasing.view', 'reports.view', 'gudang.audit']` (create the two non-catalog perms via `Permission::firstOrCreate`); PUT with `permissions => ['purchasing.view']`; assert role `hasPermissionTo('reports.view')` and `'gudang.audit'`, still has `purchasing.view`.
  - `test_update_still_removes_catalog_permission_when_unchecked`: role with `['purchasing.view', 'audit.view']`; PUT `['purchasing.view']`; assert `audit.view` removed.
  - `test_manager_role_permissions_not_changed_by_update`: PUT to manager role with `permissions => []`; assert redirect to `roles.index`, manager permission count unchanged, `display_name` updated.
- [ ] **Step 2: Run** `php artisan test --filter=CustomRoleManagementTest`. Expected: three new tests FAIL (first and third) / second passes already.
- [ ] **Step 3: Implement in `RoleService::updateRole`:** compute `$preserved = $role->permissions->pluck('name')->diff($catalogKeys)->all()`; sync `array_values(array_unique(array_merge($permissionNames, $preserved)))`; early-skip sync when `$role->name === 'manager'`. Keep `forgetCachedPermissions()` call.
- [ ] **Step 4: Run** `php artisan test --filter=CustomRoleManagementTest`. Expected: PASS (existing tests included, including `test_updating_role_with_all_catalog_permissions_succeeds`).
- [ ] **Step 5: Commit** `fix(rbac): keep non-catalog permissions and protect manager role on role update`

---

### Task 4: Manager readonly matrix in role edit view

**Files:**
- Modify: `resources/views/roles/edit.blade.php:10-14` (alert), `:46` (matrix), `:50-62` (buttons)
- Test: `tests/Feature/CustomRoleManagementTest.php`

**Interfaces:**
- Consumes: `x-permission-matrix` prop `:readonly` (already supported, `permission-matrix.blade.php:4`); Task 3 service behavior.
- Produces: nothing.

- [ ] **Step 1: Write failing tests:**
  - `test_manager_role_edit_page_is_readonly_with_notice`: GET `roles.edit` for manager role; `assertSeeText('Manager memiliki akses penuh')`; `assertDontSeeText('Simpan Perubahan')`.
  - `test_other_system_role_edit_page_still_editable`: GET edit for `gudang`; `assertSeeText('Simpan Perubahan')`.
- [ ] **Step 2: Run** `--filter=CustomRoleManagementTest`. Expected: manager test FAILS.
- [ ] **Step 3: In `edit.blade.php`:** define `$isManager = $role->name === 'manager'`; when true show a `x-alert` (type `info`) with text starting `Manager memiliki akses penuh ke seluruh fitur; wewenang tidak dapat diubah.`, pass `:readonly="$isManager"` to the matrix, and render the "Simpan Perubahan" button only when not manager (submit hidden entirely for manager). Leave the generic system-role alert for non-manager roles.
- [ ] **Step 4: Run** `php artisan test --filter=CustomRoleManagementTest`. Expected: PASS.
- [ ] **Step 5: Commit** `fix(rbac): show manager role permissions as readonly in role editor`

---

### Task 5: Keep default audit permissions on re-seed

**Files:**
- Modify: `database/seeders/PermissionSeeder.php` (role permission lists for purchasing, gudang, operasional)
- Test: `tests/Feature/RbacAndPermissionAliasTest.php`

**Interfaces:**
- Consumes: defaults from `2026_09_18_000003_add_audit_permissions.php`: purchasing → `purchasing.audit`; gudang → `rt.audit`; operasional → `batch.audit`.
- Produces: seeded system roles carry these audit permissions after `$this->seed()`.

Why: `PermissionSeeder` calls `syncPermissions()` with lists that omit these, so every `db:seed` revokes what the migration granted. `PurchasingGudangAccessTest:215` requires operasional to lack `audit.view`; do not add `audit.view` anywhere.

- [ ] **Step 1: Write failing test** `test_seeded_system_roles_keep_default_audit_permissions` in `RbacAndPermissionAliasTest`: after seed, `purchasing` role has `purchasing.audit`, `gudang` has `rt.audit`, `operasional` has `batch.audit`, none has `audit.view`.
- [ ] **Step 2: Run** `php artisan test --filter=test_seeded_system_roles_keep_default_audit_permissions`. Expected: FAIL.
- [ ] **Step 3: Add the three permissions to the matching role arrays** in the seeder.
- [ ] **Step 4: Run full suite** `composer test`. Expected: PASS, including `PurchasingGudangAccessTest` and `PurchasingRoleStageMatrixTest`.
- [ ] **Step 5: Run `vendor/bin/pint --dirty`, `graphify update .`, commit** `fix(seed): keep default audit permissions for system roles on re-seed`

---

## Self-Review

- Spec coverage: findings 1 (T1), 2 (T2), 3 (T2), 4 (T3+T4), 6 (T3) covered; 5 and 8 dropped with reasons; 7 deferred. Seeder revocation (T5) is an extra root cause found while verifying.
- Types: `updateRole` signature unchanged; no new symbols shared across tasks.
- Risk: T2 removes a catalog permission; roles that hold `report.export` in DB keep it (T3 preserves it). T5 only adds permissions that the migration already granted.
