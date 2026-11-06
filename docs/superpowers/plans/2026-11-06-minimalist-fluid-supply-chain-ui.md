# Minimalist Fluid Supply Chain UI Implementation Plan

> **For agentic workers (Gemini Flash 3.8):** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended for parallel tracks) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the entire Heaven Scent Supply Chain & Production Lab web interface into a high-density, fluid-width (`w-full max-w-none`), zero-AI-slop, monochrome Black & White (`oklch` neutral/zinc) operational workspace optimized for rapid supply chain scanning and execution.

**Architecture:**
1. **Fluid Shell & CSS Foundation (Task 1):** Remove the global `max-w-7xl` bottleneck in `app-layout.blade.php`, slim down the sidebar (`lg:w-56`) and top header (`h-12`), eliminate redundant header/footer chrome, and tighten DataTables/table density in `resources/css/app.css`.
2. **Component Library Standardization (Task 2):** Compact `page-header`, `card`, `stat-box`, `nav-item`, `badge`, `button`, and form controls so every view inherits dense spacing, monochrome borders, `tabular-nums` alignment, and semantic-only status colors.
3. **View-Level De-Slop & Fluid Refactoring (Tasks 3–6):** Parallelizable module tracks that strip decorative gradients, emojis, watermark SVGs, verbose marketing subtitles, and `max-w-*` form wrappers across Dashboard, Auth, Reports, Purchasing, Analisa Stok, Production, Inventory, Logistics, and Master Data.

**Tech Stack:** Laravel 12 Blade Components, Tailwind CSS v4 (`@theme` with `oklch` monochrome palette in `resources/css/app.css`), Alpine.js, Yajra DataTables, PHPUnit (`ViewRenderingSafetyTest`).

**Spec:** Approved 5-Point Supply Chain UX Audit in session `ccbfdd34-6e36-4b0e-830d-bdb121102e27`.

## Global Constraints

- **Monochrome Black & White Theme:** Preserve the existing `oklch` neutral/zinc variables in `resources/css/app.css`. All structural surfaces, borders, icons, buttons, and neutral KPI cards MUST use `bg-background`, `bg-card`, `bg-muted`, `text-foreground`, `text-muted-foreground`, `border-border`, or `zinc-*` classes.
- **Semantic Status Colors Only:** Chromatic colors (`emerald`, `amber`, `rose`/`red`, `sky`) are strictly restricted to operational status badges, SLA/stock alerts, and diff indicators (e.g., `Lunas`/`Selesai` = emerald, `Pending`/`Revisi` = amber, `Kritis`/`Terlambat`/`Rusak` = rose).
- **Zero AI Slop:** No `bg-gradient-to-*` decorative banners, no waving hand or decorative emojis in headings/subtitles, no oversized background watermark SVGs (`w-64 h-64`), and no verbose marketing prose subtitles.
- **100% Fluid Width (`w-full max-w-none`):** Remove `max-w-2xl`, `max-w-3xl`, `max-w-4xl`, `max-w-5xl`, `max-w-6xl`, and `max-w-7xl` from page layouts and form wrappers. **Exceptions:** Keep `max-w-*` ONLY on modal dialogs (`components/modal.blade.php` and inline modal boxes) and print paper views (`resources/views/transfers/surat-jalan.blade.php` and `resources/views/request-transfer/surat-jalan.blade.php`).
- **DOM & Test Contract Preservation:**
  - Never rename or remove existing `id="..."`, `name="..."`, `data-*`, or `x-ref="..."` attributes (DataTables `#tbl*`, filter `#filter-*`, `#app-sidebar-nav`, etc.).
  - Never place raw `<` or `>` comparison operators inside `<x-...>` Blade component attributes (`x-bind:*` or `:*`); extract comparisons to `@php` variables or Alpine helper methods (enforced by `ViewRenderingSafetyTest::test_no_risky_blade_directives_or_raw_comparison_operators_in_component_attributes`).
  - Never use `@json([...])` with literal array brackets; use `@js([...])`.
  - Preserve brand text `Heaven Scent` in `app-layout.blade.php` (asserted by `ViewRenderingSafetyTest`).

## Review Focus

