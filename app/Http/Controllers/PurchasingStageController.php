<?php

namespace App\Http\Controllers;

use App\Models\PoLeadTime;
use App\Models\PoRevisi;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderRevisionService;
use App\Services\Purchasing\PurchasingStageService;
use App\Services\Purchasing\SupplierLeadTimeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchasingStageController extends Controller
{
    protected PurchasingStageService $stageService;
    protected PurchaseOrderRevisionService $revisionService;
    protected SupplierLeadTimeService $leadTimeService;

    public function __construct(
        PurchasingStageService $stageService,
        PurchaseOrderRevisionService $revisionService,
        SupplierLeadTimeService $leadTimeService
    ) {
        $this->stageService = $stageService;
        $this->revisionService = $revisionService;
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
                $shippingData = $request->input('shipping', []);
                $this->stageService->completeStage6($purchaseOrder, auth()->user(), $shippingData, $waktuSelesai, $catatan);
                break;

            case PoStage::STAGE_7:
                $this->authorize('purchasing.receive');
                $physicalCheckData = $request->input('physical_check', []);
                $this->stageService->completeStage7($purchaseOrder, auth()->user(), $physicalCheckData, $waktuSelesai, $catatan);
                break;

            case PoStage::STAGE_8:
                $this->authorize('purchasing.receive');
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
            return response()->json(['success' => true, 'message' => 'Stage 4 diselesaikan via skema tempo.']);
        }

        return back()->with('success', 'Stage 4 diselesaikan via skema tempo.');
    }

    /**
     * Direct edit field operasional tanpa approval (TC-33).
     */
    public function directEdit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse|JsonResponse
    {
        $this->authorize('purchasing.edit');
        $request->validate([
            'fields' => ['required', 'array'],
            'fields.eta' => ['nullable', 'date'],
            'fields.eta_supplier' => ['nullable', 'date'],
            'fields.eta_tiba' => ['nullable', 'date'],
        ]);
        $fields = $request->input('fields', []);

        $this->revisionService->updateOperationalFields($purchaseOrder, $fields, auth()->user());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Field operasional berhasil diperbarui.']);
        }

        return back()->with('success', 'Field operasional berhasil diperbarui.');
    }

    /**
     * Mengajukan revisi data komitmen (TC-34, TC-37).
     */
    public function submitRevisi(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse|JsonResponse
    {
        $this->authorize('purchasing.edit');
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:255'],
            'data_usulan' => ['required', 'array'],
            'data_usulan.diskon_total' => ['nullable', 'numeric', 'min:0'],
            'data_usulan.ppn_nominal' => ['nullable', 'numeric', 'min:0'],
            'data_usulan.ongkos_kirim' => ['nullable', 'numeric', 'min:0'],
            'data_usulan.adjustment' => ['nullable', 'numeric'],
            'data_usulan.items' => ['nullable', 'array', 'min:1'],
            'data_usulan.items.*.po_item_id' => ['nullable', 'string'],
            'data_usulan.items.*.produk_id' => ['required_with:data_usulan.items', 'integer', 'exists:produk,id'],
            'data_usulan.items.*.qty' => ['nullable', 'numeric', 'gt:0'],
            'data_usulan.items.*.qty_satuan_beli' => ['nullable', 'numeric', 'gt:0'],
            'data_usulan.items.*.satuan_beli' => ['nullable', 'string', 'max:20'],
            'data_usulan.items.*.faktor_konversi' => ['nullable', 'numeric', 'gt:0'],
            'data_usulan.items.*.harga_total' => ['required_with:data_usulan.items', 'numeric', 'min:0'],
        ]);

        $revisi = $this->revisionService->submitCommitmentRevision(
            $purchaseOrder,
            $data['data_usulan'],
            $data['alasan'],
            auth()->user()
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'revisi' => $revisi]);
        }

        return back()->with('success', 'Usulan revisi berhasil diajukan ke Manager.');
    }

    /**
     * Manager menyetujui usulan revisi (TC-35).
     */
    public function approveRevisi(Request $request, PoRevisi $revisi): RedirectResponse|JsonResponse
    {
        $this->authorize('purchasing.approve');
        $this->revisionService->approveRevision($revisi, auth()->user());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Usulan revisi telah disetujui.']);
        }

        return back()->with('success', 'Usulan revisi telah disetujui.');
    }

    /**
     * Manager menolak usulan revisi (TC-36).
     */
    public function rejectRevisi(Request $request, PoRevisi $revisi): RedirectResponse|JsonResponse
    {
        $this->authorize('purchasing.approve');
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:255'],
        ]);

        $this->revisionService->rejectRevision($revisi, auth()->user(), $data['alasan']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Usulan revisi telah ditolak.']);
        }

        return back()->with('warning', 'Usulan revisi telah ditolak.');
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
}

