<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SessionSecurityController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\GudangController;
use App\Http\Controllers\AnalisaController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\PurchasingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequestTransferController;
use App\Http\Controllers\StokController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoleSwitcherController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Local & Testing Quick Role Switcher (Bab 9 No 6)
if (app()->environment('local', 'testing')) {
    Route::post('switch-role', [RoleSwitcherController::class, 'switch'])->name('switch-role');
}

// Guest
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

// Session Lifecycle & Heartbeat
Route::get('session/ping', [SessionSecurityController::class, 'ping'])->name('session.ping');

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // API / Search Endpoints
    Route::get('produk/select-data', [ProdukController::class, 'selectData'])->name('produk.select-data');
    Route::get('master-data-audit/data', [\App\Http\Controllers\MasterDataAuditController::class, 'data'])->name('master-audit.data');

    // ===== Master Data =====
    Route::middleware('can:produk.view')->group(function () {
        Route::get('produk/data', [ProdukController::class, 'data'])->name('produk.data');
        Route::resource('produk', ProdukController::class)->except(['show']);
    });

    Route::middleware('can:gudang.view')->group(function () {
        Route::get('gudang/data', [GudangController::class, 'data'])->name('gudang.data');
        Route::resource('gudang', GudangController::class)->except(['show']);
    });

    Route::middleware('can:supplier.view')->group(function () {
        Route::get('supplier/data', [SupplierController::class, 'data'])->name('supplier.data');
        Route::resource('supplier', SupplierController::class)->except(['show']);
    });

    Route::middleware('can:uom.view')->group(function () {
        Route::get('uom/data', [UomController::class, 'data'])->name('uom.data');
        Route::resource('uom', UomController::class)->except(['show']);
    });

    Route::middleware('can:divisi.view')->group(function () {
        Route::get('divisi/data', [DivisiController::class, 'data'])->name('divisi.data');
        Route::resource('divisi', DivisiController::class)->except(['show']);
    });

    Route::middleware('can:bom.view')->group(function () {
        Route::get('bom', [BomController::class, 'index'])->name('bom.index');
        Route::get('bom/data', [BomController::class, 'data'])->name('bom.data');
        Route::get('bom/{produk}/edit', [BomController::class, 'edit'])->name('bom.edit');
        Route::put('bom/{produk}', [BomController::class, 'update'])
            ->middleware('can:bom.manage')->name('bom.update');
        Route::post('bom/import', [BomController::class, 'import'])
            ->middleware('can:bom.import')->name('bom.import');
    });

    // ===== Stok & Mutasi =====
    Route::middleware('can:stok.view')->prefix('stok')->name('stok.')->group(function () {
        Route::get('/', [StokController::class, 'index'])->name('index');
        Route::get('data', [StokController::class, 'data'])->name('data');
        Route::get('log-pergerakan', [StokController::class, 'pergerakanLog'])->name('log-pergerakan');
        Route::get('log-pergerakan/data', [StokController::class, 'pergerakanLogData'])->name('log-pergerakan.data');
        Route::post('mutasi', [StokController::class, 'mutasi'])
            ->middleware('can:stok.mutasi')->name('mutasi');
        Route::get('opname', [StokController::class, 'opname'])
            ->middleware('can:stok.opname')->name('opname');
        Route::post('opname', [StokController::class, 'storeOpname'])
            ->middleware('can:stok.opname')->name('opname.store');
        Route::get('{produk}/ledger', [StokController::class, 'ledger'])
            ->middleware('can:stok.ledger.view')->name('ledger');
        Route::get('{produk}/ledger/data', [StokController::class, 'ledgerData'])
            ->middleware('can:stok.ledger.view')->name('ledger.data');
    });

    // ===== Purchasing =====
    Route::middleware('can:purchasing.view')->prefix('purchasing')->name('purchasing.')->group(function () {
        Route::get('/', [PurchasingController::class, 'index'])->name('index');
        Route::get('data', [PurchasingController::class, 'data'])->name('data');
        Route::get('data-ap', [PurchasingController::class, 'dataAp'])->middleware('can:purchasing.price.view')->name('data-ap');
        Route::get('create', [PurchasingController::class, 'create'])
            ->middleware('can:purchasing.create')->name('create');
        Route::post('/', [PurchasingController::class, 'store'])
            ->middleware('can:purchasing.create')->name('store');
        Route::get('{purchaseOrder}', [PurchasingController::class, 'show'])->name('show');
        Route::get('{purchaseOrder}/edit', [PurchasingController::class, 'edit'])
            ->middleware('can:purchasing.edit')->name('edit');
        Route::put('{purchaseOrder}', [PurchasingController::class, 'update'])
            ->middleware('can:purchasing.edit')->name('update');
        Route::put('{purchaseOrder}/quick-dates', [PurchasingController::class, 'quickDates'])
            ->middleware('can:purchasing.edit')->name('quick-dates');
        Route::post('{purchaseOrder}/submit', [PurchasingController::class, 'submit'])
            ->middleware('can:purchasing.submit')->name('submit');
        Route::post('{purchaseOrder}/approve', [PurchasingController::class, 'approve'])
            ->middleware('can:purchasing.approve')->name('approve');
        Route::post('{purchaseOrder}/receive', [PurchasingController::class, 'receive'])
            ->middleware('can:purchasing.receive')->name('receive');
        Route::post('{purchaseOrder}/pay', [PurchasingController::class, 'pay'])
            ->middleware('can:purchasing.pay')->name('pay');
        Route::post('{purchaseOrder}/cancel', [PurchasingController::class, 'cancel'])
            ->middleware('can:purchasing.cancel')->name('cancel');
    });

    // ===== Request & Transfer =====
    Route::middleware('can:rt.view')->prefix('request-transfer')->name('rt.')->group(function () {
        Route::get('/', [RequestTransferController::class, 'index'])->name('index');
        Route::get('data', [RequestTransferController::class, 'data'])->name('data');
        Route::get('create', [RequestTransferController::class, 'create'])
            ->middleware('can:rt.create')->name('create');
        Route::post('/', [RequestTransferController::class, 'store'])
            ->middleware('can:rt.create')->name('store');
        Route::get('{requestTransfer}', [RequestTransferController::class, 'show'])->name('show');
        Route::post('{requestTransfer}/transition', [RequestTransferController::class, 'transition'])
            ->name('transition');
    });

    // ===== Analisa Stok =====
    Route::middleware('can:analisa.view')->prefix('analisa')->name('analisa.')->group(function () {
        Route::get('/', [AnalisaController::class, 'index'])->name('index');
        Route::get('lokal/data', [AnalisaController::class, 'lokalData'])->name('lokal.data');
        Route::get('impor/data', [AnalisaController::class, 'imporData'])->name('impor.data');
        Route::get('fulfillment/data', [AnalisaController::class, 'fulfillmentData'])->name('fulfillment.data');
        Route::get('riwayat/data', [AnalisaController::class, 'riwayatData'])->name('riwayat.data');
        Route::post('snapshot', [AnalisaController::class, 'snapshot'])
            ->middleware('can:analisa.snapshot')->name('snapshot');
        Route::post('create-po', [AnalisaController::class, 'createPo'])
            ->middleware('can:analisa.create_po')->name('create-po');
    });

    // ===== Produksi (Batch) =====
    Route::middleware('can:batch.view')->prefix('batch')->name('batches.')->group(function () {
        Route::get('/', [BatchController::class, 'index'])->name('index');
        Route::get('data', [BatchController::class, 'data'])->name('data');
        Route::get('create', [BatchController::class, 'create'])
            ->middleware('can:batch.create')->name('create');
        Route::post('/', [BatchController::class, 'store'])
            ->middleware('can:batch.create')->name('store');
        Route::get('{batch}', [BatchController::class, 'show'])->name('show');
        Route::post('{batch}/release', [BatchController::class, 'release'])
            ->middleware('can:batch.release')->name('release');
        Route::post('{batch}/complete', [BatchController::class, 'complete'])
            ->middleware('can:batch.complete')->name('complete');
        Route::post('{batch}/cancel', [BatchController::class, 'cancel'])
            ->middleware('can:batch.cancel')->name('cancel');
        Route::post('{batch}/opname', [BatchController::class, 'opname'])
            ->middleware('can:batch.opname')->name('opname');
        Route::post('{batch}/kirim', [BatchController::class, 'kirim'])
            ->middleware('can:rt.create')->name('kirim');
    });

    // ===== Laporan =====
    Route::middleware('can:report.view')->prefix('laporan')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('produksi', [ReportController::class, 'production'])->name('production');
        Route::get('pemakaian-bahan', [ReportController::class, 'material'])->name('material');
        Route::get('defect', [ReportController::class, 'defect'])->name('defect');
        Route::get('low-stock', [ReportController::class, 'lowStock'])->name('low-stock');
        Route::get('purchasing', [ReportController::class, 'purchasing'])->name('purchasing');
        Route::get('fulfillment', [ReportController::class, 'fulfillment'])->name('fulfillment');
    });

    // ===== Admin: Users =====
    Route::middleware('can:user.manage')->group(function () {
        Route::get('users/data', [UserController::class, 'data'])->name('users.data');
        Route::resource('users', UserController::class)->except(['show']);
    });

    // ===== Admin: Roles & Permissions =====
    Route::middleware('can:role.manage')->group(function () {
        Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
        Route::resource('roles', RoleController::class)->except(['show']);
    });
});
