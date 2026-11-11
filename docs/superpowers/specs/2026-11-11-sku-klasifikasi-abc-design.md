# Design Spec: Pemilihan & Penambahan SKU ke Klasifikasi ABC serta Pemilihan Profil Analisa (Impor / Lokal)

- **Tanggal:** 2026-11-11
- **Status:** Siap untuk Approval User (Builder Check selesai & semua edge-case telah diselesaikan)
- **Acuan Framework:** `.agent/enterprise-task-framework.md` (FASE 2) & `PRD_SC_Online_v2.4.md`

---

## 1. Intent & Why

Saat ini, penetapan Klasifikasi ABC (`wajib_a`, `a`, `b`, `c`, atau kelas kustom di tabel `klasifikasi_abc`) untuk suatu SKU hanya dapat dilakukan melalui modal **Edit Input** di tab **Bahan Baku Impor** pada halaman **Analisa Stok** (`AnalisaController::updateManualImpor()`), dan hanya untuk SKU yang sudah ada di tabel `analisa_impor`. Selain itu, tidak ada jalan cepat di halaman **Analisa Stok** untuk memasukkan SKU baru ke **Analisa Lokal** maupun **Analisa Impor** sambil menentukan profil dan Klasifikasi ABC-nya.

Terdapat pula celah integrasi dan isolasi di backend:
- Form **Master Produk** (`resources/views/produk/_form.blade.php`) dan `ProdukController::validated()` menyimpan `profil_analisa` sebagai `'lokal'` atau `'impor'`, sedangkan `AnalisaService::generateImpor()`, `compareSuppliersForProduct()`, dan `selectSupplierForProduct()` mengecek `'bahan_impor'`, sehingga SKU baru berprofil `'impor'` tidak otomatis terjaring saat **Generate Analisa Impor**.
- `AnalisaController::lokalData()`, `imporData()`, `finalisasi()`, `getBatasMinimumMap()`, dan `getItemPerluOrderSummary()` belum menyaring berdasarkan `produk.profil_analisa` dan `produk.tipe` aktif, sehingga perpindahan profil berisiko menimbulkan duplikasi baris di tab Lokal dan Impor.
- Route `klasifikasi-abc/*` di `routes/web.php` masih berada di dalam middleware `can:produk.view` padahal controllernya memakai `analisa.view` / `analisa.manage`.

Perubahan ini memungkinkan user **memilih dan menambahkan SKU ke Klasifikasi ABC serta menentukan masuk ke Analisa Impor atau Analisa Lokal** secara konsisten dari:
1. **Master Produk** (saat membuat/mengedit SKU + visibilitas di tabel produk).
2. **Master Klasifikasi ABC** (mengelola daftar SKU per kelas ABC secara langsung/massal).
3. **Analisa Stok** (menambahkan SKU ke **Analisa Impor** dengan Klasifikasi ABC atau ke **Analisa Lokal**, plus ubah cepat ABC di tab Impor).

---

## 2. Goals, Non-Goals, & Anti-Goals

### Goals
1. Menyediakan kolom master `produk.klasifikasi_abc_id` dengan **sinkronisasi dua arah** ke `analisa_impor` dan `analisa_impor_meta` melalui satu helper domain terpusat di `AnalisaService`.
2. Mendukung pelepasan SKU secara murni (`klasifikasi_abc_id = null` tanpa memindahkan SKU ke daftar Kelas `C` di Master Klasifikasi ABC).
3. Memberikan kebebasan kepada user untuk memilih SKU masuk ke **Analisa Impor** (dengan Klasifikasi ABC) atau **Analisa Lokal** dari Master Produk maupun modal **+ Tambah SKU** di Analisa Stok, dengan pembersihan & isolasi working table agar tidak muncul ganda.
4. Memperbaiki ketidakcocokan `'impor'` vs `'bahan_impor'` di `AnalisaService` secara backward-compatible.
5. Menyelaraskan RBAC route `klasifikasi-abc/*` dan sidebar ke `analisa.view` / `analisa.manage`.
6. Menjaga jejak audit (`AuditableMasterData`), `Model::preventLazyLoading(true)`, kompatibilitas PostgreSQL + SQLite, `php artisan test`, dan `npm run build`.

