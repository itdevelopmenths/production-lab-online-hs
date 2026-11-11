# Design Spec: Pemisahan Permission Bahan Baku & Produk Jadi pada Modul Operasional

- **Tanggal:** 2026-11-11
- **Status:** Siap untuk Approval User (Builder Check & Klarifikasi Selesai)
- **Pendekatan Terpilih:** Pendekatan A (Permission Granular per Aksi & Kategori Barang)

---

## 1. Intent & Why

Saat ini sistem otorisasi Request & Transfer masih bergantung pada alias legacy `rt.*` (`rt.view`, `rt.create`, `rt.ship`, `rt.receive`, `rt.cancel`) dan pengecekan nama role statis (`operasional`, `fulfillment`, `gudang`):

1. **Kebocoran izin Request -> Transfer melalui `rt.create`:**
   - Rute `requests.*`, `transfers.*`, dan `batches.kirim` di `routes/web.php`, serta tombol di `requests/index`, `requests/show`, `transfers/index`, dan `batches/show` seluruhnya memeriksa `rt.create`.
   - `PermissionSeeder` memberi role `operasional` permission `request.create` sekaligus `rt.create`.
   - `AppServiceProvider::boot()` menjembatani `rt.create` <-> `transfer.create` dan `request.create`.
   - Akibatnya, role `operasional` saat ini dapat membuat Transfer untuk **Request Bahan Baku** yang sudah disetujui (`pemenuhan_request_bahan`), membuat transfer manual Bahan Baku, dan mengirim transfer Bahan Baku — padahal seharusnya role `operasional` hanya berhak membuat **Request Bahan Baku** (dan menerima barang masuk ke gudang operasional), tidak membuat/mengirim transfer Bahan Baku.
2. **Tidak ada pemisahan kategori barang (`bahan` vs `produk_jadi`):**
   - Jika `rt.create` dicabut begitu saja dari role `operasional`, user Operasional juga akan kehilangan kemampuan mengirim/mentransfer **Produk Jadi** (seperti `Kirim ke Fulfillment` / `Buat Transfer Produk Jadi` dari Batch Produksi yang selesai).
   - Selain itu, `RequestService::validateCreatorRole()` dan `TransferController::show()` masih memeriksa nama role secara hardcoded (`in_array('operasional', $roleNames)`), sehingga role kustom yang dibuat melalui **Peran & Wewenang** (`/roles/create`, `/roles/{id}/edit`) tidak bisa mewarisi perilaku ini secara fleksibel.

Tujuan fitur ini adalah menyediakan permission terpisah antara **Bahan Baku (& Kemasan)** dan **Produk Jadi** pada modul **Operasional & Logistik** sehingga seluruh role (bawaan maupun kustom) dapat dikustomisasi di **Peran & Wewenang**, dengan konfigurasi bawaan role **Operasional**:
- **Bahan Baku:** boleh **Request** (`request.bahan.create`), **tidak** boleh membuat/mengirim transfer Bahan Baku (`transfer.bahan.create` / `transfer.bahan.ship`), tetapi tetap boleh **menerima** transfer Bahan Baku masuk di gudang operasional (`transfer.bahan.receive`).
- **Produk Jadi:** boleh membuat dan mengirim transfer **Produk Jadi** (`transfer.produk_jadi.create`, `transfer.produk_jadi.ship`), termasuk pengiriman hasil batch produksi ke Fulfillment.

---

## 2. Goals, Non-Goals, & Anti-Goals

### Goals
1. Menambahkan permission granular untuk **Bahan Baku** dan **Produk Jadi** di `PermissionCatalogService` (domain **Operasional & Logistik**), agar muncul dan dapat dikustomisasi per checkbox di UI `/roles/create` & `/roles/{id}/edit`.
2. Menyediakan migrasi idempoten serta pembaruan `PermissionSeeder` agar permission baru dan pemetaan default role `operasional`, `gudang`, `fulfillment`, dan `manager` otomatis tersinkron baik pada DB baru maupun DB produksi eksisting.
3. Menghilangkan kebocoran izin `request.create` -> `transfer.create` dengan memisahkan pengecekan rute, controller, dan Blade view dari `rt.create` ke permission `request.*` / `transfer.*` yang spesifik serta menghapus ketergantungan nama role statis pada alur Request & Transfer.
4. Menegakkan validasi kategori produk & jenis transaksi pada setiap titik masuk (`RequestController`, `RequestService`, `TransferController`, `BatchController::kirim`, UI `requests/*`, `transfers/*`, `batches/show`):
   - **Operasional (default):** dapat Request Bahan Baku, ditolak (`403`) saat mencoba membuat/mengirim Transfer Bahan Baku (termasuk menyusupkan item `bahan`/`kemas`/`bahan_baku` ke dalam transfer jenis Produk Jadi/Antar Fulfillment), tetap dapat **konfirmasi terima** (`receive`) transfer Bahan Baku yang dikirim Gudang Pusat, dan dapat **membuat + mengirim** Transfer Produk Jadi.
5. Menjaga kompatibilitas penuh dengan `manager` global bypass, role `gudang`, role `fulfillment`, test domain `TransferService`, serta alias backward-compatibility untuk role/test yang hanya memegang `rt.*` atau `request.create`/`transfer.create` global.

