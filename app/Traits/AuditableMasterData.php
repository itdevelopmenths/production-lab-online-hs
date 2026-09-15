<?php

namespace App\Traits;

use App\Models\MasterDataAudit;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait AuditableMasterData
{
    public static function bootAuditableMasterData(): void
    {
        static::created(function ($model) {
            $user = Auth::user();
            $newValues = $model->attributesToArray();
            unset($newValues['created_at'], $newValues['updated_at']);

            MasterDataAudit::create([
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistem',
                'item_name' => $model->getAuditItemName(),
                'event' => 'created',
                'old_values' => null,
                'new_values' => $newValues,
                'changed_fields' => array_keys($newValues),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $user = Auth::user();
            $oldValues = [];
            $newValues = [];
            $changedFields = [];

            foreach ($changes as $field => $newValue) {
                $oldValue = $model->getOriginal($field);
                if ($oldValue != $newValue) {
                    $oldValues[$field] = $oldValue;
                    $newValues[$field] = $newValue;
                    $changedFields[] = $field;
                }
            }

            if (empty($changedFields)) {
                return;
            }

            MasterDataAudit::create([
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistem',
                'item_name' => $model->getAuditItemName(),
                'event' => 'updated',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => $changedFields,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });

        static::deleted(function ($model) {
            $user = Auth::user();
            $oldValues = $model->attributesToArray();
            unset($oldValues['created_at'], $oldValues['updated_at']);

            MasterDataAudit::create([
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistem',
                'item_name' => $model->getAuditItemName(),
                'event' => 'deleted',
                'old_values' => $oldValues,
                'new_values' => null,
                'changed_fields' => array_keys($oldValues),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(MasterDataAudit::class, 'auditable')->latest();
    }

    public function getAuditItemName(): string
    {
        if (isset($this->nama) && isset($this->sku)) {
            return "{$this->nama} ({$this->sku})";
        }

        if (isset($this->nama)) {
            return (string) $this->nama;
        }

        if (isset($this->kode)) {
            return (string) $this->kode;
        }

        if ($this instanceof \App\Models\Bom) {
            $produk = $this->produkJadi?->nama ?? "Produk #{$this->produk_jadi_id}";
            $bahan = $this->bahan?->nama ?? "Bahan #{$this->bahan_id}";
            return "BOM {$produk} &rarr; {$bahan}";
        }

        return class_basename($this) . " #{$this->getKey()}";
    }
}
