<?php

namespace App\Models;

use App\Traits\AuditableOperation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BatchProduksi extends Model
{
    use HasUuids, AuditableOperation;

    protected $table = 'batch_produksi';

    protected $fillable = [
        'no_batch',
        'produk_id',
        'qty_rencana',
        'qty_baik',
        'qty_rusak',
        'gudang_operasional_id',
        'gudang_tujuan_rencana_id',
        'status',
        'tanggal',
        'created_by',
    ];

    protected $casts = [
        'qty_rencana' => 'decimal:2',
        'qty_baik' => 'decimal:2',
        'qty_rusak' => 'decimal:2',
        'tanggal' => 'date',
    ];

    public const STATUS = ['rencana', 'release', 'selesai', 'dibatalkan'];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function gudangOperasional(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_operasional_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_rencana_id');
    }

    public function alokasi(): HasMany
    {
        return $this->hasMany(AlokasiBahan::class, 'batch_id');
    }

    public function opname(): HasMany
    {
        return $this->hasMany(StockOpname::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(BatchProduksiOutput::class, 'batch_id');
    }

    public function items(): HasMany
    {
        return $this->outputs();
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(RequestTransfer::class, 'referensi_batch_id');
    }

    public function stockRequests(): HasMany
    {
        return $this->hasMany(StockRequest::class, 'referensi_batch_id');
    }

    public function modernTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'referensi_batch_id');
    }

    public function transferKirim(): HasOne
    {
        return $this->hasOne(Transfer::class, 'referensi_batch_id')
            ->where('jenis', 'kirim_produk_jadi')
            ->where('status', '!=', 'dibatalkan');
    }

    /** Yield (%) = qty_baik / qty_rencana * 100. */
    public function yield(): ?float
    {
        if ($this->qty_baik === null || (float) $this->qty_rencana <= 0) {
            return null;
        }
        return (float) $this->qty_baik / (float) $this->qty_rencana * 100;
    }

    public function defectRate(): ?float
    {
        $total = (float) ($this->qty_baik ?? 0) + (float) ($this->qty_rusak ?? 0);
        if ($this->qty_rusak === null || $total <= 0) {
            return null;
        }
        return (float) $this->qty_rusak / $total * 100;
    }

    /**
     * Rekap Kebutuhan vs Diminta vs Diterima per bahan baku untuk batch ini (FR-PRD-05, TC-41).
     */
    public function rekapBahan(): array
    {
        $this->loadMissing(['alokasi.bahan']);
        $rekap = [];

        // 1. Kebutuhan BOM dari alokasi bahan
        foreach ($this->alokasi as $alok) {
            $bahanId = (int) $alok->bahan_id;
            $rekap[$bahanId] = [
                'bahan_id' => $bahanId,
                'nama' => $alok->bahan?->nama ?? '—',
                'sku' => $alok->bahan?->sku ?? '—',
                'satuan' => $alok->bahan?->satuan ?? 'pcs',
                'kebutuhan' => (float) $alok->qty_dialokasikan,
                'diminta' => 0.0,
                'diterima' => 0.0,
            ];
        }

        // 2. Total diminta: prioritaskan tabel baru `requests` (Epic 4), fallback ke legacy `request_transfers`
        $hasModernRequests = false;
        if (\Illuminate\Support\Facades\Schema::hasTable('requests')) {
            $reqRows = \Illuminate\Support\Facades\DB::table('requests as r')
                ->join('request_items as ri', 'ri.request_id', '=', 'r.id')
                ->where('r.referensi_batch_id', $this->id)
                ->where('r.jenis', 'request_bahan')
                ->whereNotIn('r.status', ['dibatalkan', 'ditolak'])
                ->select('ri.produk_id', \Illuminate\Support\Facades\DB::raw('SUM(ri.qty_diminta) as total_diminta'))
                ->groupBy('ri.produk_id')
                ->get();

            if ($reqRows->isNotEmpty()) {
                $hasModernRequests = true;
                foreach ($reqRows as $r) {
                    $pid = (int) $r->produk_id;
                    if (! isset($rekap[$pid])) {
                        $prod = Produk::find($pid);
                        $rekap[$pid] = [
                            'bahan_id' => $pid,
                            'nama' => $prod?->nama ?? '—',
                            'sku' => $prod?->sku ?? '—',
                            'satuan' => $prod?->satuan ?? 'pcs',
                            'kebutuhan' => 0.0,
                            'diminta' => 0.0,
                            'diterima' => 0.0,
                        ];
                    }
                    $rekap[$pid]['diminta'] += (float) $r->total_diminta;
                }
            }
        }

        if (! $hasModernRequests && \Illuminate\Support\Facades\Schema::hasTable('request_transfers')) {
            $rtRows = \Illuminate\Support\Facades\DB::table('request_transfers as rt')
                ->join('request_transfer_items as rti', 'rti.request_transfer_id', '=', 'rt.id')
                ->where('rt.referensi_batch_id', $this->id)
                ->where('rt.jenis', 'req_bahan')
                ->where('rt.status', '!=', 'dibatalkan')
                ->select('rti.produk_id', \Illuminate\Support\Facades\DB::raw('SUM(COALESCE(rti.qty_diminta, 0)) as total_diminta'))
                ->groupBy('rti.produk_id')
                ->get();

            foreach ($rtRows as $r) {
                $pid = (int) $r->produk_id;
                if (! isset($rekap[$pid])) {
                    $prod = Produk::find($pid);
                    $rekap[$pid] = [
                        'bahan_id' => $pid,
                        'nama' => $prod?->nama ?? '—',
                        'sku' => $prod?->sku ?? '—',
                        'satuan' => $prod?->satuan ?? 'pcs',
                        'kebutuhan' => 0.0,
                        'diminta' => 0.0,
                        'diterima' => 0.0,
                    ];
                }
                $rekap[$pid]['diminta'] += (float) $r->total_diminta;
            }
        }

        // 3. Total diterima: prioritaskan tabel baru `transfers` (Epic 4), fallback ke legacy `request_transfers`
        $hasModernTransfers = false;
        if (\Illuminate\Support\Facades\Schema::hasTable('transfers')) {
            $trfRows = \Illuminate\Support\Facades\DB::table('transfers as t')
                ->leftJoin('requests as r', 'r.id', '=', 't.request_id')
                ->join('transfer_items as ti', 'ti.transfer_id', '=', 't.id')
                ->where(function ($w) {
                    $w->where('t.referensi_batch_id', $this->id)
                        ->orWhere('r.referensi_batch_id', $this->id);
                })
                ->where('t.status', 'selesai')
                ->where('t.jenis', 'pemenuhan_request_bahan')
                ->select('ti.produk_id', \Illuminate\Support\Facades\DB::raw('SUM(COALESCE(ti.qty_baik, ti.qty_diterima, 0)) as total_diterima'))
                ->groupBy('ti.produk_id')
                ->get();

            if ($trfRows->isNotEmpty()) {
                $hasModernTransfers = true;
                foreach ($trfRows as $r) {
                    $pid = (int) $r->produk_id;
                    if (isset($rekap[$pid])) {
                        $rekap[$pid]['diterima'] += (float) $r->total_diterima;
                    }
                }
            }
        }

        if (! $hasModernTransfers && \Illuminate\Support\Facades\Schema::hasTable('request_transfers')) {
            $receivedRows = \Illuminate\Support\Facades\DB::table('request_transfers as rt')
                ->join('request_transfer_items as rti', 'rti.request_transfer_id', '=', 'rt.id')
                ->where('rt.referensi_batch_id', $this->id)
                ->where('rt.status', 'selesai')
                ->whereIn('rt.jenis', ['req_bahan', 'pemenuhan_request_bahan'])
                ->select('rti.produk_id', \Illuminate\Support\Facades\DB::raw('SUM(COALESCE(rti.qty_baik, rti.qty_diterima, 0)) as total_diterima'))
                ->groupBy('rti.produk_id')
                ->get();

            foreach ($receivedRows as $r) {
                $pid = (int) $r->produk_id;
                if (isset($rekap[$pid])) {
                    $rekap[$pid]['diterima'] += (float) $r->total_diterima;
                }
            }
        }

        return array_values($rekap);
    }
}
