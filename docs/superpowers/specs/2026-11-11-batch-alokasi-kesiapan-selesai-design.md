# Design Spec: Perbaikan Card Kesiapan Bahan & Tampilan State Selesai pada Alokasi Bahan Baku

- **Tanggal:** 2026-11-11
- **Status:** Menunggu Approval User
- **Pendekatan Terpilih:** Pendekatan 1 — Audit Realisasi Terpadu (`KartuStok` + `StockOpname`)

---

## 1. Intent & Why

Pada [`BatchController::show()`](app/Http/Controllers/BatchController.php#L257-L283), loop analisa peringatan dini saat ini menghitung kekurangan stok fisik (`$kurangFisik = max(0, $qtyAlokasi - $fisikOp)`) untuk setiap baris `alokasi_bahan` tanpa mengecek apakah alokasi tersebut masih `'aktif'` atau sudah `'dilepas'`, maupun status batch (`rencana`, `release`, `selesai`, `dibatalkan`).

Akibatnya, setelah **Release & Issue** memotong saldo stok GD Operasional (dan mengubah status alokasi menjadi `'dilepas'`), ketika saldo sisa gudang turun di bawah kebutuhan BOM awal, `$hasDeficitFisik` bernilai `true`. Card **Kesiapan Bahan** pada [`batches/show.blade.php`](resources/views/batches/show.blade.php#L93-L98) ikut menampilkan label menyesatkan **`Stok Kurang`** bahkan saat batch sudah berstatus `selesai`, dan tabel **Alokasi Bahan Baku** tetap menampilkan kolom stok *live* pra-rilis.

---

## 2. Goals, Non-Goals, & Constraints

### Goals
1. Memastikan baris `alokasi_bahan` dengan status selain `'aktif'` (khususnya `'dilepas'` atau `'dibatalkan'`) serta batch berstatus `selesai` / `dibatalkan` **tidak pernah memicu** `$hasDeficitFisik`, `$hasDeficitRencana`, KPI `Stok Kurang`, ataupun peringatan kekurangan di panel Kontrol Batch.
2. Menyajikan status KPI **Kesiapan Bahan** yang akurat sesuai siklus batch (`rencana`, `release`, `selesai`, `dibatalkan`).
3. Khusus ketika **`$batch->status === 'selesai'`**, mengganti kolom *live stock* pra-rilis pada card **Alokasi Bahan Baku (BOM Explode)** dengan kolom audit realisasi historis pelepasan bahan & opname.

### Non-Goals
- Tidak mengubah struktur kolom tabel **Alokasi Bahan Baku** pada status `rencana`, `release`, maupun `dibatalkan`.
- Tidak mengubah skema database atau mutasi `Release & Issue` / `Complete`.

### Constraints
- Wajib patuh terhadap `Model::preventLazyLoading(true)` (digunakan di `ProductionFlowTest` & `ViewRenderingSafetyTest`).
- Gunakan `NumberHelper::formatQty()` agar format angka kuantitas konsisten dengan tampilan eksisting.
- Jika batch `selesai` lama/hasil seeding tidak memiliki log `KartuStok` bertipe `out` (`referensi_tipe = 'batch_produksi'`), tampilkan fallback aman (`-`) tanpa mengarang angka saldo historis.

---

## 3. Approaches Considered

1. **Pendekatan 1 — Audit Realisasi Terpadu (`KartuStok` + `StockOpname`) *(Dipilih)***:
   Menampilkan `Di-Issue (BOM)`, snapshot `Saldo Pasca-Issue` + `Waktu Dilepas` dari log `out` `KartuStok` (`referensi_tipe = 'batch_produksi'`), serta ringkasan `Aktual Opname` & selisihnya saat batch `selesai`. Menambah 1 query terindeks ke `kartu_stok`, namun menjaga angka saldo tetap bersifat historis dan tidak berubah oleh mutasi berikutnya.
2. **Pendekatan 2 — Tanpa Query `KartuStok` (`Alokasi` + `StockOpname` saja)**:
   Nol query tambahan, tetapi tidak dapat menampilkan sisa saldo gudang tepat setelah pelepasan dan sedikit tumpang tindih dengan card editable `Stock Opname Pemakaian Bahan`.
3. **Pendekatan 3 — Minimalis (Hapus 3 kolom live stock saja)**:
   Hanya menyisakan `Bahan Baku`, `Kebutuhan`, `Status`, `Aksi`. Paling ringkas tetapi kurang memberi konteks audit pelepasan dan opname langsung pada card alokasi.

---

## 4. Specification

### A. [`BatchController::show()`](app/Http/Controllers/BatchController.php#L190-L297)
1. **Perbaikan pemicu defisit:**
   - Evaluasi `$kurangFisik` dan `$stokRencana` untuk pemicu `$hasDeficitFisik` & `$hasDeficitRencana` hanya berlaku jika `$a->status === 'aktif'` dan `!in_array($batch->status, ['selesai', 'dibatalkan'], true)`.
   - Nilai `stok_fisik_op`, `stok_rencana`, `stok_pusat` di `$earlyWarnings[$a->id]` tetap dihitung untuk tampilan tabel pada `rencana` dan `release` agar layout pra-selesai tetap kompatibel.
   - Siapkan state ringkasan `$readinessSummary` (`value`, `subtitle`) untuk Card **Kesiapan Bahan**:
     - `rencana`: `$hasDeficitFisik ? 'Stok Kurang' : ($hasDeficitRencana ? 'Defisit Rencana' : 'Siap Rilis')` (mempertahankan copy eksisting).
     - `release`: jika `$hasDeficitFisik` → `'Stok Kurang'`, `$hasDeficitRencana` → `'Defisit Rencana'`, selainnya (semua `'dilepas'`) → `'Bahan Di-Issue'` (`'Seluruh bahan telah dilepas dari GD Ops'`).
     - `selesai`: `'Selesai Dipakai'` (`'Bahan telah dilepas & batch selesai'`).
     - `dibatalkan`: `'Alokasi Dilepas'` (`'Batch dibatalkan tanpa potong stok'`).
2. **Data audit realisasi saat `$batch->status === 'selesai'`:**
   - Ambil log `KartuStok` dengan `referensi_tipe = 'batch_produksi'`, `referensi_id = $batch->id`, `tipe = 'out'`, `whereIn('produk_id', $bahanIds)`, `orderByDesc('created_at')`, lalu key by `produk_id`.
   - Petakan `$batch->opname` (sudah ter-eager-load) by `bahan_id` ke struktur per `$a->id` berisi:
     - `qty_issued`: `(float) ($kartu?->qty ?? $a->qty_dialokasikan)`
     - `saldo_pasca_issue`: `$kartu ? (float) $kartu->saldo_setelah : null`
     - `released_at`: `$kartu?->created_at ?? ($a->status === 'dilepas' ? $a->updated_at : null)`
     - `opname_aktual`: `$op ? (float) $op->pemakaian_aktual : null`
     - `opname_variance`: `$op ? $op->variance() : null`

### B. [`resources/views/batches/show.blade.php`](resources/views/batches/show.blade.php)
1. **KPI Card `Kesiapan Bahan`** (`#L93-L98`): gunakan `$readinessSummary['value']` dan `$readinessSummary['subtitle']`.
2. **Card `Alokasi Bahan Baku (BOM Explode)`** (`#L155-L242`):
   - Saat `$batch->status === 'selesai'`:
     - Subtitle: *"Realisasi bahan baku yang telah dilepas saat produksi & ringkasan hasil opname."*
     - Kolom tabel: `Bahan Baku` | `Di-Issue (BOM)` | `Saldo Pasca-Issue` | `Waktu Dilepas` | `Aktual Opname` | `Status` | `Aksi`.
     - `Status` menampilkan `<x-badge variant="success" size="sm">Dilepas · Selesai</x-badge>` ketika `$a->status === 'dilepas'` (fallback `ucfirst($a->status)` bila status lain).
     - `Aktual Opname` menampilkan qty aktual + chip selisih (`Sesuai BOM`, `+selisih` jika boros, `-selisih` jika hemat) atau `- (Belum opname)`.
   - Saat `$batch->status !== 'selesai'` (`rencana`, `release`, `dibatalkan`): mempertahankan kolom, badge, dan perilaku tabel seperti eksisting.

### C. Verifikasi (`tests/Feature/ProductionFlowTest.php`)
- Menambah test yang memverifikasi:
  1. Setelah batch di-release (stok sisa `0 < kebutuhan`) hingga di-complete (`selesai`) dan opname dicatat, `batches.show` **tidak** menampilkan `Stok Kurang` maupun `Perlu transfer dari GD Pusat`.
  2. Pada state `selesai`, card `Alokasi Bahan Baku` menampilkan `Selesai Dipakai`, `Dilepas · Selesai`, nilai `Saldo Pasca-Issue`, waktu rilis, dan ringkasan `Aktual Opname`, sementara pada state `release` tabel tetap memakai kolom eksisting tanpa memicu `Stok Kurang`.
