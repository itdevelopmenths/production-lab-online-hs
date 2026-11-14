# Context Arsitektur: Pemilihan & Penambahan SKU ke Klasifikasi ABC serta Profil Analisa

- **Tanggal:** 2026-11-11
- **Design Spec:** `docs/superpowers/specs/2026-11-11-sku-klasifikasi-abc-design.md`
- **Sprint Plan:** `docs/superpowers/plans/2026-11-11-sku-klasifikasi-abc.md`

---

## Ringkasan Perubahan Arsitektur

1. **Skema & Relasi (`produk.klasifikasi_abc_id`):**
   - Migrasi `database/migrations/2026_11_11_000001_add_klasifikasi_abc_id_to_produk_table.php` menambahkan FK nullable `produk.klasifikasi_abc_id` (referensi `klasifikasi_abc.id`, `nullOnDelete()`) + indeks `idx_produk_klasifikasi_abc_id`, serta backfill idempoten hanya dari baris `analisa_impor` / `analisa_impor_meta` yang `klasifikasi_abc_id`-nya non-null.
   - Model `Produk::klasifikasiAbc()` (`BelongsTo`) dan `KlasifikasiAbc::produks()` (`HasMany`) tersedia dan tercatat otomatis pada `MasterDataAudit` via trait `AuditableMasterData`.

2. **Domain Layer Terpusat (`App\Services\AnalisaService`):**
   - `isImporProfile(?string $profilAnalisa)`: menyamakan `'impor'` dan `'bahan_impor'` agar SKU baru dari Master Produk otomatis terjaring oleh `generateImpor()`, `compareSuppliersForProduct()`, `selectSupplierForProduct()`, dan `refreshAffectedProductsFromPo()`.
   - `cleanupWorkingTablesForProfile(Produk $produk, ?string $targetProfil)`: membersihkan working table profil lama (`AnalisaLokal`, `RekomendasiOrderLokal`, `LeadTimeLokal` vs `AnalisaImpor`, `LeadTimeImpor`) saat SKU berpindah profil atau menjadi `produk_jadi`/nonaktif, sambil mempertahankan histori input (`AnalisaLokalInput`, `LeadTimeLokalStage`, `AnalisaImporMeta`).
   - `syncProdukKlasifikasiAbc()`, `assignKlasifikasiAbcToSkus()`, dan `addSkuToAnalisa()`: menjaga sinkronisasi 2 arah antara `produk`, `analisa_impor`, dan `analisa_impor_meta`, termasuk mendukung pelepasan murni (`klasifikasi_abc_id = null` tanpa masuk daftar Kelas `C`).
   - Isolasi strict di `lokalEligibleProdukIds()`, `imporEligibleProdukIds()`, `finalisasi()`, `getBatasMinimumMap()`, dan `getItemPerluOrderSummary()`.

3. **Tiga Titik Masuk UI & Endpoints:**
   - **Master Produk** (`ProdukController`, `produk/_form.blade.php`, `produk/index.blade.php`): dropdown `Klasifikasi ABC (Impor)` saat profil `Impor`, koersi ke `null` untuk `lokal`/`produk_jadi`, serta kolom badge `Analisa / ABC`.
   - **Master Klasifikasi ABC** (`KlasifikasiAbcController`, `klasifikasi-abc/index.blade.php`): route dipindah ke middleware `can:analisa.view` (`can:analisa.manage` untuk mutasi), modal **Kelola SKU** dengan multi-pilih kandidat SKU, filter profil, konfirmasi pengalihan profil ke `Impor`, dan tombol **Keluarkan** (`unassignSku`).
   - **Analisa Stok** (`AnalisaController`, `analisa/index.blade.php`, `modal-add-sku.blade.php`, `table-impor.blade.php`, `modal-manual-impor.blade.php`): tombol **Tambah SKU** di tab `Lokal` & `Impor` dengan pemilihan profil tujuan (`Impor` + Klasifikasi ABC atau `Lokal`), quick-select ABC inline di tabel Impor, dan sinkronisasi penuh pada `updateManualImpor()` (termasuk reset ke `null` serta guard SKU nonaktif/`produk_jadi`).

