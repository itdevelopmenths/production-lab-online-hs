<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OperationalAudit extends Model
{
    protected $table = 'operational_audits';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'module',
        'nomor_referensi',
        'event',
        'action_title',
        'user_id',
        'user_name',
        'user_role',
        'old_values',
        'new_values',
        'changed_fields',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Badge visual untuk modul operasional
     */
    public function getModuleBadgeAttribute(): string
    {
        return match ($this->module) {
            'purchasing' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">PURCHASING</span>',
            'produksi' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">PRODUKSI</span>',
            'request' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-800 border border-teal-200">REQUEST BAHAN</span>',
            'transfer' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-200">TRANSFER GUDANG</span>',
            default => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-800 border border-gray-200">' . strtoupper($this->module) . '</span>',
        };
    }

    /**
     * Badge visual untuk event / jenis aksi
     */
    public function getEventBadgeAttribute(): string
    {
        return match ($this->event) {
            'created' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">DIBUAT</span>',
            'updated' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">DIUBAH</span>',
            'submitted' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">DIAJUKAN</span>',
            'approved' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-800 border border-teal-300">DISETUJUI</span>',
            'release' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-300">RELEASE</span>',
            'received' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-100 text-cyan-800 border border-cyan-300">DITERIMA</span>',
            'completed' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-800 border border-green-300">SELESAI</span>',
            'paid' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">DIBAYAR</span>',
            'cancelled' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">DIBATALKAN</span>',
            'deleted' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-rose-800 border border-rose-300">DIHAPUS</span>',
            default => '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-800">' . strtoupper($this->event) . '</span>',
        };
    }

    /**
     * Teks deskriptif terperinci mengenai perubahan nilai
     */
    public function getFormattedDiffAttribute(): string
    {
        $html = [];

        if (! empty($this->action_title)) {
            $html[] = '<div class="font-medium text-gray-900 leading-snug mb-1">' . e($this->action_title) . '</div>';
        }

        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $changed = $this->changed_fields ?? [];

        if (! empty($changed)) {
            $diffLines = [];
            foreach ($changed as $field) {
                // Sembunyikan field internal
                if (in_array($field, ['id', 'created_at', 'updated_at', 'created_by'], true)) {
                    continue;
                }

                $from = isset($old[$field]) ? (is_array($old[$field]) ? json_encode($old[$field]) : (string) $old[$field]) : '—';
                $to = isset($new[$field]) ? (is_array($new[$field]) ? json_encode($new[$field]) : (string) $new[$field]) : '—';
                $fieldLabel = ucwords(str_replace('_', ' ', $field));

                $diffLines[] = "<span class=\"font-medium text-gray-700\">{$fieldLabel}:</span> <span class=\"text-rose-600 line-through\">" . e($from) . "</span> &rarr; <span class=\"text-emerald-700 font-semibold\">" . e($to) . "</span>";
            }
            if (! empty($diffLines)) {
                $html[] = '<div class="text-[11px] text-gray-600 space-y-0.5 pl-2 border-l-2 border-gray-200">' . implode('<br>', $diffLines) . '</div>';
            }
        }

        if (! empty($this->metadata)) {
            $metaPairs = [];
            foreach ($this->metadata as $k => $v) {
                $kLabel = ucwords(str_replace('_', ' ', $k));
                $vStr = is_array($v) ? json_encode($v) : (string) $v;
                $metaPairs[] = "<span class=\"text-gray-500\">{$kLabel}:</span> <span class=\"font-mono font-medium text-gray-800\">" . e($vStr) . "</span>";
            }
            if (! empty($metaPairs)) {
                $html[] = '<div class="text-[10px] text-gray-500 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">' . implode(' &bull; ', $metaPairs) . '</div>';
            }
        }

        return ! empty($html) ? implode('', $html) : '<span class="text-gray-400 italic">Tidak ada rincian atribut</span>';
    }
}
