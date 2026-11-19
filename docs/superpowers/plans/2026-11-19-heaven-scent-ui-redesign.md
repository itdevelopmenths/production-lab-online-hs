# Heaven Scent Monochrome Luxury UI Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Tailored for sequential or subagent-driven execution by **Gemini Hydrogen**. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Meredesain total seluruh antarmuka **production-lab-online-hs** (100 Blade views + 17 DataTables controllers) agar identik secara visual dan interaktif dengan **Heaven Scent Design System Blueprint** (Monochrome Luxury Black & White, Dual-Theme Light & Dark, tight-tracked Inter, micro-depth borders, dan 6 primitif UI baku) tanpa merusak logika bisnis atau test suite.

**Architecture:** Pendekatan *Bottom-Up Blade Design-System Architecture*: membangun fondasi token CSS & Dual-Theme bridge di `resources/css/app.css` + `resources/js/app.js` → Application Shell (`AppSidebar` `.sidebar-active-tab` + `#627EF2` submenu tree + sticky frosted glass `AppHeader` + Dark/Light toggle) → 6 primitif Blade (`Button`, `StatusTabs`, `StatsCard`, `Card`/4-zone `DataTable`, `AppForm`/`FormSection`/`FormField`/teleported `ProductSearchSelect`, Stepper/`Badge`) → `App\Support\UiTableHelper` untuk 17 DataTables controllers → migrasi seluruh halaman modul per gelombang dengan TDD & Vite build gate setelah setiap task.

**Tech Stack:** Laravel 13 Blade Templates, Alpine.js 3 (`^3.15.12`), Tailwind CSS v4 (`@tailwindcss/vite@^4.0.0`), `@fontsource/inter` (weights 300–800), `@fontsource/jetbrains-mono` (weights 400–600), Yajra DataTables (`jquery.dataTables`), SweetAlert2 (`^11.26.25`), Chart.js (`^4.5.1`), Vite 8, PHPUnit 12.

**Spec:** [`docs/superpowers/specs/2026-11-19-heaven-scent-ui-redesign-design.md`](../specs/2026-11-19-heaven-scent-ui-redesign-design.md)

**Environment Note for Executor (Linux / FlyEnv / Homebrew):**
- Di mesin lokal ini, PHP yang memiliki `pdo_pgsql` & SQLite serta Composer berada di `/home/linuxbrew/.linuxbrew/bin/php` (bukan static PHP FlyEnv), dan Node/npm berada di `/home/admin/.config/FlyEnv/app/nodejs/v26.13.1/bin`.
- Sebelum menjalankan `php artisan ...` atau `npm run build`, ekspor PATH (atau panggil binary lengkap):
  ```bash
  export PATH="/home/linuxbrew/.linuxbrew/bin:$HOME/.config/FlyEnv/app/nodejs/v26.13.1/bin:$PATH"
  ```
- PHPUnit memakai in-memory SQLite (`phpunit.xml`), sehingga berjalan cepat tanpa menyentuh database PostgreSQL lokal `sc_online`.

---

## Global Constraints

1. **Zero Business Logic / Route / Schema Regressions:** Dilarang mengubah route URL, route name, query controller bisnis, validasi payload, atau skema DB. `Model::preventLazyLoading(true)` wajib tetap lolos pada seluruh GET routes.
2. **Full Dual-Theme (Light & Dark):** Wajib mengaktifkan `@custom-variant dark (&:is(.dark *));`, kelas `.dark` pada `<html>`, inline anti-FOUC script di `<head>` sebelum CSS, dan preferensi tersimpan di `localStorage['hs.theme']`.
3. **Proportional Fluid Scaling (`html.hs-app-shell`):** Pertahankan `@media screen and (min-width: 64rem) { html.hs-app-shell { font-size: clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem); } }` dan `@media print { html.hs-app-shell { font-size: 100%; } }` di `resources/css/app.css`. Halaman `auth/login.blade.php` dan `transfers/surat-jalan.blade.php` **DILARANG** memakai class `hs-app-shell`.
4. **Larangan Class Layout Kaku (`ViewRenderingSafetyTest`):**
   - **JANGAN** gunakan `max-w-3xl`, `max-w-4xl`, `max-w-5xl`, `max-w-6xl`, `max-w-7xl` pada form/dashboard/CRUD pages.
   - **JANGAN** gunakan `bg-gradient-to-r` atau emoji `👋` di dashboard.
   - **JANGAN** gunakan `min-w-[120px]`, `min-w-[150px]`, `min-w-[160px]`, `min-w-[170px]`, `min-w-[180px]`, `min-w-[190px]`, `min-w-[200px]`, `min-w-[220px]`, `min-w-[280px]`, `min-w-[320px]`, atau `min-width: 320px` pada view operasional (`purchasing.*`, `stok.*`, `analisa.index`, `users.index`, `reports.*`).
   - Pertahankan grid column counts: `lg:grid-cols-5` (dashboard KPI), `lg:grid-cols-6` (`reports/defect`), `sm:grid-cols-4` (`analisa/index`), `lg:grid-cols-3 gap-4` + `lg:col-span-2 space-y-5 min-w-0` (`batches/show`).
5. **Blade Compiler & Alpine Safety (`ViewRenderingSafetyTest`):**
   - **JANGAN** gunakan `@json([...])` dengan literal array/bracket — selalu gunakan `@js(...)`.
   - **JANGAN** tulis operator perbandingan mentah `<` atau `>` di dalam atribut `x-bind:` atau `::` pada komponen `<x-*>`.
6. **Preserved DOM IDs, JS Hooks & Test Strings:** Pertahankan semua ID DataTables (`#tbl*`, `#usersTable`, `#filter-*`, `#btnResetFilter`), class exact `usersTable" class="w-full text-xs [&_thead_th]:!px-2.5 [&_tbody_td]:!px-2.5"`, struktur `roles_label` (`<div class="flex flex-wrap items-center gap-1">` + `inline-flex items-center whitespace-nowrap`), serta seluruh binding & teks di `analisa/*` dan `stok/opname`.
7. **Print Isolation:** `transfers/surat-jalan.blade.php` wajib tetap kertas putih pada mode cetak (`@media print`) dan tidak terpengaruh `.dark`.
8. **Atomic Verified Commits:** Setiap task selesai hanya jika `npm run build` + PHPUnit test milik task lulus GREEN, lalu commit atomik 1 commit per task.

---

## Review Focus

1. **Dark Mode FOUC & Orphaned Light-Only Classes:** Saat reload di Dark Mode, halaman tidak boleh berkedip putih (`hs-theme` script di `<head>` harus jalan sinkron sebelum paint), dan tidak boleh tersisa `bg-white`, `bg-zinc-50`, `bg-gray-100`, `text-slate-900` tanpa pasangan `dark:*` (diperiksa via test & grep audit di Task 10).
2. **`SearchableSelect` (`<x-product-search-select>`) Modal Clipping:** Dropdown di dalam `<x-modal>` atau `.table-wrapper` (`overflow-hidden`) sering terpotong; harus dipastikan memakai Alpine `x-teleport="body"` + `position: fixed` viewport coordinates (`z-index: 9999`) (diuji di Task 4).
3. **`ViewRenderingSafetyTest` Sidebar & Component Contract Drift:** Perubahan lebar sidebar dari `lg:w-56` menjadi `lg:w-[264px]` / `lg:w-[80px]` memengaruhi assertion di `ViewRenderingSafetyTest:496` — test harus diperbarui eksplisit bersamaan dengan Task 2 sambil mempertahankan semua invariant anti-overlap lainnya (diuji di Task 2 & Task 3).
4. **17 DataTables Controllers Action Square Accessibility & Test Safety:** Mengubah link teks `"Edit"` / `"Detail"` / `"Hapus"` menjadi tombol ikon kotak `size-[32px]` tidak boleh menghilangkan teks aksesibel (`<span class="sr-only">`) maupun atribut `title`/`aria-label`, dan wajib membawa kelas `dark:*` (diuji di `tests/Unit/UiTableHelperTest.php` di Task 5).
5. **Guest Login & A4 Surat Jalan Isolation:** Keduanya berdiri di luar `app-layout` tanpa `hs-app-shell`; login mendukung Light/Dark toggle dengan Glossy Dark Brand Badge, sedangkan Surat Jalan mengisolasi blok dokumen cetak agar tetap putih saat diprint (diuji di Task 6 & Task 8).

---

## Complete File Structure Map

