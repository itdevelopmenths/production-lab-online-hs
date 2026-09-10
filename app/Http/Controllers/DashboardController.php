<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\PurchaseOrder;
use App\Models\RequestTransfer;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user()->loadMissing('roles');

        $stats = [
            'po_perlu_tindakan' => PurchaseOrder::whereIn('status', ['draft', 'diajukan', 'dikirim_ke_gudang'])->count(),
            'rt_perlu_tindakan' => RequestTransfer::whereIn('status', ['draft', 'diajukan', 'disetujui', 'diproses', 'dikirim'])->count(),
            'batch_aktif' => BatchProduksi::whereIn('status', ['rencana', 'release'])->count(),
            'batch_selesai' => BatchProduksi::where('status', 'selesai')->count(),
        ];

        // Daftar "perlu tindakan saya" ringkas.
        $poTerbaru = PurchaseOrder::with('supplier:id,nama')
            ->whereIn('status', ['draft', 'diajukan', 'dikirim_ke_gudang'])
            ->latest()->limit(5)->get();

        $rtTerbaru = RequestTransfer::with(['gudangAsal:id,nama', 'gudangTujuan:id,nama'])
            ->whereIn('status', ['draft', 'diajukan', 'disetujui', 'diproses', 'dikirim'])
            ->latest()->limit(5)->get();

        return view('dashboard', compact('user', 'stats', 'poTerbaru', 'rtTerbaru'));
    }
}
