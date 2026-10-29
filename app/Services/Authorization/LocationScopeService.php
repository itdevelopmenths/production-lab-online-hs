<?php

namespace App\Services\Authorization;

use App\Models\Gudang;
use App\Models\RequestTransfer;
use App\Models\User;
use Illuminate\Support\Collection;

class LocationScopeService
{
    /**
     * Mengembalikan daftar ID gudang yang diizinkan untuk diakses user.
     * Jika mengembalikan null, berarti user memiliki wewenang global (semua gudang).
     *
     * @return array<int>|null
     */
    public function getAccessibleWarehouseIds(User $user): ?array
    {
        if ($user->hasRole('manager') || $user->can('stok.view.all') || $user->warehouse_access_type === 'global') {
            return null;
        }

        $assigned = $user->gudangs()->pluck('gudang.id')->toArray();
        if (! empty($assigned)) {
            return array_map('intval', $assigned);
        }

        // Fallback berbasis peran standar jika disetel restricted
        if ($user->warehouse_access_type === 'restricted') {
            $role = $user->roleName();

            $ids = match ($role) {
                'gudang' => Gudang::where('tipe', 'bahan_baku')->pluck('id')->toArray(),
                'operasional' => Gudang::where('tipe', 'operasional')->pluck('id')->toArray(),
                'fulfillment' => Gudang::fulfillment()->pluck('id')->toArray(),
                default => [],
            };

            return array_map('intval', $ids);
        }

        return null;
    }

    /**
     * Memeriksa apakah user memiliki akses ke gudang tertentu.
     */
    public function canAccessWarehouse(User $user, ?int $warehouseId): bool
    {
        if ($warehouseId === null) {
            return false;
        }

        $allowedIds = $this->getAccessibleWarehouseIds($user);
        if ($allowedIds === null) {
            return true;
        }

        return in_array((int) $warehouseId, $allowedIds, true);
    }

    /**
     * Apakah user berwewenang global (Manager / All locations).
     */
    public function isGlobal(User $user): bool
    {
        return $this->getAccessibleWarehouseIds($user) === null;
    }

    /**
     * Mengambil Collection model Gudang yang dapat diakses user untuk dropdown filter.
     */
    public function getAccessibleGudangs(User $user): Collection
    {
        $query = Gudang::active()->orderBy('nama');
        $allowedIds = $this->getAccessibleWarehouseIds($user);

        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds);
        }

        return $query->get(['id', 'kode', 'nama', 'tipe']);
    }

    /**
     * Mengembalikan daftar jenis mutasi (RequestTransfer::JENIS) yang diizinkan untuk diinisiasi user
     * berdasarkan wewenang gudang (LBAC).
     *
     * @return array<string>
     */
    public function getAllowedTransferJenis(User $user): array
    {
        if ($this->isGlobal($user)) {
            return array_keys(RequestTransfer::JENIS_MATRIX);
        }

        $accessibleIds = $this->getAccessibleWarehouseIds($user) ?? [];
        if (empty($accessibleIds)) {
            return [];
        }

        $myGudangs = Gudang::active()->whereIn('id', $accessibleIds)->get(['id', 'tipe']);
        $myTypes = $myGudangs->pluck('tipe')->unique()->all();

        $allowed = [];
        foreach (RequestTransfer::JENIS_MATRIX as $jenis => $matrix) {
            $canInitiate = false;
            if ($matrix['allowed_initiator'] === 'asal') {
                $canInitiate = ! empty(array_intersect($matrix['asal_tipe'], $myTypes));
            } elseif ($matrix['allowed_initiator'] === 'either') {
                $canInitiate = ! empty(array_intersect(array_merge($matrix['asal_tipe'], $matrix['tujuan_tipe']), $myTypes));
            }

            if ($canInitiate) {
                $allowed[] = $jenis;
            }
        }

        return $allowed;
    }

    /**
     * Memvalidasi wewenang inisiasi transfer berdasarkan jenis, gudang asal, dan gudang tujuan.
     * Mengembalikan null jika valid, atau pesan kesalahan jika wewenang ditolak.
     */
    public function validateTransferAuthority(User $user, string $jenis, Gudang $asal, Gudang $tujuan): ?string
    {
        if (! isset(RequestTransfer::JENIS_MATRIX[$jenis])) {
            return 'Jenis transfer tidak valid.';
        }

        $matrix = RequestTransfer::JENIS_MATRIX[$jenis];

        if (! in_array($asal->tipe, $matrix['asal_tipe'], true)) {
            return "Gudang asal '{$asal->nama}' ({$asal->tipe}) tidak valid untuk jenis {$matrix['label']}. Tipe yang diizinkan: " . implode(', ', $matrix['asal_tipe']) . '.';
        }

        if (! in_array($tujuan->tipe, $matrix['tujuan_tipe'], true)) {
            return "Gudang tujuan '{$tujuan->nama}' ({$tujuan->tipe}) tidak valid untuk jenis {$matrix['label']}. Tipe yang diizinkan: " . implode(', ', $matrix['tujuan_tipe']) . '.';
        }

        if ((int) $asal->id === (int) $tujuan->id) {
            return 'Gudang asal dan gudang tujuan tidak boleh sama.';
        }

        // LBAC Authorization
        if (! $this->isGlobal($user)) {
            $accessibleIds = $this->getAccessibleWarehouseIds($user) ?? [];

            if ($matrix['allowed_initiator'] === 'asal') {
                if (! in_array((int) $asal->id, $accessibleIds, true)) {
                    return "Akses Ditolak: Anda tidak memiliki wewenang untuk membuat transfer dari gudang asal '{$asal->nama}'.";
                }
            } elseif ($matrix['allowed_initiator'] === 'either') {
                if (! in_array((int) $asal->id, $accessibleIds, true) && ! in_array((int) $tujuan->id, $accessibleIds, true)) {
                    return "Akses Ditolak: Anda tidak memiliki wewenang untuk gudang asal ('{$asal->nama}') maupun tujuan ('{$tujuan->nama}') transfer ini.";
                }
            }
        }

        return null;
    }

    /**
     * Memeriksa apakah user berwewenang melihat atau memantau dokumen RequestTransfer tertentu.
     */
    public function canViewTransfer(User $user, RequestTransfer $rt): bool
    {
        if ($this->isGlobal($user)) {
            return true;
        }

        $accessibleIds = $this->getAccessibleWarehouseIds($user) ?? [];

        return in_array((int) $rt->gudang_asal_id, $accessibleIds, true) ||
               in_array((int) $rt->gudang_tujuan_id, $accessibleIds, true);
    }
}