### Non-Goals
- Mengubah pembatasan akses lokasi gudang (`LocationScopeService` / `warehouse_access_type`).
- Menambah otorisasi berbasis kategori item ke `TransferService` secara paksa (karena `TransferService` dipanggil oleh unit/feature test domain yang menguji invarian stok/QC langsung dengan user fixture tanpa HTTP auth; penegakan hak akses user dilakukan di `InventoryTransferAuthorizationService`, controller, Form/Blade, dan route middleware).
- Memecah permission modul **Purchasing**, **Master Data**, **Batch Produksi**, atau **Analisa** per tipe produk.

### Anti-Goals
- Mengandalkan pengecekan nama role (`hasRole('operasional')`, `hasRole('gudang')`, `hasRole('fulfillment')`) di `RequestController`, `TransferController`, `BatchController`, atau `RequestService`.
- Memberi role `operasional` permission global `request.create`, `transfer.create`, `transfer.ship`, `transfer.receive`, atau `rt.create` di `PermissionSeeder` — karena permission global tersebut mencakup semua kategori (`bahan` dan `produk_jadi`) dan akan menggagalkan kustomisasi terpisah di halaman Edit Role.
- Hanya menyembunyikan tombol di UI Blade tanpa blokir backend `403` pada endpoint HTTP (`GET /transfers/create`, `POST /transfers`, `POST /transfers/{id}/ship`, `POST /transfers/{id}/receive`, `POST /batch/{id}/kirim`).

---

## 3. Approaches Considered & Decision

- **Pendekatan A (Dipilih):** Permission granular per aksi & kategori barang (`request.bahan.create`, `request.produk_jadi.create`, `transfer.bahan.create/ship/receive`, `transfer.produk_jadi.create/ship/receive`), dipadukan dengan pemisahan rute/UI dari `rt.create` ke `request.*` / `transfer.*` dan service otorisasi terpusat.
  - **Alasan:** Memberi kendali penuh kepada admin untuk menyesuaikan tiap kombinasi (Request Bahan, Create/Ship/Receive Transfer Bahan, Create/Ship/Receive Transfer Produk Jadi) pada role `operasional` maupun role kustom baru di **Peran & Wewenang**.
- **Pendekatan B (Ditolak):** Permission paket ringkas (`transfer.bahan.outbound`, `transfer.bahan.receive`, `transfer.produk_jadi`) tanpa memecah `create` dan `ship`.
  - **Alasan:** Mengunci aksi buat draft vs kirim barang ke dalam satu checkbox sehingga tidak fleksibel bila di masa depan perlu pemisahan tugas antara pembuat draft transfer dan pengirim fisik.
- **Pendekatan hardcode role (Ditolak):** Mengecek `$user->hasRole('operasional')` di controller.
  - **Alasan:** Tidak muncul di matriks izin `/roles/edit` dan tidak dapat dikustomisasi untuk role lain.

---

## 4. Constraints & Compatibility

1. **Klasifikasi Tipe Produk (`produk.tipe`):**
   - Database & model `Produk` memiliki variasi nilai `'produk_jadi'` (untuk Produk Jadi) dan `'bahan'`, `'kemas'`, `'bahan_baku'`, `'kemasan'` (untuk Bahan Baku & Kemasan, lihat `Produk::SUPPLIABLE_TIPES` dan fixture test `RequestAndTransferHttpTest`).
   - Aturan baku: `produk.tipe === 'produk_jadi'` diklasifikasikan sebagai kategori **`produk_jadi`**; selain itu (`'bahan'`, `'kemas'`, `'bahan_baku'`, `'kemasan'`, atau tipe bahan lain) diklasifikasikan sebagai kategori **`bahan`**.
2. **Pemetaan Jenis Transfer (`Transfer::JENIS`):**
   - `pemenuhan_request_bahan`, `retur_bahan` → kategori **`bahan`**.
   - `kirim_produk_jadi`, `retur_produk_jadi` → kategori **`produk_jadi`**.
   - `antar_fulfillment` (termasuk input legacy `distribusi` yang dinormalisasi ke `antar_fulfillment`): kategori ditentukan dari **item produk** di dalam transfer (`Transfer` berisi item bahan → kategori `bahan`, item produk jadi → kategori `produk_jadi`, item campuran → membutuhkan izin keduanya). Saat form `transfers/create` dibuka (belum ada item tersimpan), jenis `antar_fulfillment` tampil jika user punya minimal satu izin `transfer.*.create` (untuk `operasional`, produk yang tersedia otomatis difilter `produk_jadi` dan pengiriman item bahan tetap ditolak `403` saat `store`).
3. **Database & Strict Loading:**
   - Migrasi wajib kompatibel dengan SQLite (`:memory:`) dan PostgreSQL.
   - `Model::preventLazyLoading(true)` aktif pada mode non-production/test — otorisasi `Transfer` (`canShipTransfer`, `canReceiveTransfer`, `canCancelTransfer`) dan `StockRequest` wajib memanggil `loadMissing(...)` sebelum mengakses relasi `items.produk` atau `stockRequest`.
