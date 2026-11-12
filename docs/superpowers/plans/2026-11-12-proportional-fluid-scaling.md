# Proportional Fluid Scaling & Laptop Viewport Normalization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuat antarmuka Blade di layar laptop 13–14 inci (`1280px–1536px` CSS viewport) mengecil secara proporsional dengan jumlah kolom per baris identik seperti desktop (`1920px+`), bebas komponen yang saling berdempetan/overlapping.

**Architecture:** Menggunakan *Bounded Fluid Root Scale* di [`resources/css/app.css`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/css/app.css) khusus layar `screen and (min-width: 64rem)` yang ditargetkan ke `html.hs-app-shell` di [`app-layout.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/app-layout.blade.php), merampingkan sidebar expanded ke `lg:w-56`, membekali komponen inti (`page-header`, `card`, `stat-box`, `purchasing-progress-stepper`) dengan guard wrapping & `min-w-0`, serta mengonversi control/filter `min-w-[...px]` kaku ke ukuran proporsional berbasis `rem`.

**Tech Stack:** Laravel 12, Blade Templates, Tailwind CSS v4 (`@tailwindcss/vite`), Alpine.js, Yajra DataTables, PHPUnit (`ViewRenderingSafetyTest`).

**Spec:** `docs/superpowers/specs/2026-11-12-proportional-fluid-scaling-design.md`

## Global Constraints
- Skala root proporsional `clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem)` **hanya** aktif pada `@media screen and (min-width: 64rem)` untuk `html.hs-app-shell`, dengan `@media print { html.hs-app-shell { font-size: 100%; } }`.
- Mobile/tablet (`< 1024px`), dokumen cetak (`surat-jalan.blade.php`), dan halaman login (`auth/login.blade.php`) tetap pada skala dasar `100%` (`16px`).
- Jumlah kolom grid per baris desktop (`lg:grid-cols-5`, `sm:grid-cols-5`, `sm:grid-cols-4`, `lg:grid-cols-6`, `lg:grid-cols-3`, dll.) **wajib dipertahankan** — tidak menurunkan jumlah kolom pada laptop `1280px–1536px`.
- Dilarang memakai CSS `zoom` atau `transform: scale()` dan dilarang menambah wrapper pembatas `max-w-4xl` s/d `max-w-7xl` (patuh `ViewRenderingSafetyTest.php`).
- Seluruh ID DataTables (`#tbl*`, `#filter-*`, `#btn-reset*`) dan Alpine bindings (`appSidebarState`, `toggleCollapsed`, `menuQuery`) wajib tetap identik.

## Review Focus
1. Zoom browser `Ctrl++` (hingga 200%) dan preferensi font non-16px pada `html.hs-app-shell` tetap memperbesar teks lancar tanpa terkunci (`max / min = 1.23x`, `< 1024px` otomatis kembali ke `1rem` = `100%`).
2. Halaman di luar `app-layout` (`transfers/surat-jalan.blade.php` & `auth/login.blade.php`) serta mode print tetap berukuran `100%` normal.
3. Toggle sidebar expanded (`lg:w-56`) ↔ collapsed (`lg:w-16`) tetap memicu `columns.adjust()` DataTables sehingga header kolom tidak bergeser pada `1280px` dan `1366px`.
4. Halaman dengan header padat (`purchasing/show`, `batches/show`, `dashboard`) tetap membungkus badge/tombol dengan rapi tanpa memotong judul utama maupun menimbulkan overflow horizontal.
5. `<x-stat-box>` dengan nilai teks panjang (mis. nama gudang di `batches/show`) dan panel split `lg:grid-cols-3` (`purchasing/show`, `batches/show`) tidak berbenturan atau memicu horizontal bleed pada `1280×720`.

---

### Task 1: Bounded Fluid Root Scale, App Shell Sidebar Slimming & DataTables CSS Normalization