1. **Blade Component Attribute Comparison Operator Regression:** Editing `<x-button>` or `<x-badge>` bindings with inline `<` or `>` breaks `ViewRenderingSafetyTest`. Pinned by running `php artisan test --filter=ViewRenderingSafetyTest` in every task.
2. **DataTables Toolbar & Filter ID Breakage:** Merging filter cards into table headers must preserve exact element IDs (`#filter-status-po`, `#filter-stage-po`, `#filter-kategori-supplier`, `#btn-reset-po`, `#filter-gudang`, `#filter-kategori`, `#tbl`, `#tblAp`, `#tblApproval`, `#tblLtSupplier`, `#tblLtProduk`, `#tblLtTransaksi`).
3. **Print Layout Regressions (`surat-jalan.blade.php`):** Converting `max-w-3xl` on `transfers/surat-jalan.blade.php` or `request-transfer/surat-jalan.blade.php` would break A4 paper printing. Explicitly excluded and verified in Task 5.
4. **Wide-Screen Form Readability in Fluid Layout:** Removing `max-w-3xl`/`max-w-4xl` on Master Data `create`/`edit` forms without responsive grid columns makes single inputs stretch 1800px wide. Forms must use `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4` inside fluid cards.
5. **Lazy-Loading Violation in Views:** Touching Blade loops must not introduce un-eager-loaded relation calls (`Model::preventLazyLoading(true)` is active in tests).

---

### Task 1: Fluid App Layout Shell, Compact Sidebar & High-Density CSS Foundation

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/views/components/app-layout.blade.php`
- Modify: `resources/views/components/nav-item.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Produces:
  - Fluid `<main class="flex-1 px-4 py-4 sm:px-6 lg:py-5"><div class="w-full max-w-none">` in `resources/views/components/app-layout.blade.php`.
  - Compact sidebar width `lg:w-56` (expanded) / `lg:w-16` (collapsed) and compact top bar `h-12`.
  - High-density DataTables CSS rules in `resources/css/app.css`.

- [ ] **Step 1: Add fluid layout assertion to `tests/Feature/ViewRenderingSafetyTest.php`**

In `tests/Feature/ViewRenderingSafetyTest.php`, inside `test_all_dashboard_views_render_without_lazy_loading_violation_for_all_roles()`, add an assertion that the old `max-w-7xl` bottleneck is gone and `max-w-none` is rendered:

```php
$response->assertOk();
$response->assertSee('Heaven Scent');
$response->assertDontSee('max-w-7xl');
```

- [ ] **Step 2: Run test to verify it fails**

Run (with `BypassSandbox: true` on Windows if needed):
```powershell
php artisan test --filter=test_all_dashboard_views_render_without_lazy_loading_violation_for_all_roles
```
Expected: FAIL because `resources/views/components/app-layout.blade.php` currently outputs `max-w-7xl`.

- [ ] **Step 3: Tighten DataTables and table density in `resources/css/app.css`**

Modify `resources/css/app.css`:
- In `.dt-container .dt-layout-header`: change `@apply px-5 py-4 ...` to `@apply px-4 py-2.5 border-b border-border bg-muted/20 flex flex-wrap items-center justify-between gap-3;`
- In `.dt-container .dt-layout-footer`: change `@apply px-5 py-3.5 ...` to `@apply px-4 py-2.5 border-t border-border bg-muted/10 flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground;`
- In `.dt-container .dt-search input`: change `@apply h-9 rounded-lg ... px-3 py-1.5 text-sm ... sm:w-64` to `@apply h-8 rounded-md border border-input bg-background px-2.5 py-1 text-xs text-foreground placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring/20 transition-all w-full sm:w-60;`
- In `.dt-container .dt-length select`: change `@apply h-9 rounded-lg ... px-2.5 py-1.5 text-sm` to `@apply h-8 rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring/20 transition-all cursor-pointer;`
- In `table.dataTable thead th`: change `@apply bg-muted/40 text-muted-foreground font-semibold text-[13px] tracking-tight border-b border-border px-4 py-3 text-left select-none whitespace-nowrap;` to `@apply bg-muted/40 text-muted-foreground font-semibold text-[11px] uppercase tracking-wider border-b border-border px-3 py-2 text-left select-none whitespace-nowrap;`
- In `table.dataTable tbody td`: change `@apply px-4 py-3.5 text-sm text-foreground border-b border-border/50 align-middle;` to `@apply px-3 py-2 text-xs text-foreground border-b border-border/50 align-middle tabular-nums;`
- In `.dt-paging .dt-paging-button`: change `@apply inline-flex items-center justify-center min-w-8 h-8 px-2.5 text-xs font-medium rounded-lg ...` to `@apply inline-flex items-center justify-center min-w-7 h-7 px-2 text-xs font-medium rounded-md text-muted-foreground hover:text-foreground hover:bg-accent transition-colors cursor-pointer select-none;`

- [ ] **Step 4: Refactor `resources/views/components/app-layout.blade.php` & `resources/views/components/nav-item.blade.php` to fluid & compact shell**

