# RANCANGAN SISTEM SUPPLY CHAIN ONLINE (SC ONLINE)
**Alur Sistem, Struktur Menu, Functional Requirement, Rumus Analisa, ERD & Rancangan Database**
*CV. Heaven Scent — Divisi IT*
*Versi 2.0 — Konsolidasi Rancangan*

## 1. Ringkasan Eksekutif
Dokumen ini adalah versi terbaru (v2.0) dari rancangan sistem SC Online, menggantikan draft v1. Perubahan utama dibanding v1:
* Menu digabung per proses bisnis (Purchasing, Produksi, Request & Transfer, Stok & Mutasi, Master Data, Laporan) ditambah modul baru Analisa Stok.
* Ditambahkan simulasi Role (Purchasing, Gudang, Operasional, Fulfillment, Manager), sekaligus menjadi dasar hak akses per menu.
* Alert Stock dirancang 2 kolom (Kolom Stok vs Kolom Rencana) agar Rencana Produksi yang masih berstatus draft sudah memberi sinyal dini tanpa menutupi sinyal stok fisik.
* Ditambahkan modul Analisa Stok yang mereplikasi persis struktur dan rumus 3 file Excel acuan (Analisa Lokal, Analisa Import, Analisa Permintaan Fulfillment), dan dijadikan sumber tunggal Batas Minimum & Target Stock bagi seluruh sistem.
* Alur Request & Transfer disatukan jadi satu mesin generik dengan 2 bentuk pipeline (Request dengan approval, dan Transfer langsung), dipakai untuk seluruh pergerakan stok internal.
* Poin yang belum bisa dipastikan dari sumber data ditandai NEED CONFIRMATION di sepanjang dokumen dan dirangkum ulang pada bab terakhir.

## 2. Ruang Lingkup, Aktor & Role
SC Online mencakup rantai pasok dari pembelian bahan baku/kemas sampai produk jadi diterima customer. Pada v2.0, aktor bisnis dipetakan menjadi Role aplikasi yang menentukan menu yang terlihat dan aksi yang boleh dilakukan:

| Role | Contoh User | Tanggung Jawab Utama | Menu yang Diakses |
| --- | --- | --- | --- |
| Purchasing | Rina | Analisa kebutuhan bahan, PO ke supplier, pembayaran/hutang | Dashboard, Purchasing, Analisa Stok, Stok & Mutasi, Master Data, Laporan |
| Gudang | Budi | Terima barang datang, kelola stok bahan baku/kemas, proses Request & Transfer | Dashboard, Request & Transfer, Stok & Mutasi, Master Data, Laporan |
| Operasional | Dedi | Rencana & eksekusi produksi, stock opname, kirim produk jadi | Dashboard, Produksi, Request & Transfer, Stok & Mutasi, Master Data, Laporan |
| Fulfillment | Sari | Analisa & distribusi produk jadi ke gudang cabang, kirim ke customer | Dashboard, Analisa Stok, Request & Transfer, Stok & Mutasi, Master Data, Laporan |
| Manager | Andyka | Approval PO & Request/Transfer, pengawasan lintas modul | Seluruh menu, dengan wewenang approval di semua tahap |

## 3. Alur Sistem End-to-End (Best Case)

### 3.1 Alur Purchasing
1. Purchasing (atau Fulfillment untuk produk jadi) membuka menu Analisa Stok, mengisi/meninjau data historis (Terjual/Out Rata-rata 4 Bulan, Stok Saat Ini, dsb.) sesuai profil item (Lokal/Impor).
2. Sistem menghitung ADU, Buffer, Batas Minimum, Target Stock, Selisih, dan Status (Order/Tidak) mengikuti rumus pada Bab 6.
3. Untuk item berstatus Order, Purchasing menekan tombol “Buat PO” agar sistem membuat draft PO otomatis (SKU, qty, dan harga ter-carry) — atau tetap dapat membuat PO manual dari menu Purchasing tanpa melalui Analisa.
4. PO (yang kini dapat memuat banyak item dalam satu dokumen) diajukan oleh Purchasing, disetujui oleh Manager, lalu dikirim ke Gudang.
5. Gudang mengonfirmasi penerimaan fisik barang (Barang Datang) per item pada PO, mencocokkan qty diterima terhadap qty PO; stok Gudang Pusat bertambah otomatis dan PO berstatus Selesai.
6. Purchasing mencatat pembayaran (Tempo/Termin/Pelunasan) terhadap PO; status lunas/belum lunas dan sisa tagihan terekam otomatis.

### 3.2 Alur Produksi
1. Operasional membuat Rencana Produksi (Batch); sistem menghitung kebutuhan bahan dari BOM dan langsung membuat Alokasi Bahan (status Aktif) tanpa mengurangi stok fisik.
2. Batch dimajukan ke Release & Issue: bahan ditarik riil dari stok Gudang Operasional, Alokasi berubah menjadi mutasi keluar.
3. Batch diselesaikan; qty baik dan qty rusak tercatat, yield terhitung otomatis.
4. Operasional dapat melakukan Stock Opname untuk membandingkan pemakaian teoritis (BOM) dengan pemakaian aktual.
5. Batch yang Selesai dapat diajukan sebagai Request & Transfer jenis “Kirim Produk Jadi” ke Fulfillment Pusat, langsung dari menu Produksi atau tidak.

