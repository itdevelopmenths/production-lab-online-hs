# Heaven Scent Monochrome Luxury UI Redesign — Design Specification

> **Status:** Spec Approved for Planning & Execution  
> **Target Repository:** `production-lab-online-hs` (Laravel 13 + Blade + Alpine.js 3 + Tailwind CSS v4)  
> **Reference Source:** `/home/admin/Projects/HS Project/erp/erp-frontend/.agents/rules/design-system-blueprint.md` (seluruh token, anatomi shell, dan pola komponen diadopsi penuh ke dalam dokumen ini agar 100% *self-contained* untuk eksekutor di dalam repositori)  
> **Tanggal:** 2026-11-19

---

## 1. Intent, Goals, Non-Goals, & Anti-Goals

### 1.1 Intent & Why
Melakukan redesign visual menyeluruh pada `production-lab-online-hs` (**100 file Blade view** + **17 DataTables controller**) agar memiliki identitas visual yang seragam dan identik dengan **Heaven Scent ERP Design System Blueprint**:
- **Monochrome Luxury Black & White**: Aksi utama, fokus input, dan tab navigasi aktif memakai hitam absolut / grafit gelap (`#000000`, `#111111`, gradien `#1e1e1e -> #0a0a0a`) di Light Mode dan kontras putih bersih di Dark Mode.
- **Surface Layering Tegas**: Canvas aplikasi `#F9FAFB` / `dark:#030712`, kartu & modal header/footer `#FFFFFF` / `dark:#111827` (sidebar `#0A0A0A`) dengan border tipis `1px #E8E8E8` / `dark:#262626`, serta body form `#F5F5F5` / `dark:#030712` agar kartu input putih di atasnya tampak terangkat (*elevated*).
- **Micro-Depth Tanpa Shadow Berlebihan**: Border `1px` presisi + shadow mikro (`0px 1px 2px rgba(16,24,40,0.04)`) + *inner specular highlight* dan *bottom glow layer* pada tombol primer dan tab aktif sidebar.
- **Kepatuhan Dual-Theme (Light & Dark)**: Setiap permukaan, border, teks, badge, tabel, modal, dan alert memiliki pasangan class `dark:*` dan token CSS adaptif, bebas FOUC (*Flash of Unstyled Content*).

### 1.2 Goals
1. Paritas visual 1:1 dengan **Heaven Scent Design System Blueprint** di seluruh 100 Blade view (termasuk Guest Login, Shell Layout, Dashboard, 6 modul Operasional/Analisa/Laporan, dan 9 modul Master Data/Sistem).
2. Dual-Theme **Light & Dark Mode** penuh dengan toggle persisten (`localStorage['hs.theme']`) + anti-FOUC bootstrap script di `<head>`.
3. Enam primitif UI baku (`Button`, 4-zone `DataTable` + `UiTableHelper`, `StatusTabs`, `StatsCard`/`stat-box`, `AppForm`/`FormSection`/`FormField`/`SearchableSelect`, `Wizard Stepper` & `WorkflowStepper`) terstandarisasi dan dipakai ulang di semua halaman.
4. Zero regresi fungsional & test: `Model::preventLazyLoading(true)`, `ViewRenderingSafetyTest`, `BladeUiPrd24AlignmentTest`, `Analisa*Test`, `Purchasing*Test`, `Stok*Test`, `DashboardTest` tetap hijau 100%.

### 1.3 Non-Goals
- **Tidak** menulis ulang frontend ke Vue 3 / Inertia.js / Livewire. Tetap memakai **Laravel Blade + Alpine.js 3 + Yajra DataTables + SweetAlert2 + Chart.js**.
- **Tidak** mengubah logika bisnis, skema database, query controller, route name/URL, permission gate, atau alur stage 8-tahap Purchasing & Analisa ADU.
- **Tidak** mengubah halaman cetak A4 (`transfers/surat-jalan.blade.php`) menjadi gelap; dokumen cetak tetap kertas putih bersih pada `@media print`.

### 1.4 Anti-Goals (Larangan Keras)
1. Dilarang merusak *Proportional Fluid Scaling* laptop 13–14" (`html.hs-app-shell` di `resources/css/app.css`).
2. Dilarang menambah class pembatas lebar `max-w-3xl`, `max-w-4xl`, `max-w-5xl`, `max-w-6xl`, `max-w-7xl` pada halaman CRUD/dashboard, class `bg-gradient-to-r` pada dashboard, atau class kaku `min-w-[120px..280px]` / `min-w-[320px]` pada halaman operasional (seluruhnya diaudit oleh `tests/Feature/ViewRenderingSafetyTest.php`).
3. Dilarang memakai `@json([...])` dengan literal array/bracket (selalu pakai `@js(...)`) atau operator mentah `<` / `>` di dalam atribut `x-bind:` / `::` pada tag `<x-...>` (diaudit oleh `ViewRenderingSafetyTest::test_no_risky_blade_directives_or_raw_comparison_operators_in_component_attributes`).
4. Dilarang meninggalkan permukaan light-only (`bg-white`, `bg-zinc-50`, `bg-gray-50`, `bg-gray-100`, `text-slate-900`) tanpa pasangan `dark:*` atau token semantik CSS.
5. Dilarang menghapus/mengubah ID elemen yang diikat DataTables/JS/Tests (`#tbl`, `#usersTable`, `#filter-*`, `#btnResetFilter`, `manualLokalForm.*`, `manualImporForm.*`, `addSkuForm.*`, `stageForm.*`, `copyAutoStages()`, `supplierFilter`, dsb.).

---

## 2. Approaches Considered & Selected Architecture

| Pendekatan | Ringkasan | Trade-off | Keputusan |
| :--- | :--- | :--- | :--- |
| **A. Migrasi ke Vue 3 + Inertia** | Mengonversi seluruh 100 view Blade + 17 DataTables controller menjadi SPA Vue agar dapat menyalin `.vue` dari `erp-frontend`. | Risiko regresi sangat tinggi, mematahkan seluruh PHPUnit feature test & Yajra DataTables, biaya refactor masif. | Ditolak |
| **B. CSS-Only Skinning** | Hanya mengganti palet warna di `resources/css/app.css` tanpa menyentuh Blade components & controllers. | Gagal menghadirkan elemen khas Blueprint: `.sidebar-active-tab`, timeline submenu `#627EF2`, tombol primer glossy ber-glow, body modal `#F5F5F5` + `FormField` kartu putih horizontal, `SearchableSelect` teleported, `32×32px` action buttons, `StatsCard` auto-tinting, dan Dark Mode parity. | Ditolak |
| **C. Bottom-Up Blade Design System Architecture** | Membangun lapisan token CSS & Dual-Theme bridge → App Shell (`AppSidebar` + `AppHeader` + Theme Toggle) → Core Blade Primitives → Form System & Teleported `SearchableSelect` → 4-Zone `DataTable` + `App\Support\UiTableHelper` → Migrasi seluruh view per gelombang modul dengan TDD gate per task. | Mempertahankan 100% backend Laravel, DataTables, Alpine state, dan test contract sambil mencapai paritas visual penuh dengan Blueprint. | **Dipilih** |

