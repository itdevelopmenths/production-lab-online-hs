# Purchasing Single ETA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Unify Purchase Order ETA management onto `purchase_orders.eta` by removing duplicate ETA inputs from Stage 3 and Stage 7 active-stage forms and routing post-creation ETA edits through Direct Edit / header trigger so the header ETA card updates immediately.

**Architecture:** `purchase_orders.eta` remains the sole user-editable ETA field across PO lifecycle: entered at PO creation/edit (`create`, `edit`, `analisa/create-po`, `index` quick-dates) and updated afterward via Direct Edit (`PurchasingStageController::directEdit` -> `PurchaseOrderRevisionService::updateOperationalFields`). Stage 3 (`Konfirmasi Supplier`) and Stage 7 (`Pengiriman & Logistik`) active-stage cards in `purchasing/show.blade.php` drop their redundant ETA inputs, while service methods retain backward compatibility if legacy payloads omit ETA.

**Tech Stack:** Laravel 13, PHP 8.5, Blade, Alpine.js, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-11-11-purchasing-single-eta-design.md`

## Global Constraints

- Keep `purchase_orders.eta_supplier` and `po_pengiriman.eta_tiba` database columns intact (no schema drop migration).
- Keep `PurchasingStageService::completeStage3()` and `completeStage7()` safe when invoked without ETA fields in payload.
- Validate `fields.eta` (`nullable|date`), convert empty string `''` to `null` for PostgreSQL `DATE` safety, and normalize date comparison to `Y-m-d` before creating `po_log_perubahan` entries.
- Refresh PO payment status (`$po->refreshStatusPembayaran()`) whenever `eta` changes via Direct Edit.
- Do not modify `AnalisaService` inbound calculations.

## Review Focus

- **Unchanged ETA on unrelated Direct Edit:** submitting the Direct Edit form to update only `catatan` or `no_invoice` when `$po->eta` is already set must not create a false `po_log_perubahan` entry for `eta` due to `Carbon` string vs `Y-m-d` mismatch.
- **Clearing ETA to empty string:** submitting `fields[eta] = ''` in Direct Edit must persist `null` cleanly on PostgreSQL without `SQLSTATE[22007]` invalid date errors.
- **Invalid date input on Direct Edit:** submitting non-date `fields[eta]` via HTTP must fail validation with HTTP 422/302 redirect error rather than throwing a `Carbon` parse exception.
- **Payment status sync on tempo PO:** moving an overdue `tempo` PO (with null `tanggal_tempo`) from past `eta` to a future `eta` via Direct Edit must clear `overdue` status via `refreshStatusPembayaran()`.
- **Header quick-edit visibility by permission/status:** the ETA quick-edit trigger in the header metadata card must only appear when user has `purchasing.edit` and PO is active (`$hasStages && !in_array($po->status, ['selesai', 'dibatalkan'])`).

---

### Task 1: Update Direct Edit Backend for Single `eta` Field

**Files:**
- Modify: `app/Services/Purchasing/PurchaseOrderRevisionService.php:28-73`
- Modify: `app/Http/Controllers/PurchasingStageController.php:157-169`
- Test: `tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php`

**Interfaces:**
- Produces: `PurchaseOrderRevisionService::updateOperationalFields(PurchaseOrder $po, array $fields, User $actor): void` accepting `'eta'` in `$allowedPoFields`, normalizing date fields (`'eta'`, `'eta_supplier'`) to `Y-m-d` or `null`, logging diffs to `PoLogPerubahan`, and calling `$po->refreshStatusPembayaran()` when `eta` changes.
- Produces: `POST /purchasing/{purchaseOrder}/direct-edit` (`PurchasingStageController::directEdit`) validating `fields` as `array` and `fields.eta` as `nullable|date`.

- [ ] **Step 1: Write failing tests for Direct Edit `eta` normalization, clearing, invalid date validation, and payment status sync**

Add to `tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php`:
```php
public function test_direct_edit_updates_eta_logs_changes_and_syncs_payment_status(): void
{
    $po = $this->createSamplePo(PoStage::STAGE_3);
    $po->update([
        'eta' => now()->subDays(3)->toDateString(),
        'status_pembayaran' => 'overdue',
    ]);

    $newEta = now()->addDays(10)->toDateString();

    // 1. Update via HTTP updates eta, logs po_log_perubahan, and clears overdue status
    $this->actingAs($this->purchasing)
        ->post(route('purchasing.direct-edit', $po), [
            'fields' => [
                'eta' => $newEta,
                'catatan' => 'Jadwal tiba diperbarui supplier',
            ],
        ])
        ->assertRedirect();

    $po->refresh();
    $this->assertEquals($newEta, $po->eta?->format('Y-m-d'));
    $this->assertEquals('belum_lunas', $po->status_pembayaran);
    $this->assertTrue(
        PoLogPerubahan::where('po_id', $po->id)->where('nama_field', 'eta')->where('nilai_baru', $newEta)->exists()
    );

    // 2. Submitting the same eta when editing another field must not create a duplicate eta log
    $etaLogCountBefore = PoLogPerubahan::where('po_id', $po->id)->where('nama_field', 'eta')->count();
    $this->actingAs($this->purchasing)
        ->post(route('purchasing.direct-edit', $po), [
            'fields' => [
                'eta' => $newEta,
                'catatan' => 'Update catatan saja',
            ],
        ])
        ->assertRedirect();

    $this->assertSame(
        $etaLogCountBefore,
        PoLogPerubahan::where('po_id', $po->id)->where('nama_field', 'eta')->count()
    );

    // 3. Clearing eta to empty string persists null cleanly
    $this->actingAs($this->purchasing)
        ->post(route('purchasing.direct-edit', $po), [
            'fields' => ['eta' => ''],
        ])
        ->assertRedirect();

    $po->refresh();
    $this->assertNull($po->eta);

    // 4. Invalid date fails validation
    $this->actingAs($this->purchasing)
        ->postJson(route('purchasing.direct-edit', $po), [
            'fields' => ['eta' => 'not-a-date'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fields.eta');
}
```

- [ ] **Step 2: Run the new test to verify it fails**

Run: `php artisan test --filter=test_direct_edit_updates_eta_logs_changes_and_syncs_payment_status`
Expected: FAIL because `'eta'` is not yet in `$allowedPoFields`.

- [ ] **Step 3: Update `PurchaseOrderRevisionService::updateOperationalFields()` and `PurchasingStageController::directEdit()`**

In `app/Http/Controllers/PurchasingStageController.php`, validate `fields` (`required|array`) and `fields.eta` (`nullable|date`) inside `directEdit()` before calling `$this->revisionService->updateOperationalFields($purchaseOrder, $request->input('fields', []), auth()->user())`.

In `app/Services/Purchasing/PurchaseOrderRevisionService.php`:
- Add `'eta'` to `$allowedPoFields`: `['catatan', 'eta', 'eta_supplier', 'no_referensi_supplier', 'no_invoice']`.
- Add a small date-normalization path inside the `$allowedPoFields` loop for `in_array($key, ['eta', 'eta_supplier'], true)`: format existing value via `$po->{$key}?->format('Y-m-d') ?? ''`, normalize incoming non-empty value via `Carbon::parse($newValue)->format('Y-m-d')` (or `null` when empty), compare `$oldValue !== ($normalized ?? '')`, log `PoLogPerubahan` with `$oldValue ?: null` and `$normalized`, and assign `$po->{$key} = $normalized`.
- If `'eta'` is dirty on `$po`, call `$po->refreshStatusPembayaran()` prior to `$po->save()`.

- [ ] **Step 4: Run tests to verify Task 1 passes**

Run: `php artisan test tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php`
Expected: PASS (all tests in `PurchaseOrderRevisionAndDirectEditTest`).

---

### Task 2: Unify ETA UI in `purchasing/show.blade.php`

**Files:**
- Modify: `resources/views/purchasing/show.blade.php:620-625,1220-1241,1318-1341,1964-1967`
- Test: `tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php`

**Interfaces:**
- Consumes: `fields[eta]` validated by `PurchasingStageController::directEdit` from Task 1.
- Produces: Single ETA display & edit experience in `purchasing/show.blade.php`: header card `Estimasi Tiba (ETA)` displays `$po->eta` with quick-edit trigger opening `openDirectEditModal`, modal **Direct Edit** edits `fields[eta]`, and Stage 3 / Stage 7 active-stage forms omit ETA inputs.

- [ ] **Step 1: Write failing view/HTTP tests for `purchasing.show` ETA UI unification**

Add to `tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php`:
```php
public function test_purchasing_show_uses_single_eta_in_header_and_direct_edit_without_stage_eta_inputs(): void
{
    $initialEta = now()->addDays(15)->toDateString();
    $poStage3 = $this->createSamplePo(PoStage::STAGE_3);
    $poStage3->update(['eta' => $initialEta]);

    $res3 = $this->actingAs($this->purchasing)->get(route('purchasing.show', $poStage3));
    $res3->assertOk();
    $res3->assertSee(now()->addDays(15)->format('d/m/Y'));
    $res3->assertDontSee('name="confirmation[eta_supplier]"', false);
    $res3->assertDontSee('name="fields[eta_supplier]"', false);
    $res3->assertSee('name="fields[eta]"', false);

    $poStage7 = $this->createSamplePo(PoStage::STAGE_7);
    $poStage7->update(['eta' => $initialEta]);

    $res7 = $this->actingAs($this->purchasing)->get(route('purchasing.show', $poStage7));
    $res7->assertOk();
    $res7->assertSee('aria-label="Edit ETA"', false);
    $res7->assertDontSee('name="shipping[eta_tiba]"', false);

    // Finalized PO hides quick-edit ETA trigger
    $poStage7->update(['status' => 'selesai']);
    $resClosed = $this->actingAs($this->purchasing)->get(route('purchasing.show', $poStage7));
    $resClosed->assertOk();
    $resClosed->assertDontSee('aria-label="Edit ETA"', false);
}
```

- [ ] **Step 2: Run the UI test to verify it fails**

Run: `php artisan test --filter=test_purchasing_show_uses_single_eta_in_header_and_direct_edit_without_stage_eta_inputs`
Expected: FAIL because `confirmation[eta_supplier]`, `shipping[eta_tiba]`, and `fields[eta_supplier]` are currently rendered.

- [ ] **Step 3: Update `resources/views/purchasing/show.blade.php`**

1. **Header card (`#L620-L625`):** Inside the `Estimasi Tiba (ETA)` card, when `@can('purchasing.edit')` and `$hasStages && !in_array($po->status, ['selesai', 'dibatalkan'])`, add a subtle inline button (`aria-label="Edit ETA"`, `@click="openDirectEditModal = true"`) on the card header row so clicking edit at the header opens the Direct Edit modal directly.
2. **Stage 3 form (`#L1227-L1229`):** Remove the `Estimasi Tanggal Tiba Supplier (ETA)` (`confirmation[eta_supplier]`) div completely.
3. **Stage 7 form (`#L1334-L1337`):** Remove the `Estimasi Tiba di Gudang` (`shipping[eta_tiba]`) div completely.
4. **Direct Edit modal (`#L1964-L1967`):** Replace `fields[eta_supplier]` input and label with `Estimasi Tiba (ETA)` (`name="fields[eta]"`, `value="{{ $po->eta ? $po->eta->format('Y-m-d') : '' }}"`).

- [ ] **Step 4: Run targeted and related Purchasing test suites to verify all pass**

Run: `php artisan test tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php tests/Feature/PurchasingNineStageAndLeadTimeTest.php tests/Feature/PurchasingRevampFlowTest.php tests/Feature/PurchasingFlowTest.php`
Expected: PASS across all tests in those files.

- [ ] **Step 5: Run full test suite & code style check if applicable**

Run: `php artisan test`
Expected: All existing and new tests PASS.
