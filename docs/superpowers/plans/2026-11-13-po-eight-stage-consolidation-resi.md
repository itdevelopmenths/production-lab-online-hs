# PO Eight-Stage Consolidation & Tracking Number (No. Resi) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Streamline PO procurement by merging packaging (Stage 6) into shipping (Stage 7), making No. Resi optional in shipping, adding No. Resi input to Stage 9 (stock posting), and updating the progress stepper to an 8-stage layout.

**Architecture:**
- Skip `PoStage::STAGE_6 (pengemasan)` upon completion of `STAGE_5 (po_terbit)`, directly advancing to `PoStage::STAGE_7 (pengiriman)`.
- In `show.blade.php`, remove the packaging form block, keep `no_resi` optional during shipping, and allow updating `no_resi` when posting inventory in Stage 9.
- Adapt `purchasing-progress-stepper.blade.php` from a 9-column grid to an 8-column grid (`grid-cols-8`) with 4 categories: Internal (4), Supplier (1), Transport (1), Receiving (2).

**Tech Stack:** Laravel 11, Blade, Tailwind CSS, Alpine.js, PHPUnit.

**Spec:** Conversation design doc approved on 2026-11-13.

## Global Constraints
- Stage 5 completion opens `PoStage::STAGE_7` directly.
- `no_resi` in `shipping` and in `stage 9` must be optional (nullable).
- Existing `po_stages` records for completed historic POs remain valid and readable.
- All existing tests in `PurchasingNineStageAndLeadTimeTest` must pass.

## Review Focus
- Attempting to complete Stage 9 with a new `no_resi` must update `po_pengirimans.no_resi` without error if `po_pengiriman` already exists or create one if it does not.
- Completing Stage 9 without entering `no_resi` must preserve any existing `no_resi`.
- Stepper must render cleanly on 8 columns across mobile and desktop breakpoints.

---

### Task 1: Backend Transition & Stage 9 No. Resi Handling

**Files:**
- Modify: `app/Services/Purchasing/PurchasingStageService.php:450-475, 680-725`
- Modify: `app/Http/Controllers/PurchasingStageController.php:80-85`
- Test: `tests/Feature/PurchasingNineStageAndLeadTimeTest.php`

**Interfaces:**
- Consumes: `completeStage5(PurchaseOrder $po, User $purchasing, ?DateTimeInterface $waktuSelesai, ?string $catatan): PoStage`
- Produces: `completeStage5` creates and activates `PoStage::STAGE_7` instead of `PoStage::STAGE_6`.
- Consumes: `completeStage9(PurchaseOrder $po, User $gudangUser, ?DateTimeInterface $waktuSelesai, ?string $catatan, ?string $noResi = null): void`
- Produces: saves `no_resi` to `po_pengiriman` if `$noResi` is provided.

- [ ] **Step 1: Write failing test verifying Stage 5 directly opens Stage 7 and Stage 9 saves resi**
In `tests/Feature/PurchasingNineStageAndLeadTimeTest.php`, update `test_tc07_tc08` or add test checking that completing Stage 5 transitions `stage_aktif` to `PoStage::STAGE_7`, and completing Stage 9 with `no_resi` updates `po->pengiriman->no_resi`.

- [ ] **Step 2: Run test to verify failure**
Run: `php artisan test --filter=PurchasingNineStageAndLeadTimeTest`
Expected: FAIL due to expected transition to STAGE_6.

- [ ] **Step 3: Update `PurchasingStageService.php` and `PurchasingStageController.php`**
- In `completeStage5()`: change `$nextStage` creation from `PoStage::STAGE_6` to `PoStage::STAGE_7` (`urutan => 6`), set `$po->stage_aktif = PoStage::STAGE_7`.
- In `completeStage9()`: add `?string $noResi = null` argument; if filled, update/create `PoPengiriman` with `no_resi`.
- In `PurchasingStageController::completeStage()`: under `case PoStage::STAGE_9:`, extract `$request->input('no_resi')` and pass to `completeStage9`.

