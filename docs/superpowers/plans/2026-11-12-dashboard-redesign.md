# Dashboard Operasional Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure `resources/views/dashboard.blade.php` into Option A (Unified 5-Cell Metric Strip + 7/5 Asymmetric Command Split + Accessible ARIA Tabbed Context Panel) with first-user plain-language status hints and container-responsive defect analytics.

**Architecture:** Extract pure status explanation mapping into `App\Support\DashboardStatusHint`, make `resources/views/partials/defect-dashboard.blade.php` container-query responsive (`@container`) so it fits cleanly inside narrow panels as well as full-width views, and rewrite `resources/views/dashboard.blade.php` around a compact status-guided header, a single unified clickable KPI ribbon, an internally scrollable `lg:col-span-7` action center, and an `lg:col-span-5` Alpine + ARIA tabbed context panel preserving SSR DOM presence (`hidden="until-found"`).

**Tech Stack:** Laravel 12 Blade, Tailwind CSS v4 (`@tailwindcss/vite`), Alpine.js, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-12-dashboard-redesign-design.md`

## Global Constraints

- Stay faithful to the existing minimal OKLCH enterprise light theme (`bg-card`, `border-border`, `font-mono tabular-nums`, restrained semantic badges).
- Never introduce `max-w-7xl`, `bg-gradient-to-r`, decorative emojis (`👋`), new JS chart libraries, or routes/DB changes.
- Preserve proportional scaling (`html.hs-app-shell`) and horizontal overflow guards (`min-w-0`, `truncate`, wrapping filters/pills) on 13–14" laptop viewports.
- Never hide inactive tabs with `x-if` (keep all panels in SSR DOM using `hidden="until-found"` and `@beforematch` sync for Find-in-Page and `assertSee`).
- Style selected `[role="tab"]` from `[aria-selected="true"]` without changing element dimensions (WCAG 1.4.1 non-color underline/indicator, no `box-shadow` reliance in forced colors).

## Review Focus

- **Query-parameter reload on Defect filter (`?year=`, `?month=`, `?gudang_id=`):** After submitting the filter form on `/dashboard`, the right panel MUST initialize directly on the `defect` tab (`aria-selected="true"`), rather than snapping back to `streamLeft`.
- **Roles without `defect.view` (e.g., `purchasing`):** Must render only two tabs (`streamLeft`, `streamRight`) with valid ARIA selection even if defect query params appear in the URL.
- **Long currency values / SKU titles on 13" screens:** Purchasing's 5th KPI (`Rp ...`) and narrow `5/12` column rows must never overflow or clip action buttons.
- **Unknown or future status strings:** `DashboardStatusHint` must return a safe, plain fallback (or null) without throwing on unseen statuses or null properties.
- **Unrelated working tree files:** Stage only exact task files during commits; never stage `.superpowers/` or unrelated specs.

---

### Task 1: Plain-Language Status Helper (`DashboardStatusHint`)

**Files:**
- Create: `app/Support/DashboardStatusHint.php`
- Test: `tests/Unit/DashboardStatusHintTest.php`

**Interfaces:**
- Produces:
  - `App\Support\DashboardStatusHint::describe(?string $label, bool $isTerlambat = false): ?string`
  - `App\Support\DashboardStatusHint::forStockLevel(float|int $qty): string`

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/DashboardStatusHintTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\DashboardStatusHint;
use PHPUnit\Framework\TestCase;

class DashboardStatusHintTest extends TestCase
{
    public function test_describes_known_workflow_and_action_statuses_in_plain_indonesian(): void
    {
        $this->assertSame('Menunggu keputusan Anda sebelum diproses lanjut', DashboardStatusHint::describe('Persetujuan Manager'));
        $this->assertSame('Purchasing sudah memperbarui PO, cek ulang sebelum setujui', DashboardStatusHint::describe('Menunggu Revisi'));
        $this->assertSame('Butuh persetujuan pengeluaran stok antar gudang', DashboardStatusHint::describe('Otorisasi Transfer'));
        $this->assertSame('Siapkan fisik barang & terbitkan surat jalan', DashboardStatusHint::describe('Draft Surat Jalan'));
        $this->assertSame('Barang sedang dikirim; verifikasi saat tiba', DashboardStatusHint::describe('Dalam Perjalanan'));
        $this->assertSame('Semua bahan formula siap untuk dirilis ke lab', DashboardStatusHint::describe('Bahan Lengkap'));
        $this->assertSame('Sebagian bahan baku kurang, perlu permintaan stok', DashboardStatusHint::describe('Kurang Bahan'));
    }

    public function test_prioritizes_sla_overdue_hint_and_handles_unknown_or_null_gracefully(): void
    {
        $this->assertStringContainsString('Melewati batas waktu SLA', DashboardStatusHint::describe('Persetujuan Manager', true));
        $this->assertNull(DashboardStatusHint::describe(null));
        $this->assertNull(DashboardStatusHint::describe('Status Kustom Baru'));
    }

    public function test_describes_stock_level_in_plain_language(): void
    {
        $this->assertSame('Stok kritis, segera ajukan pengadaan', DashboardStatusHint::forStockLevel(5));
        $this->assertSame('Stok mendekati batas minimum', DashboardStatusHint::forStockLevel(25));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/DashboardStatusHintTest.php`  
