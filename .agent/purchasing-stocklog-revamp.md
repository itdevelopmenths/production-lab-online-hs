# Purchasing & Log Pergerakan Stok Revamp Specification

**Dokumen Arsitektur & Pelaksanaan Teknis Enterprise**
*Path:* `.agent/purchasing-stocklog-revamp.md`  
*Role:* Senior Software Architect & Project Lead  
*Date:* 15 September 2026  
*Status:* Approved for Execution  

---

## 1. Executive Summary & Ringkasan Kebutuhan
Proyek ini memutakhirkan siklus pengadaan (*Purchasing*), pencatatan tempo hutang dagang (*Accounts Payable*), visibilitas persetujuan pengadaan (*Manager Approval*), keterbukaan data harga historis (*HPP Lama*), serta transparansi audit mutasi persediaan barang melalui modul baru *Log Pergerakan Stok*.

### Kebutuhan Fungsional Utama:
1. **Deadline Pembayaran Tempo (`tanggal_tempo`)**:
   - Menambahkan kolom tanggal tempo khusus pada model `PurchaseOrder` (`tanggal_tempo`).
   - Pada form `resources/views/purchasing/create.blade.php` dan `edit.blade.php`, jika tipe pembayaran dipilih `tempo`, tampilkan input khusus tanggal deadline pembayaran tempo (`tanggal_tempo`) yang wajib diisi.
   - Pada `resources/views/purchasing/show.blade.php`, tampilkan informasi tanggal jatuh tempo pembayaran tempo beserta status ketepatan waktu / overdue.
   - Sinkronisasi otomatis evaluasi status pembayaran `overdue` pada `refreshStatusPembayaran()`.

2. **Penyempurnaan Struktur Tabel & Filter di `resources/views/purchasing/index.blade.php`**:
   - **Tabel 1: Daftar Purchase Order (PO)**:
     - Kolom: `No Invoice | Supplier | Lokasi | Tgl PO | ETA | Tgl Tiba | Status PO | Aksi`
     - Kolom `Status AP`, `Nilai Tagihan`, `Sisa Tagihan` dihapus dari tabel PO (hanya fokus pada logistik & procurement).
     - Filter `Status AP / Bayar` dipindahkan sepenuhnya ke Tab Buku Tagihan (toolbar PO hanya menyisakan filter status PO & reset).
   - **Tabel 2: Buku Tagihan (Accounts Payable)**:
     - Kolom: `No Invoice | Supplier | Tgl PO | Skema | Total Tagihan | Telah Dibayar | Sisa Tagihan | Jatuh Tempo | Status Bayar | Aksi`
     - Filter `Status AP` dan `Skema Transaksi` tetap terfokus di tab ini.
   - **Tabel 3: Tab Approval Manajerial**:
     - Menambahkan filter/tab khusus *Approval* untuk memudahkan Manager/Atasan menyaring dan meninjau PO yang berstatus `diajukan` tanpa tercampur status lain.
     - Dilengkapi indikator badge counter PO yang menunggu persetujuan.

3. **Tampilan HPP Lama pada Pembuatan PO**:
   - Pada `resources/views/purchasing/create.blade.php`, saat memilih bahan/kemasan, tampilkan nilai HPP lama (`harga_hpp`) produk tersebut sebagai pembanding harga historis sebelum input PO diajukan.
   - Menjamin endpoint `produk/select-data` menyertakan atribut `harga_hpp`.

4. **Halaman & Menu Baru: Log Pergerakan Stok**:
   - Menambahkan menu global di Sidebar: **Log Pergerakan Stok** di bawah grup menu **Stok & Mutasi**.
   - Halaman baru dengan kolom:
     `Tipe | Item | Lokasi Gudang | Ref | Qty | Sebelum --> Sesudah | Tanggal | Oleh`
   - Sumber data: tabel `kartu_stok` (eager load: `produk`, `gudang`, `user/creator`).
   - Menambahkan kolom `created_by` pada `kartu_stok` agar setiap pergerakan tercatat user penanggung jawabnya.

---

## 2. Arsitektur Data & Schema Migration