---

## 3. Stack Adaptation Matrix (Blueprint Vue → Target Laravel Blade)

| Blueprint (`erp-frontend`) | Implementasi di `production-lab-online-hs` | Catatan Teknis |
| :--- | :--- | :--- |
| `tailwindcss@^4.0.0` + `@custom-variant dark (&:is(.dark *));` | Tailwind v4 (`@tailwindcss/vite`) di `resources/css/app.css` | Tambahkan `@custom-variant dark (&:is(.dark *));` tepat setelah `@import 'tailwindcss';`. |
| `@fontsource/inter` weights `300..800` | `@fontsource/inter/latin-{300,400,500,600,700,800}.css` + `@fontsource/jetbrains-mono/latin-{400,500,600}.css` | Pastikan `latin-300.css` diimpor; hapus plugin `bunny('Instrument Sans')` di `vite.config.js` agar font tunggal adalah `Inter` + `JetBrains Mono`. |
| `lucide-vue-next` (`14–20px`) & `@phosphor-icons/vue` (`18px` sidebar) | Inline SVG crisp stroke-based di Blade (`size-[18px]` pada sidebar, `size-[14px]`–`size-[20px]` pada UI) + opsional helper `UiTableHelper` | Zero dependensi npm baru, zero overhead runtime, SVG tetap tajam pada 1px/1.5px/2px stroke. |
| `AdminLayout` + `AppSidebar` + `AppHeader` | `resources/views/components/app-layout.blade.php`, `nav-item.blade.php`, `nav-group.blade.php` (baru) | Mempertahankan `hs-app-shell` bounded fluid scaling dan `appSidebarState()`. |
| `Button.vue` | `resources/views/components/button.blade.php` | Primary glossy `#1e1e1e -> #0a0a0a` + bottom blur glow layer + crisp white `secondary`/`outline` + soft semantic variants. |
| `DataTable.vue` (4 Zona) | `resources/css/app.css` + `app-layout.blade.php` (defaults DataTables) + `card.blade.php` + `app/Support/UiTableHelper.php` (baru) | 4 zona konsisten untuk 17 DataTables & tabel Alpine/server-rendered. |
| `StatusTabs.vue` | `resources/views/components/status-tabs.blade.php` (baru) | Mendukung mode `href` link, Alpine `model`, maupun callback JS (`@click` / `filterTab()`). |
| `StatsCard.vue` | `resources/views/components/stat-box.blade.php` | Tambah prop `iconColor` (`indigo`, `emerald`, `blue`, `amber`, `rose`, `purple`, `gray`), nilai `28px` semibold `tracking-[-0.308px]`, trend pill, dan guard `flex-1 min-w-0 break-words`. |
| `AppForm`, `FormSection`, `FormField` | `modal.blade.php`, `form-section.blade.php` (baru), `form-field.blade.php` (baru), `form-group.blade.php`, `input.blade.php`, `select.blade.php`, `textarea.blade.php` | Modal body `#F5F5F5` / `dark:bg-gray-950`, kartu putih `rounded-[8px]` horizontal (`150px` label), `variant="seamless"` untuk input di dalam `FormField`. |
| `SearchableSelect` (`<Teleport to="body">`) | `resources/views/components/product-search-select.blade.php` + `resources/js/app.js` | Memakai Alpine 3 `x-teleport="body"` + `position: fixed` berbasis `getBoundingClientRect()` (`z-index: 9999`), debounce 300ms, dan support `variant="seamless"`. |
| `ExcelImportModal` Wizard & `WorkflowStepper` | `resources/views/bom/import.blade.php` (3-step wizard `#00bc7d`) + `resources/views/components/purchasing-progress-stepper.blade.php` | Preserves 8-Stage Purchasing classification & `aria-label` test contracts. |
| `Badge.vue` & Status Pills | `resources/views/components/badge.blade.php` + `UiTableHelper::statusPill()` | Dual-theme pill & semantic status variants. |
| `Brand Logo Badge` | `resources/views/auth/login.blade.php` + Sidebar Brand Header | Glossy Dark Square `size-[56px] rounded-[12px]` memakai `asset('Logo HS - black.png')` dengan filter `brightness-0 invert` agar tampil putih murni tanpa perlu file eksternal. |

---

## 4. Verbatim Design Tokens & CSS Architecture (`resources/css/app.css`)

### 4.1 Tabel Palet Warna (Light & Dark)

| Kategori | Token / Nama | Light Mode | Dark Mode | Kegunaan |
| :--- | :--- | :--- | :--- | :--- |
| **Surface Base** | `--bg` | `#FFFFFF` | `#0A0A0A` | Sidebar drawer, kartu dasar, modal header/footer |
| **Surface Subtle** | `--bg-2` | `#FBFBFB` | `#141414` | Input search sidebar, header tabel sekunder |
| **Surface Muted** | `--bg-3` | `#F5F5F5` | `#1A1A1A` | Body container modal/form `AppForm`, canvas sekunder |
| **App Canvas** | `bg-gray-50` / `--color-background` | `#F9FAFB` | `#030712` (`gray-950`) | Background utama halaman |
| **Active Surface** | `--color-bg-active` | `#F2F2F2` | `#111111` | Hover pill / highlight bar |
| **Border Default** | `--border` / `--color-border` | `#E8E8E8` | `#262626` | Border kartu, tabel, sidebar, form field |
| **Border Hover/Strong** | `--border-2` / `--color-border-active` | `#DEDEDE` | `#383838` | Border sekunder saat hover |
| **Border Focus** | `--color-border-focus` | `#111111` | `#FFFFFF` | Ring focus input |
| **Text Primary / Active** | `--color-text-active` / `#101828` | `#111111` / `#101828` | `#FFFFFF` | Judul, isi sel tabel, nilai input, heading |
| **Text Muted** | `--color-text-muted` / `#6A7282` | `#666666` / `#6A7282` | `#999999` / `#9CA3AF` | Label kolom tabel, subtitle, metadata |
| **Text Placeholder** | `--color-text-placeholder` | `#999999` | `#737373` | Placeholder input |
| **Brand Monochrome** | `brand-500` .. `brand-950` | `#000000` | `#000000` (teks white) | Tombol brand, border focus, progress fill |
| **Semantic Success** | `success-500` / Stepper Emerald | `#17B26A` / `#00BC7D` | `#32D583` | Status selesai, stepper aktif/selesai |
| **Semantic Warning** | `warning-500` | `#F79009` | `#FDB022` | Peringatan, status pending/opening |
| **Semantic Error** | `error-500` / `#E7000B` | `#F04438` / `#E7000B` | `#F97066` | Validasi error, hapus data, status dibatalkan |
| **Semantic Info / Accent** | `blue-light-500` / `#627EF2` | `#0BA5EC` / `#627EF2` | `#36BFFA` | Timeline submenu aktif, link, badge info |

