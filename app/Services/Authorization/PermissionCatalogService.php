<?php

namespace App\Services\Authorization;

class PermissionCatalogService
{
    /**
     * Mengembalikan katalog permissions yang dikelompokkan secara terstruktur
     * berdasarkan domain bisnis dan modul aplikasi.
     *
     * @return array<string, array<string, array{label: string, icon?: string, actions: array<string, string>}>>
     */
    public function getGroupedCatalog(): array
    {
        return [
            'Master Data' => [
                'produk' => [
                    'label' => 'Master Produk',
                    'icon' => 'cube',
                    'actions' => [
                        'produk.view' => 'Lihat Daftar & Detail Produk',
                        'produk.create' => 'Tambah Produk Baru',
                        'produk.edit' => 'Ubah Data Produk',
                        'produk.delete' => 'Hapus Data Produk',
                    ],
                ],
                'gudang' => [
                    'label' => 'Master Fasilitas Gudang',
                    'icon' => 'building-office',
                    'actions' => [
                        'gudang.view' => 'Lihat Daftar Gudang',
                        'gudang.create' => 'Tambah Gudang Baru',
                        'gudang.edit' => 'Ubah Data Gudang',
                        'gudang.delete' => 'Hapus Gudang',
                    ],
                ],
                'supplier' => [
                    'label' => 'Master Supplier / Vendor',
                    'icon' => 'truck',
                    'actions' => [
                        'supplier.view' => 'Lihat Daftar Supplier',
                        'supplier.create' => 'Tambah Supplier Baru',
                        'supplier.edit' => 'Ubah Data Supplier',
                        'supplier.delete' => 'Hapus Data Supplier',
                    ],
                ],
                'uom' => [
                    'label' => 'Master Satuan Ukur (UOM)',
                    'icon' => 'scale',
                    'actions' => [
                        'uom.view' => 'Lihat Daftar Satuan',
                        'uom.create' => 'Tambah Satuan Baru',
                        'uom.edit' => 'Ubah Satuan',
                        'uom.delete' => 'Hapus Satuan',
                    ],
                ],
                'bom' => [
                    'label' => 'Formula Resep (BOM)',
                    'icon' => 'beaker',
                    'actions' => [
                        'bom.view' => 'Lihat Formula Resep Produk',
                        'bom.manage' => 'Atur & Ubah Komposisi BOM',
                        'bom.import' => 'Impor Data BOM (CSV Stamps POS)',
                    ],
                ],
                'divisi' => [
                    'label' => 'Master Divisi / Departemen',
                    'icon' => 'user-group',
                    'actions' => [
                        'divisi.view' => 'Lihat Daftar Divisi',
                        'divisi.create' => 'Tambah Divisi Baru',
                        'divisi.edit' => 'Ubah Data Divisi',
                        'divisi.delete' => 'Hapus Divisi',
                    ],
                ],
            ],

            'Operasional & Logistik' => [
                'purchasing' => [
                    'label' => 'Purchasing & Pengadaan (PO)',
                    'icon' => 'shopping-cart',
                    'actions' => [
                        'purchasing.view' => 'Lihat Riwayat & Dokumen PO',
                        'purchasing.price.view' => 'Lihat Harga Beli & Status Pembayaran AP',
                        'purchasing.create' => 'Buat Draft Pengajuan PO',
                        'purchasing.edit' => 'Ubah Draft Dokumen PO',
                        'purchasing.submit' => 'Ajukan PO ke Manajer',
                        'purchasing.approve' => 'Setujui (Approval) Pesanan PO',
                        'purchasing.receive' => 'Terima Barang Datang (Gudang)',
                        'purchasing.pay' => 'Catat Pembayaran & Pelunasan',
                        'purchasing.cancel' => 'Batalkan Dokumen PO',
                    ],
                ],
                'batch' => [
                    'label' => 'Batch Produksi Lab',
                    'icon' => 'clipboard-document-check',
                    'actions' => [
                        'batch.view' => 'Lihat Rencana Batch Produksi',
                        'batch.create' => 'Buat Rencana Batch Baru',
                        'batch.release' => 'Rilis Bahan (Issue & Potong Stok)',
                        'batch.complete' => 'Selesaikan Batch (Hasil Jadi & QC)',
                        'batch.cancel' => 'Batalkan Rencana Batch',
                        'batch.opname' => 'Stock Opname Selisih Batch',
                    ],
                ],
                'rt' => [
                    'label' => 'Request & Transfer Antar Gudang',
                    'icon' => 'arrows-right-left',
                    'actions' => [
                        'rt.view' => 'Lihat Dokumen Transfer',
                        'rt.create' => 'Buat Permintaan Transfer (RT)',
                        'rt.submit' => 'Ajukan Permintaan ke Manajer',
                        'rt.approve' => 'Setujui Permintaan Transfer',
                        'rt.process' => 'Proses Pengeluaran Barang',
                        'rt.ship' => 'Kirim Barang (Status In Transit)',
                        'rt.receive' => 'Terima Alokasi Barang di Tujuan',
                        'rt.cancel' => 'Batalkan Dokumen Transfer',
                    ],
                ],
            ],

            'Inventori & Analisis' => [
                'stok' => [
                    'label' => 'Stok & Kartu Inventori',
                    'icon' => 'circle-stack',
                    'actions' => [
                        'stok.view' => 'Lihat Saldo Stok & Buffer Alert',
                        'stok.view.all' => 'Akses Seluruh Gudang (Bypass Pembatasan Lokasi)',
                        'stok.mutasi' => 'Eksekusi Mutasi Stok Manual',
                        'stok.opname' => 'Stock Opname Fisik Aktual',
                        'stok.ledger.view' => 'Lihat Kartu Stok (Audit Trail)',
                    ],
                ],
                'analisa' => [
                    'label' => 'Analisa Kebutuhan Stok',
                    'icon' => 'chart-bar',
                    'actions' => [
                        'analisa.view' => 'Lihat Perhitungan Analisa Stok',
                        'analisa.manage' => 'Simpan Konfigurasi Parameter',
                        'analisa.snapshot' => 'Simpan Riwayat Snapshot Analisa',
                        'analisa.create_po' => 'Buat PO Otomatis dari Rekomendasi',
                    ],
                ],
                'report' => [
                    'label' => 'Laporan & Audit',
                    'icon' => 'document-chart-bar',
                    'actions' => [
                        'report.view' => 'Lihat Ringkasan & Laporan',
                        'report.export' => 'Ekspor Laporan ke Excel/CSV',
                    ],
                ],
            ],

            'Administrasi Sistem' => [
                'user' => [
                    'label' => 'Manajemen Pengguna (User)',
                    'icon' => 'users',
                    'actions' => [
                        'user.manage' => 'Kelola Data Pengguna (CRUD & Akun)',
                    ],
                ],
                'role' => [
                    'label' => 'Peran & Wewenang (Role)',
                    'icon' => 'shield-check',
                    'actions' => [
                        'role.manage' => 'Kelola Peran & Kustomisasi Permissions',
                    ],
                ],
            ],
        ];
    }

