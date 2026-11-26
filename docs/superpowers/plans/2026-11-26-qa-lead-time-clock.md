# QA Lead Time Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Model eksekusi:** Sonnet 5.5 (`model: "sonnet"` untuk setiap subagent implementer). Reviewer akhir boleh model terkuat.

**Goal:** Menyediakan QA Clock global (offset berjalan) dan pengatur waktu per PO (mundurkan PO, selesaikan stage dengan durasi X) di environment non-production, agar QA bisa menguji lead time dan SLA purchasing tanpa menunggu hari nyata.

**Architecture:** `App\Services\Qa\QaClock` menyimpan offset menit di file JSON dan menerapkannya via `Carbon::setTestNow(jamAsli + offset)` dari middleware web, boot console, dan `Queue::before`. Durasi per stage di-resolve oleh `QaStageTimeResolver` lalu tetap diteruskan ke `PurchasingStageService::completeStageN` asli (ringkasan LT dihitung oleh `recordCompletedPo` seperti produksi). Penggeseran waktu PO oleh `QaPoTimeShiftService` lewat query builder.

**Tech Stack:** Laravel 13, PHP 8.3, Carbon, Blade + Alpine + Tailwind 4, PHPUnit (SQLite in-memory).

**Spec:** [docs/superpowers/specs/2026-11-26-qa-lead-time-clock-design.md](../specs/2026-11-26-qa-lead-time-clock-design.md)

## Global Constraints

- Fitur aktif hanya jika `config('qa.time_tools') === true` **dan** `! app()->isProduction()`. Default `QA_TIME_TOOLS=false`.
- Route QA didaftarkan bila `! app()->isProduction()`; setiap action controller `abort_unless($clock->enabled(), 404)`.
- Akses: semua user login (tanpa permission baru; jangan sentuh `PermissionCatalogService`/`PermissionSeeder`).
- Offset QA Clock selalu `>= 0` (menit, integer).
- File offset: `config('qa.clock_path')`, default `storage_path('framework/qa-clock.json')`, isi `{"offset_minutes": int, "updated_at": ISO-8601, "updated_by": string|null}`.
- Penggeseran waktu dihitung di PHP (Carbon), bukan fungsi tanggal SQL — harus jalan di SQLite dan PostgreSQL.
- `Model::preventLazyLoading` aktif di test: eager-load setiap relasi yang disentuh.
- Error input/bisnis dilempar sebagai `Illuminate\Validation\ValidationException` (pola service stage).
- Teks UI Bahasa Indonesia; identifier mengikuti gaya proyek (`durasi`, `mundurkan`).
- Setiap test class QA: `tearDown()` memanggil `\Illuminate\Support\Carbon::setTestNow()` **sebelum** `parent::tearDown()` dan menghapus file clock test.
- Sebelum menjelajah kode: `graphify query "<pertanyaan>"` dulu (aturan CLAUDE.md); setelah selesai: `graphify update .`.

## Review Focus