**Files:**
- Modify: `resources/css/app.css:64-174`
- Modify: `resources/views/components/app-layout.blade.php:8-106,563-597`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Produces CSS class contract: `html.hs-app-shell` di [`app-layout.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/app-layout.blade.php#L9) ditarget oleh rule `@media screen and (min-width: 64rem)` di [`app.css`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/css/app.css).
- Produces DataTables toolbar classes: `.dt-layout-header`, `.dt-layout-footer`, `.dataTables_filter input` ber-`flex-wrap` dan lebar `rem` proporsional.

- [ ] **Step 1: Tambahkan test gagal (`RED`) di [`tests/Feature/ViewRenderingSafetyTest.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/tests/Feature/ViewRenderingSafetyTest.php)**
  - Tambahkan method `test_proportional_fluid_scaling_shell_and_css_rules`:
    - Assert `GET /dashboard` (sebagai `manager@heavenscent.id`) merender class `hs-app-shell` pada tag `<html>`, merender `lg:w-56`, dan `$response->assertDontSee('lg:w-64')`.
    - Assert `GET /login` dan route `transfers.surat-jalan` (atau view render) tidak memakai `hs-app-shell`.
    - Assert isi file `resources/css/app.css` memuat `html.hs-app-shell`, `clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem)`, `@media print`, dan tidak lagi memuat `sm:min-w-[240px]` pada rule `.dataTables_filter input`.

- [ ] **Step 2: Jalankan test untuk memverifikasi `RED`**
  - Run: `php artisan test tests/Feature/ViewRenderingSafetyTest.php --filter=test_proportional_fluid_scaling_shell_and_css_rules`
  - Expected: FAIL karena `hs-app-shell`, `lg:w-56`, dan CSS rule baru belum ada.

- [ ] **Step 3: Implementasi Fluid Root Scale & DataTables CSS di [`resources/css/app.css`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/css/app.css)**
  - Di `@layer base`:
    - Tambahkan `@media screen and (min-width: 64rem)` dengan selector `html.hs-app-shell { font-size: clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem); }`.
    - Tambahkan `@media print { html.hs-app-shell { font-size: 100%; } }`.
    - Uji `--text-xs: max(0.75rem, 10.5px);` di `@theme` atau utilitas teks DataTables bila kompilasi LightningCSS/`@tailwindcss/vite` mendukung bersih tanpa memicu warning atau inkonsistensi ukuran tombol `xs`.
  - Di section DataTables (`L86-L173`):
    - `.dt-layout-header`: pastikan memakai `flex flex-wrap justify-between items-center px-4 py-2.5 border-b border-border gap-2.5 bg-muted/20`.
    - `.dt-layout-footer`: pastikan memakai `flex flex-wrap justify-between items-center px-4 py-2 bg-muted/10 border-t border-border gap-2 text-xs text-muted-foreground rounded-b-lg`.
    - `.dataTables_wrapper .dataTables_filter input`: ganti `min-w-[200px] sm:min-w-[240px]` menjadi `w-full sm:w-52 min-w-0`.