### Non-Goals
- Perhitungan otomatis kelas ABC (mis. Pareto `Out × Harga`); pemilihan kelas ABC tetap manual oleh user sesuai `PRD_SC_Online_v2.4.md` (Bab 6.6.1).
- Penerapan bobot buffer Klasifikasi ABC pada rumus **Bahan Baku Lokal** atau **Produk Jadi (Fulfillment)**.
- Perubahan rumus `ADU ETA`, `Buffer Days`, `Safety Stock`, `Target Stock`, `Proyeksi`, atau pembulatan `MOQ`.

### Anti-Goals
- Membuat baris `analisa_impor` kosong (`out = 0`, `stok = 0`) secara diam-diam saat user sekadar menyimpan SKU di Master Produk (agar tidak mengganggu auto-generate `if (! AnalisaImpor::exists())` di `AnalisaController::imporData()`).
- Mengizinkan produk bertipe `produk_jadi` memiliki `klasifikasi_abc_id` atau masuk ke `analisa_impor` / `analisa_lokal`.
- Membiarkan satu SKU tampil ganda di tab Bahan Baku Lokal dan Bahan Baku Impor setelah berpindah profil.

---

## 3. Constraints & Keputusan Desain

- **Database:** Produksi memakai **PostgreSQL** (`LOWER(...) LIKE`, subquery `whereIn` agar efisien), test memakai **SQLite `:memory:`**.
- **Strict Eloquent:** Test menggunakan `Model::preventLazyLoading(true)`. Seluruh relasi baru wajib di-eager load atau dihitung via `withCount` / subquery.
- **View Test Constraints:** `ViewRenderingSafetyTest` menguji `route('produk.create')` dengan `assertDontSee('max-w-4xl')`. Form produk tetap menggunakan layout standar tanpa class `max-w-4xl`.
- **Backward Compatibility:** Test eksisting (`AnalisaMultiSupplierLeadTimeTest`, `Prd24EdgeCaseAndBugAnalysisTest`) memakai `profil_analisa = 'bahan_impor'`, dan `AnalisaImporEngineTest::test_benchmark_botol_50ml_case_prd_v2_2()` membuat produk dengan `profil_analisa = null` lalu memanggil `analisa.update-manual-impor`. Oleh karena itu `AnalisaService` menerima `['impor', 'bahan_impor']` dan `updateManualImpor()` otomatis mempromosikan `profil_analisa` menjadi `'impor'` jika belum impor.
- **Keputusan Desain (Calls Made):**
  1. **Pelepasan ("Keluarkan") SKU dari Klasifikasi ABC:** Menyetel `produk.klasifikasi_abc_id = null`, `analisa_impor.klasifikasi_abc_id = null`, dan `analisa_impor_meta.klasifikasi_abc_id = null`. Pada `analisa_impor` yang sudah ada, kolom non-nullable `klasifikasi_abc` diisi `'c'` dan `tambahan_buffer_hari = 0` sebagai fallback internal, **tanpa** menautkannya ke record Kelas `C` di Master Klasifikasi ABC (sehingga SKU tidak masuk ke daftar Kelas `C` dan benar-benar terlepas).
  2. **Pemilihan Masuk ke Analisa Impor atau Lokal:** User dapat menentukan apakah SKU masuk ke `Impor` atau `Lokal` baik dari Master Produk maupun modal **+ Tambah SKU** di halaman Analisa Stok; sedangkan di modal **Kelola SKU** pada Master Klasifikasi ABC, memilih SKU yang berprofil `Lokal`/`Belum Diset` menampilkan peringatan bahwa profil SKU akan dialihkan ke `Impor`. Setiap perpindahan profil membersihkan baris working table profil lama (`AnalisaLokal`/`RekomendasiOrderLokal`/`LeadTimeLokal` vs `AnalisaImpor`/`LeadTimeImpor`) sekaligus mempertahankan isolasi query sehingga tidak terjadi double-counting.
  3. **Penyelarasan RBAC `klasifikasi-abc/*`:** Memindahkan group route `klasifikasi-abc.*` di `routes/web.php` ke middleware `can:analisa.view` (dengan mutasi `can:analisa.manage`), menu sidebar ke `@can('analisa.view')`, dan `$canManage = auth()->user()->can('analisa.manage')` di `KlasifikasiAbcController::data()`.