4. **Kontrak Test Eksisting:**
   - `Prd24EdgeCaseAndBugAnalysisTest::test_bidirectional_gate_aliasing`: `$user->givePermissionTo('request.create')` tetap harus meloloskan `$user->can('rt.create') === true` dan gagal `rt.approve`.
   - `RbacAndPermissionAliasTest::test_legacy_rt_permissions_alias_to_request_and_transfer_gates`: `$customUser->givePermissionTo('rt.create')` tetap harus meloloskan `request.create` & `transfer.create`, dan gagal `transfer.ship`.
   - `RbacAndPermissionAliasTest::test_operasional_role_has_request_capabilities_and_cannot_approve_requests`: `$operasionalUser->can('request.create')` harus tetap `true` (dipenuhi lewat resolusi Gate: punya `request.bahan.create` ⇒ lulus `request.create`).
   - `RequestAndTransferHttpTest`:
     - `test_request_lifecycle_http`: `operasional` membuat `request_bahan` → tetap lolos.
     - `test_transfer_lifecycle_and_strict_anti_fraud_receiving_http`: `gudang` membuat & mengirim `distribusi` (`antar_fulfillment`) berisi `bahanBaku`, lalu `operasional` mem-post `transfers.receive` → tetap lolos karena `gudang` punya `transfer.bahan.create/ship` dan `operasional` punya `transfer.bahan.receive`.
     - `test_transfer_create_from_request_preserves_item_metadata_on_insufficient_stock_redirect`: `gudang` membuat/kirim `pemenuhan_request_bahan` → tetap lolos.
   - `ProductionFlowTest`: `operasional` memanggil `POST batches/{batch}/kirim` (`kirim_produk_jadi`) → tetap lolos karena `operasional` punya `transfer.produk_jadi.create`.

---

## 5. Detailed Design

### 5.1 Katalog Permission ([`PermissionCatalogService`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Services/Authorization/PermissionCatalogService.php))

Di dalam `getGroupedCatalog()['Operasional & Logistik']`:

1. **Modul `request` (`Permintaan Barang (Request)`):**
   - `request.view` => `'Lihat Dokumen Permintaan Barang'`
   - `request.create` => `'Buat Semua Jenis Permintaan Barang (Global)'`
   - `request.bahan.create` => `'Buat Request Bahan Baku & Kemasan'`
   - `request.produk_jadi.create` => `'Buat Request Produk Jadi'`
   - `request.submit` => `'Ajukan Permintaan ke Manajer'`
   - `request.approve` => `'Setujui Permintaan Barang'`
   - `request.cancel` => `'Batalkan Permintaan Barang'`

2. **Modul `transfer` (`Transfer & Mutasi Fisik`):**
   - `transfer.view` => `'Lihat Dokumen Transfer & Surat Jalan'`
   - `defect.view` => `'Lihat Dashboard & Rekapitulasi Barang Rusak (Defect)'`
   - `transfer.create` => `'Buat Semua Jenis Transfer (Global)'`
   - `transfer.bahan.create` => `'Bahan Baku: Buat Transfer Keluar / Pemenuhan Request'`
   - `transfer.bahan.ship` => `'Bahan Baku: Kirim Transfer (Potong Stok In-Transit)'`
   - `transfer.bahan.receive` => `'Bahan Baku: Terima Transfer Masuk di Tujuan'`
   - `transfer.produk_jadi.create` => `'Produk Jadi: Buat Transfer / Kirim Hasil Batch ke Fulfillment'`
   - `transfer.produk_jadi.ship` => `'Produk Jadi: Kirim Transfer (Potong Stok In-Transit)'`
   - `transfer.produk_jadi.receive` => `'Produk Jadi: Terima Transfer Masuk di Tujuan'`
   - `transfer.ship` => `'Kirim Semua Jenis Transfer (Global)'`
   - `transfer.receive` => `'Terima Semua Jenis Transfer (Global)'`
   - `transfer.cancel` => `'Batalkan Dokumen Transfer'`

3. **Modul `rt`:** Tetap dipertahankan di katalog demi kompatibilitas role/test legacy, namun tidak lagi dipasang sebagai gate utama di rute/controller/view aktif.

---

### 5.2 Migrasi & Pembaruan Seeder

1. **Migrasi baru:** `database/migrations/2026_11_11_000001_add_granular_bahan_produk_jadi_permissions.php`
   - Memanggil `app(PermissionCatalogService::class)->syncCatalogToDatabase()`.
   - Memperbarui izin sistem role `operasional`, `gudang`, `fulfillment`, dan `manager` secara idempoten (hanya jika role terkait sudah ada di tabel `roles`):
     - **`operasional`:** mengganti kombinasi lama (`rt.create`, `rt.ship`, `rt.receive`, `transfer.ship`, `transfer.receive`, `request.create`) dengan izin eksplisit baru sesuai matriks bawaan di §5.3 agar lingkungan eksisting langsung mengikuti aturan baru saat `php artisan migrate`.
     - **`gudang`:** menyinkronkan `transfer.bahan.*` dan `transfer.produk_jadi.*` serta mencabut ketergantungan global `rt.create`/`transfer.create` jika perlu (lihat §5.3).
     - **`fulfillment`:** menyinkronkan `request.produk_jadi.create` dan `transfer.produk_jadi.*`.
     - **`manager`:** `syncPermissions(Permission::all())`.
   - Memanggil `app(PermissionRegistrar::class)->forgetCachedPermissions()`.
2. **Seeder ([`PermissionSeeder`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/database/seeders/PermissionSeeder.php)):**
   Menyamakan daftar permission per role persis dengan §5.3 (memanggil `$allPermissions` yang sudah otomatis menggabungkan `PermissionCatalogService::getAllPermissionKeys()`).

---

### 5.3 Matriks Permission Bawaan System Roles

