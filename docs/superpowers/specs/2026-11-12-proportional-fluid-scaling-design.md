# Design Doc: Proportional Fluid Scaling & Laptop Viewport Normalization

**Date:** 2026-11-12  
**Status:** Draft — Pending Builder Check & User Approval  
**Scope:** Global UI Shell (`app.css`, `app-layout`, shared Blade components) dan halaman operasional padat untuk menyelaraskan proporsi tampilan Desktop (`1920px+`) dengan Laptop 13–14 inci (`1280px–1536px` CSS viewport).

---

## 1. Latar Belakang & Diagnosis Akar Masalah

Pengguna melaporkan bahwa tampilan Blade di monitor desktop rapi secara tata letak dan proporsi, tetapi saat dibuka di laptop 13–14 inci (meski sama-sama beraspek rasio `16:9`), antar-komponen terasa saling berdempetan (*cramped*) dan kurang rapi.

Audit pada codebase (`resources/css/app.css` dan `resources/views/**`) menemukan empat akar masalah teknis:

1. **Rasio Layar Sama (`16:9`) ≠ Lebar Viewport CSS Sama:** Monitor desktop `1920×1080` pada OS scaling `100%` memberi viewport CSS `1920px`. Laptop 13–14 inci di Windows umumnya memakai OS display scaling `125%–150%` (menjadi `1536px` atau `1280px` CSS width) atau resolusi `1366px`.
2. **Satu Bucket Breakpoint Desktop/Laptop + Overhead Sidebar Lebar:** Breakpoint Tailwind `sm:` (`640px`) dan `lg:` (`1024px`) sudah aktif di `1280px` maupun `1920px`. Di `app-layout.blade.php`, sidebar terbentang masih menggunakan `lg:w-64` (`256px`) ditambah padding `<main>` (`48px`), menyisakan area konten efektif hanya `~976px` di layar `1280px` (40% lebih sempit dibanding `~1616px` di desktop `1920px`), sementara grid `lg:grid-cols-5` (`dashboard.blade.php`), `lg:grid-cols-6` (`reports/defect.blade.php`), `sm:grid-cols-5` (`purchasing/show.blade.php`), `sm:grid-cols-4` (`analisa/index.blade.php`), dan `lg:grid-cols-3` split panel semuanya aktif bersamaan.
3. **Skala `:root` Statis (`16px`):** Seluruh utility `rem` Tailwind v4 (font, padding, gap, tinggi tombol/kartu, radius, ikon) memakai `1rem = 16px` tetap pada viewport `1024px–1920px`, tidak ikut mengecil proporsional.
4. **Hambatan Tetap (`px`) & Non-Wrapping Layout:**
   - Input cari DataTables di `resources/css/app.css` memaksa `min-w-[200px] sm:min-w-[240px]`.
   - Select filter di `purchasing/index.blade.php`, `stok/index.blade.php`, `analisa/index.blade.php`, `stok/ledger.blade.php`, `stok/pergerakan-log.blade.php`, `users/index.blade.php`, `reports/defect.blade.php`, dan `reports/lead-time.blade.php` memakai `min-w-[120px..320px]`.
   - Header actions di `dashboard.blade.php` menggunakan `flex items-center gap-2` tanpa `flex-wrap`, `purchasing-progress-stepper.blade.php` melepas `min-w-[760px]` terlalu dini pada `sm:` (`640px`), dan `stat-box.blade.php` tidak mengawal teks nilai panjang agar tidak berbenturan dengan ikon.

---

## 2. Tujuan, Non-Tujuan, dan Batasan