| Layer | Files | Responsibility |
| :--- | :--- | :--- |
| **Tokens, Font, JS, Build** | `resources/css/app.css`, `resources/js/app.js`, `vite.config.js` | Blueprint CSS tokens (`--bg..--border-2`, half-step OKLCH grays, shadows), Dual-Theme bridge, `.sidebar-active-tab`, 4-zone DataTables CSS, SweetAlert2 & `<dialog>` theming, `window.hsTheme`, local Inter 300–800. |
| **Application Shell** | `resources/views/components/app-layout.blade.php`, `nav-item.blade.php`, `nav-group.blade.php` (new) | Anti-FOUC head script, `w-[264px]`/`w-[80px]` sidebar drawer, search bar `h-[44px]`, `.sidebar-active-tab`, collapsible `#627EF2` submenu tree, sticky `h-16` glass header + theme toggle. |
| **Core UI Primitives** | `resources/views/components/{button,card,stat-box,status-tabs,badge,alert,callout,page-header}.blade.php` | Glossy primary button + bottom glow, 4-zone card header, auto-tinted `StatsCard` (`28px`), segmented `StatusTabs`, Dual-Theme `Badge`, `Alert`, `Callout`, `PageHeader`. |
| **Form System** | `resources/views/components/{modal,form-section,form-field,form-group,input,select,textarea,checkbox,product-search-select}.blade.php` | Blueprint `AppForm` modal shell (`#F5F5F5`), `FormSection` eyebrow, `FormField` inline white card, seamless inputs, teleported `ProductSearchSelect`. |
| **DataTables Helper & 17 Controllers** | `app/Support/UiTableHelper.php` (new), `app/Http/Controllers/{Produk,Kategori,Varian,Gudang,Supplier,Uom,Divisi,KlasifikasiAbc,Bom,User,Role,Batch,Purchasing,Request,Transfer,Stok,OperationalAudit}Controller.php` | Standarisasi tombol aksi kotak `32×32px` + `sr-only` + status pills Dual-Theme di seluruh endpoint Yajra DataTables. |
| **Auth & Dashboard** | `resources/views/auth/login.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/partials/defect-dashboard.blade.php` | Glossy Dark Square Brand Badge login, contextual KPI strip, Urgent Action Center, Activity Streams, Defect analytics card + `<x-status-tabs>`. |
| **Workflow & Operasional** | `resources/views/components/purchasing-progress-stepper.blade.php`, `resources/views/{purchasing,batches,requests,transfers,stok}/*.blade.php` | 8-Stage `WorkflowStepper`, modals & `<dialog>`, forms (`FormSection`+`FormField`), 4-zone DataTable views, print-safe Surat Jalan. |
| **Analisa & Laporan** | `resources/views/analisa/{index,create-po}.blade.php`, `resources/views/analisa/partials/*.blade.php` (11 partials), `resources/views/reports/*.blade.php` (8 views) | Analisa `StatusTabs`, 4 table partials, 7 `AppForm` modals (preserve all Alpine hooks), 8 Reports views + KPI strip. |
| **Master Data & Sistem** | `resources/views/{produk,kategori,varian,gudang,supplier,uom,divisi}/*.blade.php`, `klasifikasi-abc/index.blade.php`, `bom/*.blade.php`, `users/*.blade.php`, `roles/*.blade.php`, `components/{permission-matrix,operational-audit-history}.blade.php` | 9 Master Data CRUD modules, 3-step `#00bc7d` Emerald Wizard Stepper di `bom/import`, Users/Roles/RBAC matrix & Audit History. |
| **Tests** | `tests/Feature/{DesignSystemFoundationTest,DesignSystemComponentsTest,ViewRenderingSafetyTest}.php`, `tests/Unit/UiTableHelperTest.php` | TDD contract verification untuk tokens, shell, primitif, teleport, helper, & regresi semua halaman. |

---

### Task 1: Design Token Foundation, Local Inter 300–800, Dual-Theme Bridge & Global SweetAlert2/Dialog Styling

**Files:**
- Modify: `vite.config.js:1-42`
- Modify: `resources/css/app.css:1-318`
- Modify: `resources/js/app.js:1-153`
- Create: `tests/Feature/DesignSystemFoundationTest.php`

**Interfaces:**
- Produces CSS tokens di `resources/css/app.css`: `@custom-variant dark (&:is(.dark *));`, `--bg`, `--bg-2`, `--bg-3`, `--border`, `--border-2`, `--color-bg`, `--color-bg-2`, `--color-bg-3`, `--color-bg-active`, `--color-border-active`, `--color-border-focus`, `--color-text-active`, `--color-text-muted`, `--color-text-placeholder`, `--color-brand-25..950`, `--color-gray-150..850`, `--color-success-50/500/600`, `--color-error-50/500/600`, `--color-warning-50/500/600`, `--color-blue-light-50/500`, `--color-orange-50/500`, `--shadow-theme-xs..xl`, `--shadow-focus-ring`, backward-compat `--color-background..--color-destructive`, `@utility glass`, `@utility no-scrollbar`, `@utility custom-scrollbar`, `.sidebar-active-tab`, SweetAlert2 `.swal2-popup` Dual-Theme rules, dan `html.hs-app-shell` bounded fluid scaling.
- Produces JS helper di `resources/js/app.js`: `window.hsTheme = { current(), isDark(), set(mode), toggle() }` yang sinkron ke `localStorage['hs.theme']`, `document.documentElement.classList`, `document.documentElement.style.colorScheme`, dan meta `theme-color` serta memancarkan `CustomEvent('hs-theme-changed', { detail: { theme } })`.

