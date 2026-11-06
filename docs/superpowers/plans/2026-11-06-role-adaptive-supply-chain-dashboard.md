# Role-Adaptive Supply Chain Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the homepage (`/`) into an interactive, role-adaptive supply chain operational cockpit for Manager, Purchasing, Gudang, Operasional Lab, and Fulfillment users, adhering strictly to the monochrome B&W minimalist UI and fluid layout.

**Architecture:** `DashboardController` dispatches to dedicated role-builder methods (`buildManagerData()`, `buildPurchasingData()`, `buildGudangData()`, `buildOperasionalData()`, `buildFulfillmentData()`) executing only role-relevant, eager-loaded queries. The Blade view (`resources/views/dashboard.blade.php`) renders a unified 3-Tier layout (Tier 1: 5 Contextual KPI StatBoxes; Tier 2: Full-width Action Center for blocked tasks; Tier 3: 2-Column Operational Activity Streams).

**Tech Stack:** Laravel 11/12 Blade, Alpine.js, Tailwind CSS v4, PHPUnit, DataTables / Heroicons SVG.

**Spec:** `docs/superpowers/specs/2026-11-06-role-adaptive-supply-chain-dashboard-design.md`

## Global Constraints
- **Zero AI-Slop:** No decorative gradient banners, no emojis in headers, no watermark SVGs, no stock photography.
- **Strict B&W Neutral Theme:** OKLCH zinc/neutral palette in `resources/css/app.css`; color reserved exclusively for semantic status badges (`danger`, `warning`, `success`).
- **100% Fluid Width:** Full width canvas (`w-full max-w-none`) with no artificial `max-w-*` container limits on main content.
- **No Database Migrations:** Leverage existing tables (`purchase_orders`, `po_stages`, `batch_produksi`, `transfers`, `stock_requests`, `produks`, `gudangs`).
- **Eager Loading Safety:** All relation queries must use explicit column constraints in `with([...])` to prevent `LazyLoadingViolationException`.

## Review Focus
- **Unassigned/Default Role:** When a user has a custom or unassigned role, fallback to `buildDefaultData()` without throwing 500 error.
- **Zero-State Handling:** Empty state states (`@empty`) must render a clean, compact monochrome callout instead of broken table layouts.
- **Location Scope Security:** Users with restricted warehouse access only see shipments and stock relevant to their assigned warehouses (`LocationScopeService`).
- **Dev Role Switcher Persistence:** Switching roles in local/testing environment immediately renders the new role's dashboard seamlessly.
- **Data Density Integrity:** Table rows and action lists maintain `px-3 py-2 text-xs tabular-nums` for maximum scannability.

---

### Task 1: Role-Builder Engine in `DashboardController.php`

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: Models `PurchaseOrder`, `BatchProduksi`, `Transfer`, `StockRequest`, `Produk`, `Gudang`, Services `AnalisaService`, `StokService`, `LocationScopeService`.
- Produces: `DashboardController::index()` returning `view('dashboard', array_merge(['user' => $user, 'role' => $role], $data))`.

- [ ] **Step 1: Write failing TDD test for role-based dashboard rendering in `tests/Feature/ViewRenderingSafetyTest.php`**

Add `test_dashboard_renders_safely_for_all_five_roles()` in `tests/Feature/ViewRenderingSafetyTest.php`:
```php
public function test_dashboard_renders_safely_for_all_five_roles(): void
{
    $roles = [
        'manager@heavenscent.id' => 'manager',
        'purchasing@heavenscent.id' => 'purchasing',
        'gudang@heavenscent.id' => 'gudang',
        'operasional@heavenscent.id' => 'operasional',
        'fulfillment@heavenscent.id' => 'fulfillment',
    ];

    foreach ($roles as $email => $roleName) {
        $user = User::where('email', $email)->firstOrFail();
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertViewHas('role', $roleName);
        $response->assertViewHas('kpis');
        $response->assertViewHas('actionQueue');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```powershell
