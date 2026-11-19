# Purchasing Permission Hardening & Payment Gate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every action in the 8-stage purchasing flow enforce its role and state rules on the backend (not only by hiding buttons in `purchasing/show.blade.php`), and block payments until the Manager has approved the PO.

**Architecture:** The rules live in one place each: a payment-eligibility method on `PurchaseOrder` that `PurchasingPaymentService::recordPayment` enforces, a staged-PO guard on the three legacy status routes, and one gudang-visibility rule (a model scope and instance method) that both the list endpoint and `show()` use. Warehouse scope for receiving actions goes through the existing `LocationScopeService`. The Blade view reads the same model method, so the UI and the backend cannot disagree.

**Tech Stack:** Laravel 13 / PHP 8.3, Spatie laravel-permission, PHPUnit feature tests on in-memory SQLite, Blade + Alpine.

**Spec:** this conversation's request, restated here as the source of truth:
1. Analyse the 8-stage purchasing flow end to end and validate permissions per role.
2. Permissions must be enforced on the backend (routes, controllers, services), not only in the frontend.
3. A PO cannot be paid while it is draft or in perencanaan. Payment is only allowed after the Manager has approved it.

Role matrix reference: `DOKUMENTASI_FLOW_BISNIS.md` §7. Stage rules: `DOKUMENTASI_FLOW_BISNIS.md` Modul 3 (the doc says 9 stages; the code uses 8, with `pengemasan` folded into `pengiriman`, so follow the code).

## Audit findings (what this plan fixes)

The routes, controller checks and seeded roles were read for this plan:
- `purchasing` role: view, price.view, create, edit, submit, pay, cancel.
- `gudang` role: view, receive.
- `manager`: everything, via `Gate::before`.

Every stage action (`PurchasingStageController`) already calls `authorize()` with the right permission, and `PurchasingStageService::validateExpectedStage` enforces stage order. Four gaps remain:

| # | Gap | Where | Effect |
|---|---|---|---|
| G1 | `pay()` and `PurchasingPaymentService::recordPayment` never check PO status or stage | `PurchasingController.php:792`, `PurchasingPaymentService.php:26` | A payment can be recorded on a draft or not-yet-approved PO. The payment form in `show.blade.php:1649` is also shown at any stage. |
| G2 | Legacy `submit`/`approve`/`receive` routes accept staged POs | `PurchasingController.php:669, 689, 709` | `store()` creates every PO with `stage_aktif = perencanaan` and `status = draft`. A direct POST to `purchasing.submit` → `purchasing.approve` → `purchasing.receive` moves it to `selesai` and posts stock, skipping all 8 stages. The view hides these buttons (`@if(!$hasStages)`), but the backend does not block them. |
| G3 | Gudang can open any PO by URL, and the visibility rule itself starts one stage too late | `PurchasingController::show` (line 604); `data()` lines 122–135 | **Reported by the user:** a gudang user pasting `/purchasing/<uuid>` of a step-1 PO sees all its data (cannot act, but can read). The list hides it, `show()` does not. **Required rule (user):** gudang sees a PO only from step 5 (`po_terbit`) onward. The list rule currently starts at step 6 (`pengiriman`), so step 5 is wrongly hidden too. PO ids are UUIDs (`HasUuids`), so URLs cannot be guessed, but they leak through copy-paste and shared links. |
| G4 | No warehouse-scope check on receiving | stage 6/7/8 in `PurchasingStageController::completeStage`, legacy `receive()` | A `restricted` gudang user (seeded `gudang@` has access only to warehouse id 1) can unload and post stock for a PO bound to another warehouse. |

**Correction found during execution (Ruling 4):** `purchase_orders.stage_aktif` is NOT NULL with default `'perencanaan'`. A PO is never "unstaged" by a null column. The marker for a legacy PO (no 8-stage tracking) is: **it has no `po_stage` rows** (`! $po->stages()->exists()`, the same idea as `$hasStages` in `show.blade.php`). Wherever this plan says "`stage_aktif` null" for a legacy PO, read "has no `po_stage` rows". `PurchaseOrder::usesStageFlow(): bool` (added in Task 2) is the single definition. Task 1 is already committed with an equivalent rule (legacy approved statuses `disetujui`/`dikirim_ke_gudang`/`selesai` count as approved for any stage), which is accepted.