### Goals
- **Jumlah kolom per baris tetap sama seperti desktop** pada viewport laptop–desktop (`>= 1024px`, misal 5 KPI card per baris di Dashboard, 5 metadata card + split 2:1 di Detail PO, 6 KPI card di Report Defect).
- **Skala elemen UI ikut mengecil secara proporsional** di layar `1280px–1536px` dibanding `1920px+` tanpa membuat teks utama/tabel menjadi sulit dibaca.
- **Menghilangkan komponen yang saling berdempetan / menabrak** pada header, toolbar filter, DataTables header/footer, 9-stage stepper, kartu KPI, dan dropdown autocomplete.
- **Mempertahankan seluruh kontrak DOM & pengujian** (`ViewRenderingSafetyTest.php`, `BladeUiPrd24AlignmentTest.php`, ID DataTables/Alpine, `Model::preventLazyLoading(true)`).

### Non-Goals
- Tidak mengubah perilaku layout mobile/tablet kecil (`< 1024px`) — layar sempit tetap menggunakan skala dasar `100%` (`1rem = browser default`) dan pola responsive stack bawaan.
- Tidak mengubah tata letak atau ukuran cetak kertas dokumen (`transfers/surat-jalan.blade.php` dan `@media print`).
- Tidak mengubah halaman autentikasi mandiri (`auth/login.blade.php`).
- Tidak mengubah logika bisnis backend, controller, atau query DataTables.

### Anti-Goals & Constraints
- **Dilarang memakai CSS `zoom` atau `transform: scale()`:** Merusak `position: sticky` sidebar/header, koordinat dropdown autocomplete, dan posisi/backdrop `<dialog>` serta modal Alpine.
- **Kepatuhan Aksesibilitas Zoom (WCAG 1.4.4):** Rumus skala `font-size` wajib memadukan unit font-relatif (`rem`) dan viewport-relatif (`vw`) dalam `clamp()` dengan rasio `max / min <= 2.5x`, serta tidak mematikan zoom browser (`Ctrl++`).
- **Tetap mempertahankan rule `max-w-none`:** Tidak mengembalikan wrapper `max-w-4xl`..`max-w-7xl` yang dilarang oleh `ViewRenderingSafetyTest.php` (hanya `modal.blade.php`, cetak `surat-jalan`, `login`, dan input cari `max-w-sm` yang diizinkan memakai `max-w-*`).

---

## 3. Pendekatan yang Dipertimbangkan & Keputusan Arsitektur

1. **Pendekatan 1 — Fluid Root Scale Saja (`html` `clamp()`):** Cepat, tetapi kontrol `min-w-[...px]`, flex non-wrap, dan sidebar `w-64` tetap menyebabkan komponen dempet di `1280px`.
2. **Pendekatan 2 — Container Query & `cqi` Per Komponen Saja:** Memerlukan penulisan ulang besar-besaran di puluhan file Blade dan berisiko membuat skala visual antar-panel di satu halaman tidak seragam.
3. **Pendekatan 3 (Dipilih) — Bounded Fluid Root Scale (`>= 1024px`) + Normalisasi Shell, Filter & Panel:** Memadukan skala proporsional global berbasis `rem + vw` untuk shell terautentikasi dengan perampingan sidebar dan konversi kontrol/toolbar kaku agar skala mengecil mulus tanpa titik tabrakan.

---

## 4. Rancangan Detail Perubahan

