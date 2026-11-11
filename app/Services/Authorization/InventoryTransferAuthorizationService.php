<?php

namespace App\Services\Authorization;

use App\Models\BatchProduksi;
use App\Models\Produk;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\User;

class InventoryTransferAuthorizationService
{
    public const CATEGORY_BAHAN = 'bahan';
    public const CATEGORY_PRODUK_JADI = 'produk_jadi';

    public function isProdukJadiTipe(?string $tipe): bool
    {
        return $tipe === self::CATEGORY_PRODUK_JADI;
    }

    /**
     * Daftar tipe produk non-produk-jadi (Bahan Baku & Kemasan) yang disuplai ke lab/purchasing.
     *
     * @return array<int, string>
     */
    public function allowedBahanProductTipes(): array
    {
        return Produk::SUPPLIABLE_TIPES;
    }

    /**
     * Resolusi kategori unik ('bahan', 'produk_jadi') dari daftar ID produk.
     *
     * @param  iterable<int, string|null>  $produkIds
     * @return array<int, string>
     */
    public function resolveProductCategories(iterable $produkIds): array
    {
        $ids = collect($produkIds)->filter()->unique()->values()->all();
        if (empty($ids)) {
            return [];
        }

        $tipes = Produk::query()
            ->whereIn('id', $ids)
            ->pluck('tipe')
            ->all();

        return $this->mapTipesToCategories($tipes);
    }

    /**
     * Resolusi gabungan kategori dari jenis transfer, referensi StockRequest, dan seluruh item payload.
     * Memastikan penyusupan item bahan di balik jenis produk_jadi tetap terdeteksi.
     *
     * @param  array<int, string|null>  $produkIds
     * @return array<int, string>
     */
    public function resolveCategoriesForTransferPayload(string $jenis, ?StockRequest $stockRequest, array $produkIds): array
    {
        $categories = [];

        if ($stockRequest) {
            $categories[] = $stockRequest->jenis === 'request_produk_jadi'
                ? self::CATEGORY_PRODUK_JADI
                : self::CATEGORY_BAHAN;
        }

        if (in_array($jenis, ['pemenuhan_request_bahan', 'retur_bahan'], true)) {
            $categories[] = self::CATEGORY_BAHAN;
        } elseif (in_array($jenis, ['kirim_produk_jadi', 'retur_produk_jadi'], true)) {
            $categories[] = self::CATEGORY_PRODUK_JADI;
        }

        $categories = array_merge($categories, $this->resolveProductCategories($produkIds));
        $categories = array_values(array_unique($categories));

        if (empty($categories) && $jenis === 'antar_fulfillment') {
            return [self::CATEGORY_PRODUK_JADI];
        }

        return $categories;
    }

    /**
     * Resolusi kategori unik untuk instance model Transfer yang sudah ada tanpa memicu lazy-loading error.
     *
     * @return array<int, string>
     */
    public function resolveCategoriesForTransfer(Transfer $transfer): array
    {
        if ($transfer->exists) {
            $transfer->loadMissing(['items.produk', 'stockRequest:id,jenis']);
        }

        $categories = [];

        if ($transfer->stockRequest) {
            $categories[] = $transfer->stockRequest->jenis === 'request_produk_jadi'
                ? self::CATEGORY_PRODUK_JADI
                : self::CATEGORY_BAHAN;
        }

        if (in_array($transfer->jenis, ['pemenuhan_request_bahan', 'retur_bahan'], true)) {
            $categories[] = self::CATEGORY_BAHAN;
        } elseif (in_array($transfer->jenis, ['kirim_produk_jadi', 'retur_produk_jadi'], true)) {
            $categories[] = self::CATEGORY_PRODUK_JADI;
        }

        $itemTipes = $transfer->relationLoaded('items')
            ? $transfer->items->map(static fn ($item) => $item->produk?->tipe)->filter()->all()
            : [];

        $categories = array_values(array_unique(array_merge(
            $categories,
            $this->mapTipesToCategories($itemTipes)
        )));

        if (empty($categories) && in_array($transfer->jenis, ['antar_fulfillment', 'distribusi'], true)) {
            return [self::CATEGORY_PRODUK_JADI];
        }

        return $categories;
    }