G3 scope check: `show()` is the only read-by-id endpoint for a PO. The other actions taking a PO or revision id are writes gated by permissions the gudang role does not have, except stage 6/7/8 and legacy `receive`, which Task 3 guards. The gudang dashboard lists only `pengiriman`/`unloading`/`input_sistem` POs, which is inside the new rule. `leadTimeData` returns supplier aggregates, not individual POs.

Out of scope, listed for the human to decide (do NOT implement):
- `purchasing` role has `purchasing.cancel` and `cancel()` accepts POs up to `dikirim_ke_gudang`. The doc's role matrix has no cancel row.
- Stage 6 (Pengiriman) also accepts `purchasing.receive` (gudang). The doc lists Purchasing as the stage owner.

## Global Constraints

- Work on branch `fix/purchasing-permission-payment-gate` created from `main`. The working tree already holds unrelated uncommitted changes. Stage only the files each task names (`git add <paths>`), never `git add -A` or `git add .`.
- Business-rule failures throw `Illuminate\Validation\ValidationException::withMessages([...])`, matching the existing purchasing services. Status-transition failures in `PurchasingController` use the existing `ensureStatus()` style (`abort_unless(..., 422, ...)`). Permission failures stay `403`.
- User-facing messages are Indonesian.
- Tests run on in-memory SQLite and call `$this->seed()`. Log in with seeded users: `manager@`, `purchasing@`, `gudang@heavenscent.id`. `Model::preventLazyLoading` is on in tests, so eager-load anything you touch.
- Use the `PoStage::STAGE_*` constants, never stage string literals.
- Gudang visibility rule (user decision): a gudang-only user may see a PO only when `stage_aktif` is `po_terbit` (step 5) or later, i.e. `po_terbit`, `pengiriman`, `unloading`, `input_sistem`, or legacy `pengemasan`. Legacy POs (no `po_stage` rows) with status `dikirim_ke_gudang`, `diterima` or `selesai` stay visible. Steps 1–4 (`perencanaan`, `approval`, `konfirmasi_supplier`, `pembayaran`) are never visible to a gudang-only user, in the list or by direct URL. Manager and purchasing are unaffected.
- New permission names: none. No change to `PermissionSeeder` or `PermissionCatalogService`.
- After code changes run `graphify update .`.
- Full suite baseline: 435 pass, plus 1 known unrelated failure (`DesignSystemComponentsTest::test_core_primitives_match_blueprint_markup_and_dark_mode_parity`, stat-box `bg-emerald-50`). Every task must leave exactly that one failure.

## Review Focus

1. **A PO sent back by the Manager.** After `rejectStage2`, the PO is back at stage 1 (new putaran). Payment must be blocked again even though an earlier putaran's stage-2 row exists. Test in Task 1: `test_payment_blocked_again_after_manager_rejects`.
2. **Manager is not exempt from the payment gate.** `Gate::before` lets the manager skip permission checks, but the approval rule is about PO state, not permission, so the manager must be blocked too. Test in Task 1: `test_manager_also_cannot_pay_unapproved_po`.
3. **Legacy PO (no `po_stage` rows).** Old data must still be payable once its status is approved, and still receivable through the legacy route. Tests in Task 1 (`test_legacy_po_payable_only_after_approval`) and Task 2 (`test_legacy_route_still_works_for_unstaged_po`).
4. **A restricted gudang user posting stock to another warehouse.** It must fail with 403 and leave no `KartuStok`. Test in Task 3: `test_restricted_gudang_cannot_receive_po_for_other_warehouse`.
5. **Copy-pasted URL of a step 1–4 PO (reported by the user).** A gudang user opening `purchasing/<uuid>` must get 403, for every one of steps 1, 2, 3 and 4, not only step 1. Test in Task 3: `test_gudang_gets_403_on_direct_url_for_steps_1_to_4`.
6. **Boundary at step 5.** The PO becomes visible exactly when it reaches `po_terbit`, not one stage later. Test in Task 3: `test_gudang_can_open_po_at_step_5_po_terbit`.
7. **JSON callers.** The stage and pay endpoints also answer `wantsJson()`. A blocked payment must return 422 JSON, not a redirect. Test in Task 1: `test_blocked_payment_returns_422_json`.