---

## 4. Approaches Considered & Decision

- **Opsi A (Dipilih & Disetujui):** Kolom nullable `produk.klasifikasi_abc_id` + satu helper sinkronisasi dua arah di `AnalisaService` ke `analisa_impor` & `analisa_impor_meta` + pembersihan/isolasi working table saat pindah profil.
- **Opsi B (Ditolak):** Menyimpan langsung ke `analisa_impor` tanpa kolom di `produk` (membuat baris kerja mentah sebelum generate dan tidak tercatat di audit `Produk`).
- **Opsi C (Ditolak):** Tabel pivot baru atau sinkronisasi satu arah (kompleksitas berlebih untuk relasi 1:1 dan rawan inkonsistensi antar layar).

---

## 5. Detailed Design

### 5.1 Database & Migration
Buat migrasi `database/migrations/2026_11_11_000001_add_klasifikasi_abc_id_to_produk_table.php`:
1. Tambah kolom `klasifikasi_abc_id` (`nullable`, `foreignId('klasifikasi_abc_id')->nullable()->after('profil_analisa')->constrained('klasifikasi_abc')->nullOnDelete()`) beserta index `idx_produk_klasifikasi_abc_id`.
2. Backfill idempoten untuk produk aktif/non-`produk_jadi` yang `klasifikasi_abc_id`-nya masih `NULL`:
   - Prioritas 1: `analisa_impor.klasifikasi_abc_id` (hanya jika tidak null).
   - Prioritas 2: `analisa_impor_meta.klasifikasi_abc_id` (hanya jika tidak null).
3. `down()`: cek `Schema::hasColumn('produk', 'klasifikasi_abc_id')`, drop foreign key, drop index jika ada, lalu drop column secara aman untuk SQLite dan PostgreSQL.

### 5.2 Models
- **`App\Models\Produk`:**
  - Tambah `'klasifikasi_abc_id'` pada `$fillable` dan `'klasifikasi_abc_id' => 'integer'` pada `$casts`.
  - Tambah relasi `klasifikasiAbc(): BelongsTo`.
- **`App\Models\KlasifikasiAbc`:**
  - Tambah relasi `produks(): HasMany` (`hasMany(Produk::class, 'klasifikasi_abc_id')`).

### 5.3 Domain Logic (`App\Services\AnalisaService`)
1. **Normalisasi Profil & Isolasi Query:**
   - Helper `public static function isImporProfile(?string $profilAnalisa): bool` → `in_array($profilAnalisa, ['impor', 'bahan_impor'], true)`.
   - Gunakan `['impor', 'bahan_impor']` di:
     - `generateImpor()` (saat `$produkId === null`: ambil produk aktif `tipe IN ('bahan', 'kemas')` dengan `profil_analisa IN ('impor', 'bahan_impor')`, atau legacy `profil_analisa IS NULL` yang sudah punya `analisaImpor` / `leadTimeImpor` / `analisaImporMeta` — mengecualikan yang secara eksplisit diset `'lokal'`).
     - `compareSuppliersForProduct()`, `selectSupplierForProduct()`, `refreshAffectedProductsFromPo()`.
   - Tambah filter defensif di `finalisasi()`, `getBatasMinimumMap()`, dan `getItemPerluOrderSummary()` agar baris Lokal hanya berasal dari produk aktif `tipe IN ('bahan', 'kemas')` dengan `profil_analisa = 'lokal'` / `NULL`, dan baris Impor hanya dari produk aktif `tipe IN ('bahan', 'kemas')` dengan `profil_analisa IN ('impor', 'bahan_impor')`.
2. **Resolusi ABC di `generateImpor()` (Mendukung Unassigned Murni):**
   - Untuk produk `$p`:
     1. Jika `$existingAnalisa` ada:
        - Gunakan `$existingAnalisa->klasifikasi_abc_id` (bila non-null) → lookup `KlasifikasiAbc::find(...)`.
        - Bila `$existingAnalisa->klasifikasi_abc_id` null, cek `$p->klasifikasi_abc_id` (jika user baru menugaskannya di Master Produk sebelum sinkronisasi) → jika masih null, pertahankan `klasifikasi_abc_id = null`, `klasifikasi_abc = 'c'`, `tambahan_buffer_hari = 0` (**jangan** mengisi ulang `klasifikasi_abc_id` ke ID record `'c'`).
     2. Jika `$existingAnalisa` belum ada (inisialisasi baru):
        - Cek `$p->klasifikasi_abc_id` → lalu `$meta?->klasifikasi_abc_id` → lalu jika `$meta?->klasifikasi_abc` terisi (non-empty), cocokkan kode di `KlasifikasiAbc` → jika semua kosong, simpan `klasifikasi_abc_id = null`, `klasifikasi_abc = 'c'`, `tambahan_buffer_hari = 0`.
