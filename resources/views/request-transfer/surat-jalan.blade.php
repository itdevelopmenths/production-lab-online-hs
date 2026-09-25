<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan - {{ $requestTransfer->no_transaksi }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        @media print {
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .print-page {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            @page {
                size: A4 portrait;
                margin: 12mm 15mm 12mm 15mm;
            }
        }
    </style>
</head>
<body class="min-h-screen py-6 px-4 print:p-0">

    <!-- Action Toolbar (Hidden during Print) -->
    <div class="max-w-3xl mx-auto mb-6 flex items-center justify-between no-print">
        <a href="{{ route('rt.show', $requestTransfer) }}" class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 hover:text-gray-900 bg-white border border-gray-300 rounded-sm px-3 py-1.5 shadow-2xs hover:bg-gray-50 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Detail Dokumen
        </a>

        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-2 text-xs font-bold text-white bg-primary-600 hover:bg-primary-700 rounded-sm px-4 py-1.5 shadow-xs transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Surat Jalan
            </button>
        </div>
    </div>

    <!-- Container Utama Surat Jalan -->
    <div class="max-w-3xl mx-auto bg-white rounded-sm border border-gray-300 p-8 shadow-sm print-page">

        <!-- Header: Judul & Nomor Dokumen -->
        <div class="flex items-start justify-between border-b-2 border-gray-900 pb-4 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-950 uppercase">
                    SURAT JALAN
                </h1>
                <p class="text-xs text-gray-500 font-medium mt-0.5">
                    Dokumen Pengiriman Barang
                </p>
            </div>

            <div class="text-right">
                <div class="font-mono text-base font-extrabold text-gray-950 tracking-tight">
                    {{ $requestTransfer->no_transaksi }}
                </div>
                <div class="text-xs text-gray-600 mt-1">
                    <span class="text-gray-500">Tanggal:</span>
                    <span class="font-medium text-gray-900">{{ $requestTransfer->created_at->format('d/m/Y') }}</span>
                </div>
                <div class="text-xs text-gray-600">
                    <span class="text-gray-500">Status:</span>
                    <span class="font-bold text-gray-900 uppercase">{{ ucfirst(str_replace('_', ' ', $requestTransfer->status)) }}</span>
                </div>
            </div>
        </div>

        <!-- Box Pengirim (Dari) & Penerima (Ke) -->
        <div class="flex items-center justify-between gap-4 mb-6">
            <!-- Box Dari (Pengirim) -->
            <div class="flex-1 bg-gray-50/70 border border-gray-300 rounded-sm p-3.5">
                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                    DARI (PENGIRIM)
                </div>
                <div class="text-sm font-bold text-gray-900">
                    {{ $requestTransfer->gudangAsal?->nama ?? 'Gudang Pusat' }}
                </div>
                <div class="text-xs text-gray-500 capitalize">
                    {{ $requestTransfer->gudangAsal?->tipe ?? 'Gudang' }}
                </div>
            </div>

            <!-- Arrow Indicator -->
            <div class="text-gray-400 font-bold text-lg px-2 shrink-0">
                &rarr;
            </div>

            <!-- Box Ke (Penerima) -->
            <div class="flex-1 bg-gray-50/70 border border-gray-300 rounded-sm p-3.5">
                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                    KE (PENERIMA)
                </div>
                <div class="text-sm font-bold text-gray-900">
                    {{ $requestTransfer->gudangTujuan?->nama ?? 'Gudang Tujuan' }}
                </div>
                <div class="text-xs text-gray-500 capitalize">
                    {{ $requestTransfer->gudangTujuan?->tipe ?? 'Toko / Fulfillment' }}
                </div>
            </div>
        </div>

        <!-- Tabel Barang -->
        <div class="mb-6 overflow-hidden border border-gray-300 rounded-sm">
            <table class="w-full text-xs">
                <thead class="bg-gray-100/90 border-b border-gray-300 text-gray-700 font-bold uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="py-2.5 px-3 text-center w-10 border-r border-gray-200">NO</th>
                        <th class="py-2.5 px-3 text-left w-24 border-r border-gray-200">KODE</th>
                        <th class="py-2.5 px-3 text-left border-r border-gray-200">NAMA BARANG</th>
                        <th class="py-2.5 px-3 text-center w-16 border-r border-gray-200">SATUAN</th>
                        <th class="py-2.5 px-3 text-right w-24 border-r border-gray-200">QTY DIMINTA</th>
                        <th class="py-2.5 px-3 text-right w-24 border-r border-gray-200">QTY DIKIRIM</th>
                        <th class="py-2.5 px-3 text-left w-28">KETERANGAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-gray-800">
                    @forelse($requestTransfer->items as $idx => $item)
                    @php
                        $qtyDiminta = (float) $item->qty_diminta;
                        $qtyDikirim = $item->qty_dikirim !== null ? (float) $item->qty_dikirim : (float) $item->qty_diminta;
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="py-2 px-3 text-center border-r border-gray-200 font-medium text-gray-500">
                            {{ $idx + 1 }}
                        </td>
                        <td class="py-2 px-3 font-mono text-gray-900 border-r border-gray-200 uppercase font-semibold">
                            {{ $item->produk?->sku ?? '—' }}
                        </td>
                        <td class="py-2 px-3 font-bold text-gray-900 border-r border-gray-200">
                            {{ $item->produk?->nama ?? '—' }}
                        </td>
                        <td class="py-2 px-3 text-center border-r border-gray-200 font-mono text-gray-600">
                            {{ $item->produk?->satuan ?? 'unit' }}
                        </td>
                        <td class="py-2 px-3 text-right font-mono border-r border-gray-200 text-gray-700">
                            {{ number_format($qtyDiminta, (fmod($qtyDiminta, 1) !== 0.0) ? 2 : 0, ',', '.') }}
                        </td>
                        <td class="py-2 px-3 text-right font-mono border-r border-gray-200 font-bold text-gray-950">
                            {{ number_format($qtyDikirim, (fmod($qtyDikirim, 1) !== 0.0) ? 2 : 0, ',', '.') }}
                        </td>
                        <td class="py-2 px-3 text-gray-500 text-[11px]">
                            {{ $item->keterangan_rusak ?: '' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-4 text-center text-gray-400">
                            Tidak ada barang dalam dokumen ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Ringkasan Logistik & Catatan -->
        <div class="mb-6 space-y-4">
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs">
                <div class="flex items-center justify-between py-0.5 border-b border-gray-100">
                    <span class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">TOTAL ITEM</span>
                    <span class="font-bold text-gray-900">{{ count($requestTransfer->items) }} jenis</span>
                </div>
                <div class="flex items-center justify-between py-0.5 border-b border-gray-100">
                    <span class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">DIBUAT OLEH</span>
                    <span class="font-medium text-gray-900">{{ $requestTransfer->creator?->name ?? 'Logistik' }}</span>
                </div>
                <div class="flex items-center justify-between py-0.5 border-b border-gray-100">
                    <span class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">DIKIRIM OLEH</span>
                    <span class="font-medium text-gray-900">{{ $requestTransfer->creator?->name ?? 'Logistik' }}</span>
                </div>
                <div class="flex items-center justify-between py-0.5 border-b border-gray-100">
                    <span class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">TGL KIRIM</span>
                    <span class="font-mono text-gray-900">{{ $requestTransfer->updated_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>

            <!-- Box Catatan -->
            <div class="bg-gray-50/60 border border-gray-300 rounded-sm p-3">
                <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                    CATATAN
                </div>
                <p class="text-xs text-gray-800 uppercase font-semibold leading-relaxed">
                    {{ $requestTransfer->catatan ?: '—' }}
                </p>
            </div>
        </div>

        <!-- Tanda Tangan 3 Kolom -->
        <div class="grid grid-cols-3 gap-6 pt-6 pb-2 text-center text-xs">
            <!-- Kolom 1: Pengirim -->
            <div class="flex flex-col justify-between h-28">
                <div class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">
                    PENGIRIM
                </div>
                <div class="mt-auto">
                    <div class="border-b border-gray-900 w-36 mx-auto mb-1.5"></div>
                    <div class="font-medium text-gray-800">{{ $requestTransfer->creator?->name ?? 'Logistik' }}</div>
                </div>
            </div>

            <!-- Kolom 2: Pengemudi / Kurir -->
            <div class="flex flex-col justify-between h-28">
                <div class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">
                    PENGEMUDI / KURIR
                </div>
                <div class="mt-auto">
                    <div class="border-b border-gray-900 w-36 mx-auto mb-1.5"></div>
                    <div class="text-gray-500 font-mono">( &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; )</div>
                </div>
            </div>

            <!-- Kolom 3: Penerima -->
            <div class="flex flex-col justify-between h-28">
                <div class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">
                    PENERIMA
                </div>
                <div class="mt-auto">
                    <div class="border-b border-gray-900 w-36 mx-auto mb-1.5"></div>
                    <div class="font-medium text-gray-800">{{ $requestTransfer->gudangTujuan?->nama ?? 'Penerima' }}</div>
                </div>
            </div>
        </div>

        <!-- Footer Watermark & Timestamp -->
        <div class="mt-8 pt-4 border-t border-gray-200 text-center text-[10px] text-gray-400">
            Dicetak pada {{ now()->format('d/m/Y H:i') }} WIB &mdash; Harumnya POS &bull; Production Lab Online
        </div>
    </div>

</body>
</html>
