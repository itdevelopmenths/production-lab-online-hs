<x-app-layout title="Purchasing">
    @php
        $user = auth()->user();
        $canSeePrice = $canSeePrice ?? $user->can('purchasing.price.view');
        $canApprove = $user->can('purchasing.approve');
        $canSeeApprovalTab = $canSeeApprovalTab ?? ($user->hasAnyRole(['manager', 'purchasing', 'admin_holding', 'super_admin']) || $canApprove);
        $pendingApprovalCount = $pendingApprovalCount ?? ($canSeeApprovalTab
            ? \App\Models\PurchaseOrder::whereNotIn('status', ['selesai', 'dibatalkan'])
                ->where(fn ($q) => $q->where('status', 'diajukan')->orWhere('stage_aktif', \App\Models\PoStage::STAGE_2))
                ->count()
            : 0);
        $canInvalidateLt = $user->can('purchasing.lead-time.invalidate') || $user->hasRole('manager');

        // Setup default active tab
        $defaultTab = request('tab', 'po');
        if ($defaultTab === 'lead-time') {
            $defaultTab = 'lead_time';
        }
        if (! in_array($defaultTab, ['po', 'ap', 'approval', 'lead_time'], true)) {
            $defaultTab = 'po';
        }
        if (! $canSeeApprovalTab && $defaultTab === 'approval') {
            $defaultTab = 'po';
        }
        if (! $canSeePrice && $defaultTab === 'ap') {
            $defaultTab = 'po';
        }
    @endphp

    <x-page-header
        title="Purchase Order"
        :breadcrumbs="['Purchasing' => null]"
    >
        <x-slot:actions>
            @can('purchasing.create')
            <x-button href="{{ route('purchasing.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Buat PO Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="tbl-fit" x-data="{ activeTab: '{{ $defaultTab }}', leadTimeLoaded: {{ $defaultTab === 'lead_time' ? 'true' : 'false' }} }"
         x-init="{{ $defaultTab === 'lead_time' ? '$nextTick(() => { loadLeadTimeData(); })' : '' }}">
        {{-- Blueprint Segmented StatusTabs --}}
        <div class="bg-[#f3f4f6] dark:bg-gray-900 dark:border dark:border-gray-800 p-[2px] gap-[2px] rounded-[8px] h-9 sm:h-10 inline-flex items-center w-fit max-w-full overflow-x-auto no-scrollbar mb-4" role="tablist">
            {{-- TAB 1: Daftar Purchase Order --}}
            <button
                type="button"
                role="tab"
                @click="activeTab = 'po'; $nextTick(() => { if (dtTable) dtTable.columns.adjust(); })"
                :class="activeTab === 'po' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.05)] font-medium' : 'bg-transparent text-[#6a7282] dark:text-gray-400 border border-transparent hover:text-[#101828] dark:hover:text-white hover:bg-black/[0.03] dark:hover:bg-white/[0.03] font-medium'"
                class="px-[10px] sm:px-3 py-[4px] text-[13px] sm:text-[14px] leading-[20px] tracking-[-0.154px] gap-[6px] h-full inline-flex items-center whitespace-nowrap transition-all cursor-pointer select-none rounded-[6px]"
            >
                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Daftar Purchase Order
            </button>

            {{-- TAB 2: Buku Tagihan & Pembayaran (AP) --}}
            @if($canSeePrice)
            <button
                type="button"
                role="tab"
                @click="activeTab = 'ap'; $nextTick(() => { if (dtApTable) dtApTable.columns.adjust(); })"
                :class="activeTab === 'ap' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.05)] font-medium' : 'bg-transparent text-[#6a7282] dark:text-gray-400 border border-transparent hover:text-[#101828] dark:hover:text-white hover:bg-black/[0.03] dark:hover:bg-white/[0.03] font-medium'"
                class="px-[10px] sm:px-3 py-[4px] text-[13px] sm:text-[14px] leading-[20px] tracking-[-0.154px] gap-[6px] h-full inline-flex items-center whitespace-nowrap transition-all cursor-pointer select-none rounded-[6px]"
            >
                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Buku Tagihan (AP)
            </button>
            @endif

            {{-- TAB 3: Antrean Approval --}}
            @if($canSeeApprovalTab)
            <button
                type="button"
                role="tab"
                @click="activeTab = 'approval'; $nextTick(() => { if (dtApprovalTable) dtApprovalTable.columns.adjust(); })"
                :class="activeTab === 'approval' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.05)] font-medium' : 'bg-transparent text-[#6a7282] dark:text-gray-400 border border-transparent hover:text-[#101828] dark:hover:text-white hover:bg-black/[0.03] dark:hover:bg-white/[0.03] font-medium'"
                class="px-[10px] sm:px-3 py-[4px] text-[13px] sm:text-[14px] leading-[20px] tracking-[-0.154px] gap-[6px] h-full inline-flex items-center whitespace-nowrap transition-all cursor-pointer select-none rounded-[6px]"
            >
                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Antrean Approval
                @if($pendingApprovalCount > 0)
                    <span class="bg-[#f3f4f6] dark:bg-gray-700 text-[#364153] dark:text-gray-200 px-[7px] py-px rounded-full text-[11px] font-medium tabular-nums">
                        {{ $pendingApprovalCount }}
                    </span>
                @endif
            </button>
            @endif

            {{-- TAB 4: Lead Time Supplier --}}
            <button
                type="button"
                role="tab"
                @click="activeTab = 'lead_time'; if (!leadTimeLoaded) { leadTimeLoaded = true; loadLeadTimeData(); }"
                :class="activeTab === 'lead_time' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.05)] font-medium' : 'bg-transparent text-[#6a7282] dark:text-gray-400 border border-transparent hover:text-[#101828] dark:hover:text-white hover:bg-black/[0.03] dark:hover:bg-white/[0.03] font-medium'"
                class="px-[10px] sm:px-3 py-[4px] text-[13px] sm:text-[14px] leading-[20px] tracking-[-0.154px] gap-[6px] h-full inline-flex items-center whitespace-nowrap transition-all cursor-pointer select-none rounded-[6px]"
            >
                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Lead Time Supplier
            </button>
        </div>

        <!-- TAB 1: Daftar Purchase Order -->
        <div x-show="activeTab === 'po'" class="min-h-[480px]">
            <!-- Toolbar Filter PO -->
            <div class="flex flex-wrap items-center justify-between gap-2.5 bg-white dark:bg-gray-900 px-4 py-3 rounded-[12px] border border-[#e8e8e8] dark:border-border mb-3.5 shadow-theme-xs">
                <div class="flex flex-wrap items-center gap-2.5">
                    <div>
                        <label for="filter-status-po" class="block text-[11px] font-medium text-text-muted mb-1">Status PO</label>
                        <select id="filter-status-po" class="h-8 border border-[#e8e8e8] dark:border-border bg-white dark:bg-gray-900 text-xs py-1 px-2.5 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-40 max-w-full outline-none cursor-pointer rounded-[6px]">
                            <option value="">— Semua Status PO —</option>
                            <option value="draft">Draft</option>
                            <option value="diajukan">Diajukan (Menunggu Approval)</option>
                            <option value="disetujui">Disetujui (Gudang)</option>
                            <option value="dikirim_ke_gudang">Dikirim ke Gudang</option>
                            <option value="selesai">Selesai</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-kategori-supplier" class="block text-[11px] font-medium text-text-muted mb-1">Kategori Supplier</label>
                        <select id="filter-kategori-supplier" class="h-8 border border-[#e8e8e8] dark:border-border bg-white dark:bg-gray-900 text-xs py-1 px-2.5 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer rounded-[6px]">
                            <option value="">— Semua Kategori —</option>
                            <option value="lokal">Lokal</option>
                            <option value="impor">Impor</option>
                            <option value="distributor">Distributor</option>
                            <option value="pabrik">Pabrik</option>
                        </select>
                    </div>
                </div>
                <div>
                    <x-button type="button" id="btn-reset-po" variant="secondary" size="xs">
                        Reset Filter
                    </x-button>
                </div>
            </div>

            <x-card class="tbl-modern" title="Semua Dokumen Purchase Order" subtitle="Tracking status pengadaan 8 stage & SLA real-time" :noPadding="true">
                <x-slot:icon>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </x-slot:icon>
                <div class="overflow-x-auto">
                    <table id="tbl" class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left">No Invoice</th>
                                <th class="px-4 py-3 text-left">Supplier</th>
                                <th class="px-4 py-3 text-left">Lokasi</th>
                                <th class="px-4 py-3 text-left">Tgl PO</th>
                                <th class="px-4 py-3 text-left">ETA</th>
                                <th class="px-4 py-3 text-left">Tgl Tiba</th>
                                <th class="px-4 py-3 text-left">Status PO</th>
                                <th class="px-4 py-3 text-left">Total Tagihan</th>
                                <th class="px-4 py-3 text-left">SLA / Durasi</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </x-card>
        </div>

        <!-- TAB 2: Buku Tagihan & Pembayaran (AP) -->
        @if($canSeePrice)
        <div x-show="activeTab === 'ap'" x-cloak class="min-h-[480px]">
            <!-- Toolbar Filter AP -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-900 p-3.5 rounded-[12px] border border-[#e8e8e8] dark:border-border mb-3.5 shadow-theme-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <label for="filter-status-bayar-ap" class="block text-[11px] font-medium text-text-muted mb-1">Filter Status Bayar / AP</label>
                        <select id="filter-status-bayar-ap" class="h-8 border border-[#e8e8e8] dark:border-border bg-white dark:bg-gray-900 text-xs py-1 px-2.5 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-40 max-w-full outline-none cursor-pointer rounded-[6px]">
                            <option value="">— Semua Status Bayar —</option>
                            <option value="overdue">🔴 Overdue (Jatuh Tempo - Prioritas)</option>
                            <option value="belum_lunas">🟡 Belum Lunas</option>
                            <option value="parsial">🔵 Parsial (Sebagian)</option>
                            <option value="lunas">🟢 Lunas</option>
                            <option value="batal">⚫ Batal</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-skema-bayar-ap" class="block text-[11px] font-medium text-text-muted mb-1">Filter Skema Transaksi</label>
                        <select id="filter-skema-bayar-ap" class="h-8 border border-[#e8e8e8] dark:border-border bg-white dark:bg-gray-900 text-xs py-1 px-2.5 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer rounded-[6px]">
                            <option value="">— Semua Skema —</option>
                            <option value="cash">Tunai (Cash)</option>
                            <option value="tempo">Tempo</option>
                            <option value="termin">Termin</option>
                        </select>
                    </div>
                </div>
                <div>
                    <x-button type="button" id="btn-reset-ap" variant="secondary" size="xs">
                        Reset Filter
                    </x-button>
                </div>
            </div>

            <x-card class="tbl-modern" title="Buku Rekapitulasi Tagihan Vendor (Accounts Payable)" :noPadding="true">
                <x-slot:icon>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </x-slot:icon>
                <div class="overflow-x-auto">
                    <table id="tblAp" class="w-full text-xs">
                        <thead>
                            <tr>
                                <th class="px-3 py-2.5 text-left">No Invoice</th>
                                <th class="px-3 py-2.5 text-left">Supplier</th>
                                <th class="px-3 py-2.5 text-left">Tgl PO</th>
                                <th class="px-3 py-2.5 text-left">Skema</th>
                                <th class="px-3 py-2.5 text-right">Total Tagihan</th>
                                <th class="px-3 py-2.5 text-right">Telah Dibayar</th>
                                <th class="px-3 py-2.5 text-right">Sisa Tagihan</th>
                                <th class="px-3 py-2.5 text-left">Jatuh Tempo</th>
                                <th class="px-3 py-2.5 text-left">Status Bayar</th>
                                <th class="px-3 py-2.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </x-card>
        </div>
        @endif

        @if($canSeeApprovalTab)
        <!-- TAB 3: Antrean Approval -->
        <div x-show="activeTab === 'approval'" x-cloak class="min-h-[480px] space-y-4">
            <div class="p-3.5 bg-card dark:bg-card border border-border dark:border-border rounded-[12px] text-xs flex flex-wrap items-center justify-between gap-3 shadow-theme-xs">
                <div class="flex items-center gap-2 text-foreground dark:text-foreground">
                    <span class="inline-block w-2 h-2 rounded-full bg-gray-400 animate-pulse shrink-0"></span>
                    <span>Antrean ini menyaring dokumen PO yang menunggu persetujuan otorisasi Manager (Stage 2 / Diajukan) dan usulan Revisi Komitmen PO.</span>
                </div>
                <div class="shrink-0 tabular-nums text-xs font-medium text-foreground dark:text-foreground">
                    Total Menunggu: <span class="px-2 py-0.5 bg-card dark:bg-card rounded font-medium">{{ $pendingApprovalCount }}</span>
                </div>
            </div>

            {{-- Daftar Antrean Revisi Komitmen (PoRevisi Menunggu) --}}
            @if(isset($pendingRevisis) && $pendingRevisis->isNotEmpty())
            <x-card title="Antrean Usulan Revisi Komitmen (PoRevisi)" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-card dark:bg-card border-b border-border dark:border-border text-foreground dark:text-foreground text-xs">
                                <th class="px-3 py-2.5 text-left font-normal">No PO / Invoice</th>
                                <th class="px-3 py-2.5 text-left font-normal">Supplier</th>
                                <th class="px-3 py-2.5 text-left font-normal">Revisi Ke</th>
                                <th class="px-3 py-2.5 text-left font-normal">Diajukan Oleh</th>
                                <th class="px-3 py-2.5 text-left font-normal">Alasan Pengajuan</th>
                                <th class="px-3 py-2.5 text-center font-normal">Aksi Review</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e8e8e8] dark:divide-gray-800/60 bg-white dark:bg-gray-900">
                            @foreach($pendingRevisis as $rev)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition">
                                <td class="px-3 py-2.5 tabular-nums font-medium text-[#101828] dark:text-white">
                                    {{ $rev->purchaseOrder?->no_invoice ?: $rev->purchaseOrder?->no_po }}
                                    <span class="block text-[10px] text-text-muted font-normal">PO: {{ $rev->purchaseOrder?->no_po }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-[#101828] dark:text-gray-200">{{ $rev->purchaseOrder?->supplier?->nama ?? '—' }}</td>
                                <td class="px-3 py-2.5 tabular-nums">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-card dark:bg-card text-foreground dark:text-foreground border border-border dark:border-border">
                                        Rev #{{ $rev->revisi_ke }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-[#101828] dark:text-gray-200">{{ $rev->requester?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-text-muted">{{ $rev->alasan ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <x-button :href="route('purchasing.show', $rev->purchaseOrder)" variant="warning" size="xs">
                                        Review Usulan &rarr;
                                    </x-button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            @endif

            {{-- Antrean PO Menunggu Persetujuan --}}
            <x-card class="tbl-modern" title="Antrean Persetujuan Purchase Order" :noPadding="true">
                <x-slot:icon>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </x-slot:icon>
                <div class="overflow-x-auto">
                    <table id="tblApproval" class="w-full text-xs">
                        <thead>
                            <tr>
                                <th class="px-3 py-2.5 text-left">No Invoice</th>
                                <th class="px-3 py-2.5 text-left">Supplier</th>
                                <th class="px-3 py-2.5 text-left">Lokasi</th>
                                <th class="px-3 py-2.5 text-left">Tgl PO</th>
                                <th class="px-3 py-2.5 text-left">ETA</th>
                                <th class="px-3 py-2.5 text-left">Status PO</th>
                                <th class="px-3 py-2.5 text-center">Aksi Persetujuan</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </x-card>
        </div>
        @endif

        {{-- TAB 4: Lead Time Supplier --}}
        <div x-show="activeTab === 'lead_time'" x-cloak class="min-h-[480px] space-y-5">
            {{-- Header & Sub-toolbar --}}
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-900 p-4 rounded-[12px] border border-[#e8e8e8] dark:border-border shadow-theme-xs">
                <div>
                    <h3 class="text-sm font-semibold text-[#101828] dark:text-white">Analisis Lead Time & Performa Supplier (PRD v2.4)</h3>
                    <p class="text-xs text-text-muted mt-0.5">Ringkasan performa pemenuhan pesanan Level-1 (Supplier), Level-2 (Produk), Breakdown 8 Stage, dan Audit Log Transaksi</p>
                </div>
                <div>
                    <x-button type="button" onclick="loadLeadTimeData()" variant="secondary" size="sm">
                        <x-slot:icon>
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </x-slot:icon>
                        Segarkan Data
                    </x-button>
                </div>
            </div>

            {{-- 1. Level-1: Ringkasan Lead Time per Supplier --}}
            <x-card title="Level-1: Performa Lead Time per Supplier" subtitle="Rata-rata dan durasi maksimal lead time pengadaan per vendor" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" id="tblLtSupplier">
                        <thead class="bg-[#f8fafb] dark:bg-gray-800/95 border-y border-[#e8e8e8] dark:border-border text-text-muted">
                            <tr>
                                <th class="px-4 py-3 text-left font-normal">Supplier</th>
                                <th class="px-4 py-3 text-left font-normal">Kategori</th>
                                <th class="px-4 py-3 text-center font-normal">Jml Transaksi Valid</th>
                                <th class="px-4 py-3 text-right font-normal">Rata-rata Lead Time</th>
                                <th class="px-4 py-3 text-right font-normal">Lead Time Maksimal</th>
                                <th class="px-4 py-3 text-center font-normal">Breakdown Stage</th>
                            </tr>
                        </thead>
                        <tbody id="bodyLtSupplier" class="divide-y divide-[#e8e8e8] dark:divide-gray-800/60 bg-white dark:bg-gray-900">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-text-muted">Memuat ringkasan supplier...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- 2. Level-2: Ringkasan Lead Time per Supplier - Produk --}}
            <x-card title="Level-2: Performa Lead Time per Supplier & SKU Produk" subtitle="Matriks kecepatan pemenuhan per varian/komoditas bahan baku" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" id="tblLtProduk">
                        <thead class="bg-[#f8fafb] dark:bg-gray-800/95 border-y border-[#e8e8e8] dark:border-border text-text-muted">
                            <tr>
                                <th class="px-4 py-3 text-left font-normal">Supplier</th>
                                <th class="px-4 py-3 text-left font-normal">SKU</th>
                                <th class="px-4 py-3 text-left font-normal">Nama Produk / Bahan</th>
                                <th class="px-4 py-3 text-center font-normal">Satuan</th>
                                <th class="px-4 py-3 text-center font-normal">Jml Transaksi</th>
                                <th class="px-4 py-3 text-right font-normal">Rata-rata LT</th>
                                <th class="px-4 py-3 text-right font-normal">Max LT</th>
                            </tr>
                        </thead>
                        <tbody id="bodyLtProduk" class="divide-y divide-[#e8e8e8] dark:divide-gray-800/60 bg-white dark:bg-gray-900">
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-text-muted">Memuat data produk...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- 3. Log Transaksi PoLeadTime & Invalidation --}}
            <x-card title="Log Transaksi Lead Time PO (PoLeadTime)" subtitle="100 transaksi lead time terakhir dengan opsi pembatalan validitas (Invalidate) untuk outlier" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" id="tblLtTransaksi">
                        <thead class="bg-[#f8fafb] dark:bg-gray-800/95 border-y border-[#e8e8e8] dark:border-border text-text-muted">
                            <tr>
                                <th class="px-3 py-3 text-left font-normal">No PO</th>
                                <th class="px-3 py-3 text-left font-normal">Supplier</th>
                                <th class="px-3 py-3 text-left font-normal">Waktu Selesai</th>
                                <th class="px-3 py-3 text-right font-normal">Total LT</th>
                                <th class="px-3 py-3 text-right font-normal">LT Internal</th>
                                <th class="px-3 py-3 text-right font-normal">LT Supplier</th>
                                <th class="px-3 py-3 text-right font-normal">LT Logistik</th>
                                <th class="px-3 py-3 text-center font-normal">Status</th>
                                <th class="px-3 py-3 text-center font-normal">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bodyLtTransaksi" class="divide-y divide-[#e8e8e8] dark:divide-gray-800/60 bg-white dark:bg-gray-900">
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-text-muted">Memuat transaksi lead time...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Modal Detail Breakdown Stage Supplier -->
    <div id="modalStageBreakdown" class="fixed inset-0 z-[999] bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-900 rounded-[12px] border border-[#e8e8e8] dark:border-border shadow-2xl max-w-2xl w-full overflow-hidden">
            <div class="px-4 py-3 bg-white dark:bg-gray-900 border-b border-[#e8e8e8] dark:border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight">Breakdown Performa Stage</h3>
                    <p class="text-xs text-text-muted" id="modalStageSupplierName">Supplier: —</p>
                </div>
                <button type="button" onclick="closeStageBreakdownModal()" class="text-text-muted hover:text-text-active p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer rounded-[6px]">✕</button>
            </div>
            <div class="p-4 sm:p-5 max-h-[75vh] overflow-y-auto custom-scrollbar bg-[#F5F5F5] dark:bg-gray-950">
                <div class="bg-white dark:bg-gray-900 rounded-[8px] border border-[#e8e8e8] dark:border-border overflow-hidden">
                    <table class="w-full text-xs">
                        <thead class="bg-[#f8fafb] dark:bg-gray-800/95 border-b border-[#e8e8e8] dark:border-border text-text-muted">
                            <tr>
                                <th class="px-3 py-2 text-left font-normal">Tahap (Stage)</th>
                                <th class="px-3 py-2 text-center font-normal">Jml Transaksi</th>
                                <th class="px-3 py-2 text-right font-normal">Rata-rata Hari</th>
                                <th class="px-3 py-2 text-right font-normal">Rata-rata Jam</th>
                                <th class="px-3 py-2 text-right font-normal">Max Hari</th>
                            </tr>
                        </thead>
                        <tbody id="bodyModalStages" class="divide-y divide-[#e8e8e8] dark:divide-gray-800/60">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="px-4 py-3 bg-white dark:bg-gray-900 border-t border-[#e8e8e8] dark:border-border flex justify-end">
                <x-button type="button" onclick="closeStageBreakdownModal()" variant="secondary" size="sm">Tutup</x-button>
            </div>
        </div>
    </div>

    <!-- Modal Invalidate Lead Time Transaksi -->
    <div id="modalInvalidateLt" class="fixed inset-0 z-[999] bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-900 rounded-[12px] border border-[#e8e8e8] dark:border-border shadow-2xl max-w-md w-full overflow-hidden">
            <div class="px-4 py-3 bg-white dark:bg-gray-900 border-b border-[#e8e8e8] dark:border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-red-600 dark:text-red-400 flex items-center gap-1.5 tracking-tight">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Batalkan Validitas Lead Time PO
                    </h3>
                    <p class="text-xs text-text-muted" id="modalInvPoNo">PO: —</p>
                </div>
                <button type="button" onclick="closeInvalidateLtModal()" class="text-text-muted hover:text-text-active p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer rounded-[6px]">✕</button>
            </div>
            <form id="formInvalidateLt" onsubmit="submitInvalidateLt(event)" class="text-xs">
                <input type="hidden" id="invPoId">
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5 space-y-3">
                    <div class="p-3 bg-red-50/90 dark:bg-red-500/10 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/20 rounded-[8px] text-xs leading-relaxed">
                        Tindakan ini akan mengecualikan transaksi PO ini dari perhitungan rata-rata benchmark lead time vendor, mengembalikan akumulasi durasi, dan menghitung ulang SLA.
                    </div>
                    <x-form-field label="Alasan Anomali" name="alasan" :required="true" align="top">
                        <textarea id="invAlasan" rows="3" required minlength="5" placeholder="Contoh: Terjadi bencana alam banjir di pabrik supplier, bukan performa normal vendor..." class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0"></textarea>
                    </x-form-field>
                </div>
                <div class="px-4 py-3 bg-white dark:bg-gray-900 border-t border-[#e8e8e8] dark:border-border flex items-center justify-end gap-2">
                    <x-button type="button" onclick="closeInvalidateLtModal()" variant="secondary" size="sm">Batal</x-button>
                    <x-button type="submit" variant="danger" size="sm" id="btnSubmitInvalidate">Batalkan Validitas</x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Quick-Edit Tanggal PO & ETA -->
    <div id="quickDateModal" class="fixed inset-0 z-[999] bg-black/50 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-900 rounded-[12px] border border-[#e8e8e8] dark:border-border shadow-2xl max-w-sm w-full overflow-hidden">
            <div class="px-4 py-3 bg-white dark:bg-gray-900 border-b border-[#e8e8e8] dark:border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight">Ubah Tanggal PO & ETA</h3>
                    <p class="text-xs text-text-muted" id="modalPoNumber">PO-XXXX</p>
                </div>
                <button type="button" onclick="closeQuickDateModal()" class="text-text-muted hover:text-text-active p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer rounded-[6px]">✕</button>
            </div>
            <form id="quickDateForm" onsubmit="submitQuickDate(event)" class="text-xs">
                <input type="hidden" id="editPoId">
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5 space-y-2.5">
                    <x-form-field label="Tanggal PO" name="tanggal" :required="true">
                        <input type="date" id="editTanggal" required class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active outline-none">
                    </x-form-field>
                    <x-form-field label="Estimasi (ETA)" name="eta">
                        <input type="date" id="editEta" class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active outline-none">
                    </x-form-field>
                </div>
                <div class="px-4 py-3 bg-white dark:bg-gray-900 border-t border-[#e8e8e8] dark:border-border flex items-center justify-end gap-2">
                    <x-button type="button" onclick="closeQuickDateModal()" variant="secondary" size="sm">Batal</x-button>
                    <x-button type="submit" variant="primary" size="sm" id="btnSaveQuickDate">Simpan Perubahan</x-button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    let dtTable = null;
    let dtApTable = null;
    let dtApprovalTable = null;
    let ltGlobalData = null;
    const canInvalidateLt = {{ $canInvalidateLt ? 'true' : 'false' }};

    $(function(){
        const canSeePrice = {{ $canSeePrice ? 'true' : 'false' }};

        // Status modern: titik warna + label (gaya tabel `tbl-modern`)
        function tblStatus(label, tone) {
            return `<span class="tbl-status"><i class="tbl-dot ${tone ? 'tbl-dot--' + tone : ''}" aria-hidden="true"></i>${label}</span>`;
        }

        function renderStatusApPriorityBadge(data) {
            if (!data || data === '—') return '—';
            let norm = data.toLowerCase().replace(/_/g, ' ');
            if (norm.includes('batal')) return tblStatus('Batal', 'bad');
            if (norm.includes('overdue')) return tblStatus('Overdue', 'bad');
            if (norm.includes('belum')) return tblStatus('Belum Lunas', 'warn');
            if (norm.includes('parsial')) return tblStatus('Parsial', 'info');
            if (norm.includes('lunas')) return tblStatus('Lunas', 'ok');
            return tblStatus(data.replace(/_/g, ' '), '');
        }

        function renderStatusPoBadge(data) {
            let norm = (data || '').toLowerCase();
            let tone = '';
            if (norm.includes('diajukan')) tone = 'warn';
            else if (norm.includes('disetujui') || norm.includes('berjalan') || norm.includes('gudang')) tone = 'info';
            else if (norm.includes('selesai')) tone = 'ok';
            else if (norm.includes('batal')) tone = 'bad';
            return tblStatus(data, tone);
        }

        // ==========================================
        // 1. DataTables: Daftar Purchase Order (10 Kolom)
        // No Invoice | Supplier | Lokasi | Tgl PO | ETA | Tgl Tiba | Status PO | Stage Aktif | SLA | Aksi
        // ==========================================
        dtTable = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            order: [],
            search: {
                search: @json(request('search', ''))
            },
            ajax: {
                url: '{{ route("purchasing.data") }}',
                data: function(d) {
                    d.status_po = $('#filter-status-po').val();
                    d.kategori_supplier = $('#filter-kategori-supplier').val();
                }
            },
            columns: [
                {
                    data: 'no_invoice',
                    render: function(data, type, row) {
                        let invTitle = data && data !== '—' ? data : row.no_po;
                        let sub = row.no_po && row.no_po !== invTitle ? `<span class="block text-[10px] text-muted-foreground font-normal">PO: ${row.no_po}</span>` : '';
                        let revisiBadge = row.has_pending_revisi ? `<span class="tbl-status mt-1" style="font-size:11.5px"><i class="tbl-dot tbl-dot--warn" aria-hidden="true"></i>Revisi menunggu</span>` : '';
                        return `<span class="tabular-nums font-medium text-foreground">${invTitle}</span>${sub}${revisiBadge}`;
                    }
                },
                { data: 'supplier_nama', orderable: false },
                { data: 'gudang_nama', orderable: false },
                { data: 'tanggal', className: 'tabular-nums' },
                { data: 'eta', className: 'tabular-nums' },
                { data: 'tgl_aktual_tiba', className: 'tabular-nums' },
                {
                    data: 'status',
                    render: function(data) {
                        return renderStatusPoBadge(data);
                    }
                },
                {
                    data: 'total_nilai',
                    orderable: false,
                    className: 'text-left tabular-nums text-foreground',
                    render: function(data) {
                        return (!data || data === '—') ? '—' : `Rp ${data}`;
                    }
                },
                {
                    data: 'sla',
                    orderable: false,
                    className: 'tabular-nums text-xs',
                    render: function(data, type, row) {
                        if (!data || data === '—') return '—';
                        if (row.stage_badge_state === 'terlambat' || String(data).includes('⚠️')) {
                            return `<span class="text-rose-600 font-medium">${data}</span>`;
                        }
                        return `<span class="text-foreground">${data}</span>`;
                    }
                },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });

        $('#filter-status-po, #filter-kategori-supplier').on('change', function() {
            if (dtTable) dtTable.ajax.reload();
        });

        $('#btn-reset-po').on('click', function() {
            $('#filter-status-po').val('');
            $('#filter-kategori-supplier').val('');
            if (dtTable) {
                dtTable.search('').ajax.reload();
            }
        });

        // ==========================================
        // 2. DataTables: Buku Tagihan AP (10 Kolom)
        // No Invoice | Supplier | Tgl PO | Skema | Total Tagihan | Telah Dibayar | Sisa Tagihan | Jatuh Tempo | Status Bayar | Aksi
        // ==========================================
        if (canSeePrice && $('#tblAp').length) {
            dtApTable = $('#tblAp').DataTable({
                processing: true,
                serverSide: true,
                order: [],
                ajax: {
                    url: '{{ route("purchasing.data-ap") }}',
                    data: function(d) {
                        d.status_bayar = $('#filter-status-bayar-ap').val();
                        d.skema_bayar = $('#filter-skema-bayar-ap').val();
                    }
                },
                columns: [
                    {
                        data: 'no_invoice',
                        render: function(data, type, row) {
                            let invTitle = data && data !== '—' ? data : row.no_po;
                            let sub = row.no_po && row.no_po !== invTitle ? `<span class="block text-[10px] text-muted-foreground font-normal">PO: ${row.no_po}</span>` : '';
                            return `<span class="tabular-nums font-medium text-foreground">${invTitle}</span>${sub}`;
                        }
                    },
                    { data: 'supplier_nama', orderable: false },
                    { data: 'tanggal', className: 'tabular-nums' },
                    { data: 'skema_bayar' },
                    {
                        data: 'total_nilai',
                        className: 'text-right tabular-nums text-foreground',
                        render: function(data) { return `Rp ${data}`; }
                    },
                    {
                        data: 'total_dibayar',
                        className: 'text-right tabular-nums text-foreground font-medium',
                        render: function(data) { return `Rp ${data}`; }
                    },
                    {
                        data: 'sisa',
                        className: 'text-right tabular-nums font-medium',
                        render: function(data, type, row) {
                            if (data === '—' || data === null || data === undefined) return '—';
                            const statusNorm = (row.status_pembayaran || '').toLowerCase().trim();
                            if (statusNorm.includes('batal')) {
                                return `<span class="text-muted-foreground font-medium tabular-nums">Rp 0</span>`;
                            }
                            const isLunas = data === '0' || statusNorm === 'lunas';
                            if (isLunas) {
                                return `<span class="text-foreground font-medium tabular-nums">Rp 0</span>`;
                            }
                            return `<span class="text-rose-600 font-medium tabular-nums">Rp ${data}</span>`;
                        }
                    },
                    {
                        data: 'jatuh_tempo_terdekat',
                        className: 'tabular-nums text-xs',
                        render: function(data, type, row) {
                            if (row.status_pembayaran === 'overdue') {
                                return `<span class="text-rose-600 font-medium inline-flex items-center gap-1">⚠️ ${data}</span>`;
                            }
                            return `<span class="text-foreground">${data}</span>`;
                        }
                    },
                    {
                        data: 'status_pembayaran',
                        render: function(data) {
                            return renderStatusApPriorityBadge(data);
                        }
                    },
                    { data: 'action', orderable: false, searchable: false, className: 'text-center' }
                ]
            });

            $('#filter-status-bayar-ap, #filter-skema-bayar-ap').on('change', function() {
                if (dtApTable) dtApTable.ajax.reload();
            });

            $('#btn-reset-ap').on('click', function() {
                $('#filter-status-bayar-ap').val('');
                $('#filter-skema-bayar-ap').val('');
                if (dtApTable) dtApTable.ajax.reload();
            });
        }

        @if($canSeeApprovalTab)
        // ==========================================
        // 3. DataTables: Antrean Approval Manager & Purchasing
        // Menyaring data PO dengan status 'diajukan' atau stage 'approval'
        // ==========================================
        dtApprovalTable = $('#tblApproval').DataTable({
            processing: true,
            serverSide: true,
            order: [],
            ajax: {
                url: '{{ route("purchasing.data") }}',
                data: function(d) {
                    d.approval_queue = 1;
                }
            },
            columns: [
                {
                    data: 'no_invoice',
                    render: function(data, type, row) {
                        let invTitle = data && data !== '—' ? data : row.no_po;
                        let sub = row.no_po && row.no_po !== invTitle ? `<span class="block text-[10px] text-muted-foreground font-normal">PO: ${row.no_po}</span>` : '';
                        return `<span class="tabular-nums font-medium text-foreground">${invTitle}</span>${sub}`;
                    }
                },
                { data: 'supplier_nama', orderable: false },
                { data: 'gudang_nama', orderable: false },
                { data: 'tanggal', className: 'tabular-nums' },
                { data: 'eta', className: 'tabular-nums' },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        let label = row.stage_label || 'Menunggu Approval';
                        return tblStatus(label, 'warn');
                    }
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `<div class="flex items-center justify-center gap-1.5">
                            <a href="{{ url('purchasing') }}/${row.id}" class="inline-flex items-center px-2 py-1 bg-gray-900 dark:bg-white hover:bg-gray-800 dark:hover:bg-gray-100 text-white dark:text-gray-900 rounded text-[11px] font-medium transition shadow-2xs">
                                Review & Approve &rarr;
                            </a>
                        </div>`;
                    }
                }
            ]
        });
        @endif
    });

    // =========================================================================
    // 4. Sub-Tab Lead Time Supplier AJAX Loader & Modals
    // =========================================================================
    function loadLeadTimeData() {
        $('#bodyLtSupplier').html('<tr><td colspan="6" class="px-4 py-8 text-center text-muted-foreground">Memuat ringkasan supplier...</td></tr>');
        $('#bodyLtProduk').html('<tr><td colspan="7" class="px-4 py-8 text-center text-muted-foreground">Memuat data produk...</td></tr>');
        $('#bodyLtTransaksi').html('<tr><td colspan="9" class="px-4 py-8 text-center text-muted-foreground">Memuat transaksi lead time...</td></tr>');

        fetch('{{ route("purchasing.lead-time-data") }}')
            .then(res => res.json())
            .then(data => {
                ltGlobalData = data;
                renderLtSuppliers(data.suppliers, data.supplier_stages);
                renderLtProduk(data.supplier_produk);
                renderLtTransaksi(data.transaksi);
            })
            .catch(err => {
                console.error(err);
                $('#bodyLtSupplier').html('<tr><td colspan="6" class="px-4 py-8 text-center text-rose-500 font-medium">Gagal memuat data lead time supplier.</td></tr>');
            });
    }

    function renderLtSuppliers(suppliers, stages) {
        if (!suppliers || suppliers.length === 0) {
            $('#bodyLtSupplier').html('<tr><td colspan="6" class="px-4 py-8 text-center text-muted-foreground">Belum ada data benchmark lead time supplier.</td></tr>');
            return;
        }

        let html = '';
        suppliers.forEach(s => {
            html += `<tr class="hover:bg-muted/40 transition">
                <td class="px-4 py-3 font-medium text-foreground">${s.supplier_nama}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-[10px] font-medium bg-muted text-muted-foreground uppercase">${s.kategori || '—'}</span></td>
                <td class="px-4 py-3 text-center tabular-nums font-medium">${s.jumlah_transaksi} PO</td>
                <td class="px-4 py-3 text-right tabular-nums font-medium text-foreground">
                    ${s.average_lt_hari} hari
                    <span class="text-[10px] text-muted-foreground font-normal">(${s.average_lt_jam} jam)</span>
                </td>
                <td class="px-4 py-3 text-right tabular-nums font-medium text-foreground">
                    ${s.max_lt_hari} hari
                </td>
                <td class="px-4 py-3 text-center">
                    <button type="button" onclick="openStageBreakdownModal(${s.supplier_id}, '${s.supplier_nama.replace(/'/g, "\\'")}')" class="inline-flex items-center px-2 py-1 rounded text-[11px] font-medium bg-primary/10 text-primary hover:bg-primary/20 transition cursor-pointer">
                        Lihat 9 Stage &rarr;
                    </button>
                </td>
            </tr>`;
        });
        $('#bodyLtSupplier').html(html);
    }

    function renderLtProduk(produkList) {
        if (!produkList || produkList.length === 0) {
            $('#bodyLtProduk').html('<tr><td colspan="7" class="px-4 py-8 text-center text-muted-foreground">Belum ada data lead time per produk.</td></tr>');
            return;
        }

        let html = '';
        produkList.forEach(p => {
            html += `<tr class="hover:bg-muted/40 transition">
                <td class="px-4 py-3 font-medium text-foreground">${p.supplier_nama}</td>
                <td class="px-4 py-3 tabular-nums font-medium text-primary">${p.sku}</td>
                <td class="px-4 py-3 font-medium text-foreground">${p.nama_produk}</td>
                <td class="px-4 py-3 text-center text-muted-foreground uppercase text-[10px]">${p.satuan || '—'}</td>
                <td class="px-4 py-3 text-center tabular-nums">${p.jumlah_transaksi} PO</td>
                <td class="px-4 py-3 text-right tabular-nums font-medium text-foreground">
                    ${p.average_lt_hari} hari
                    <span class="text-[10px] text-muted-foreground font-normal">(${p.average_lt_jam} jam)</span>
                </td>
                <td class="px-4 py-3 text-right tabular-nums font-medium text-foreground">${p.max_lt_hari} hari</td>
            </tr>`;
        });
        $('#bodyLtProduk').html(html);
    }

    function renderLtTransaksi(transaksiList) {
        if (!transaksiList || transaksiList.length === 0) {
            $('#bodyLtTransaksi').html('<tr><td colspan="9" class="px-4 py-8 text-center text-muted-foreground">Belum ada riwayat transaksi lead time PO.</td></tr>');
            return;
        }

        let html = '';
        transaksiList.forEach(t => {
            let statusBadge = t.is_valid
                ? '<span class="px-2 py-0.5 rounded text-[10px] font-medium bg-card text-foreground border border-border">Valid</span>'
                : `<span class="px-2 py-0.5 rounded text-[10px] font-medium bg-rose-50 text-rose-700 border border-rose-200" title="${t.alasan_tidak_valid || ''}">Dibatalkan</span>`;

            let actionBtn = '—';
            if (canInvalidateLt && t.is_valid) {
                actionBtn = `<button type="button" onclick="openInvalidateLtModal('${t.po_id}', '${t.no_po}')" class="px-2 py-1 text-[11px] font-medium text-destructive hover:bg-destructive/10 rounded transition cursor-pointer">
                    Invalidate
                </button>`;
            }

            html += `<tr class="hover:bg-muted/40 transition">
                <td class="px-3 py-2.5 tabular-nums font-medium text-foreground">
                    <a href="{{ url('purchasing') }}/${t.po_id}" class="hover:underline text-primary">${t.no_invoice && t.no_invoice !== '—' ? t.no_invoice : t.no_po}</a>
                    <span class="block text-[10px] text-muted-foreground font-normal">PO: ${t.no_po}</span>
                </td>
                <td class="px-3 py-2.5 text-foreground">${t.supplier_nama}</td>
                <td class="px-3 py-2.5 tabular-nums text-[11px] text-muted-foreground">${t.waktu_selesai}</td>
                <td class="px-3 py-2.5 text-right tabular-nums font-medium text-foreground">${t.total_lt_hari} hr <span class="text-[10px] font-normal text-muted-foreground">(${t.total_lt_jam} j)</span></td>
                <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">${t.lt_internal_hari} hr</td>
                <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">${t.lt_supplier_hari} hr</td>
                <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">${t.lt_logistik_hari} hr</td>
                <td class="px-3 py-2.5 text-center">${statusBadge}</td>
                <td class="px-3 py-2.5 text-center">${actionBtn}</td>
            </tr>`;
        });
        $('#bodyLtTransaksi').html(html);
    }

    function openStageBreakdownModal(supplierId, supplierNama) {
        $('#modalStageSupplierName').text(`Supplier: ${supplierNama}`);
        if (!ltGlobalData || !ltGlobalData.supplier_stages) return;

        let supplierStages = ltGlobalData.supplier_stages.filter(s => s.supplier_id == supplierId);
        let rows = '';
        if (supplierStages.length === 0) {
            rows = '<tr><td colspan="5" class="px-3 py-4 text-center text-muted-foreground">Belum ada data breakdown tahap.</td></tr>';
        } else {
            supplierStages.forEach(st => {
                rows += `<tr>
                    <td class="px-3 py-2.5 font-medium text-foreground">${st.stage_label}</td>
                    <td class="px-3 py-2.5 text-center tabular-nums">${st.jumlah_transaksi} PO</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-medium">${st.average_hari} hr</td>
                    <td class="px-3 py-2.5 text-right tabular-nums text-muted-foreground">${st.average_jam} j</td>
                    <td class="px-3 py-2.5 text-right tabular-nums font-medium text-foreground">${st.max_hari} hr</td>
                </tr>`;
            });
        }
        $('#bodyModalStages').html(rows);
        $('#modalStageBreakdown').removeClass('hidden').addClass('flex');
    }

    function closeStageBreakdownModal() {
        $('#modalStageBreakdown').removeClass('flex').addClass('hidden');
    }

    function openInvalidateLtModal(poId, noPo) {
        $('#invPoId').val(poId);
        $('#modalInvPoNo').text(`PO: ${noPo}`);
        $('#invAlasan').val('');
        $('#modalInvalidateLt').removeClass('hidden').addClass('flex');
    }

    function closeInvalidateLtModal() {
        $('#modalInvalidateLt').removeClass('flex').addClass('hidden');
    }

    function submitInvalidateLt(e) {
        e.preventDefault();
        const poId = $('#invPoId').val();
        const alasan = $('#invAlasan').val();
        const btn = $('#btnSubmitInvalidate');

        btn.prop('disabled', true).text('Memproses...');

        fetch(`{{ url('purchasing') }}/${poId}/lead-time/invalidate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ alasan })
        })
        .then(res => res.json())
        .then(data => {
            btn.prop('disabled', false).text('Batalkan Validitas');
            if (data.success) {
                closeInvalidateLtModal();
                loadLeadTimeData();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    appDialog.alert(data.message, { tone: 'danger', title: 'Gagal' });
                }
            } else {
                appDialog.alert(data.message || 'Gagal membatalkan validitas lead time.', { tone: 'danger', title: 'Gagal' });
            }
        })
        .catch(err => {
            btn.prop('disabled', false).text('Batalkan Validitas');
            appDialog.alert('Terjadi kesalahan koneksi.', { tone: 'danger', title: 'Gagal' });
        });
    }

    function openQuickDateModal(id, noPo, tanggal, eta) {
        $('#editPoId').val(id);
        $('#modalPoNumber').text(noPo);
        $('#editTanggal').val(tanggal);
        $('#editEta').val(eta);
        $('#quickDateModal').removeClass('hidden').addClass('flex');
    }

    function closeQuickDateModal() {
        $('#quickDateModal').removeClass('flex').addClass('hidden');
    }

    function submitQuickDate(e) {
        e.preventDefault();
        const id = $('#editPoId').val();
        const tanggal = $('#editTanggal').val();
        const eta = $('#editEta').val();
        const btn = $('#btnSaveQuickDate');

        btn.prop('disabled', true).text('Menyimpan...');

        fetch(`{{ url('purchasing') }}/${id}/quick-dates`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ tanggal, eta })
        })
        .then(res => res.json())
        .then(data => {
            btn.prop('disabled', false).text('Simpan Perubahan');
            if (data.success) {
                closeQuickDateModal();
                if (dtTable) dtTable.ajax.reload(null, false);
                if (dtApTable) dtApTable.ajax.reload(null, false);
                if (dtApprovalTable) dtApprovalTable.ajax.reload(null, false);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    appDialog.alert(data.message, { tone: 'danger', title: 'Gagal' });
                }
            } else {
                appDialog.alert(data.message || 'Gagal menyimpan perubahan.', { tone: 'danger', title: 'Gagal' });
            }
        })
        .catch(err => {
            btn.prop('disabled', false).text('Simpan Perubahan');
            appDialog.alert('Terjadi kesalahan koneksi.', { tone: 'danger', title: 'Gagal' });
        });
    }
    </script>
    @endpush
</x-app-layout>