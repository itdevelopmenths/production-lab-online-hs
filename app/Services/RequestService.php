<?php

namespace App\Services;

use App\Models\AlokasiBahan;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\Authorization\InventoryTransferAuthorizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestService
{
    private readonly InventoryTransferAuthorizationService $authz;

    public function __construct(?InventoryTransferAuthorizationService $authz = null)
    {
        $this->authz = $authz ?? app(InventoryTransferAuthorizationService::class);
    }
    /**
     * Generate next nomor request unik (e.g. REQ-202611-0001).
     */
    public function nextNoRequest(): string
    {
        $prefix = 'REQ-' . date('Ym') . '-';
        $last = StockRequest::where('no_request', 'like', $prefix . '%')
            ->orderByDesc('no_request')
            ->value('no_request');

        $nextSeq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validasi wewenang pembuat request berdasarkan jenis (berbasis permission granular, tanpa hardcode role).
     */
    public function validateCreatorRole(User $user, string $jenis): void
    {
        if (! $this->authz->canCreateRequestForType($user, $jenis)) {
            $label = $jenis === 'request_produk_jadi' ? 'Request Produk Jadi' : 'Request Bahan Baku';
            throw ValidationException::withMessages([
                'jenis' => "Anda tidak memiliki izin untuk membuat {$label}.",
            ]);
        }
    }

    /**
     * Pastikan kategori produk item sesuai dengan jenis Request.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function validateItemsMatchRequestType(string $jenis, array $items): void
    {
        $produkIds = collect($items)->pluck('produk_id')->filter()->all();
        $violation = $this->authz->resolveRequestItemCategoryViolations($jenis, $produkIds);
        if ($violation !== null) {
            throw ValidationException::withMessages([
                'items' => $violation,
            ]);
        }
    }

    /**
     * Buat StockRequest baru (FR-REQ-01..03, TC-18).
     * TIDAK PERNAH memotong atau menambah stok fisik / kartu stok.
     */
    public function create(array $data, User $actor): StockRequest
    {
        $this->validateCreatorRole($actor, $data['jenis']);

        if ((int) $data['gudang_asal_id'] === (int) $data['gudang_tujuan_id']) {
            throw ValidationException::withMessages([
                'gudang_tujuan_id' => 'Gudang asal dan gudang tujuan tidak boleh sama.',
            ]);
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'Minimal satu item produk wajib dimasukkan.',
            ]);
        }

        $this->validateItemsMatchRequestType($data['jenis'], $data['items']);

        // Peringatan non-blocking jika melebihi alokasi batch (NC-26)
        $batchId = $data['referensi_batch_id'] ?? null;

        return DB::transaction(function () use ($data, $actor, $batchId) {
            $request = StockRequest::create([
                'no_request' => $this->nextNoRequest(),
                'jenis' => $data['jenis'],
                'referensi_batch_id' => $batchId,
                'gudang_asal_id' => $data['gudang_asal_id'],
                'gudang_tujuan_id' => $data['gudang_tujuan_id'],
                'status' => $data['status'] ?? 'draft',
                'catatan' => $data['catatan'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($data['items'] as $item) {
                $qty = (float) ($item['qty_diminta'] ?? 0);
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Kuantitas item yang diminta harus lebih besar dari 0.',
                    ]);
                }

                $request->items()->create([
                    'produk_id' => $item['produk_id'],
                    'qty_diminta' => $qty,
                ]);
            }

            return $request;
        });
    }

    /**
     * Submit request dari draft ke diajukan.
     */
    public function submit(StockRequest $request, User $actor): void
    {
        if ($request->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya request dengan status draft yang dapat diajukan.',
            ]);
        }

        $request->update(['status' => 'diajukan']);
    }

    /**
     * Persetujuan request oleh Manager (TC-19).
     */
    public function approve(StockRequest $request, User $manager): void
    {
        if (! in_array($request->status, ['draft', 'diajukan'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Request ini sudah diproses dan tidak dapat disetujui lagi.',
            ]);
        }

        $request->update([
            'status' => 'disetujui',
            'disetujui_oleh' => $manager->id,
            'disetujui_pada' => now(),
            'alasan_tolak' => null,
        ]);
    }

    /**
     * Penolakan request oleh Manager (TC-19, alasan wajib).
     */
    public function reject(StockRequest $request, User $manager, string $alasan): void
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penolakan request wajib diisi.',
            ]);
        }

        if (! in_array($request->status, ['draft', 'diajukan'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Request ini sudah diproses dan tidak dapat ditolak.',
            ]);
        }

        $request->update([
            'status' => 'ditolak',
            'alasan_tolak' => $alasan,
            'disetujui_oleh' => $manager->id,
            'disetujui_pada' => now(),
        ]);
    }

    /**
     * Update/Edit Request (FR-REQ-08).
     * Jika diedit dari status 'disetujui', otomatis mengembalikan status ke 'diajukan' dan mereset approval.
     */
    public function update(StockRequest $request, array $data, User $actor): StockRequest
    {
        $this->validateCreatorRole($actor, $request->jenis);

        // Cek apakah sudah ada transfer aktif
        if ($request->activeTransfer()->exists() || in_array($request->status, ['diproses', 'selesai', 'dibatalkan', 'ditolak'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Request tidak dapat diubah karena sudah memiliki transfer aktif atau sudah selesai/batal.',
            ]);
        }

        if (! empty($data['items']) && is_array($data['items'])) {
            $this->validateItemsMatchRequestType($request->jenis, $data['items']);
        }

        return DB::transaction(function () use ($request, $data) {
            $isPreviouslyApproved = $request->status === 'disetujui';

            $request->update([
                'gudang_asal_id' => $data['gudang_asal_id'] ?? $request->gudang_asal_id,
                'gudang_tujuan_id' => $data['gudang_tujuan_id'] ?? $request->gudang_tujuan_id,
                'referensi_batch_id' => array_key_exists('referensi_batch_id', $data) ? $data['referensi_batch_id'] : $request->referensi_batch_id,
                'catatan' => $data['catatan'] ?? $request->catatan,
                // Jika diedit setelah approved, reset kembali ke diajukan (FR-REQ-08)
                'status' => $isPreviouslyApproved ? 'diajukan' : $request->status,
                'disetujui_oleh' => $isPreviouslyApproved ? null : $request->disetujui_oleh,
                'disetujui_pada' => $isPreviouslyApproved ? null : $request->disetujui_pada,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                $request->items()->delete();
                foreach ($data['items'] as $item) {
                    $qty = (float) ($item['qty_diminta'] ?? 0);
                    if ($qty <= 0) {
                        throw ValidationException::withMessages([
                            'items' => 'Kuantitas item yang diminta harus lebih besar dari 0.',
                        ]);
                    }

                    $request->items()->create([
                        'produk_id' => $item['produk_id'],
                        'qty_diminta' => $qty,
                    ]);
                }
            }

            return $request;
        });
    }

    /**
     * Batalkan Request oleh pembuat atau Manager.
     */
    public function cancel(StockRequest $request, User $actor, string $alasan): void
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan request wajib diisi.',
            ]);
        }

        if ($request->activeTransfer()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Request memiliki Transfer aktif yang sedang berjalan dan tidak dapat dibatalkan.',
            ]);
        }

        if (in_array($request->status, ['selesai', 'dibatalkan'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Request sudah final atau sudah dibatalkan sebelumnya.',
            ]);
        }

        $request->update([
            'status' => 'dibatalkan',
            'alasan_batal' => $alasan,
        ]);
    }
}