### 3.3 Alur Request & Transfer
Seluruh pergerakan stok internal memakai satu mesin generik dengan dua bentuk pipeline:
* **Pipeline Request** (dipakai untuk permintaan bahan Gudang → Operasional, karena sifatnya “mengajukan” dan butuh alokasi sumber daya): Draft → Diajukan → Disetujui → Diproses & Dikirim → Selesai.
* **Pipeline Transfer Langsung** (dipakai untuk retur bahan, kirim produk jadi ke Fulfillment, transfer antar gudang Fulfillment, dan retur produk jadi): Draft → Dikirim → Selesai, tanpa tahap approval karena sumber data tidak menyebutkan proses persetujuan untuk jenis-jenis ini.

Satu dokumen Request & Transfer kini dapat memuat banyak item sekaligus (lihat Bab 8), dengan qty diminta/dikirim/diterima dicatat per item sehingga selisih dapat diketahui per SKU.

### 3.4 Alur Fulfillment & Distribusi
1. Fulfillment Pusat menerima produk jadi dari Operasional melalui Request & Transfer jenis Kirim Produk Jadi.
2. Fulfillment memeriksa Analisa Stok Produk Jadi per gudang cabang; hasil agregasi ALL menjadi sinyal kebutuhan produksi tambahan ke Operasional.
3. Fulfillment membuat Request & Transfer jenis Antar Gudang Fulfillment untuk mendistribusikan stok ke cabang (mis. Pusat → SBY → Solo/Jakarta).
4. Mutasi keluar ke customer dicatat di Stok & Mutasi, dengan kategori terpisah untuk kebutuhan Affiliate/KOL.
5. Produk cacat diretur ke Operasional melalui Request & Transfer jenis Retur Produk Jadi.

### 3.5 Alur Analisa Stok & Keterhubungannya
1. Hasil Batas Minimum & Target Stock dari Analisa Stok otomatis menjadi acuan status Order/Tidak pada Kolom Stok dan Kolom Rencana di menu Stok & Mutasi — tidak ada lagi input Batas Minimum manual terpisah.
2. Tombol Buat PO di Analisa Stok membuat draft PO bertanda “Dari Analisa”, tetap dapat diedit/dilengkapi oleh Purchasing sebelum diajukan.
3. Setiap kali user menekan “Simpan Snapshot ke Riwayat”, hasil hitungan saat itu (tanggal, item/gudang, Batas Minimum, Target Stock, Status, Qty Order, dan siapa yang menyimpan) dicatat permanen di tab Riwayat untuk penelusuran keputusan di kemudian hari.

## 4. Struktur Menu Aplikasi
Menu digabung per proses bisnis, bukan per submenu di dokumen sumber. Tanda ✓ berarti role tersebut dapat mengakses menu; Manager selalu dapat mengakses seluruh menu dan melakukan aksi approval di tahap manapun.

| Menu | Purchasing | Gudang | Operasional | Fulfillment | Manager |
| --- | --- | --- | --- | --- | --- |
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ |
| Purchasing | ✓ | - | - | - | ✓ |
| Produksi (Batch) | - | - | ✓ | - | ✓ |
| Request & Transfer | - | ✓ | ✓ | ✓ | ✓ |
| Analisa Stok | ✓ | - | - | ✓ | ✓ |
| Stok & Mutasi | ✓ | ✓ | ✓ | ✓ | ✓ |
| Master Data | ✓ | ✓ | ✓ | ✓ | ✓ |
| Laporan | ✓ | ✓ | ✓ | ✓ | ✓ |

### 4.1 Rincian Sub-Tab per Menu
| Menu | Sub-Tab / Bagian |
| --- | --- |
| Dashboard | Ringkasan kartu statistik + daftar “perlu tindakan saya”, disesuaikan per role. |
| Purchasing | Daftar PO (Lokal & Impor, approval, Barang Datang terintegrasi) • Pembayaran. |
| Produksi (Batch) | Daftar & form Rencana Produksi, kebutuhan bahan otomatis dari BOM, status batch, aksi Ajukan Transfer. |
| Request & Transfer | Filter per jenis (Request Bahan, Retur Bahan, Kirim Produk Jadi, Antar Fulfillment, Retur Produk Jadi) • form Buat Baru • daftar dengan aksi sesuai status & role. |
| Analisa Stok | Bahan Lokal • Bahan Impor • Produk Jadi (Fulfillment) • Riwayat. |
| Stok & Mutasi | Stok (dengan status Alert 2 kolom) • Mutasi (manual in/out + kartu stok) • Opname. |
| Master Data | Produk • Gudang • Supplier • Lead Time & Buffer • BOM. |
| Laporan | Produksi • Pemakaian Bahan • Defect • Low Stock • Purchasing/Hutang • Pergerakan Stok Fulfillment. |

## 5. Functional Requirements

### 5.1 Dashboard
| FR-ID | Deskripsi Requirement |
| --- | --- |
| FR-DASH-01 | Sistem menampilkan kartu ringkasan yang relevan dengan role yang sedang login (mis. jumlah PO/Request & Transfer yang perlu tindakan, jumlah item berstatus ORDER). |
| FR-DASH-02 | Sistem menampilkan daftar transaksi (PO/Request & Transfer) yang menunggu tindakan dari role yang sedang login, dengan tautan langsung ke menu terkait. |