3. **Pembersihan Working Table Saat Pindah Profil (`cleanupWorkingTablesForProfile`):**
   - `cleanupWorkingTablesForProfile(Produk $produk, ?string $targetProfil): void`
     - Jika `$produk->tipe === 'produk_jadi'` atau `! $produk->is_active`: hapus working rows `AnalisaLokal`, `RekomendasiOrderLokal`, `LeadTimeLokal`, `AnalisaImpor`, `LeadTimeImpor` milik `$produk->id`.
     - Jika `self::isImporProfile($targetProfil)`: hapus `AnalisaLokal`, `RekomendasiOrderLokal`, `LeadTimeLokal` milik `$produk->id`.
     - Jika `$targetProfil === 'lokal'` (atau `null` untuk bahan/kemas): hapus `AnalisaImpor`, `LeadTimeImpor` milik `$produk->id`.
   - *(Input historis seperti `AnalisaLokalInput`, `LeadTimeLokalStage`, `AnalisaImporMeta` tetap dipertahankan sehingga data historis input tidak hilang jika user mengembalikan profilnya).*
4. **Helper Sinkronisasi Terpusat:**
   - `syncProdukKlasifikasiAbc(Produk $produk, ?int $klasifikasiAbcId, array $options = []): void`
     - Options: `promote_to_impor` (bool, default `false`), `ensure_analisa_row` (bool, default `false`), `user_id` (?int).
     - Guard: jika `$produk->tipe === 'produk_jadi'`, tolak dengan `ValidationException` bila dipanggil dari flow assignment ABC/Analisa, atau paksa `null` saat dari Master Produk.
     - Di dalam `DB::transaction()`:
       - Jika `promote_to_impor = true` dan `! self::isImporProfile($produk->profil_analisa)`: set `$produk->profil_analisa = 'impor'` dan panggil `cleanupWorkingTablesForProfile($produk, 'impor')`.
       - Simpan `$produk->klasifikasi_abc_id = $klasifikasiAbcId` (`save()` agar `AuditableMasterData` mencatat perubahan).
       - Resolve `$abc = $klasifikasiAbcId ? KlasifikasiAbc::find($klasifikasiAbcId) : null`.
       - Jika `AnalisaImporMeta` ada untuk `$produk->id`, update `klasifikasi_abc_id = $abc?->id` dan `klasifikasi_abc = $abc?->kode ?? 'c'`.
       - Cek `$existingAnalisa = AnalisaImpor::where('produk_id', $produk->id)->first()`.
       - Jika `$existingAnalisa` ada: update `klasifikasi_abc_id = $abc?->id`, `klasifikasi_abc = $abc?->kode ?? 'c'`, `tambahan_buffer_hari = $abc?->tambahan_buffer_hari ?? 0`, lalu panggil `generateImpor($produk->id, $userId)`.
       - Else jika `ensure_analisa_row = true`: panggil `generateImpor($produk->id, $userId)`.
   - `assignKlasifikasiAbcToSkus(int $klasifikasiAbcId, array $produkIds, bool $ensureAnalisaRow = false, ?int $userId = null): int`
     - Jalankan `DB::transaction()`, filter produk `whereIn('id', $produkIds)->whereIn('tipe', ['bahan', 'kemas'])->where('is_active', true)`, panggil `syncProdukKlasifikasiAbc($p, $klasifikasiAbcId, ['promote_to_impor' => true, 'ensure_analisa_row' => $ensureAnalisaRow, 'user_id' => $userId])`.
   - `addSkuToAnalisa(Produk $produk, string $targetProfil, ?int $klasifikasiAbcId = null, array $initialParams = [], ?int $userId = null): void`
     - Guard: tolak bila `$produk->tipe === 'produk_jadi'` atau `! $produk->is_active`.
     - Jika `$targetProfil === 'lokal'`:
       - Set `$produk->profil_analisa = 'lokal'`, `$produk->klasifikasi_abc_id = null`, `save()`.
       - Panggil `cleanupWorkingTablesForProfile($produk, 'lokal')`.
       - Simpan `initialParams` opsional ke `AnalisaLokalInput` / `LeadTimeLokal` jika dikirim.
       - Panggil `generateLokal($produk->id, $userId)`.
     - Jika `$targetProfil === 'impor'`:
       - Set `LeadTimeImpor` / `AnalisaImpor` dengan `initialParams` opsional jika dikirim.
       - Panggil `syncProdukKlasifikasiAbc($produk, $klasifikasiAbcId, ['promote_to_impor' => true, 'ensure_analisa_row' => true, 'user_id' => $userId])`.

