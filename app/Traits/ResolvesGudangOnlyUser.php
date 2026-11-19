<?php

namespace App\Traits;

use App\Models\User;

/**
 * Satu definisi "user gudang murni" untuk modul Purchasing:
 * boleh menerima barang tetapi tidak boleh mengedit PO.
 */
trait ResolvesGudangOnlyUser
{
    private function isGudangOnly(User $user): bool
    {
        return ! $user->can('purchasing.edit') && $user->can('purchasing.receive');
    }
}
