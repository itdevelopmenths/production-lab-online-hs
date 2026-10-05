<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KlasifikasiAbc extends Model
{
    use HasFactory;

    protected $table = 'klasifikasi_abc';

    protected $fillable = [
        'kode',
        'nama',
        'tambahan_buffer_hari',
        'deskripsi',
        'warna_badge',
        'is_active',
    ];

    protected $casts = [
        'tambahan_buffer_hari' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke Analisa Impor
     */
    public function analisaImpor(): HasMany
    {
        return $this->hasMany(AnalisaImpor::class, 'klasifikasi_abc_id');
    }

    /**
     * Relasi ke Analisa Impor Meta
     */
    public function analisaImporMeta(): HasMany
    {
        return $this->hasMany(AnalisaImporMeta::class, 'klasifikasi_abc_id');
    }
}
