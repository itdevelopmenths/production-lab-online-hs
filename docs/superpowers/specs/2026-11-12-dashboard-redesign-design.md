# Dashboard Operasional Redesign (Option A: Command Split + Tabbed Context)

**Date:** 2026-11-12  
**Status:** Draft (Awaiting User Approval)  
**Target File:** `resources/views/dashboard.blade.php` (plus container-responsive adjustment in `resources/views/partials/defect-dashboard.blade.php`)

---

## 1. Intent & Why

Redesign and restructure `dashboard.blade.php` to solve three concrete UX problems identified on desktop and 13–14" laptop screens:
1. **Excessive vertical scrolling:** Four stacked full-width tiers (KPI grid, action queue, dual streams, and the 12-month defect widget) push critical items far below the fold.
2. **Card density & visual clutter:** Five separate bordered `<x-stat-box>` cards with repeated footers plus multiple stacked full-width list containers feel crowded on laptop viewports.
3. **Opaque card/row statuses for first-time users:** Raw internal stage/status pills (e.g., `Approval`, `Menunggu Revisi`, `Otorisasi Transfer`, `Release`, `Draft Surat Jalan`) do not tell new users what each state means or what happens next.

This redesign stays strictly faithful to the existing neutral OKLCH enterprise theme (`bg-card`, `border-border`, monospace tabular figures, semantic status accents) and the proportional root scaling system (`html.hs-app-shell`), with zero decorative gradients, hero banners, or unnecessary charts.

---

## 2. Goals, Non-Goals & Anti-Goals

### Goals
- Fit primary daily work (KPI overview, pending actions, and active stream) cleanly above the fold on 13–14" laptop screens.
- Reduce container chrome by unifying the 5 KPI metrics into a single divided metric ribbon.
- Provide immediate status clarity for first-time users via a compact color legend and plain-language micro-explanations on both action queue rows and stream items.
- Consolidate `$streamLeft`, `$streamRight`, and `partials/defect-dashboard` into a tabbed right-hand workspace panel without dropping any server-rendered content.
- Preserve 100% compatibility with all 5 role builders in `DashboardController` (`manager`, `purchasing`, `gudang`, `operasional`, `fulfillment`, plus `default`) and all existing Feature tests.

### Non-Goals
- No database schema changes, route changes, or business logic alterations in `DashboardController`.
- No addition of chart libraries, external JS plugins, or unrequested analytics widgets.
- No visual regressions to `partials/defect-dashboard.blade.php` when rendered on `transfers/index.blade.php`.

### Anti-Goals
- Must NOT introduce `max-w-7xl`, `bg-gradient-to-r`, decorative emojis, or AI-slop KPI decorations (asserted against in `ViewRenderingSafetyTest`).
- Must NOT conditionally unmount tabs from HTML with `x-if` (which would break SSR assertions and browser Find-in-Page).

---

## 3. Constraints

- **Tailwind CSS v4 + Alpine.js** only (existing stack).
- **Proportional laptop scale** (`13–14"`): `min-w-0` and `truncate` / wrapping guards must remain strictly applied so long supplier names, SKU labels, or badges never cause horizontal overflow or cramped overlaps inside the 7/12 and 5/12 columns.
- **Test contracts preserved:**
  - `ViewRenderingSafetyTest`: verifies all 5 role KPI titles, `$actionQueue['title']`, `$streamLeft['title']`, `$streamRight['title']`, `Heaven Scent`, and absence of `max-w-7xl`, `bg-gradient-to-r`, `👋`.
  - `DashboardTest`: verifies defect analytics headings (`Dashboard Barang Rusak & Defect Mutasi`, `Total Barang Rusak`, `Defect Rate`), permission scoping (`defect.view`), and links to PO/Transfer/Batch/Request detail pages.

---

## 4. Approaches Considered

1. **Option A — Command Split (7/5) + Unified Metric Strip + Tabbed Context Panel *(Selected)***  
   - **Trade-off:** Fits daily work into a single laptop viewport with minimal chrome; secondary stream and defect analytics take one tab click to switch between.
2. **Option B — Dual Top Workspace Tabs (`Ringkasan Harian` vs `Audit Barang Rusak`) + 6/6 Split**  
   - **Trade-off:** Keeps both streams visible simultaneously, but stacks two cards vertically on the right (taller page) and offers no top-level split benefit for roles without `defect.view`.
3. **Option C — 3 Parallel Columns + Collapsible Defect Bar**  
   - **Trade-off:** Shows everything side-by-side without tabs, but narrows each column enough to risk text cramping on 13" screens.

---

## 5. Selected Architecture (Option A)