### 4.2 Complete Token Block & Backward-Compatible Semantic Bridge

Blok berikut **wajib** ada di `resources/css/app.css` (menggabungkan token Blueprint Section 2B, bridge token legacy `--color-background`/`--color-card`/`--color-foreground`/`--color-muted`/`--color-border`/`--color-primary`/`--color-destructive`, serta *Bounded Fluid Scaling* `html.hs-app-shell`):

```css
@import 'tailwindcss';

/* ── 1. FONT DEFINITIONS (Zero-Flash Local Inter & JetBrains Mono) ── */
@import '@fontsource/inter/latin-300.css';
@import '@fontsource/inter/latin-400.css';
@import '@fontsource/inter/latin-500.css';
@import '@fontsource/inter/latin-600.css';
@import '@fontsource/inter/latin-700.css';
@import '@fontsource/inter/latin-800.css';
@import '@fontsource/jetbrains-mono/latin-400.css';
@import '@fontsource/jetbrains-mono/latin-500.css';
@import '@fontsource/jetbrains-mono/latin-600.css';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../views/**/*.blade.php';
@source '../../app/Support/UiTableHelper.php';

@custom-variant dark (&:is(.dark *));

/* ── 2. SEMANTIC CSS VARIABLES (LIGHT & DARK + BACKWARD-COMPAT BRIDGE) ── */
:root {
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --font-display: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  /* Blueprint Semantic Variables */
  --color-bg-active: #F2F2F2;
  --color-border-active: #DEDEDE;
  --color-border-focus: #111111;
  --color-text-active: #111111;
  --color-text-muted: #6A7282;
  --color-text-placeholder: #999999;

  --bg: #FFFFFF;
  --bg-2: #FBFBFB;
  --bg-3: #F5F5F5;
  --canvas: #F9FAFB;

  --text: #111111;
  --text-base: #101828;
  --text-muted: var(--color-text-muted);
  --text-subtle: #888888;

  --border: #E8E8E8;
  --border-2: #DEDEDE;

  --primary: #000000;
  --primary-rgb: 0, 0, 0;
  --nav-bg: rgba(255, 255, 255, 0.85);

  /* Production Lab Backward-Compatible Token Bridge */
  --hs-background: #F9FAFB;
  --hs-foreground: #101828;
  --hs-card: #FFFFFF;
  --hs-card-foreground: #101828;
  --hs-primary: #000000;
  --hs-primary-foreground: #FFFFFF;
  --hs-muted: #F5F5F5;
  --hs-muted-foreground: #6A7282;
  --hs-accent: #F2F2F2;
  --hs-accent-foreground: #111111;
  --hs-border: #E8E8E8;
  --hs-input: #E8E8E8;
  --hs-ring: #111111;
  --hs-destructive: #F04438;
  --hs-destructive-foreground: #FFFFFF;
}

.dark {
  --color-bg-active: #111111;
  --color-border-active: #383838;
  --color-border-focus: #FFFFFF;
  --color-text-active: #FFFFFF;
  --color-text-muted: #9CA3AF;
  --color-text-placeholder: #737373;

  --bg: #0A0A0A;
  --bg-2: #141414;
  --bg-3: #1A1A1A;
  --canvas: #030712;

  --text: #FFFFFF;
  --text-base: #EDEDED;
  --text-muted: var(--color-text-muted);
  --text-subtle: #737373;

  --border: #262626;
  --border-2: #383838;

  --primary: #FFFFFF;
  --primary-rgb: 255, 255, 255;
  --nav-bg: rgba(10, 10, 10, 0.85);

  /* Production Lab Backward-Compatible Token Bridge (Dark) */
  --hs-background: #030712;
  --hs-foreground: #FFFFFF;
  --hs-card: #111827;
  --hs-card-foreground: #FFFFFF;
  --hs-primary: #FFFFFF;
  --hs-primary-foreground: #0A0A0A;
  --hs-muted: #1A1A1A;
  --hs-muted-foreground: #9CA3AF;
  --hs-accent: #1F2937;
  --hs-accent-foreground: #FFFFFF;
  --hs-border: #262626;
  --hs-input: #262626;
  --hs-ring: #FFFFFF;
  --hs-destructive: #F97066;
  --hs-destructive-foreground: #0A0A0A;
}

/* ── 3. TAILWIND V4 @theme MAPPING ── */
@theme {
  --font-*: initial;
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --font-display: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  /* Blueprint Direct Tokens */
  --color-bg: var(--bg);
  --color-bg-2: var(--bg-2);
  --color-bg-3: var(--bg-3);
  --color-bg-active: var(--color-bg-active);
  --color-border: var(--hs-border);
  --color-border-2: var(--border-2);
  --color-border-active: var(--color-border-active);
  --color-border-focus: var(--color-border-focus);
  --color-text: var(--text);
  --color-text-base: var(--text-base);
  --color-text-active: var(--color-text-active);
  --color-text-muted: var(--color-text-muted);
  --color-text-placeholder: var(--color-text-placeholder);
  --color-text-subtle: var(--text-subtle);

  /* Semantic Bridge Tokens (Existing Views Compatibility) */
  --color-background: var(--hs-background);
  --color-foreground: var(--hs-foreground);
  --color-card: var(--hs-card);
  --color-card-foreground: var(--hs-card-foreground);
  --color-primary: var(--hs-primary);
  --color-primary-foreground: var(--hs-primary-foreground);
  --color-muted: var(--hs-muted);
  --color-muted-foreground: var(--hs-muted-foreground);
  --color-accent: var(--hs-accent);
  --color-accent-foreground: var(--hs-accent-foreground);
  --color-input: var(--hs-input);
  --color-ring: var(--hs-ring);
  --color-destructive: var(--hs-destructive);
  --color-destructive-foreground: var(--hs-destructive-foreground);

  /* Breakpoints */
  --breakpoint-*: initial;
  --breakpoint-2xsm: 375px;
  --breakpoint-xsm: 425px;
  --breakpoint-sm: 640px;
  --breakpoint-md: 768px;
  --breakpoint-lg: 1024px;
  --breakpoint-xl: 1280px;
  --breakpoint-2xl: 1536px;
  --breakpoint-3xl: 2000px;

  /* Type Tokens */
  --text-xs: max(0.75rem, 10.5px);
  --text-theme-xl: 20px;
  --text-theme-xl--line-height: 30px;
  --text-theme-sm: 14px;
  --text-theme-sm--line-height: 20px;
  --text-theme-xs: 12px;

  /* Radius Tokens */
  --radius-lg: 0.75rem;
  --radius-md: 0.5rem;
  --radius-sm: 0.375rem;

  /* Monochrome Brand Scale */
  --color-brand-25: #FCFCFC;
  --color-brand-50: #F9F9F9;
  --color-brand-100: #F2F2F2;
  --color-brand-200: #EAEAEA;
  --color-brand-300: #D5D5D5;
  --color-brand-400: #A4A4A4;
  --color-brand-500: #000000;
  --color-brand-600: #000000;
  --color-brand-700: #000000;
  --color-brand-800: #000000;
  --color-brand-900: #000000;
  --color-brand-950: #000000;

  /* Legacy Primary Neutral Scale (Compat) */
  --color-primary-50: #f4f4f5;
  --color-primary-100: #e4e4e7;
  --color-primary-200: #d4d4d8;
  --color-primary-300: #a1a1aa;
  --color-primary-400: #71717a;
  --color-primary-500: #27272a;
  --color-primary-600: #18181b;
  --color-primary-700: #09090b;
  --color-primary-800: #040405;
  --color-primary-900: #000000;

  /* Half-step OKLCH Grays */
  --color-gray-150: oklch(94.75% .0045 264.537);
  --color-gray-250: oklch(90% .008 261.435);
  --color-gray-350: oklch(78.95% .016 259.832);
  --color-gray-450: oklch(62.9% .0245 262.845);
  --color-gray-550: oklch(49.85% .0285 260.583);
  --color-gray-650: oklch(40.95% .032 258.268);
  --color-gray-750: oklch(32.55% .0335 258.291);
  --color-gray-850: oklch(24.4% .0335 260.757);

  /* Semantic Colors */
  --color-success-50: #ecfdf3;
  --color-success-500: #17B26A;
  --color-success-600: #039855;

  --color-error-50: #fef3f2;
  --color-error-500: #F04438;
  --color-error-600: #d92d20;

  --color-warning-50: #fffaeb;
  --color-warning-500: #F79009;
  --color-warning-600: #dc6803;

  --color-blue-light-50: #f0f9ff;
  --color-blue-light-500: #0ba5ec;

  --color-orange-50: #fff6ed;
  --color-orange-500: #fb6514;

  /* Elevation Shadows */
  --shadow-theme-xs: 0px 1px 2px 0px rgba(16, 24, 40, 0.02);
  --shadow-theme-sm: 0px 2px 4px 0px rgba(16, 24, 40, 0.03);
  --shadow-theme-md: 0px 12px 16px -4px rgba(16, 24, 40, 0.04), 0px 4px 6px -2px rgba(16, 24, 40, 0.02);
  --shadow-theme-lg: 0px 24px 32px -8px rgba(16, 24, 40, 0.04), 0px 8px 12px -4px rgba(16, 24, 40, 0.03);
  --shadow-theme-xl: 0px 32px 64px -12px rgba(16, 24, 40, 0.08);
  --shadow-focus-ring: 0px 0px 0px 4px rgba(70, 95, 255, 0.08);
}
```