1. **Test-now bocor antar test** → suite lama (`PurchasingNineStageAndLeadTimeTest`, dll.) harus tetap hijau; dicek di Task 5 dengan `composer test` penuh.
2. **File clock hilang / JSON rusak / offset negatif di file** → `offsetMinutes()` mengembalikan `0`, tanpa exception (test di Task 1).
3. **`QA_TIME_TOOLS=true` tapi env production** → `enabled()` false (test Task 1); route 404 dan banner/field tidak dirender saat nonaktif (test Task 2, 3, 4); smoke manual `APP_ENV=production` di Task 5.
4. **Durasi kosong vs `0` vs negatif** → kosong = perilaku lama (`waktu_selesai` request atau `now()`); `0` = `waktu_selesai = waktu_mulai`; negatif = error validasi (test di Task 3).
5. **Durasi membuat `waktu_selesai` > now** → error `qa_durasi` dengan pesan yang menyebut "Mundurkan PO" / "QA Clock", stage tidak berubah (test di Task 3).

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `config/qa.php` (baru) | flag `time_tools`, `clock_path` |
| `app/Services/Qa/QaClock.php` (baru) | baca/tulis offset, `enabled()`, `realNow()`, `apply()` |
| `app/Http/Middleware/ApplyQaClock.php` (baru) | panggil `QaClock::apply()` tiap request web |
| `bootstrap/app.php` | append middleware ke grup `web` |
| `app/Providers/AppServiceProvider.php` | singleton `QaClock`; apply saat console + `Queue::before` |
| `app/Http/Controllers/QaToolsController.php` (baru) | halaman & aksi QA Clock, aksi mundurkan PO |
| `resources/views/qa/clock.blade.php` (baru) | halaman kontrol QA Clock |
| `resources/views/components/qa-clock-banner.blade.php` (baru) | banner global |
| `resources/views/components/app-layout.blade.php` | render banner + link di menu dev |
| `app/Services/Qa/QaStageTimeResolver.php` (baru) | hitung `waktu_selesai` dari durasi QA |
| `app/Http/Controllers/PurchasingStageController.php` | pakai resolver di 4 action |
| `resources/views/components/qa-stage-duration.blade.php` (baru) | input durasi di form stage |
| `app/Services/Qa/QaPoTimeShiftService.php` (baru) | geser waktu stage & `created_at` PO |
| `resources/views/components/qa-po-time-panel.blade.php` (baru) | panel mundurkan PO di detail PO |
| `resources/views/purchasing/show.blade.php` | sisipkan komponen durasi + panel |
| `routes/web.php` | route `qa.*` |
| `.env.example`, `docs/qa/QA_TIME_TOOLS.md` (baru) | flag + panduan QA |
| `tests/Feature/Qa/*.php` (baru) | test per task |

---

### Task 1: Config + `QaClock` service + penerapan clock

**Files:**
- Create: `config/qa.php`, `app/Services/Qa/QaClock.php`, `app/Http/Middleware/ApplyQaClock.php`
- Modify: `bootstrap/app.php` (blok `withMiddleware`), `app/Providers/AppServiceProvider.php` (`register` + `boot`), `.env.example` (tambah `QA_TIME_TOOLS=false` setelah blok APP_*)
- Test: `tests/Feature/Qa/QaClockTest.php`

**Interfaces:**
- Produces (`App\Services\Qa\QaClock`, singleton):
  - `enabled(): bool`
  - `offsetMinutes(): int` — 0 bila file tidak ada/rusak/negatif
  - `setOffsetMinutes(int $minutes, ?string $actor = null): void` — `InvalidArgumentException` bila `< 0`
  - `advance(int $minutes, ?string $actor = null): void` — tambah ke offset sekarang
  - `reset(?string $actor = null): void` — offset 0
  - `realNow(): \Illuminate\Support\Carbon` — jam asli, tidak terpengaruh test-now (bangun dari `new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone')))`)
  - `fakeNow(): \Illuminate\Support\Carbon` — `realNow()->addMinutes(offsetMinutes())`
  - `apply(): void` — jika `enabled()` dan offset > 0: `Carbon::setTestNow(fakeNow())` dan set flag statis `$applied = true`; jika offset 0 dan `$applied`: `Carbon::setTestNow()` lalu `$applied = false`; jika tidak enabled: tidak melakukan apa-apa
- Produces: middleware `App\Http\Middleware\ApplyQaClock` (`handle(Request, Closure)`: `app(QaClock::class)->apply()` lalu `$next`)

- [ ] **Step 1: Tulis test yang gagal**

