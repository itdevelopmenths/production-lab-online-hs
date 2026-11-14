# Master Produk, Analisa Stok (Generate-State Engine), & Purchasing UOM Conversion
## Sprint Planning & Enterprise Architecture Specification

- **File Path:** `.agent/sprint-planning-master-produk-analisa-stok.md`
- **Created Date:** 2026-09-16
- **Status:** 🚀 Active Sprint Execution
- **Architect & Lead:** Senior Software Architect & Project Lead

---

## 1. Domain Overview & Architectural Principles

Transformasi arsitektur ini memodernisasi 3 pilar domain inventori pabrik:

1. **Master Produk (Normalized Dimension)**:
   - Memecah penamaan produk menjadi 3 dimensi: `Kategori`, `Varian` (scoped per Kategori), dan `Nama Produk`.
   - Satu SKU = $\text{Nama Produk} \times \text{Varian}$.
   - Backward compatibility terjamin 100% via Eloquent Accessor `$produk->nama = "{$nama_produk} {$varian->nama}"`.
2. **Purchasing PO (Multi-UOM Purchase vs Base UOM Inventory)**:
   - Pemilihan satuan beli (Liter, Jerigen, Kilogram, Karung, Drum, dll) **HANYA ADA DI MODUL PURCHASING (PO)**.
   - Modul hilir (Barang Datang, Stok Saldo, Kartu Stok, Resep BOM, Batch Produksi, Analisa Stok) **HANYA MEMAKAI SATUAN DASAR (`ml`, `gr`, `pcs`)**.
   - Input: `qty_satuan_beli` (misal 5 Jerigen) $\rightarrow$ sistem otomatis mengonversi ke `qty` dasar ($5 \times 1.000 = 5.000\text{ ml}$).
   - HPP / Unit dihitung dan disimpan dalam satuan dasar (`Rp ... / ml` atau `Rp ... / gr`).
3. **Analisa Stok (State-Persisted Engine: Generate ➔ Checking ➔ Finalisasi)**:
   - Berpindah dari kalkulasi sesaat di memori (*on-the-fly*) menjadi *persistent state* di tabel operasional.
   - **Generate**: Membaca input, menjalankan kalkulasi formula analitik, dan menyimpan seluruh kolom hasil ke database.
   - **Checking (Generate Ulang)**: Memperbarui data aktif saat ada revisi buffer atau angka penjualan.
   - **Finalisasi**: Membekukan (*lock & freeze*) salinan hasil akhir ke `riwayat_analisa` untuk audit bulanan.

---

## 2. Database Schema & Data Dictionary

### 2.1 Master Produk (Kategori, Varian, Produk)

#### 1. Tabel `kategori`
```sql
CREATE TABLE kategori (
    id BIGSERIAL PRIMARY KEY,
    nama VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### 2. Tabel `varian`
```sql
CREATE TABLE varian (
    id BIGSERIAL PRIMARY KEY,
    kategori_id BIGINT NOT NULL REFERENCES kategori(id) ON DELETE CASCADE,
    nama VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT uq_varian_kategori_nama UNIQUE (kategori_id, nama)
);
```

#### 3. Perubahan Tabel `produk`
```sql
ALTER TABLE produk 
    ADD COLUMN kategori_id BIGINT NULL REFERENCES kategori(id) ON DELETE SET NULL,
    ADD COLUMN varian_id BIGINT NULL REFERENCES varian(id) ON DELETE SET NULL,
    ADD COLUMN nama_produk VARCHAR(150) NULL,
    ADD COLUMN faktor_konversi NUMERIC(15,4) DEFAULT 1.0000;
```
*Catatan Data Migration*: 
- Salin `nama` ke `nama_produk`.
- Buat kategori default sesuai `tipe` ('bahan_baku' $\rightarrow$ "Bahan Baku", 'kemas' $\rightarrow$ "Kemasan", 'produk_jadi' $\rightarrow$ "Eau de Parfum").
- Set `kategori_id` default, lalu ubah kolom menjadi `NOT NULL`.

---

### 2.2 Purchasing (Purchase Order Items UOM Conversion)

#### Perubahan Tabel `purchase_order_items`
```sql
ALTER TABLE purchase_order_items
    ADD COLUMN qty_satuan_beli NUMERIC(15,2) NULL,
    ADD COLUMN satuan_beli VARCHAR(20) NULL,
    ADD COLUMN faktor_konversi NUMERIC(15,4) DEFAULT 1.0000;