### 5.2 Purchasing
| FR-ID | Deskripsi Requirement |
| --- | --- |
| FR-PUR-01 | Satu Purchase Order dapat memuat banyak item (header-detail) — SKU, qty, dan harga dicatat per item pada tabel detail, sesuai kebutuhan bahwa satu PO tidak dibatasi satu SKU. |
| FR-PUR-02 | PO melalui alur status Draft → Diajukan → Disetujui (oleh Manager) → Dikirim ke Gudang → Selesai (via konfirmasi Barang Datang oleh Gudang). |
| FR-PUR-03 | Barang Datang dicatat per item PO; qty diterima dapat berbeda dari qty PO (selisih tercatat), dan hanya PO berstatus Dikirim ke Gudang yang dapat diproses Barang Datang. |
| FR-PUR-04 | Payment dicatat per PO dengan skema Tempo/Termin/Pelunasan; sistem menjumlah seluruh pembayaran suatu PO untuk menentukan status Lunas/Belum Lunas dan sisa tagihan. |
| FR-PUR-05 | Harga per satuan (HPP) dihitung otomatis dari harga total item dibagi qty, ditampilkan sebagai bagian dari detail item PO. |

### 5.3 Produksi (Batch)
| FR-ID | Deskripsi Requirement |
| --- | --- |
| FR-PRD-01 | Saat Rencana Produksi dibuat, sistem menghitung kebutuhan bahan dari BOM × qty rencana dan langsung membuat Alokasi Bahan berstatus Aktif tanpa mengubah stok fisik. |
| FR-PRD-02 | Batch mengikuti alur status Rencana → Release & Issue (bahan ditarik riil dari stok Operasional, Alokasi dilepas) → Selesai (qty baik/rusak dicatat) atau Dibatalkan (Alokasi dilepas tanpa mutasi stok). |
| FR-PRD-03 | Batch berstatus Selesai dapat langsung diajukan sebagai Request & Transfer jenis Kirim Produk Jadi ke Fulfillment Pusat tanpa berpindah menu. |

### 5.4 Request & Transfer
| FR-ID | Deskripsi Requirement |
| --- | --- |
| FR-RT-01 | Satu dokumen Request & Transfer dapat memuat banyak item (header-detail); qty diminta, qty dikirim, dan qty diterima dicatat per item agar selisih diketahui per SKU. |
| FR-RT-02 | Sistem menyediakan 5 jenis Request & Transfer: Request Bahan (Gudang↔Operasional), Retur Bahan (Operasional→Gudang), Kirim Produk Jadi (Operasional→Fulfillment Pusat), Antar Gudang Fulfillment, dan Retur Produk Jadi (Fulfillment→Operasional). |
| FR-RT-03 | Jenis Request Bahan memakai pipeline 5 tahap dengan approval (Draft→Diajukan→Disetujui→Diproses→Selesai); jenis lainnya memakai pipeline 3 tahap tanpa approval (Draft→Dikirim→Selesai). |
| FR-RT-04 | Setiap transisi status yang menyebabkan stok berpindah tervalidasi terhadap role yang berwenang pada tahap tersebut (mis. hanya Gudang yang dapat memproses & mengirim Request Bahan). |
| FR-RT-05 | Stok berkurang dari gudang asal saat dokumen dikirim/diproses, dan bertambah ke gudang tujuan saat diterima; setiap perubahan tercatat di Kartu Stok dengan referensi ke dokumen Request & Transfer terkait. |

### 5.5 Analisa Stok
| FR-ID | Deskripsi Requirement |
| --- | --- |
| FR-ANL-01 | Sistem menyediakan 3 profil analisa (Bahan Lokal, Bahan Impor, Produk Jadi/Fulfillment) mengikuti struktur dan rumus dari file Excel acuan (lihat Bab 6). |
| FR-ANL-02 | Hasil Batas Minimum & Target Stock dari Analisa Stok menjadi sumber tunggal yang dipakai perhitungan Kolom Stok/Kolom Rencana pada menu Stok & Mutasi — tidak ada input Batas Minimum manual di Master Data. |
| FR-ANL-03 | Untuk item/baris berstatus Order (Bahan Lokal) atau PO (Bahan Impor), user dapat menekan tombol Buat PO untuk membuat draft Purchase Order otomatis (ditandai “Dari Analisa”); pembuatan PO manual dari menu Purchasing tetap tersedia sebagai alternatif. |
| FR-ANL-04 | User dapat menekan tombol Simpan Snapshot pada tiap profil analisa untuk mencatat hasil hitungan saat itu (tanggal, item/gudang, Batas Minimum, Target Stock, Status, Qty Order, dan role yang menyimpan) ke tab Riwayat, sebagai jejak audit sederhana. |

### 5.6 Stok & Mutasi, Master Data, Laporan
| FR-ID | Deskripsi Requirement |
| --- | --- |
| FR-STK-01 | Tab Stok menampilkan Kolom Stok (stok fisik + inbound, tidak dipengaruhi rencana produksi) dan Kolom Rencana (memperhitungkan alokasi/rencana aktif), masing-masing dengan status Order/Tidak. |
| FR-STK-02 | Tab Mutasi mencatat penyesuaian stok manual dan menampilkan Kartu Stok gabungan dari seluruh sumber mutasi (PO, Request & Transfer, produksi, mutasi manual). |
| FR-STK-03 | Tab Opname membandingkan pemakaian bahan teoritis (BOM × realisasi batch) dengan pemakaian aktual. |
| FR-MST-01 | Master Data menyediakan CRUD untuk Produk, Gudang (dengan relasi induk-cabang), Supplier, Lead Time & Buffer (parameter dasar saja, Batas Minimum mengikuti Analisa Stok), dan BOM. |
| FR-LAP-01 | Laporan menyajikan ringkasan Produksi, Pemakaian Bahan, Defect, Low Stock, Purchasing/Hutang, dan Pergerakan Stok Fulfillment, dihitung otomatis dari data transaksi berjalan. |