- [ ] **Step 1: Buat test gagal (`RED`) di `tests/Feature/DesignSystemFoundationTest.php`**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesignSystemFoundationTest extends TestCase
{
    public function test_css_foundation_contains_blueprint_tokens_dark_variant_and_fluid_scaling(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $vite = file_get_contents(base_path('vite.config.js'));
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($css);
        $this->assertIsString($vite);
        $this->assertIsString($js);

        // Font weights 300-800 lokal & bebas Bunny CDN Instrument Sans
        $this->assertStringContainsString("@import '@fontsource/inter/latin-300.css';", $css);
        $this->assertStringContainsString("@import '@fontsource/inter/latin-800.css';", $css);
        $this->assertStringNotContainsString('Instrument Sans', $vite);

        // Tailwind v4 dark variant & token inti Blueprint
        $this->assertStringContainsString('@custom-variant dark (&:is(.dark *));', $css);
        foreach ([
            '--bg: #FFFFFF;',
            '--bg-2: #FBFBFB;',
            '--bg-3: #F5F5F5;',
            '--border: #E8E8E8;',
            '--color-bg: var(--bg);',
            '--color-brand-500: #000000;',
            '--color-gray-150:',
            '--shadow-theme-xs:',
            '@utility glass',
            '@utility custom-scrollbar',
            '.sidebar-active-tab',
            '.swal2-popup',
            'html.hs-app-shell',
            'clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }

        // Theme controller di app.js
        $this->assertStringContainsString('window.hsTheme', $js);
        $this->assertStringContainsString('hs.theme', $js);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi `RED`**

Run: `php artisan test tests/Feature/DesignSystemFoundationTest.php --filter=test_css_foundation_contains_blueprint_tokens_dark_variant_and_fluid_scaling`
Expected: FAIL karena `DesignSystemFoundationTest` menemukan token Blueprint & `window.hsTheme` belum ada dan `Instrument Sans` masih ada di `vite.config.js`.

- [ ] **Step 3: Bersihkan font Bunny CDN di `vite.config.js`**
  - Hapus import `bunny` dan opsi `fonts: [bunny('Instrument Sans', ...)]` dari `vite.config.js` agar font tunggal adalah `@fontsource/inter` dan `@fontsource/jetbrains-mono` lokal. Pertahankan konfigurasi `laravel(...)`, `tailwindcss()`, `server.watch.ignored`, dan `build.rollupOptions.manualChunks`.

- [ ] **Step 4: Perbarui `resources/css/app.css` dengan seluruh token Blueprint Section 4.2 di Spec + DataTables/Dialog/SweetAlert2 Dual-Theme**
  - Salin lengkap blok CSS Section 4.2 dari Spec (`docs/superpowers/specs/2026-11-19-heaven-scent-ui-redesign-design.md`).
  - Tambahkan `@layer base`:
    - `*, ::after, ::before, ::backdrop, ::file-selector-button { border-color: var(--border); }`
    - `button:not(:disabled), [role='button']:not(:disabled) { cursor: pointer; }`
    - Pertahankan `@media screen and (min-width: 64rem) { html.hs-app-shell { font-size: clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem); } }` dan `@media print { html.hs-app-shell { font-size: 100%; } }`
    - `body { background-color: var(--hs-background); color: var(--hs-foreground); font-family: var(--font-body); letter-spacing: -0.01em; }`
  - Tambahkan `@utility glass`, `@utility no-scrollbar`, `@utility custom-scrollbar` dari Blueprint.
  - Tambahkan `.sidebar-active-tab` dari Blueprint Section 5.1 Spec.
  - Tambahkan aturan `.swal2-popup` Dual-Theme (Light `#FFFFFF` border `#E8E8E8` text `#101828`; `.dark .swal2-popup` `#111827` border `#262626` text `#FFFFFF`; tombol confirm `#000000` / `.dark` `#FFFFFF`) dan `dialog:modal` / `dialog::backdrop`.

- [ ] **Step 5: Tambahkan `window.hsTheme` di `resources/js/app.js`**
  - Definisikan `window.hsTheme` sebelum `Alpine.start()` dengan method `current()`, `isDark()`, `set(mode)`, dan `toggle()` yang menyimpan ke `localStorage.setItem('hs.theme', mode)`, memperbarui `document.documentElement.classList.toggle('dark', isDark)`, `document.documentElement.style.colorScheme`, `<meta name="theme-color">`, dan memicu `window.dispatchEvent(new CustomEvent('hs-theme-changed', { detail: { theme: mode } }))`.
  - Pastikan kode existing `window.productSearchSelect` dan `Alpine.start()` tetap utuh.

- [ ] **Step 6: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Feature/DesignSystemFoundationTest.php tests/Feature/ViewRenderingSafetyTest.php --filter="test_css_foundation|test_proportional_fluid_scaling"`
Expected: `npm run build` sukses (termasuk bundel `inter-latin-300`), `test_css_foundation` PASS. (Catat: `test_proportional_fluid_scaling` masih hijau di Task 1 karena `app-layout` belum diubah.)

- [ ] **Step 7: Commit**

```bash
git add vite.config.js resources/css/app.css resources/js/app.js tests/Feature/DesignSystemFoundationTest.php
git commit -m "feat(ui): adopt Heaven Scent Blueprint CSS tokens, Inter 300-800, and Dual-Theme foundation"
```

---

### Task 2: Application Shell Redesign (`AppLayout`, `AppSidebar`, `NavItem`, `NavGroup`, Sticky Glass `AppHeader`, Theme Toggle)

**Files:**
- Modify: `resources/views/components/app-layout.blade.php:1-650`
- Modify: `resources/views/components/nav-item.blade.php:1-45`
- Create: `resources/views/components/nav-group.blade.php`
- Modify: `tests/Feature/DesignSystemFoundationTest.php`
- Modify: `tests/Feature/ViewRenderingSafetyTest.php:483-528` (update kontrak sidebar width `lg:w-[264px]` / `lg:w-[80px]`)

**Interfaces:**
- Consumes: CSS tokens, `.sidebar-active-tab`, `glass`, `custom-scrollbar`, `window.hsTheme` dari Task 1.
- Produces:
  - `<x-nav-item :href="..." :active="..." :badge="..." :isChild="bool">` — `h-[44px]` menu item dengan `.sidebar-active-tab` + bottom glow pill untuk top-level item aktif, serta vertical timeline bar `#627EF2` (`left-[-19px] w-[3px] h-6`) ketika `$isChild = true`.
  - `<x-nav-group title="..." :active="bool" :searchKeywords="'...'">` — collapsible submenu group dengan container `ml-5 border-l-[3px] border-[#E8E8E8] dark:border-border pl-4 py-0.5` yang otomatis expand bila `:active` bernilai `true` atau `menuQuery` non-kosong & cocok dengan child.
  - `app-layout` `<head>` anti-FOUC theme script + `appSidebarState()` (mempertahankan `collapsed`, `mobileOpen`, `menuQuery`, `toggleCollapsed()` + `isDark` reactive state).

- [ ] **Step 1: Perbarui `tests/Feature/ViewRenderingSafetyTest.php:493-498` & tambah test Shell di `tests/Feature/DesignSystemFoundationTest.php`**
  - Di `ViewRenderingSafetyTest.php:496-497`, ubah assertion sidebar width ke kontrak Blueprint baru:
    - `$dashboardRes->assertSee('lg:w-[264px]');`
    - `$dashboardRes->assertDontSee('lg:w-64');`
  - Di `DesignSystemFoundationTest.php`, tambah `test_app_shell_renders_blueprint_sidebar_glass_header_and_theme_toggle`:
    - Login sebagai `manager@heavenscent.id`, `GET /dashboard`:
      - Assert status 200, melihat `hs.theme`, `bg-gray-50 dark:bg-gray-950`, `lg:w-[264px]`, `sidebar-active-tab`, `h-16`, `backdrop-blur-md`, `hsTheme.toggle()`, `#627EF2` (saat route child master data dibuka, e.g. `GET /produk`), serta `Logo HS - black.png`.

- [ ] **Step 2: Jalankan test untuk verifikasi `RED`**

Run: `php artisan test tests/Feature/DesignSystemFoundationTest.php tests/Feature/ViewRenderingSafetyTest.php --filter="test_app_shell_renders|test_proportional_fluid_scaling"`
Expected: FAIL karena shell masih memakai `lg:w-56`, `h-12`, tanpa `.sidebar-active-tab` atau theme toggle.

- [ ] **Step 3: Buat komponen `<x-nav-group>` di `resources/views/components/nav-group.blade.php`**
  - Props: `'title'`, `'active' => false`, `'icon' => null`, `'keywords' => ''`.
  - Alpine `x-data="{ open: @js((bool) $active) }"` + `x-effect="if (menuQuery && menuQuery.trim().length > 0 && @js(strtolower($keywords)).includes(menuQuery.toLowerCase().trim())) { open = true; }"`.
  - Trigger button `h-[44px]` matching Blueprint menu item (`px-3 gap-2.5` expanded, `px-0 justify-center` collapsed; saat `$active` dan collapsed tampilkan `.sidebar-active-tab`).
  - Submenu tree wrapper (`x-show="open && !collapsed" x-collapse/x-transition`): `mt-1 space-y-0.5 ml-5 border-l-[3px] border-[#E8E8E8] dark:border-border pl-4 py-0.5`.

- [ ] **Step 4: Redesign `resources/views/components/nav-item.blade.php`**
  - Props: `'href'`, `'active' => false`, `'badge' => null`, `'badgeVariant' => 'neutral'`, `'isChild' => false`.
  - Ketika `!$isChild`: tinggi `h-[44px] rounded-lg border overflow-hidden`, `$active` memakai class `sidebar-active-tab` + menyisipkan *bottom glow pill* dari Blueprint Section 5.1 Spec; tidak aktif memakai `border-transparent text-text-muted hover:bg-[#FBFBFB] hover:text-black dark:hover:bg-white/[0.02] dark:hover:text-white`.
  - Ketika `$isChild`: tinggi `h-9 rounded-lg relative px-2.5 text-[13px]`, dan bila `$active` tampilkan bar vertikal kiri `<div class="absolute left-[-19px] top-1/2 -translate-y-1/2 w-[3px] h-6 bg-[#627EF2] rounded-full"></div>` serta teks `text-[#111111] dark:text-white font-semibold`.
  - Ikon berukuran `size-[18px] shrink-0`. Pertahankan `data-nav-item`, `data-nav-active`, `hs_sidebar_scroll` onclick, dan klik same-page preventDefault.

- [ ] **Step 5: Redesign `resources/views/components/app-layout.blade.php` (Head + Sidebar + Header + Footer)**
  - `<head>`: Sisipkan anti-FOUC dark theme script (Section 5.1 Spec) di paling atas sebelum `<style>`. Ganti inline `style` background-color hardcoded `!important` menjadi menggunakan `var(--hs-background)` agar mendukung Light & Dark tanpa flash.
  - `<body>`: `min-h-screen font-sans antialiased text-text-base bg-gray-50 dark:bg-gray-950 flex flex-col`.
  - Root layout wrapper: `<div class="min-h-screen bg-gray-50 dark:bg-gray-950 lg:flex w-full min-w-0 overflow-x-hidden">`.
  - Sidebar container: `fixed inset-y-0 left-0 z-[100] transition-all duration-300 ease-out lg:sticky lg:top-0 lg:h-screen lg:z-40` dengan `:class="[mobileOpen ? 'translate-x-0 w-[264px]' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-[80px]' : 'lg:w-[264px]']"`.
  - `<aside>`: `bg-white dark:bg-[#0A0A0A] flex h-full w-full flex-col border-r border-[#E8E8E8] dark:border-border shadow-theme-xs overflow-hidden`.
  - Brand Header: Glossy dark square logo container (`size-9 rounded-[8px]`) memakai `asset('Logo HS - black.png')` dengan `brightness-0 invert` + teks `HEAVEN SCENT` / `Production Lab` + tombol toggle collapsed `size-[40px] rounded-[8px] bg-[#FBFBFB] dark:bg-[#141414] border border-[#E1E1E1] dark:border-border`.
  - Search Bar (`!collapsed`): `h-[44px] px-3 gap-2 bg-[#FBFBFB] dark:bg-[#141414] border border-[#E1E1E1] dark:border-border rounded-lg focus-within:border-border-focus`.
  - Navigasi: Kelompokkan `Beranda`, `Operasional` (`Purchasing`, `Produksi`, `Permintaan Barang`, `Transfer Barang`, `Analisa Stok`), lalu gunakan `<x-nav-group>` untuk `Stok & Mutasi`, `Master Data`, `Laporan`, dan `Sistem`, sehingga menampilkan timeline vertical tree `#627EF2` dengan tetap mempertahankan seluruh `@can` / `@canany` dan `menuQuery` filter.
  - Footer user menu: `min-h-[72px] border-t border-[#E8E8E8] dark:border-border` dengan dukungan `dark:*` pada popover & quick role switch.
  - Sticky `AppHeader`: `sticky top-0 z-[50] w-full h-16 px-4 sm:px-5 lg:px-8 backdrop-blur-md bg-white/80 dark:bg-gray-950/80 border-b border-gray-100 dark:border-gray-800/60 shadow-theme-xs flex items-center justify-between gap-3`. Sertakan tombol menu mobile circular `size-10 rounded-full`, optional back button circular `size-10 rounded-full`, title stack `text-sm md:text-base font-bold text-gray-900 dark:text-white tracking-tight truncate`, `$actions`, serta **Theme Toggle Button** (`@click="hsTheme.toggle(); isDark = hsTheme.isDark()"`).
  - Flash alerts (`success`, `error`, `$errors`) diberi kelas Dual-Theme (`dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-300`, `dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-300`).
  - Footer halaman: `border-t border-[#E8E8E8] dark:border-border bg-white dark:bg-[#0A0A0A]`.

- [ ] **Step 6: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Feature/DesignSystemFoundationTest.php tests/Feature/ViewRenderingSafetyTest.php`
Expected: `npm run build` sukses, `DesignSystemFoundationTest` PASS, seluruh `ViewRenderingSafetyTest` PASS!

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/app-layout.blade.php resources/views/components/nav-item.blade.php resources/views/components/nav-group.blade.php tests/Feature/DesignSystemFoundationTest.php tests/Feature/ViewRenderingSafetyTest.php
git commit -m "feat(ui): redesign AppShell, Sidebar (.sidebar-active-tab + #627EF2 tree), Glass Header, and Dark Mode toggle"
```

---

### Task 3: Core UI Primitives (`Button`, `Card`, `StatBox`, `StatusTabs`, `Badge`, `Alert`, `Callout`, `PageHeader`)

**Files:**
- Modify: `resources/views/components/button.blade.php:1-46`
- Modify: `resources/views/components/card.blade.php:1-55`
- Modify: `resources/views/components/stat-box.blade.php:1-46`
- Create: `resources/views/components/status-tabs.blade.php`
- Modify: `resources/views/components/badge.blade.php:1-52`
- Modify: `resources/views/components/alert.blade.php:1-43`
- Modify: `resources/views/components/callout.blade.php:1-32`
- Modify: `resources/views/components/page-header.blade.php:1-59`
- Create: `tests/Feature/DesignSystemComponentsTest.php`

**Interfaces:**
- Produces `<x-status-tabs :items="[['key' => string, 'label' => string, 'href' => ?string, 'count' => ?int, 'dot' => ?string, 'active' => ?bool]]" :active="string" :alpineModel="?string" :onClick="?string" />`
- Produces `<x-button variant="primary|glossy|secondary|outline|danger|destructive|warning|success|info|ghost|link" size="xs|sm|md|default|lg|icon">`
- Produces `<x-card :title="..." :subtitle="..." :icon="..." :count="null" :noPadding="false" :overflow="null">`
- Produces `<x-stat-box :title="..." :value="..." :subtitle="null" :prefix="null" :trend="null" :trendType="'up'" :icon="null" :iconColor="'indigo'" :badge="null" :href="null" :footerText="null">`
- Produces `<x-badge variant="neutral|primary|info|progress|warning|success|danger|muted|draft|pending|diajukan|in_transit|diproses|received|completed|selesai|cancelled|ditolak" :dot="false" :pill="false" size="default|sm|xs">`

- [ ] **Step 1: Buat test gagal (`RED`) di `tests/Feature/DesignSystemComponentsTest.php`**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DesignSystemComponentsTest extends TestCase
{
    public function test_core_primitives_match_blueprint_markup_and_dark_mode_parity(): void
    {
        $btn = Blade::render('<x-button variant="primary" size="sm">Simpan</x-button>');
        $this->assertStringContainsString('from-[#1e1e1e] to-[#0a0a0a]', $btn);
        $this->assertStringContainsString('rounded-[6px]', $btn);
        $this->assertStringContainsString('active:scale-[0.98]', $btn);

        $tabs = Blade::render('<x-status-tabs :items="[[\'key\' => \'all\', \'label\' => \'Semua\', \'count\' => 12]]" active="all" />');
        $this->assertStringContainsString('bg-[#f3f4f6] dark:bg-gray-900', $tabs);
        $this->assertStringContainsString('bg-white dark:bg-gray-800', $tabs);

        $stat = Blade::render('<x-stat-box title="Total Nilai" value="12.500.000" prefix="Rp" iconColor="emerald" trend="+8.5%"><x-slot:icon><svg></svg></x-slot:icon></x-stat-box>');
        $this->assertStringContainsString('text-[28px]', $stat);
        $this->assertStringContainsString('tracking-[-0.308px]', $stat);
        $this->assertStringContainsString('bg-emerald-50 dark:bg-emerald-500/15', $stat);
        $this->assertStringContainsString('flex-1 min-w-0', $stat);
        $this->assertStringContainsString('break-words', $stat);

        $card = Blade::render('<x-card title="Data PO" :count="5"><x-slot:icon><svg></svg></x-slot:icon>Isi</x-card>');
        $this->assertStringContainsString('rounded-[12px]', $card);
        $this->assertStringContainsString('size-[42px]', $card);
        $this->assertStringContainsString('text-[16px]', $card);

        $pill = Blade::render('<x-badge variant="completed" :pill="true">Selesai</x-badge>');
        $this->assertStringContainsString('rounded-full', $pill);
        $this->assertStringContainsString('dark:bg-emerald-500/10', $pill);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi `RED`**

Run: `php artisan test tests/Feature/DesignSystemComponentsTest.php --filter=test_core_primitives_match_blueprint_markup_and_dark_mode_parity`
Expected: FAIL karena `<x-status-tabs>` belum ada dan `<x-button>`, `<x-stat-box>`, `<x-card>` masih versi lama.

- [ ] **Step 3: Implementasi `button.blade.php`, `status-tabs.blade.php`, `card.blade.php`, `stat-box.blade.php`, `badge.blade.php`, `alert.blade.php`, `callout.blade.php`, `page-header.blade.php`**
  - `button.blade.php`: Terapkan spesifikasi Section 5.2 Spec (termasuk *bottom glow layer* internal pada `primary`/`glossy`, `secondary`/`outline` crisp white, disabled state, semantic soft variants, ikon `size-[14px]`–`size-[16px]`, dan support `$href` vs `<button>`).
  - `status-tabs.blade.php`: Terapkan spesifikasi Section 5.4 Spec. Dukung 3 cara interaksi: link (`$item['href']`), Alpine reactive (`$alpineModel` memakai `:class="..."` tanpa operator `<`/`>`), atau klik JS (`$item['onclick']` / `$onClick`).
  - `card.blade.php`: Terapkan spesifikasi Section 5.3 Spec (`rounded-[12px]`, Zone 1 header `px-5 py-4` dengan optional icon box `size-[42px]` & count pill, footer `dark:bg-gray-900/60`, tetap jaga `flex items-center gap-2.5 min-w-0 flex-1` + `flex-wrap items-center gap-2 shrink-0` agar lulus `ViewRenderingSafetyTest`).
  - `stat-box.blade.php`: Terapkan spesifikasi Section 5.5 Spec (auto-tinting `iconColor` map, `text-[28px] leading-[1.4] font-semibold tracking-[-0.308px] break-words tabular-nums`, optional `prefix`, `trend` badge, dan tetap pertahankan `flex-1 min-w-0` + `break-words` untuk `ViewRenderingSafetyTest`).
  - `badge.blade.php`: Terapkan Dual-Theme `dark:*` untuk seluruh variant `neutral`, `primary`, `info`, `progress`, `warning`, `success`, `danger`, `muted`, PLUS mapping status transaksi (`draft`, `pending`, `diajukan`, `in_transit`, `diproses`, `received`, `completed`, `selesai`, `cancelled`, `ditolak`) serta prop `:pill="true"`.
  - `alert.blade.php` & `callout.blade.php`: Tambahkan pasangan `dark:bg-*-500/10 dark:border-*-500/20 dark:text-*-300` untuk semua tipe (`danger`, `warning`, `success`, `info`, `primary`).
  - `page-header.blade.php`: Terapkan tipografi Header Halaman Blueprint (`text-base sm:text-lg font-bold text-[#101828] dark:text-white tracking-tight`), border `border-[#E8E8E8] dark:border-border`, dan pertahankan `min-w-0 flex-1`, `sm:justify-end gap-x-3 gap-y-2`, serta `data-back-button="true"`.

- [ ] **Step 4: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Feature/DesignSystemComponentsTest.php tests/Feature/ViewRenderingSafetyTest.php`
Expected: Semua test di `DesignSystemComponentsTest` dan `ViewRenderingSafetyTest` PASS!

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/button.blade.php resources/views/components/card.blade.php resources/views/components/stat-box.blade.php resources/views/components/status-tabs.blade.php resources/views/components/badge.blade.php resources/views/components/alert.blade.php resources/views/components/callout.blade.php resources/views/components/page-header.blade.php tests/Feature/DesignSystemComponentsTest.php
git commit -m "feat(ui): redesign core Blade primitives (Button, Card, StatsCard, StatusTabs, Badge, Alert, Callout, PageHeader)"
```

---

### Task 4: Form Architecture (`Modal/AppForm`, `FormSection`, `FormField`, Seamless Inputs, Checkbox, Teleported `ProductSearchSelect`)

**Files:**
- Modify: `resources/views/components/modal.blade.php:1-88`
- Create: `resources/views/components/form-section.blade.php`
- Create: `resources/views/components/form-field.blade.php`
- Modify: `resources/views/components/form-group.blade.php:1-41`
- Modify: `resources/views/components/input.blade.php:1-59`
- Modify: `resources/views/components/select.blade.php:1-38`
- Modify: `resources/views/components/textarea.blade.php:1-31`
- Modify: `resources/views/components/checkbox.blade.php:1-36`
- Modify: `resources/views/components/product-search-select.blade.php:1-166`
- Modify: `resources/js/app.js:7-153`
- Modify: `tests/Feature/DesignSystemComponentsTest.php`

**Interfaces:**
- Produces `<x-form-section :title="string" :cols="1|2|3|4">`
- Produces `<x-form-field :label="string" :name="?string" :required="false" :disabled="false" :align="'center'|'top'" :help="?string" :labelWidth="'150px'">`
- Updates `<x-input>`, `<x-select>`, `<x-textarea>`, `<x-product-search-select>` dengan prop `:variant="'boxed'|'seamless'"` (default `'boxed'` agar form legacy tetap rapi sebelum dimigrasi; `'seamless'` dipakai di dalam `<x-form-field>`).
- Updates `window.productSearchSelect` di `resources/js/app.js` agar mendukung `panelStyle` viewport coordinates (`top`, `left`, `width`, `placement`) saat panel di-teleport ke `<body>`.

- [ ] **Step 1: Tambahkan test gagal (`RED`) di `tests/Feature/DesignSystemComponentsTest.php`**
  - Tambahkan method `test_form_architecture_and_teleported_searchable_select`:
    - Render `<x-modal name="openTest" title="Modal Uji">Konten</x-modal>` → assert `bg-[#F5F5F5] dark:bg-gray-950`, `rounded-[12px]`, `z-[999]`, `custom-scrollbar`.
    - Render `<x-form-section title="Informasi"><x-form-field label="Kode SKU" name="sku" :required="true"><x-input name="sku" variant="seamless" /></x-form-field></x-form-section>` → assert eyebrow `text-[12px] font-medium text-text-muted tracking-[0.12px] uppercase`, kartu putih `rounded-[8px]`, label `sm:w-[150px]`, dan input seamless `bg-transparent border-0 p-0`.
    - Render `<x-product-search-select name="produk_id" variant="seamless" />` → assert `x-teleport="body"`, `z-index: 9999`, `position: fixed`, `dark:bg-gray-900`.

- [ ] **Step 2: Jalankan test untuk verifikasi `RED`**

Run: `php artisan test tests/Feature/DesignSystemComponentsTest.php --filter=test_form_architecture_and_teleported_searchable_select`
Expected: FAIL karena `form-section`, `form-field`, dan `x-teleport="body"` belum ada.

- [ ] **Step 3: Implementasi `modal.blade.php`, `form-section.blade.php`, `form-field.blade.php`, `form-group.blade.php`, `input.blade.php`, `select.blade.php`, `textarea.blade.php`, `checkbox.blade.php`**
  - `modal.blade.php`: Terapkan `AppForm` shell Section 5.6 Spec (`z-[999] bg-black/50 backdrop-blur-xs`, `rounded-[12px]`, white header/footer `dark:bg-gray-900 border-border`, body `bg-[#F5F5F5] dark:bg-gray-950 p-3 sm:p-4 md:p-5 custom-scrollbar`). Catatan: tetap hormati `$noPadding` bila pemanggil sudah membungkus `<form>` sendiri agar layout modal Analisa tidak berganda padding.
  - `form-section.blade.php`: Eyebrow header + responsive grid `1|2|3|4` kolom (`gap-[10px]`).
  - `form-field.blade.php`: Inline white card `rounded-[8px]`, error message di bawah kartu (`@error($name)`), support `$align === 'top'` untuk `<textarea>`, dan `$disabled` state (`opacity-60 bg-gray-50 dark:bg-gray-800/40 cursor-not-allowed`).
  - `input.blade.php`, `select.blade.php`, `textarea.blade.php`: Tambah prop `'variant' => 'boxed'` (`boxed` = bordered `bg-white dark:bg-gray-900 border-[#E8E8E8] dark:border-border focus:border-[#111111] dark:focus:border-white`; `seamless` = `w-full bg-transparent border-0 p-0 shadow-none outline-none text-[14px] font-medium text-text-active placeholder:text-text-placeholder focus:ring-0`).
  - `checkbox.blade.php` & `form-group.blade.php`: Sesuaikan warna ke monochrome `#000000` / `dark:white` dan border `#E8E8E8` / `dark:#262626`.

- [ ] **Step 4: Upgrade `window.productSearchSelect` (`resources/js/app.js`) dan `product-search-select.blade.php`**
  - Di `resources/js/app.js`: tambah state `panelStyle: ''`, `updatePosition()` (hitung `trigger.getBoundingClientRect()`, pilih `bottom` atau flip ke atas jika ruang bawah `< 280px` dan atas lebih lega, set `position: fixed; left: ...px; top: ...px; width: ...px; z-index: 9999;`), bind listener `scroll` (capture `true`) dan `resize` saat `open === true`, serta debounce pencarian `300ms`.
  - Di `product-search-select.blade.php`:
    - Tambah prop `'variant' => 'boxed'` (`boxed` atau `seamless`).
    - Tambah `x-ref="trigger"` pada trigger box dan bungkus dropdown panel dalam `<template x-teleport="body">` dengan `:style="panelStyle"` dan click-outside check (`@click.outside` tidak menutup saat klik di dalam panel yang diteleport — tangani via `if (open && !$el.contains($event.target) && !($refs.panel && $refs.panel.contains($event.target))) open = false` pada `@click.window`).
    - Gunakan warna Blueprint (`bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700`, highlight opsi `bg-brand-50 dark:bg-brand-500/10`, active `font-bold text-[#111111] dark:text-white`).

- [ ] **Step 5: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Feature/DesignSystemComponentsTest.php tests/Feature/ViewRenderingSafetyTest.php`
Expected: Semua test di `DesignSystemComponentsTest` & `ViewRenderingSafetyTest` PASS!

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/modal.blade.php resources/views/components/form-section.blade.php resources/views/components/form-field.blade.php resources/views/components/form-group.blade.php resources/views/components/input.blade.php resources/views/components/select.blade.php resources/views/components/textarea.blade.php resources/views/components/checkbox.blade.php resources/views/components/product-search-select.blade.php resources/js/app.js tests/Feature/DesignSystemComponentsTest.php
git commit -m "feat(ui): implement AppForm modal, FormSection, FormField, seamless inputs, and teleported SearchableSelect"
```

---

### Task 5: 4-Zone Enterprise DataTable CSS & Centralized `App\Support\UiTableHelper` for All 17 Controllers

**Files:**
- Create: `app/Support/UiTableHelper.php`
- Modify: `resources/css/app.css` (bagian `.dataTables_wrapper` & `.table-wrapper`)
- Modify: `resources/views/components/app-layout.blade.php` (bagian `$.fn.dataTable.defaults` & `language`)
- Modify (17 Controllers):
  - `app/Http/Controllers/ProdukController.php`
  - `app/Http/Controllers/KategoriController.php`
  - `app/Http/Controllers/VarianController.php`
  - `app/Http/Controllers/GudangController.php`
  - `app/Http/Controllers/SupplierController.php`
  - `app/Http/Controllers/UomController.php`
  - `app/Http/Controllers/DivisiController.php`
  - `app/Http/Controllers/KlasifikasiAbcController.php`
  - `app/Http/Controllers/BomController.php`
  - `app/Http/Controllers/UserController.php`
  - `app/Http/Controllers/RoleController.php`
  - `app/Http/Controllers/BatchController.php`
  - `app/Http/Controllers/PurchasingController.php`
  - `app/Http/Controllers/RequestController.php`
  - `app/Http/Controllers/TransferController.php`
  - `app/Http/Controllers/StokController.php`
  - `app/Http/Controllers/OperationalAuditController.php`
- Create: `tests/Unit/UiTableHelperTest.php`

**Interfaces:**
- Produces `App\Support\UiTableHelper`:
  - `public static function actionLink(string $href, string $label, string $icon = 'eye', string $tone = 'default'): string`
  - `public static function actionButton(string $onclick, string $label, string $icon = 'trash', string $tone = 'danger', array $attrs = []): string`
  - `public static function actionGroup(array $buttonsHtml, string $align = 'center'): string`
  - `public static function statusPill(string $label, string $tone = 'draft'): string`

- [ ] **Step 1: Buat unit test gagal (`RED`) di `tests/Unit/UiTableHelperTest.php`**

```php
<?php

namespace Tests\Unit;

use App\Support\UiTableHelper;
use PHPUnit\Framework\TestCase;

class UiTableHelperTest extends TestCase
{
    public function test_action_buttons_render_blueprint_32px_squares_with_sr_only_and_dark_mode(): void
    {
        $edit = UiTableHelper::actionLink('/produk/1/edit', 'Edit', 'pencil', 'default');
        $del = UiTableHelper::actionButton("hapus('/produk/1')", 'Hapus', 'trash', 'danger');
        $ok = UiTableHelper::actionButton("approve(1)", 'Setujui', 'check', 'success');

        $this->assertStringContainsString('size-[32px]', $edit);
        $this->assertStringContainsString('rounded-[6px]', $edit);
        $this->assertStringContainsString('border-[#e8e8e8] dark:border-border', $edit);
        $this->assertStringContainsString('<span class="sr-only">Edit</span>', $edit);
        $this->assertStringContainsString('title="Edit"', $edit);

        $this->assertStringContainsString('border-[#ffc9c9] dark:border-red-500/30', $del);
        $this->assertStringContainsString('<span class="sr-only">Hapus</span>', $del);

        $this->assertStringContainsString('border-[#a4f4cf] dark:border-emerald-500/30', $ok);
        $this->assertStringContainsString('<span class="sr-only">Setujui</span>', $ok);

        $pill = UiTableHelper::statusPill('Selesai', 'completed');
        $this->assertStringContainsString('rounded-full', $pill);
        $this->assertStringContainsString('dark:bg-emerald-500/10', $pill);
    }
}
```

- [ ] **Step 2: Jalankan test untuk verifikasi `RED`**

Run: `php artisan test tests/Unit/UiTableHelperTest.php`
Expected: FAIL (`Class "App\Support\UiTableHelper" not found`).

- [ ] **Step 3: Buat `app/Support/UiTableHelper.php`**
  - Implementasikan `actionLink`, `actionButton`, `actionGroup`, `statusPill`, serta ikon SVG crisp stroke (`eye`, `pencil`, `trash`, `check`, `calendar`, `book`, `sliders`, `layers`, `printer`, `send`, `x`) berukuran `size-[14px]`.
  - Seluruh karakter output di-escape memakai `e(...)` (untuk href/label/title) dan atribut onclick aman seperti pola pemanggilnya.
  - Tone `statusPill`: `draft`, `pending`, `in_transit`, `received`, `completed`, `cancelled`, `neutral` sesuai Section 5.7 Spec (semuanya menyertakan pasangan `dark:*`).

- [ ] **Step 4: Perbarui DataTables CSS (`resources/css/app.css`) & defaults (`app-layout.blade.php`)**
  - Di `resources/css/app.css`: ubah `.dt-layout-header`, `.dt-layout-footer`, `table.dataTable thead th` (`sticky top-0 z-30 bg-[#f8fafb] dark:bg-gray-800/95 backdrop-blur-md border-y border-border font-normal text-text-muted text-xs sm:text-[13px]`), `table.dataTable tbody td` (`px-3 py-3 text-[14px] leading-[20px] font-normal tracking-[-0.154px] text-[#101828] dark:text-white border-b border-border dark:border-gray-800/50`), `.paginate_button.current` (`bg-black dark:bg-white text-white dark:text-gray-900`), serta `.dataTables_processing` Dual-Theme (`dark:bg-gray-900/95`). Pertahankan tidak adanya `sm:min-w-[240px]` agar lolos `ViewRenderingSafetyTest`.
  - Di `app-layout.blade.php`: rapikan `$.fn.dataTable.defaults.language.paginate` (`previous: 'Back'`, `next: 'Next'` atau ikon sesuai Blueprint Section 5.3).

- [ ] **Step 5: Refactor HTML kolom `action` & `status` pada 17 DataTables Controllers memakai `UiTableHelper`**
  - Baca setiap controller sebelum mengedit untuk mempertahankan persis hak akses (`$canEdit`, `$canDelete`), URL/onclick handler, dan kolom lain yang sudah ada.
  - Pastikan `UserController::data()` **TETAP** menjaga HTML exact `roles_label` (`<div class="flex flex-wrap items-center gap-1">` dan `inline-flex items-center whitespace-nowrap` — diuji oleh `ViewRenderingSafetyTest:666-667`), dan gunakan `dark:*` pada semua badge divisi/gudang/status di seluruh 17 controller.

- [ ] **Step 6: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Unit/UiTableHelperTest.php tests/Feature/ViewRenderingSafetyTest.php tests/Feature/ProdukMultiSupplierTest.php tests/Feature/PurchasingFlowTest.php`
Expected: Semua test PASS!

- [ ] **Step 7: Commit**

```bash
git add app/Support/UiTableHelper.php resources/css/app.css resources/views/components/app-layout.blade.php app/Http/Controllers/ tests/Unit/UiTableHelperTest.php
git commit -m "feat(ui): standardize 4-zone DataTable styling and UiTableHelper across all 17 controllers"
```

---

### Task 6: Guest Auth Login Page (Glossy Brand Badge) & Role-Adaptive Dashboard + Defect Widget

**Files:**
- Modify: `resources/views/auth/login.blade.php:1-196`
- Modify: `resources/views/dashboard.blade.php:1-243`
- Modify: `resources/views/partials/defect-dashboard.blade.php:1-328`

**Interfaces:**
- Consumes: CSS tokens, `window.hsTheme`, `Button`, `Card`, `StatBox` (`iconColor`), `StatusTabs`, `Badge` dari Task 1–3.
- Preserves: `login.blade.php` bebas dari `hs-app-shell`, `dashboard.blade.php` bebas dari `max-w-7xl`, `👋`, `bg-gradient-to-r`, mempertahankan `lg:grid-cols-5` dan semua teks judul per role (`DashboardTest`, `ViewRenderingSafetyTest`), serta `defect-dashboard.blade.php` mempertahankan `flex items-center gap-3 min-w-0 flex-1` dan `flex flex-wrap items-center lg:justify-end gap-2 shrink-0 max-w-full`.

- [ ] **Step 1: Tambahkan test gagal (`RED`) di `tests/Feature/DesignSystemFoundationTest.php`**
  - Tambah `test_login_and_dashboard_match_blueprint_visual_contracts`:
    - `GET /login`: assert ok, assertDontSee `hs-app-shell`, assertSee `from-[#292929]`, `to-[#0a0a0a]`, `border-[#212121]`, `Logo HS - black.png`, `dark:bg-gray-950`.
    - `GET /dashboard` (sebagai `manager@heavenscent.id`): assert ok, assertSee `text-[28px]`, `rounded-[12px]`, `lg:grid-cols-5`, `hs-defect-dashboard` / `defect-dashboard`.

- [ ] **Step 2: Jalankan test untuk verifikasi `RED`**

Run: `php artisan test tests/Feature/DesignSystemFoundationTest.php --filter=test_login_and_dashboard_match_blueprint_visual_contracts`
Expected: FAIL karena `login.blade.php` dan `dashboard.blade.php` masih layout lama.

- [ ] **Step 3: Redesign `resources/views/auth/login.blade.php`**
  - Anti-FOUC theme script di `<head>` (tanpa `hs-app-shell`!).
  - Canvas `bg-gray-50 dark:bg-gray-950` + tombol toggle tema kecil di pojok kanan atas.
  - Card container `rounded-[12px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border shadow-theme-md overflow-hidden`:
    - Header: Glossy Dark Square Brand Logo Badge persis seperti Section 5.7 Spec (`size-[56px] rounded-[12px] bg-gradient-to-b from-[#292929] from-[17%] to-[#0a0a0a] border-[#212121]` — tanpa ring putih tembus) + judul & subjudul.
    - Body `#F5F5F5 dark:bg-gray-950`: kartu putih horizontal/stack untuk field Email & Password + checkbox Remember Me + tombol submit `primary` glossy (`from-[#1e1e1e] to-[#0a0a0a]`) + blok dev quick-login (`app()->environment('local', 'testing')`) yang tetap bekerja identik.

- [ ] **Step 4: Redesign `resources/views/dashboard.blade.php` & `resources/views/partials/defect-dashboard.blade.php`**
  - `dashboard.blade.php`:
    - Pertahankan `<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 mb-4">` dan render setiap KPI melalui `<x-stat-box>` dengan mapping `iconColor` kontekstual (`indigo`, `amber`, `emerald`, `blue`, `rose`).
    - Urgent Action Center & Operational Activity Streams memakai `<x-card>` `rounded-[12px]` dengan Zone 1 icon box, `<x-badge :pill="true">`, dan `<x-button size="xs" variant="secondary">`.
  - `partials/defect-dashboard.blade.php`:
    - Ganti switcher `matrix` vs `detail` menjadi style `<x-status-tabs>` atau pill track `#f3f4f6 dark:bg-gray-900`, perbaiki kontras Dual-Theme semua sel/chart/filter, serta pertahankan exact class `flex items-center gap-3 min-w-0 flex-1` dan `flex flex-wrap items-center lg:justify-end gap-2 shrink-0 max-w-full`.

- [ ] **Step 5: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Feature/DesignSystemFoundationTest.php tests/Feature/ViewRenderingSafetyTest.php tests/Feature/DashboardTest.php tests/Feature/TransferMonthlyWarehouseDefectDashboardTest.php`
Expected: Semua test PASS!

- [ ] **Step 6: Commit**

```bash
git add resources/views/auth/login.blade.php resources/views/dashboard.blade.php resources/views/partials/defect-dashboard.blade.php tests/Feature/DesignSystemFoundationTest.php
git commit -m "feat(ui): redesign Login (Glossy Logo Badge), Dashboard KPIs, and Defect Analytics widget"
```

---

### Task 7: Workflow Stepper & Core Operational Modules — Purchasing (4 Views) & Produksi/Batches (3 Views)

**Files:**
- Modify: `resources/views/components/purchasing-progress-stepper.blade.php:1-378`
- Modify: `resources/views/purchasing/{index,create,edit,show}.blade.php`
- Modify: `resources/views/batches/{index,create,show}.blade.php`

**Interfaces:**
- Consumes: `Button`, `Card`, `StatusTabs`, `StatBox`, `Badge`, `Modal/AppForm`, `FormSection`, `FormField`, `ProductSearchSelect`, `UiTableHelper`.
- Preserves: Seluruh data-* & aria-* stepper (`ViewRenderingSafetyTest:563-569`), privacy masking gudang (`Rp ••••••` di `BladeUiPrd24AlignmentTest`), 8-Stage PoStage transitions & modals/dialogs, `batches/create` bebas `max-w-4xl`, `purchasing/create` bebas `max-w-6xl` & `min-w-[320px]`.

- [ ] **Step 1: Cek ulang baris-baris kritis di `purchasing/*` & `batches/*` (baca file sebelum edit)**
  - Baca penuh `purchasing/{index,create,edit,show}.blade.php` dan `batches/{index,create,show}.blade.php` agar tidak ada form field name, Alpine function (`openQuickDateModal`, `closeStageBreakdownModal`, dll.), atau `<dialog>` ID (`reject-revisi-dialog-*`) yang hilang.

- [ ] **Step 2: Upgrade `resources/views/components/purchasing-progress-stepper.blade.php` ke Blueprint `WorkflowStepper`**
  - Terapkan card `rounded-[12px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border p-4 sm:p-5`, garis track `h-0.5 bg-gray-100 dark:bg-gray-800`, node selesai `#00bc7d` dengan emerald glow, node berjalan `bg-brand-500 dark:bg-white` ring-4, node terlambat `#F04438`, dan Dual-Theme phase group headers + footer summary strip.
  - Hilangkan `bg-slate-900` / `text-slate-900` yang tersisa tanpa token Blueprint.
  - Pertahankan persis `tabindex="0" role="region" aria-label="Scroll Tahapan Purchase Order"`, `min-w-[44rem] lg:min-w-0`, `flex-1 min-w-0 px-1 relative z-10`, dan `aria-label="Tahapan Purchase Order"`.

- [ ] **Step 3: Redesign `resources/views/purchasing/{index,create,edit,show}.blade.php`**
  - `purchasing/index.blade.php`: ganti tab navigasi (`PO`, `Approval`, `AP`, `Lead Time`) ke `<x-status-tabs>`, filter bar ke input/select Blueprint Dual-Theme (bebas rigid `min-w-[...px]`, gunakan `min-w-40`), 4-zone `<x-card>` tables, modal `modalStageBreakdown` & quick-date modal ke `<x-modal>` (`#F5F5F5 dark:bg-gray-950` + white cards).
  - `purchasing/create.blade.php` & `edit.blade.php`: konversi form ke layout `#F5F5F5 dark:bg-gray-950` + `<x-form-section>` + `<x-form-field>` dengan `<x-product-search-select variant="seamless" />` pada baris item. Pertahankan `min-w-64 sm:min-w-80` dan bebas `max-w-6xl`.
  - `purchasing/show.blade.php`: upgrade seluruh kartu ringkasan, panel eksekusi 8-stage, tabel item/barang datang/pembayaran, modal & native `<dialog id="reject-revisi-dialog-...">` ke permukaan `#FFFFFF`/`dark:bg-gray-900` & `#F5F5F5`/`dark:bg-gray-950` dengan `<x-button>` dan `<x-badge :pill="true">`.

- [ ] **Step 4: Redesign `resources/views/batches/{index,create,show}.blade.php`**
  - `batches/index.blade.php`: `<x-status-tabs>` + 4-zone DataTable card.
  - `batches/create.blade.php`: `#F5F5F5 dark:bg-gray-950` + `<x-form-section>` + `<x-form-field>` (bebas `max-w-4xl`).
  - `batches/show.blade.php`: hilangkan seluruh 6 `bg-gray-50` & 3 `bg-gray-100` light-only; ubah ke `bg-bg-2 dark:bg-gray-800/50` / `bg-muted dark:bg-gray-800`; pertahankan exact layout `lg:grid-cols-3 gap-4` dan `lg:col-span-2 space-y-5 min-w-0`.

- [ ] **Step 5: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Feature/ViewRenderingSafetyTest.php tests/Feature/BladeUiPrd24AlignmentTest.php tests/Feature/PurchasingNineStageAndLeadTimeTest.php tests/Feature/PurchasingFlowTest.php tests/Feature/PurchasingPrivacyGudangTest.php`
Expected: Semua test PASS!

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/purchasing-progress-stepper.blade.php resources/views/purchasing/ resources/views/batches/
git commit -m "feat(ui): redesign WorkflowStepper, Purchasing (4 views), and Batches (3 views) to Blueprint Dual-Theme"
```

---

### Task 8: Operational Modules — Requests (4 Views), Transfers (4 Views incl. Print-Safe Surat Jalan), & Stok (4 Views)

**Files:**
- Modify: `resources/views/requests/{index,create,edit,show}.blade.php`
- Modify: `resources/views/transfers/{index,create,show,surat-jalan}.blade.php`
- Modify: `resources/views/stok/{index,ledger,opname,pergerakan-log}.blade.php`

**Interfaces:**
- Consumes: `Button`, `Card`, `StatusTabs`, `StatBox`, `Badge`, `Modal/AppForm`, `FormSection`, `FormField`, `ProductSearchSelect`, `UiTableHelper`.
- Preserves:
  - `stok/opname.blade.php`: bebas `max-w-4xl`, tetap menampilkan `Monitoring Saldo Sistem`, `Stok di Sistem`, `Estimasi Deviasi` (`StokEnhancementTest`).
  - `stok/index.blade.php` & `stok/ledger.blade.php`: tetap menampilkan `Lokasi Gudang`, `Kategori Barang`, `Lokasi Terpilih`, serta label akses peran (`All Gudang ...`).
  - `transfers/surat-jalan.blade.php`: **TIDAK** memakai `hs-app-shell`, tombol toolbar `.no-print` memakai gaya Blueprint (`rounded-[6px]`, primary glossy untuk Cetak), sementara lembar cetak `.print-page` tetap kertas putih bersih di `@media print`.

- [ ] **Step 1: Baca seluruh file target di `requests/`, `transfers/`, `stok/` untuk memverifikasi elemen interaktif & ID**
  - Catat seluruh ID tabel, filter select, Alpine modal state (`requests/index`, `requests/show`, `transfers/show`, `stok/index`), serta script opname live delta.

- [ ] **Step 2: Redesign `resources/views/requests/{index,create,edit,show}.blade.php`**
  - Migrasi tab status ke `<x-status-tabs>`, DataTable ke 4-zone `<x-card>`, form `create`/`edit` ke `<x-form-section>` + `<x-form-field>` + `<x-product-search-select variant="seamless" />`, modal approval/reject ke `<x-modal>` `#F5F5F5 dark:bg-gray-950`, dan hilangkan `bg-gray-100` light-only di `requests/show`.

- [ ] **Step 3: Redesign `resources/views/transfers/{index,create,show,surat-jalan}.blade.php`**
  - Migrasi `index`, `create`, `show` ke Blueprint 4-zone cards, `<x-status-tabs>`, `<x-form-section>`/`<x-form-field>`, hilangkan `bg-white` di `transfers/create` dan `bg-gray-100` di `transfers/show` untuk Dual-Theme parity.
  - Pada `transfers/surat-jalan.blade.php`: rapikan toolbar `.no-print` dengan tombol Blueprint monochrome (`rounded-[6px]`), ubah font `@fontsource/inter` & `JetBrains Mono`, dan isolasi penuh lembar `.print-page` putih A4 pada `@media print` (tetap tanpa `hs-app-shell` dan tanpa `.dark`).

- [ ] **Step 4: Redesign `resources/views/stok/{index,ledger,opname,pergerakan-log}.blade.php`**
  - `stok/index.blade.php`: `<x-status-tabs>` filter kategori/gudang, 4-zone DataTable, modal penyesuaian/transfer ke Blueprint `AppForm` Dual-Theme, tanpa rigid `min-w-[...px]`.
  - `stok/ledger.blade.php` & `stok/pergerakan-log.blade.php`: 4-zone `<x-card>`, Dual-Theme badges `MASUK`/`KELUAR`/`PENYESUAIAN`, sticky `thead`, tanpa rigid `min-w-[...px]`.
  - `stok/opname.blade.php`: `#F5F5F5 dark:bg-gray-950` + `<x-form-section>` + `<x-form-field>` + kartu live comparison `Monitoring Saldo Sistem` / `Stok di Sistem` / `Estimasi Deviasi` (bebas `max-w-4xl` dan bebas `bg-gray-50` tanpa `dark:*`).

- [ ] **Step 5: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Feature/ViewRenderingSafetyTest.php tests/Feature/StokEnhancementTest.php tests/Feature/StokRencanaAndFormattingTest.php`
Expected: Semua test PASS!

- [ ] **Step 6: Commit**

```bash
git add resources/views/requests/ resources/views/transfers/ resources/views/stok/
git commit -m "feat(ui): redesign Requests, Transfers, Print-Safe Surat Jalan, and Stok/Opname views to Blueprint"
```

---

### Task 9: Analisa Stok Module (13 Files) & All Reports (8 Files)

**Files:**
- Modify: `resources/views/analisa/{index,create-po}.blade.php`
- Modify: `resources/views/analisa/partials/{table-lokal,table-impor,table-fulfillment,table-riwayat}.blade.php`
- Modify: `resources/views/analisa/partials/{modal-add-sku,modal-compare-supplier,modal-manual-impor,modal-manual-lokal,modal-snapshot,modal-stage-lead-time,modal-varian}.blade.php`
- Modify: `resources/views/reports/{index,defect,fulfillment,lead-time,low-stock,material,production,purchasing}.blade.php`

**Interfaces:**
- Consumes: `Button`, `Card`, `StatusTabs`, `StatBox`, `Badge`, `Modal/AppForm`, `FormSection`, `FormField`.
- Preserves (KRITIS):
  - `analisa/index.blade.php` & partials: seluruh variabel/method Alpine (`manualLokalForm.*`, `manualImporForm.*`, `addSkuForm.*`, `stageForm.*`, `copyAutoStages()`, `openStageModal`, `openManualLokalModal`, `openManualImporModal`, `supplierFilter`, `lokalSubTab`, `imporSubTab`), grid `sm:grid-cols-4`, serta seluruh label persis yang di-assert oleh `AnalisaModalCurrentReferenceTest`, `AnalisaSupplierRelationTest`, dan `AnalisaMultiSupplierLeadTimeTest`.
  - `reports/defect.blade.php`: pertahankan `lg:grid-cols-6` dan semua teks rekapitulasi yang dicek `BladeUiPrd24AlignmentTest`; semua `reports/*` bebas dari rigid `min-w-[120px..280px]`.

- [ ] **Step 1: Redesign `resources/views/analisa/index.blade.php`, `create-po.blade.php`, dan 4 Table Partials**
  - Main tabs (`lokal`, `impor`, `fulfillment`, `riwayat`) dan sub-tabs (`lokalSubTab`, `imporSubTab`) memakai gaya Blueprint `StatusTabs` (`bg-[#f3f4f6] dark:bg-gray-900` track + white active pill `dark:bg-gray-800`).
  - KPI strip `sm:grid-cols-4` memakai kartu metrik bergaya `StatsCard` (`rounded-[12px]`, `text-[28px]` semibold, `iconColor` box `size-[32px]`).
  - Seluruh tabel di `table-lokal`, `table-impor`, `table-fulfillment`, `table-riwayat`, dan `renderTable()` JS memakai sticky `thead` `#f8fafb dark:bg-gray-800/95`, cell `14px/20px` `text-[#101828] dark:text-white`, badge `ORDER`/`AMAN` Dual-Theme, dan dark/white active pagination buttons. Hapus sisa `bg-zinc-100`/`bg-gray-100` tanpa `dark:*`.
  - `create-po.blade.php`: konversi ke `#F5F5F5 dark:bg-gray-950` + `<x-form-section>` + `<x-form-field>` tanpa mengubah nama field POST (`create-po.store`).

- [ ] **Step 2: Redesign 7 Analisa Modals (`modal-*.blade.php`) ke Blueprint `AppForm` Dual-Theme**
  - Terapkan shell modal putih ber-border `rounded-[12px]` + body `#F5F5F5 dark:bg-gray-950` + field kartu putih `rounded-[8px]` + simulasi preview card ber-border tipis Dual-Theme + tombol `<x-button variant="primary" />` & `<x-button variant="secondary" />`.
  - **JANGAN** hapus atau ubah sedikit pun ekspresi Alpine `manualLokalForm.current_*`, `manualImporForm.current_*`, `manualImporForm.empirical_*`, `addSkuForm.current_*`, `stageForm.auto_*`, `copyAutoStages()`, tombol `"Gunakan Nilai Sistem"`, dan tombol `"Gunakan Nilai Sistem (PO Otomatis)"`.

- [ ] **Step 3: Redesign Seluruh 8 File `resources/views/reports/*.blade.php`**
  - `reports/index.blade.php`: kartu menu laporan `rounded-[12px]` dengan icon box `size-[42px]` Dual-Theme.
  - `reports/{defect,fulfillment,lead-time,low-stock,material,production,purchasing}.blade.php`: upgrade KPI cards ke `<x-stat-box>` (pertahankan `lg:grid-cols-6` di `defect.blade.php`), filter bar Dual-Theme (bebas rigid `min-w-[...px]`), dan tabel laporan ke 4-zone `<x-card>` dengan sticky `thead`, `role="region"` pada wrapper scroll tabel, dan Dual-Theme badge/status pills.

- [ ] **Step 4: Build Vite & jalankan test verifikasi (`GREEN`)**

Run: `npm run build && php artisan test tests/Feature/ViewRenderingSafetyTest.php tests/Feature/AnalisaModalCurrentReferenceTest.php tests/Feature/AnalisaSupplierRelationTest.php tests/Feature/AnalisaMultiSupplierLeadTimeTest.php tests/Feature/AnalisaImporEngineTest.php tests/Feature/AnalisaLokalEngineTest.php tests/Feature/AnalisaStokTest.php tests/Feature/BladeUiPrd24AlignmentTest.php`
Expected: Semua test Analisa, Reports, dan ViewRenderingSafetyTest PASS!

- [ ] **Step 5: Commit**

```bash
git add resources/views/analisa/ resources/views/reports/
git commit -m "feat(ui): redesign Analisa Stok (13 files) and all Reports (8 views) to Blueprint Dual-Theme"
```

---

### Task 10: Master Data CRUD (9 Modules / 30 Views), BOM Wizard Stepper, Sistem (Users/Roles/RBAC/Audit), SweetAlert Standardization, & Final QA Verification

**Files:**
- Modify: `resources/views/{produk,kategori,varian,gudang,supplier,uom,divisi}/{index,create,edit,_form}.blade.php` (28 files)
- Modify: `resources/views/klasifikasi-abc/index.blade.php`
- Modify: `resources/views/bom/{index,edit,import}.blade.php`
- Modify: `resources/views/users/{index,create,edit}.blade.php`
- Modify: `resources/views/roles/{index,create,edit}.blade.php`
- Modify: `resources/views/components/{permission-matrix,operational-audit-history}.blade.php`
- Modify (jika perlu penyeragaman `Swal.fire` theme): helper `window.hsSwal` di `resources/js/app.js` + pemanggil `Swal.fire` yang masih memakai `#0284c7` / biru.

**Interfaces:**
- Consumes: Semua primitif Blueprint dari Task 1–5 (`Button`, `Card`, `StatusTabs`, `Badge`, `AppForm`, `FormSection`, `FormField`, `ProductSearchSelect`, Wizard Stepper `#00bc7d`).
- Preserves:
  - Tidak ada `max-w-3xl`, `max-w-4xl`, `max-w-5xl` pada form create/edit (`ViewRenderingSafetyTest:358,363,368,374,383`).
  - `users/index.blade.php` tetap memakai `usersTable" class="w-full text-xs [&_thead_th]:!px-2.5 [&_tbody_td]:!px-2.5"`.
  - `bom/import.blade.php` tetap mempertahankan `<div class="min-w-0 flex-1">`, `flex flex-wrap items-center lg:justify-end gap-2 shrink-0 max-w-full`, `inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap`, dan grid status `sm:grid-cols-5`.
  - `produk/_form.blade.php` tetap menjaga `produkFormState(...)` dan `supplierProdukChecklist` contract (`ProdukMultiSupplierTest`).

- [ ] **Step 1: Redesign seluruh Master Data Index & Form (`produk`, `kategori`, `varian`, `gudang`, `supplier`, `uom`, `divisi`, `klasifikasi-abc`)**
  - Semua `index.blade.php`: `<x-page-header>` + `<x-status-tabs>` (jika ada tab seperti `produk/index`) + 4-zone `<x-card>` DataTable.
  - Semua `_form.blade.php` + `create.blade.php` + `edit.blade.php`: bungkus body form dalam container `bg-[#F5F5F5] dark:bg-gray-950 border border-[#E8E8E8] dark:border-border rounded-[12px] p-4 sm:p-5 space-y-4`, kelompokkan field dengan `<x-form-section>` + `<x-form-field>` + seamless inputs, dan akhiri footer putih `bg-white dark:bg-gray-900 border-t` dengan tombol `<x-button variant="secondary">` & `<x-button variant="primary">`. Pastikan bersih dari `bg-zinc-*` / `bg-slate-*` tanpa `dark:*` dan tanpa `max-w-3xl..5xl`.
  - `klasifikasi-abc/index.blade.php`: 4-zone `<x-card>` + modal `<x-modal>` `#F5F5F5 dark:bg-gray-950`.

- [ ] **Step 2: Redesign `bom/{index,edit,import}.blade.php` + Tambah Blueprint Wizard Stepper `#00bc7d`**
  - `bom/index.blade.php` & `bom/edit.blade.php`: 4-zone DataTable card + `#F5F5F5` form cards + `<x-product-search-select variant="seamless" />`.
  - `bom/import.blade.php`: Tambahkan **Wizard Stepper 3 Langkah** sesuai Section 5.7 Spec (`1. Unggah / Isi Grid` → `2. Validasi Formula` → `3. Simpan Resep BOM`) dengan lingkaran `28×28px` `#00bc7d` glowing (`shadow-[0px_0px_0px_4px_rgba(0,188,125,0.18)]`) yang state aktif/selesainya reaktif terhadap data Alpine `bomDualGridImporter()` (`rows.length > 0`, `validRowsCount > 0 && invalidRowsCount === 0`, dan status submit), sementara header toolbar tetap mempertahankan class yang dicek `ViewRenderingSafetyTest:654-658`.

- [ ] **Step 3: Redesign Sistem (`users/{index,create,edit}`, `roles/{index,create,edit}`, `permission-matrix`, `operational-audit-history`) & Penyeragaman SweetAlert2**
  - `users/*` & `roles/*`: 4-zone `<x-card>` + form `<x-form-section>`/`<x-form-field>`, jaga exact class `usersTable` di `users/index.blade.php` dan zero `max-w-5xl` di `users/create.blade.php`.
  - `components/permission-matrix.blade.php`: domain filter pills memakai style `StatusTabs`, module cards memakai style Blueprint `rounded-[12px]` Dual-Theme.
  - `components/operational-audit-history.blade.php`: tabel & diff badge Dual-Theme.
  - Ganti seluruh `confirmButtonColor: '#0284c7'` (biru non-monokrom) di `Swal.fire` views menjadi monochrome `#111111` (atau `window.hsTheme.isDark() ? '#ffffff' : '#111111'`) sehingga SweetAlert2 konsisten monokrom di seluruh aplikasi.

- [ ] **Step 4: Jalankan Audit Dark-Mode Parity, Blade Compile Check, Vite Build, & Full PHPUnit Suite**
  - Run audit grep untuk memastikan **0 kemunculan** class light-only yatim (`bg-white`, `bg-zinc-50`, `bg-gray-50`, `bg-gray-100`, `text-slate-900`) tanpa pasangan `dark:` (kecuali `transfers/surat-jalan.blade.php` `@media print`):
    ```bash
    grep -rnE '\b(bg-white|bg-zinc-50|bg-gray-50|bg-gray-100|text-slate-900)\b' resources/views/ | grep -v 'dark:' | grep -v 'surat-jalan'
    ```
    Jika ada baris tersisa yang belum berpasangan `dark:*` (selain di dalam aturan khusus seperti `status-tabs`/`button`/`stat-box` yang memang sudah menyertakan `dark:*` di baris yang sama), perbaiki saat itu juga.
  - Run Vite production build & Blade cache check:
    ```bash
    npm run build && php artisan view:cache && php artisan view:clear
    ```
  - Run **SELURUH** PHPUnit Test Suite:
    ```bash
    php artisan test
    ```
    Expected: `npm run build` sukses, `view:cache` exit 0, dan **100% seluruh tests PASS** tanpa skipped atau gagal!

- [ ] **Step 5: Commit**

```bash
git add resources/views/ resources/js/app.js
git commit -m "feat(ui): complete Blueprint redesign for Master Data, BOM Wizard, Sistem RBAC, and final Dark Mode QA"
```

---

## Panduan Delegasi ke Gemini Hydrogen (Copy-Paste Prompt)

Saat Anda siap mengeksekusi plan ini di sesi **Gemini Hydrogen**, gunakan prompt berikut:

```text
Implementasikan redesign total tampilan production-lab-online-hs berdasarkan spec dan implementation plan berikut:
- Spec: docs/superpowers/specs/2026-11-19-heaven-scent-ui-redesign-design.md
- Plan: docs/superpowers/plans/2026-11-19-heaven-scent-ui-redesign.md

Aturan eksekusi wajib:
1. Gunakan skill superpowers:executing-plans (atau subagent-driven-development), kerjakan berurutan mulai Task 1 sampai Task 10 tanpa melompati task.
2. Sebelum menjalankan command `php` atau `npm`, pastikan PATH menyertakan PHP Homebrew & Node FlyEnv:
   export PATH="/home/linuxbrew/.linuxbrew/bin:$HOME/.config/FlyEnv/app/nodejs/v26.13.1/bin:$PATH"
3. Patuhi seluruh Global Constraints & Test Contracts di spec/plan (Dual-Theme Light & Dark, hs-app-shell fluid scaling, larangan max-w-3xl..7xl / min-w-[...px] kaku / @json([...]) / operator < > di <x-*>).
4. Setelah setiap task selesai: jalankan `npm run build` dan seluruh command `php artisan test` untuk task tersebut hingga PASS 100%, lalu buat 1 git commit atomik sebelum lanjut ke task berikutnya.
5. Jangan ubah logika bisnis, route URL, atau schema database.
```