Expected: FAIL (`Class "App\Support\DashboardStatusHint" not found`).

- [ ] **Step 3: Implement `App\Support\DashboardStatusHint`**

Implement `describe(?string $label, bool $isTerlambat = false): ?string` and `forStockLevel(float|int $qty): string` using normalized lowercase lookup table covering PO stages (`STAGE_LABELS`), Transfer statuses (`draft`, `dikirim`, `selesai`), StockRequest statuses (`draft`, `diajukan`, `disetujui`, `selesai`), Batch statuses (`rencana`, `release`, `selesai`, `Bahan Lengkap`, `Kurang Bahan`), and `Terlambat SLA` / `$isTerlambat`. Keep the mapping centralized in one `const HINTS` array and avoid switch/if sprawl.

- [ ] **Step 4: Run unit test to verify it passes**

Run: `php artisan test tests/Unit/DashboardStatusHintTest.php`  
Expected: PASS (3 tests passing).

- [ ] **Step 5: Commit Task 1**

```bash
git add app/Support/DashboardStatusHint.php tests/Unit/DashboardStatusHintTest.php
git commit -m "feat(dashboard): add plain-language status hints helper for first-time users"
```

---

### Task 2: Container-Responsive Embedded Defect Dashboard Partial

**Files:**
- Modify: `resources/views/partials/defect-dashboard.blade.php:1-95`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: `$defectAnalytics`, `$warehouses`, `$year`, `$month`, `$gudangId`, `$formAction`, optional `$embedded` (default `false`)
- Produces: `#defect-dashboard` container wrapped in `@container` (`[container-type:inline-size]`) so internal KPI grid uses `grid-cols-2 @xl:grid-cols-5` regardless of whether it is rendered inside a `5/12` column or full-width page.

- [ ] **Step 1: Write the failing feature test**

Add to `tests/Feature/ViewRenderingSafetyTest.php`:

```php
    public function test_defect_dashboard_uses_container_queries_for_embedded_narrow_panels(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $response = $this->actingAs($manager)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('@container', false);
        $response->assertSee('@xl:grid-cols-5', false);
        $response->assertSee('@lg:justify-end', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ViewRenderingSafetyTest.php --filter=test_defect_dashboard_uses_container_queries_for_embedded_narrow_panels`  
Expected: FAIL because `partials/defect-dashboard.blade.php` still uses viewport `md:grid-cols-5` and lacks `@container` / `@lg:justify-end`.

- [ ] **Step 3: Update `resources/views/partials/defect-dashboard.blade.php` for container-aware responsiveness**

