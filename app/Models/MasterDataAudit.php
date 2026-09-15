<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MasterDataAudit extends Model
{
    protected $table = 'master_data_audits';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'user_id',
        'user_name',
        'item_name',
        'event',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
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
     * Menghasilkan teks deskriptif ringkas dari perubahan data
     */
    public function getFormattedDiffAttribute(): string
    {
        if ($this->event === 'created') {
            return '<span class="text-emerald-700 font-semibold">Data baru dibuat</span>';
        }

        if ($this->event === 'deleted') {
            return '<span class="text-rose-700 font-semibold">Data dihapus dari sistem</span>';
        }

        if ($this->event === 'imported') {
            return '<span class="text-indigo-700 font-semibold">Data diimpor melalui berkas batch</span>';
        }

        $changes = [];
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        foreach ($this->changed_fields ?? [] as $field) {
            $from = isset($old[$field]) ? (is_array($old[$field]) ? json_encode($old[$field]) : (string) $old[$field]) : '—';
            $to = isset($new[$field]) ? (is_array($new[$field]) ? json_encode($new[$field]) : (string) $new[$field]) : '—';
            $fieldLabel = ucwords(str_replace('_', ' ', $field));
            $changes[] = "<span class=\"font-medium text-gray-700\">{$fieldLabel}:</span> <span class=\"text-rose-600 line-through\">{$from}</span> &rarr; <span class=\"text-emerald-600 font-semibold\">{$to}</span>";
        }

        return !empty($changes) ? implode('<br>', $changes) : '<span class="text-gray-400">Tidak ada perubahan atribut</span>';
    }
}