### 4.3 Skala Tipografi Baku (Inter + Tight Tracking)

| Elemen UI | Ukuran & Line Height | Weight | Tracking | Warna Baku (Light / Dark) | Contoh Class Tailwind |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Header Halaman (`AppHeader`)** | `14px`–`16px` / `leading-tight` | `700` | `tracking-tight` | `#111827` / `#FFFFFF` | `text-sm md:text-base font-bold text-gray-900 dark:text-white tracking-tight` |
| **Judul Card / DataTable** | `16px` / `leading-[1.4]` | `600` | `tracking-[-0.176px]` | `#101828` / `#FFFFFF` | `text-[16px] leading-[1.4] font-semibold tracking-[-0.176px] text-[#101828] dark:text-white` |
| **Angka KPI (`StatsCard`)** | `28px` / `leading-[1.4]` | `600` | `tracking-[-0.308px]` | `#101828` / `#FFFFFF` | `text-[28px] leading-[1.4] font-semibold text-[#101828] dark:text-white tracking-[-0.308px]` |
| **Judul Modal (`AppForm`)** | `16px` (`text-base`) | `700` | `tracking-tight` | `text-text-active` | `font-bold text-base text-text-active tracking-tight leading-snug` |
| **Sel Tabel (`DataTable`)** | `14px` / `20px` | `400`–`500` | `tracking-[-0.154px]` | `#101828` / `#FFFFFF` | `text-[14px] leading-[20px] font-normal tracking-[-0.154px] text-[#101828] dark:text-white` |
| **Label FormField & Input** | `13px`–`14px` | `500` | Normal / `-0.01em` | `text-text-active` | `text-[13px] sm:text-[14px] font-medium text-text-active` |
| **Header Kolom Tabel (`<th>`)** | `12px`–`13px` | `400` | Normal | `text-text-muted` | `text-xs sm:text-[13px] font-normal text-text-muted` |
| **Group Sidebar Eyebrow** | `12px` uppercase | `600` | `tracking-[0.6px]` | `text-text-muted` | `text-[12px] font-semibold text-text-muted uppercase tracking-[0.6px]` |
| **FormSection Eyebrow** | `12px` uppercase | `500` | `tracking-[0.12px]` | `text-text-muted` | `text-[12px] font-medium text-text-muted tracking-[0.12px] uppercase` |
| **Subtitle / Caption** | `12px` / `18px` | `400` | `tracking-[-0.132px]` | `#6A7282` / `gray-400` | `text-[12px] leading-[18px] font-normal tracking-[-0.132px] text-[#6a7282] dark:text-gray-400` |
| **Kode / ID / SKU** | `11px`–`12px` | `600` | Normal | `text-text-muted` / `active` | `font-mono text-xs font-semibold` |