Modify `resources/views/components/app-layout.blade.php`:
1. Change `@props(['title' => 'Dashboard', 'subtitle' => 'Sistem Manajemen Laboratorium Produksi'])` default subtitle to `' subtitle' => null`.
2. Change desktop sidebar width from `:class="collapsed ? 'lg:w-20' : 'lg:w-64'"` to `:class="collapsed ? 'lg:w-16' : 'lg:w-56'"`.
3. Compact sidebar brand header (`pt-3.5 pb-3 px-4`), sidebar search box (`h-8 text-xs`, wrapper `px-3 pb-3`), and `#app-sidebar-nav` spacing (`space-y-4 px-2.5 py-3`).
4. Compact section group labels in `#app-sidebar-nav` (`px-2.5 pb-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/70`).
5. Compact sticky top `<header>`: change `h-16 px-4 sm:px-8` to `h-12 px-4 sm:px-6`. Show `$title` in `text-sm font-semibold text-foreground tracking-tight`, and only render `$subtitle` if explicitly passed and non-empty (`@if($subtitle) <span class="hidden md:inline text-xs text-muted-foreground before:content-['·'] before:mx-2">{{ $subtitle }}</span> @endif`).
6. Change `<main class="flex-1 px-4 py-8 sm:px-8 lg:py-10">` and `<div class="mx-auto w-full max-w-7xl">` to:
   ```blade
   <main class="flex-1 px-4 py-4 sm:px-6 lg:py-5">
       <div class="w-full max-w-none">
           {{ $slot }}
       </div>
   </main>
   ```
7. Remove the bulky multi-line footer (`DevScore Theme Edition`) or condense it to a single ultra-thin status line (`py-2 px-6 border-t border-border/60 text-[11px] text-muted-foreground flex items-center justify-between`).

Modify `resources/views/components/nav-item.blade.php`:
1. Change `$activeClasses` from `bg-primary text-primary-foreground font-semibold shadow-md` to `bg-primary text-primary-foreground font-semibold shadow-2xs`.
2. Change `:class="collapsed ? 'justify-center h-10 w-10 mx-auto rounded-xl' : 'h-11 gap-3 rounded-lg px-3 text-[14px]'"` to `:class="collapsed ? 'justify-center h-9 w-9 mx-auto rounded-md' : 'h-8.5 gap-2.5 rounded-md px-2.5 text-xs'"` and icon wrapper to `size-4`.

- [ ] **Step 5: Build assets and run `ViewRenderingSafetyTest`**

Run:
```powershell
npm run build
php artisan test --filter=ViewRenderingSafetyTest
```
Expected: PASS (all 12 tests in `ViewRenderingSafetyTest` pass).

- [ ] **Step 6: Commit Task 1**

```powershell
git add resources/css/app.css resources/views/components/app-layout.blade.php resources/views/components/nav-item.blade.php tests/Feature/ViewRenderingSafetyTest.php
git commit -m "refactor(ui): fluid app-layout shell, compact sidebar, and dense datatables css"
```

---

### Task 2: Reusable Blade UI Component Library Standardization