---

### Task 1: Payment gate (G1, backend)

**Files:**
- Modify: `app/Models/PurchaseOrder.php` (add method near `isCancelled()`, ~line 203)
- Modify: `app/Services/Purchasing/PurchasingPaymentService.php:37-50` (inside the transaction, after the `isCancelled()` check)
- Test: `tests/Feature/PurchasingPaymentGateTest.php` (new)
- Modify (if they break): tests that record a payment before approval. Candidates: `PurchasingFlowTest`, `PurchasingRevampFlowTest`, `PurchasingFraudAndWorkflowAuditTest`, `PurchasingComprehensiveQATest`, `PurchaseOrderRevisionAndDirectEditTest`, `PurchasingCancelledPaymentStatusTest`.

**Interfaces:**
- Produces: `PurchaseOrder::isApprovedForPayment(): bool`. Task 4 uses it in the view.
  - Cancelled → `false`.
  - `stage_aktif === null` (legacy) → `in_array($this->status, ['disetujui', 'dikirim_ke_gudang', 'selesai'], true)`.
  - Otherwise → `true` only when `stage_aktif` is a stage after approval: the position of `stage_aktif` in `PoStage::STAGES` is ≥ 3, or it is `PoStage::LEGACY_STAGE_PENGEMASAN`. A PO whose status is `selesai` is `true`.
  - Do not use the `status` column for staged POs: it stays `berjalan` from stage 1 through stage 8.
- Error key and message (exact): `'status' => 'Pembayaran belum dapat dicatat. PO harus disetujui Manager (Tahap 2 selesai) terlebih dahulu.'`

- [ ] **Step 1: Write the failing tests** in `PurchasingPaymentGateTest`.
  - setUp mirrors `PurchasingNineStageAndLeadTimeTest::setUp`, plus a `createStagedPo(string $skema = 'tempo')` helper copied from its `createSamplePo`.
  - The payload helper posts `['skema' => 'tempo', 'tanggal_bayar' => now()->toDateString(), 'nominal' => 100000]` to `route('purchasing.pay', $po)`. Use a partial tempo payment: `cash` and `pelunasan` must pay the exact remaining amount (`PurchasingPaymentService.php:81`) and would fail for an unrelated reason.
  - `test_cannot_pay_po_in_perencanaan`: as `purchasing@`, pay → `assertSessionHasErrors('status')`; `Payment::where('po_id', $po->id)->count() === 0`.
  - `test_cannot_pay_po_waiting_manager_approval`: run `completeStage1` via the stage service, then pay → errors `status`, 0 payments.
  - `test_can_pay_after_manager_approval`: run `completeStage1` + `approveStage2` (as `manager@`) → stage_aktif `konfirmasi_supplier`. Pay → `assertSessionHasNoErrors()`, 1 payment.
  - `test_payment_blocked_again_after_manager_rejects`: run `completeStage1`, `rejectStage2`, `completeStage1` again → pay → errors `status`.
  - `test_manager_also_cannot_pay_unapproved_po`: as `manager@`, pay on a stage-1 PO → errors `status`.
  - `test_legacy_po_payable_only_after_approval`:
    - Create a PO without `initializePoStages` (`stage_aktif` null) and `status => 'diajukan'`. Pay → errors.
    - Update status to `dikirim_ke_gudang`, pay again → no errors.
  - `test_blocked_payment_returns_422_json`: `postJson` on a stage-1 PO → `assertStatus(422)->assertJsonValidationErrors('status')`.
  - `test_is_approved_for_payment_per_stage`: a unit-style loop over every `PoStage::STAGES` value, setting `stage_aktif` in memory. Expect `false` for `perencanaan` and `approval`, `true` for the rest. A cancelled PO is always `false`.

- [ ] **Step 2: Run and confirm they fail**
  Run: `php artisan test --filter=PurchasingPaymentGateTest`
  Expected: the "cannot pay" tests FAIL (the payment is recorded), and `test_is_approved_for_payment_per_stage` FAILs with "undefined method".