- [ ] **Step 4: Implementasi `hs-app-shell` & perampingan sidebar di [`resources/views/components/app-layout.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/app-layout.blade.php)**
  - Ubah tag `<html lang="id" class="h-full hs-app-shell bg-[oklch(0.985_0_0)]">` (L9).
  - Pastikan wrapper `<div class="flex min-h-screen w-full min-w-0">` (L91).
  - Ubah kelas lebar sidebar desktop `collapsed ? 'lg:w-16' : 'lg:w-64'` menjadi `collapsed ? 'lg:w-16' : 'lg:w-56'` (L105), pertahankan `mobileOpen ? 'translate-x-0 w-64' : ...` (L104).
  - Rapikan `<main class="flex-1 min-w-0 px-4 py-4 sm:px-5 lg:px-6 lg:py-4">` (L595).

- [ ] **Step 5: Build aset Vite & jalankan test verifikasi (`GREEN`)**
  - Run: `npm run build` dan `php artisan test tests/Feature/ViewRenderingSafetyTest.php --filter=test_proportional_fluid_scaling_shell_and_css_rules`
  - Expected: Build `npm run build` sukses bersih & test PASS.

- [ ] **Step 6: Commit**
  - Run: `git add resources/css/app.css resources/views/components/app-layout.blade.php tests/Feature/ViewRenderingSafetyTest.php && git commit -m "feat(ui): add bounded proportional fluid root scale and slim lg:w-56 shell"`

---

### Task 2: Shared Blade Components Safety (`page-header`, `card`, `stat-box`, `purchasing-progress-stepper`)

**Files:**
- Modify: `resources/views/components/page-header.blade.php:10-59`
- Modify: `resources/views/components/card.blade.php:20-45`
- Modify: `resources/views/components/stat-box.blade.php:14-31`
- Modify: `resources/views/components/purchasing-progress-stepper.blade.php:144-238`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes CSS root scale dari Task 1 (`rem` scaling di `>= 1024px`).
- Preserves seluruh props komponen (`page-header`, `card`, `stat-box`, `purchasing-progress-stepper`) serta atribut `data-back-button="true"`, `aria-label`, dan `aria-current="step"`.

- [ ] **Step 1: Tambahkan test gagal (`RED`) di [`tests/Feature/ViewRenderingSafetyTest.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/tests/Feature/ViewRenderingSafetyTest.php)**
  - Tambahkan method `test_shared_layout_components_prevent_cramping_on_laptop_viewports`:
    - Render langsung atau via Blade::render pada `<x-page-header>`, `<x-card>`, `<x-stat-box value="Gudang Operasional Formulation Panjang">` dan `<x-purchasing-progress-stepper>` (melalui PO ber-stage di DB atau view render).
    - Assert wrapper kanan `page-header` memakai `sm:justify-end` & `flex-wrap`, inner `stat-box` teks memiliki `flex-1 min-w-0` + `break-words`, header `card` memiliki `flex-1 min-w-0` + `flex-wrap`, dan `purchasing-progress-stepper` memakai `min-w-[44rem] lg:min-w-0` serta tidak lagi memakai `sm:min-w-0` atau `max-w-[85px]`.

- [ ] **Step 2: Jalankan test untuk memverifikasi `RED`**
  - Run: `php artisan test tests/Feature/ViewRenderingSafetyTest.php --filter=test_shared_layout_components_prevent_cramping_on_laptop_viewports`
  - Expected: FAIL karena class proteksi wrapping/lebar baru belum diterapkan.

- [ ] **Step 3: Perbarui [`page-header.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/page-header.blade.php), [`card.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/card.blade.php), [`stat-box.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/stat-box.blade.php), dan [`purchasing-progress-stepper.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/purchasing-progress-stepper.blade.php)**
  - `page-header.blade.php`: blok kiri `min-w-0 flex-1`; wrapper kanan `flex flex-wrap items-center sm:justify-end gap-x-3 gap-y-2`; tetap jaga breadcrumbs, slot `actions`, dan `data-back-button="true"`.
  - `card.blade.php`: wrapper ikon+judul menggunakan `flex items-center gap-2.5 min-w-0 flex-1` dengan wrapper teks `min-w-0 flex-1`; slot `tools` memakai `flex flex-wrap items-center gap-2 shrink-0`.
  - `stat-box.blade.php`: wrapper teks kiri `flex-1 min-w-0`; nilai `$value` menggunakan `mt-1 text-2xl font-bold font-mono tabular-nums text-foreground tracking-tight leading-tight break-words` agar teks panjang tidak bertabrakan dengan badge/ikon.
  - `purchasing-progress-stepper.blade.php`: ubah container scroll (`L144-145`) dengan `tabindex="0" role="region" aria-label="Scroll Tahapan Purchase Order"` dan `<nav aria-label="Tahapan Purchase Order" class="min-w-[44rem] lg:min-w-0">`; pada tiap `<li ... class="flex flex-col items-center text-center group flex-1 min-w-0 px-1 relative z-10">` dan hapus `truncate max-w-[85px]` (ganti `text-[11px] leading-tight text-balance` dan subtitle `hidden sm:block text-[10px] leading-tight text-muted-foreground`).