### 2.1. Migration: `purchase_orders`
Menambahkan kolom `tanggal_tempo` (date, nullable) setelah `skema_bayar`:
```sql
ALTER TABLE purchase_orders ADD COLUMN tanggal_tempo DATE NULL;
CREATE INDEX idx_po_tanggal_tempo ON purchase_orders (tanggal_tempo);
```

### 2.2. Migration: `kartu_stok`
Menambahkan kolom `created_by` (foreignId to users, nullable) pada `kartu_stok`:
```sql
ALTER TABLE kartu_stok ADD COLUMN created_by BIGINT UNSIGNED NULL REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX idx_kartu_created_by ON kartu_stok (created_by);
```

### 2.3. Model Relationships & Logic
- **`App\Models\PurchaseOrder`**:
  - `$fillable` menambahkan `'tanggal_tempo'`.
  - `$casts` menambahkan `'tanggal_tempo' => 'date'`.
  - Pada `refreshStatusPembayaran()`: evaluasi overdue memeriksa `tanggal_tempo` jika `skema_bayar === 'tempo'`.
- **`App\Models\KartuStok`**:
  - `$fillable` menambahkan `'created_by'`.
  - Relasi `creator(): BelongsTo` ke `User`.
- **`App\Services\StokService`**:
  - Pada `post()`: otomatis set `'created_by' => auth()->id()`.

---

## 3. Detail Implementasi Modul

### 3.1. Purchasing Create & Edit Blade
- `resources/views/purchasing/create.blade.php`:
  - Di sebelah select `skema_bayar`, tampilkan blok kondisional:
    `x-show="skemaBayar === 'tempo'"` dengan label `Jatuh Tempo Pembayaran (Deadline Tempo)`.
  - Pada pemilihan produk, tampilkan badge `HPP Lama: Rp X.XXX / Satuan`.
  - Di kolom HPP/Unit, tampilkan komparasi HPP Baru vs HPP Lama.
- `resources/views/purchasing/show.blade.php`:
  - Metadata header & kartu Ringkasan Keuangan menampilkan tanggal tempo dan indikator jika sudah melewati batas waktu.

### 3.2. Purchasing Index Blade & DataTables
- Penyesuaian konfigurasi kolom DataTables:
  - Tab PO: 8 kolom murni (`No Invoice`, `Supplier`, `Lokasi`, `Tgl PO`, `ETA`, `Tgl Tiba`, `Status PO`, `Aksi`).
  - Tab AP: 10 kolom keuangan (`No Invoice`, `Supplier`, `Tgl PO`, `Skema`, `Total Tagihan`, `Telah Dibayar`, `Sisa Tagihan`, `Jatuh Tempo`, `Status Bayar`, `Aksi`).
  - Tab Approval: Menyaring `status === 'diajukan'` dengan tombol review/persetujuan instan bagi manajer.

### 3.3. Log Pergerakan Stok
- **Route**:
  - `GET /stok/log-pergerakan` -> `StokController@logPergerakan`
  - `GET /stok/log-pergerakan/data` -> `StokController@logPergerakanData`
- **Controller**:
  - Menghitung stok sebelum: `tipe == 'in' ? (saldo_setelah - qty) : (saldo_setelah + qty)`.
  - Format kolom `Sebelum --> Sesudah`: e.g. `100 ➔ 150 KG`.
  - Resolver pembuat/aktor: `$k->creator?->name ?? 'Sistem'`.
- **View**:
  - `resources/views/stok/pergerakan-log.blade.php` lengkap dengan filter gudang, jenis mutasi (IN/OUT), dan rentang tanggal.

---

## 4. Pelaksanaan 6 Fase Enterprise
1. **Fase 1: Discovery & Requirements** -> Selesai & Divalidasi.
2. **Fase 2: System Architecture** -> Schema, relasi, dan rute dirancang optimal.
3. **Fase 3: Sprint Planning** -> Breakdown tugas, urutan eksekusi, dan alokasi komponen.
4. **Fase 4: Development Execution** -> Migrasi, Models, Controller, Views.
5. **Fase 5: Testing & QA** -> Automated feature tests & sanitasi regresi.
6. **Fase 6: Security & Compliance** -> Proteksi izin otorisasi (*gate/policy*) & sanitasi query.
