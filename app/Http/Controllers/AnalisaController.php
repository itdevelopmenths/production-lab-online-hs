<?php

namespace App\Http\Controllers;

use App\Models\AnalisaFulfillmentInput;
use App\Models\AnalisaImporMeta;
use App\Models\AnalisaLokalInput;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\RiwayatAnalisa;
use App\Services\AnalisaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalisaController extends Controller
{
    public function __construct(private readonly AnalisaService $analisa)
    {
    }

    public function index()
    {
        $this->authorize('analisa.view');

        return view('analisa.index');
    }

    public function lokalData(): JsonResponse
    {
        $this->authorize('analisa.view');

        $rows = AnalisaLokalInput::with('produk:id,sku,nama,satuan,satuan_order_moq')->get()
            ->map(function ($input) {
                $hasil = $this->analisa->lokal($input);

                return array_merge([
                    'produk_id' => $input->produk_id,
                    'sku' => $input->produk?->sku,
                    'nama' => $input->produk?->nama,
                    'stok_saat_ini' => (float) $input->stok_saat_ini,
                    'akan_datang' => (float) $input->akan_datang,
                ], $hasil);
            });

        return response()->json(['data' => $rows]);
    }

    public function imporData(): JsonResponse
    {
        $this->authorize('analisa.view');

        $rows = AnalisaImporMeta::with(['produk:id,sku,nama,satuan,satuan_order_moq', 'varian'])->get()
            ->map(function ($meta) {
                $hasil = $this->analisa->impor($meta);

                return [
                    'produk_id' => $meta->produk_id,
                    'sku' => $meta->produk?->sku,
                    'nama' => $meta->produk?->nama,
                    'punya_varian' => $meta->punya_varian,
                    'klasifikasi_abc' => $meta->klasifikasi_abc,
                    'buffer_days' => $hasil['buffer_days'],
                    'total_qty_order' => $hasil['total_qty_order'],
                    'total_selisih' => $hasil['total_selisih'],
                    'status' => $hasil['status'],
                    'total_nominal_order' => $hasil['total_nominal_order'],
                    'varian' => $hasil['varian'],
                ];
            });

        return response()->json(['data' => $rows]);
    }

    public function fulfillmentData(): JsonResponse
    {
        $this->authorize('analisa.view');

        $grouped = AnalisaFulfillmentInput::with(['produk:id,sku,nama,satuan_order_moq', 'gudang:id,nama'])
            ->get()->groupBy('produk_id');

        $rows = $grouped->map(function ($rows, $produkId) {
            $produk = $rows->first()->produk;
            $hasil = $this->analisa->fulfillment($rows, (float) ($produk?->satuan_order_moq ?? 1));

            return array_merge([
                'produk_id' => $produkId,
                'sku' => $produk?->sku,
                'nama' => $produk?->nama,
            ], $hasil);
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function riwayatData(): JsonResponse
    {
        $this->authorize('analisa.view');

        $rows = RiwayatAnalisa::with('pencatat:id,name')->latest()->limit(500)->get()
            ->map(fn ($r) => [
                'tanggal' => $r->tanggal->format('d/m/Y'),
                'tipe' => ucwords(str_replace('_', ' ', $r->tipe)),
                'item_label' => $r->item_label,
                'batas_minimum' => (float) $r->batas_minimum,
                'target_stock' => (float) $r->target_stock,
                'status' => $r->status,
                'qty_order' => (float) $r->qty_order,
                'pencatat' => $r->pencatat?->name,
            ]);

        return response()->json(['data' => $rows]);
    }

    /** Simpan snapshot hasil analisa saat ini ke tab Riwayat. */
    public function snapshot(Request $request)
    {
        $this->authorize('analisa.snapshot');

        $tipe = $request->validate([
            'tipe' => ['required', Rule::in(['bahan_lokal', 'bahan_impor', 'produk_jadi_fulfillment'])],
        ])['tipe'];

        $count = DB::transaction(function () use ($tipe) {
            $n = 0;
            if ($tipe === 'bahan_lokal') {
                foreach (AnalisaLokalInput::with('produk')->get() as $input) {
                    $h = $this->analisa->lokal($input);
                    $this->simpanRiwayat($tipe, $input->produk?->sku ?? '-', $h['batas_minimum'], $h['target_stock'], $h['status'], $h['qty_order']);
                    $n++;
                }
            } elseif ($tipe === 'bahan_impor') {
                foreach (AnalisaImporMeta::with(['produk', 'varian'])->get() as $meta) {
                    $h = $this->analisa->impor($meta);
                    $this->simpanRiwayat($tipe, $meta->produk?->sku ?? '-', 0, 0, $h['status'], $h['total_qty_order']);
                    $n++;
                }
            } else {
                $grouped = AnalisaFulfillmentInput::with(['produk', 'gudang'])->get()->groupBy('produk_id');
                foreach ($grouped as $rows) {
                    $produk = $rows->first()->produk;
                    $h = $this->analisa->fulfillment($rows, (float) ($produk?->satuan_order_moq ?? 1));
                    $this->simpanRiwayat($tipe, $produk?->sku ?? '-', $h['batas_minimum_total'], $h['target_stock_total'], $h['status'], $h['qty_order']);
                    $n++;
                }
            }

            return $n;
        });

        return back()->with('success', "Snapshot tersimpan ({$count} baris).");
    }

    /** Buat draft PO dari hasil analisa (ditandai "Dari Analisa"). */
    public function createPo(Request $request)
    {
        $this->authorize('analisa.create_po');

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:supplier,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.harga_total' => ['required', 'numeric', 'min:0'],
        ]);

        $po = DB::transaction(function () use ($data) {
            $year = now()->year;
            $no = sprintf('PO-%d-%04d', $year, PurchaseOrder::whereYear('tanggal', $year)->count() + 1);
            $po = PurchaseOrder::create([
                'no_po' => $no,
                'supplier_id' => $data['supplier_id'],
                'tanggal' => now()->toDateString(),
                'status' => 'draft',
                'dari_analisa' => true,
                'created_by' => auth()->id(),
            ]);
            foreach ($data['items'] as $row) {
                $po->items()->create($row);
            }

            return $po;
        });

        return redirect()->route('purchasing.show', $po)->with('success', "Draft PO {$po->no_po} dibuat dari Analisa.");
    }

    private function simpanRiwayat(string $tipe, string $label, float $batasMin, float $target, string $status, float $qtyOrder): void
    {
        RiwayatAnalisa::create([
            'tanggal' => now()->toDateString(),
            'tipe' => $tipe,
            'item_label' => $label,
            'batas_minimum' => $batasMin,
            'target_stock' => $target,
            'status' => $status,
            'qty_order' => $qtyOrder,
            'dicatat_oleh' => auth()->id(),
        ]);
    }
}
