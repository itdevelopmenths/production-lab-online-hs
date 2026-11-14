# Blueprint & Rancangan Arsitektur: Dynamic Customizable Roles & Permissions System
**Proyek:** Heaven Scent Enterprise — Production Lab Online  
**Target Dokumen:** `.agent/implementation.md`  
**Standar Arsitektur:** SOLID Principles, Reusable Component Architecture, High Efficiency & Scalability  

---

## 1. Latar Belakang & Analisis Masalah (Why We Need This)

### Kondisi Saat Ini (Current State)
Saat ini sistem Heaven Scent Enterprise menggunakan role statis yang didefinisikan secara *hardcoded* di [database/seeders/PermissionSeeder.php](file:///c:/Users/User/HS%20Project/production-lab-online-hs/database/seeders/PermissionSeeder.php), yaitu 5 persona:
- `manager` (akses penuh ke semua modul)
- `purchasing` (terbatas pada pengadaan & analisa)
- `gudang` (terbatas pada stok & penerimaan barang)
- `operasional` (terbatas pada batch produksi & request transfer)
- `fulfillment` (terbatas pada mutasi cabang & stok)

### Masalah & Keterbatasan (Pain Points)
1. **Tidak Fleksibel:** Jika ada kebutuhan organisasi baru (misal: "Assistant Manager", "Supervisor QC", "Auditor Finansial", atau "Staff Gudang Junior"), developer harus mengubah seeder dan menjalankan migrasi ulang.
2. **All-or-Nothing Permissions:** Role `manager` saat ini otomatis memiliki seluruh izin tanpa bisa dibatasi (misal: manajer operasional cabang hanya boleh `create, view, update` pada modul Produksi dan Bahan Baku, tetapi tidak boleh menghapus data master atau mengakses pembayaran purchasing).
3. **Ketiadaan Antarmuka Manajemen Peran:** Sistem sudah memiliki `UserController`, namun belum memiliki antarmuka (`RoleController`) untuk membuat peran baru dan mengatur matriks wewenang (*permission matrix*) secara visual.

---

## 2. Prinsip Rekayasa Perangkat Lunak (SOLID, Reusable, Efficient, Scalable)

Rancangan ini dibangun dengan mematuhi prinsip rekayasa perangkat lunak modern:

### A. Penerapan Prinsip SOLID
1. **Single Responsibility Principle (SRP):**
   - `PermissionCatalogService`: Hanya bertanggung jawab mendefinisikan, mengelompokkan, dan menyediakan metadata katalog wewenang (nama modul, label manusiawi, aksi).
   - `RoleService`: Hanya bertanggung jawab atas logika bisnis CRUD peran, sinkronisasi relasi izin, dan proteksi peran inti sistem.
   - `RoleController`: Hanya mengurus siklus HTTP request/response dan validasi form.
   - `PermissionMatrixComponent`: Komponen Blade mandiri yang hanya bertugas merender matriks interaktif centang wewenang.

2. **Open/Closed Principle (OCP):**
   - Arsitektur katalog wewenang bersifat terbuka terhadap penambahan modul baru tanpa perlu memodifikasi kode inti otorisasi. Modul baru (misalnya modul `qc_inspection` atau `maintenance`) cukup mendaftarkan daftar *permissions*-nya ke dalam katalog.

3. **Liskov Substitution Principle (LSP):**
   - Baik peran bawaan sistem (`system role`) maupun peran buatan pengguna (`custom role`) mewarisi kontrak model `App\Models\Role` yang sama dan dapat digunakan secara identik oleh middleware Laravel `can:` dan Spatie traits tanpa diskriminasi tipe.

4. **Interface Segregation Principle (ISP):**
   - Memisahkan kontrak antarmuka:
     - `RoleServiceInterface` (manajemen peran administratif)
     - `PermissionCatalogInterface` (penyedia katalog permission untuk UI)
     - Klien tidak dipaksa bergantung pada method yang tidak diperlukannya.

5. **Dependency Inversion Principle (DIP):**
   - `RoleController` bergantung pada abstraksi interface/service (`RoleService`), bukan berinteraksi langsung dengan query model Spatie di controller.

---

### B. Efisiensi Performa (Performance & Caching)
1. **Spatie In-Memory Permission Cache:**
   - Laravel Spatie Permission secara default menyimpan permissions pengguna dalam cache Redis/File. Setiap kali role atau wewenang diperbarui via `RoleService`, service akan otomatis memicu `app()->make(PermissionRegistrar::class)->forgetCachedPermissions()` sehingga query database otorisasi per page-load tetap **0 query** (menggunakan cache terhidrasi).
2. **Eager Loading Optimization:**
   - DataTables peran dan pengguna memuat relasi peran dengan `with('permissions:id,name')` secara eager, mencegah masalah N+1 query.
3. **Payload Ringan pada Matrix UI:**
   - Form pembuatan/pengubahan peran hanya mengirimkan array ID atau nama permission (`permissions[]`), diverifikasi dalam transaksi atomik (`DB::transaction`).

---

### C. Skalabilitas & Keamanan (Scalability & Guard Rails)
1. **Proteksi Peran Inti Sistem (`is_system = true`):**
   - Menambahkan kolom `is_system` (boolean) pada tabel `roles`.
   - Peran dengan `is_system = true` (seperti `manager` atau `superadmin`) tidak dapat dihapus (*undeletable*) dan nama kuncinya tidak dapat diubah, untuk mencegah skenario fatal hilangnya akun administrator sistem.
2. **Audit Logging & Granularitas Aksi:**
   - Format penamaan permission seragam: `{modul}.{aksi}`:
     - Standard CRUD: `.view`, `.create`, `.edit`, `.delete`
     - Workflow Actions: `.submit`, `.approve`, `.receive`, `.pay`, `.cancel`, `.release`, `.complete`
3. **Direct User Overrides (Otorisasi Bertingkat):**
   - Selain wewenang berbasis peran (Role-based), arsitektur tetap mendukung wewenang langsung per user (*user-level permission override*), misal: seorang staff operasional tertentu diberikan izin khusus `purchasing.view` tanpa harus mengubah role operasional secara global.

---

## 3. Desain Arsitektur & Struktur Komponen

### Diagram Alur Arsitektur

```
[ HTTP Request: Admin/Manager ]
             │
             ▼
    [ RoleController ] ──(Request Validation: RoleRequest)
             │
             ▼
    [ RoleService ] ◄─── Uses ─── [ PermissionCatalogService ]
             │                                   │
             ├───────────────────────────────────┼──────────┐
             ▼                                   ▼          ▼
    [ Database / Spatie ]               [ Spatie Cache ]  [ UI: Permission Matrix ]
  (roles, permissions,                  (forgetCached)    (Accordion grouped by module)
   model_has_roles)
```

---

## 4. Struktur Database & Model

### 1. Migrasi Database: Penambahan Kolom pada `roles`
File: `database/migrations/xxxx_xx_xx_add_is_system_and_description_to_roles_table.php`

```php
Schema::table('roles', function (Blueprint $table) {
    $table->string('display_name')->nullable()->after('name');
    $table->text('description')->nullable()->after('display_name');
    $table->boolean('is_system')->default(false)->after('guard_name');
});
```

*Alasan Konkret:*
- `name`: Identifier teknis tanpa spasi (misal: `manager`, `head_purchasing`).
- `display_name`: Label ramah pengguna (misal: `Kepala Bagian Pengadaan`).
- `description`: Penjelasan tanggung jawab wewenang.
- `is_system`: Mencegah penghapusan peran vital oleh user di UI.

### 2. Custom Role Model
File: `app/Models/Role.php`

```php
namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];
}
```

---

## 5. Service Layer (SOLID Architecture)

### 1. `PermissionCatalogService`
File: `app/Services/Authorization/PermissionCatalogService.php`  
**Tanggung Jawab Tunggal:** Menyediakan struktur taksonomi modul dan aksi untuk form UI matriks izin.

```php
namespace App\Services\Authorization;

class PermissionCatalogService
{
    /**
     * Mengembalikan katalog permissions yang dikelompokkan berdasarkan Modul Bisnis.
     */
    public function getGroupedCatalog(): array
    {
        return [
            'Master Data' => [
                'produk' => [
                    'label' => 'Master Produk',
                    'actions' => [
                        'produk.view' => 'Lihat Data Produk',
                        'produk.create' => 'Tambah Produk Baru',
                        'produk.edit' => 'Ubah Data Produk',
                        'produk.delete' => 'Hapus Produk',
                    ]
                ],
                'gudang' => [
                    'label' => 'Master Fasilitas Gudang',
                    'actions' => [
                        'gudang.view' => 'Lihat Gudang',
                        'gudang.create' => 'Tambah Gudang',
                        'gudang.edit' => 'Ubah Gudang',
                        'gudang.delete' => 'Hapus Gudang',
                    ]
                ],
                'supplier' => [
                    'label' => 'Master Supplier',
                    'actions' => [
                        'supplier.view' => 'Lihat Supplier',
                        'supplier.create' => 'Tambah Supplier',
                        'supplier.edit' => 'Ubah Supplier',
                        'supplier.delete' => 'Hapus Supplier',
                    ]
                ],
                'uom' => [
                    'label' => 'Master Satuan (UOM)',
                    'actions' => [
                        'uom.view' => 'Lihat UOM',
                        'uom.create' => 'Tambah UOM',
                        'uom.edit' => 'Ubah UOM',
                        'uom.delete' => 'Hapus UOM',
                    ]
                ],
                'bom' => [
                    'label' => 'Formula Resep (BOM)',
                    'actions' => [
                        'bom.view' => 'Lihat Formula BOM',
                        'bom.manage' => 'Atur & Ubah Komposisi BOM',
                        'bom.import' => 'Impor Data BOM (CSV Stamps POS)',
                    ]
                ],
            ],

            'Operasional & Logistik' => [
                'purchasing' => [
                    'label' => 'Purchasing & Pengadaan (PO)',
                    'actions' => [
                        'purchasing.view' => 'Lihat Daftar PO',
                        'purchasing.create' => 'Buat Draft PO Baru',
                        'purchasing.edit' => 'Ubah Draft PO',
                        'purchasing.submit' => 'Ajukan PO ke Manajer',
                        'purchasing.approve' => 'Setujui (Approval) PO',
                        'purchasing.receive' => 'Terima Barang Datang (Gudang Pusat)',
                        'purchasing.pay' => 'Catat Pembayaran & Pelunasan',
                        'purchasing.cancel' => 'Batalkan Pesanan PO',
                    ]
                ],
                'batch' => [
                    'label' => 'Batch Produksi Lab',
                    'actions' => [
                        'batch.view' => 'Lihat Rencana Batch',
                        'batch.create' => 'Buat Rencana Batch Baru',
                        'batch.release' => 'Rilis Bahan (Issue & Potong Stok)',
                        'batch.complete' => 'Selesaikan Batch (Input Output & QC)',
                        'batch.cancel' => 'Batalkan Batch Rencana',
                        'batch.opname' => 'Stock Opname Selisih Batch',
                    ]
                ],
                'rt' => [
                    'label' => 'Request & Transfer Antar Gudang',
                    'actions' => [
                        'rt.view' => 'Lihat Dokumen Transfer',
                        'rt.create' => 'Buat Dokumen Request/Transfer',
                        'rt.submit' => 'Ajukan Permintaan Bahan',
                        'rt.approve' => 'Setujui Permintaan Bahan (Manajer)',
                        'rt.process' => 'Proses Pengeluaran Barang',
                        'rt.ship' => 'Kirim Barang (Status In Transit)',
                        'rt.receive' => 'Terima Alokasi Barang di Tujuan',
                        'rt.cancel' => 'Batalkan Dokumen Transfer',
                    ]
                ],
            ],

            'Inventori & Analisis' => [
                'stok' => [
                    'label' => 'Stok & Kartu Inventori',
                    'actions' => [
                        'stok.view' => 'Lihat Saldo Stok & Alert',
                        'stok.mutasi' => 'Eksekusi Mutasi Stok Manual',
                        'stok.opname' => 'Stock Opname Fisik Aktual',
                        'stok.ledger.view' => 'Lihat Kartu Stok (Audit Trail)',
                    ]
                ],
                'analisa' => [
                    'label' => 'Analisa Kebutuhan Stok',
                    'actions' => [
                        'analisa.view' => 'Lihat Perhitungan Analisa Stok',
                        'analisa.manage' => 'Simpan Parameter Analisa',
                        'analisa.snapshot' => 'Simpan Snapshot ke Riwayat',
                        'analisa.create_po' => 'Buat Draft PO Langsung dari Analisa',
                    ]
                ],
                'report' => [
                    'label' => 'Laporan & Ekspor',
                    'actions' => [
                        'report.view' => 'Lihat Laporan Transaksi',
                        'report.export' => 'Ekspor Laporan ke Excel/CSV',
                    ]
                ],
            ],

            'Administrasi Sistem' => [
                'user' => [
                    'label' => 'Manajemen Pengguna',
                    'actions' => [
                        'user.manage' => 'Kelola Data Pengguna (CRUD)',
                    ]
                ],
                'role' => [
                    'label' => 'Manajemen Peran & Wewenang',
                    'actions' => [
                        'role.manage' => 'Kelola Peran & Kustomisasi Permissions',
                    ]
                ],
            ],
        ];
    }
}
```

---

### 2. `RoleService`
File: `app/Services/Authorization/RoleService.php`  
**Tanggung Jawab Tunggal:** Logika transaksi database pembuatan, pengubahan, pembersihan cache, dan pencegahan penghapusan peran sistem.

```php
namespace App\Services\Authorization;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function createRole(array $data, array $permissionNames): Role
    {
        return DB::transaction(function () use ($data, $permissionNames) {
            $role = Role::create([
                'name' => strtolower(str_replace(' ', '_', $data['name'])),
                'display_name' => $data['display_name'] ?? $data['name'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($permissionNames);
            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    public function updateRole(Role $role, array $data, array $permissionNames): Role
    {
        return DB::transaction(function () use ($role, $data, $permissionNames) {
            $updateData = [
                'display_name' => $data['display_name'] ?? $role->display_name,
                'description' => $data['description'] ?? $role->description,
            ];

            // Peran sistem tidak boleh diubah identifier 'name'-nya
            if (!$role->is_system && isset($data['name'])) {
                $updateData['name'] = strtolower(str_replace(' ', '_', $data['name']));
            }

            $role->update($updateData);
            $role->syncPermissions($permissionNames);
            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    public function deleteRole(Role $role): void
    {
        if ($role->is_system) {
            throw new \DomainException("Peran sistem bawaan ({$role->display_name}) tidak boleh dihapus.");
        }

        if ($role->users()->count() > 0) {
            throw new \DomainException("Peran ini masih digunakan oleh {$role->users()->count()} pengguna aktif. Pindahkan peran pengguna terlebih dahulu.");
        }

        DB::transaction(function () use ($role) {
            $role->syncPermissions([]);
            $role->delete();
            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
```

---

## 6. Antarmuka Komponen Reusable (UI / UX)

### A. Komponen Blade Matriks Izin: `<x-permission-matrix>`
File: `resources/views/components/permission-matrix.blade.php`

**Fitur Enterprise:**
1. **Accordion per Kategori & Modul:** Memisahkan modul menjadi grup rapi (Master Data, Operasional, Inventori, Sistem).
2. **Fitur "Pilih Semua" (Select All) per Modul:** Memudahkan admin mencentang sekaligus semua aksi pada suatu modul dengan 1 klik via Alpine.js.
3. **Badge Tipe Aksi (Visual Hierarchy):**
   - View = Badge Abu-abu/Biru Muda
   - Create/Edit = Badge Hijau/Amber
   - Delete/Cancel = Badge Merah
   - Approval/Workflow = Badge Ungu/Emas
4. **Responsive Grid:** Berisi 2-3 kolom checkbox rapi dengan deskripsi jelas.

### B. View Halaman Manajemen Peran
1. `resources/views/roles/index.blade.php`: DataTables daftar peran, jumlah pengguna yang menggunakannya, status `System / Custom`, dan tombol Kelola Izin.
2. `resources/views/roles/create.blade.php`: Form pembuatan peran baru + Matriks Izin.
3. `resources/views/roles/edit.blade.php`: Form pengubahan peran + Matriks Izin yang sudah tercentang sesuai izin aktif.

---

## 7. Rencana Eksekusi & Tahapan Pengerjaan

| Tahap | Aktivitas | File Terlibat | Target Hasil |
|---|---|---|---|
| **Tahap 1** | Migrasi Database & Role Model | `database/migrations/...`, `app/Models/Role.php` | Kolom `display_name`, `description`, `is_system` aktif |
| **Tahap 2** | Service Layer (SOLID) | `PermissionCatalogService.php`, `RoleService.php` | Logika katalog & transaksi terpisah rapi |
| **Tahap 3** | Form Request & Controller | `RoleStoreRequest.php`, `RoleUpdateRequest.php`, `RoleController.php` | Validasi unik nama peran & endpoint CRUD siap |
| **Tahap 4** | Reusable UI Component | `components/permission-matrix.blade.php` | Matriks permission interaktif dengan fitur select-all |
| **Tahap 5** | Views Manajemen Peran | `resources/views/roles/index, create, edit.blade.php` | Antarmuka AdminLTE 4 enterprise siap pakai |
| **Tahap 6** | Routing & Integrasi Navigasi | `routes/web.php`, `components/app-layout.blade.php` | Menu "Peran & Wewenang" muncul di bawah menu "Sistem" |
| **Tahap 7** | Automated Test Suite | `tests/Feature/CustomRoleManagementTest.php` | Pengujian pembuatan custom role, modifikasi izin, proteksi delete system role, dan verifikasi otorisasi dinamis |

---

## 8. Mengapa Arsitektur Ini Paling Tepat? (Alasan Logis & Konkrit)

1. **Kompatibilitas 100% dengan Kode yang Sudah Ada:**
   - Tidak merusak middleware `can:nama.permission` yang sudah terpasang di 171 baris `routes/web.php` dan blade `@can`.
   - Hanya mengubah cara wewenang diasosiasikan dengan peran, dari yang tadinya statis di database seeder menjadi dinamis via antarmuka.
2. **Zero Performance Overhead (Cache Terjamin):**
   - Memanfaatkan built-in Spatie caching sehingga pengecekan hak akses di setiap halaman tetap instan tanpa query SQL berulang.
3. **Mencegah Kerusakan Fatal (Fail-Safe Mechanism):**
   - Flag `is_system` menjamin administrator tidak sengaja menghapus peran `manager`, sehingga akses root sistem tetap terlindungi.
4. **Prinsip SOLID Terpelihara:**
   - Kode tetap bersih, teruji (*testable*), mudah diperluas (*scalable*), dan memisahkan urusan presentasi, katalogisasi, serta persistensi data.