```php
// tests/Feature/Qa/QaClockTest.php  (extends Tests\TestCase, tanpa seed)
// setUp: config(['qa.time_tools' => true, 'qa.clock_path' => storage_path('framework/testing/qa-clock-'.uniqid().'.json')]);
// tearDown: Carbon::setTestNow(); @unlink(config('qa.clock_path')); parent::tearDown();

test_disabled_when_flag_false()                 // config qa.time_tools=false → enabled() false
test_disabled_in_production_even_if_flag_true() // $this->app['env']='production' → enabled() false (kembalikan 'testing' setelahnya)
test_offset_defaults_to_zero_without_file()     // offsetMinutes() === 0
test_offset_zero_when_file_corrupt()            // file_put_contents(path, '{nope') → 0
test_offset_zero_when_file_negative()           // file berisi {"offset_minutes":-30} → 0
test_set_and_advance_persist_to_file()          // setOffsetMinutes(60,'qa@x'); advance(30) → offsetMinutes()===90; json file updated_by==='qa@x'
test_set_negative_throws()                      // expectException(InvalidArgumentException); setOffsetMinutes(-1)
test_apply_shifts_now_by_offset()               // setOffsetMinutes(3*1440); apply(); abs(now()->diffInMinutes(realNow()->addDays(3))) <= 1
test_apply_after_reset_clears_test_now()        // set 60, apply(), reset(), apply() → Carbon::hasTestNow() === false
test_apply_noop_when_disabled()                 // config false, file 60 → apply(); Carbon::hasTestNow() === false
test_web_request_runs_under_fake_clock()        // set 3 hari; actingAs(manager seeded? → pakai User::factory atau seed) GET route('dashboard'); lalu assert now()->toDateString() === realNow()->addDays(3)->toDateString()
```

Untuk test terakhir, panggil `$this->seed()` di test itu saja dan login `manager@heavenscent.id`.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Qa/QaClockTest.php`
Expected: FAIL — `Class "App\Services\Qa\QaClock" not found`

- [ ] **Step 3: Implementasi**

- `config/qa.php`: `'time_tools' => (bool) env('QA_TIME_TOOLS', false)`, `'clock_path' => env('QA_CLOCK_PATH', storage_path('framework/qa-clock.json'))`.
- `QaClock` sesuai Interfaces; tulis file dengan `File::put(..., json_encode(..., JSON_PRETTY_PRINT))`, buat direktori bila perlu; `updated_at` diisi `realNow()->toIso8601String()`.
- `bootstrap/app.php`: `$middleware->appendToGroup('web', \App\Http\Middleware\ApplyQaClock::class);`
- `AppServiceProvider::register`: `$this->app->singleton(QaClock::class)`. `boot`: jika `$this->app->runningInConsole()` → `app(QaClock::class)->apply()`; selalu daftarkan `Queue::before(fn () => app(QaClock::class)->apply())`.

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test tests/Feature/Qa/QaClockTest.php`
Expected: PASS (11 tests)

- [ ] **Step 5: Commit**

```bash
git add config/qa.php app/Services/Qa/QaClock.php app/Http/Middleware/ApplyQaClock.php bootstrap/app.php app/Providers/AppServiceProvider.php .env.example tests/Feature/Qa/QaClockTest.php
git commit -m "feat(qa): tambah QA Clock offset global untuk environment non-production"
```

---

### Task 2: Halaman kontrol QA Clock + banner global

**Files:**
- Create: `app/Http/Controllers/QaToolsController.php`, `resources/views/qa/clock.blade.php`, `resources/views/components/qa-clock-banner.blade.php`
- Modify: `routes/web.php` (di dalam grup `Route::middleware('auth')`, dibungkus `if (! app()->isProduction())`), `resources/views/components/app-layout.blade.php` (banner tepat di dalam `<main>` sebelum `{{ $slot }}`, ±baris 636; link "QA Clock" di blok `@if(app()->environment('local', 'testing'))` menu user ±baris 522 — ubah kondisi link ke `app(\App\Services\Qa\QaClock::class)->enabled()`)
- Test: `tests/Feature/Qa/QaClockControllerTest.php`

**Interfaces:**
- Consumes: `QaClock` (Task 1)
- Produces routes (nama → method → action):
  - `qa.clock` GET `qa/clock` → `QaToolsController@clock`
  - `qa.clock.advance` POST `qa/clock/advance` → `@advanceClock` (input `hari` int 0–365, `jam` int 0–999, total > 0)
  - `qa.clock.set` POST `qa/clock/set` → `@setClock` (input `waktu` date, harus `>= realNow()`; offset = selisih menit dibulatkan ke bawah)
  - `qa.clock.reset` POST `qa/clock/reset` → `@resetClock`