    /**
     * Mendapatkan daftar semua permission keys yang valid dalam sistem.
     *
     * @return array<int, string>
     */
    public function getAllPermissionKeys(): array
    {
        $keys = [];
        foreach ($this->getGroupedCatalog() as $modules) {
            foreach ($modules as $moduleData) {
                foreach (array_keys($moduleData['actions']) as $permKey) {
                    $keys[] = $permKey;
                }
            }
        }
        return $keys;
    }

    /**
     * Mengembalikan styling badge (CSS class) berdasarkan jenis aksi permission.
     */
    public function getActionBadgeStyle(string $permissionKey): array
    {
        $action = last(explode('.', $permissionKey));

        return match ($action) {
            'view' => [
                'bg' => 'bg-slate-100 text-slate-700 border-slate-200',
                'label' => 'READ',
            ],
            'create' => [
                'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'label' => 'CREATE',
            ],
            'edit', 'manage' => [
                'bg' => 'bg-amber-50 text-amber-700 border-amber-200',
                'label' => 'UPDATE',
            ],
            'delete', 'cancel' => [
                'bg' => 'bg-rose-50 text-rose-700 border-rose-200',
                'label' => 'DELETE',
            ],
            default => [
                'bg' => 'bg-sky-50 text-sky-700 border-sky-200',
                'label' => strtoupper($action),
            ],
        };
    }
}