## 6. Rumus dan Istilah dalam Sistem (Analisa Stok)
Bab ini merangkum seluruh istilah dan rumus yang dipakai modul Analisa Stok, disalin persis dari file Excel acuan (Analisa Lokal, Analisa Import, Analisa Permintaan Fulfillment) agar penamaan yang dilihat user di aplikasi konsisten dengan yang mereka pahami selama ini.

### 6.1 Istilah Umum
| Istilah | Definisi |
| --- | --- |
| ADU (Average Daily Usage) | Rata-rata pemakaian/penjualan harian. |
| Lead Time | Waktu sejak kebutuhan disadari sampai barang siap dipakai/dijual. |
| Buffer / Safety Stock | Cadangan akibat ketidakpastian lead time. Catatan: pada file Analisa Lokal, “Safety Stock” dinyatakan dalam satuan HARI (identik dengan Total Buffer); pada file Analisa Import, “Safety Stock” dinyatakan dalam satuan QTY (ADU × Buffer Days). Kedua penamaan dipertahankan apa adanya per profil agar sesuai sumber. |
| Review Period | Interval antar peninjauan ulang kebutuhan stok. |
| Batas Minimum / Minimum Stock | Ambang bawah sebelum dianggap kritis (ROP). |
| Target Stock | Stok ideal setelah order berikutnya tiba. |
| Satuan Order / MOQ | Kelipatan pembulatan qty order, disimpan sebagai master data per SKU. |
| Klasifikasi ABC | Kategori item (Wajib A/A/B/C) yang menentukan tambahan hari buffer pada profil Impor: Wajib A = 4 hari, A = 4 hari, B = 2 hari, C = 0 hari. |

### 6.2 Profil Bahan Lokal
1. Lead Time dirinci per tahapan (Perencanaan, Approval, Supplier Confirm, Payment, PO, Pengemasan, Pengiriman, Unloading, Input), masing-masing untuk skenario Rata-rata dan Maksimum:
   * Total Average Lead Time = jumlah seluruh tahapan skenario rata-rata.
   * Total Max Lead Time = jumlah seluruh tahapan skenario maksimum.
   * Total Buffer (= “Safety Stock” dalam hari) = (Total Max Lead Time − Total Average Lead Time) + Tambahan Buffer manual.
2. Analisa:
   * ADU = Terjual (Rata-Rata 4 Bulan) ÷ 30.
   * Batas Minimum = ADU × (Total Average Lead Time + Safety Stock).
   * Target Stock = ADU × (Total Average Lead Time + Safety Stock + Review Period).
3. Analisa Order:
   * Order/Tidak = (Stok Saat Ini + Akan Datang) ≤ Batas Minimum.
   * Selisih = (Stok Saat Ini + Akan Datang) − Batas Minimum.
   * Qty Order = pembulatan ke atas \|Selisih\| ke kelipatan Satuan Order (MOQ), bila status Order.
   * Total Nominal Order = Qty Order × Harga per Satuan.

### 6.3 Profil Bahan Impor
Berlaku dua varian rumus tergantung apakah item memiliki pecahan varian (mis. warna) atau tidak:
* Out = Persentase Varian × Out Rata-rata 4 Bulan (Gudang), untuk item ber-varian; atau langsung Out Rata-rata 4 Bulan (Gudang) untuk item tanpa varian.
* ADU Base = Out ÷ 122 (≈ 4 bulan).
* ADU ETA (ber-varian) = ADU Base + (ADU Base × Lead Time Average ÷ 30).
* ADU ETA (tanpa varian) = ADU Base + (Lead Time Average ÷ Review Period) — direplikasi persis dari file sumber; lihat catatan NEED CONFIRMATION Bab 10 mengenai kewajaran satuan rumus ini.
* Buffer Days = (Lead Time Max − Lead Time Average) + Tambahan Hari (dari Klasifikasi ABC).
* Safety Stock (qty) = ADU ETA × Buffer Days.
* Minimum Stock = ADU ETA × Lead Time Average.
* Target Stock = ADU ETA × (Lead Time Average + Review Period) + Safety Stock.
* Proyeksi = Stok Saat Ini + Inbound (Before ETA) − (ADU ETA × Lead Time Average).
* Selisih = Proyeksi − Target Stock.
* Status = “PO” bila Selisih < 0, selain itu “Tidak PO”.
* Qty Order = pembulatan ke atas \|Selisih\| ke kelipatan Satuan Order (MOQ), bila Status = PO.