---

## 5. Spesifikasi Komponen & Shell Layout

### 5.1 Application Shell (`AppLayout`, `AppSidebar`, `AppHeader`)
- **Root Wrapper**: `min-h-screen bg-gray-50 dark:bg-gray-950 lg:flex overflow-x-hidden`
- **`AppSidebar`**:
  - Dimensi desktop: Expanded `lg:w-[264px]`, Collapsed `lg:w-[80px]`. Mobile: slide-over `translate-x-0 w-[264px]` vs `-translate-x-full lg:translate-x-0`.
  - Surface: `fixed inset-y-0 left-0 z-[100] lg:sticky lg:top-0 lg:h-screen border-r border-[#E8E8E8] dark:border-border bg-white dark:bg-[#0A0A0A] flex flex-col shadow-theme-xs transition-all duration-300 ease-out`.
  - Top Brand + Toggle + Search:
    - Brand logo icon menggunakan **Glossy Dark Square** (`size-9 rounded-[8px]`) berisi `asset('Logo HS - black.png')` (`brightness-0 invert`), label `HEAVEN SCENT` / `Production Lab`.
    - Search bar (`!collapsed`): `h-[44px] px-3 gap-2 bg-[#FBFBFB] dark:bg-[#141414] border border-[#E1E1E1] dark:border-border rounded-lg focus-within:border-border-focus`.
    - Collapsed search/expand trigger: tombol kotak `size-[40px] p-[8px] rounded-[8px] bg-[#FBFBFB] dark:bg-[#141414] border border-[#E1E1E1] dark:border-border`.
  - Menu Item Standard (`44px` height):
    - Base: `w-full h-[44px] rounded-lg transition-all duration-200 flex items-center relative border overflow-hidden px-3 gap-2.5` (collapsed: `px-0 justify-center`).
    - Icon: `size-[18px] shrink-0`. Text: `text-[14px] leading-5 font-medium tracking-[-0.16px] whitespace-nowrap`.
    - Inactive: `border-transparent text-text-muted hover:bg-[#FBFBFB] hover:text-black dark:hover:bg-white/[0.02] dark:hover:text-white`.
    - **Active (`.sidebar-active-tab`)**:
      ```css
      .sidebar-active-tab {
        background-color: #111111 !important;
        background-image:
          url("data:image/svg+xml;utf8,<svg viewBox='0 0 232 44' xmlns='http://www.w3.org/2000/svg' preserveAspectRatio='none'><rect x='0' y='0' height='100%' width='100%' fill='url(%23grad)' opacity='0.5'/><defs><radialGradient id='grad' gradientUnits='userSpaceOnUse' cx='0' cy='0' r='10' gradientTransform='matrix(4.5373e-15 5.4214 -16.991 -5.8638e-13 116 26.714)'><stop stop-color='rgba(84,84,84,1)' offset='0'/><stop stop-color='rgba(84,84,84,0)' offset='1'/></radialGradient></defs></svg>"),
          linear-gradient(90deg, rgb(17, 17, 17) 0%, rgb(17, 17, 17) 100%) !important;
        border: 1px solid #525252 !important;
        box-shadow: 0px 0px 0px 1px #212121 !important;
        color: #FFFFFF !important;
      }
      ```
      Ditambah *bottom glow pill* internal:
      `<div class="-translate-x-1/2 absolute h-[28px] left-1/2 top-[29px] w-[232px] pointer-events-none opacity-40 bg-radial-[at_50%_50%] from-stone-400 to-transparent rounded-full blur-[2px]"></div>`
  - **Submenu Tree & Vertical Timeline Indicator (`<x-nav-group>`)**:
    - Group yang memiliki beberapa menu turunan (**Stok & Mutasi**, **Master Data**, **Laporan**, **Sistem**) memakai `<x-nav-group>` yang otomatis terbuka saat salah satu child route aktif atau saat `menuQuery` cocok dengan child-nya.
    - Container list: `mt-1 space-y-0.5 ml-5 border-l-[3px] border-[#E8E8E8] dark:border-border pl-4 py-0.5`.
    - Active sub-item indicator bar (`#627EF2`):
      `<div class="absolute left-[-19px] top-1/2 -translate-y-1/2 w-[3px] h-6 bg-[#627EF2] rounded-full"></div>`
  - **Sidebar Footer**: `min-h-[72px] pt-3 border-t border-[#E8E8E8] dark:border-border` berisi user menu + dev role switcher.
- **`AppHeader` (Sticky Frosted Glass Bar)**:
  - Container: `sticky top-0 z-[50] w-full h-16 px-5 lg:px-8 backdrop-blur-md bg-white/80 dark:bg-gray-950/80 border-b border-gray-100 dark:border-gray-800/60 shadow-theme-xs flex items-center justify-between gap-3`.
  - Mobile buttons: Drawer trigger `lg:hidden size-10 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm`, Back button `lg:hidden size-10 rounded-full border-[#e5e7eb] dark:border-gray-700 shadow-[0px_0px_0px_2px_rgba(43,127,255,0.2),0px_1px_3px_0px_rgba(0,0,0,0.1)]`.
  - Title stack: `h1` (`text-sm md:text-base font-bold text-gray-900 dark:text-white tracking-tight truncate`) + `p` subtitle (`text-[10px] md:text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 font-medium truncate`).
  - Kanan Header: Slot `$actions` + **Dual-Theme Toggle Button** (ikon Sun/Moon Alpine `window.hsTheme.toggle()`, `size-9 rounded-[8px] border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900`).
- **Anti-FOUC Theme Init Script (di `<head>` sebelum CSS)**:
  ```html
  <script>
      (function () {
          try {
              var t = localStorage.getItem('hs.theme');
              var isDark = t === 'dark' || (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
              document.documentElement.classList.toggle('dark', isDark);
              document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
          } catch (e) {}
      })();
  </script>
  ```

### 5.2 Primitif 1: `Button` (`resources/views/components/button.blade.php`)
- Base: `relative overflow-hidden group focus-visible:outline-none inline-flex shrink-0 items-center justify-center border rounded-[6px] tracking-[-0.154px] leading-[1.4] whitespace-nowrap transition-all duration-150 active:scale-[0.98] disabled:pointer-events-none cursor-pointer select-none`.
- Skala ukuran:
  - `xs`: `h-7 px-2.5 py-0.5 text-xs gap-1 rounded-[6px]`
  - `sm`: `h-8 px-3 py-1.5 text-xs sm:text-[13px] gap-1.5 rounded-[6px]`
  - `md` / `default`: `h-[38px] px-3.5 py-2 text-xs sm:text-sm gap-1.5 rounded-[6px]`
  - `lg`: `h-11 px-4 sm:px-5 py-2.5 text-sm sm:text-base gap-2 rounded-[6px]`
  - `icon`: `size-[32px] p-0 rounded-[6px]`
