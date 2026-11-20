<?php

namespace App\Http\Controllers;

use App\Models\PoLeadTime;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Services\Authorization\LocationScopeService;
use App\Services\Purchasing\PurchasingStageService;
use App\Services\Purchasing\SupplierLeadTimeService;
use App\Traits\ResolvesGudangOnlyUser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchasingStageController extends Controller
{
    use ResolvesGudangOnlyUser;

    protected PurchasingStageService $stageService;
    protected SupplierLeadTimeService $leadTimeService;

    public function __construct(
        PurchasingStageService $stageService,
        SupplierLeadTimeService $leadTimeService
    ) {
        $this->stageService = $stageService;
        $this->leadTimeService = $leadTimeService;
    }

    /**
     * Selesaikan stage secara umum sesuai urutan 1..9.
     */
    public function completeStage(Request $request, PurchaseOrder $purchaseOrder, string $stage): RedirectResponse|JsonResponse
    {
        $waktuSelesai = $request->filled('waktu_selesai') ? Carbon::parse($request->input('waktu_selesai')) : now();
        $catatan = $request->input('catatan');

        switch ($stage) {
            case PoStage::STAGE_1:
                $this->authorize('purchasing.submit');
                $this->stageService->completeStage1($purchaseOrder, auth()->user(), $waktuSelesai, $catatan);
                break;

            case PoStage::STAGE_2:
                $this->authorize('purchasing.approve');
                $this->stageService->approveStage2($purchaseOrder, auth()->user(), $waktuSelesai, $catatan);
                break;

            case PoStage::STAGE_3:
                $this->authorize('purchasing.edit');
                $confirmationData = $request->input('confirmation', []);
                $this->stageService->completeStage3($purchaseOrder, auth()->user(), $confirmationData, $waktuSelesai, $catatan);
                break;

            case PoStage::STAGE_5:
                $this->authorize('purchasing.edit');
                $this->stageService->completeStage5($purchaseOrder, auth()->user(), $waktuSelesai, $catatan);
                break;

            case PoStage::STAGE_6:
                abort_unless(auth()->user()->can('purchasing.edit') || auth()->user()->can('purchasing.receive'), 403);
                if (! auth()->user()->can('purchasing.edit')) {
                    $this->ensureGudangAccess($purchaseOrder);
                }
                $shippingData = $request->input('shipping', []);
                $this->stageService->completeStage6($purchaseOrder, auth()->user(), $shippingData, $waktuSelesai, $catatan);
                break;

            case PoStage::STAGE_7:
                $this->authorize('purchasing.receive');
                $this->ensureGudangAccess($purchaseOrder);
                $physicalCheckData = $request->input('physical_check', []);
                $this->stageService->completeStage7($purchaseOrder, auth()->user(), $physicalCheckData, $waktuSelesai, $catatan);
                break;

            case PoStage::STAGE_8:
                $this->authorize('purchasing.receive');
                $this->ensureGudangAccess($purchaseOrder);
                $existingResi = trim($purchaseOrder->pengiriman?->no_resi ?? '');
                if ($existingResi === '') {
                    $request->validate([
                        'no_resi' => ['required', 'string', 'max:100'],
                    ], [
                        'no_resi.required' => 'Nomor resi wajib diisi karena belum diinput pada tahap pengiriman.',
                    ]);
                } else {
                    $request->validate([
                        'no_resi' => ['nullable', 'string', 'max:100'],
                    ]);
                }

                $noResi = $request->input('no_resi');
                $this->stageService->completeStage8($purchaseOrder, auth()->user(), $waktuSelesai, $catatan, $noResi);
                break;

            case PoStage::LEGACY_STAGE_PENGEMASAN:
                $this->authorize('purchasing.edit');
                $packingData = $request->input('packing', []);
                $this->stageService->completeLegacyPengemasan($purchaseOrder, auth()->user(), $packingData, $waktuSelesai, $catatan);
                break;

            default:
                abort(400, "Stage '{$stage}' tidak valid.");
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Stage {$stage} berhasil diselesaikan."]);
        }

        return back()->with('success', "Tahap {$stage} berhasil diselesaikan.");
    }

    /**
     * Reject Stage 2 oleh Manager (BR-06, TC-12).
     */
    public function rejectStage2(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse|JsonResponse
    {
        $this->authorize('purchasing.approve');
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:255'],
            'waktu_selesai' => ['nullable', 'date'],
        ]);

        $waktuSelesai = ! empty($data['waktu_selesai']) ? Carbon::parse($data['waktu_selesai']) : now();
        $this->stageService->rejectStage2($purchaseOrder, auth()->user(), $data['alasan'], $waktuSelesai);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'PO ditolak dan dikembalikan ke Stage 1 putaran baru.']);
        }

        return back()->with('warning', 'PO ditolak dan dikembalikan ke Stage 1 putaran baru.');
    }

    /**
     * Selesaikan Stage 4 via tombol 'Dibayarkan' (TC-26, TC-27).
     */
    public function dibayarkan(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse|JsonResponse
    {
        $this->authorize('purchasing.pay');
        $waktuSelesai = $request->filled('waktu_selesai') ? Carbon::parse($request->input('waktu_selesai')) : now();
        $catatan = $request->input('catatan');

        $this->stageService->completeStage4WithPayment($purchaseOrder, auth()->user(), $waktuSelesai, $catatan);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Stage 4 diselesaikan via konfirmasi pembayaran.']);
        }

        return back()->with('success', 'Stage 4 diselesaikan via konfirmasi pembayaran.');
    }

    /**
     * Selesaikan Stage 4 via tombol 'Lanjut Tempo' (TC-28).
     */
    public function lanjutTempo(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse|JsonResponse
    {
        $this->authorize('purchasing.pay');
        $waktuSelesai = $request->filled('waktu_selesai') ? Carbon::parse($request->input('waktu_selesai')) : now();
        $catatan = $request->input('catatan');

        $this->stageService->completeStage4Tempo($purchaseOrder, auth()->user(), $waktuSelesai, $catatan);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Tahap Pembayaran dilanjutkan tanpa DP.']);
        }

        return back()->with('success', 'Tahap Pembayaran dilanjutkan tanpa DP.');
    }

    /**
     * Manager melakukan invalidasi transaksi Lead Time (TC-14).
     */
    public function invalidateLeadTime(Request $request, PoLeadTime $poLeadTime): RedirectResponse|JsonResponse
    {
        $this->authorize('purchasing.approve');
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:255'],
        ]);

        $this->leadTimeService->invalidateTransaction($poLeadTime, auth()->user(), $data['alasan']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Data lead time transaksi telah diinvalidasi.']);
        }

        return back()->with('success', 'Data lead time transaksi telah diinvalidasi.');
    }

    /**
     * Visibilitas PO untuk user gudang murni, lalu cakupan gudang tujuan PO.
     */
    private function ensureGudangAccess(PurchaseOrder $po): void
    {
        $user = auth()->user();

        abort_if(
            $this->isGudangOnly($user) && ! $po->isVisibleToGudang(),
            403,
            'Anda tidak memiliki akses ke PO ini pada tahap saat ini.'
        );

        abort_unless(
            app(LocationScopeService::class)->canAccessWarehouse($user, $po->gudang_id),
            403,
            $po->gudang_id
                ? 'Anda tidak memiliki akses ke gudang tujuan PO ini.'
                : 'PO belum memiliki gudang tujuan; minta Purchasing melengkapi.'
        );
    }
}
