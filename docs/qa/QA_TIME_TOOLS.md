# Panduan QA Time Tools (Simulasi Lead Time Purchasing)

Alat bantu untuk menguji fitur yang bergantung pada waktu di modul Purchasing (durasi stage, lead time supplier, status "Terlambat", jatuh tempo termin) tanpa menunggu hari sungguhan. Hanya aktif bila `APP_ENV=local`.

Spec: `docs/superpowers/specs/2026-11-26-qa-lead-time-clock-design.md`
Kode: `config/qa.php`, `App\Services\Qa\*`, `App\Http\Middleware\ApplyQaClock`, `App\Http\Controllers\QaToolsController`.

## Mengaktifkan

1. Pastikan `.env` berisi `APP_ENV=local` (tidak perlu flag lain).
2. Login. Menu user sekarang punya link **QA Clock** (`/qa/clock`).

Alat aktif hanya bila `APP_ENV=local`. Di environment lain (staging, production) route `/qa/*` tidak ada dan banner/field QA tidak muncul. Semua user yang login bisa memakainya. Untuk berganti role, pakai dropdown **Ganti Peran (Dev)** di menu user, atau login sebagai user seed: `manager@heavenscent.id`, `purchasing@heavenscent.id`, `gudang@heavenscent.id` (password default `password`, lihat `database/seeders/UserSeeder.php`).

## Komponen

- **QA Clock (global)**: offset menit terhadap jam asli, berlaku untuk web, artisan, queue, dan scheduler. Banner merah `QA CLOCK: ... (+X hari Y jam)` tampil di semua halaman saat offset aktif. Aksi di `/qa/clock`: Majukan (hari/jam), Set waktu (harus >= jam asli), Reset. Offset tidak bisa negatif.
- **Mundurkan PO** (halaman detail PO `/purchasing/{po}`, panel "QA: Mundurkan waktu PO" di bawah stepper, field **Hari** dan **Jam**, tombol **Mundurkan**): menggeser semua `waktu_mulai`/`waktu_selesai` stage PO itu (dan `created_at` PO) ke belakang, durasi tetap. Hanya untuk PO yang belum selesai/dibatalkan.
- **Durasi stage (QA)**: field `Hari` dan `Jam` (`qa_durasi_hari`, `qa_durasi_jam`) di setiap form penyelesaian stage. `waktu_selesai = waktu_mulai stage aktif + durasi`, lalu diproses lewat `PurchasingStageService` asli, jadi ringkasan lead time dihitung seperti produksi. Bila hasilnya melewati "sekarang", muncul error agar Anda **Mundurkan PO** atau majukan QA Clock. Stage 4 (pembayaran) diselesaikan lewat tombol **Konfirmasi Dibayarkan** atau **Lanjut tanpa DP**, keduanya punya field durasi. Form ini hanya muncul untuk user dengan permission `purchasing.pay`; **Konfirmasi Dibayarkan** juga baru muncul setelah ada pembayaran tercatat (catat dulu di panel **Status Pembayaran (AP)**), sedangkan **Lanjut tanpa DP** tidak butuh pembayaran.

## Resep 1: PO dengan lead time 10 hari

1. Sebagai purchasing, buat PO untuk supplier uji (satu supplier, agar ringkasannya mudah dibaca).
2. Buka detail PO, panel **Mundurkan PO**: isi 11 hari, klik **Mundurkan**. PO sekarang "dimulai" 11 hari lalu.
3. Selesaikan ke-8 stage berurutan (perencanaan, konfirmasi supplier, approval, pembayaran, PO terbit, pengiriman, unloading, input sistem) dengan mengisi **Durasi stage (QA)** di tiap form. Total durasi harus 10 hari, misalnya 1 hari + 1 + 1 + 1 + 1 + 3 + 1 + 1. Ganti role sesuai stage (purchasing, manager untuk approval, gudang untuk unloading/input sistem) lewat **Ganti Peran (Dev)**, atau login sebagai `purchasing@`, `manager@`, `gudang.id` (password `password`). Stage pembayaran: pilih **Lanjut tanpa DP** (atau catat pembayaran lalu **Konfirmasi Dibayarkan**) sebagai user dengan permission `purchasing.pay`. Total durasi tidak boleh melebihi 11 hari agar tidak melewati sekarang.
4. Setelah stage terakhir selesai, buka **Laporan Lead Time Supplier** (`/laporan/lead-time`, route `reports.lead-time`). Ringkasan supplier ada di kartu "Level-1: Ringkasan Performa Lead Time Supplier & 9-Stage Breakdown" (rata-rata per supplier dan per stage), dan baris PO-nya di kartu "Buku Audit Transaksi Lead Time Purchase Order". Pastikan lead time PO = 10 hari dan rata-rata/ringkasan supplier ikut terbarui.

