<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class BomController extends Controller
{
    public function index()
    {
        $this->authorize('bom.view');

        return view('bom.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('bom.view');

        $query = Produk::query()->produkJadi()->select('produk.*')
            ->withCount('bom as bom_count');

        return DataTables::eloquent($query)
            ->addColumn('bom_count', fn ($p) => $p->bom_count)
            ->addColumn('action', fn ($p) => view('bom._actions', ['p' => $p])->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function edit(Produk $produk)
    {
        $this->authorize('bom.view');
        $items = $produk->bom()->with('bahan:id,sku,nama,satuan')->get();
        $bahanList = Produk::bahan()->active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);

        return view('bom.edit', compact('produk', 'items', 'bahanList'));
    }

    public function update(Request $request, Produk $produk)
    {
        $this->authorize('bom.manage');

        $data = $request->validate([
            'items' => ['array'],
            'items.*.bahan_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty_per_unit' => ['required', 'numeric', 'gt:0'],
        ]);

        DB::transaction(function () use ($produk, $data) {
            $produk->bom()->delete();
            foreach ($data['items'] ?? [] as $row) {
                Bom::create([
                    'produk_jadi_id' => $produk->id,
                    'bahan_id' => $row['bahan_id'],
                    'qty_per_unit' => $row['qty_per_unit'],
                ]);
            }
        });

        return redirect()->route('bom.index')->with('success', "BOM {$produk->nama} berhasil disimpan.");
    }

    public function import(Request $request)
    {
        $this->authorize('bom.import');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle, 0, ';');
        $skuIndex = array_flip(array_map('trim', $header ?: []));
        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($handle, $skuIndex, &$imported, &$errors) {
            $line = 1;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $line++;
                $skuJadi = trim($row[$skuIndex['sku_produk_jadi'] ?? 0] ?? '');
                $skuBahan = trim($row[$skuIndex['sku_bahan'] ?? 1] ?? '');
                $qty = (float) str_replace(',', '.', $row[$skuIndex['qty_per_unit'] ?? 2] ?? '0');

                $jadi = Produk::where('sku', $skuJadi)->first();
                $bahan = Produk::where('sku', $skuBahan)->first();
                if (! $jadi || ! $bahan || $qty <= 0) {
                    $errors[] = "Baris {$line}: data tidak valid ({$skuJadi} / {$skuBahan}).";

                    continue;
                }
                Bom::updateOrCreate(
                    ['produk_jadi_id' => $jadi->id, 'bahan_id' => $bahan->id],
                    ['qty_per_unit' => $qty],
                );
                $imported++;
            }
        });
        fclose($handle);

        $msg = "{$imported} baris BOM diimpor.";
        if ($errors) {
            $msg .= ' ' . count($errors) . ' baris dilewati.';
        }

        return redirect()->route('bom.index')->with($errors ? 'error' : 'success', $msg);
    }
}