### A. Bounded Proportional Root Scale (`resources/css/app.css` & `app-layout.blade.php`)
1. Tandai root HTML shell aplikasi pada [`app-layout.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/app-layout.blade.php) dengan class penanda `hs-app-shell` (`<html lang="id" class="h-full hs-app-shell bg-[oklch(0.985_0_0)]">`), sehingga dokumen cetak (`surat-jalan.blade.php`) dan `login.blade.php` tidak ikut terpengaruh.
2. Di [`resources/css/app.css`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/css/app.css), definisikan skala root proporsional khusus layar laptop–desktop (`@media screen and (min-width: 64rem)`):
   ```css
   @media screen and (min-width: 64rem) {
       html.hs-app-shell {
           font-size: clamp(0.8125rem, calc(0.5rem + 0.4167vw), 1rem);
       }
   }

   @media print {
       html.hs-app-shell {
           font-size: 100%;
       }
   }
   ```
   **Dampak terhitung (pada browser default `16px`):**
   - `< 1024px` (mobile/tablet & saat zoom tinggi): `16px` (`100%`).
   - `1280px` (laptop 13–14" @ 150% DPI): `13.33px` (`83.3%`).
   - `1366px` (laptop 14" HD): `13.69px` (`85.6%`).
   - `1536px` (laptop 14" @ 125% DPI): `14.40px` (`90.0%`).
   - `>= 1920px` (desktop monitor `1080p+`): `16.00px` (`100%`).
   - Rasio `max / min = 1.0 / 0.8125 = 1.23x` (aman untuk WCAG 1.4.4 zoom 200%).
3. **Legibility Floor untuk `text-xs` (`--text-xs`):** Agar teks tabel padat (`text-xs`) tidak turun di bawah batas nyaman di `1280px` (`~10px`), atur `--text-xs: max(0.75rem, 10.5px);` (atau di verifikasi build Tailwind v4 bila didukung LightningCSS) sehingga pada `1280px` tetap minimal `10.5px` yang tajam sementara pada `1920px` dan saat zoom kembali mengikuti `0.75rem` (`12px+`). *Builder dapat menyesuaikan ke `10.5px` atau membiarkan `10px` bila `--text-xs` memengaruhi proporsi tombol `xs`, berdasarkan pemeriksaan hasil build Tailwind v4.*

### B. App Shell & Komponen Inti (`app-layout`, `page-header`, `stat-box`, `card`, `purchasing-progress-stepper`)
1. **Sidebar Desktop ([`app-layout.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/app-layout.blade.php)):**
   - Ubah lebar sidebar expanded dari `lg:w-64` menjadi `lg:w-56` (`14rem`: `~186.7px` pada `1280px`, `~224px` pada `1920px`) sambil mempertahankan drawer mobile `w-64`. Ini menghemat ~69px ruang horizontal di laptop `1280px` sehingga kanvas konten efektif mencapai `~1053px` (`79rem`).
   - Pastikan `<body class="... flex flex-col">` dan container utama memiliki `w-full min-w-0` agar tidak memicu horizontal overflow saat konten bersanding dengan sidebar sticky.
   - Rapikan padding horizontal `<main>` menjadi `px-4 py-4 sm:px-5 lg:px-6 lg:py-4`.
2. **Page Header ([`page-header.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/page-header.blade.php)):**
   - Pastikan blok judul memakai `min-w-0 flex-1` dan wrapper kanan (`breadcrumbs` + `actions` + `backUrl`) menggunakan `flex flex-wrap items-center sm:justify-end gap-x-3 gap-y-2`, serta sub-wrapper tombol aksi memakai `flex flex-wrap items-center gap-2` agar pada halaman padat (seperti `purchasing/show` dan `batches/show`) badge dan tombol tidak mendesak judul.
3. **Stat Box ([`stat-box.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/stat-box.blade.php)) & Card Header ([`card.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/card.blade.php)):**
   - Pada `stat-box.blade.php`: tambahkan `flex-1 min-w-0` pada wrapper teks kiri dan gunakan `text-xl xl:text-2xl` (atau `text-2xl` dengan `break-words leading-tight`) agar angka besar maupun label teks seperti nama gudang pada `batches/show` tidak bertabrakan dengan badge/ikon.
   - Pada `card.blade.php`: pastikan blok judul memiliki `min-w-0 flex-1` dan slot `tools` memakai `flex flex-wrap items-center gap-2 shrink-0`.
4. **9-Stage Progress Stepper ([`purchasing-progress-stepper.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/components/purchasing-progress-stepper.blade.php)):**
   - Ubah `min-w-[760px] sm:min-w-0` menjadi `min-w-[44rem] lg:min-w-0`: pada layar laptop/desktop (`>= 1024px`) seluruh 9 tahap tetap tampil penuh dalam 1 baris tanpa scrollbar dan tanpa saling bertindihan, sedangkan pada layar sempit `< 1024px` (`640px–1023px`) track tidak dipaksa menyempit sampai tabrakan.
   - Tambahkan `min-w-0 px-1` pada setiap `<li>` dan izinkan judul tahap membungkus rapi (`text-[11px] leading-tight text-balance`) daripada terpotong paksa dengan `max-w-[85px]`.