- Wrap `#defect-dashboard` root `<div>` with `@container`.
- Change header flex breakpoint to `flex flex-col @lg:flex-row @lg:items-center justify-between` and filter `<form>` to `@lg:justify-end shrink-0 max-w-full` while retaining `max-w-40 truncate` and `shrink-0 whitespace-nowrap` from prior laptop fix.
- Change the 5 KPI stat cards grid from `grid-cols-2 md:grid-cols-5 gap-3` to `grid-cols-2 @xl:grid-cols-5 gap-2.5`.
- On the 5th KPI card (`Gudang Terdampak`), use `col-span-2 @xl:col-span-1` so two rows align symmetrically when rendered inside a narrow container.
- Add optional compact full-page link `<a href="{{ route('transfers.index') }}#defect-dashboard">` when `$embedded ?? false` is true so users inside the `5/12` tab can jump to full-screen audit view in one click.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ViewRenderingSafetyTest.php tests/Feature/TransferMonthlyWarehouseDefectDashboardTest.php`  
Expected: PASS (all cases passing, including prior laptop cramping test).

- [ ] **Step 5: Commit Task 2**

```bash
git add resources/views/partials/defect-dashboard.blade.php tests/Feature/ViewRenderingSafetyTest.php
git commit -m "refactor(ui): make defect dashboard widget container-query responsive"
```

---

### Task 3: Dashboard Redesign (`dashboard.blade.php` — Metric Strip, 7/5 Command Split, Accessible ARIA Tabs & Status Guide)

**Files:**
- Modify: `resources/views/dashboard.blade.php` (full structural rewrite)
- Test: `tests/Feature/DashboardTest.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: `$kpis`, `$actionQueue`, `$streamLeft`, `$streamRight`, optional `$defectAnalytics`, and `App\Support\DashboardStatusHint`.
- Produces: Redesigned `/` / `route('dashboard')` UI adhering to all spec constraints and `modern-web-guidance` tab & layout patterns.

- [ ] **Step 1: Write failing feature tests covering Option A layout, ARIA tabs, query-param auto-tab, and status clarity**

Add to `tests/Feature/DashboardTest.php`:

```php
    public function test_dashboard_uses_unified_metric_strip_command_split_and_accessible_tabs(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $response = $this->actingAs($manager)->get(route('dashboard'));
        $response->assertOk();

        // Compact status legend for first-time users
        $response->assertSee('Panduan Status:');
        $response->assertSee('Perlu Tindakan');
        $response->assertSee('Dalam Proses');

        // Unified KPI strip + 7/5 asymmetric command split
        $response->assertSee('divide-border', false);
        $response->assertSee('lg:col-span-7', false);
        $response->assertSee('lg:col-span-5', false);

        // Accessible tabs & SSR DOM preservation (hidden="until-found", never x-if)
        $response->assertSee('role="tablist"', false);
        $response->assertSee('role="tab"', false);
        $response->assertSee('role="tabpanel"', false);
        $response->assertSee('Analisa Defect');
        $response->assertSee('hidden="until-found"', false);
    }

    public function test_dashboard_preselects_defect_tab_when_defect_filters_are_applied_for_authorized_role(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $response = $this->actingAs($manager)->get(route('dashboard', ['year' => now()->year]));
        $response->assertOk();
        $response->assertSee("activeTab: 'defect'", false);
    }

    public function test_dashboard_omits_defect_tab_for_role_without_defect_permission_even_with_query_params(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $response = $this->actingAs($purchasing)->get(route('dashboard', ['year' => now()->year]));
        $response->assertOk();
        $response->assertDontSee('Analisa Defect');
        $response->assertSee("activeTab: 'streamLeft'", false);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/DashboardTest.php`  
Expected: FAIL on the 3 newly added tests (`Panduan Status:`, `role="tablist"`, `activeTab: 'defect'`).

- [ ] **Step 3: Rewrite `resources/views/dashboard.blade.php`**

Implement per spec & `modern-web-guidance`:
1. **Header & Status Legend:**
   - `<x-page-header title="Dashboard Operasional">` with concise subtitle + compact 1-line inline **Panduan Status** (`Perlu Tindakan`, `Prioritas / Kritis`, `Dalam Proses`, `Siap / Selesai`) using restrained semantic dots (`bg-amber-500`, `bg-red-500`, `bg-sky-500`, `bg-emerald-500`), plus existing role badge & `Analisa Stok` / `Buat PO` buttons.
2. **Layer 1 — Unified 5-Cell Metric Strip:**
   - Single `bg-card border border-border rounded-lg shadow-2xs overflow-hidden mb-4` container with `grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-border`.
   - Clickable KPI cells (`<a href="...">` when `href` present) with `min-w-0 p-3.5 hover:bg-muted/40 transition-colors`, keeping `$kpi['title']`, `$kpi['value']` (`text-lg xl:text-xl font-bold font-mono tabular-nums`), icon, and `$kpi['subtitle']`. Omit duplicate `Lihat Rincian` footers to save vertical height.
