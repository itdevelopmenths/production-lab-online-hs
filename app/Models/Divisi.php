<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Divisi extends Model
{
    use \App\Traits\AuditableMasterData;

    protected $table = 'divisi';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const AVAILABLE_COLORS = [
        'slate' => 'Abu-abu (Slate)',
        'indigo' => 'Indigo / Royal Blue',
        'blue' => 'Biru (Sky/Blue)',
        'emerald' => 'Hijau Zamrud (Emerald)',
        'teal' => 'Teal / Hijau Laut',
        'amber' => 'Kuning Kunyit (Amber)',
        'rose' => 'Merah Mawar (Rose)',
        'purple' => 'Ungu Elegan (Purple)',
        'cyan' => 'Sian / Biru Terang (Cyan)',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'divisi_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Mengembalikan Tailwind CSS class untuk badge UI berdasarkan pilihan warna.
     */
    public function badgeClass(): string
    {
        return match ($this->color) {
            'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
            'blue' => 'bg-blue-50 text-blue-700 border-blue-200/80',
            'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
            'teal' => 'bg-teal-50 text-teal-700 border-teal-200/80',
            'amber' => 'bg-amber-50 text-amber-700 border-amber-200/80',
            'rose' => 'bg-rose-50 text-rose-700 border-rose-200/80',
            'purple' => 'bg-purple-50 text-purple-700 border-purple-200/80',
            'cyan' => 'bg-cyan-50 text-cyan-700 border-cyan-200/80',
            default => 'bg-slate-50 text-slate-700 border-slate-200/80',
        };
    }
}