### 6.4 Profil Produk Jadi (Fulfillment)
Dihitung per gudang, lalu diagregasi (mengikuti pola sheet “Analisa Permintaan Fulfillment”):
* ADU (per gudang) = Terjual (Rata-Rata 4 Bulan) ÷ 30.
* Batas Minimum (per gudang) = ADU × (Lead Time Distribusi + Buffer Distribusi).
* Target Stock (per gudang) = ADU × (Lead Time Distribusi + Buffer Distribusi + Review Period).
* ALL — Batas Minimum (Total) = jumlah Batas Minimum seluruh gudang; Target Stock (Total) = jumlah Target Stock seluruh gudang; Stok Saat Ini (Total) = jumlah Stok Saat Ini seluruh gudang.
* Akan Datang (level ALL) = hanya diambil dari gudang yang menerima langsung dari Operasional (pada data contoh: Fulfillment Pusat).
* Stok All = Stok Saat Ini (Total) + Akan Datang.
* Order/Tidak (level ALL) = Stok All ≤ Target Stock (Total).
* Selisih = Stok All − Target Stock (Total); Qty Order = pembulatan \|Selisih\| ke Satuan Order, bila Order.
* Hasil ORDER pada level ini adalah sinyal kebutuhan produksi tambahan ke Operasional, bukan PO ke supplier — sehingga tidak ada aksi Buat PO pada profil ini.

## 7. Entity Relationship Diagram (ERD)
Diagram ERD menjabarkan entitas dan relasinya. Warna kelompok: biru tua = Master Data & Stok, cokelat = Purchasing, hijau = Request & Transfer, ungu = Produksi, merah = Analisa Stok. (Lihat visual ERD dari dokumen asli).

### 7.1 Penjelasan Entitas Utama
| Entitas | Deskripsi | Relasi Utama |
| --- | --- | --- |
| PRODUK | Master seluruh SKU: bahan baku, kemas, produk jadi. | 1:N ke BOM, STOK, KARTU_STOK, PURCHASE_ORDER_ITEM, dsb. |
| GUDANG | Master gudang lintas tipe, mendukung relasi induk-cabang (self-reference) untuk Fulfillment berjenjang. | 1:N ke STOK, KARTU_STOK, BATCH_PRODUKSI, REQUEST_TRANSFER. |
| STOK | Saldo stok terkini per kombinasi produk + gudang. | Diperbarui oleh setiap transaksi yang menyebabkan pergerakan stok. |
| KARTU_STOK | Log seluruh mutasi stok (buku besar), menyimpan referensi ke dokumen sumber (PO, Request & Transfer, Batch, dsb.) melalui referensi_tipe + referensi_id. | |
| PURCHASE_ORDER / PURCHASE_ORDER_ITEM | Header PO dan daftar item di dalamnya — satu PO dapat memuat banyak item, sesuai kebutuhan yang diminta. | 1:N ke PAYMENT, BARANG_DATANG. |
| REQUEST_TRANSFER / REQUEST_TRANSFER_ITEM | Header dokumen pergerakan stok internal dan daftar item di dalamnya — satu dokumen dapat memuat banyak item. | Dapat mereferensikan BATCH_PRODUKSI (untuk jenis Kirim Produk Jadi). |
| BATCH_PRODUKSI / ALOKASI_BAHAN | Rencana & eksekusi produksi, beserta alokasi bahan yang tercipta begitu batch dibuat. | 1:N ke STOCK_OPNAME, REQUEST_TRANSFER (referensi). |
| ANALISA_LOKAL_INPUT / LEAD_TIME_STAGE / ANALISA_IMPOR_META / ANALISA_IMPOR_VARIAN / ANALISA_FULFILLMENT_INPUT | Input & parameter Analisa Stok per profil, sumber tunggal Batas Minimum & Target Stock. | 1:1 atau 1:N ke PRODUK/GUDANG sesuai profil. |
| RIWAYAT_ANALISA | Snapshot hasil Analisa Stok yang sengaja disimpan user sebagai jejak audit. | N:1 ke APP_USER (dicatat oleh). |

## 8. Rancangan Database

### 8.1 Master Data

**PRODUK**
Master seluruh SKU: bahan baku, kemas, dan produk jadi.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1001 |
| sku | VARCHAR(30), unik | Ya | ALK-01 |
| nama | VARCHAR(150) | Ya | Alkohol 96% |
| tipe | VARCHAR(20) | Ya | bahan (bahan/kemas/produk_jadi) |
| satuan | VARCHAR(10) | Ya | L |
| satuan_order_moq | NUMERIC(15,2) | Ya | 5000 |
| created_at, updated_at | TIMESTAMP | Ya | 2026-09-07 10:00:00 |

**BOM**
Kebutuhan bahan per 1 unit produk jadi.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| produk_jadi_id | BIGINT (FK → PRODUK.id) | Ya | id SKU GOH-P50 |
| bahan_id | BIGINT (FK → PRODUK.id) | Ya | id SKU ALK-01 |
| qty_per_unit | NUMERIC(15,4) | Ya | 0.05 |

**GUDANG**
Master gudang lintas tipe, dengan relasi induk-cabang untuk Fulfillment berjenjang.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| kode | VARCHAR(20), unik | Ya | FF-SBY |
| nama | VARCHAR(150) | Ya | Fulfillment SBY |
| tipe | VARCHAR(30) | Ya | fulfillment_cabang |
| parent_gudang_id | BIGINT (FK → GUDANG.id) | Tidak | 3 (id Fulfillment Pusat) |
| status | VARCHAR(10) | Ya | aktif (aktif/nonaktif) |

**SUPPLIER**
Master supplier lokal & impor.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| nama | VARCHAR(150) | Ya | PT Alkohol Nusantara |
| kategori | VARCHAR(10) | Ya | lokal (lokal/impor) |
| kontak | VARCHAR(100) | Tidak | 081234500001 |
| alamat | TEXT | Tidak | Sidoarjo, Jawa Timur |
| termin_default | VARCHAR(15) | Tidak | termin (tempo/termin/pelunasan) |