**Files:**
- Modify: `resources/views/components/page-header.blade.php`
- Modify: `resources/views/components/card.blade.php`
- Modify: `resources/views/components/stat-box.blade.php`
- Modify: `resources/views/components/badge.blade.php`
- Modify: `resources/views/components/button.blade.php`
- Modify: `resources/views/components/input.blade.php`
- Modify: `resources/views/components/select.blade.php`
- Modify: `resources/views/components/callout.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: Fluid layout shell from Task 1.
- Produces: Standardized, compact `<x-page-header>`, `<x-card>`, `<x-stat-box>`, `<x-badge>`, `<x-button>`, `<x-input>`, `<x-select>`, and `<x-callout>` components with zero AI slop and high data density.

- [ ] **Step 1: Compact `resources/views/components/page-header.blade.php`**

Modify `resources/views/components/page-header.blade.php`:
- Reduce bottom spacing from `mb-8 pb-6` to `mb-4 pb-3 border-b border-border`.
- Change title size from `text-2xl sm:text-3xl font-bold` to `text-lg sm:text-xl font-bold tracking-tight text-foreground`.
- Render `$subtitle` in `mt-0.5 text-xs text-muted-foreground` only when provided.
- Reduce breadcrumb bottom margin from `mb-3` to `mb-1 text-xs`.
- Keep `$actions` slot aligned to the right (`flex flex-wrap items-center gap-2 shrink-0`).

- [ ] **Step 2: Compact `resources/views/components/card.blade.php`**

Modify `resources/views/components/card.blade.php`:
- Change outer container from `rounded-xl border border-border bg-card text-card-foreground shadow-xs overflow-hidden` to `rounded-lg border border-border bg-card text-card-foreground shadow-2xs overflow-hidden`.
- Change card header from `px-6 py-4 border-b border-border bg-muted/20` to `px-4 py-2.5 border-b border-border bg-muted/20`.
- Change card `$title` from `text-base font-bold` to `text-sm font-semibold text-foreground tracking-tight`.
- Change card `$subtitle` to `text-[11px] text-muted-foreground mt-0.5`.
- Change default body padding (when `!$noPadding`) from `p-6` to `p-4`.
- Change footer from `px-6 py-3.5` to `px-4 py-2.5`.

- [ ] **Step 3: Standardize `resources/views/components/stat-box.blade.php` to monochrome high-density KPI card**

Modify `resources/views/components/stat-box.blade.php`:
- Change outer padding from `rounded-xl ... p-5 shadow-xs hover:shadow-md` to `rounded-lg border border-border bg-card p-3.5 text-card-foreground shadow-2xs transition-colors hover:border-foreground/20 flex flex-col justify-between`.
- Change label to `text-[11px] font-semibold uppercase tracking-wider text-muted-foreground truncate`.
- Change icon wrapper to `size-7 rounded-md bg-muted text-foreground/80 flex items-center justify-center shrink-0` (strictly monochrome).
- Change value typography from `text-3xl font-extrabold` to `text-2xl font-bold tracking-tight text-foreground font-mono tabular-nums leading-none mt-2`.

- [ ] **Step 4: Refine `badge.blade.php`, `button.blade.php`, `input.blade.php`, `select.blade.php`, and `callout.blade.php`**

- In `resources/views/components/button.blade.php`:
  - Change default radius from `rounded-lg` to `rounded-md`, remove `active:scale-[0.98]` bounce, and adjust sizes:
    - `'xs' => 'h-7 px-2 text-xs'`
    - `'sm' => 'h-8 px-2.5 text-xs'`
    - `'md' => 'h-9 px-3.5 text-xs'`
    - `'lg' => 'h-10 px-4 text-sm'`
    - `'icon' => 'size-8'`
- In `resources/views/components/badge.blade.php`:
  - Keep semantic variants (`success`, `warning`, `danger`, `info`) with crisp borders and subtle backgrounds; change `'primary'` and `'neutral'` to clean monochrome (`bg-zinc-900 text-white` / `bg-zinc-100 text-zinc-800 border-zinc-200`).
- In `resources/views/components/input.blade.php` and `resources/views/components/select.blade.php`:
  - Ensure input/select height is compact (`h-9 rounded-md px-3 text-xs sm:text-sm`) with crisp focus rings (`focus:ring-1 focus:ring-ring/30`).
- In `resources/views/components/callout.blade.php`:
  - Compact padding to `p-3 rounded-md text-xs`.

- [ ] **Step 5: Compile views and run `ViewRenderingSafetyTest`**

Run:
```powershell
php artisan view:clear
php artisan test --filter=ViewRenderingSafetyTest
```
Expected: PASS (all 12 tests pass, zero Blade syntax or attribute comparison violations).

- [ ] **Step 6: Commit Task 2**

```powershell
git add resources/views/components/
git commit -m "refactor(components): standardize compact monochrome UI components"
```

---

### Task 3: Dashboard, Auth Login & Reports De-Slop

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/reports/index.blade.php`
- Modify: `resources/views/reports/purchasing.blade.php`
- Modify: `resources/views/reports/production.blade.php`
- Modify: `resources/views/reports/material.blade.php`
- Modify: `resources/views/reports/defect.blade.php`
- Modify: `resources/views/reports/low-stock.blade.php`
- Modify: `resources/views/reports/fulfillment.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: Task 1 fluid layout & Task 2 compact components.
- Produces: High-density operational Dashboard, clean monochrome Auth Login page, and fluid Reports views.

- [ ] **Step 1: Add anti-slop assertion for Dashboard in `tests/Feature/ViewRenderingSafetyTest.php`**

In `tests/Feature/ViewRenderingSafetyTest.php` inside `test_all_dashboard_views_render_without_lazy_loading_violation_for_all_roles()`, assert that the waving hand emoji and decorative gradient banner are removed:

```php
$response->assertDontSee('👋');
$response->assertDontSee('bg-gradient-to-r');
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```powershell
php artisan test --filter=test_all_dashboard_views_render_without_lazy_loading_violation_for_all_roles
```
Expected: FAIL because `dashboard.blade.php` currently contains `👋` and `bg-gradient-to-r`.

- [ ] **Step 3: Refactor `resources/views/dashboard.blade.php`**

