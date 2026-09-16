<?php

namespace App\Models;

use App\Traits\AuditableMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    use AuditableMasterData;

    protected $table = 'kategori';

    protected $fillable = [
        'nama',
    ];

    public function varians(): HasMany
    {
        return $this->hasMany(Varian::class, 'kategori_id');
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'kategori_id');
    }
}
