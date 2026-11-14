# Spesifikasi Arsitektur: Peningkatan Modul Stok & Mutasi
**Proyek:** Heaven Scent Enterprise — Production Lab Online  
**Dokumen:** `.agent/stok-mutasi-enhancements.md`  
**Fitur:** Perbaikan Filter Pencarian Data Stok, Isolasi Lokasi Kartu Stok (Ledger Scoping), dan Penampil Stok Sistem di Form Opname  

---

## 1. Problem Statement & User Requirements
1. **Filter Pencarian di `resources/views/stok/index.blade.php` Belum Berfungsi**:
   - Pencarian teks (SKU/Nama Produk/Gudang) di DataTables tidak menyaring data karena `StokController::data` menggunakan `addColumn` tanpa `filterColumn` pada kolom berelasi.
   - Toolbar atas hanya memiliki dropdown gudang dan kategori tanpa kotak input pencarian teks langsung.
2. **Kartu Stok di `resources/views/stok/ledger.blade.php` Menampilkan Seluruh Gudang**:
   - Saat pengguna mengklik "Kartu Stok" pada baris barang di gudang tertentu (contoh: *Oil BOP* di *Gudang Operasional*), tabel ledger menampilkan mutasi dari seluruh gudang.
   - Seharusnya kartu stok terisolasi khusus ke gudang terpilih, dengan fleksibilitas untuk beralih gudang melalui filter.
3. **Penampil Stok Sistem pada Form Opname di `resources/views/stok/opname.blade.php`**:
   - Saat menginput opname fisik, operator tidak mengetahui berapa saldo stok yang tercatat di sistem saat ini.
   - Diperlukan penampil realtime saldo sistem dan kalkulasi selisih (deviasi) saat kuantitas fisik dimasukkan.

---

## 2. Arsitektur Teknis Solusi

### A. Routing & Controllers
- `GET /stok/current-stock` $\to$ `stok.current-stock` (`StokController::currentStock`)
- `StokController::data`:
  - Menambahkan join `gudang` dan definisi `filterColumn` untuk `sku`, `nama`, dan `gudang_nama`.
  - Tautan kartu stok menyertakan parameter `gudang_id`: `route('stok.ledger', ['produk' => $s->produk_id, 'gudang_id' => $s->gudang_id])`.
- `StokController::ledger`:
  - Mengambil parameter `gudang_id` dan memuat daftar gudang yang dapat diakses.
- `StokController::ledgerData`:
  - Menyaring query berdasarkan `kartu_stok.gudang_id` jika parameter `gudang_id` diberikan.

### B. Frontend Layer
- `stok/index.blade.php`: Input pencarian teks terintegrasi ke DataTables.
- `stok/ledger.blade.php`: Dropdown filter gudang dengan pre-selected gudang dari tautan sebelumnya.
- `stok/opname.blade.php`: Komponen Alpine.js `opnameForm` dengan 3 kartu metrik perbandingan (Stok Sistem, Fisik Diinput, Estimasi Selisih).