- Varian visual:
  - **`primary` / `glossy`**:
    `text-white bg-gradient-to-b from-[#1e1e1e] to-[#0a0a0a] border-[rgba(255,255,255,0.3)] shadow-[inset_0px_2px_2px_0px_rgba(255,255,255,0.05),inset_0px_2px_2px_0px_rgba(255,255,255,0.08)] hover:from-[#2c2c2c] hover:to-[#141414] hover:border-[rgba(255,255,255,0.4)] active:from-[#0a0a0a] active:to-[#1a1a1a] active:border-[rgba(255,255,255,0.18)] active:shadow-[inset_0px_1px_3px_0px_rgba(0,0,0,0.4)] focus-visible:shadow-[0px_0px_0px_1px_#ffffff,0px_0px_0px_2px_rgba(46,46,46,0.22)] dark:from-white dark:to-gray-100 dark:text-[#0A0A0A] dark:border-white/80 dark:hover:from-gray-100 dark:hover:to-gray-200 disabled:from-[#ededed] disabled:to-[#e3e3e3] disabled:text-[#a3a3a3] disabled:border-[rgba(255,255,255,0.6)] dark:disabled:from-zinc-800 dark:disabled:to-zinc-800 dark:disabled:text-zinc-500`
    Dilengkapi *bottom glow layer* internal (`!disabled`):
    ```html
    <div class="absolute inset-0 rounded-[inherit] overflow-hidden pointer-events-none z-10 opacity-65 group-hover:opacity-100 group-active:opacity-30">
      <div class="absolute left-[-8%] right-[-8%] -bottom-[10px] h-[18px] rounded-full"
           style="background: linear-gradient(90deg, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.5) 25%, rgba(213,229,248,0.5) 75%, rgba(213,229,248,0.02) 100%); filter: blur(6px);"></div>
    </div>
    ```
  - **`secondary` / `outline`**:
    `bg-white text-black dark:bg-zinc-800 dark:text-zinc-100 border-[#e8e8e8] dark:border-zinc-700 hover:bg-[#fafafa] hover:border-[#dedede] dark:hover:bg-zinc-700 active:bg-[#f3f3f3] shadow-theme-xs`
  - **Semantic Soft Variants**:
    - `warning`: `bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400`
    - `danger` / `destructive`: `bg-red-50 text-red-600 border-red-200 hover:bg-red-100 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400`
    - `success`: `bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400`
    - `info`: `bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100 dark:bg-blue-500/10 dark:border-blue-500/20 dark:text-blue-400`
    - `ghost`: `border-transparent bg-transparent text-text-active hover:bg-gray-100 dark:hover:bg-gray-800`
    - `link`: `border-transparent bg-transparent text-text-active underline-offset-4 hover:underline p-0 h-auto shadow-none`

### 5.3 Primitif 2: `DataTable` (4 Zona) + `Card` + `UiTableHelper`
- **Outer Card (`<x-card>`)**: `bg-white dark:bg-gray-900 border border-[#e8e8e8] dark:border-border rounded-[12px] flex flex-col shadow-theme-xs`
- **Zone 1 — Header (`px-5 py-4 border-b border-[#e8e8e8] dark:border-border`)**:
  - Left Icon Box (`$icon`): `size-[42px] rounded-[8px] bg-[#f9fafb] dark:bg-gray-800 border border-[#e8e8e8] dark:border-border flex items-center justify-center shrink-0` (ikon `size-[20px] text-[#101828] dark:text-white`).
  - Title & Count Pill (`$count`): `text-[16px] leading-[1.4] font-semibold tracking-[-0.176px] text-[#101828] dark:text-white` + pill `bg-[#f3f4f6] dark:bg-gray-800 px-[7px] py-px rounded-full text-[12px] font-medium text-[#6a7282] dark:text-gray-400`.
  - Subtitle: `text-[12px] leading-[18px] tracking-[-0.132px] text-[#6a7282] dark:text-gray-400`.
- **Zone 2 — Toolbar (`px-5 py-3 border-b border-[#e8e8e8] dark:border-border`)**:
  - `.dt-layout-header`: `flex flex-wrap justify-between items-center px-5 py-3 border-b border-[#e8e8e8] dark:border-border gap-3 bg-white dark:bg-gray-900`.
  - Search input: `h-[32px] w-full sm:w-64 min-w-0 bg-white dark:bg-gray-900 border border-[#e8e8e8] dark:border-border rounded-[8px] px-3 py-1.5 text-xs text-[#101828] dark:text-white focus:border-brand-500 dark:focus:border-white`.
- **Zone 3 — Table Content**:
  - `<thead>`: `sticky top-0 z-30 bg-[#f8fafb] dark:bg-gray-800/95 backdrop-blur-md border-y border-border`
  - `<th>`: `px-3 py-2.5 font-normal text-text-muted text-xs sm:text-[13px] whitespace-nowrap` (tidak uppercase kaku; ikon sort aktif `text-blue-600 dark:text-blue-400`).
  - `<tbody>`: `divide-y divide-border dark:divide-gray-800/50 text-text-active font-medium`
  - `<tr>`: `group transition-all duration-200 hover:bg-gray-50/80 dark:hover:bg-gray-800/30`
  - `<td>`: `px-3 py-3 text-[14px] leading-[20px] font-normal tracking-[-0.154px] text-[#101828] dark:text-white`
  - **Row Action Square Buttons (`32×32px` via `App\Support\UiTableHelper`)**:
    - Base: `size-[32px] rounded-[6px] bg-white dark:bg-gray-900 inline-flex items-center justify-center transition-colors border shrink-0` (ikon `size-[14px]` + `<span class="sr-only">Label</span>` + `title="Label"`).
    - `default` (Detail/Edit): `border-[#e8e8e8] dark:border-border hover:bg-gray-50 dark:hover:bg-gray-800 text-[#101828] dark:text-white`
    - `danger` (Delete): `border-[#ffc9c9] dark:border-red-500/30 hover:bg-red-50/70 dark:hover:bg-red-950/50 text-[#e7000b] dark:text-red-400`
    - `success` (Approve/Verify): `border-[#a4f4cf] dark:border-emerald-500/30 hover:bg-emerald-50/70 dark:hover:bg-emerald-950/50 text-[#007a55] dark:text-emerald-400`
