# Analisa Impor: Tab Lead Time, Periode Global, Kolom Analisa — Design

Tanggal: 2026-11-28 · Status: disetujui user ("sesuaikan saran anda")

## Intent

Menu Analisa Stok Impor disamakan polanya dengan Analisa Lokal dan disederhanakan:
parameter perhitungan (jumlah hari periode) diatur sekali untuk semua SKU, rincian lead
time per tahap bisa dilihat/diedit, dan tab Analisa menampilkan kolom yang dipakai rumus.

## Keputusan user

1. Tambah sub-tab **Lead Time** di Analisa Impor, seperti "Rincian 8 Tahap Lead Time" di Lokal.
2. **Periode mulai & akhir global** (satu untuk semua data impor), bukan per baris. Yang dipakai
   rumus adalah jumlah harinya.
3. Tab **Analisa** impor menampilkan: dropdown Kelas ABC | Avg LT | Buffer Days | Total OUT |
   ADU Base | ADU ETA | Review Period | Safety Stock.
4. Rumus:
   - OUT = pemakaian bahan dari kartu stok tipe `out` referensi `batch_produksi` (sumber sama
     dengan lokal: `DemandAduService::aduBahanPusat`).
   - ADU Base = OUT ÷ jumlah hari periode (default jendela 4 bulan).
   - **ADU ETA = ADU Base × (1 + Avg LT ÷ Review Period)** (opsi (b), dipilih user).
   - Safety Stock (hari) = (Max LT − Avg LT) + Buffer Days.
5. **ADU Pivot dihapus dulu** dari rumus dan UI.

## Calls yang dibuat (bisa dikoreksi)

- **Buffer Days** = tambahan hari dari Klasifikasi ABC (`analisa_impor.tambahan_buffer_hari`).
  **Safety Stock (hari)** = nilai yang sekarang disimpan di `analisa_impor.buffer_days`
  ((max−avg)+ABC). Tidak ada perubahan skema/semantik kolom; `safety_stock` (unit) tetap dipakai
  rekomendasi, riwayat, dan Create PO.
- Min Stock = ADU ETA × Avg LT; Target = ADU ETA × (Avg LT + SS hari + RP) — struktur tetap,
  hanya ADU ETA yang berubah. Review Period ≤ 0 diperlakukan 30 (default impor).
- Periode global disimpan di tabel baru `analisa_setting` (key/value). Kosong = jendela bergulir
  4 bulan terakhir (perilaku sekarang). Hanya berlaku untuk impor; lokal tidak berubah.
- Lead time stage impor: tabel baru `lead_time_impor_stage` (kolom sama dengan
  `lead_time_lokal_stage`). Simpan stage → `lead_time_impor.lead_time_average/max` = total stage
  (sumber manual). Data otomatis tetap dari `supplier_produk_lt_stage_summary` bila ada histori PO.
- Modal Edit Input impor: input periode, LT avg/max, dan blok pivot dihapus dari UI. LT diedit di
  tab Lead Time. Endpoint `update-manual-impor` tetap menerima `lead_time_average/max` (kompatibel).
- Kolom DB pivot (`adu_pivot`, `grup_pivot`, `persentase_pivot`) tetap ada; `adu_pivot` ditulis 0.
- Tab Rekomendasi impor tidak diubah kecuali hilangnya baris "Periode" per item.

## Approaches

- **A. Mirror lokal + setting global (dipilih).** Tabel stage impor sendiri, partial tabel stage
  dipakai bersama lokal/impor, setting periode di tabel key/value. Konsisten dengan lokal.
- B. Lead Time impor read-only dari histori PO. Lebih kecil, tapi SKU tanpa histori tidak bisa
  diatur per tahap; tidak "seperti lokal".
- C. Periode via config/env. Tidak bisa diubah user dari UI; ditolak.

## Non-goals

- Mengubah Analisa Lokal, Fulfillment, Riwayat, atau rumus rekomendasi selain ADU ETA.
- Migrasi data pivot lama.

## Risiko yang perlu diketahui user

ADU ETA × (1 + LT/RP) dengan LT 60 & RP 30 membuat ADU ETA = 3× ADU Base, lalu dikali lagi dengan
LT di Min/Target. Angka rekomendasi order impor akan naik signifikan dibanding sekarang.