php artisan test --filter=test_dashboard_renders_safely_for_all_five_roles
```
Expected: FAIL because `kpis` and `actionQueue` view variables do not exist yet.

- [ ] **Step 3: Implement dedicated role builders in `app/Http/Controllers/DashboardController.php`**

1. Inject `StokService` and `LocationScopeService` into `DashboardController::__construct`.
2. Implement:
   - `buildManagerData(User $user): array`
   - `buildPurchasingData(User $user): array`
   - `buildGudangData(User $user): array`
   - `buildOperasionalData(User $user): array`
   - `buildFulfillmentData(User $user): array`
   - `buildDefaultData(User $user): array`
3. Each builder returns structured array containing:
   - `kpis`: list of 5 stat box definitions (`['title' => ..., 'value' => ..., 'subtitle' => ..., 'href' => ..., 'icon' => ...]` )
   - `actionQueue`: list of urgent action items
   - `streamLeft`: first activity stream dataset
   - `streamRight`: second activity stream dataset
4. Update `index()` to dispatch based on `$role = $user->roleName() ?? 'operasional'`.

- [ ] **Step 4: Run test to verify it passes**

Run:
```powershell
php artisan test --filter=test_dashboard_renders_safely_for_all_five_roles
```
Expected: PASS.

- [ ] **Step 5: Commit Task 1**

```powershell
git add app/Http/Controllers/DashboardController.php tests/Feature/ViewRenderingSafetyTest.php
git commit -m "feat(dashboard): implement role-builder engine in DashboardController"
```

---

### Task 2: Tier 1 Adaptive KPI StatBox Strip in `dashboard.blade.php`

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: `$kpis` array passed from `DashboardController` (`title`, `value`, `subtitle`, `href`, `icon`, `variant`).
- Produces: High-density responsive 5-column grid of `<x-stat-box>` widgets.

- [ ] **Step 1: Write assertions for role-specific KPI titles in `tests/Feature/ViewRenderingSafetyTest.php`**

Assert distinct KPI labels appear when viewing as each role:
- Manager sees `PO Menunggu Approval`.
- Purchasing sees `Item Perlu Order (Lokal)`.
- Gudang sees `Kedatangan Siap Unloading`.
- Operasional sees `Kesiapan Bahan BOM`.
- Fulfillment sees `Total Saldo Produk Jadi`.

- [ ] **Step 2: Run test to verify it fails**

Run:
```powershell
php artisan test --filter=test_dashboard_renders_safely_for_all_five_roles
```
Expected: FAIL because `dashboard.blade.php` still uses legacy static stat boxes.

- [ ] **Step 3: Refactor Tier 1 KPI grid in `resources/views/dashboard.blade.php`**

Replace static stat cards with dynamic iterator:
```blade
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 mb-4">
    @foreach($kpis as $kpi)
        <x-stat-box
            :title="$kpi['title']"
            :value="$kpi['value']"
            :subtitle="$kpi['subtitle'] ?? null"
            :href="$kpi['href'] ?? null"
            :footerText="$kpi['footerText'] ?? null"
        >
            @if(isset($kpi['icon']))
                <x-slot:icon>{!! $kpi['icon'] !!}</x-slot:icon>
            @endif
        </x-stat-box>
    @endforeach
</div>
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```powershell
php artisan test --filter=test_dashboard_renders_safely_for_all_five_roles
```
Expected: PASS.

- [ ] **Step 5: Commit Task 2**

```powershell
git add resources/views/dashboard.blade.php tests/Feature/ViewRenderingSafetyTest.php
git commit -m "feat(dashboard): dynamic role-adaptive Tier 1 KPI stat box grid"
```

---

### Task 3: Tier 2 Action Center (Urgent Queue) in `dashboard.blade.php`

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: `$actionQueue` array (`title`, `subtitle`, `badge`, `items`, `viewAllUrl`).
- Produces: Full-width prioritized action card with quick review buttons and SLA warning badges.