### 5.4 Controllers, Routes, & RBAC

#### A. Master Produk (`App\Http\Controllers\ProdukController`)
- `create()` & `edit()`: kirim `$klasifikasiAbcList` (aktif, urut `tambahan_buffer_hari` desc).
- `validated()`:
  - Tambah `'klasifikasi_abc_id' => ['nullable', 'integer', 'exists:klasifikasi_abc,id']`.
  - Jika `tipe === 'produk_jadi'` atau `! AnalisaService::isImporProfile($validated['profil_analisa'] ?? null)`, set `klasifikasi_abc_id = null`.
- `store()` & `update()`:
  - Injeksi `AnalisaService $analisaService`.
  - Setelah simpan `$produk`:
    - Panggil `$analisaService->cleanupWorkingTablesForProfile($produk, $produk->tipe === 'produk_jadi' || ! $produk->is_active ? null : $produk->profil_analisa)`.
    - Jika `AnalisaService::isImporProfile($produk->profil_analisa)` dan `$produk->tipe !== 'produk_jadi'`:
      - Jika `AnalisaImpor::where('produk_id', $produk->id)->exists()` atau `AnalisaImporMeta::where('produk_id', $produk->id)->exists()`, panggil `syncProdukKlasifikasiAbc($produk, $produk->klasifikasi_abc_id, ['ensure_analisa_row' => false])`.
- `data()`:
  - Eager-load `'klasifikasiAbc'` (`->with(['kategori', 'varian', 'uom', 'klasifikasiAbc'])`).
  - Tambah kolom `klasifikasi_abc_badge` yang menampilkan badge kode ABC jika profil impor & `klasifikasiAbc` terisi, atau `Lokal` / `—` yang informatif; daftarkan di `rawColumns`.

#### B. Master Klasifikasi ABC (`App\Http\Controllers\KlasifikasiAbcController` & `routes/web.php`)
- Routes di `routes/web.php` dipindah ke `Route::middleware('can:analisa.view')`:
  - `GET klasifikasi-abc/options` → `options`
  - `GET klasifikasi-abc/data` → `data`
  - `GET klasifikasi-abc/available-skus` → `availableSkus` (`authorize('analisa.view')`)
  - `GET klasifikasi-abc/{klasifikasiAbc}/skus` → `skus` (`authorize('analisa.view')`)
  - `POST klasifikasi-abc/{klasifikasiAbc}/skus` → `assignSkus` (`middleware('can:analisa.manage')`, `authorize('analisa.manage')`)
  - `DELETE klasifikasi-abc/{klasifikasiAbc}/skus/{produk}` → `unassignSku` (`middleware('can:analisa.manage')`, `authorize('analisa.manage')`)
  - `resource('klasifikasi-abc')` dengan mutasi `store/update/destroy` tetap `authorize('analisa.manage')`.
- `data()`:
  - Gunakan `$canManage = auth()->user()->can('analisa.manage')`.
  - Hitung `penggunaan_count` secara akurat tanpa duplikasi (`produks_count` untuk `is_active = true` dan `tipe IN ('bahan','kemas')`, ditambah count `analisa_impor` yang `produk.klasifikasi_abc_id` nya null pada legacy data) dan tambahkan tombol **"Kelola SKU"** (terlihat bagi `analisa.view`, tombol mutasi di modal mengikuti `analisa.manage`).