- **Zone 4 — Pagination Footer**:
  - `.dt-layout-footer`: `pt-3 px-5 py-3 border-t border-border bg-white dark:bg-gray-900 flex flex-wrap justify-between items-center gap-3 rounded-b-[12px]`.
  - Page size select: `h-8 sm:h-9 min-w-[54px] rounded-[4px] border border-border bg-white dark:bg-gray-900`.
  - Tombol halaman: `w-8 h-8 sm:min-w-[36px] sm:h-9 rounded-[4px] border border-border`; halaman aktif memakai `bg-black dark:bg-white text-white dark:text-gray-900 relative overflow-hidden` + glow radial putih halus.

### 5.4 Primitif 3: `StatusTabs` (`resources/views/components/status-tabs.blade.php`)
- Outer Track: `bg-[#f3f4f6] dark:bg-gray-900 dark:border dark:border-gray-800 p-[2px] gap-[2px] rounded-[8px] h-9 sm:h-10 inline-flex items-center w-fit max-w-full overflow-x-auto no-scrollbar`
- Tab Button: `px-[10px] sm:px-3 py-[4px] text-[13px] sm:text-[14px] leading-[20px] tracking-[-0.154px] gap-[6px] rounded-[6px] h-full inline-flex items-center whitespace-nowrap transition-all cursor-pointer`
  - Active: `bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.05)] font-semibold`
  - Inactive: `bg-transparent text-[#6a7282] dark:text-gray-400 border border-transparent hover:text-[#101828] dark:hover:text-white hover:bg-black/[0.03] dark:hover:bg-white/[0.03] font-medium`
- Opsional dot: `size-[6px] rounded-full shrink-0`; opsional count badge: `bg-[#f3f4f6] dark:bg-gray-700 text-[#364153] dark:text-gray-200 px-[7px] py-px rounded-full text-[12px] font-semibold tabular-nums`.

### 5.5 Primitif 4: `StatsCard` (`resources/views/components/stat-box.blade.php`)
- Outer Card: `bg-white dark:bg-gray-900 border border-[#e8e8e8] dark:border-gray-800 rounded-[12px] px-[20px] py-[16px] flex flex-col gap-[12px] justify-between shadow-[0px_1px_2px_0px_rgba(16,24,40,0.04)] hover:border-gray-300 dark:hover:border-gray-700 transition-all`
- Icon Box (`size-[32px] rounded-[8px] shrink-0 flex items-center justify-center`) auto-tinted via prop `iconColor`:
  - `indigo` (default): `bg-[#eef2ff] dark:bg-indigo-500/15 text-[#4f39f6] dark:text-indigo-400`
  - `emerald`: `bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400`
  - `blue`: `bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400`
  - `amber`: `bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400`
  - `rose`: `bg-red-50 dark:bg-red-500/15 text-red-600 dark:text-red-400`
  - `purple`: `bg-purple-50 dark:bg-purple-500/15 text-purple-600 dark:text-purple-400`
  - `gray`: `bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300`
- Value typography: `text-[28px] leading-[1.4] font-semibold text-[#101828] dark:text-white tracking-[-0.308px] break-words tabular-nums` + wrapper teks `flex-1 min-w-0` (memenuhi `ViewRenderingSafetyTest`).

### 5.6 Primitif 5: Form Architecture (`Modal/AppForm`, `FormSection`, `FormField`, `ProductSearchSelect`)
- **Modal (`<x-modal>`)**:
  - Backdrop: `fixed inset-0 z-[999] bg-black/50 backdrop-blur-xs p-2 sm:p-4 md:p-6 flex items-center justify-center`
  - Dialog Box: `bg-white dark:bg-gray-900 border border-border rounded-[12px] shadow-2xl overflow-hidden flex flex-col max-h-[calc(100dvh-2rem)]`
  - Header: `px-3.5 py-2.5 sm:px-4 sm:py-3 bg-white dark:bg-gray-900 border-b border-border` (`font-bold text-base text-text-active tracking-tight leading-snug` + subtitle `text-xs text-text-muted`)
  - **Body**: `bg-[#F5F5F5] dark:bg-gray-950 p-3 sm:p-4 md:p-5 flex flex-col gap-2.5 sm:gap-3.5 overflow-y-auto custom-scrollbar flex-1`
  - **Footer**: `bg-white dark:bg-gray-900 border-t border-border px-3.5 py-2.5 sm:px-4 sm:py-3 flex items-center justify-end gap-2 shrink-0`
- **`<x-form-section>`**:
  - Eyebrow: `pl-[10px] text-left` → `<span class="text-[12px] font-medium text-text-muted tracking-[0.12px] uppercase">{{ $title }}</span>`
  - Grid: `w-full gap-[10px] grid grid-cols-1 md:grid-cols-2` (atau `cols=1|2|3|4`).
- **`<x-form-field>` (Inline White Card — Signature Pattern)**:
  ```html
  <div class="bg-white dark:bg-gray-900 border border-border dark:border-border rounded-[8px] flex flex-col sm:flex-row sm:items-center px-3 py-2 gap-2.5 w-full relative transition-all duration-200 focus-within:border-brand-500 dark:focus-within:border-white">
    <label class="shrink-0 font-medium text-[13px] sm:text-[14px] text-text-active text-left select-none leading-snug sm:w-[150px]">
      <span>Nama Field</span><span class="text-red-500 ml-0.5">*</span>
    </label>
    <div class="flex-1 w-full relative flex items-center min-w-0">
      <!-- seamless input / select / textarea / product-search-select -->
    </div>
  </div>
  ```
  - Disabled state: `opacity-60 bg-gray-50 dark:bg-gray-800/40 cursor-not-allowed`.
  - `textarea` mode (`align="top"`): `sm:items-start` pada container + `sm:mt-1` pada label.
- **`<x-product-search-select>` (Teleported Remote Search Dropdown)**:
  - Mendukung `variant="seamless"` (tanpa border/shadow luar saat di dalam `FormField`) maupun `variant="boxed"` (default standalone).
  - Panel dropdown dirender dengan Alpine 3 `<template x-teleport="body">` dan `position: fixed; z-index: 9999;` yang menghitung `top`, `left`, `width` dari `$refs.trigger.getBoundingClientRect()` (auto-flip ke atas jika ruang bawah viewport sempit, ikut ter-update saat scroll/resize), sehingga **tidak pernah terpotong `overflow-hidden` modal/card**.
  - Debounce pencarian `300ms`, limit server-side `10`/`20` item, panel `rounded-xl border border-gray-200 bg-white shadow-2xl overflow-hidden dark:border-gray-700 dark:bg-gray-900`.