3. **Layer 2 — 7/5 Asymmetric Command Split (`grid grid-cols-1 lg:grid-cols-12 gap-4 items-start`):**
   - **Left (`lg:col-span-7`) — Action Center (`$actionQueue`):**
     - `<x-card>` header with `$actionQueue['title']`, active item count pill, `$actionQueue['subtitle']`, and `Lihat Semua ->`.
     - List wrapper with `divide-y divide-border/60 lg:max-h-[34rem] lg:overflow-y-auto overscroll-contain [scrollbar-gutter:stable]`.
     - Each row displays semantic status badge (`$item->badge_label` + optional `Terlambat`), `min-w-0` doc/meta stack (`$item->nomor`, `$item->meta`), plain-language hint via `\App\Support\DashboardStatusHint::describe($item->badge_label ?? $item->type ?? null, !empty($item->is_terlambat))`, relative timestamp, and CTA button.
   - **Right (`lg:col-span-5`) — Accessible Tabbed Context Panel:**
     - Determine initial tab server-side in PHP: `$canViewDefect = auth()->user()->can('defect.view') && !empty($defectAnalytics); $initialTab = ($canViewDefect && (request()->filled('year') || request()->filled('month') || request()->filled('gudang_id'))) ? 'defect' : 'streamLeft';`. Also check `window.location.hash === '#defect-dashboard'` inside Alpine `x-init` when `$canViewDefect` is true.
     - Single `<div class="bg-card text-card-foreground rounded-lg border border-border shadow-2xs overflow-hidden" x-data="{ activeTab: '{{ $initialTab }}' }">`:
       - Single-row scrollable `<div role="tablist" aria-label="Panel Pemantauan Operasional" class="flex items-center border-b border-border bg-muted/30 px-2 overflow-x-auto">` containing `<button type="button" role="tab">` tabs with bottom active underline driven by `aria-selected` (`aria-selected="true"` -> bottom `border-b-2 border-foreground font-semibold text-foreground`, fixed vertical height/padding across selected & inactive states so tabs never shift size!), ArrowLeft/ArrowRight keyboard support, and numeric count badges.
       - Three `<div role="tabpanel" ...>` siblings (no flex/grid gap between them!) rendered directly in SSR HTML with `:hidden="activeTab === '...' ? false : 'until-found'"` and `@beforematch="activeTab = '...'"` plus initial `hidden="until-found"` attribute on non-active tabs (no Tailwind `.hidden` class on panels, since `display: none` would override `until-found`). `[role="tabpanel"]` itself carries zero padding/border/margin/background so `hidden="until-found"` leaves no empty strip; inner padding lives on a child wrapper `<div>`.
       - Stream 1 & Stream 2 rows reuse one clean row presentation block (avoiding duplicate HTML via a single loop over `['streamLeft' => $streamLeft, 'streamRight' => $streamRight]`), including `DashboardStatusHint` hints (`describe(...)` or `forStockLevel(...)` for `Stok` items).
       - Tab 3 (`defect`, only if `$canViewDefect`) wraps `@include('partials.defect-dashboard', ['formAction' => route('dashboard'), 'embedded' => true])` inside a clean padded wrapper.

- [ ] **Step 4: Build assets and verify all tests pass**

Run:
1. `npm run build`
2. `php artisan test tests/Unit/DashboardStatusHintTest.php tests/Feature/DashboardTest.php tests/Feature/ViewRenderingSafetyTest.php tests/Feature/TransferMonthlyWarehouseDefectDashboardTest.php tests/Feature/AuthorizationTest.php`
3. `php artisan test`

Expected: Build succeeds cleanly (`vite` outputs CSS/JS), all targeted tests & full test suite pass with 0 failures and 0 skipped assertions.

- [ ] **Step 5: Commit Task 3**

```bash
git add resources/views/dashboard.blade.php tests/Feature/DashboardTest.php
git commit -m "feat(ui): redesign operational dashboard with command split and plain-language statuses"
```