- [ ] **Step 4: Build & jalankan test verifikasi (`GREEN`)**
  - Run: `npm run build` dan `php artisan test tests/Feature/ViewRenderingSafetyTest.php --filter=test_shared_layout_components_prevent_cramping_on_laptop_viewports`
  - Expected: Build sukses & test PASS.

- [ ] **Step 5: Commit**
  - Run: `git add resources/views/components/page-header.blade.php resources/views/components/card.blade.php resources/views/components/stat-box.blade.php resources/views/components/purchasing-progress-stepper.blade.php tests/Feature/ViewRenderingSafetyTest.php && git commit -m "refactor(components): add flex-wrap and min-w-0 guards for laptop density"`

---

### Task 3: High-Density Action Bars, Split Panels & Rigid `min-w-[...px]` Filter/Dropdown Normalization

**Files:**
- Modify: `resources/views/dashboard.blade.php:4`
- Modify: `resources/views/batches/show.blade.php:110-115`
- Modify: `resources/views/purchasing/index.blade.php:108-200`
- Modify: `resources/views/purchasing/show.blade.php:402,503,697-715,1917-1918`
- Modify: `resources/views/stok/index.blade.php:24-45`
- Modify: `resources/views/stok/ledger.blade.php:10-20`
- Modify: `resources/views/stok/pergerakan-log.blade.php:11-31`
- Modify: `resources/views/analisa/index.blade.php:133-159`
- Modify: `resources/views/users/index.blade.php:28-53`
- Modify: `resources/views/reports/defect.blade.php:11-53,78-100`
- Modify: `resources/views/reports/lead-time.blade.php:11-32`
- Modify: Dropdown autocomplete `min-w-[320px]` di `analisa/create-po.blade.php:261`, `batches/create.blade.php:181`, `bom/edit.blade.php:99`, `bom/import.blade.php:217,310`, `purchasing/create.blade.php:257`, `purchasing/edit.blade.php:252`, `requests/create.blade.php:347`, `requests/edit.blade.php:286`, `transfers/create.blade.php:610`
- Test: `tests/Feature/ViewRenderingSafetyTest.php`

**Interfaces:**
- Consumes proportional `rem` scale dari Task 1 sehingga kelas `min-w-36`, `min-w-44`, `min-w-64 sm:min-w-80` otomatis mengecil proporsional di `1280px–1536px` sambil menjaga seluruh ID filter (`#filter-status-po`, `#filter-gudang`, `#filterDivisi`, dll.) dan jumlah kolom grid (`lg:grid-cols-5`, `lg:grid-cols-6`, `sm:grid-cols-5`, `lg:grid-cols-3`).

