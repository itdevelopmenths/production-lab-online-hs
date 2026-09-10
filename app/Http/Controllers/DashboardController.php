<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\PurchaseOrder;
use App\Models\RequestTransfer;
use App\Services\AnalisaService;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalisaService $analisa)
    {
    }

    public function index()
    {
        $user = auth()->user()->loadMissing('roles');
        $roleName = $user->roleName();

        // 1. Ringkasan item berstatus ORDER dari Analisa Stok
        $orderSummary = $this->analisa->getItemPerluOrderSummary();

        // 2. Statistik kartu ringkasan (disesuaikan dengan kebutuhan tindakan per role)
        $stats = [
            'item_perlu_order' => $orderSummary['total'],
            'po_perlu_tindakan' => PurchaseOrder::whereIn('status', match ($roleName) {
                'manager' => ['diajukan'],
                'purchasing' => ['draft'],
                'gudang' => ['dikirim_ke_gudang'],
                default => ['draft', 'diajukan', 'dikirim_ke_gudang'],
            })->count(),
            'rt_perlu_tindakan' => RequestTransfer::whereIn('status', match ($roleName) {
                'manager' => ['diajukan'],
                'gudang' => ['disetujui'],
                'fulfillment' => ['diproses', 'dikirim'],
                'operasional' => ['draft'],
                default => ['draft', 'diajukan', 'disetujui', 'diproses', 'dikirim'],
            })->count(),
            'batch_aktif' => BatchProduksi::whereIn('status', ['rencana', 'release'])->count(),
            'batch_selesai' => BatchProduksi::where('status', 'selesai')->count(),
        ];

        // 3. PO Terbaru sesuai kebutuhan tindakan role (FR-DASH-02)
        $poQuery = PurchaseOrder::with('supplier:id,nama');
        if ($roleName === 'manager') {
            $poQuery->where('status', 'diajukan');
        } elseif ($roleName === 'purchasing') {
            $poQuery->whereIn('status', ['draft', 'dikirim_ke_gudang']);
        } elseif ($roleName === 'gudang') {
            $poQuery->where('status', 'dikirim_ke_gudang');
        } else {
            $poQuery->whereIn('status', ['draft', 'diajukan', 'dikirim_ke_gudang']);
        }
        $poTerbaru = $poQuery->latest('tanggal')->limit(5)->get();

        // 4. Request & Transfer Terbaru sesuai tindakan role (FR-DASH-02)
        $rtQuery = RequestTransfer::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama']);
        if ($roleName === 'manager') {
            $rtQuery->where('status', 'diajukan');
        } elseif ($roleName === 'gudang') {
            $rtQuery->whereIn('status', ['disetujui', 'diproses', 'dikirim']);
        } elseif ($roleName === 'fulfillment') {
            $rtQuery->whereIn('status', ['diproses', 'dikirim']);
        } elseif ($roleName === 'operasional') {
            $rtQuery->whereIn('status', ['draft', 'diproses']);
        } else {
            $rtQuery->whereIn('status', ['draft', 'diajukan', 'disetujui', 'diproses', 'dikirim']);
        }
        $rtTerbaru = $rtQuery->latest()->limit(5)->get();

        return view('dashboard', compact('user', 'stats', 'orderSummary', 'poTerbaru', 'rtTerbaru'));
    }
}