- Semua aksi: `abort_unless($clock->enabled(), 404)`; actor = `auth()->user()->email`; redirect `route('qa.clock')` dengan flash `success`.
- Banner (`<x-qa-clock-banner />`): tidak merender apa pun bila `! enabled()`. Offset 0 → strip netral "Mode QA aktif · jam asli". Offset > 0 → strip merah, teks persis: `QA CLOCK: {fakeNow format 'd M Y H:i'} (+{X} hari {Y} jam)` dan link "Atur" ke `qa.clock`.
- Halaman `qa/clock`: tampilkan jam asli, jam QA, offset; form maju (hari, jam), form set waktu (`datetime-local`), tombol reset dengan konfirmasi berteks: `Data yang dibuat saat jam dimajukan akan tampak berada di masa depan setelah reset.`; daftar singkat gotcha dari spec (sesi kedaluwarsa, kolom `useCurrent()`). Mode Operate: pakai komponen yang ada (`x-card`, `x-button`, `x-input`, `x-callout`), bukan desain baru.

- [ ] **Step 1: Tulis test yang gagal**

```php
// tests/Feature/Qa/QaClockControllerTest.php — setUp: seed(), config qa.* seperti Task 1, login manager@heavenscent.id
test_clock_page_404_when_disabled()        // config false → get(route('qa.clock'))->assertNotFound()
test_clock_page_renders_when_enabled()     // assertOk()->assertSee('QA Clock')
test_advance_adds_offset()                 // post advance ['hari'=>2,'jam'=>3] → offsetMinutes()===2*1440+180; assertRedirect(route('qa.clock'))
test_advance_rejects_zero_total()          // ['hari'=>0,'jam'=>0] → assertSessionHasErrors('hari'); offset tetap 0
test_advance_rejects_negative()            // ['hari'=>-1] → assertSessionHasErrors('hari')
test_set_rejects_past_time()               // ['waktu'=>realNow()->subHour()] → assertSessionHasErrors('waktu')
test_set_future_time_sets_offset()         // ['waktu'=>realNow()->addDays(5)->format('Y-m-d\TH:i')] → offset antara 5*1440-2 dan 5*1440
test_reset_sets_offset_zero()
test_banner_shows_fake_date_when_offset()  // setOffsetMinutes(3*1440) → get(route('dashboard'))->assertSee('QA CLOCK')->assertSee(realNow()->addDays(3)->format('d M Y'))
test_banner_hidden_when_disabled()         // config false → get dashboard → assertDontSee('QA CLOCK')->assertDontSee('Mode QA aktif')
test_any_logged_in_role_can_open_page()    // login gudang@heavenscent.id → assertOk()
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Qa/QaClockControllerTest.php`
Expected: FAIL — `Route [qa.clock] not defined`

- [ ] **Step 3: Implementasi** route, controller, view, banner, link menu sesuai Interfaces.

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test tests/Feature/Qa/QaClockControllerTest.php`
Expected: PASS (11 tests)

- [ ] **Step 5: Cek visual** — `composer dev`, set `QA_TIME_TOOLS=true` di `.env`, buka `/qa/clock`, majukan 2 hari: banner merah tampil di dashboard (light & dark), tidak menyebabkan scroll horizontal di lebar 375px.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/QaToolsController.php resources/views/qa resources/views/components/qa-clock-banner.blade.php resources/views/components/app-layout.blade.php routes/web.php tests/Feature/Qa/QaClockControllerTest.php
git commit -m "feat(qa): halaman kontrol QA Clock dan banner global"
```

---

### Task 3: Durasi QA saat menyelesaikan stage

**Files:**
- Create: `app/Services/Qa/QaStageTimeResolver.php`, `resources/views/components/qa-stage-duration.blade.php`
- Modify: `app/Http/Controllers/PurchasingStageController.php` (`completeStage` ±baris 37, `rejectStage2` ±126, `dibayarkan` ±142, `lanjutTempo` ±160), `resources/views/purchasing/show.blade.php` (setiap `<form>` ber-action `route('purchasing.stages.complete'|'stages.reject-stage2'|'stages.dibayarkan'|'stages.lanjut-tempo')` — temukan dengan `grep -n "route('purchasing.stages\." resources/views/purchasing/show.blade.php`; saat ini 10 form)
- Test: `tests/Feature/Qa/QaStageDurationTest.php`