Modify `resources/views/dashboard.blade.php`:
1. Replace the decorative gradient welcome banner (`bg-gradient-to-r from-primary/10...`, waving emoji `👋`, and `w-64 h-64` background SVG) with a clean, single-row operational status bar or `<x-page-header title="Dashboard Operasional">` displaying user name, role badge, division/warehouse badge, and quick action buttons (`Analisa Stok`, `+ Buat Batch`).
2. Convert the 5 KPI `<x-stat-box>` cards (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3`):
   - Remove rainbow icon classes (`text-rose-600`, `text-amber-600`, `text-sky-600`, `text-indigo-600`, `text-emerald-600`) so icons use the monochrome default.
   - Use semantic `<x-badge>` only for actionable counts (e.g. `danger` badge when `$stokLow > 0`, `warning` badge when `$pendingCount > 0`, `neutral` when `0`).
3. Tighten the 2-column operational tables (`Stok Mendekati / Di Bawah Minimum` and `Batch Produksi Aktif`):
   - Change cell padding from `px-5 py-3.5` to `px-3 py-2 text-xs tabular-nums`.
   - Remove filler card subtitles (`Prioritas pengadaan kembali`, `Sedang berjalan di lantai produksi`).
4. Tighten the bottom 3-column summary cards (`Permintaan Bahan Menunggu`, `Ringkasan Entitas`, ` Distribusi Kategori Produk`) with `gap-4` and compact list rows (`py-1.5 text-xs`).

- [ ] **Step 4: Refactor `resources/views/auth/login.blade.php` to clean monochrome card**

Modify `resources/views/auth/login.blade.php`:
- Remove the 2-column stock photo panel (`/images/perfume-lab.jpg`), amber radial gradient, and "Artisanal Precision • Industrial Standard" marketing copy.
- Present a focused, centered monochrome login card (`w-full max-w-sm rounded-lg border border-border bg-card p-6 shadow-xs`) on `bg-zinc-50` with the Heaven Scent brand mark, concise title (`Masuk ke Sistem`), email/password inputs, remember-me checkbox, and monochrome primary submit button. Preserve all form `name` attributes (`email`, `password`, `remember`) and `@csrf`.

- [ ] **Step 5: Refactor `resources/views/reports/*.blade.php` for fluid high-density display**

Modify `resources/views/reports/index.blade.php`, `purchasing.blade.php`, `production.blade.php`, `material.blade.php`, `defect.blade.php`, `low-stock.blade.php`, and `fulfillment.blade.php`:
- In `reports/index.blade.php`: remove verbose marketing subtitles; make the 6 report navigation cards compact monochrome cards (`p-4 rounded-lg border border-border hover:border-foreground/30 transition-colors`).
- In all 6 report detail views: tighten filter bars into a single compact row, tighten table cells to `px-3 py-2 text-xs tabular-nums`, and ensure `w-full` fluid layout.

- [ ] **Step 6: Run tests to verify Dashboard, Auth, and Reports pass**

Run:
```powershell
php artisan test --filter=ViewRenderingSafetyTest
```
Expected: PASS.

- [ ] **Step 7: Commit Task 3**

```powershell
git add resources/views/dashboard.blade.php resources/views/auth/login.blade.php resources/views/reports/ tests/Feature/ViewRenderingSafetyTest.php
git commit -m "refactor(ui): de-slop dashboard, login, and reports views"
```

---

### Task 4: Core Supply Chain Modules — Purchasing & Analisa Stok

**Files:**
- Modify: `resources/views/purchasing/index.blade.php`
- Modify: `resources/views/purchasing/create.blade.php`
- Modify: `resources/views/purchasing/edit.blade.php`
- Modify: `resources/views/purchasing/show.blade.php`
- Modify: `resources/views/analisa/index.blade.php`
- Modify: `resources/views/analisa/create-po.blade.php`
- Modify: `resources/views/analisa/partials/table-lokal.blade.php`
- Modify: `resources/views/analisa/partials/table-impor.blade.php`
- Modify: `resources/views/analisa/partials/table-fulfillment.blade.php`
- Modify: `resources/views/analisa/partials/table-riwayat.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`
- Test: `tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php`

**Interfaces:**
- Consumes: Task 1 fluid shell & Task 2 components.
- Produces: Fluid, high-density Purchasing and Analisa Stok screens with all existing IDs, Alpine models, and routes intact.

- [ ] **Step 1: Add fluid container assertions for Purchasing & Analisa in `tests/Feature/ViewRenderingSafetyTest.php`**

In `tests/Feature/ViewRenderingSafetyTest.php`, inside `test_purchasing_views_render_safely()`, add:
```php
$this->get(route('purchasing.create'))
    ->assertOk()
    ->assertDontSee('max-w-6xl');
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```powershell
php artisan test --filter=test_purchasing_views_render_safely
```
Expected: FAIL because `purchasing/create.blade.php` currently uses `max-w-6xl mx-auto`.

- [ ] **Step 3: Refactor `resources/views/purchasing/index.blade.php`, `create.blade.php`, `edit.blade.php`, and `show.blade.php`**

1. `resources/views/purchasing/index.blade.php`:
   - Remove verbose subtitle from `<x-page-header title="Purchase Order & Pengadaan">`.
   - Compact the 6 KPI cards at the top (`p-3.5 rounded-lg`), converting decorative colored borders/backgrounds on neutral cards to monochrome while keeping semantic accents only for SLA overdue/revisi/hutang alerts.
   - Integrate the PO filter toolbar (`#filter-status-po`, `#filter-stage-po`, `#filter-kategori-supplier`, `#btn-reset-po`) directly into the top of the DataTables card (`border-b border-border bg-muted/10 px-4 py-2.5`) instead of a separate stacked `<x-card class="mb-4">`.
   - Preserve all table IDs (`#tbl`, `#tblAp`, `#tblApproval`, `#tblLtSupplier`, `#tblLtProduk`, `#tblLtTransaksi`) and tab query handling (`?tab=po&search=...`).
2. `resources/views/purchasing/create.blade.php` & `resources/views/purchasing/edit.blade.php`:
   - Remove `max-w-6xl mx-auto` wrapper → use `w-full space-y-4`.
   - Keep all Alpine `poForm()` logic, UOM conversions, global discount/PPN/ongkir/adjustment calculations, and input names untouched.
3. `resources/views/purchasing/show.blade.php`:
   - Tighten card spacing (`space-y-4`, `gap-4`) and table density (`px-3 py-2 text-xs tabular-nums`).
   - Preserve all 9-stage action forms, commitment revision modal (`revisiForm()`), and `isSubmitDisabled()` helper.

- [ ] **Step 4: Refactor `resources/views/analisa/index.blade.php`, `create-po.blade.php`, and `partials/*.blade.php`**

1. `resources/views/analisa/index.blade.php`:
   - Compact `<x-page-header title="Analisa & Rekomendasi Stok">` (remove long subtitle).
   - Compact tab navigation and bulk selection bar (`Buat PO dari Pilihan`).
2. `resources/views/analisa/create-po.blade.php`:
   - Replace `max-w-6xl mx-auto space-y-6` at line 89 with `w-full space-y-4`.
3. `resources/views/analisa/partials/table-lokal.blade.php`, `table-impor.blade.php`, `table-fulfillment.blade.php`, `table-riwayat.blade.php`:
   - Tighten table cell padding (`px-3 py-2 text-xs tabular-nums`) and keep the `Histori LT` button linking to `route('purchasing.index', ['tab' => 'po'])`.

- [ ] **Step 5: Run Purchasing & Analisa test suites**

Run:
```powershell
php artisan test --filter="ViewRenderingSafetyTest|PurchaseOrderRevisionAndDirectEditTest|PurchasingNineStageAndLeadTimeTest"
```
Expected: PASS (all tests green).

- [ ] **Step 6: Commit Task 4**

```powershell
git add resources/views/purchasing/ resources/views/analisa/ tests/Feature/ViewRenderingSafetyTest.php
git commit -m "refactor(purchasing,analisa): fluid layout, integrated filter toolbar, and dense tables"
```

---

### Task 5: Production, Inventory & Logistics Modules (Batches, Stok, Requests, Transfers, RT, BOM)

**Files:**
- Modify: `resources/views/batches/index.blade.php`
- Modify: `resources/views/batches/create.blade.php`
- Modify: `resources/views/batches/show.blade.php`
- Modify: `resources/views/stok/index.blade.php`
- Modify: `resources/views/stok/opname.blade.php`
- Modify: `resources/views/stok/ledger.blade.php`
- Modify: `resources/views/requests/index.blade.php`
- Modify: `resources/views/requests/create.blade.php`
- Modify: `resources/views/requests/show.blade.php`
- Modify: `resources/views/transfers/index.blade.php`
- Modify: `resources/views/transfers/create.blade.php`
- Modify: `resources/views/transfers/show.blade.php`
- Modify: `resources/views/request-transfer/index.blade.php`
- Modify: `resources/views/request-transfer/create.blade.php`
- Modify: `resources/views/request-transfer/edit.blade.php`
- Modify: `resources/views/request-transfer/show.blade.php`
- Modify: `resources/views/bom/index.blade.php`
- Modify: `resources/views/bom/edit.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: Task 1 fluid shell & Task 2 components.
- Produces: Unified page headers, fluid form layouts (`w-full`), monochrome KPI strips, and preserved A4 print containers on `surat-jalan.blade.php`.

- [ ] **Step 1: Add fluid container assertions for Batches, RT, Stok Opname, and BOM in `tests/Feature/ViewRenderingSafetyTest.php`**

In `tests/Feature/ViewRenderingSafetyTest.php`:
- In `test_batch_production_views_render_safely()`: assert `$this->get(route('batches.create'))->assertOk()->assertDontSee('max-w-4xl');`
- In `test_request_transfer_views_render_safely()`: assert `$this->get(route('rt.create'))->assertOk()->assertDontSee('max-w-5xl');`
- In `test_stok_and_ledger_views_render_safely()`: assert `$this->get(route('stok.opname'))->assertOk()->assertDontSee('max-w-4xl');`

- [ ] **Step 2: Run test to verify it fails**

Run:
```powershell
php artisan test --filter="test_batch_production_views_render_safely|test_request_transfer_views_render_safely|test_stok_and_ledger_views_render_safely"
```
Expected: FAIL because those views currently have `max-w-4xl` / `max-w-5xl`.

- [ ] **Step 3: Refactor `batches/*` and `bom/*`**

1. `resources/views/batches/index.blade.php`:
   - Add `<x-page-header title="Batch Produksi">` with the `+ Buat Batch Baru` action button in the `<x-slot name="actions">` slot (matching all other modules) instead of burying the button inside `<x-card>`.
2. `resources/views/batches/create.blade.php`:
   - Remove `max-w-4xl mx-auto` at line 28 → `w-full`. Use a 2-column responsive layout (`grid grid-cols-1 lg:grid-cols-12 gap-4`) so form inputs and BOM preview sit cleanly side-by-side on wide screens.
3. `resources/views/batches/show.blade.php`:
   - Tighten spacing (`space-y-4`) and table density (`px-3 py-2 text-xs tabular-nums`).
4. `resources/views/bom/index.blade.php` & `resources/views/bom/edit.blade.php`:
   - In `bom/edit.blade.php`: replace `mx-auto max-w-4xl space-y-6` with `w-full space-y-4`.

- [ ] **Step 4: Refactor `stok/*`, `requests/*`, `transfers/*`, and `request-transfer/*`**

1. `resources/views/stok/index.blade.php`:
   - Merge `#filter-gudang` and `#filter-kategori` into the top toolbar of the DataTables card (`border-b border-border bg-muted/10 px-4 py-2.5`) instead of a separate card.
2. `resources/views/stok/opname.blade.php` & `resources/views/stok/ledger.blade.php`:
   - In `stok/opname.blade.php`: replace `w-full max-w-4xl space-y-6` with `w-full space-y-4`.
   - In `stok/ledger.blade.php`: compact KPI summary and ledger table rows (`px-3 py-2 text-xs tabular-nums`).
3. `resources/views/transfers/index.blade.php`, `create.blade.php`, `show.blade.php`:
   - In `transfers/index.blade.php`: remove the decorative `bg-gradient-to-br` banner, rose icon box, and rainbow stat numbers (`text-blue-600`, `text-indigo-600`); replace with clean `<x-page-header title="Transfer Barang & Retur">` and monochrome `<x-stat-box>` strip.
   - **IMPORTANT:** Do NOT remove `max-w-3xl` from `resources/views/transfers/surat-jalan.blade.php`.
4. `resources/views/request-transfer/index.blade.php`, `create.blade.php`, `edit.blade.php`, `show.blade.php`:
   - In `request-transfer/create.blade.php` and `edit.blade.php`: replace `max-w-5xl mx-auto` with `w-full`.
   - **IMPORTANT:** Do NOT remove `max-w-3xl` from `resources/views/request-transfer/surat-jalan.blade.php`.
5. `resources/views/requests/index.blade.php`, `create.blade.php`, `show.blade.php`:
   - Standardize `<x-page-header>` and compact table density.

- [ ] **Step 5: Run feature tests for Batches, Stok, Requests, Transfers, and RT**

Run:
```powershell
php artisan test --filter="ViewRenderingSafetyTest|BatchProductionFlowTest|StokAndBomTest"
```
Expected: PASS.

- [ ] **Step 6: Commit Task 5**

```powershell
git add resources/views/batches/ resources/views/bom/ resources/views/stok/ resources/views/requests/ resources/views/transfers/ resources/views/request-transfer/ tests/Feature/ViewRenderingSafetyTest.php
git commit -m "refactor(ops,inventory): fluid layout and monochrome headers for production and logistics"
```

---

### Task 6: Master Data & Access Control Modules Fluid Standardization

**Files:**
- Modify: `resources/views/produk/index.blade.php`, `create.blade.php`, `edit.blade.php`, `show.blade.php`
- Modify: `resources/views/kategori/index.blade.php`, `create.blade.php`, `edit.blade.php`
- Modify: `resources/views/varian/index.blade.php`, `create.blade.php`, `edit.blade.php`
- Modify: `resources/views/klasifikasi-abc/index.blade.php`
- Modify: `resources/views/gudang/index.blade.php`, `create.blade.php`, `edit.blade.php`, `show.blade.php`
- Modify: `resources/views/supplier/index.blade.php`, `create.blade.php`, `edit.blade.php`, `show.blade.php`
- Modify: `resources/views/uom/index.blade.php`, `create.blade.php`, `edit.blade.php`
- Modify: `resources/views/divisi/index.blade.php`, `create.blade.php`, `edit.blade.php`
- Modify: `resources/views/users/index.blade.php`, `create.blade.php`, `edit.blade.php`
- Modify: `resources/views/roles/index.blade.php`, `create.blade.php`, `edit.blade.php`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: Task 1 fluid shell & Task 2 components.
- Produces: Consistent `<x-page-header>` across all Master Data index views and fluid `w-full` forms on all Master Data `create`/`edit` views.

- [ ] **Step 1: Add fluid assertions to `test_master_data_views_render_safely` in `tests/Feature/ViewRenderingSafetyTest.php`**

In `tests/Feature/ViewRenderingSafetyTest.php` inside `test_master_data_views_render_safely()`, add:
```php
$this->get(route('produk.create'))->assertOk()->assertDontSee('max-w-4xl');
$this->get(route('gudang.create'))->assertOk()->assertDontSee('max-w-3xl');
$this->get(route('supplier.create'))->assertOk()->assertDontSee('max-w-3xl');
$this->get(route('uom.create'))->assertOk()->assertDontSee('max-w-3xl');
$this->get(route('users.create'))->assertOk()->assertDontSee('max-w-5xl');
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```powershell
php artisan test --filter=test_master_data_views_render_safely
```
Expected: FAIL because Master Data `create` views currently contain `max-w-3xl` / `max-w-4xl` / `max-w-5xl`.

- [ ] **Step 3: Remove `max-w-*` wrappers and standardize headers across all Master Data & Access Control views**

1. **Index Views (`produk`, `kategori`, `varian`, `klasifikasi-abc`, `gudang`, `supplier`, `uom`, `divisi`, `users`, `roles`):**
   - Ensure every index page uses `<x-page-header title="...">` with primary `+ Tambah ...` button in `<x-slot name="actions">` (specifically add `<x-page-header>` to `produk/index.blade.php` where it was previously missing, and integrate `#filter-tipe` into the card header toolbar).
   - Remove verbose marketing subtitles.
2. **Create & Edit Views:**
   - Replace `mx-auto max-w-2xl`, `mx-auto max-w-3xl`, `mx-auto max-w-4xl`, and `mx-auto max-w-5xl` outer wrappers with `w-full space-y-4`:
     - `resources/views/produk/create.blade.php`, `edit.blade.php`
     - `resources/views/kategori/create.blade.php`, `edit.blade.php`
     - `resources/views/varian/create.blade.php`, `edit.blade.php`
     - `resources/views/gudang/create.blade.php`, `edit.blade.php`
     - `resources/views/supplier/create.blade.php`, `edit.blade.php`
     - `resources/views/uom/create.blade.php`, `edit.blade.php`
     - `resources/views/divisi/create.blade.php`, `edit.blade.php`
     - `resources/views/users/create.blade.php`, `edit.blade.php`
     - `resources/views/roles/create.blade.php`, `edit.blade.php`
   - Use responsive multi-column grids (`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4`) inside form cards so inputs remain well-proportioned on fluid widescreen monitors.

- [ ] **Step 4: Run Master Data & Roles safety tests**

Run:
```powershell
php artisan test --filter="test_master_data_views_render_safely|test_roles_views_render_safely_without_lazy_loading_violation"
```
Expected: PASS.

- [ ] **Step 5: Commit Task 6**

```powershell
git add resources/views/produk/ resources/views/kategori/ resources/views/varian/ resources/views/klasifikasi-abc/ resources/views/gudang/ resources/views/supplier/ resources/views/uom/ resources/views/divisi/ resources/views/users/ resources/views/roles/ tests/Feature/ViewRenderingSafetyTest.php
git commit -m "refactor(master-data): fluid form containers and standardized page headers"
```

---

### Task 7: Final Build, Global `max-w-*` Audit & Full Regression Gate

**Files:**
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes: Tasks 1–6.
- Produces: Verified production build (`public/build/*`) and 100% green PHPUnit suite.

- [ ] **Step 1: Verify no non-modal/non-print `max-w-*` layout bottlenecks remain**

Run:
```powershell
php artisan view:clear
php artisan view:cache
php artisan view:clear
```
Expected: `Blade templates cached successfully.`

- [ ] **Step 2: Build frontend production assets**

Run:
```powershell
npm run build
```
Expected: Vite build succeeds with `0 errors`.

- [ ] **Step 3: Run full test suite**

Run:
```powershell
php artisan test
```
Expected: 100% PASS across `ViewRenderingSafetyTest` and all feature/unit test suites.

- [ ] **Step 4: Final commit (if build assets are tracked)**

```powershell
git status -s
```
If clean, ready for final review/push.

