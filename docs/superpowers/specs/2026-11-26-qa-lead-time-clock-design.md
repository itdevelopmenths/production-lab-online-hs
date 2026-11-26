# QA Lead Time Tools — Design

Tanggal: 2026-11-26 · Status: disetujui (A1, B1, C1)

## Intent

QA perlu menguji fitur yang bergantung pada waktu di modul Purchasing (durasi stage, lead time supplier/produk, status "terlambat" SLA, jatuh tempo termin) tanpa menunggu hari-hari sungguhan. Saat ini setiap PO dimulai di `now()`, UI tidak pernah mengirim `waktu_selesai`, dan `validateTimeWindow` menolak waktu masa depan, jadi PO dengan lead time 14 hari butuh 14 hari nyata.

## Goals

1. **QA Clock (global, berjalan)** — offset "+N menit" terhadap jam asli, berlaku di seluruh environment (web, artisan, queue, scheduler) lewat `Carbon::setTestNow(jamAsli + offset)`. Banner merah di semua halaman saat offset aktif.
2. **Pengatur waktu per PO** — dua aksi di halaman detail PO:
   - *Mundurkan PO N hari/jam*: geser semua `waktu_mulai`/`waktu_selesai` stage PO itu ke belakang (durasi tetap).
   - *Selesaikan stage dengan durasi X*: field opsional di setiap form penyelesaian stage; `waktu_selesai = waktu_mulai stage aktif + X`, lalu tetap lewat `PurchasingStageService::completeStageN` asli sehingga `recordCompletedPo` menghitung ringkasan LT seperti produksi.

## Non-goals

- Tidak mengubah rumus lead time, SLA, atau stage machine.
- Tidak menyediakan edit bebas baris `po_stage` atau rekalkulasi ringkasan LT.
- Tidak ada jam per sesi/per tester.
- Tidak menyentuh lead time manual di Analisa (lokal/impor).

## Anti-goals (gagal walau "jalan")

- Alat ini bisa aktif di production.
- Ringkasan `supplier_lt_*` tidak sinkron dengan `po_lead_time` karena jalur bypass service.
- Test suite yang ada jadi flaky karena test-now bocor antar test.

## Constraints

- Aktif hanya jika `config('qa.time_tools') === true` **dan** `! app()->isProduction()`. Default `false`. Route tetap didaftarkan di non-production (agar test bisa mengaktifkan via config), dan controller `abort(404)` bila nonaktif.
- Akses: siapa pun yang login di environment QA (QA berganti role lewat role switcher, dan field durasi harus muncul di form role mana pun yang menyelesaikan stage). *Penyimpangan dari usulan awal "manager saja" — call builder, lihat bawah.*
- Portabel SQLite (test) dan PostgreSQL; geser waktu dihitung di PHP, bukan SQL tanggal.
- `Model::preventLazyLoading` aktif — eager-load.
- Error bisnis lewat `ValidationException` (pola yang sudah dipakai service stage).

## Approaches (dipilih: A1, B1, C1)

| | Opsi | Trade-off | Pilihan |
|---|---|---|---|
| A | Offset berjalan vs jam beku | Beku = durasi berhenti, aneh untuk multi-stage | **Offset berjalan** |
| B | Satu jam per environment vs per sesi | Per sesi = data antar role tidak konsisten (durasi negatif) | **Per environment** |
| C | Lewat service asli vs edit `po_stage` langsung | Edit langsung = ringkasan LT tak sinkron, tak ada fungsi rekalkulasi | **Lewat service asli** |

## Keputusan desain

- **Penyimpanan offset**: file JSON `storage/framework/qa-clock.json` (`{"offset_minutes": int, "updated_at": iso, "updated_by": email}`). Bukan cache/DB karena cache database memakai TTL berbasis jam yang ikut digeser.
- **Offset hanya ≥ 0.** Aksi: maju +hari/+jam, set ke tanggal-waktu tertentu (harus ≥ jam asli), reset ke 0 (dengan peringatan bahwa data yang dibuat saat jam maju akan tampak di masa depan).
- **Penerapan**: middleware `ApplyQaClock` di grup `web` (append), `AppServiceProvider::boot` saat `runningInConsole()`, dan `Queue::before`. Jam asli diambil dengan `Carbon::setTestNow()` (reset) sebelum menambah offset, supaya tidak menumpuk.
- **Durasi per stage**: input `qa_durasi_hari` + `qa_durasi_jam` (Blade component `<x-qa-stage-duration />` disisipkan ke form stage 1, 2 approve, 2 reject, 3, 4 dibayarkan, 4 tempo, 5, 6, 7, 8). Resolver `QaStageTimeResolver` dipanggil dari `PurchasingStageController` menggantikan resolusi `waktu_selesai` lokal. Jika hasil > `now()` → `ValidationException` dengan pesan jelas ("mundurkan PO atau majukan QA Clock").
- **Mundurkan PO**: `QaPoTimeShiftService::shiftBack(PO, menit)` — hanya untuk PO berstatus bukan `selesai`/`dibatalkan`; geser `po_stage.waktu_mulai`, `waktu_selesai` (non-null), dan `purchase_orders.created_at`. Tidak menggeser `tanggal` PO, pembayaran, atau pengiriman. Tulis via query builder (tanpa event audit) dan catat satu baris `Log::info`.

## Gotchas yang diketahui (didokumentasikan, tidak diperbaiki)

- Memajukan QA Clock melewati `SESSION_LIFETIME` membuat sesi lama kedaluwarsa → semua user login ulang sekali.
- Kolom dengan default DB `useCurrent()` (`supplier_lt_history.dicatat_pada`, `supplier_lt_summary.diperbarui_pada`, dll.) memakai jam DB asli bila kode tidak mengisinya.
- Reset clock setelah membuat data saat jam maju → stage yang `waktu_mulai`-nya di masa depan akan gagal diselesaikan sampai jam asli menyusul.

## Call yang dibuat builder (cek saat membaca)

1. Akses semua user login (bukan manager saja) — karena QA berganti role.
2. Penyimpanan di file, bukan cache/DB.
3. Offset tidak boleh negatif.
4. Mundurkan PO hanya untuk PO yang belum final.