    /**
     * Validasi kecocokan item terhadap jenis Transfer yang secara eksplisit mengikat satu kategori.
     * jenis 'antar_fulfillment' bersifat agnostik kategori dan dikawal langsung lewat izin kategori itemnya.
     *
     * @param  array<int, string|null>  $produkIds
     */
    public function resolveExplicitJenisItemMismatch(string $jenis, array $produkIds): ?string
    {
        $itemCategories = $this->resolveProductCategories($produkIds);

        if (in_array($jenis, ['pemenuhan_request_bahan', 'retur_bahan'], true)
            && in_array(self::CATEGORY_PRODUK_JADI, $itemCategories, true)
        ) {
            return 'Transfer Bahan Baku tidak boleh berisi item Produk Jadi.';
        }

        if (in_array($jenis, ['kirim_produk_jadi', 'retur_produk_jadi'], true)
            && in_array(self::CATEGORY_BAHAN, $itemCategories, true)
        ) {
            return 'Transfer Produk Jadi tidak boleh berisi item Bahan Baku atau Kemasan.';
        }

        return null;
    }

    /**
     * Validasi kecocokan kategori produk di payload Request terhadap jenis Request.
     *
     * @param  array<int, string|null>  $produkIds
     */
    public function resolveRequestItemCategoryViolations(string $jenis, array $produkIds): ?string
    {
        $itemCategories = $this->resolveProductCategories($produkIds);

        if ($jenis === 'request_bahan' && in_array(self::CATEGORY_PRODUK_JADI, $itemCategories, true)) {
            return 'Request Bahan Baku hanya boleh berisi item Bahan Baku atau Kemasan.';
        }

        if ($jenis === 'request_produk_jadi' && in_array(self::CATEGORY_BAHAN, $itemCategories, true)) {
            return 'Request Produk Jadi hanya boleh berisi item Produk Jadi.';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function allowedRequestTypes(User $user): array
    {
        $types = [];
        if ($user->can('request.bahan.create')) {
            $types[] = 'request_bahan';
        }
        if ($user->can('request.produk_jadi.create')) {
            $types[] = 'request_produk_jadi';
        }

        return $types;
    }

    /**
     * Null berarti semua tipe produk diizinkan; array kosong berarti tidak ada yang diizinkan.
     *
     * @return array<int, string>|null
     */
    public function allowedRequestProductTipes(User $user): ?array
    {
        return $this->allowedProductTipesForBooleans(
            $user->can('request.bahan.create'),
            $user->can('request.produk_jadi.create')
        );
    }

    public function canCreateRequestForType(User $user, string $jenis): bool
    {
        return match ($jenis) {
            'request_bahan' => $user->can('request.bahan.create'),
            'request_produk_jadi' => $user->can('request.produk_jadi.create'),
            default => false,
        };
    }

    /**
     * @param  array<int, string>  $categories
     */
    public function canCreateRequestWithCategories(User $user, array $categories): bool
    {
        return $this->checkAllCategories($user, $categories, 'request', 'create');
    }

    /**
     * @return array<int, string>
     */
    public function allowedTransferJenis(User $user): array
    {
        $allowBahan = $this->canCreateTransferCategory($user, self::CATEGORY_BAHAN);
        $allowProdukJadi = $this->canCreateTransferCategory($user, self::CATEGORY_PRODUK_JADI);

        $jenis = [];
        if ($allowBahan) {
            $jenis[] = 'pemenuhan_request_bahan';
            $jenis[] = 'retur_bahan';
        }
        if ($allowProdukJadi) {
            $jenis[] = 'kirim_produk_jadi';
            $jenis[] = 'retur_produk_jadi';
        }
        if ($allowBahan || $allowProdukJadi) {
            $jenis[] = 'antar_fulfillment';
        }

        return $jenis;
    }

    /**
     * Null berarti semua tipe produk diizinkan; array kosong berarti tidak ada yang diizinkan.
     *
     * @return array<int, string>|null
     */
    public function allowedTransferProductTipes(User $user): ?array
    {
        return $this->allowedProductTipesForBooleans(
            $this->canCreateTransferCategory($user, self::CATEGORY_BAHAN),
            $this->canCreateTransferCategory($user, self::CATEGORY_PRODUK_JADI)
        );
    }

    public function canCreateTransferCategory(User $user, string $category): bool
    {
        if (! in_array($category, [self::CATEGORY_BAHAN, self::CATEGORY_PRODUK_JADI], true)) {
            return false;
        }

        return $user->can("transfer.{$category}.create");
    }

    public function canCreateTransferForRequest(User $user, StockRequest $stockRequest): bool
    {
        $category = $stockRequest->jenis === 'request_produk_jadi'
            ? self::CATEGORY_PRODUK_JADI
            : self::CATEGORY_BAHAN;

        return $this->canCreateTransferCategory($user, $category);
    }

    public function canCreateTransferForBatch(User $user, BatchProduksi $batch): bool
    {
        return $this->canCreateTransferCategory($user, self::CATEGORY_PRODUK_JADI);
    }

    /**
     * @param  array<int, string>  $categories
     */
    public function canCreateTransferWithCategories(User $user, array $categories): bool
    {
        return $this->checkAllCategories($user, $categories, 'transfer', 'create');
    }

    /**
     * @param  array<int, string>  $categories
     */
    public function canShipCategories(User $user, array $categories): bool
    {
        return $this->checkAllCategories($user, $categories, 'transfer', 'ship');
    }

    public function canShipTransfer(User $user, Transfer $transfer): bool
    {
        return $this->canShipCategories($user, $this->resolveCategoriesForTransfer($transfer));
    }

    public function canReceiveTransfer(User $user, Transfer $transfer): bool
    {
        return $this->checkAllCategories($user, $this->resolveCategoriesForTransfer($transfer), 'transfer', 'receive');
    }

    public function canCancelTransfer(User $user, Transfer $transfer): bool
    {
        if (! in_array($transfer->status, ['draft', 'dikirim'], true) || ! $user->can('transfer.cancel')) {
            return false;
        }

        if ($user->hasAnyRole(['manager', 'admin'])) {
            return true;
        }

        if ((int) $transfer->created_by !== (int) $user->id) {
            return false;
        }

        return $this->canCreateTransferWithCategories($user, $this->resolveCategoriesForTransfer($transfer));
    }

    public function authorizeOrAbort(bool $allowed, string $message): void
    {
        abort_unless($allowed, 403, $message);
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function checkAllCategories(User $user, array $categories, string $prefix, string $action): bool
    {
        $uniqueCategories = array_values(array_unique($categories));
        if (empty($uniqueCategories)) {
            return false;
        }

        foreach ($uniqueCategories as $category) {
            if (! in_array($category, [self::CATEGORY_BAHAN, self::CATEGORY_PRODUK_JADI], true)) {
                return false;
            }
            if (! $user->can("{$prefix}.{$category}.{$action}")) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>|null
     */
    private function allowedProductTipesForBooleans(bool $allowBahan, bool $allowProdukJadi): ?array
    {
        if ($allowBahan && $allowProdukJadi) {
            return null;
        }
        if ($allowBahan) {
            return $this->allowedBahanProductTipes();
        }
        if ($allowProdukJadi) {
            return [self::CATEGORY_PRODUK_JADI];
        }

        return [];
    }

    /**
     * @param  iterable<int, string|null>  $tipes
     * @return array<int, string>
     */
    private function mapTipesToCategories(iterable $tipes): array
    {
        $categories = [];
        foreach ($tipes as $tipe) {
            if ($tipe === null || $tipe === '') {
                continue;
            }
            $categories[] = $this->isProdukJadiTipe($tipe)
                ? self::CATEGORY_PRODUK_JADI
                : self::CATEGORY_BAHAN;
        }

        return array_values(array_unique($categories));
    }
}
