<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Models\Transfer;
use App\Services\Authorization\LocationScopeService;
use App\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Menu Barang Rusak: akumulasi barang rusak hasil QC penerimaan Transfer
 * per barang per lokasi (gudang tujuan), dengan filter gudang dan rentang tanggal.
 */
class BarangRusakController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly LocationScopeService $locationScope,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('transfer.view');

        $data = $request->validate([
            'gudang_id' => ['nullable', 'integer'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
        ]);

        $tanggalMulai = isset($data['tanggal_mulai']) ? Carbon::parse($data['tanggal_mulai']) : now()->startOfMonth();
        $tanggalSelesai = isset($data['tanggal_selesai']) ? Carbon::parse($data['tanggal_selesai']) : now();
        if ($tanggalMulai->gt($tanggalSelesai)) {
            [$tanggalMulai, $tanggalSelesai] = [$tanggalSelesai, $tanggalMulai];
        }

        // Lokasi yang boleh dilihat user (null = semua gudang)
        $accessibleIds = $this->locationScope->getAccessibleWarehouseIds($request->user());
        $gudangList = Gudang::active()
            ->when($accessibleIds !== null, fn ($q) => $q->whereIn('id', $accessibleIds))
            ->orderBy('nama')
            ->get(['id', 'nama']);

        $gudangId = isset($data['gudang_id']) ? (int) $data['gudang_id'] : null;
        if ($gudangId !== null && $accessibleIds !== null && ! in_array($gudangId, $accessibleIds, true)) {
            $gudangId = null;
        }
        $filterIds = $gudangId !== null ? [$gudangId] : $accessibleIds;

        $items = $this->transferService->barangRusakList($filterIds, $tanggalMulai, $tanggalSelesai);

        return view('barang-rusak.index', [
            'items' => $items,
            'gudangList' => $gudangList,
            'gudangId' => $gudangId,
            'tanggalMulai' => $tanggalMulai->toDateString(),
            'tanggalSelesai' => $tanggalSelesai->toDateString(),
            'totalTransfer' => $items->flatMap(fn ($i) => $i->details->pluck('transfer_id'))->unique()->count(),
            'jenisLabels' => Transfer::JENIS_LABELS,
        ]);
    }
}