**Interfaces:**
- Consumes: `QaClock::enabled()` (Task 1)
- Produces: `QaStageTimeResolver::resolve(Request $request, PurchaseOrder $po, string $stageKey): \Illuminate\Support\Carbon`
  - Jika `! enabled()` atau `qa_durasi_hari` dan `qa_durasi_jam` dua-duanya tidak `filled` → perilaku lama: `waktu_selesai` request di-parse, atau `now()`.
  - Selain itu: validasi `qa_durasi_hari` `nullable|integer|min:0|max:365`, `qa_durasi_jam` `nullable|integer|min:0|max:999`; ambil baris stage aktif (`$po->stages()->where('stage', $stageKey)->where('status', 'berjalan')->latest('putaran')->first()`); bila tidak ada → kembalikan `now()` (biarkan service melempar error urutan stage seperti biasa); hasil = `waktu_mulai + hari*1440 + jam*60` menit.
  - Bila hasil `> now()` → `ValidationException::withMessages(['qa_durasi' => 'Durasi QA melewati waktu sekarang. Mundurkan PO atau majukan QA Clock terlebih dahulu.'])`.
  - Stage key per action: `completeStage` → `$stage`; `rejectStage2` → `PoStage::STAGE_2`; `dibayarkan`/`lanjutTempo` → `PoStage::STAGE_4`.
- Komponen `<x-qa-stage-duration />`: kosong bila `! enabled()`; dua input number `qa_durasi_hari`, `qa_durasi_jam` (min 0), label "Durasi stage (QA)", hint "Kosongkan untuk memakai waktu sekarang", tampilkan `@error('qa_durasi')`.

- [ ] **Step 1: Tulis test yang gagal**

```php
// tests/Feature/Qa/QaStageDurationTest.php — setUp: seed(), config qa.* enabled, ambil user purchasing/manager/gudang,
// helper createSamplePo() disalin dari tests/Feature/PurchasingNineStageAndLeadTimeTest.php::createSamplePo (waktu mulai now()->subDays(10))
test_duration_sets_waktu_selesai_from_stage_start()
    // login purchasing; post(route('purchasing.stages.complete', [$po, PoStage::STAGE_1]), ['qa_durasi_hari'=>1,'qa_durasi_jam'=>6])
    // stage perencanaan: durasi_menit === 1800; waktu_selesai == waktu_mulai + 1800 menit; stage_aktif === STAGE_3
test_zero_duration_is_allowed()                    // ['qa_durasi_hari'=>0] → durasi_menit === 0
test_negative_duration_rejected()                  // ['qa_durasi_jam'=>-2] → assertSessionHasErrors('qa_durasi_jam'); stage_aktif tetap STAGE_1
test_duration_beyond_now_rejected_with_hint()      // ['qa_durasi_hari'=>11] → assertSessionHasErrors('qa_durasi'); pesan mengandung 'Mundurkan PO'; stage_aktif tetap STAGE_1
test_duration_ignored_when_disabled()              // config false; ['qa_durasi_hari'=>1] → durasi_menit ≈ 10 hari (>= 14399) karena memakai now()
test_reject_and_payment_routes_use_duration()      // jalankan s/d approval; post stages.reject-stage2 dengan alasan + qa_durasi_jam=5 → putaran 1 approval durasi_menit===300;
                                                   // lanjutkan ke pembayaran; post stages.lanjut-tempo qa_durasi_jam=2 → stage pembayaran durasi_menit===120
test_full_lifecycle_with_durations_records_lead_time()
    // selesaikan 8 stage via HTTP (role sesuai permission; data minimal untuk stage 6/7/8 meniru
    // test_tc01_happy_path_full_nine_stage_purchasing_lifecycle) masing-masing qa_durasi_jam = 12
    // → PoLeadTime::where('po_id',$po->id)->first()->total_lt_menit === 8*720; SupplierLtSummary supplier: jumlah_transaksi === 1
test_form_renders_duration_inputs_only_when_enabled()
    // get(route('purchasing.show',$po)) → assertSee('qa_durasi_hari', false); config false → assertDontSee('qa_durasi_hari', false)
```