- `availableSkus(Request $request)`:
  - Query `Produk::query()->active()->whereIn('tipe', ['bahan', 'kemas'])->with('klasifikasiAbc:id,kode,nama,warna_badge')`, dukung search `q` (`LOWER(sku)` / `LOWER(nama)`), filter `profil` (`all` / `impor` / `lokal` / `unset`), dan opsional `exclude_klasifikasi_id`.
- `skus(KlasifikasiAbc $klasifikasiAbc)`:
  - Ambil SKU aktif `tipe IN ('bahan', 'kemas')` yang memiliki `produk.klasifikasi_abc_id = $klasifikasiAbc->id` (atau legacy `analisa_impor.klasifikasi_abc_id = $klasifikasiAbc->id` saat `produk.klasifikasi_abc_id` null).
- `assignSkus()`:
  - Validasi `produk_ids` (`required|array|min:1`), `produk_ids.*` (`integer|exists:produk,id`). Tolak bila ada `produk_jadi` atau nonaktif.
  - Panggil `$analisa->assignKlasifikasiAbcToSkus($klasifikasiAbc->id, $produkIds, ensureAnalisaRow: AnalisaImpor::exists(), userId: auth()->id())`.
- `unassignSku()`:
  - Panggil `$analisa->syncProdukKlasifikasiAbc($produk, null, ['ensure_analisa_row' => false, 'user_id' => auth()->id()])` sehingga `produk.klasifikasi_abc_id` dan `analisa_impor.klasifikasi_abc_id` dilepas (`null`).
- `destroy()`:
  - Cek `$klasifikasiAbc->produks()->exists()` di samping `analisaImpor()` & `analisaImporMeta()` agar kelas ABC yang masih dipakai di Master Produk tidak dapat dihapus.

#### C. Analisa Stok (`App\Http\Controllers\AnalisaController` & `routes/web.php`)
- Isolasi data list:
  - `lokalData()`: saring `RekomendasiOrderLokal` / `AnalisaLokal` / `LeadTimeLokal` / `stages` agar hanya mengikutsertakan produk aktif (`is_active = true`, `tipe IN ('bahan','kemas')`) dengan `profil_analisa = 'lokal'` atau `NULL`.
  - `imporData()`: saring `AnalisaImpor` agar hanya mengikutsertakan produk aktif (`is_active = true`, `tipe IN ('bahan','kemas')`) dengan `profil_analisa IN ('impor', 'bahan_impor')`.
  - `createPoForm()`: ikut menyaring rekomendasi aktif & sesuai profil agar tidak memprefill item stale.
- Endpoints baru di group `prefix('analisa')`:
  - `GET analisa/available-skus` → `availableSkus(Request $request)` (`can:analisa.view`):
    - Mengembalikan kandidat SKU aktif `tipe IN ('bahan', 'kemas')` beserta status saat ini (`profil_analisa`, `klasifikasi_abc_id`, `di_analisa_lokal`, `di_analisa_impor`, `harga_hpp`).
  - `POST analisa/add-sku` → `addSku(Request $request)` (`middleware('can:analisa.manage')`):
    - Validasi:
      - `produk_id`: `required|integer|exists:produk,id`
      - `target_profil`: `required|in:lokal,impor`
      - `klasifikasi_abc_id`: `nullable|required_if:target_profil,impor|integer|exists:klasifikasi_abc,id`
      - Parameter opsional: `out` (`nullable|numeric|min:0`), `lead_time_average` (`nullable|numeric|min:0`), `lead_time_max` (`nullable|numeric|min:0`), `terjual_rata_rata_4bulan` (`nullable|numeric|min:0`), `tambahan_buffer_hari` (`nullable|numeric|min:0`), `review_period` (`nullable|integer|min:1`), `harga_per_satuan` (`nullable|numeric|min:0`).
    - Panggil `$this->analisa->addSkuToAnalisa($produk, $data['target_profil'], $data['klasifikasi_abc_id'] ?? null, $initialParams, auth()->id())`.
  - `POST analisa/impor/quick-abc` (atau gunakan `updateManualImpor` dengan `klasifikasi_abc_id` opsional saja!) → Memakai `updateManualImpor()` yang sudah ada cukup dengan memastikan ketika hanya `produk_id` + `klasifikasi_abc_id` dikirim, `updateManualImpor()` menyinkronkan ke `produk` lewat `syncProdukKlasifikasiAbc()` tanpa menimpa field lain, serta otomatis mempromosikan `profil_analisa = 'impor'` jika masih `null`/non-impor!