**APP_USER**
User aplikasi beserta role akses.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| nama | VARCHAR(100) | Ya | Rina |
| email | VARCHAR(150), unik | Ya | rina@heavenscent.id |
| role | VARCHAR(20) | Ya | purchasing |
| status | VARCHAR(10) | Ya | aktif (aktif/nonaktif) |

### 8.2 Stok & Kartu Stok

**STOK**
Saldo stok terkini per kombinasi produk + gudang (PK gabungan).
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| produk_id | BIGINT (PK gabungan, FK → PRODUK.id) | Ya | id SKU ALK-01 |
| gudang_id | BIGINT (PK gabungan, FK → GUDANG.id) | Ya | id Gudang Pusat |
| qty_saat_ini | NUMERIC(15,2) | Ya | 8000 |
| updated_at | TIMESTAMP | Ya | 2026-09-07 10:00:00 |

**KARTU_STOK**
Log seluruh mutasi stok (buku besar), dengan referensi polimorfik ke dokumen sumber.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| tanggal | DATE | Ya | 2026-09-07 |
| produk_id | BIGINT (FK → PRODUK.id) | Ya | id SKU ALK-01 |
| gudang_id | BIGINT (FK → GUDANG.id) | Ya | id Gudang Pusat |
| tipe | VARCHAR(5) | Ya | in (in/out) |
| qty | NUMERIC(15,2) | Ya | 4000 |
| saldo_setelah | NUMERIC(15,2) | Ya | 12000 |
| referensi_tipe | VARCHAR(30) | Ya | purchase_order |
| referensi_id | BIGINT | Ya | id Purchase Order terkait |
| catatan | VARCHAR(255) | Tidak | Barang Datang PO-2026-001 |

### 8.3 Purchasing (Header-Detail)

**PURCHASE_ORDER**
Header PO — satu PO dapat memuat banyak item.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| no_po | VARCHAR(30), unik | Ya | PO-2026-001 |
| supplier_id | BIGINT (FK → SUPPLIER.id) | Ya | id PT Alkohol Nusantara |
| tanggal | DATE | Ya | 2026-09-01 |
| eta | DATE | Tidak | 2026-09-12 |
| sumber_dana | VARCHAR(50) | Tidak | Kas Purchasing |
| status | VARCHAR(25) | Ya | diajukan |
| dari_analisa | BOOLEAN | Ya | true |
| created_by | BIGINT (FK → APP_USER.id) | Ya | id Rina |
| created_at, updated_at | TIMESTAMP | Ya | 2026-09-01 09:00:00 |

**PURCHASE_ORDER_ITEM**
Satu baris per item dalam sebuah PO.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| po_id | BIGINT (FK → PURCHASE_ORDER.id) | Ya | id PO-2026-001 |
| produk_id | BIGINT (FK → PRODUK.id) | Ya | id SKU ALK-01 |
| qty | NUMERIC(15,2) | Ya | 4000 |
| harga_total | NUMERIC(18,2) | Ya | 60000000 |

**PAYMENT**
Beberapa payment dapat terjadi untuk satu PO (termin bertahap).
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| po_id | BIGINT (FK → PURCHASE_ORDER.id) | Ya | id PO-2026-001 |
| skema | VARCHAR(15) | Ya | termin (tempo/termin/pelunasan) |
| tanggal_bayar | DATE | Ya | 2026-09-15 |
| nominal | NUMERIC(18,2) | Ya | 30000000 |
| bukti_file | VARCHAR(255) (path file) | Tidak | bukti/inv-001.pdf |

**BARANG_DATANG**
Header penerimaan barang untuk satu PO.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| po_id | BIGINT (FK → PURCHASE_ORDER.id) | Ya | id PO-2026-001 |
| tanggal_terima | DATE | Ya | 2026-09-12 |
| kondisi | VARCHAR(20) | Ya | baik (baik/rusak_sebagian) |

**BARANG_DATANG_ITEM**
Qty diterima dicatat per item PO.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| bardat_id | BIGINT (FK → BARANG_DATANG.id) | Ya | id Bardat #1 |
| po_item_id | BIGINT (FK → PURCHASE_ORDER_ITEM.id) | Ya | id item ALK-01 pada PO-2026-001 |
| qty_diterima | NUMERIC(15,2) | Ya | 4000 |

### 8.4 Request & Transfer (Header-Detail)

**REQUEST_TRANSFER**
Header dokumen pergerakan stok internal.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| no_transaksi | VARCHAR(30), unik | Ya | TR-2026-0001 |
| jenis | VARCHAR(30) | Ya | req_bahan |
| gudang_asal_id | BIGINT (FK → GUDANG.id) | Tidak | id Gudang Pusat (null untuk kirim_produk_jadi) |
| gudang_tujuan_id | BIGINT (FK → GUDANG.id) | Tidak | id Gudang Operasional |
| referensi_batch_id | BIGINT (FK → BATCH_PRODUKSI.id) | Tidak | id Batch B1 (khusus kirim_produk_jadi) |
| status | VARCHAR(20) | Ya | diajukan |
| catatan | TEXT | Tidak | Kebutuhan produksi minggu ini |
| created_by | BIGINT (FK → APP_USER.id) | Ya | id Dedi |
| created_at, updated_at | TIMESTAMP | Ya | 2026-09-07 08:30:00 |