Catatan untuk `test_full_lifecycle_*`: cek payload wajib stage 7 (`physical_check`) dan stage 8 (`no_resi`) dari `PurchasingStageController::completeStage` dan `completeStage7` di service sebelum menulis test.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Qa/QaStageDurationTest.php`
Expected: FAIL — durasi_menit tidak sesuai / class resolver tidak ada

- [ ] **Step 3: Implementasi** resolver, ganti 4 baris resolusi `$waktuSelesai` di controller dengan `$this->qaStageTimeResolver->resolve(...)` (inject via constructor), buat komponen, sisipkan `<x-qa-stage-duration />` sebelum tombol submit di 10 form.

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test tests/Feature/Qa/QaStageDurationTest.php && php artisan test tests/Feature/PurchasingNineStageAndLeadTimeTest.php`
Expected: PASS semua

- [ ] **Step 5: Commit**

```bash
git add app/Services/Qa/QaStageTimeResolver.php app/Http/Controllers/PurchasingStageController.php resources/views/components/qa-stage-duration.blade.php resources/views/purchasing/show.blade.php tests/Feature/Qa/QaStageDurationTest.php
git commit -m "feat(qa): input durasi stage untuk simulasi lead time PO"
```

---

### Task 4: Mundurkan waktu PO

**Files:**
- Create: `app/Services/Qa/QaPoTimeShiftService.php`, `resources/views/components/qa-po-time-panel.blade.php`
- Modify: `app/Http/Controllers/QaToolsController.php` (action `shiftPo`), `routes/web.php` (di blok QA Task 2), `resources/views/purchasing/show.blade.php` (panel tepat setelah `<x-purchasing-progress-stepper ... />`, ±baris 777)
- Test: `tests/Feature/Qa/QaPoTimeShiftTest.php`

**Interfaces:**
- Consumes: `QaClock::enabled()` (Task 1), `QaToolsController` (Task 2)
- Produces:
  - `QaPoTimeShiftService::shiftBack(PurchaseOrder $po, int $minutes, string $actor): int` — jumlah baris `po_stage` yang digeser.
    - `$minutes <= 0` → `ValidationException(['jam' => 'Total pergeseran harus lebih dari 0.'])`
    - `$po->status` `selesai`/`dibatalkan` → `ValidationException(['qa_shift' => 'PO yang sudah final tidak dapat digeser.'])`
    - Dalam `DB::transaction`: untuk setiap `PoStage` milik PO, `PoStage::whereKey($id)->update(...)` dengan `waktu_mulai - menit`, `waktu_selesai - menit` (bila tidak null); `PurchaseOrder::whereKey($po->id)->update(['created_at' => created_at - menit])`. Tanpa event model/audit. Tulis `Log::info('QA shift PO', [...])`.
  - Route `qa.purchasing.shift` POST `qa/purchasing/{purchaseOrder}/shift` → `QaToolsController@shiftPo` (input `hari` 0–365, `jam` 0–999); redirect `back()` dengan `success` "Waktu PO dimundurkan {X} hari {Y} jam."
  - Panel `<x-qa-po-time-panel :po="$po" />`: kosong bila `! enabled()` atau PO final; tampilkan stage aktif, `waktu_mulai`-nya dan lama berjalan; form hari/jam → `qa.purchasing.shift`. Pakai `$po->stages` yang sudah di-eager-load oleh `PurchasingController@show` (`'stages.picUser:id,name'`); jangan query ulang.

- [ ] **Step 1: Tulis test yang gagal**