| Role | Request Permissions | Transfer Permissions (Bahan Baku) | Transfer Permissions (Produk Jadi) | Lainnya (Request & Transfer Module) |
| :--- | :--- | :--- | :--- | :--- |
| **`manager`** | Semua (bypass `Gate::before`) | Semua | Semua | Semua |
| **`operasional`** | `request.view`, `request.bahan.create`, `request.submit`, `request.cancel` | **`transfer.bahan.receive`** *(TIDAK punya `.create` / `.ship`)* | **`transfer.produk_jadi.create`**, **`transfer.produk_jadi.ship`** *(TIDAK punya `.receive` default, bisa ditambah via Edit Role)* | `transfer.view`, `defect.view`, `rt.view`, `rt.submit` *(tanpa `transfer.cancel` default)* |
| **`gudang`** | `request.view` | `transfer.bahan.create`, `transfer.bahan.ship`, `transfer.bahan.receive` | `transfer.produk_jadi.create`, `transfer.produk_jadi.ship`, `transfer.produk_jadi.receive` | `transfer.view`, `defect.view`, `rt.view`, `rt.submit`, `rt.process` |
| **`fulfillment`** | `request.view`, `request.produk_jadi.create`, `request.submit`, `request.cancel` | *(tidak ada default; bisa dicentang via Edit Role bila butuh retur bahan)* | `transfer.produk_jadi.create`, `transfer.produk_jadi.ship`, `transfer.produk_jadi.receive` | `transfer.view`, `defect.view`, `rt.view` |

**Keputusan Desain — Pembatalan Transfer (`transfer.cancel`):**
- Sesuai keputusan user (*Manager saja*), `transfer.cancel` **tidak** diberikan secara bawaan kepada role `operasional`, `gudang`, maupun `fulfillment`, tetapi tetap tersedia di matriks **Peran & Wewenang** bila suatu saat perlu diaktifkan pada role tertentu.
- Jika suatu role kustom diaktifkan `transfer.cancel`, maka di `InventoryTransferAuthorizationService::canCancelTransfer(User $user, Transfer $transfer)`: role `manager` selalu diizinkan pada draft apa pun; non-manager wajib memiliki `$user->can('transfer.cancel')`, menjadi `created_by`, dan memenuhi `canCreateTransferWithCategories($user, $categories)` agar tidak bisa membatalkan draft kategori yang tidak dikuasainya.
- Di `TransferController::show()`, `$canCancelDraft` dan `TransferController::cancel()` sama-sama memanggil `canCancelTransfer($user, $transfer)` untuk mengembalikan `403` saat akses tidak sah.

---

### 5.4 Resolusi Gate di [`AppServiceProvider`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Providers/AppServiceProvider.php)

Untuk menjaga hierarki izin yang intuitif tanpa kebocoran antar-modul maupun antar-kategori:

1. **Manager bypass:** `$user->hasRole('manager')` → `return true;`
2. **Guard re-entrancy:** Gunakan flag per `$ability` atau array stack `$checkingAbilities[$ability]` (bukan single `static bool $checking`, agar pengecekan bersarang antar-ability berbeda tidak saling mematikan evaluasi!).
3. **Resolusi Permission Granular (ketika memeriksa `request.bahan.create`, `request.produk_jadi.create`, `transfer.bahan.*`, `transfer.produk_jadi.*`):**
   - `request.bahan.create` / `request.produk_jadi.create` → `true` jika user punya izin persis itu, **ATAU** punya `request.create` (global), **ATAU** punya `rt.create` (legacy).
   - `transfer.bahan.create` / `transfer.produk_jadi.create` → `true` jika user punya izin persis itu, **ATAU** punya `transfer.create` (global), **ATAU** punya `rt.create` (legacy).
   - `transfer.bahan.ship` / `transfer.produk_jadi.ship` → `true` jika user punya izin persis itu, **ATAU** punya `transfer.ship` (global), **ATAU** punya `rt.ship` (legacy).
   - `transfer.bahan.receive` / `transfer.produk_jadi.receive` → `true` jika user punya izin persis itu, **ATAU** punya `transfer.receive` (global), **ATAU** punya `rt.receive` (legacy).
   - **Penting:** Untuk mencegah loop rekursif saat memeriksa `hasPermissionTo`, cek langsung permission DB via `$user->hasPermissionTo(...)` atau `$user->getAllPermissions()->pluck('name')` untuk key global/legacy sebelum mendelegasikan, atau stack `$checkingAbilities`! (Menggunakan cache `$user->hasPermissionTo` tidak memanggil `Gate` secara langsung; Spatie `can()` memanggil `Gate::forUser` sedangkan `hasPermissionTo()` memeriksa koleksi permission role/user langsung — namun menggunakan Laravel Gate `hasPermission` via helper private tanpa memicu loop adalah yang paling aman. Kita tetap pakai `Gate` guard per ability + `hasDirectOrRolePermission` via Spatie `getAllPermissions()->contains('name', ...)` jika ability terdaftar).
4. **Resolusi Permission Tingkat Modul (`request.create`, `transfer.create`, `transfer.ship`, `transfer.receive`) untuk middleware route & tombol umum:**
   - `request.create` → `true` jika punya `request.create` **OR** `request.bahan.create` **OR** `request.produk_jadi.create` **OR** `rt.create`.
   - `transfer.create` → `true` jika punya `transfer.create` **OR** `transfer.bahan.create` **OR** `transfer.produk_jadi.create` **OR** `rt.create`.
   - `transfer.ship` → `true` jika punya `transfer.ship` **OR** `transfer.bahan.ship` **OR** `transfer.produk_jadi.ship` **OR** `rt.ship`.
   - `transfer.receive` → `true` jika punya `transfer.receive` **OR** `transfer.bahan.receive` **OR** `transfer.produk_jadi.receive` **OR** `rt.receive`.
   - **Tidak ada bridge lintas Request ↔ Transfer:** memiliki `request.create` atau `request.bahan.create` **TIDAK PERNAH** membuat `$user->can('transfer.create')` bernilai `true`!