- [ ] **Step 3: Implement `PurchaseOrder::isApprovedForPayment(): bool`** with the rules in Interfaces. Use `array_search($this->stage_aktif, PoStage::STAGES, true)` for the position.

- [ ] **Step 4: Enforce it in `recordPayment`.** Right after the `$lockedPo->isCancelled()` check, add: `if (! $lockedPo->isApprovedForPayment()) throw ValidationException::withMessages([...exact message...]);`.

- [ ] **Step 5: Run the new tests** with `php artisan test --filter=PurchasingPaymentGateTest`. Expected: all PASS.

- [ ] **Step 6: Run the purchasing suite and triage**
  Run: `php artisan test --filter=Purchas`
  Any newly failing test that records a payment before approval now fails by design. Move its payment to after `approveStage2`, or for legacy-flow tests after `purchasing.approve`. Never weaken the gate to make a test pass. If a failure is not about payment timing, stop and report it.

- [ ] **Step 7: Run the full suite.** `php artisan test` → only the known `DesignSystemComponentsTest` failure.

- [ ] **Step 8: Commit**
  ```bash
  git add app/Models/PurchaseOrder.php app/Services/Purchasing/PurchasingPaymentService.php tests/Feature/PurchasingPaymentGateTest.php <any adjusted test files>
  git commit -m "fix(purchasing): block payments until manager approves the PO"
  ```

---

### Task 2: Close the legacy-route bypass (G2)

**Files:**
- Modify: `app/Http/Controllers/PurchasingController.php`: `submit()` (669), `approve()` (689), `receive()` (709); new private helper next to `ensureStatus()` (~1104).
- Test: `tests/Feature/PurchasingLegacyRouteGuardTest.php` (new)
- Modify: `tests/Feature/PurchasingFlowTest.php`, `tests/Feature/PurchasingComprehensiveQATest.php`, `tests/Feature/PurchasingFraudAndWorkflowAuditTest.php`: the cases that drive a `store()`-created PO through the legacy routes.

**Interfaces:**
- Produces: `private function ensureLegacyFlow(PurchaseOrder $po): void`. It calls `abort_if($po->usesStageFlow(), 422, 'PO ini memakai alur 8 tahap. Gunakan tombol pada panel Eksekusi Tahap Aktif.')`. Call it first thing after `authorize()` in `submit`, `approve` and `receive`.
- Also produces: `PurchaseOrder::usesStageFlow(): bool` in `app/Models/PurchaseOrder.php`, returning `$this->stages()->exists()` (use `$this->relationLoaded('stages') ? $this->stages->isNotEmpty() : ...` to avoid an extra query and lazy-loading errors when already loaded). Task 3 reuses it. Add `app/Models/PurchaseOrder.php` to this task's Files.
- Test helper for adjusted tests: `private function makeLegacy(PurchaseOrder $po): PurchaseOrder`. It deletes `$po->stages()` (do not touch `stage_aktif`: the column is NOT NULL), so a test that really targets the legacy flow keeps working.

- [ ] **Step 1: Write the failing tests** in `PurchasingLegacyRouteGuardTest`, each on a PO created via `POST purchasing.store` (so it is staged):
  - `test_legacy_submit_rejected_for_staged_po`: as `purchasing@` → `assertStatus(422)`; status still `draft`.
  - `test_legacy_approve_rejected_for_staged_po`: set status to `diajukan` directly. As `manager@` → 422; status unchanged.
  - `test_legacy_receive_rejected_for_staged_po`:
    - Set status to `dikirim_ke_gudang`. As `gudang@`, post a valid receive payload → 422.
    - `KartuStok` count for the PO's items is unchanged, and status is not `selesai`.
  - `test_legacy_route_still_works_for_unstaged_po`: create a PO without calling `initializePoStages` (no `po_stage` rows) and with status `draft`. Submit → `diajukan`, approve → `dikirim_ke_gudang`.

- [ ] **Step 2: Run** `php artisan test --filter=PurchasingLegacyRouteGuardTest`. Expected: the three `rejected` tests FAIL.

- [ ] **Step 3: Implement `ensureLegacyFlow`** and call it in the three actions.