### 5.7 Primitif 6: Stepper, Badge, Status Pills, & Brand Logo Badge
- **Wizard Stepper (3-step BOM Import di `bom/import.blade.php`)**:
  - Outer card: `bg-white dark:bg-gray-900 border border-border rounded-xl px-3 sm:px-6 py-2.5 sm:py-3.5 shadow-2xs`
  - Completed/Active circle (`28×28px`): `w-7 h-7 rounded-full bg-[#00bc7d] text-white shadow-[0px_0px_0px_4px_rgba(0,188,125,0.18)]`
  - Pending circle: `w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500`
  - Connector line: `flex-1 min-w-[16px] sm:min-w-[32px] h-[2px] rounded-[1px]` (`bg-[#00bc7d]` atau `bg-gray-200 dark:bg-gray-700`)
- **`WorkflowStepper` (`purchasing-progress-stepper.blade.php`)**:
  - Card putih `p-4 sm:p-5 rounded-[12px] border border-border bg-white dark:bg-gray-900`, track `h-0.5 bg-gray-100 dark:bg-gray-800`.
  - Node selesai: `bg-[#00bc7d] text-white shadow-[0px_0px_0px_4px_rgba(0,188,125,0.18)]`. Node aktif: `bg-brand-500 dark:bg-white text-white dark:text-gray-900 shadow-lg ring-4 ring-brand-500/15 dark:ring-white/20`. Node terlambat: `bg-[#F04438] text-white ring-4 ring-red-500/20`.
  - Tetap mempertahankan `tabindex="0" role="region" aria-label="Scroll Tahapan Purchase Order"`, `min-w-[44rem] lg:min-w-0`, `flex-1 min-w-0 px-1 relative z-10`, `aria-label="Tahapan Purchase Order"`.
- **Transaction Status Pills**:
  - `draft`: `bg-gray-100 border-gray-200 text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300`
  - `pending` / `diajukan`: `bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20`
  - `in_transit` / `diproses` / `dikirim_ke_gudang` / `dikirim`: `bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20`
  - `received` / `diterima` / `parsial`: `bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-500/10 dark:text-teal-400 dark:border-teal-500/20`
  - `completed` / `selesai` / `disetujui` / `aktif`: `bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20`
  - `cancelled` / `ditolak` / `dibatalkan` / `nonaktif`: `bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20`
- **Brand Logo Badge (`auth/login.blade.php` & Sidebar Header)**:
  ```html
  <div class="relative size-[56px] rounded-[12px] bg-gradient-to-b from-[#292929] from-[17%] to-[#0a0a0a] border border-[#212121] shadow-[0_2px_6px_rgba(0,0,0,0.12),inset_0_1px_1px_0_rgba(255,255,255,0.15),inset_0_-1px_2px_0_rgba(255,255,255,0.35)] flex items-center justify-center shrink-0">
    <div class="w-[42px] h-[24px] shrink-0 pointer-events-none">
      <img src="{{ asset('Logo HS - black.png') }}" alt="Heaven Scent" class="w-full h-full object-contain brightness-0 invert pointer-events-none select-none" />
    </div>
  </div>
  ```
  > *Anti-pattern*: Jangan pasang `border-white/30` di wrapper terluar yang transparan karena background halaman akan tembus dan membentuk ring putih di sekeliling badge. Gradien dan border gelap `#212121` harus berada langsung di elemen container utama.

---

## 6. Test Contract & Safety Invariants (Wajib Dipertahankan)

1. **`tests/Feature/ViewRenderingSafetyTest.php`**:
   - Seluruh GET routes bebas `LazyLoadingViolationException` (`Model::preventLazyLoading(true)`).
   - `login` dan `transfers/surat-jalan` tidak mengandung `hs-app-shell`.
   - `dashboard` tidak mengandung `max-w-7xl`, `👋`, atau `bg-gradient-to-r`, serta mempertahankan `lg:grid-cols-5`.
   - `purchasing.create` tidak mengandung `max-w-6xl`, `min-w-[320px]`, `min-width: 320px`, dan mempertahankan `min-w-64 sm:min-w-80`.
   - `batches.create`, `stok.opname`, `produk.create` tidak mengandung `max-w-4xl`; `gudang.create`, `supplier.create`, `uom.create` tidak mengandung `max-w-3xl`; `users.create` tidak mengandung `max-w-5xl`.
   - `users.index` mempertahankan `usersTable" class="w-full text-xs [&_thead_th]:!px-2.5 [&_tbody_td]:!px-2.5"`.
   - `UserController::data()` mempertahankan `<div class="flex flex-wrap items-center gap-1">` dan `inline-flex items-center whitespace-nowrap` pada `roles_label`.
   - Route `purchasing.index`, `stok.index`, `stok.log-pergerakan`, `analisa.index`, `users.index`, `reports.defect`, `reports.lead-time` bebas class kaku `min-w-[120px..280px]`.
   - `reports.defect` mempertahankan `lg:grid-cols-6`; `analisa.index` mempertahankan `sm:grid-cols-4`.
   - *Catatan perubahan kontrak yang disengaja di Task 2:* Sidebar shell berubah dari `lg:w-56` ke `lg:w-[264px]` / `lg:w-[80px]` sesuai Blueprint Section 4B — update assertion di `test_proportional_fluid_scaling_shell_and_css_rules` bersamaan dengan Task 2.
2. **`tests/Feature/AnalisaModalCurrentReferenceTest.php` & `tests/Feature/AnalisaSupplierRelationTest.php`**:
   - Mempertahankan seluruh binding Alpine (`manualLokalForm.*`, `manualImporForm.*`, `addSkuForm.*`, `stageForm.*`, `copyAutoStages()`, `openStageModal`, `openManualLokalModal`, `openManualImporModal`, `supplierFilter`) dan label tombol tepat (`Gunakan Nilai Sistem`, `Gunakan Nilai Sistem (PO Otomatis)`, `Generate Analisa`, `Checking (Generate Ulang)`, `Finalisasi ke Riwayat`, `Form Input Tahap Lead Time`, `Form Input Bahan Baku Lokal`, `Form Input Bahan Baku Impor`).
3. **`tests/Feature/BladeUiPrd24AlignmentTest.php` & `tests/Feature/StokEnhancementTest.php` & `tests/Feature/DashboardTest.php`**:
   - Mempertahankan seluruh teks judul, label stok, badge stage 8-tahap, dan `role="region"` aksesibilitas tabel.