5. **Resolusi Legacy `rt.*`:**
   - `rt.view` → `rt.view` OR `request.view` OR `transfer.view`
   - `rt.create` → `rt.create` OR `request.create` OR `request.bahan.create` OR `request.produk_jadi.create` OR `transfer.create` OR `transfer.bahan.create` OR `transfer.produk_jadi.create` *(menjaga test `test_bidirectional_gate_aliasing`)*
   - `rt.submit` → `rt.submit` OR `request.submit`
   - `rt.approve` → `rt.approve` OR `request.approve`
   - `rt.ship` → `rt.ship` OR `transfer.ship` OR `transfer.bahan.ship` OR `transfer.produk_jadi.ship`
   - `rt.receive` → `rt.receive` OR `transfer.receive` OR `transfer.bahan.receive` OR `transfer.produk_jadi.receive`
   - `rt.cancel` → `rt.cancel` OR `request.cancel` OR `transfer.cancel`
   - **Kunci keamanan:** Karena seluruh rute, controller, dan Blade view aktif tidak lagi memakai `rt.create` / `rt.ship` / `rt.receive`, keberadaan bridge `rt.*` hanya untuk kompatibilitas balik test/kode lama dan **tidak bisa dipakai untuk mengakses modul Transfer oleh role yang hanya punya `request.bahan.create`.**

---

### 5.5 Service Otorisasi Terpusat ([`InventoryTransferAuthorizationService`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Services/Authorization/InventoryTransferAuthorizationService.php))

Membuat service terpusat `App\Services\Authorization\InventoryTransferAuthorizationService` agar tidak ada duplikasi logika kategori antara controller, service request, dan view:

1. **Klasifikasi Kategori Item & Jenis:**
   - `isProdukJadiTipe(?string $tipe): bool` → `$tipe === 'produk_jadi'`.
   - `resolveProductCategories(iterable $produkIds): array` → query `Produk::whereIn('id', ...)->pluck('tipe')` (atau pakai koleksi/relasi yang sudah dimuat), kembalikan array unik berisi subset `['bahan', 'produk_jadi']`.
   - `resolveCategoriesForTransferPayload(string $jenis, ?StockRequest $stockRequest, array $produkIds): array`:
     - Jika `$stockRequest` ada: `request_bahan` ⇒ `['bahan']`, `request_produk_jadi` ⇒ `['produk_jadi']`.
     - Jika `$jenis` ∈ `['pemenuhan_request_bahan', 'retur_bahan']`: masukkan `'bahan'`.
     - Jika `$jenis` ∈ `['kirim_produk_jadi', 'retur_produk_jadi']`: masukkan `'produk_jadi'`.
     - Tambahan wajib dari item (`resolveProductCategories($produkIds)`): gabungkan kategori semua item! *(Ini menutup celah: user memilih `jenis = kirim_produk_jadi` atau `antar_fulfillment`, lalu mengirim `produk_id` Bahan Baku → array kategori menyertakan `'bahan'` → ditolak jika tidak punya izin `'bahan'`!)*
   - `resolveCategoriesForTransfer(Transfer $transfer): array`:
     - Muat `$transfer->loadMissing(['items.produk:id,tipe', 'stockRequest:id,jenis'])`.
     - Gabungkan hasil dari `transfer->jenis`, `stockRequest->jenis`, dan `items.*.produk->tipe`.
     - Jika transfer tidak punya item sama sekali dan berjenis `antar_fulfillment`, kembalikan `['produk_jadi']` sebagai fallback kategori default transfer non-bahan.

