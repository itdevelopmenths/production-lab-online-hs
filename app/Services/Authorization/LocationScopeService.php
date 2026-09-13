<?php

namespace App\Services\Authorization;

use App\Models\Gudang;
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
}