- [ ] **Step 4: Run** the new test file. Expected: PASS.

- [ ] **Step 5: Fix the dependent tests.** Run `php artisan test --filter=Purchas`. For each failure caused by the 422, apply `makeLegacy($po)` right after the PO is created. Known spots:
  - `PurchasingFlowTest` lines 70–112 and 295–330.
  - `PurchasingComprehensiveQATest` lines 330–350 and 431.
  - `PurchasingFraudAndWorkflowAuditTest` line 485 (already named `$poLegacy`, so check before changing).

  Re-run until green.

- [ ] **Step 6: Full suite.** `php artisan test` → only the known failure.

- [ ] **Step 7: Commit**
  ```bash
  git add app/Http/Controllers/PurchasingController.php tests/Feature/PurchasingLegacyRouteGuardTest.php tests/Feature/PurchasingFlowTest.php tests/Feature/PurchasingComprehensiveQATest.php tests/Feature/PurchasingFraudAndWorkflowAuditTest.php
  git commit -m "fix(purchasing): legacy submit/approve/receive routes reject staged POs"
  ```

---

### Task 3: Gudang visibility and warehouse scope (G3, G4)

**Files:**
- Modify: `app/Models/PurchaseOrder.php`: add the scope and instance method.
- Modify: `app/Http/Controllers/PurchasingController.php`:
  - `data()` lines 122–135: replace the inline gudang `where` with the scope (this also moves the list boundary from step 6 to step 5).
  - `show()` at line 606: add the guard.
  - legacy `receive()`: add the warehouse check.
- Modify: `app/Http/Controllers/PurchasingStageController.php`: `completeStage` cases `STAGE_6` (only when the user lacks `purchasing.edit`), `STAGE_7` and `STAGE_8`. Add the warehouse check before calling the service.
- Test: `tests/Feature/PurchasingGudangAccessTest.php` (new)

