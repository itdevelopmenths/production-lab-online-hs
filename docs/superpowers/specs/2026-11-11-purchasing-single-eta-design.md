# Design Spec: Penyatuan Input ETA Purchase Order (Single Source of Truth)

- **Tanggal:** 2026-11-11
- **Status:** Menunggu Approval User
- **Ukuran Perubahan:** Small Change
- **Pendekatan Terpilih:** Pendekatan 1 — Satu Kolom Acuan `purchase_orders.eta` dengan Pembaruan via Form Awal & Direct Edit (tanpa input ETA di Card Eksekusi Tahap Aktif)

---

## 1. Intent & Why

Saat ini alur Purchasing memiliki beberapa input tanggal ETA yang terpisah dan tidak sinkron:
- Form pembuatan/edit awal PO ([`create.blade.php`](resources/views/purchasing/create.blade.php#L146-L148), [`edit.blade.php`](resources/views/purchasing/edit.blade.php#L141-L142), [`analisa/create-po.blade.php`](resources/views/analisa/create-po.blade.php#L150-L151), dan aksi `Edit Tgl`) menulis ke `purchase_orders.eta`.
- Kartu header **Estimasi Tiba (ETA)** di [`show.blade.php`](resources/views/purchasing/show.blade.php#L620-L625) dan tabel list PO di [`index.blade.php`](resources/views/purchasing/index.blade.php#L571) membaca `purchase_orders.eta`.
- Sebaliknya, form Stage 3 (**Konfirmasi Supplier**) pada card **Eksekusi Tahap Aktif** dan modal **Direct Edit** di [`show.blade.php`](resources/views/purchasing/show.blade.php#L1227-L1229) menulis ke `purchase_orders.eta_supplier` (serta Stage 7 menulis ke `po_pengiriman.eta_tiba`).

Akibatnya, user melihat dua tempat input ETA dan ketika ETA diubah setelah PO dibuat, nilai **Estimasi Tiba (ETA)** pada header PO tidak ikut berubah.

---

## 2. Goals, Non-Goals, & Anti-Goals

### Goals
1. Menjadikan **`purchase_orders.eta`** sebagai satu-satunya sumber acuan ETA yang diinput di awal pembuatan PO dan ditampilkan di header/daftar PO.
2. Menghapus input ETA (`confirmation[eta_supplier]` dan keputusan kami: termasuk `shipping[eta_tiba]` di Stage 7) dari card **Eksekusi Tahap Aktif** di [`show.blade.php`](resources/views/purchasing/show.blade.php).
3. Mengarahkan modal **Direct Edit** (serta akses tombol edit cepat dari header PO jika relevan) agar langsung mengubah **`purchase_orders.eta`** melalui [`PurchaseOrderRevisionService::updateOperationalFields()`](app/Services/Purchasing/PurchaseOrderRevisionService.php#L28-L73), sehingga nilai pada kartu header **Estimasi Tiba (ETA)** langsung ikut berubah dan tercatat di `po_log_perubahan`.

### Non-Goals
- Tidak menghapus kolom database `purchase_orders.eta_supplier` maupun `po_pengiriman.eta_tiba` (menjaga kompatibilitas migrasi, data lama, dan test eksisting).
- Tidak mengubah logika agregasi `akan_datang` / `inbound_before_eta` di [`AnalisaService`](app/Services/AnalisaService.php) (di luar cakupan perubahan ini).

### Anti-Goals
- Tidak menciptakan kolom ETA baru atau menjaga sinkronisasi dua arah antar kolom terpisah yang rawan tidak konsisten.

---

## 3. Constraints

- [`PurchasingStageService::completeStage3()`](app/Services/Purchasing/PurchasingStageService.php#L283-L338) dan [`completeStage7()`](app/Services/Purchasing/PurchasingStageService.php#L531-L575) harus tetap aman apabila dipanggil secara terprogram (misalnya dari unit/feature test) tanpa key ETA di payload.
- Input `fields[eta]` pada `Direct Edit` wajib divalidasi (`nullable|date`) dan string kosong dikonversi menjadi `null` agar aman pada PostgreSQL (`DATE` column) serta dinormalisasi ke format `Y-m-d` saat perbandingan perubahan log agar tidak memicu log palsu di `po_log_perubahan`.
- Jika `skema_bayar === 'tempo'` dan `tanggal_tempo` kosong, pembaruan `eta` memicu `refreshStatusPembayaran()` agar konsisten dengan perilaku `PurchaseOrder::refreshStatusPembayaran()`.

---

## 4. Approaches Considered

1. **Pendekatan 1 — Satu Acuan `purchase_orders.eta` + Edit via Direct Edit / Header *(Dipilih User)***:
   Menghapus input ETA dari card **Eksekusi Tahap Aktif** (Stage 3 & Stage 7), serta mengubah modal **Direct Edit** agar langsung memperbarui `purchase_orders.eta` (plus tombol edit cepat di kartu header ETA yang membuka modal Direct Edit). *Trade-off:* perubahan ETA saat konfirmasi supplier atau pengiriman dilakukan lewat **Direct Edit**, bukan di form Eksekusi Tahap Aktif.
2. **Pendekatan 2 — Input ETA Tetap di Stage 3 / Stage 7 dan Disinkronkan ke `eta`**:
   Menjaga input di card tahap aktif lalu menimpa kolom `eta`. *Trade-off:* masih mempertahankan duplikasi input ETA di banyak tempat, bertentangan dengan permintaan penyederhanaan UI.

---

## 5. Specification & Calls Made

### Keputusan Desain (Our Calls)
- **Stage 7 (`shipping[eta_tiba]`):** ikut dihapus dari form card **Eksekusi Tahap Aktif** Stage 7 di [`show.blade.php`](resources/views/purchasing/show.blade.php#L1334-L1337) agar benar-benar hanya ada **satu** alur input/edit ETA di Purchasing (di awal pembuatan PO dan lewat **Direct Edit**).
- **Akses dari Kartu Header ETA:** kartu **Estimasi Tiba (ETA)** di [`show.blade.php`](resources/views/purchasing/show.blade.php#L620-L625) menampilkan `$po->eta`, dan bagi user berhak `purchasing.edit` pada PO aktif (`!in_array($po->status, ['selesai', 'dibatalkan'])`) disediakan tombol kecil untuk membuka modal **Direct Edit** langsung dari kartu header.

### Perubahan Teknis
1. **[`resources/views/purchasing/show.blade.php`](resources/views/purchasing/show.blade.php)**:
   - Hapus field `confirmation[eta_supplier]` pada form Stage 3 (`#L1227-L1229`).
   - Hapus field `shipping[eta_tiba]` pada form Stage 7 (`#L1334-L1337`).
   - Ubah modal **Direct Edit** (`#L1964-L1967`) dari `fields[eta_supplier]` menjadi `fields[eta]` dengan label `Estimasi Tiba (ETA)` dan value `$po->eta?->format('Y-m-d') ?? ''`.
   - Tambahkan tombol pemicu `@click="openDirectEditModal = true"` pada kartu metadata **Estimasi Tiba (ETA)** di header saat PO belum final & user memiliki izin `purchasing.edit`.
2. **[`app/Http/Controllers/PurchasingStageController.php`](app/Http/Controllers/PurchasingStageController.php#L157-L169)**:
   - Validasi `fields.eta` (`nullable|date`) pada `directEdit()` sebelum meneruskan ke `PurchaseOrderRevisionService`.
3. **[`app/Services/Purchasing/PurchaseOrderRevisionService.php`](app/Services/Purchasing/PurchaseOrderRevisionService.php#L28-L73)**:
   - Tambahkan `'eta'` ke `$allowedPoFields` (menggantikan `'eta_supplier'` untuk UI Direct Edit, atau tetap menyisakan kompatibilitas internal bila dipanggil langsung).
   - Normalisasi perbandingan nilai tanggal `eta` menggunakan `Y-m-d` dan konversi string kosong `''` ke `null`, lalu panggil `$po->refreshStatusPembayaran()` sebelum `$po->save()` jika `eta` berubah.
4. **Verifikasi ([`tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php`](tests/Feature/PurchaseOrderRevisionAndDirectEditTest.php))**:
   - Uji Direct Edit `fields[eta]` via HTTP & service memperbarui `purchase_orders.eta`, mencatat ke `po_log_perubahan`, dan tampil pada kartu header `Estimasi Tiba (ETA)` di `purchasing.show`.
   - Uji halaman `purchasing.show` pada Stage 3 dan Stage 7 tidak lagi merender input `confirmation[eta_supplier]`, `shipping[eta_tiba]`, maupun `fields[eta_supplier]`.
