<?php

namespace App\Http\Controllers;

use App\Models\BarangDatang;
use App\Models\BarangDatangItem;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class PurchasingController extends Controller
{
    public function __construct(private readonly StokService $stok)
    {
    }

    public function index()
    {
        $this->authorize('purchasing.view');

        return view('purchasing.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('purchasing.view');

        $query = PurchaseOrder::query()->select('purchase_orders.*')
            ->with(['supplier:id,nama'])
            ->withSum('items as total_nilai', 'harga_total')
            ->withSum('payments as total_dibayar', 'nominal');

        return DataTables::eloquent($query)
            ->addColumn('supplier_nama', fn ($po) => $po->supplier?->nama)
            ->editColumn('tanggal', fn ($po) => $po->tanggal?->format('d/m/Y'))
            ->editColumn('status', fn ($po) => ucwords(str_replace('_', ' ', $po->status)))
            ->addColumn('total_nilai', fn ($po) => number_format((float) $po->total_nilai, 0, ',', '.'))
            ->addColumn('sisa', fn ($po) => number_format((float) $po->total_nilai - (float) $po->total_dibayar, 0, ',', '.'))
            ->addColumn('action', fn ($po) => view('purchasing._actions', ['po' => $po])->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('purchasing.create');
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $produk = Produk::bahan()->active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);

        return view('purchasing.create', compact('suppliers', 'produk'));
    }

    public function store(Request $request)
    {
        $this->authorize('purchasing.create');

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:supplier,id'],
            'tanggal' => ['required', 'date'],
            'eta' => ['nullable', 'date'],
            'sumber_dana' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.harga_total' => ['required', 'numeric', 'min:0'],
        ]);

        $po = DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'no_po' => $this->nextNoPo(),
                'supplier_id' => $data['supplier_id'],
                'tanggal' => $data['tanggal'],
                'eta' => $data['eta'] ?? null,
                'sumber_dana' => $data['sumber_dana'] ?? null,
                'status' => 'draft',
                'dari_analisa' => false,
                'created_by' => auth()->id(),
            ]);
            foreach ($data['items'] as $row) {
                $po->items()->create([
                    'produk_id' => $row['produk_id'],
                    'qty' => $row['qty'],
                    'harga_total' => $row['harga_total'],
                ]);
            }

            return $po;
        });

        return redirect()->route('purchasing.show', $po)->with('success', "PO {$po->no_po} dibuat (draft).");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.view');
        $purchaseOrder->load(['supplier', 'items.produk:id,sku,nama,satuan', 'items.bardatItems', 'payments', 'barangDatang.items', 'creator:id,name']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);

        return view('purchasing.show', compact('purchaseOrder', 'gudang'));
    }

    public function submit(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.submit');
        $this->ensureStatus($purchaseOrder, ['draft']);
        $purchaseOrder->update(['status' => 'diajukan']);

        return back()->with('success', 'PO diajukan ke Manager.');
    }

    public function approve(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.approve');
        $this->ensureStatus($purchaseOrder, ['diajukan']);
        $purchaseOrder->update(['status' => 'dikirim_ke_gudang']);

        return back()->with('success', 'PO disetujui & dikirim ke Gudang.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.receive');
        $this->ensureStatus($purchaseOrder, ['dikirim_ke_gudang']);

        $data = $request->validate([
            'gudang_id' => ['required', 'exists:gudang,id'],
            'tanggal_terima' => ['required', 'date'],
            'kondisi' => ['required', Rule::in(['baik', 'rusak_sebagian'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.po_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.qty_diterima' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $purchaseOrder) {
            $bardat = BarangDatang::create([
                'po_id' => $purchaseOrder->id,
                'tanggal_terima' => $data['tanggal_terima'],
                'kondisi' => $data['kondisi'],
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                $qty = (float) $row['qty_diterima'];
                if ($qty <= 0) {
                    continue;
                }
                $item = $purchaseOrder->items()->findOrFail($row['po_item_id']);
                BarangDatangItem::create([
                    'bardat_id' => $bardat->id,
                    'po_item_id' => $item->id,
                    'qty_diterima' => $qty,
                ]);
                $this->stok->masuk(
                    $item->produk_id,
                    $data['gudang_id'],
                    $qty,
                    'purchase_order',
                    $purchaseOrder->id,
                    "Barang Datang {$purchaseOrder->no_po}",
                    \Illuminate\Support\Carbon::parse($data['tanggal_terima']),
                );
            }

            $purchaseOrder->update(['status' => 'selesai']);
        });

        return back()->with('success', 'Barang datang dicatat & stok bertambah.');
    }

    public function pay(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.pay');

        $data = $request->validate([
            'skema' => ['required', Rule::in(['tempo', 'termin', 'pelunasan'])],
            'tanggal_bayar' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'gt:0'],
        ]);

        $purchaseOrder->payments()->create($data);

        return back()->with('success', 'Pembayaran dicatat.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('purchasing.cancel');
        $this->ensureStatus($purchaseOrder, ['draft', 'diajukan', 'disetujui', 'dikirim_ke_gudang']);
        $purchaseOrder->update(['status' => 'dibatalkan']);

        return back()->with('success', 'PO dibatalkan.');
    }

    private function ensureStatus(PurchaseOrder $po, array $allowed): void
    {
        abort_unless(in_array($po->status, $allowed, true), 422, 'Transisi status PO tidak valid.');
    }

    private function nextNoPo(): string
    {
        $year = now()->year;
        $count = PurchaseOrder::whereYear('tanggal', $year)->count() + 1;

        return sprintf('PO-%d-%04d', $year, $count);
    }
}
