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
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-[12px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border shadow-theme-xs">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-[10px] bg-[#FBFBFB] dark:bg-gray-800 border border-[#E8E8E8] dark:border-border text-text-active flex items-center justify-center shrink-0">
                    <svg class="size-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-xl font-semibold tabular-nums text-[#101828] dark:text-white">{{ $stockRequest->no_request }}</h2>
                        <x-badge :variant="$stockRequest->status" :pill="true">
                            {{ ucfirst($stockRequest->status) }}
                        </x-badge>
                    </div>
                    <p class="text-xs text-text-muted mt-1">
                        Dibuat oleh <span class="font-medium text-text-active">{{ $stockRequest->creator?->name ?? 'Sistem' }}</span> pada {{ $stockRequest->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($canSubmit)
                    <form method="POST" action="{{ route('requests.submit', $stockRequest) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="primary" size="sm">
                            Ajukan ke Manajer
                        </x-button>
                    </form>
                @endif

                @if($canApprove)
                    <x-button type="button" @click="openApproveModal = true" variant="success" size="sm">
                        Setujui Permintaan
                    </x-button>
                    <x-button type="button" @click="openRejectModal = true" variant="danger" size="sm">
                        Tolak
                    </x-button>
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
                    <button type="button" @click="openCancelModal = true" class="px-3.5 py-2 text-xs font-medium border border-destructive/30 text-destructive hover:bg-destructive/10 transition rounded-[6px]">
                        Batalkan
                    </button>
                @endif
            </div>
        </div>

        {{-- Grid Info --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Jenis Permintaan</div>
                <div class="text-sm font-medium text-foreground mt-1">
                    {{ $stockRequest->jenis === 'request_bahan' ? 'Bahan Baku Operasional' : 'Produk Jadi Fulfillment' }}
                </div>
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Gudang Pemohon</div>
                <div class="text-sm font-medium text-foreground mt-1">
                    {{ $stockRequest->gudangPemohon?->nama ?? '-' }}
                </div>
                <div class="text-[10px] text-muted-foreground">{{ $stockRequest->gudangPemohon?->kode }}</div>
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Gudang Penyedia</div>
                <div class="text-sm font-medium text-foreground mt-1">
                    {{ $stockRequest->gudangTujuan?->nama ?? '-' }}
                </div>
                <div class="text-[10px] text-muted-foreground">{{ $stockRequest->gudangTujuan?->kode }}</div>
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Batch Produksi</div>
                <div class="text-sm font-medium text-foreground mt-1 tabular-nums">
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
            <span class="font-medium text-foreground">Catatan Pemohon:</span>
            <p class="text-muted-foreground mt-0.5">{{ $stockRequest->catatan }}</p>
        </div>
        @endif

        @if($stockRequest->alasan_tolak)
        <div class="p-4 rounded-xl bg-rose-50 border-rose-200 text-rose-900 border text-xs">
            <span class="font-medium">Alasan Penolakan Manager:</span>
            <p class="mt-0.5">{{ $stockRequest->alasan_tolak }}</p>
            <div class="text-[10px] opacity-80 mt-1">Oleh: {{ $stockRequest->approver?->name ?? 'Manager' }} pada {{ $stockRequest->disetujui_pada?->format('d/m/Y H:i') }}</div>
        </div>
        @endif

        @if($stockRequest->alasan_batal)
        <div class="p-4 rounded-xl bg-card border-border text-foreground border text-xs">
            <span class="font-medium">Alasan Pembatalan:</span>
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
                            <td class="px-4 py-3 tabular-nums font-medium text-foreground">{{ $it->produk?->sku ?? '-' }}</td>
                            <td class="px-4 py-3 font-medium text-foreground">{{ $it->produk?->nama ?? '-' }}</td>
                            <td class="px-4 py-3 font-medium text-foreground">{{ number_format($it->qty_diminta, 2) }}</td>
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
                            <td class="px-4 py-3 tabular-nums font-medium text-primary">
                                <a href="{{ route('transfers.show', $tr) }}" class="hover:underline">{{ $tr->no_transfer }}</a>
                            </td>
                            <td class="px-4 py-3 text-foreground">{{ $tr->gudangAsal?->nama }}</td>
                            <td class="px-4 py-3 text-foreground">{{ $tr->gudangTujuan?->nama }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $tr->status === 'selesai' ? 'bg-card text-foreground' : 'bg-card text-foreground' }}">
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
        <x-modal name="openApproveModal" title="Setujui Permintaan Barang" maxWidth="md" :noPadding="true">
            <form method="POST" action="{{ route('requests.approve', $stockRequest) }}">
                @csrf
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5 space-y-3">
                    <x-form-field label="Catatan Approval" name="catatan" align="top">
                        <textarea name="catatan" x-model="catatanApprove" rows="3" class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0" placeholder="Catatan persetujuan (opsional)..."></textarea>
                    </x-form-field>
                </div>
                <div class="bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border px-4 py-3 flex justify-end gap-2">
                    <x-button type="button" variant="secondary" size="sm" @click="openApproveModal = false">Batal</x-button>
                    <x-button type="submit" variant="success" size="sm">Konfirmasi Setujui</x-button>
                </div>
            </form>
        </x-modal>

        <x-modal name="openRejectModal" title="Tolak Permintaan Barang" maxWidth="md" :noPadding="true">
            <form method="POST" action="{{ route('requests.reject', $stockRequest) }}">
                @csrf
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5 space-y-3">
                    <x-form-field label="Alasan Penolakan" name="alasan" :required="true" align="top">
                        <textarea name="alasan" x-model="alasanReject" required rows="3" class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0" placeholder="Jelaskan alasan penolakan..."></textarea>
                    </x-form-field>
                </div>
                <div class="bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border px-4 py-3 flex justify-end gap-2">
                    <x-button type="button" variant="secondary" size="sm" @click="openRejectModal = false">Batal</x-button>
                    <x-button type="submit" variant="danger" size="sm">Konfirmasi Tolak</x-button>
                </div>
            </form>
        </x-modal>

        <x-modal name="openCancelModal" title="Batalkan Permintaan Barang" maxWidth="md" :noPadding="true">
            <form method="POST" action="{{ route('requests.cancel', $stockRequest) }}">
                @csrf
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5 space-y-3">
                    <x-form-field label="Alasan (Opsional)" name="alasan" align="top">
                        <textarea name="alasan" x-model="alasanCancel" rows="3" class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0" placeholder="Alasan pembatalan..."></textarea>
                    </x-form-field>
                </div>
                <div class="bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border px-4 py-3 flex justify-end gap-2">
                    <x-button type="button" variant="secondary" size="sm" @click="openCancelModal = false">Kembali</x-button>
                    <x-button type="submit" variant="danger" size="sm">Batalkan Request</x-button>
                </div>
            </form>
        </x-modal>

        {{-- Operational Audit History --}}
        <div class="mt-6">
            <x-operational-audit-history :auditable="$stockRequest" title="Jejak Riwayat Audit Permintaan {{ $stockRequest->no_request }}" />
        </div>
    </div>
</x-app-layout>

