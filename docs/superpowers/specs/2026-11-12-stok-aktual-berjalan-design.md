# Design Spec: Kolom Stok Aktual, Stok Berjalan, dan Badge Evaluasi pada Stok & Mutasi

- **Tanggal:** 2026-11-12
- **Status:** Menunggu Approval User
- **Pendekatan Terpilih:** Pendekatan 1 (Pemisahan Murni `Stok Aktual` & `Stok Berjalan`) + Kolom `Status` Tunggal

---

## 1. Intent & Why

Pada [`StokController::data()`](app/Http/Controllers/StokController.php#L221-L277) dan [`resources/views/stok/index.blade.php`](resources/views/stok/index.blade.php#L60-L61), **`Kolom Stok`** saat ini menghitung `stok fisik + inbound (PO & transfer berjalan)`, sedangkan **`Kolom Rencana`** menghitung `(stok fisik + inbound) - alokasi_aktif` (bahan/kemas) atau `+ rencana_batch` (produk jadi).

Di **Gudang Bahan Baku Pusat** (yang merupakan filter default), tidak ada alokasi produksi aktif (`alokasi_aktif = 0`), sehingga kedua kolom hampir selalu menampilkan angka kembar (misalnya `800 pcs` dan `800 pcs`) dan tidak cocok dengan saldo fisik terakhir di **Kartu Stok / Ledger** maupun modal **Mutasi Manual** (`50 pcs`). Perubahan ini memisahkan stok fisik aktual dan stok yang sedang berjalan, serta menyatukan badge evaluasi `AMAN` / `ORDER` dengan rumus tunggal `stok_aktual + stok_berjalan - min_analisa`.

---

## 2. Goals, Non-Goals, & Constraints

### Goals
1. Mengubah **`Kolom Stok`** menjadi **`Stok Aktual`** yang murni menampilkan saldo fisik aktual di gudang (`stok.qty_saat_ini`), sehingga 100% konsisten dengan **Kartu Stok (Ledger)** dan modal **Mutasi Manual**.
2. Mengganti **`Kolom Rencana`** menjadi **`Stok Berjalan`** yang hanya menampilkan kuantitas stok yang sedang dalam proses berjalan menuju gudang tersebut (tanpa mencampur saldo fisik `qty_saat_ini`).
3. Menyatukan perhitungan badge **`AMAN` / `ORDER`** menggunakan rumus:
   `(stok_aktual + stok_berjalan) - min_analisa` (bila `min_analisa > 0` dan selisihnya `<= 0` → **`ORDER`**, selainnya → **`AMAN`**), lalu menampilkannya pada kolom **`Status`** terpisah agar angka stok tidak membingungkan.

### Non-Goals
- Tidak mengubah rumus kolom **`Batas Min (Analisa)`**, modul **Analisa Stok** (`AnalisaService`), **HPP**, **Nilai Stok**, **Mutasi Manual**, **Opname Fisik**, maupun **Kartu Stok (Ledger)**.
- Tidak mengubah tabel alokasi/kesiapan bahan (`Kolom Rencana` per batch) di [`batches/show.blade.php`](resources/views/batches/show.blade.php#L184).

### Constraints & Call-Outs
- **Stok Berjalan untuk Produk Jadi (`produk_jadi`):** tetap mengikutsertakan batch produksi berjalan (`status IN ['rencana', 'release']` dari `$rencanaBatchMap`) ditambah transfer `dikirim` menuju gudang tujuan, agar rencana output multi-produk PRD v2.4 tetap terpantau sebagai barang jadi yang sedang berjalan. *(Keputusan desain eksplisit.)*
- **Penghapusan pengurangan `alokasi_aktif` pada tabel Stok & Mutasi:** sesuai rumus badge final `stok aktual + stok berjalan - min analisa`, subquery `alokasi_aktif` tidak lagi memotong angka pada halaman ini; 3 feature test PRD v2.4 ([`StockAlertTest`](tests/Feature/StockAlertTest.php), [`StokRencanaAndFormattingTest`](tests/Feature/StokRencanaAndFormattingTest.php), [`BladeUiPrd24AlignmentTest`](tests/Feature/BladeUiPrd24AlignmentTest.php)) akan diperbarui mengikuti kontrak kolom dan rumus badge baru.
- **Format kuantitas:** tetap menggunakan `StokController::formatQty()`.

---

## 3. Approaches Considered

1. **Pendekatan 1 — Pemisahan Murni `Stok Aktual` vs `Stok Berjalan` + Kolom `Status` Tunggal *(Dipilih)***:
   `Stok Aktual` (`50 pcs`) dan `Stok Berjalan` (`750 pcs`) dipisah tegas sehingga tidak pernah kembar dan cocok dengan Ledger, sementara badge `AMAN`/`ORDER` dihitung dari `(stok_aktual + stok_berjalan) - min_analisa` dan diletakkan di kolom **`Status`** tersendiri.
2. **Pendekatan 2 — `Stok Aktual` vs `Proyeksi (Fisik + Berjalan ± Batch)`**:
   Mempertahankan proyeksi total di kolom kedua (`800 pcs`), namun berisiko kembali menampilkan angka kembar (`50` dan `50`) saat tidak ada PO berjalan maupun batch aktif.
3. **Pendekatan 3 — Pemisahan Murni + Sub-label Alokasi di `Stok Aktual`**:
   Mirip Pendekatan 1 tetapi menambah baris sub-teks alokasi batch pada sel `Stok Aktual`; ditolak karena tidak sesuai dengan rumus evaluasi tunggal `stok aktual + stok berjalan - min analisa` dan membuat sel lebih padat.

---

## 4. Specification

### A. [`app/Http/Controllers/StokController.php`](app/Http/Controllers/StokController.php) (`data()`)
1. **Hapus subquery `alokasi_aktif`** dari `$query` karena tidak lagi digunakan dalam kalkulasi kolom maupun badge.
2. **Kolom `stok_aktual`:**
   - Ambil `$fisik = (float) $s->qty_saat_ini`.
   - Format tampilan: `$this->formatQty($fisik) . ' ' . $s->produk?->satuan`.
3. **Kolom `stok_berjalan`:**
   - Hitung `$inbound`: PO aktif (status `berjalan`, `diajukan`, `disetujui`, `diproses_supplier`, `dikirim_ke_gudang`, `parsial` untuk item `bahan`/`kemas` di `$gudangPusatId`) + Transfer `dikirim` / Request Transfer `diproses`/`dikirim` (`$inboundRtMap` untuk key `produk_id_gudang_id`).
   - Untuk `produk_jadi`: `$berjalan = $inbound + (float) ($rencanaBatchMap[$rtKey] ?? 0)`. Untuk selainnya (`bahan`/`kemas`): `$berjalan = $inbound`.
   - Format tampilan: jika `$berjalan <= 0`, render span redup (`text-muted-foreground`) bernilai `0 <satuan>`; jika `> 0`, render span bernilai `<qty> <satuan>`.
4. **Kolom `status_stok` (badge evaluasi):**
   - Ambil `$batasMin = $this->analisa->getBatasMinimumForStok($s->produk_id, $s->gudang_id, $batasMinMap)`.
   - Hitung `$proyeksi = (float) $s->qty_saat_ini + $berjalan`.
   - Hitung `$selisih = $proyeksi - $batasMin`.
   - `$isOrder = $batasMin > 0 && $selisih <= 0`.
   - Render badge **`ORDER`** (merah) atau **`AMAN`** (hijau) lengkap dengan atribut `title` informatif (contoh saat `$batasMin > 0`: `Aktual (50) + Berjalan (750) - Min (200) = 600 pcs`).
5. Update `rawColumns(['stok_berjalan', 'status_stok', 'action'])`.

### B. [`resources/views/stok/index.blade.php`](resources/views/stok/index.blade.php)
1. Perbarui `<thead>` tabel (`#L55-L68`) menjadi:
   `SKU` | `Produk` | `Gudang` | `Batas Min (Analisa)` | `Stok Aktual` | `Stok Berjalan` | `Status` | *(jika `$canSeePrice`: `HPP` | `Nilai Stok`)* | `Aksi`.
2. Perbarui array `cols` DataTables (`#L225-L240`) untuk menggunakan `stok_aktual` (rata kanan), `stok_berjalan` (rata kanan), dan `status_stok` (rata tengah), sebelum `hpp`/`nilai_stok` & `action`.

### C. Verifikasi (`tests/Feature/`)
1. [`StockAlertTest`](tests/Feature/StockAlertTest.php): verifikasi bahwa `stok_aktual` murni menampilkan saldo fisik, `stok_berjalan` hanya menampilkan PO/transfer/batch berjalan, dan `status_stok` mengevaluasi `stok_aktual + stok_berjalan - min_analisa <= 0` (`ORDER` saat di bawah batas min, lalu menjadi `AMAN` saat ada PO/transfer berjalan yang menutupi kekurangan).
2. [`StokRencanaAndFormattingTest`](tests/Feature/StokRencanaAndFormattingTest.php): sesuaikan pengecekan ke `stok_aktual` (`1.000 ml`, `50 pcs`), `stok_berjalan` (`0 ml` untuk bahan tanpa inbound, `10 pcs` untuk produk jadi dengan rencana batch 10), dan formatting angka tanpa `.00`.
3. [`BladeUiPrd24AlignmentTest`](tests/Feature/BladeUiPrd24AlignmentTest.php): verifikasi bahwa `stok_aktual` = `10` dan `stok_berjalan` menjumlahkan multi-output (`150`) + fallback legacy (`50`) = `200` (tanpa mencampur saldo fisik `10`).

