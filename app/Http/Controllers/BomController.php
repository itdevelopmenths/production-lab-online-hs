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

    public function importPage()
    {
        $this->authorize('bom.import');

        $produkJadiList = Produk::produkJadi()->active()->orderBy('nama')->get(['id', 'sku', 'nama']);
        $bahanList = Produk::bahan()->active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);

        return view('bom.import', compact('produkJadiList', 'bahanList'));
    }

    public function downloadTemplate(Request $request)
    {
        $this->authorize('bom.import');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_resep_bom.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            // Header template standar
            fputcsv($handle, ['sku_produk_jadi', 'sku_bahan', 'qty_per_unit'], ';');
            // Contoh baris
            fputcsv($handle, ['PRD-SAMPLE-01', 'BAH-PARFUM-01', '15.5000'], ';');
            fputcsv($handle, ['PRD-SAMPLE-01', 'BAH-ALKOHOL-01', '35.0000'], ';');
            fputcsv($handle, ['PRD-SAMPLE-02', 'BAH-PARFUM-01', '20.0000'], ';');
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importBulk(Request $request): JsonResponse
    {
        $this->authorize('bom.import');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku_produk_jadi' => ['required', 'string'],
            'items.*.sku_bahan' => ['required', 'string'],
            'items.*.qty_per_unit' => ['required', 'numeric', 'gt:0'],
        ]);

        $imported = 0;
        $duplicatesCount = 0;
        $errors = [];
        $seenPairs = [];

        // Map SKU ke Produk secara efisien
        $allSkus = collect($data['items'])
            ->flatMap(fn ($i) => [trim($i['sku_produk_jadi']), trim($i['sku_bahan'])])
            ->unique()
            ->filter();

        $allProduk = Produk::whereIn('sku', $allSkus)->get()->keyBy('sku');

        DB::transaction(function () use ($data, $allProduk, &$imported, &$duplicatesCount, &$errors, &$seenPairs) {
            foreach ($data['items'] as $index => $item) {
                $rowNum = $index + 1;
                $skuJadi = trim($item['sku_produk_jadi']);
                $skuBahan = trim($item['sku_bahan']);
                $qty = (float) $item['qty_per_unit'];

                // Validasi duplikasi pasangan produk jadi & bahan dalam batch impor
                $pairKey = strtolower($skuJadi) . '|' . strtolower($skuBahan);
                if (isset($seenPairs[$pairKey])) {
                    $duplicatesCount++;
                    $prevRow = $seenPairs[$pairKey];
                    $errors[] = "Baris {$rowNum}: Duplikat terdeteksi — formula untuk SKU Produk '{$skuJadi}' dan Bahan '{$skuBahan}' sudah ada pada baris {$prevRow}. Baris dilewati.";
                    continue;
                }
                $seenPairs[$pairKey] = $rowNum;

                $jadi = $allProduk->get($skuJadi);
                $bahan = $allProduk->get($skuBahan);

                if (! $jadi || $jadi->tipe !== 'produk_jadi') {
                    $errors[] = "Baris {$rowNum}: SKU Produk Jadi '{$skuJadi}' tidak valid atau bukan produk jadi.";
                    continue;
                }

                if (! $bahan || $bahan->tipe === 'produk_jadi') {
                    $errors[] = "Baris {$rowNum}: SKU Bahan '{$skuBahan}' tidak valid atau bukan bahan baku/kemasan.";
                    continue;
                }

                Bom::updateOrCreate(
                    ['produk_jadi_id' => $jadi->id, 'bahan_id' => $bahan->id],
                    ['qty_per_unit' => $qty]
                );
                $imported++;
            }
        });

        if ($imported > 0) {
            \App\Models\MasterDataAudit::create([
                'auditable_type' => Bom::class,
                'auditable_id' => null,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()?->name ?? 'Sistem',
                'item_name' => "Grid Impor Resep BOM ({$imported} formula)",
                'event' => 'imported',
                'old_values' => null,
                'new_values' => [
                    'imported_count' => $imported,
                    'duplicates_count' => $duplicatesCount,
                    'skipped_count' => count($errors),
                ],
                'changed_fields' => ['boms'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $msg = "Berhasil menyimpan {$imported} formula resep BOM.";
        if ($duplicatesCount > 0) {
            $msg .= " ({$duplicatesCount} baris duplikat dilewati).";
        }
        if (count($errors) > 0) {
            $msg .= ' (' . count($errors) . ' baris catatan/dilewati)';
        }

        return response()->json([
            'success' => $imported > 0,
            'message' => $msg,
            'imported_count' => $imported,
            'duplicates_count' => $duplicatesCount,
            'errors' => $errors,
        ]);
    }

    public function import(Request $request, \App\Services\StampsBomImporter $importer)
    {
        $this->authorize('bom.import');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $filePath = $request->file('file')->getRealPath();
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return redirect()->route('bom.import-page')->with('error', 'Gagal membaca file CSV.');
        }

        // Auto-detect delimiter (; or ,)
        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine ?: '', ';') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
        $headerString = strtolower(implode(';', $header ?: []));

        // Format ekspor Stamps POS (Cibinong City Mall)
        if (str_contains($headerString, 'item name') || str_contains($headerString, 'ingredient name')) {
            rewind($handle);
            try {
                $result = $importer->importFromHandle($handle);
                $msg = "Impor Stamps berhasil: {$result['boms_imported']} resep BOM diproses ({$result['products_created']} produk baru, {$result['materials_created']} bahan baru).";
                if (!empty($result['duplicates_skipped'])) {
                    $msg .= " Catatan: {$result['duplicates_skipped']} baris resep duplikat dalam berkas dilewati.";
                }

                \App\Models\MasterDataAudit::create([
                    'auditable_type' => Bom::class,
                    'auditable_id' => null,
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()?->name ?? 'Sistem',
                    'item_name' => "Impor Format Stamps ({$result['boms_imported']} resep)",
                    'event' => 'imported',
                    'old_values' => null,
                    'new_values' => $result,
                    'changed_fields' => ['boms_imported', 'products_created', 'materials_created', 'duplicates_skipped'],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return redirect()->route('bom.index')->with('success', $msg);
            } catch (\Throwable $e) {
                return redirect()->route('bom.import-page')->with('error', 'Gagal memproses file Stamps: ' . $e->getMessage());
            }
        }

        // Format alternatif standar: sku_produk_jadi;sku_bahan;qty_per_unit
        $skuIndex = array_flip(array_map('trim', $header ?: []));
        $imported = 0;
        $duplicatesCount = 0;
        $errors = [];
        $seenPairs = [];

        DB::transaction(function () use ($handle, $delimiter, $skuIndex, &$imported, &$duplicatesCount, &$errors, &$seenPairs) {
            $line = 1;
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $line++;
                $skuJadi = trim($row[$skuIndex['sku_produk_jadi'] ?? 0] ?? '');
                $skuBahan = trim($row[$skuIndex['sku_bahan'] ?? 1] ?? '');
                $qty = (float) str_replace(',', '.', $row[$skuIndex['qty_per_unit'] ?? 2] ?? '0');

                if ($skuJadi === '' && $skuBahan === '') {
                    continue;
                }

                if ($skuJadi === '' || $skuBahan === '') {
                    $errors[] = "Baris {$line}: SKU Produk Jadi atau SKU Bahan tidak boleh kosong.";
                    continue;
                }

                // Validasi duplikasi pasangan produk jadi & bahan dalam berkas CSV
                $pairKey = strtolower($skuJadi) . '|' . strtolower($skuBahan);
                if (isset($seenPairs[$pairKey])) {
                    $duplicatesCount++;
                    $prevLine = $seenPairs[$pairKey];
                    $errors[] = "Baris {$line}: Duplikat terdeteksi — formula untuk SKU Produk '{$skuJadi}' dan Bahan '{$skuBahan}' sudah ada pada baris {$prevLine}. Baris dilewati.";
                    continue;
                }
                $seenPairs[$pairKey] = $line;

                $jadi = Produk::where('sku', $skuJadi)->first();
                $bahan = Produk::where('sku', $skuBahan)->first();
                if (! $jadi || ! $bahan || $qty <= 0) {
                    $errors[] = "Baris {$line}: data tidak valid ({$skuJadi} / {$skuBahan}, qty: {$qty}).";
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

        $msg = "{$imported} baris formula BOM berhasil diimpor.";
        if ($duplicatesCount > 0) {
            $msg .= " ({$duplicatesCount} baris duplikat dilewati).";
        }
        if ($errors) {
            $msg .= ' ' . count($errors) . ' baris catatan/dilewati.';
        }

        \App\Models\MasterDataAudit::create([
            'auditable_type' => Bom::class,
            'auditable_id' => null,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name ?? 'Sistem',
            'item_name' => "Batch Impor Resep BOM ({$imported} formula)",
            'event' => 'imported',
            'old_values' => null,
            'new_values' => [
                'imported' => $imported,
                'duplicates_count' => $duplicatesCount,
                'errors_count' => count($errors),
            ],
            'changed_fields' => ['boms'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('bom.index')->with($errors && $imported === 0 ? 'error' : 'success', $msg);
    }
}
