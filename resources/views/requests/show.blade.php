<x-app-layout title="Detail Permintaan {{ $stockRequest->no_request }}">
    <div x-data="{
        openApproveModal: false,
        openRejectModal: false,
        openCancelModal: false,
        catatanApprove: '',
        alasanReject: '',
        alasanCancel: ''
    }" class="space-y-6">

        <x-page-header
            title="Detail Permintaan Barang"
            :subtitle="$stockRequest->no_request . ' · ' . $stockRequest->created_at->format('d/m/Y H:i')"
            :breadcrumbs="['Permintaan' => route('requests.index'), $stockRequest->no_request => null]"
            :backUrl="route('requests.index')"
        />

        {{-- Header Status Bar --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-card border border-border">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <svg class="size-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-bold font-mono text-foreground">{{ $stockRequest->no_request }}</h2>
                        @php
                            $statusBadge = match($stockRequest->status) {
                                'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                                'diajukan' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                'disetujui' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                'diproses' => 'bg-blue-100 text-blue-800 border-blue-300',
                                'selesai' => 'bg-green-100 text-green-800 border-green-300',
                                'ditolak' => 'bg-rose-100 text-rose-800 border-rose-300',
                                'dibatalkan' => 'bg-red-100 text-red-700 border-red-300',
                                default => 'bg-gray-100 text-gray-700 border-gray-300',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadge }}">
                            {{ ucfirst($stockRequest->status) }}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Dibuat oleh <span class="font-medium text-foreground">{{ $stockRequest->creator?->name ?? 'Sistem' }}</span> pada {{ $stockRequest->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($canSubmit)
                    <form method="POST" action="{{ route('requests.submit', $stockRequest) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="primary">
                            Ajukan ke Manajer
                        </x-button>
                    </form>
                @endif

                @if($canApprove)
                    <button type="button" @click="openApproveModal = true" class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition">
                        Setujui Permintaan
                    </button>
                    <button type="button" @click="openRejectModal = true" class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition">
                        Tolak
                    </button>
                @endif

                @if($canCreateTransfer)
                    <x-button href="{{ route('transfers.create', ['request_id' => $stockRequest->id]) }}" variant="primary">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        </x-slot:icon>
                        Buat Transfer / Kirim Stok
                    </x-button>
                @endif

                @if($canEdit && $stockRequest->status === 'disetujui')
                    <x-button href="{{ route('requests.edit', $stockRequest) }}" variant="outline" title="Revisi request disetujui (FR-REQ-08)">
                        Revisi Permintaan
                    </x-button>
                @endif

                @if($canCancel)
                    <button type="button" @click="openCancelModal = true" class="px-3.5 py-2 text-xs font-semibold rounded-lg border border-destructive/30 text-destructive hover:bg-destructive/10 transition">
                        Batalkan
                    </button>
                @endif
            </div>
        </div>

        {{-- Grid Info --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Jenis Permintaan</div>
                <div class="text-sm font-semibold text-foreground mt-1">
                    {{ $stockRequest->jenis === 'request_bahan' ? 'Bahan Baku Operasional' : 'Produk Jadi Fulfillment' }}
                </div>
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Gudang Pemohon</div>
                <div class="text-sm font-semibold text-foreground mt-1">
                    {{ $stockRequest->gudangPemohon?->nama ?? '-' }}
                </div>
                <div class="text-[10px] text-muted-foreground">{{ $stockRequest->gudangPemohon?->kode }}</div>
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Gudang Penyedia</div>
                <div class="text-sm font-semibold text-foreground mt-1">
                    {{ $stockRequest->gudangTujuan?->nama ?? '-' }}
                </div>
                <div class="text-[10px] text-muted-foreground">{{ $stockRequest->gudangTujuan?->kode }}</div>
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Batch Produksi</div>
                <div class="text-sm font-semibold text-foreground mt-1 font-mono">
                    @if($stockRequest->batch)
                        <a href="{{ route('batches.show', $stockRequest->batch) }}" class="text-primary hover:underline">
                            {{ $stockRequest->batch->no_batch }}
                        </a>
                    @else
                        -
                    @endif
                </div>
            </x-card>
        </div>

        @if($stockRequest->catatan)
        <div class="p-4 rounded-xl bg-muted/40 border border-border text-xs">
            <span class="font-semibold text-foreground">Catatan Pemohon:</span>
            <p class="text-muted-foreground mt-0.5">{{ $stockRequest->catatan }}</p>
        </div>
        @endif

        @if($stockRequest->alasan_tolak)
        <div class="p-4 rounded-xl bg-rose-50 border-rose-200 text-rose-900 border text-xs">
            <span class="font-bold">Alasan Penolakan Manager:</span>
            <p class="mt-0.5">{{ $stockRequest->alasan_tolak }}</p>
            <div class="text-[10px] opacity-80 mt-1">Oleh: {{ $stockRequest->approver?->name ?? 'Manager' }} pada {{ $stockRequest->disetujui_pada?->format('d/m/Y H:i') }}</div>
        </div>
        @endif

        @if($stockRequest->alasan_batal)
        <div class="p-4 rounded-xl bg-amber-50 border-amber-200 text-amber-900 border text-xs">
            <span class="font-bold">Alasan Pembatalan:</span>
            <p class="mt-0.5">{{ $stockRequest->alasan_batal }}</p>
        </div>
        @endif

        {{-- Table Items --}}
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="text-lg font-semibold text-foreground tracking-tight">Rincian Barang yang Diminta</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Daftar item produk dan volume kebutuhan</p>
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground font-medium">
                            <th class="px-4 py-3 text-left w-12">No</th>
                            <th class="px-4 py-3 text-left">SKU</th>
                            <th class="px-4 py-3 text-left">Nama Produk</th>
                            <th class="px-4 py-3 text-left w-32">Kuantitas</th>
                            <th class="px-4 py-3 text-left w-24">Satuan</th>
                            <th class="px-4 py-3 text-left">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($stockRequest->items as $idx => $it)
                        <tr class="hover:bg-muted/30">
                            <td class="px-4 py-3 text-center text-muted-foreground">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-mono font-medium text-foreground">{{ $it->produk?->sku ?? '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-foreground">{{ $it->produk?->nama ?? '-' }}</td>
                            <td class="px-4 py-3 font-bold text-foreground">{{ number_format($it->qty_diminta, 2) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ $it->produk?->satuan ?? '-' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ $it->keterangan ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Associated Transfers --}}
        @if($stockRequest->transfers->isNotEmpty())
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="text-lg font-semibold text-foreground tracking-tight">Dokumen Transfer Terkait</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Pengiriman dan mutasi fisik barang untuk memenuhi permintaan ini</p>
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground font-medium">
                            <th class="px-4 py-3 text-left">No Transfer</th>
                            <th class="px-4 py-3 text-left">Gudang Asal</th>
                            <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($stockRequest->transfers as $tr)
                        <tr class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono font-semibold text-primary">
                                <a href="{{ route('transfers.show', $tr) }}" class="hover:underline">{{ $tr->no_transfer }}</a>
                            </td>
                            <td class="px-4 py-3 text-foreground">{{ $tr->gudangAsal?->nama }}</td>
                            <td class="px-4 py-3 text-foreground">{{ $tr->gudangTujuan?->nama }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $tr->status === 'selesai' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ ucfirst($tr->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-button href="{{ route('transfers.show', $tr) }}" variant="outline" size="sm">
                                    Buka Transfer
                                </x-button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
        @endif

        {{-- Modals --}}
        <x-modal name="openApproveModal" maxWidth="md">
            <x-slot:header>
                <div class="px-6 py-4 border-b border-border bg-emerald-50 flex items-center justify-between">
                    <h3 class="text-base font-bold text-emerald-950">Setujui Permintaan Barang</h3>
                </div>
            </x-slot:header>
            <form method="POST" action="{{ route('requests.approve', $stockRequest) }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-foreground mb-1">Catatan Approval (Opsional)</label>
                    <textarea name="catatan" x-model="catatanApprove" rows="3" class="w-full text-xs rounded-lg border border-border p-2 focus:ring-2 focus:ring-primary/20" placeholder="Catatan persetujuan..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="outline" @click="openApproveModal = false">Batal</x-button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition">
                        Konfirmasi Setujui
                    </button>
                </div>
            </form>
        </x-modal>

        <x-modal name="openRejectModal" maxWidth="md">
            <x-slot:header>
                <div class="px-6 py-4 border-b border-border bg-rose-50 flex items-center justify-between">
                    <h3 class="text-base font-bold text-rose-950">Tolak Permintaan Barang</h3>
                </div>
            </x-slot:header>
            <form method="POST" action="{{ route('requests.reject', $stockRequest) }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-foreground mb-1">Alasan Penolakan <span class="text-destructive">*</span></label>
                    <textarea name="alasan" x-model="alasanReject" required rows="3" class="w-full text-xs rounded-lg border border-border p-2 focus:ring-2 focus:ring-primary/20" placeholder="Jelaskan alasan penolakan..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="outline" @click="openRejectModal = false">Batal</x-button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition">
                        Konfirmasi Tolak
                    </button>
                </div>
            </form>
        </x-modal>

        <x-modal name="openCancelModal" maxWidth="md">
            <x-slot:header>
                <div class="px-6 py-4 border-b border-border bg-amber-50 flex items-center justify-between">
                    <h3 class="text-base font-bold text-amber-950">Batalkan Permintaan Barang</h3>
                </div>
            </x-slot:header>
            <form method="POST" action="{{ route('requests.cancel', $stockRequest) }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-foreground mb-1">Alasan Pembatalan (Opsional)</label>
                    <textarea name="alasan" x-model="alasanCancel" rows="3" class="w-full text-xs rounded-lg border border-border p-2 focus:ring-2 focus:ring-primary/20" placeholder="Alasan pembatalan..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="outline" @click="openCancelModal = false">Kembali</x-button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition">
                        Batalkan Request
                    </button>
                </div>
            </form>
        </x-modal>

        {{-- Operational Audit History --}}
        <div class="mt-6">
            <x-operational-audit-history :auditable="$stockRequest" title="Jejak Riwayat Audit Permintaan {{ $stockRequest->no_request }}" />
        </div>
    </div>
</x-app-layout>