```
- `qty_satuan_beli`: Kuantitas dalam satuan beli (misal 5 jerigen).
- `satuan_beli`: Kode satuan beli (misal 'jerigen', 'l', 'kg').
- `qty`: Tetap dalam satuan dasar (misal 5.000 ml) $\rightarrow$ $\text{qty} = \text{qty\_satuan\_beli} \times \text{faktor\_konversi}$.
- `hpp_per_satuan`: Tetap dalam satuan dasar (misal Rp 100 / ml).

---

### 2.3 Analisa Stok Profil Lokal (4 Tabel)

#### 1. Tabel `lead_time_lokal_stage`
Menyimpan rincian 9 tahap lead time, 2 baris per SKU (`skenario` = 'average' dan 'max').
```sql
CREATE TABLE lead_time_lokal_stage (
    id BIGSERIAL PRIMARY KEY,
    produk_id BIGINT NOT NULL REFERENCES produk(id) ON DELETE CASCADE,
    skenario VARCHAR(10) NOT NULL, -- 'average' / 'max'
    perencanaan INTEGER NOT NULL DEFAULT 0,
    approval INTEGER NOT NULL DEFAULT 0,
    supplier_confirm INTEGER NOT NULL DEFAULT 0,
    payment INTEGER NOT NULL DEFAULT 0,
    po INTEGER NOT NULL DEFAULT 0,
    pengemasan INTEGER NOT NULL DEFAULT 0,
    pengiriman INTEGER NOT NULL DEFAULT 0,
    unloading INTEGER NOT NULL DEFAULT 0,
    input INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT uq_ltls_produk_skenario UNIQUE (produk_id, skenario)
);
```

#### 2. Tabel `lead_time_lokal`
Ringkasan Lead Time, 1 baris per SKU.
```sql
CREATE TABLE lead_time_lokal (
    id BIGSERIAL PRIMARY KEY,
    produk_id BIGINT NOT NULL UNIQUE REFERENCES produk(id) ON DELETE CASCADE,
    total_average_lead_time INTEGER NOT NULL DEFAULT 0,
    total_max_lead_time INTEGER NOT NULL DEFAULT 0,
    tambahan_buffer_hari INTEGER NULL DEFAULT 0,
    safety_stock INTEGER NOT NULL DEFAULT 0, -- (total_max - total_avg) + tambahan_buffer
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### 3. Tabel `analisa_lokal`
Working data hasil generate analisa lokal, 1 baris per SKU.
```sql
CREATE TABLE analisa_lokal (
    id BIGSERIAL PRIMARY KEY,
    produk_id BIGINT NOT NULL UNIQUE REFERENCES produk(id) ON DELETE CASCADE,
    total_average_lead_time INTEGER NOT NULL DEFAULT 0,
    safety_stock INTEGER NOT NULL DEFAULT 0,
    terjual_rata_rata_4bulan NUMERIC(15,2) NOT NULL DEFAULT 0,
    adu NUMERIC(15,4) NOT NULL DEFAULT 0, -- terjual_rata_rata_4bulan / 30
    review_period INTEGER NOT NULL DEFAULT 15,
    batas_minimum NUMERIC(15,2) NOT NULL DEFAULT 0, -- adu * (total_avg + safety_stock)
    target_stock NUMERIC(15,2) NOT NULL DEFAULT 0, -- adu * (total_avg + safety_stock + review_period)
    generated_at TIMESTAMP NOT NULL,
    generated_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### 4. Tabel `rekomendasi_order_lokal`
Working data rekomendasi pemesanan lokal, 1 baris per SKU.
```sql
CREATE TABLE rekomendasi_order_lokal (
    id BIGSERIAL PRIMARY KEY,
    produk_id BIGINT NOT NULL UNIQUE REFERENCES produk(id) ON DELETE CASCADE,
    batas_minimum NUMERIC(15,2) NOT NULL DEFAULT 0,
    target_stock NUMERIC(15,2) NOT NULL DEFAULT 0,
    stok_saat_ini NUMERIC(15,2) NOT NULL DEFAULT 0,
    akan_datang NUMERIC(15,2) NOT NULL DEFAULT 0,
    selisih NUMERIC(15,2) NOT NULL DEFAULT 0, -- (stok_saat_ini + akan_datang) - target_stock
    rumus_moq NUMERIC(15,2) NOT NULL DEFAULT 0, -- CEILING(|selisih|, moq)
    status VARCHAR(10) NOT NULL DEFAULT 'tidak', -- 'order' / 'tidak'
    rekomendasi_order NUMERIC(15,2) NOT NULL DEFAULT 0, -- rumus_moq / faktor_konversi
    harga_ml_pcs NUMERIC(15,2) NOT NULL DEFAULT 0,
    total_nominal_order NUMERIC(18,2) NOT NULL DEFAULT 0, -- rekomendasi_order * harga_ml_pcs
    generated_at TIMESTAMP NOT NULL,
    generated_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

### 2.4 Analisa Stok Profil Impor (2 Tabel)

#### 1. Tabel `lead_time_impor`
```sql
CREATE TABLE lead_time_impor (
    id BIGSERIAL PRIMARY KEY,
    produk_id BIGINT NOT NULL UNIQUE REFERENCES produk(id) ON DELETE CASCADE,
    lead_time_average NUMERIC(10,2) NOT NULL DEFAULT 0,
    lead_time_max NUMERIC(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### 2. Tabel `analisa_impor`
Menggabungkan Analisa + Rekomendasi Order Impor (1 baris per SKU).
```sql
CREATE TABLE analisa_impor (
    id BIGSERIAL PRIMARY KEY,
    produk_id BIGINT NOT NULL UNIQUE REFERENCES produk(id) ON DELETE CASCADE,
    out NUMERIC(15,2) NOT NULL DEFAULT 0,
    adu_base NUMERIC(15,4) NOT NULL DEFAULT 0, -- out / 122
    adu_eta NUMERIC(15,4) NOT NULL DEFAULT 0, -- adu_base + (lead_time / review_period)
    lead_time NUMERIC(10,2) NOT NULL DEFAULT 0,
    review_period INTEGER NOT NULL DEFAULT 30,
    klasifikasi_abc VARCHAR(10) NOT NULL DEFAULT 'c', -- 'wajib_a'(+4), 'a'(+4), 'b'(+2), 'c'(+0)
    buffer_days NUMERIC(10,2) NOT NULL DEFAULT 0, -- (lt_max - lt_avg) + tambahan_hari_abc
    safety_stock NUMERIC(15,2) NOT NULL DEFAULT 0, -- adu_eta * buffer_days
    minimum_stock NUMERIC(15,2) NOT NULL DEFAULT 0, -- adu_eta * lead_time
    target_stock NUMERIC(15,2) NOT NULL DEFAULT 0, -- adu_eta * (lead_time + safety_stock + review_period)
    stok_saat_ini NUMERIC(15,2) NOT NULL DEFAULT 0,
    inbound_before_eta NUMERIC(15,2) NOT NULL DEFAULT 0,
    proyeksi NUMERIC(15,2) NOT NULL DEFAULT 0, -- stok_saat_ini + inbound_before_eta - (adu_eta * lead_time)
    qty_order NUMERIC(15,2) NOT NULL DEFAULT 0, -- proyeksi - target_stock (negatif = kurang)
    po NUMERIC(15,2) NOT NULL DEFAULT 0, -- CEILING(|qty_order|, moq) jika status = order
    status VARCHAR(10) NOT NULL DEFAULT 'tidak', -- 'order' jika qty_order < 0
    generated_at TIMESTAMP NOT NULL,
    generated_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

## 3. Matriks Keluarga Satuan Pembelian (UOM Family Matrix)

| Kategori Keluarga | Satuan Dasar (Sistem) | Pilihan Satuan Beli (PO) | Konversi ke Satuan Dasar |
|:---|:---|:---|:---:|
| **Volume** | **`ml`** | • **Liter (`l`)**<br/>• **Mililiter (`ml`)**<br/>• **Jerigen**<br/>• **Drum**<br/>• **Galon** | $\times 1.000$<br/>$\times 1$<br/>$\times \text{kapasitas jerigen (mis. } 1.000\text{ / }5.000\text{ ml)}$<br/>$\times 200.000\text{ ml}$<br/>$\times 19.000\text{ / }5.000\text{ ml}$ |
| **Massa** | **`gr`** | • **Kilogram (`kg`)**<br/>• **Gram (`gr`)**<br/>• **Karung / Sak**<br/>• **Ton** | $\times 1.000$<br/>$\times 1$<br/>$\times \text{kapasitas sak (mis. } 25.000\text{ gr)}$<br/>$\times 1.000.000\text{ gr}$ |
| **Satuan Hitung** | **`pcs`** | • **Pieces (`pcs`)**<br/>• **Dus / Box / Karton**<br/>• **Pack** | $\times 1$<br/>$\times \text{isi dus (mis. } 100\text{ pcs)}$<br/>$\times \text{isi pack}$ |

*Aturan Emas*: HPP / Unit dihitung dan disimpan selalu dalam **Satuan Dasar** ($\text{Net Total} \div \text{Qty Dasar}$).

---

## 4. Breakdown Sprints & Work Breakdown Structure (WBS)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ SPRINT 1: Master Data Kategori, Varian & Normalisasi Produk [COMPLETED ✓]    │
│ Status: SELESAI (16 Sep 2026) | Tests: 11/11 Passed (50 assertions)          │
├──────────────────────────────────────────────────────────────────────────────┤
│ [x] 1.1 Migration DDL: tabel kategori, varian, dan alter tabel produk        │
│ [x] 1.2 Data Migration Seeder: Backfill data produk eksisting tanpa data loss│
│ [x] 1.3 Model & Eloquent: Kategori, Varian, dan Accessor getNamaAttribute()  │
│ [x] 1.4 CRUD Master Kategori & CRUD Master Varian di menu Master Data        │
│ [x] 1.5 Dynamic Dependent Dropdown (Kategori -> Varian) di form Create/Edit │
│ [x] 1.6 Unit & Feature Tests: KategoriVarianProdukTest                       │
└──────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│ SPRINT 2: Purchasing PO Satuan Beli & Live Konversi HPP Dasar               │
│ Status: SELESAI (16 Sep 2026) | Commit: a543877 | Tests: 7/7 Passed          │
├──────────────────────────────────────────────────────────────────────────────┤
│ [x] 2.1 Migration DDL: purchase_order_items (qty_satuan_beli, satuan_beli, dll) │
│ [x] 2.2 Alpine.js di create/edit PO: Dropdown Satuan Beli per Keluarga Satuan   │
│ [x] 2.3 Live Preview Konversi: "5 Liter x 1.000 = 5.000 ml"                      │
│ [x] 2.4 Live HPP Calculation: HPP selalu per ml / gr (Net / Qty Dasar)          │
│ [x] 2.5 Controller Purchasing store & update: simpan qty dasar & qty beli        │
│ [x] 2.6 View & Cetak PO: Tampilkan satuan beli dan kuantitas dasar              │
│ [x] 2.7 Feature Tests: PurchasingUomConversionTest                              │
└──────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│ SPRINT 3: Analisa Stok Profil Lokal (4 Tabel Engine: Generate-Checking)      │
│ Status: SELESAI (16 Sep 2026) | Commit: 0ac3122 | Tests: 6/6 Passed (83 ass) │
├──────────────────────────────────────────────────────────────────────────────┤
│ [x] 3.1 Migration DDL: 4 tabel lokal (lead_time_stage, lt_lokal, analisa, order)│
│ [x] 3.2 AnalisaService::generateLokal($produkId, $userId)                        │
│ [x] 3.3 Formula MOQ: CEILING(|selisih|, moq) & kalkulasi rekomendasi order       │
│ [x] 3.4 Controller & Routes: POST /analisa/generate-lokal                        │
│ [x] 3.5 UI Analisa Lokal: Tombol Generate, Checking, dan render tabel tersimpan │
│ [x] 3.6 Create PO otomatis dari Rekomendasi Order tersimpan                      │
│ [x] 3.7 Feature Tests: AnalisaLokalEngineTest                                    │
└──────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│ SPRINT 4: Analisa Stok Profil Impor (2 Tabel) & Finalisasi ke History       │
│ Estimasi: 3 Hari Kerja | Prioritas: High                                    │
├──────────────────────────────────────────────────────────────────────────────┤
│ 4.1 Migration DDL: lead_time_impor & analisa_impor                           │
│ 4.2 AnalisaService::generateImpor() dengan Klasifikasi ABC Buffer Days      │
│ 4.3 Controller & Routes: POST /analisa/generate-impor                        │
│ 4.4 Workflow Finalisasi: Kunci data kerja & salin ke riwayat_analisa        │
│ 4.5 UI Analisa Impor & Tab Riwayat Analisa                                  │
│ 4.6 Full Regression QA (PHPUnit all modules) & Vite asset build             │
│ 4.7 Dokumentasi Walkthrough & Release Notes                                  │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Risk Register & Mitigasi Teknis

| Risiko Teknis | Dampak | Strategi Mitigasi |
|:---|:---:|:---|
| **Foreign Key Error saat migrasi Produk** | Kritis | Tambah kolom `nullable` lebih dulu, jalankan seeder backfill, baru enforce constraint `NOT NULL`. |
| **Kode lama memanggil `$produk->nama`** | Kritis | Gunakan Eloquent Accessor `getNamaAttribute()` menggabungkan `nama_produk` + `varian.nama`. |
| **Pembagian nol pada faktor konversi** | Sedang | Gunakan fallback defensif: `coalesce(faktor_konversi, 1.0000)`. |
| **Salah pilih satuan beli (mis. alkohol beli kg)** | Sedang | Filter dropdown satuan beli hanya menampilkan keluarga yang cocok dengan satuan dasar produk. |
| **Data generate tertimpa tanpa sengaja** | Rendah | Tandai timestamp `generated_at` dan actor `generated_by`. Validasi status sebelum overwrite. |

---

## 6. Commit Convention Standard

Setiap fitur yang selesai pada masing-masing sprint wajib di-commit secara terisolasi dengan format:
- `feat(master-data): add kategori and varian models, migrations, and CRUD`
- `feat(produk): normalize produk with category and variant relation`
- `feat(purchasing): add purchase unit conversion and base unit hpp calculation`
- `feat(analisa): implement local profile 4-table generate engine`
- `feat(analisa): implement import profile and finalization locking workflow`