2. **Method Pemeriksaan Izin (mengembalikan `bool`):**
   - `allowedRequestTypes(User $user): array` → kembalikan `['request_bahan', ...]` jika `$user->can('request.bahan.create')`, dan `'request_produk_jadi'` jika `$user->can('request.produk_jadi.create')`.
   - `canCreateRequestForType(User $user, string $jenis): bool`
   - `canCreateTransferCategory(User $user, string $category): bool` (`'bahan'` → `transfer.bahan.create`, `'produk_jadi'` → `transfer.produk_jadi.create`).
   - `allowedTransferJenis(User $user): array`:
     - Jika `canCreateTransferCategory($user, 'bahan')`: sertakan `'pemenuhan_request_bahan'`, `'retur_bahan'`.
     - Jika `canCreateTransferCategory($user, 'produk_jadi')`: sertakan `'kirim_produk_jadi'`, `'retur_produk_jadi'`.
     - Jika punya minimal salah satu dari `'bahan'` atau `'produk_jadi'`: sertakan `'antar_fulfillment'`.
   - `allowedTransferProductTipes(User $user): ?array`:
     - Jika bisa `'bahan'` dan `'produk_jadi'`: `null` (semua tipe aktif boleh).
     - Jika hanya `'bahan'`: `['bahan', 'kemas', 'bahan_baku', 'kemasan']`.
     - Jika hanya `'produk_jadi'`: `['produk_jadi']`.
     - Jika tidak bisa keduanya: `[]`.
   - `canCreateTransferForRequest(User $user, StockRequest $stockRequest): bool`:
     - Cek `$stockRequest->jenis === 'request_bahan'` ⇒ `$user->can('transfer.bahan.create')`
     - `$stockRequest->jenis === 'request_produk_jadi'` ⇒ `$user->can('transfer.produk_jadi.create')`
   - `canCreateTransferForBatch(User $user, BatchProduksi $batch): bool`:
     - Cek `$user->can('transfer.produk_jadi.create')`.
   - `canCreateTransferWithCategories(User $user, array $categories): bool`:
     - Setiap kategori di `$categories` wajib lolos `canCreateTransferCategory($user, $cat)` (tidak kosong).
   - `canShipTransfer(User $user, Transfer $transfer): bool`:
     - Muat relasi via `resolveCategoriesForTransfer($transfer)`; setiap kategori wajib lolos `$user->can("transfer.{$cat}.ship")`.
   - `canReceiveTransfer(User $user, Transfer $transfer): bool`:
     - Muat relasi via `resolveCategoriesForTransfer($transfer)`; setiap kategori wajib lolos `$user->can("transfer.{$cat}.receive")`.
   - `canCancelTransfer(User $user, Transfer $transfer): bool`:
     - Wajib `$transfer->status === 'draft'` dan `$user->can('transfer.cancel')`. Jika `$user->hasRole('manager')`, kembalikan `true`; jika bukan, wajib `(int) $transfer->created_by === (int) $user->id` dan `canCreateTransferWithCategories($user, resolveCategoriesForTransfer($transfer))`.
   - `resolveRequestItemCategoryViolations(string $jenis, array $produkIds): ?string`:
     - Jika `$jenis === 'request_bahan'` dan ada item bertipe `'produk_jadi'`, atau jika `$jenis === 'request_produk_jadi'` dan ada item non-`'produk_jadi'`, kembalikan pesan error validasi yang jelas; jika cocok semua, kembalikan `null`.
   - `authorizeOrAbort(bool $allowed, string $message): void` → `abort_unless($allowed, 403, $message);`.

---

### 5.6 Pembaruan Routes, Controllers, & Services Eksisting

1. **[`routes/web.php`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/routes/web.php):**
   - **Group `requests.*`:** `middleware('can:request.view')`
     - `create`, `store`, `edit`, `update` → `middleware('can:request.create')`
     - `submit` → `middleware('can:request.submit')`
     - `approve`, `reject` → `middleware('can:request.approve')`
     - `cancel` → `middleware('can:request.cancel')`
   - **Group `transfers.*`:** `middleware('can:transfer.view')`
     - `create`, `store` → `middleware('can:transfer.create')`
     - `ship` → `middleware('can:transfer.ship')`
     - `receive` → `middleware('can:transfer.receive')`
     - `cancel` → `middleware('can:transfer.cancel')`
   - **Route `batches.kirim`:** `middleware('can:transfer.produk_jadi.create')`.
   - *(Endpoint `ProdukController::selectData` tetap dipakai bersama oleh form Master/PO/Request/Transfer dengan parameter `tipe` yang difilter di UI serta divalidasi ulang secara ketat di backend `RequestController` / `TransferController`.)*

