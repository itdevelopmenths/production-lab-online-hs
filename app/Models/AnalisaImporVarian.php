<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisaImporVarian extends Model
{
    protected $table = 'analisa_impor_varian';

    protected $fillable = [
        'analisa_impor_meta_id',
        'nama_varian',
        'persentase_distribusi',
        'stok_saat_ini',
        'inbound_before_eta',
    ];

    protected $casts = [
        'persentase_distribusi' => 'decimal:4',
        'stok_saat_ini' => 'decimal:2',
        'inbound_before_eta' => 'decimal:2',
    ];

    public function meta(): BelongsTo
    {
        return $this->belongsTo(AnalisaImporMeta::class, 'analisa_impor_meta_id');
    }
}