## Resep 2: Status "Terlambat" (SLA)

Status terlambat dihitung dari benchmark rata-rata durasi stage supplier, jadi supplier harus sudah punya riwayat PO selesai (jalankan Resep 1 dulu, atau pakai supplier yang sudah punya riwayat).

1. Buat PO baru untuk supplier yang sudah punya riwayat.
2. Buka `/qa/clock`, majukan QA Clock melewati rata-rata durasi stage tersebut (mis. +30 hari).
3. Buka daftar/dashboard Purchasing dan detail PO: stage aktif tampil merah **Terlambat** (stepper dan status PO).
4. Reset clock di akhir pengujian (lihat Gotcha).

## Resep 3: Jatuh tempo termin

1. Buat PO dengan skema bayar termin (punya jadwal termin dengan `tanggal_tempo`) atau tempo, lalu jalankan sampai tahap pembayaran. Untuk termin, daftar muncul di panel **Status Pembayaran (AP)** sebagai "Jadwal Termin Tagihan".
2. Majukan QA Clock melewati `tanggal_tempo` (`/qa/clock`). Jatuh tempo dihitung "sebelum hari ini", jadi majukan minimal sampai hari berikutnya.
3. Jalankan `php artisan purchasing:check-overdue` (`refreshAllOverdueStatuses`, ikut memakai QA Clock). Yang berubah:
   - Setiap termin non-lunas dengan `tanggal_tempo` < hari ini (PO belum dibatalkan): kolom `purchase_order_termins.status` menjadi `overdue` (kecuali sudah lunas), dan badge termin di panel berubah merah.
   - `purchase_orders.status_pembayaran` hanya disinkronkan untuk PO yang punya termin non-lunas dengan `tanggal_tempo` terlewat, atau PO (belum lunas/batal) dengan `eta` < hari ini. PO tempo dengan `tanggal_tempo` terlewat tetapi ETA kosong atau masih di masa depan, dan tanpa termin overdue, **tidak** ikut diperbarui oleh command ini. Untuk PO yang terpilih, kolom menjadi `overdue` bila ada termin overdue, atau (PO tempo) `tanggal_tempo` terlewat (ETA dipakai hanya bila tanpa `tanggal_tempo`), atau (skema lain) ETA terlewat; PO yang sudah lunas/dibatalkan tidak diubah. Agar PO tempo uji ikut berubah, isi ETA yang sudah lewat.
   - Output command: "Pemeriksaan selesai. N termin/PO jatuh tempo telah diperiksa dan disinkronkan." (N hanya menghitung termin).
4. Cek detail PO: badge Status Pembayaran (AP) merah (overdue). Untuk PO tempo, "Deadline Tempo" dan "Jatuh Tempo Pembayaran" juga merah dengan tanda "Overdue" meski command belum dijalankan, karena tampilan menghitungnya langsung dari tanggal; command yang menyimpan status ke database.

## Gotcha

- Sesi yang ditulis saat jam dimajukan membawa `last_activity` di masa depan. Ini tidak berbahaya, dan tidak ada user yang ter-logout karena kedaluwarsa sesi dicek memakai jam asli.
- Kolom dengan default DB `useCurrent()` (`supplier_lt_history.dicatat_pada`, `supplier_lt_summary.diperbarui_pada`, dll.) memakai jam DB asli bila kode tidak mengisinya.
- Reset clock setelah membuat data saat jam maju: stage yang `waktu_mulai`-nya di masa depan akan gagal diselesaikan sampai jam asli menyusul.
- Worker queue (`queue:work`) mengikuti QA Clock pada setiap polling, jadi tidak perlu di-restart setelah offset diubah. `schedule:work` yang dijalankan saat offset > 0 bisa membeku di jam saat dimulai; jalankan `php artisan schedule:run` atau command-nya langsung (mis. `php artisan purchasing:check-overdue`).
- Fungsi PHP native `date()`/`strtotime()` tidak mengikuti QA Clock (hanya `now()`/Carbon yang mengikuti). Contoh: default `tanggal` di form buat PO (purchasing/create), default dan atribut `min` tanggal di detail PO (`tanggal_terima`, `tgl_kirim`, `tanggal_bayar`, `tanggal_tempo`), dan prefix nomor REQ/TRF. Saat jam dimajukan, form tersebut memakai tanggal asli, jadi isi tanggalnya secara manual.