- [ ] **Step 4: Run test to verify it passes**
Run: `php artisan test --filter=PurchasingNineStageAndLeadTimeTest`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add app/Services/Purchasing/PurchasingStageService.php app/Http/Controllers/PurchasingStageController.php
git commit -m "feat(purchasing): transition stage 5 directly to stage 7 and support resi input in stage 9"
```

---

### Task 2: Stepper Component 8-Stage Refactoring

**Files:**
- Modify: `resources/views/components/purchasing-progress-stepper.blade.php:80-180`

**Interfaces:**
- Consumes: `$po`, `$po->stages`
- Produces: 8-node timeline (`grid-cols-8`) matching:
  - 1: Perencanaan (Internal)
  - 2: Approval (Internal)
  - 3: Supplier Confirm (Internal)
  - 4: Payment (Internal)
  - 5: PO (Supplier)
  - 6: Pengiriman (Transportation)
  - 7: Unloading (Receiving)
  - 8: Input (Receiving)

- [ ] **Step 1: Update `$stageDefs` in `purchasing-progress-stepper.blade.php`**
Define keys 1..8 mapping to:
`STAGE_1` (Perencanaan), `STAGE_2` (Approval), `STAGE_3` (Supplier Confirm), `STAGE_4` (Payment), `STAGE_5` (PO), `STAGE_7` (Pengiriman), `STAGE_8` (Unloading), `STAGE_9` (Input).

- [ ] **Step 2: Update top phase header grid in `purchasing-progress-stepper.blade.php`**
Change `grid-cols-9` to `grid-cols-8`:
- Internal Processing: `col-span-4`
- Supplier Processing: `col-span-1`
- Transportation Time: `col-span-1`
- Receiving: `col-span-2`

- [ ] **Step 3: Update subtotal calculation loop**
Ensure `$supplierMenit` computes sum of `STAGE_5`, `$transportationMenit` computes `STAGE_7`, `$receivingMenit` computes `STAGE_8` + `STAGE_9`.

- [ ] **Step 4: Verify rendering via test**
Run: `php artisan test --filter=BladeUiPrd24AlignmentTest`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add resources/views/components/purchasing-progress-stepper.blade.php
git commit -m "feat(ui): update progress stepper to 8 stages with symmetrical grid"
```

---

### Task 3: Show Blade UI Streamlining (Remove Stage 6, Add Resi to Stage 9)

**Files:**
- Modify: `resources/views/purchasing/show.blade.php:1110-1200, 1320-1380, 2080-2130`

**Interfaces:**
- Consumes: `$po`, `$po->stage_aktif`, `$po->pengiriman`
- Produces:
  - Remove Stage 6 form execution block.
  - Stage 7 shipping form: `shipping[no_resi]` confirmed optional without required constraint.
  - Stage 9 stock posting form: add `<input type="text" name="no_resi" value="{{ $po->pengiriman?->no_resi ?? '' }}" placeholder="No. Resi / AWB (Opsional)">`.
  - Bottom summary card: update Supplier Processing to reference Stage 5, Transportation to Stage 7.

- [ ] **Step 1: Remove Stage 6 form block in `resources/views/purchasing/show.blade.php`**
Delete lines 1329–1352 (`@elseif($stgAktif === \App\Models\PoStage::STAGE_6)`).

- [ ] **Step 2: Add No. Resi input to Stage 9 posting form in `resources/views/purchasing/show.blade.php`**
In the Stage 9 form (lines 1170–1200), add an input group for `No. Resi / AWB (Opsional)` above the submit button.

- [ ] **Step 3: Update bottom lead time card categories**
In lines 2085–2130, update `$cardSupplierMenit` to use `STAGE_5` (plus fallback `STAGE_6` for legacy POs).

- [ ] **Step 4: Verify rendering and syntax**
Run: `php artisan test --filter="PurchasingNineStageAndLeadTimeTest|BladeUiPrd24AlignmentTest"`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add resources/views/purchasing/show.blade.php
git commit -m "feat(purchasing): remove stage 6 form, add no_resi to stage 9 posting form"
```

---

### Task 4: Test Suite Alignment & End-to-End Verification

**Files:**
- Modify: `tests/Feature/PurchasingNineStageAndLeadTimeTest.php`

- [ ] **Step 1: Align test assertions in `PurchasingNineStageAndLeadTimeTest.php`**
- Adjust `test_tc07_tc08` or sequential test flow: complete Stage 5 -> directly assert `stage_aktif === PoStage::STAGE_7` (Pengiriman).
- Verify completing Stage 7 with optional `no_resi` works.
- Verify Stage 9 posting form allows passing `no_resi` and persists it to `po_pengirimans`.

- [ ] **Step 2: Run all purchasing tests**
Run: `php artisan test --filter="PurchasingNineStageAndLeadTimeTest|BladeUiPrd24AlignmentTest|PurchaseOrderRevisionAndDirectEditTest|ViewRenderingSafetyTest"`
Expected: 100% PASS with 0 failures.

- [ ] **Step 3: Commit**
```bash
git add tests/Feature/PurchasingNineStageAndLeadTimeTest.php
git commit -m "test(purchasing): align test suite to 8-stage flow with optional resi in stage 9"
```