- [ ] **Step 1: Write assertions for Action Center in `tests/Feature/ViewRenderingSafetyTest.php`**

Assert Action Center renders table rows with action URLs for pending items.

- [ ] **Step 2: Run test to verify it fails**

Run:
```powershell
php artisan test --filter=test_dashboard_renders_safely_for_all_five_roles
```
Expected: FAIL.

- [ ] **Step 3: Implement Tier 2 Action Center in `resources/views/dashboard.blade.php`**

Render `<x-card :title="$actionQueue['title']" :subtitle="$actionQueue['subtitle']" :noPadding="true" class="mb-4">`:
- Clean table/row view with status pill, document code, description, timestamps, and actionable button (`Buka`, `Review`, `Unload`).
- Empty state: `<div class="py-10 text-center text-xs text-muted-foreground">` with checkmark icon confirming no blocked tasks.

- [ ] **Step 4: Run test to verify it passes**

Run:
```powershell
php artisan test --filter=test_dashboard_renders_safely_for_all_five_roles
```
Expected: PASS.

- [ ] **Step 5: Commit Task 3**

```powershell
git add resources/views/dashboard.blade.php tests/Feature/ViewRenderingSafetyTest.php
git commit -m "feat(dashboard): full-width Tier 2 urgent action center"
```

---

### Task 4: Tier 3 Operational Activity Streams in `dashboard.blade.php`

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: `$streamLeft` and `$streamRight` datasets.
- Produces: 2-column grid displaying role-specific monitoring streams.

- [ ] **Step 1: Write assertions for Tier 3 stream cards in `tests/Feature/ViewRenderingSafetyTest.php`**

Assert streams render for:
- Manager: Payment commitments + Critical stock alerts.
- Purchasing: Top stock recommendations + Supplier lead times.
- Gudang: Unloading queue + Defect receiving logs.
- Operasional: Active production batches + Material request status.
- Fulfillment: Ready finished goods + Recent distribution transfers.

- [ ] **Step 2: Run test to verify it fails**

Run:
```powershell
php artisan test --filter=test_dashboard_renders_safely_for_all_five_roles
```
Expected: FAIL.

- [ ] **Step 3: Implement Tier 3 2-column grid in `resources/views/dashboard.blade.php`**

Render `<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">`:
- Left Column card with `:noPadding="true"`.
- Right Column card with `:noPadding="true"`.
- Compact high-density list items (`px-3.5 py-2.5 text-xs`) matching the app's established design language.

- [ ] **Step 4: Run test to verify it passes**

Run:
```powershell
php artisan test --filter=test_dashboard_renders_safely_for_all_five_roles
```
Expected: PASS.

- [ ] **Step 5: Commit Task 4**

```powershell
git add resources/views/dashboard.blade.php tests/Feature/ViewRenderingSafetyTest.php
git commit -m "feat(dashboard): 2-column Tier 3 operational activity streams"
```

---

### Task 5: Build, Full Regression & Dev Role Switcher Verification

**Files:**
- Modify: `tests/Feature/ViewRenderingSafetyTest.php`
- Verify: Entire application test suite

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: 100% green PHPUnit test suite, compiled production assets, zero regressions.

- [ ] **Step 1: Verify Blade compilation**

Run:
```powershell
php artisan view:clear; php artisan view:cache; php artisan view:clear
```
Expected: `Blade templates cached successfully.`

- [ ] **Step 2: Build frontend assets with Vite**

Run:
```powershell
npm run build
```
Expected: Vite build succeeds with `0 errors`.

- [ ] **Step 3: Run full PHPUnit test suite**

Run:
```powershell
php artisan test
```
Expected: 100% PASS across all 355+ tests.

- [ ] **Step 4: Final commit & status check**

```powershell
git status -s
```
Ensure working tree is clean.

