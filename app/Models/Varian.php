<?php

namespace App\Models;

use App\Traits\AuditableMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Varian extends Model
{
    use AuditableMasterData;

    protected $table = 'varian';

    protected $fillable = [
        'kategori_id',
        'nama',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'varian_id');
    }
}