2. **[`RequestService::validateCreatorRole`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Services/RequestService.php#L36-L56):**
   - Ganti pengecekan role statis menjadi pengecekan permission + validasi kecocokan kategori item (Keputusan Builder: Item-level category check pada Request ditetapkan wajib konsisten dengan Transfer):
     - `'request_bahan'` → cek `$user->can('request.bahan.create')`; jika false, lempar `ValidationException`.
     - `'request_produk_jadi'` → cek `$user->can('request.produk_jadi.create')`; jika false, lempar `ValidationException`.
     - Pada `create()` dan `update()`, validasi pula `resolveRequestItemCategoryViolations($jenis, $produkIds)`: jika item tidak cocok dengan `jenis` Request, lempar `ValidationException::withMessages(['items' => ...])`.

3. **[`RequestController`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/RequestController.php):**
   - Ganti semua `$this->authorize('rt.*')` dengan `request.*`.
   - `create()`:
     - Gunakan `$this->authz->allowedRequestTypes($user)` (menghilangkan hardcode role `operasional` / `fulfillment` / `manager`).
     - Jika `$request->filled('batch_id')` namun user tidak punya `request.bahan.create`, jalankan `abort(403, 'Anda tidak memiliki izin membuat Request Bahan Baku dari Batch Produksi.')`; hanya muat `$activeBatches` & `$batchPrefills` bila `'request_bahan'` termasuk dalam `$allowedJenis`.
     - Filter koleksi `$produk` awal yang dikirim ke view bila user hanya punya satu kategori izin (hanya `bahan`/`kemas`/`bahan_baku`/`kemasan` jika hanya `request.bahan.create`; hanya `produk_jadi` jika hanya `request.produk_jadi.create`).
   - `store()`:
     - **Sebelum `try` block:** cek `abort_unless($this->authz->canCreateRequestForType($user, $validated['jenis']), 403, ...)` dan cek setiap item kategori di `items.*.produk_id`: jika user bukan `manager` dan menyertakan produk dari kategori yang izin request-nya tidak dimilikinya, `abort(403, ...)`. Baru kemudian validasi kecocokan `jenis` vs item (`ValidationException` → redirect `back()->withInput()` jika semata-mata salah pilih kategori oleh role yang punya dua izin/manager).
   - `edit()` & `update()`:
     - Cek `abort_unless($this->authz->canCreateRequestForType($user, $stockRequest->jenis), 403, ...)` dan pada `update()` pastikan item tetap cocok dengan `$stockRequest->jenis` (`403` jika izin kategori item tidak dimiliki, redirect validasi jika tidak cocok jenis).
   - `data()` & `show()`:
     - Tombol `Buat Transfer` (`$canCreateTransfer`) hanya `true` jika `$r->status === 'disetujui' && ! $hasActiveTransfer && $this->authz->canCreateTransferForRequest($user, $r)`.
     - `$isManager` / aksi approve: gunakan `$user->can('request.approve')` untuk `canApprove`.

4. **[`TransferController`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/TransferController.php):**
   - Ganti semua `$this->authorize('rt.*')` dengan `transfer.*` (`transfer.view`, `transfer.create`, `transfer.ship`, `transfer.receive`, `transfer.cancel`).
   - `create(Request $request)`:
     - Jika `request_id` diisi: wajib `abort_unless($this->authz->canCreateTransferForRequest($user, $stockRequest), 403, ...)`.
     - Jika `batch_id` diisi: wajib `abort_unless($this->authz->canCreateTransferForBatch($user, $batch), 403, ...)`.
     - Jika `jenis` query param diisi secara eksplisit dan bertentangan dengan izin user (mis. `?jenis=retur_bahan` oleh user tanpa `transfer.bahan.create`): `abort(403, ...)`.
     - Hitung `$allowedJenis = $this->authz->allowedTransferJenis($user)` & `$allowedProductTipes = $this->authz->allowedTransferProductTipes($user)` serta `$canTransferBahan` / `$canTransferProdukJadi`.
     - Filter koleksi `$products` agar hanya memuat tipe yang diizinkan (`$allowedProductTipes`), dan kirim `$allowedJenis`, `$canTransferBahan`, `$canTransferProdukJadi`, `$allowedProductTipes` ke view `transfers.create`.
     - Tentukan `$defaultJenis` aman dari `$allowedJenis` (jika user hanya boleh Produk Jadi, default `'kirim_produk_jadi'` atau `'antar_fulfillment'`; jika hanya Bahan, default `'retur_bahan'`).
   - `store(Request $request)`:
     - Validasi input seperti saat ini.
     - Tentukan `$stockReq` (jika `request_id` diisi), `$jenis` efektif (normalisasi `'distribusi'` → `'antar_fulfillment'`), dan kategori via `resolveCategoriesForTransferPayload($jenis, $stockReq, $produkIds)`.
     - **Sebelum `DB::beginTransaction()` dan `try/catch`:** jalankan `abort_unless($this->authz->canCreateTransferWithCategories($user, $categories), 403, 'Anda tidak memiliki izin untuk membuat transfer pada kategori barang tersebut.')`, serta jika `action === 'ship'`, cek juga `abort_unless($this->authz->canShipCategories($user, $categories), 403, 'Anda tidak memiliki izin untuk mengirim (In-Transit) transfer pada kategori barang tersebut.')`.
     - Cek pula konsistensi jenis eksplisit vs item (mis. `kirim_produk_jadi` / `retur_produk_jadi` tidak boleh berisi item bahan baku; `pemenuhan_request_bahan` / `retur_bahan` tidak boleh berisi item produk jadi → lempar `ValidationException`).
   - `show(Transfer $transfer)`:
     - `$canShip = $transfer->status === 'draft' && $this->authz->canShipTransfer($user, $transfer);` (tidak lagi meloloskan `created_by` jika user tidak punya izin `ship` atas kategori barang tersebut — menghilangkan hardcode role!).
     - `$canReceive = $transfer->status === 'dikirim' && $this->authz->canReceiveTransfer($user, $transfer) && ($isManager || (int) $transfer->created_by !== (int) $user->id);`
     - `$canCancelDraft = $this->authz->canCancelTransfer($user, $transfer);`
   - `ship(Request $request, Transfer $transfer)`:
     - Setelah `$this->authorize('transfer.ship')`, jalankan `abort_unless($this->authz->canShipTransfer($user, $transfer), 403, ...)`.
   - `receive(Request $request, Transfer $transfer)`:
     - Setelah `$this->authorize('transfer.receive')`, jalankan `abort_unless($this->authz->canReceiveTransfer($user, $transfer), 403, ...)`.
   - `cancel(Request $request, Transfer $transfer)`:
     - Setelah `$this->authorize('transfer.cancel')`, jalankan `abort_unless($this->authz->canCancelTransfer($user, $transfer), 403, ...)`.

5. **[`BatchController::kirim`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/app/Http/Controllers/BatchController.php#L512-L568):**
   - Ganti `$this->authorize('rt.create')` menjadi `$this->authorize('transfer.produk_jadi.create')`!

---

### 5.7 Pembaruan Blade Views

1. **`resources/views/requests/index.blade.php` & `requests/show.blade.php`:**
   - Tombol `Buat Request Baru`: `@can('request.create')` (menggantikan `@can('rt.create')`).
   - Di `requests/show.blade.php` baris 61: cukup `@if($canCreateTransfer)` (karena controller sudah memvalidasi `canCreateTransferForRequest` tanpa `rt.create`!).
2. **`resources/views/requests/create.blade.php` & `requests/edit.blade.php`:**
   - Filter dropdown `productSearchSelect` (`filterTipe`) secara reaktif sesuai `jenis` (`request_bahan` → `['bahan', 'kemas', 'bahan_baku', 'kemasan']`, `request_produk_jadi` → `['produk_jadi']`), dan sembunyikan blok pemilihan Batch Produksi bila user tidak memiliki `'request_bahan'` di `$allowedJenis`.
3. **`resources/views/transfers/index.blade.php`:**
   - Tombol `Buat Transfer` / `Buat Transfer Baru`: `@can('transfer.create')`.
4. **`resources/views/transfers/create.blade.php`:**
   - Dropdown `jenis`: hanya merender key yang ada di `$allowedJenis` (`Transfer::JENIS_LABELS`), sehingga role `operasional` tidak melihat `Pemenuhan Request Bahan Baku` maupun `Retur Bahan Baku`.
   - Komponen `productSearchSelect`: secara reaktif mengirim `filterTipe` sesuai izin & `jenis` yang dipilih:
     - Jika user tidak punya izin `transfer.bahan.create`: paksa `['produk_jadi']`.
     - Jika user tidak punya izin `transfer.produk_jadi.create`: paksa `['bahan', 'kemas', 'bahan_baku', 'kemasan']`.
     - Jika user punya keduanya: saat memilih `pemenuhan_request_bahan` / `retur_bahan` → filter bahan; saat memilih `kirim_produk_jadi` / `retur_produk_jadi` → filter produk jadi; saat `antar_fulfillment` → tampilkan semua.
5. **`resources/views/batches/show.blade.php`:**
   - Baris 53 & 728 (`Kirim ke Fulfillment` / `Buat Transfer Produk Jadi`): `@can('transfer.produk_jadi.create')`.
   - Baris 654 & 691 (`Buat Request Bahan`): `@can('request.bahan.create')`.

---

## 6. Testing & Verification Plan

1. **Test Unit / Feature RBAC ([`RbacAndPermissionAliasTest`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/tests/Feature/RbacAndPermissionAliasTest.php)):**
   - Verifikasi role `operasional` memiliki `request.bahan.create`, `transfer.bahan.receive`, `transfer.produk_jadi.create`, `transfer.produk_jadi.ship`, **dan TIDAK memiliki** `transfer.bahan.create`, `transfer.bahan.ship`, `request.produk_jadi.create`, `rt.create`.
   - Verifikasi `$operasional->can('request.create') === true` dan `$operasional->can('transfer.create') === true` (untuk route gate umum) sementara `$operasional->can('transfer.bahan.create') === false` dan `$operasional->can('transfer.bahan.ship') === false`.
   - Pertahankan uji alias legacy (`rt.create`, `request.create` -> `rt.create`).
2. **Test Kustomisasi Role ([`CustomRoleManagementTest`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/tests/Feature/CustomRoleManagementTest.php)):**
   - Verifikasi seluruh key baru muncul pada halaman `roles.create` / `roles.edit` dan role kustom yang hanya diberi `request.bahan.create` + `transfer.produk_jadi.create` mengikuti batas akses yang sama tanpa harus bernama `operasional`.
   - Uji role kustom yang dicentang `transfer.bahan.create` + `transfer.bahan.ship` di Edit Role otomatis diizinkan membuat & mengirim Transfer Bahan Baku.
3. **Test End-to-End HTTP ([`RequestAndTransferHttpTest`](file:///c:/Users/User/HS%20Project/production-lab-online-hs/tests/Feature/RequestAndTransferHttpTest.php)):**
   - Role `operasional`:
     - Boleh `GET/POST requests.create` (`request_bahan`) & tombol `Buat Request Bahan` terlihat di `batches.show`.
     - Pada `request_bahan` yang disetujui: tombol `Buat Transfer` tidak muncul di `requests.show` maupun JSON `requests.data`.
     - Akses paksa `GET transfers.create?request_id=<approved_request_bahan>` → `403`.
     - Akses paksa `POST transfers.store` dengan `request_id=<approved_request_bahan>` atau `jenis=pemenuhan_request_bahan` atau `jenis=retur_bahan` → `403`.
     - Akses paksa `POST transfers.store` dengan `jenis=kirim_produk_jadi` atau `antar_fulfillment` namun `items` menyelipkan `produk_id` Bahan Baku / Kemas → `403`.
     - Akses paksa `POST transfers.ship` pada draft Transfer Bahan Baku → `403`, dan tombol `Kirim Sekarang` tidak tampil pada `transfers.show`.
     - `POST transfers.receive` pada Transfer Bahan Baku berstatus `dikirim` (yang dibuat oleh Gudang) → **berhasil (`302`)** & tombol Konfirmasi Penerimaan tampil di `transfers.show`.
     - `GET transfers.create` untuk `operasional`: hanya menampilkan jenis Produk Jadi (`kirim_produk_jadi`, `antar_fulfillment`, `retur_produk_jadi`), menyembunyikan opsi Bahan Baku.
     - `POST batches.kirim`, `GET/POST transfers.create?jenis=kirim_produk_jadi&batch_id=...`, `POST transfers.store` (`kirim_produk_jadi` dengan item `produk_jadi` + `action=ship`), serta `POST transfers.ship` pada draft Produk Jadi → **berhasil**.
4. **Regresi Penuh:**
   - Jalankan `npm run build` dan seluruh suite test relevan (`RbacAndPermissionAliasTest`, `CustomRoleManagementTest`, `AuthorizationTest`, `Prd24EdgeCaseAndBugAnalysisTest`, `RequestAndTransferHttpTest`, `RequestAndTransferSeparationTest`, `ProductionFlowTest`, `BatchMultiProductAndRequestLinkTest`, `ViewRenderingSafetyTest`, dll.) — atau seluruh `php artisan test`.
