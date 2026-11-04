<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use \App\Traits\AuditableMasterData;

    protected $table = 'supplier';

    protected $fillable = [
        'nama',
        'kategori',
        'kontak',
        'alamat',
        'termin_default',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class, 'supplier_id');
    }

    public function produks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Produk::class, 'produk_supplier', 'supplier_id', 'produk_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function activeProduks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->produks()->wherePivot('status', 'aktif');
    }

    public function produkSuppliers(): HasMany
    {
        return $this->hasMany(ProdukSupplier::class, 'supplier_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