**REQUEST_TRANSFER_ITEM**
Satu baris per item dalam dokumen.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| request_transfer_id | BIGINT (FK → REQUEST_TRANSFER.id) | Ya | id TR-2026-0001 |
| produk_id | BIGINT (FK → PRODUK.id) | Ya | id SKU ALK-01 |
| qty_diminta | NUMERIC(15,2) | Ya | 2000 |
| qty_dikirim | NUMERIC(15,2) | Tidak | 2000 |
| qty_diterima | NUMERIC(15,2) | Tidak | 1950 |

### 8.5 Produksi

**BATCH_PRODUKSI**
Rencana & eksekusi produksi.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| no_batch | VARCHAR(30), unik | Ya | B-2026-0001 |
| produk_id | BIGINT (FK → PRODUK.id) | Ya | id SKU GOH-P50 |
| qty_rencana | NUMERIC(15,2) | Ya | 80000 |
| qty_baik | NUMERIC(15,2) | Tidak | 76000 |
| qty_rusak | NUMERIC(15,2) | Tidak | 4000 |
| gudang_tujuan_rencana_id | BIGINT (FK → GUDANG.id) | Ya | id Fulfillment SBY |
| status | VARCHAR(15) | Ya | rencana |
| tanggal | DATE | Ya | 2026-09-10 |

**ALOKASI_BAHAN**
Tercipta otomatis saat batch dibuat, tanpa mengubah stok fisik.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| batch_id | BIGINT (FK → BATCH_PRODUKSI.id) | Ya | id Batch B-2026-0001 |
| bahan_id | BIGINT (FK → PRODUK.id) | Ya | id SKU ALK-01 |
| qty_dialokasikan | NUMERIC(15,2) | Ya | 4000 |
| status | VARCHAR(15) | Ya | aktif |

**STOCK_OPNAME**
Membandingkan pemakaian bahan teoritis (BOM) dengan aktual.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| batch_id | BIGINT (FK → BATCH_PRODUKSI.id) | Ya | id Batch B-2026-0001 |
| bahan_id | BIGINT (FK → PRODUK.id) | Ya | id SKU ALK-01 |
| pemakaian_teoritis | NUMERIC(15,2) | Ya | 4000 |
| pemakaian_aktual | NUMERIC(15,2) | Ya | 4050 |
| keterangan | VARCHAR(255) | Tidak | Tumpah saat pencampuran |

### 8.6 Analisa Stok

**ANALISA_LOKAL_INPUT**
Satu baris per produk berprofil Lokal.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| produk_id | BIGINT (FK → PRODUK.id, unik) | Ya | id SKU ALK-01 |
| terjual_rata_rata_4bulan | NUMERIC(15,2) | Ya | 39303.25 |
| review_period | INTEGER (hari) | Ya | 15 |
| stok_saat_ini | NUMERIC(15,2) | Ya | 8000 |
| akan_datang | NUMERIC(15,2) | Ya | 0 |
| harga_per_satuan | NUMERIC(15,2) | Ya | 526 |

**LEAD_TIME_STAGE**
9 tahap × 2 skenario (average/max) per produk Lokal.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| produk_id | BIGINT (FK → PRODUK.id) | Ya | id SKU ALK-01 |
| skenario | VARCHAR(10) | Ya | average (average/max) |
| tahap | VARCHAR(20) | Ya | supplier_confirm |
| jumlah_hari | INTEGER | Ya | 2 |
| tambahan_buffer_hari | INTEGER | Tidak | 2 |

**ANALISA_IMPOR_META**
Satu baris per produk berprofil Impor.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| produk_id | BIGINT (FK → PRODUK.id, unik) | Ya | id SKU BTL-P50 |
| punya_varian | BOOLEAN | Ya | true |
| out_total_4bulan | NUMERIC(15,2) | Ya | 47312 |
| lead_time_average | NUMERIC(10,2) | Ya | 81.75 |
| lead_time_max | NUMERIC(10,2) | Ya | 114 |
| klasifikasi_abc | VARCHAR(10) | Ya | a (wajib_a/a/b/c) |
| review_period | INTEGER (hari) | Ya | 30 |
| harga_per_satuan | NUMERIC(15,2) | Ya | 1500 |

**ANALISA_IMPOR_VARIAN**
Untuk item tanpa varian, cukup 1 baris.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| analisa_impor_meta_id | BIGINT (FK → ANALISA_IMPOR_META.id) | Ya | id meta BTL-P50 |
| nama_varian | VARCHAR(50) | Tidak | Bening |
| persentase_distribusi | NUMERIC(5,4) | Ya | 0.6 |
| stok_saat_ini | NUMERIC(15,2) | Ya | 1573 |
| inbound_before_eta | NUMERIC(15,2) | Ya | 10044 |

**ANALISA_FULFILLMENT_INPUT**
Satu baris per gudang Fulfillment.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| gudang_id | BIGINT (FK → GUDANG.id, unik) | Ya | id Fulfillment SBY |
| terjual_rata_rata_4bulan | NUMERIC(15,2) | Ya | 90000 |
| lead_time_distribusi | INTEGER (hari) | Ya | 2 |
| buffer_distribusi | INTEGER (hari) | Ya | 1 |
| review_period | INTEGER (hari) | Ya | 15 |
| stok_saat_ini | NUMERIC(15,2) | Ya | 4000 |
| akan_datang | NUMERIC(15,2) | Tidak | 0 |