### 5.5 UI / UX Changes
1. **Sidebar (`resources/views/components/app-layout.blade.php`):**
   - Bungkus item menu `Klasifikasi ABC` dengan `@can('analisa.view')` (dan pastikan tetap terlihat bila user punya akses analisa).
2. **Master Produk (`resources/views/produk/_form.blade.php` & `index.blade.php`):**
   - Pada `_form.blade.php`: tambah state Alpine `profilAnalisa` dan `selectedKlasifikasiAbcId`, ikat ke `profil_analisa`, dan tampilkan dropdown `Klasifikasi ABC (Impor)` saat `tipe !== 'produk_jadi' && (profilAnalisa === 'impor' || profilAnalisa === 'bahan_impor')`.
   - Pada `produk/index.blade.php`: tambah kolom `Klasifikasi ABC` (badge kelas ABC / `Lokal` / `—`).
3. **Master Klasifikasi ABC (`resources/views/klasifikasi-abc/index.blade.php`):**
   - Tambah tombol **"Kelola SKU"** di kolom aksi (serta klik pada badge jumlah SKU).
   - Modal **Kelola SKU Klasifikasi ABC**:
     - Panel **Tambah SKU**: search SKU/nama, filter profil (`Semua` / `Impor` / `Belum Diset` / `Lokal`), checkbox multi-pilih, badge status profil saat ini + peringatan jelas bila SKU berprofil `Lokal`/`Belum Diset` akan dipindahkan ke `Impor`, dan tombol **"Tambahkan SKU Terpilih"**.
     - Panel **SKU Terdaftar**: tabel daftar SKU pada kelas ini beserta tombol **"Keluarkan"** (hanya jika `@can('analisa.manage')`).
4. **Analisa Stok (`resources/views/analisa/index.blade.php`, `table-lokal.blade.php`, `table-impor.blade.php`, & `partials/modal-add-sku.blade.php`):**
   - Tombol **"+ Tambah SKU"** di action bar saat `tab === 'lokal' || tab === 'impor'` (`@can('analisa.manage')`).
   - Modal `modal-add-sku.blade.php`:
     - Pilihan SKU (pencarian SKU/nama + info profil saat ini).
     - Pilihan **Masuk ke Analisa**: radio/segmented toggle **`Bahan Baku Impor`** vs **`Bahan Baku Lokal`** (default sesuai tab aktif, dengan peringatan bila memindahkan profil dari tab seberang).
     - Jika **Bahan Baku Impor**: dropdown wajib **Klasifikasi ABC** + input opsional (`Out`, `LT Avg`, `LT Max`, `Review Period`, `Harga Satuan`).
     - Jika **Bahan Baku Lokal**: input opsional (`Terjual 4 Bulan`, `Review Period`, `Tambahan Buffer`, `Harga Satuan`).
   - Pada `table-impor.blade.php`:
     - Kolom `ABC` menampilkan badge yang jelas (membedakan kelas ter-assign vs `Default (C)` ketika `klasifikasi_abc_id === null`).
     - Bagi user `@can('analisa.manage')`, tersedia quick-select dropdown / modal edit cepat di kolom `ABC` yang langsung memanggil `update-manual-impor` untuk mengubah kelas ABC tanpa harus membuka modal penuh (sementara modal `Edit Input` juga tetap memiliki opsi kosong/Default atau kelas ABC).

---

## 6. What's Left to the Builder
- Penamaan variabel lokal Alpine.js, komponen badge/select ringkas, dan penyesuaian styling Tailwind agar konsisten dengan komponen `x-modal`, `x-card`, dan `x-button` yang ada.
- Penyusunan test suite komprehensif (feature tests untuk seluruh 3 titik masuk UI/API, sinkronisasi dua arah, pelepasan SKU murni, perpindahan profil Lokal ↔ Impor tanpa duplikasi, RBAC, `Model::preventLazyLoading`, serta verifikasi `php artisan test` & `npm run build`).