```php
// tests/Feature/Qa/QaPoTimeShiftTest.php — setUp seperti Task 3 (seed, config enabled, createSamplePo)
test_shift_moves_all_stage_times_back_and_keeps_durations()
    // selesaikan STAGE_1 via service dengan waktu_selesai = mulai+2 jam; catat waktu & durasi
    // post(route('qa.purchasing.shift',$po), ['hari'=>3,'jam'=>0]) → assertRedirect()
    // setiap po_stage: waktu_mulai lama - 4320 menit; waktu_selesai (non-null) lama - 4320; durasi_menit tidak berubah; created_at PO mundur 4320
test_shift_then_duration_completes_stage_in_past()
    // PO baru mulai now(); shift 2 hari; complete STAGE_1 dengan qa_durasi_hari=1 → sukses, durasi_menit === 1440
test_shift_rejected_for_final_po()                 // set status 'selesai' → assertSessionHasErrors('qa_shift'); waktu tidak berubah
test_shift_rejected_for_zero()                     // ['hari'=>0,'jam'=>0] → assertSessionHasErrors('jam')
test_shift_route_404_when_disabled()
test_shift_does_not_create_audit_entries()         // OperationalAudit::count() sebelum == sesudah
test_panel_visible_only_when_enabled_and_not_final() // get purchasing.show → assertSee(route('qa.purchasing.shift',$po)); config false → assertDontSee
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/Qa/QaPoTimeShiftTest.php`
Expected: FAIL — `Route [qa.purchasing.shift] not defined`

- [ ] **Step 3: Implementasi** service, action, route, panel sesuai Interfaces.

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test tests/Feature/Qa/QaPoTimeShiftTest.php`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Qa/QaPoTimeShiftService.php app/Http/Controllers/QaToolsController.php routes/web.php resources/views/components/qa-po-time-panel.blade.php resources/views/purchasing/show.blade.php tests/Feature/Qa/QaPoTimeShiftTest.php
git commit -m "feat(qa): panel mundurkan waktu PO untuk simulasi lead time"
```

---

### Task 5: Panduan QA, verifikasi penuh, update graph

**Files:**
- Create: `docs/qa/QA_TIME_TOOLS.md`
- Modify: `CLAUDE.md` (satu baris di bagian Purchasing: "QA time tools: `config/qa.php`, `App\Services\Qa\*`, hanya non-production; lihat docs/qa/QA_TIME_TOOLS.md")

**Interfaces:**
- Consumes: semua route/komponen Task 1–4.

- [ ] **Step 1: Tulis panduan** berisi: cara mengaktifkan (`QA_TIME_TOOLS=true`, `php artisan config:clear`), tiga resep —
  1. *PO dengan LT 10 hari*: buat PO → Mundurkan PO 11 hari → selesaikan 8 stage dengan durasi masing-masing → cek Laporan Lead Time & ringkasan supplier.
  2. *Status terlambat SLA*: buat PO → majukan QA Clock melewati rata-rata stage → cek badge "Terlambat SLA" di dashboard.
  3. *Jatuh tempo termin*: majukan QA Clock → `php artisan purchasing:check-overdue` → cek status overdue.
  — plus bagian "Gotcha" yang disalin dari spec (sesi kedaluwarsa, kolom `useCurrent()`, reset setelah data dibuat).

- [ ] **Step 2: Jalankan seluruh suite**

Run: `composer test`
Expected: semua test PASS, termasuk suite lama (bukti tidak ada test-now yang bocor)

- [ ] **Step 3: Style**

Run: `vendor/bin/pint --test app config tests/Feature/Qa`
Expected: tidak ada file yang perlu diperbaiki (jika ada, jalankan `vendor/bin/pint` pada file tersebut lalu ulangi)

- [ ] **Step 4: Smoke test manual** — `.env` `QA_TIME_TOOLS=true`, `composer dev`, jalankan resep 1 dari panduan dari awal sampai akhir sebagai purchasing → manager → gudang lewat role switcher. Lalu set `APP_ENV=production` sementara: `/qa/clock` → 404, banner hilang. Kembalikan `.env`.

- [ ] **Step 5: Update graph**

Run: `graphify update .`
Expected: selesai tanpa error

- [ ] **Step 6: Commit**

```bash
git add docs/qa/QA_TIME_TOOLS.md CLAUDE.md graphify-out
git commit -m "docs(qa): panduan QA time tools untuk simulasi lead time"
```
