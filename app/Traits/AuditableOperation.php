<?php

namespace App\Traits;

use App\Models\OperationalAudit;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait AuditableOperation
{
    /** Flag untuk mencegah pencatatan ganda saat controller memanggil recordAudit() secara eksplisit */
    protected bool $suppressAutoUpdateAudit = false;

    public static function bootAuditableOperation(): void
    {
        static::created(function ($model) {
            $user = Auth::user();
            $newValues = $model->attributesToArray();
            unset($newValues['created_at'], $newValues['updated_at']);

            $refNo = $model->getAuditReferenceNumber();
            $module = $model->getAuditModuleName();
            $statusLabel = isset($model->status) ? ucwords(str_replace('_', ' ', $model->status)) : 'Baru';

            OperationalAudit::create([
                'auditable_type' => get_class($model),
                'auditable_id' => (string) $model->getKey(),
                'module' => $module,
                'nomor_referensi' => $refNo,
                'event' => 'created',
                'action_title' => "Pembuatan dokumen {$refNo} (Status: {$statusLabel})",
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistem',
                'user_role' => $user?->roles?->pluck('name')?->first() ?? 'User',
                'old_values' => null,
                'new_values' => $newValues,
                'changed_fields' => array_keys($newValues),
                'metadata' => null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });

        static::updated(function ($model) {
            if ($model->suppressAutoUpdateAudit) {
                return;
            }

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

            $refNo = $model->getAuditReferenceNumber();
            $module = $model->getAuditModuleName();

            // Bangun ringkasan aksi otomatis jika terdapat status yang berubah
            $title = "Pembaruan data dokumen {$refNo}";
            $event = 'updated';

            if (isset($changes['status'])) {
                $oldStatus = ucwords(str_replace('_', ' ', $oldValues['status'] ?? ''));
                $newStatus = ucwords(str_replace('_', ' ', $newValues['status'] ?? ''));
                $title = "Perubahan status dokumen {$refNo}: {$oldStatus} &rarr; {$newStatus}";
                $event = match ($changes['status']) {
                    'diajukan' => 'submitted',
                    'disetujui' => 'approved',
                    'selesai' => 'completed',
                    'dibatalkan' => 'cancelled',
                    'release' => 'release',
                    'dikirim_ke_gudang', 'diproses', 'dikirim' => 'status_changed',
                    default => 'updated',
                };
            }

            OperationalAudit::create([
                'auditable_type' => get_class($model),
                'auditable_id' => (string) $model->getKey(),
                'module' => $module,
                'nomor_referensi' => $refNo,
                'event' => $event,
                'action_title' => $title,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistem',
                'user_role' => $user?->roles?->pluck('name')?->first() ?? 'User',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => $changedFields,
                'metadata' => null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });

        static::deleted(function ($model) {
            $user = Auth::user();
            $oldValues = $model->attributesToArray();
            unset($oldValues['created_at'], $oldValues['updated_at']);

            $refNo = $model->getAuditReferenceNumber();
            $module = $model->getAuditModuleName();

            OperationalAudit::create([
                'auditable_type' => get_class($model),
                'auditable_id' => (string) $model->getKey(),
                'module' => $module,
                'nomor_referensi' => $refNo,
                'event' => 'deleted',
                'action_title' => "Penghapusan dokumen {$refNo} dari sistem",
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistem',
                'user_role' => $user?->roles?->pluck('name')?->first() ?? 'User',
                'old_values' => $oldValues,
                'new_values' => null,
                'changed_fields' => array_keys($oldValues),
                'metadata' => null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });
    }

    /**
     * Catat log audit kustom dengan konteks operasional terperinci
     */
    public function recordAudit(
        string $event,
        string $actionTitle,
        ?array $customChanges = null,
        ?array $metadata = null
    ): OperationalAudit {
        $this->suppressAutoUpdateAudit = true;

        $user = Auth::user();
        $oldValues = null;
        $newValues = null;
        $changedFields = null;

        if ($customChanges) {
            $oldValues = [];
            $newValues = [];
            $changedFields = [];
            foreach ($customChanges as $key => $vals) {
                $changedFields[] = $key;
                if (is_array($vals) && count($vals) === 2) {
                    $oldValues[$key] = $vals[0];
                    $newValues[$key] = $vals[1];
                } else {
                    $newValues[$key] = $vals;
                }
            }
        }

        return OperationalAudit::create([
            'auditable_type' => get_class($this),
            'auditable_id' => (string) $this->getKey(),
            'module' => $this->getAuditModuleName(),
            'nomor_referensi' => $this->getAuditReferenceNumber(),
            'event' => $event,
            'action_title' => $actionTitle,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistem',
            'user_role' => $user?->roles?->pluck('name')?->first() ?? 'User',
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public function operationalAudits(): MorphMany
    {
        return $this->morphMany(OperationalAudit::class, 'auditable')->latest('id');
    }

    public function getAuditReferenceNumber(): string
    {
        return (string) ($this->no_po ?? $this->no_batch ?? $this->no_transaksi ?? $this->getKey());
    }

    public function getAuditModuleName(): string
    {
        if ($this instanceof \App\Models\PurchaseOrder) {
            return 'purchasing';
        }
        if ($this instanceof \App\Models\BatchProduksi) {
            return 'produksi';
        }
        if ($this instanceof \App\Models\RequestTransfer) {
            return $this->jenis === 'req_bahan' ? 'request' : 'transfer';
        }
        return 'operasional';
    }
}