**RIWAYAT_ANALISA**
Snapshot manual, tidak menimpa data analisa yang sedang berjalan.
| Field | Tipe | Not Null | Keterangan (contoh) |
| --- | --- | --- | --- |
| id | BIGINT (PK, auto increment) | Ya | 1 |
| tanggal | DATE | Ya | 2026-09-07 |
| tipe | VARCHAR(30) | Ya | bahan_lokal |
| item_label | VARCHAR(100) | Ya | ALK-01 |
| batas_minimum | NUMERIC(15,2) | Ya | 5250 |
| target_stock | NUMERIC(15,2) | Ya | 7100 |
| status | VARCHAR(10) | Ya | order |
| qty_order | NUMERIC(15,2) | Tidak | 25000 |
| dicatat_oleh | BIGINT (FK → APP_USER.id) | Ya | id Rina |

## 9. Riwayat Keputusan
| No | Topik | Keputusan |
| --- | --- | --- |
| 1 | Struktur Alert Stock | Ditampilkan sebagai 2 kolom independen: Kolom Stok (murni fisik+inbound) dan Kolom Rencana (memperhitungkan Rencana Produksi aktif). |
| 2 | Cakupan status Rencana Produksi pada Kolom Rencana | Rencana Produksi berstatus Rencana/Draft ke atas ikut dihitung, bukan hanya yang sudah Release & Issue. |
| 3 | Arah pengaruh Rencana Produksi | Bahan baku: pengurang ketersediaan. Produk jadi: penambah ketersediaan mendatang. |
| 4 | Pelepasan alokasi bahan | Otomatis dilepas tanpa mutasi stok bila batch Dibatalkan; berubah jadi mutasi keluar riil saat Release & Issue. |
| 5 | Struktur menu aplikasi | Digabung per proses bisnis (Purchasing, Produksi, Request & Transfer, Stok & Mutasi, Master Data, Laporan, Analisa Stok) — bukan 1:1 dari submenu dokumen sumber. |
| 6 | Role & hak akses | Disimulasikan lewat role switcher (Purchasing, Gudang, Operasional, Fulfillment, Manager) yang menentukan menu dan aksi yang diizinkan. |
| 7 | Mesin Request & Transfer | Satu mesin generik dengan 2 bentuk pipeline: Request (5 tahap, dengan approval, khusus Gudang↔Operasional) dan Transfer Langsung (3 tahap, tanpa approval, untuk jenis lainnya). |
| 8 | Modul Analisa Stok | Dibuat sebagai menu tersendiri yang mereplikasi persis struktur & rumus 3 file Excel acuan, dengan 3 profil: Bahan Lokal, Bahan Impor, Produk Jadi (Fulfillment). |
| 9 | Pembuatan PO dari Analisa | Tersedia tombol Buat PO otomatis dari hasil Analisa Stok; pembuatan PO manual dari menu Purchasing tetap tersedia sebagai opsi kedua. |
| 10 | Sumber Batas Minimum/Target Stock | Analisa Stok menjadi sumber tunggal; Master Lead Time & Buffer tidak lagi menyimpan Batas Minimum manual. |
| 11 | Riwayat/History Analisa | Disediakan sebagai snapshot manual (tombol Simpan Snapshot per profil), dicatat di tab Riwayat, bukan audit trail otomatis setiap perubahan input. |
| 12 | Struktur data Purchase Order & Request/Transfer | Dirancang header-detail — satu Purchase Order dapat memuat banyak item, dan satu Request & Transfer dapat memuat banyak item. |

## 10. Ringkasan NEED CONFIRMATION
* Struktur gudang fulfillment: benar berjenjang 3 tingkat (Pusat → Hub Regional → Sub-cabang) atau cukup 2 tingkat?
* Apakah mutasi khusus Affiliate/KOL dikecualikan dari perhitungan ADU pada Alert Stock produk jadi?
* Pada agregasi Fulfillment, “Akan Datang” hanya diambil dari satu gudang (Fulfillment Pusat) — apakah ini aturan tetap?
* Apakah Satuan Order/MOQ bersifat tetap per SKU atau bisa berubah mengikuti kesepakatan per PO?
* Apakah approval PO memerlukan lebih dari satu tingkat (mis. Purchasing → Manager → Finance) atau cukup satu tingkat approval oleh Manager seperti pada rancangan saat ini?
* Apakah retur dari Fulfillment ke Operasional memerlukan proses rework/produksi ulang di sistem, atau cukup dicatat sebagai pengurang stok?
* Rumus ADU ETA untuk item Impor tanpa varian (ADU Base + Lead Time ÷ Review Period) terlihat janggal secara satuan bila dibandingkan versi ber-varian — perlu dikonfirmasi ke pemilik proses apakah ini sudah sesuai maksud aslinya.
* Status Request & Transfer dirancang di level header (berlaku untuk seluruh item dalam satu dokumen) — apakah perlu status per item (sebagian item bisa berstatus berbeda dari yang lain dalam satu dokumen yang sama)?
* Apakah selisih qty pada Request & Transfer per item memerlukan catatan/alasan tersendiri per item, atau cukup satu catatan di level header dokumen?

---
*Dokumen ini adalah draft v2.0 untuk direview bersama tim IT dan pemilik proses (Purchasing, Gudang, Operasional, Fulfillment, Manager) sebelum dilanjutkan ke tahap implementasi database dan pengembangan sistem sesungguhnya.*