### C. Normalisasi DataTables, Filter Toolbar, dan Grid Split Padat
1. **DataTables Global ([`resources/css/app.css`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/css/app.css)):**
   - Tambahkan `flex-wrap` pada `.dt-layout-header` dan `.dt-layout-footer` agar length selector & search input tidak pernah saling berdesakan di area sempit.
   - Ubah `.dataTables_wrapper .dataTables_filter input` dari `min-w-[200px] sm:min-w-[240px]` menjadi `w-full sm:w-52 min-w-0` (berbasis `rem` sehingga ikut mengecil proporsional di laptop).
2. **Header Action & Filter Toolbar Across Views:**
   - [`dashboard.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/dashboard.blade.php#L4): ubah wrapper actions menjadi `flex flex-wrap items-center justify-end gap-2`, pertahankan `lg:grid-cols-5` dan `lg:grid-cols-2`.
   - Konversi selektif `min-w-[...px]` kaku menjadi ukuran proporsional berbasis `rem` + `max-w-full` pada:
     - [`purchasing/index.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/purchasing/index.blade.php): filter `status`, `stage`, `kategori`, `AP status`, `AP skema` (`min-w-36 sm:min-w-44 max-w-full`).
     - [`stok/index.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/index.blade.php), [`stok/ledger.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/ledger.blade.php), [`stok/pergerakan-log.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/stok/pergerakan-log.blade.php).
     - [`analisa/index.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/analisa/index.blade.php): `min-w-0 flex-1` pada toolbar kiri & `w-full sm:w-64` untuk kotak pencarian.
     - [`users/index.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/users/index.blade.php), [`reports/defect.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/reports/defect.blade.php), [`reports/lead-time.blade.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/resources/views/reports/lead-time.blade.php).
     - Dropdown autocomplete produk (`analisa/create-po`, `batches/create`, `bom/edit`, `bom/import`, `purchasing/create`, `purchasing/edit`, `requests/create`, `requests/edit`, `transfers/create`): konversi `min-w-[320px]` menjadi `min-w-64 sm:min-w-80 max-w-[calc(100vw-2rem)]`.
3. **Panel Split `lg:grid-cols-3` (`purchasing/show.blade.php`, `batches/show.blade.php`):**
   - Pastikan kedua kolom anak (`lg:col-span-2` dan kolom kanan) memiliki `min-w-0`, serta ubah lebar `th` bertipe `min-w-[180px..220px]` ke `min-w-36` / `min-w-44` dengan `gap-4` agar panel 2/3 tetap lega pada `1280px`.

### D. Apa yang Diserahkan ke Builder
- Audit menyeluruh seluruh view Blade (`resources/views/**/*.blade.php`) jika ada flex toolbar lain tanpa `flex-wrap` atau `min-w-[...px]` tersisa yang masih berisiko menabrak.
- Pembuatan test verifikasi CSS/view rendering (`ViewRenderingSafetyTest.php` / test khusus) dan build Vite (`npm run build`) untuk memastikan tidak ada sintaks CSS/Tailwind v4 yang ditolak.
- Uji visual multi-viewport terukur di browser (`1920×1080`, `1536×864`, `1366×768`, `1280×720`, dan `390×844`) pada halaman utama (`dashboard`, `purchasing.index`, `purchasing.show`, `stok.index`, `analisa.index`, `reports.defect`).