- [ ] **Step 1: Tambahkan test gagal (`RED`) di [`tests/Feature/ViewRenderingSafetyTest.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/tests/Feature/ViewRenderingSafetyTest.php)**
  - Tambahkan method `test_operational_views_use_fluid_filters_and_wrapping_toolbars`:
    - Assert halaman `dashboard` menjaga grid `lg:grid-cols-5` dan actions memakai `flex-wrap`.
    - Assert halaman `purchasing.index`, `stok.index`, `analisa.index`, `users.index`, `reports.defect`, `reports.lead-time` **tidak** lagi merender `min-w-[170px]`, `min-w-[180px]`, `min-w-[190px]`, `min-w-[200px]`, `min-w-[220px]`, `min-w-[280px]`, sementara grid utama `lg:grid-cols-6` (pada `reports.defect`) dan `sm:grid-cols-4` (pada `analisa.index`) tetap utuh.
    - Assert `batches.show` memiliki `lg:col-span-2 space-y-5 min-w-0` dan form `purchasing.create` tidak lagi memakai `min-w-[320px]`.

- [ ] **Step 2: Jalankan test untuk memverifikasi `RED`**
  - Run: `php artisan test tests/Feature/ViewRenderingSafetyTest.php --filter=test_operational_views_use_fluid_filters_and_wrapping_toolbars`
  - Expected: FAIL karena view masih memakai `min-w-[...px]` dan wrapper non-wrap.

- [ ] **Step 3: Normalisasi Header, Split Panels, Filter Toolbars & Autocomplete Dropdowns**
  - [`dashboard.blade.php:4`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/dashboard.blade.php#L4): ubah menjadi `flex flex-wrap items-center justify-end gap-2`.
  - [`batches/show.blade.php:112`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/batches/show.blade.php#L112) & panel kanan split `lg:grid-cols-3`: tambahkan `min-w-0` serta sesuaikan grid gap ke `gap-4`; lakukan audit yang sama pada kolom kanan `lg:grid-cols-3` di `purchasing/show.blade.php`.
  - [`purchasing/show.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/purchasing/show.blade.php): konversi `th` `min-w-[180px]` / `min-w-[200px]` / `min-w-[220px]` menjadi `min-w-36` / `min-w-44` dan ubah `gap-5` pada `lg:grid-cols-3` menjadi `gap-4`.
  - Konversi `<select>` / wrapper filter kaku:
    - `min-w-[120px]` -> `w-full sm:w-auto min-w-28 max-w-full`
    - `min-w-[150px]`–`min-w-[160px]` -> `w-full sm:w-auto min-w-36 max-w-full`
    - `min-w-[170px]`–`min-w-[200px]` -> `w-full sm:w-auto min-w-40 max-w-full`
    - Pada `analisa/index.blade.php:135-136`: ubah wrapper kiri menjadi `flex flex-wrap items-center gap-3 flex-1 min-w-0`, input search menjadi `relative flex-1 min-w-44 max-w-sm`.
    - Pada `reports/defect.blade.php:78-100`: tambahkan `min-w-0` pada keenam KPI card dalam `lg:grid-cols-6`.
  - Konversi 10 autocomplete dropdown `min-w-[320px]` menjadi `min-w-64 sm:min-w-80 max-w-[calc(100vw-2rem)]`.
  - Lakukan scan cepat `git grep -n "min-w-\[" resources/views/` setelah edit untuk memastikan tidak ada `min-w-[...px]` filter/toolbar tertinggal yang menyebabkan bentrok di `1280px`.

- [ ] **Step 4: Build & jalankan test verifikasi (`GREEN`)**
  - Run: `npm run build` dan `php artisan test tests/Feature/ViewRenderingSafetyTest.php`
  - Expected: Build sukses & seluruh test `ViewRenderingSafetyTest` PASS.

- [ ] **Step 5: Commit**
  - Run: `git add resources/views/ tests/Feature/ViewRenderingSafetyTest.php && git commit -m "refactor(views): normalize rigid pixel min-widths and wrap dense toolbars"`

---

### Task 4: Full Verification Suite & Multi-Viewport Browser Layout Verification

**Files:**
- Verify: Seluruh repo, `npm run build`, dan full `php artisan test`

- [ ] **Step 1: Jalankan seluruh PHPUnit test suite & Vite build**
  - Run: `npm run build`
  - Run: `php artisan test`
  - Expected: 100% seluruh suite PASS (0 failures, 0 errors).

- [ ] **Step 2: Verifikasi visual & bounding-box multi-viewport (`1920×1080`, `1536×864`, `1366×768`, `1280×720`, `390×844`)**
  - Jalankan preview app lokal + `browser` subagent / pemeriksaan DevTools untuk mengukur:
    - Rasio font root terkomputasi (`getComputedStyle(document.documentElement).fontSize`: ~16px @ 1920px, ~14.4px @ 1536px, ~13.7px @ 1366px, ~13.3px @ 1280px, 16px @ 390px).
    - Tidak ada `document.documentElement.scrollWidth > window.innerWidth` pada halaman utama (`dashboard`, `purchasing.index`, `purchasing.show`, `stok.index`, `analisa.index`, `reports.defect`).
    - Jumlah kolom grid utama per baris pada `1280×720` identik dengan `1920×1080` dan nol tumpang tindih antar elemen (`getBoundingClientRect()` bersih).