### 5.1 Header & First-User Status Legend
- Keep `<x-page-header title="Dashboard Operasional">` with role badge and quick actions (`Analisa Stok`, `Buat PO`) gated by existing `@can` permissions.
- Directly inside the header's subtitle/meta strip, include a restrained 1-line **Status Guide** (`Butuh Tindakan` amber dot · `Kritis / Terlambat` red dot · `Dalam Proses` sky dot · `Siap / Selesai` emerald dot) so new users decode badge colors at a glance.

### 5.2 Layer 1: Unified 5-Cell Metric Strip
- Replace 5 separate `<x-stat-box>` cards (which each add outer padding, border, and repeated `Lihat Rincian ->` footer rows) with a single cohesive surface:
  - Container: `bg-card border border-border rounded-lg shadow-2xs overflow-hidden mb-4`
  - Grid: `grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-border`
  - Each KPI item becomes a clickable block (`<a href="...">` when `href` is set, otherwise `<div>`) with subtle `hover:bg-muted/40 transition-colors`, keeping the exact `$kpi['title']`, `$kpi['value']` (`font-mono tabular-nums`), compact icon, and `$kpi['subtitle']`.

### 5.3 Layer 2: Asymmetric Command Split (`lg:grid-cols-12 gap-4 items-start`)

#### Left Column (`lg:col-span-7`): Urgent Action Center (`$actionQueue`)
- Shows `$actionQueue['title']`, `$actionQueue['subtitle']`, an active item counter badge, and `Lihat Semua ->`.
- Each item row presents:
  1. **Semantic Status Badge** (`$item->badge_label` + optional `Terlambat` danger badge).
  2. **Document ID & Entity Meta** (`$item->nomor`, `$item->meta`).
  3. **Plain-Language Next-Step Hint** derived via a clean Blade helper mapping common stage/status terms to human-readable guidance (e.g., *Persetujuan Manager* -> `"Menunggu keputusan Anda untuk lanjut ke vendor"`, *Menunggu Revisi* -> `"Periksa perubahan sebelum disetujui"`, *Otorisasi Transfer* -> `"Perlu izin keluar stok antar gudang"`, *Draft Surat Jalan* -> `"Siapkan fisik barang & cetak surat jalan"`, *Kurang Bahan* -> `"Cek kekurangan bahan baku di analisa stok"`).
  4. **Primary CTA button** (`$item->action_label`).
- Scroll containment: on large screens (`lg:max-h-[34rem] lg:overflow-y-auto`), long action queues scroll cleanly inside the card while preserving full-list access via `Lihat Semua ->`.

#### Right Column (`lg:col-span-5`): Tabbed Context Panel (`$streamLeft`, `$streamRight`, `partials/defect-dashboard`)
- Accessible ARIA tablist (`role="tablist"`, `role="tab"` buttons with `aria-selected`, keyboard-accessible focus states, and `role="tabpanel"` panels):
  1. **Tab 1 (`streamLeft`):** `$streamLeft['title']` + count badge.
  2. **Tab 2 (`streamRight`):** `$streamRight['title']` + count badge.
  3. **Tab 3 (`defect` — rendered only when `@can('defect.view') && !empty($defectAnalytics)`):** `Analisa Defect` + rate pill (`{{ number_format($defectAnalytics['summary']['defect_rate_percent'] ?? 0, 2) }}%`).
- **Auto-tab selection on reload:** Default tab is `streamLeft`. If the URL contains defect filter query parameters (`year`, `month`, `gudang_id`) or hash `#defect-dashboard`, Alpine initializes directly on the `defect` tab so filter submissions stay on the defect view.
- **Hidden via CSS class (`:class="activeTab !== '...' ? 'hidden' : ''"`) rather than `x-if`:** Preserves all SSR HTML in the DOM for Laravel feature tests (`assertSee`) and browser Ctrl+F search.
- **Stream item clarity:** Each row in `streamLeft` / `streamRight` retains its existing title, subtitle, clickable link, and status badge, supplemented by plain-language status hints mapped cleanly in the view helper.
- **Embedded Defect Widget Compatibility:** `partials/defect-dashboard.blade.php` is wrapped with Tailwind container-query support (`@container`) or an `$embedded` flag so inside the narrower `5/12` column its 5 summary metrics wrap cleanly (`grid-cols-2 @xl:grid-cols-5`), its filter bar wraps cleanly without cramping, and the monthly matrix table scrolls cleanly inside `overflow-x-auto` with a direct link to open full view on `transfers.index#defect-dashboard` if desired.

---

## 6. Verification Plan

After implementation:
1. Build frontend assets with `npm run build` cleanly.
2. Run Laravel feature test suite (`DashboardTest`, `ViewRenderingSafetyTest`, `AuthorizationTest`, `TransferMonthlyWarehouseDefectDashboardTest`, and full `php artisan test`).
3. Visually verify `/dashboard` across desktop (`1440px`) and laptop (`1280px` / `1366px`) viewports (using the browser tool/subagent if helpful) across roles with and without `defect.view`.

