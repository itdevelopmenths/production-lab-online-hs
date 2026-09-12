<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'divisi',
        'password',
    ];

    public const DIVISI_LIST = [
        'purchasing' => 'Purchasing & Pengadaan',
        'produksi' => 'Produksi & Laboratorium',
        'gudang' => 'Gudang & Logistik',
        'fulfillment' => 'Fulfillment & Distribusi',
        'qc' => 'Quality Control (QC)',
        'finance' => 'Finance & Akuntansi',
        'manajemen' => 'Manajemen & Direksi',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Nama role utama untuk ditampilkan (role switcher / badge). */
    public function roleName(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function roleDisplayName(): ?string
    {
        $role = $this->roles->first();
        return $role?->display_name ?: $this->roleName();
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function divisiLabel(): string
    {
        if (empty($this->divisi)) {
            return '-';
        }

        return self::DIVISI_LIST[$this->divisi] ?? ucwords(str_replace(['_', '-'], ' ', $this->divisi));
    }

    public function isGlobalWarehouseAccess(): bool
    {
        return $this->isManager() || $this->can('stok.view.all') || $this->gudangs()->count() === 0;
    }

    public function primaryGudang(): ?Gudang
    {
        return $this->gudangs()->wherePivot('is_primary', true)->first() ?: $this->gudangs()->first();
    }

    public function gudangs()
    {
        return $this->belongsToMany(Gudang::class, 'gudang_user', 'user_id', 'gudang_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