**Interfaces:**
- Produces on `PurchaseOrder`:
  - `public const GUDANG_VISIBLE_STAGES = [PoStage::STAGE_5, PoStage::STAGE_6, PoStage::STAGE_7, PoStage::STAGE_8, PoStage::LEGACY_STAGE_PENGEMASAN];` (adds `STAGE_5`, the user's step-5 rule)
  - `public function scopeVisibleToGudang(Builder $q): Builder`: the exact rule now inlined in `data()`. That is `NOT EXISTS (po_stage rows)` (legacy, use `whereDoesntHave('stages')`), OR status in `['dikirim_ke_gudang', 'diterima', 'selesai']`, OR `stage_aktif` in `GUDANG_VISIBLE_STAGES`. Qualify columns with `purchase_orders.`.
  - `public function isVisibleToGudang(): bool`: the same rule on one instance.
- "Gudang-only user" means `! $user->can('purchasing.edit') && $user->can('purchasing.receive')`. This is the definition already used in `data()`. Extract it as `private function isGudangOnly(User $user): bool` in `PurchasingController`.
- Visibility guard on actions: besides `show()`, call `abort_if($this->isGudangOnly($user) && ! $po->isVisibleToGudang(), 403, 'Anda tidak memiliki akses ke PO ini pada tahap saat ini.')` first in the stage-6/7/8 branches of `PurchasingStageController::completeStage` and in legacy `receive()`. For `PurchasingStageController`, add a small private `isGudangOnly(User)` with the same definition, or move the method to a shared trait or the model. The choice is yours, but keep one definition.
- `show()` error: 403 with the message above. A 404 would hide that the PO exists, but the id has already been shared by whoever pasted the URL, so 403 is clearer for the user.
- Warehouse check (both controllers): `abort_unless(app(LocationScopeService::class)->canAccessWarehouse(auth()->user(), $po->gudang_id), 403, 'Anda tidak memiliki akses ke gudang tujuan PO ini.')`. `LocationScopeService` is `App\Services\Authorization\LocationScopeService`. A manager or global user passes, because `getAccessibleWarehouseIds` returns null for them; confirm `canAccessWarehouse` handles null, and fix only if it does not.

- [ ] **Step 1: Write the failing tests** in `PurchasingGudangAccessTest`, using a staged PO helper like Task 1's:
  - `test_gudang_gets_403_on_direct_url_for_steps_1_to_4`: for each of `perencanaan`, `approval`, `konfirmasi_supplier`, `pembayaran`, walk a fresh PO to that stage. As `gudang@`, GET `route('purchasing.show', $po)` → 403, and the response body does not contain the PO's `no_po`.
  - `test_gudang_can_open_po_at_step_5_po_terbit`: walk a PO to `po_terbit`. As `gudang@` → 200.
  - `test_gudang_can_open_po_at_unloading`: walk the PO to `unloading` via the stage service (as manager/purchasing). As `gudang@` → 200.
  - `test_gudang_cannot_complete_stage_6_on_invisible_po`: an `approval`-stage PO. As `gudang@`, POST `stages.complete` for `STAGE_6` → 403.
  - `test_purchasing_can_open_po_at_any_stage`: as `purchasing@` on a `perencanaan` PO → 200.
  - `test_restricted_gudang_cannot_receive_po_for_other_warehouse`:
    - Create a second `Gudang` (`tipe` `bahan_baku`). Point the PO at it and walk it to `unloading`.
    - As `gudang@` (seeded restricted to warehouse 1), POST `stages.complete` for `STAGE_7` with a valid `physical_check` payload → 403.
    - No `BarangDatang` exists for the PO.
  - `test_gudang_list_and_show_use_same_rule`: create one PO at each of `perencanaan`, `approval`, `konfirmasi_supplier`, `pembayaran`, `po_terbit`, `pengiriman`, `unloading`. The ids returned by `purchasing.data` (as `gudang@`) equal the ids whose `show` returns 200, and that set is exactly the last four stages.

- [ ] **Step 2: Run** `php artisan test --filter=PurchasingGudangAccessTest`. Expected: the 403 tests FAIL.

- [ ] **Step 3: Implement** the scope, the instance method, `isGudangOnly`, the `show()` guard (`abort_if($this->isGudangOnly($user) && ! $purchaseOrder->isVisibleToGudang(), 403)`), the `data()` refactor and the warehouse checks.

- [ ] **Step 4: Run** the new tests, then `php artisan test --filter=Purchas` (`PurchasingPrivacyGudangTest` must stay green; if it asserts that a `po_terbit` PO is hidden from gudang, update that assertion to the new step-5 rule and say so in the commit message). Expected: PASS.

- [ ] **Step 5: Full suite.** `php artisan test` → only the known failure.

- [ ] **Step 6: Commit**
  ```bash
  git add app/Models/PurchaseOrder.php app/Http/Controllers/PurchasingController.php app/Http/Controllers/PurchasingStageController.php tests/Feature/PurchasingGudangAccessTest.php
  git commit -m "fix(purchasing): enforce gudang visibility and warehouse scope on backend"
  ```

---

### Task 4: Role × stage permission matrix test, and UI parity in `show.blade.php`

This task adds the regression net for "permission tidak hanya di frontend" and makes the view read the same rules as the backend.

**Files:**
- Test: `tests/Feature/PurchasingRoleStageMatrixTest.php` (new)
- Modify: `resources/views/purchasing/show.blade.php`:
  - payment panel (`@can('purchasing.pay')` block, ~1649–1875)
  - stage-4 "Dibayarkan" helper text (~1307–1342)
- Modify: `DOKUMENTASI_FLOW_BISNIS.md` Modul 3 table, row **4 Pembayaran**: add the sentence "Pembayaran hanya dapat dicatat setelah PO disetujui Manager (Tahap 2 selesai)." Also add one sentence under the Modul 3 table: "User Gudang hanya dapat melihat PO mulai Tahap 5 (PO Terbit); PO pada Tahap 1–4 tidak muncul di daftar dan tidak dapat dibuka lewat tautan langsung."

**Interfaces:**
- Consumes: `PurchaseOrder::isApprovedForPayment()` (Task 1).

- [ ] **Step 1: Write the matrix test.** Use a data provider `actionProvider()` that yields `[action, stage the PO must be at, route name + params builder, payload, allowed roles]`. The rows below assert today's intended behavior:

  | action | PO at stage | route | allowed (else 403) |
  |---|---|---|---|
  | submit stage 1 | `perencanaan` | `stages.complete` `STAGE_1` | purchasing, manager |
  | approve stage 2 | `approval` | `stages.complete` `STAGE_2` | manager |
  | reject stage 2 | `approval` | `stages.reject-stage2` (`alasan`) | manager |
  | confirm supplier | `konfirmasi_supplier` | `stages.complete` `STAGE_3` | purchasing, manager |
  | dibayarkan | `pembayaran` | `stages.dibayarkan` | purchasing, manager |
  | lanjut tempo | `pembayaran` (skema tempo) | `stages.lanjut-tempo` | purchasing, manager |
  | po terbit | `po_terbit` | `stages.complete` `STAGE_5` | purchasing, manager |
  | pengiriman | `pengiriman` | `stages.complete` `STAGE_6` | purchasing, gudang, manager |
  | unloading | `unloading` | `stages.complete` `STAGE_7` | gudang, manager |
  | input sistem | `input_sistem` | `stages.complete` `STAGE_8` | gudang, manager |
  | record payment | `konfirmasi_supplier` | `purchasing.pay` | purchasing, manager |
  | direct edit | `pengiriman` | `purchasing.direct-edit` | purchasing, manager |
  | submit revisi | `konfirmasi_supplier` | `purchasing.revisi.submit` | purchasing, manager |
  | cancel | `perencanaan` | `purchasing.cancel` (`alasan`) | purchasing, manager |

  - Test `test_role_can_or_cannot_perform_action` runs each row for `purchasing@`, `gudang@`, `manager@` and also `operasional@`.
  - `operasional@` has no purchasing permissions, so the route middleware `can:purchasing.view` must give 403 for every row.
  - Assert `403` for disallowed roles, and `!== 403` for allowed ones. The allowed request may legitimately fail validation (302/422) because the payload is minimal; the test only proves the permission layer.
  - Stage positioning: build a helper `walkTo(PurchaseOrder $po, string $stage)` that drives the stage service as `manager@`. Reuse each service call's arguments from `PurchasingNineStageAndLeadTimeTest`.

- [ ] **Step 2: Run** `php artisan test --filter=PurchasingRoleStageMatrixTest`. Expected: PASS. It is a characterization test after Tasks 1–3. Any 403/allow mismatch is a real gap: report it, do not edit the table to match.

- [ ] **Step 3: Write the failing view test** `test_payment_form_hidden_until_manager_approval`, in the same file or `PurchasingPaymentGateTest`.
  - As `purchasing@`, GET `purchasing.show` for a `perencanaan` PO. Assert `assertDontSee('id="form-pembayaran"', false)` and `assertSee('Pembayaran tersedia setelah PO disetujui Manager')`.
  - After approval, assert `assertSee('id="form-pembayaran"', false)`.

- [ ] **Step 4: Run it** → FAIL.

- [ ] **Step 5: Edit `show.blade.php`.** In the `@can('purchasing.pay')` payment block, add a branch before the form, after the pending-revision branch: `@elseif(!$po->isLunas() && !$po->isCancelled() && !$po->isApprovedForPayment())`. It renders a notice in the same markup style as the "Pembayaran dikunci sementara" notice just above it:
  - Title: "Pembayaran belum tersedia".
  - Body: "Pembayaran tersedia setelah PO disetujui Manager (Tahap 2 selesai)."
  - Keep the existing form branch as the final `@elseif`.

- [ ] **Step 6: Run** the view test and `php artisan test --filter="Purchas|ViewRendering"` → PASS.

- [ ] **Step 7: Update the doc row** in `DOKUMENTASI_FLOW_BISNIS.md` (one sentence, per Files above).

- [ ] **Step 8: Full suite, then graph.** Run `php artisan test` (only the known failure), then `graphify update .`.

- [ ] **Step 9: Commit**
  ```bash
  git add tests/Feature/PurchasingRoleStageMatrixTest.php resources/views/purchasing/show.blade.php DOKUMENTASI_FLOW_BISNIS.md
  git commit -m "test(purchasing): role x stage permission matrix; hide payment form until approval"
  ```
