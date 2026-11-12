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

    <div x-data="{ activeTab: '{{ $defaultTab }}', leadTimeLoaded: {{ $defaultTab === 'lead_time' ? 'true' : 'false' }} }"
         x-init="{{ $defaultTab === 'lead_time' ? '$nextTick(() => { loadLeadTimeData(); })' : '' }}">
        {{-- Segmented Tabs --}}
        <div class="inline-flex flex-wrap items-center gap-1 p-1 bg-muted/40 rounded-lg border border-border mb-3.5">
            {{-- TAB 1: Daftar Purchase Order --}}
            <button
                type="button"
                @click="activeTab = 'po'; $nextTick(() => { if (dtTable) dtTable.columns.adjust(); })"
                :class="activeTab === 'po' ? 'bg-primary text-primary-foreground shadow-2xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors cursor-pointer select-none flex items-center gap-1.5"
            >
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Daftar Purchase Order
            </button>

            {{-- TAB 2: Buku Tagihan & Pembayaran (AP) --}}
            @if($canSeePrice)
            <button
                type="button"
                @click="activeTab = 'ap'; $nextTick(() => { if (dtApTable) dtApTable.columns.adjust(); })"
                :class="activeTab === 'ap' ? 'bg-primary text-primary-foreground shadow-2xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors cursor-pointer select-none flex items-center gap-1.5"
            >
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Buku Tagihan (AP)
            </button>
            @endif

            {{-- TAB 3: Antrean Approval --}}
            @if($canSeeApprovalTab)
            <button
                type="button"
                @click="activeTab = 'approval'; $nextTick(() => { if (dtApprovalTable) dtApprovalTable.columns.adjust(); })"
                :class="activeTab === 'approval' ? 'bg-primary text-primary-foreground shadow-2xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors cursor-pointer select-none flex items-center gap-1.5"
            >
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Antrean Approval
                @if($pendingApprovalCount > 0)
                    <span :class="activeTab === 'approval' ? 'bg-primary-foreground text-primary' : 'bg-muted text-foreground'" class="px-1.5 py-0.5 text-[9px] font-bold rounded-full">
                        {{ $pendingApprovalCount }}
                    </span>
                @endif
            </button>
            @endif

            {{-- TAB 4: Lead Time Supplier --}}
            <button
                type="button"
                @click="activeTab = 'lead_time'; if (!leadTimeLoaded) { leadTimeLoaded = true; loadLeadTimeData(); }"
                :class="activeTab === 'lead_time' ? 'bg-primary text-primary-foreground shadow-2xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors cursor-pointer select-none flex items-center gap-1.5"
            >
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Lead Time Supplier
            </button>
        </div>

        <!-- TAB 1: Daftar Purchase Order -->
        <div x-show="activeTab === 'po'" class="min-h-[480px]">
            <!-- Toolbar Filter PO (Menjaga filter-status-po & btn-reset-po) -->
            <div class="flex flex-wrap items-center justify-between gap-2.5 bg-card px-3.5 py-2 rounded-lg border border-border mb-3 shadow-2xs">
                <div class="flex flex-wrap items-center gap-2.5">
                    <div>
                        <label for="filter-status-po" class="block text-[11px] font-medium text-muted-foreground mb-0.5">Status PO</label>
                        <select id="filter-status-po" class="h-8 rounded-md border border-input bg-background text-xs py-1 px-2.5 focus:ring-1 focus:ring-primary/20 text-foreground min-w-[170px] outline-none cursor-pointer">
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
                        <label for="filter-stage-po" class="block text-[11px] font-medium text-muted-foreground mb-0.5">Stage Aktif</label>
                        <select id="filter-stage-po" class="h-8 rounded-md border border-input bg-background text-xs py-1 px-2.5 focus:ring-1 focus:ring-primary/20 text-foreground min-w-[170px] outline-none cursor-pointer">
                            <option value="">— Semua Stage —</option>
                            <option value="perencanaan">1. Perencanaan</option>
                            <option value="approval">2. Approval</option>
                            <option value="konfirmasi_supplier">3. Konfirmasi Supplier</option>
                            <option value="pembayaran">4. Pembayaran / DP</option>
                            <option value="po_terbit">5. PO Terbit</option>
                            <option value="pengemasan">6. Pengemasan</option>
                            <option value="pengiriman">7. Pengiriman</option>
                            <option value="unloading">8. Unloading Fisik</option>
                            <option value="input_sistem">9. Input Sistem</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-kategori-supplier" class="block text-[11px] font-medium text-muted-foreground mb-0.5">Kategori Supplier</label>
                        <select id="filter-kategori-supplier" class="h-8 rounded-md border border-input bg-background text-xs py-1 px-2.5 focus:ring-1 focus:ring-primary/20 text-foreground min-w-[150px] outline-none cursor-pointer">
                            <option value="">— Semua Kategori —</option>
                            <option value="lokal">Lokal</option>
                            <option value="impor">Impor</option>
                            <option value="distributor">Distributor</option>
                            <option value="pabrik">Pabrik</option>
                        </select>
                    </div>
                </div>
                <div>
                    <button type="button" id="btn-reset-po" class="text-xs text-muted-foreground hover:text-foreground font-medium cursor-pointer transition-colors">
                        Reset Filter
                    </button>
                </div>
            </div>

            <x-card title="Semua Dokumen Purchase Order" :noPadding="true">
                <div class="overflow-x-auto">
                    <table id="tbl" class="w-full text-sm">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                                <th class="px-4 py-3 text-left">No Invoice</th>
                                <th class="px-4 py-3 text-left">Supplier</th>
                                <th class="px-4 py-3 text-left">Lokasi</th>
                                <th class="px-4 py-3 text-left">Tgl PO</th>
                                <th class="px-4 py-3 text-left">ETA</th>
                                <th class="px-4 py-3 text-left">Tgl Tiba</th>
                                <th class="px-4 py-3 text-left">Status PO</th>
                                <th class="px-4 py-3 text-left">Stage Aktif</th>
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
            <!-- Toolbar Filter AP (Filter status AP terpusat di sini) -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-card p-3 rounded-xl border border-border mb-3 shadow-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <label for="filter-status-bayar-ap" class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">Filter Status Bayar / AP</label>
                        <select id="filter-status-bayar-ap" class="rounded-lg border-border bg-card text-xs py-1.5 px-2.5 focus:border-primary focus:ring-1 focus:ring-primary min-w-[200px] outline-none">
                            <option value="">— Semua Status Bayar —</option>
                            <option value="overdue">🔴 Overdue (Jatuh Tempo - Prioritas)</option>
                            <option value="belum_lunas">🟡 Belum Lunas</option>
                            <option value="parsial">🔵 Parsial (Sebagian)</option>
                            <option value="lunas">🟢 Lunas</option>
                            <option value="batal">⚫ Batal</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-skema-bayar-ap" class="block text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-1">Filter Skema Transaksi</label>
                        <select id="filter-skema-bayar-ap" class="rounded-lg border-border bg-card text-xs py-1.5 px-2.5 focus:border-primary focus:ring-1 focus:ring-primary min-w-[160px] outline-none">
                            <option value="">— Semua Skema —</option>
                            <option value="cash">Tunai (Cash)</option>
                            <option value="tempo">Tempo</option>
                            <option value="termin">Termin</option>
                        </select>
                    </div>
                </div>
                <div>
                    <button type="button" id="btn-reset-ap" class="text-xs text-muted-foreground hover:text-foreground font-medium cursor-pointer transition-colors">
                        Reset Filter
                    </button>
                </div>
            </div>

            <x-card title="Buku Rekapitulasi Tagihan Vendor (Accounts Payable)" :noPadding="true">
                <div class="overflow-x-auto p-2">
                    <table id="tblAp" class="w-full text-xs">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[11px] uppercase">
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
        <!-- TAB 3: Antrean Approval (Default: Manager & Purchasing) -->
        <div x-show="activeTab === 'approval'" x-cloak class="min-h-[480px] space-y-4">
            <div class="p-3 bg-amber-50/80 border border-amber-200 rounded-xl text-xs flex items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center gap-2 text-amber-900">
                    <span class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>Antrean ini menyaring dokumen PO yang menunggu persetujuan otorisasi Manager (Stage 2 / Diajukan) dan usulan Revisi Komitmen PO.</span>
                </div>
                <div class="shrink-0 font-mono text-xs font-semibold text-amber-800">
                    Total Menunggu: <span class="px-2 py-0.5 bg-amber-200/80 rounded font-bold">{{ $pendingApprovalCount }}</span>
                </div>
            </div>

            {{-- Daftar Antrean Revisi Komitmen (PoRevisi Menunggu) --}}
            @if(isset($pendingRevisis) && $pendingRevisis->isNotEmpty())
            <x-card title="Antrean Usulan Revisi Komitmen (PoRevisi)" :noPadding="true">
                <div class="overflow-x-auto p-2">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-amber-50 border-b border-amber-200 text-amber-900 text-[11px] uppercase">
                                <th class="px-3 py-2.5 text-left">No PO / Invoice</th>
                                <th class="px-3 py-2.5 text-left">Supplier</th>
                                <th class="px-3 py-2.5 text-left">Revisi Ke</th>
                                <th class="px-3 py-2.5 text-left">Diajukan Oleh</th>
                                <th class="px-3 py-2.5 text-left">Alasan Pengajuan</th>
                                <th class="px-3 py-2.5 text-center">Aksi Review</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($pendingRevisis as $rev)
                            <tr class="hover:bg-muted/40 transition">
                                <td class="px-3 py-2.5 font-mono font-bold text-foreground">
                                    {{ $rev->purchaseOrder?->no_invoice ?: $rev->purchaseOrder?->no_po }}
                                    <span class="block text-[10px] text-muted-foreground font-normal">PO: {{ $rev->purchaseOrder?->no_po }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-foreground">{{ $rev->purchaseOrder?->supplier?->nama ?? '—' }}</td>
                                <td class="px-3 py-2.5 font-mono">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                        Rev #{{ $rev->revisi_ke }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-foreground">{{ $rev->requester?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $rev->alasan ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <a href="{{ route('purchasing.show', $rev->purchaseOrder) }}" class="inline-flex items-center px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-[11px] font-semibold transition shadow-2xs">
                                        Review Usulan &rarr;
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            @endif

            {{-- Antrean PO Menunggu Persetujuan --}}
            <x-card title="Antrean Persetujuan Purchase Order" :noPadding="true">
                <div class="overflow-x-auto p-2">
                    <table id="tblApproval" class="w-full text-xs">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[11px] uppercase">
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

        {{-- TAB 4: Lead Time Supplier (Level-1, Level-2, 9-Stage Breakdown, & Log Transaksi) --}}
        <div x-show="activeTab === 'lead_time'" x-cloak class="min-h-[480px] space-y-6">
            {{-- Header & Sub-toolbar --}}
            <div class="flex flex-wrap items-center justify-between gap-3 bg-card p-4 rounded-xl border border-border shadow-xs">
                <div>
                    <h3 class="text-sm font-bold text-foreground">Analisis Lead Time & Performa Supplier (PRD v2.4)</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Ringkasan performa pemenuhan pesanan Level-1 (Supplier), Level-2 (Produk), Breakdown 9 Stage, dan Audit Log Transaksi</p>
                </div>
                <div>
                    <button type="button" onclick="loadLeadTimeData()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-muted hover:bg-muted/80 text-foreground rounded-lg text-xs font-medium transition cursor-pointer border border-border">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Segarkan Data
                    </button>
                </div>
            </div>

            {{-- 1. Level-1: Ringkasan Lead Time per Supplier --}}
            <x-card title="Level-1: Performa Lead Time per Supplier" subtitle="Rata-rata dan durasi maksimal lead time pengadaan per vendor" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" id="tblLtSupplier">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[11px] uppercase">
                                <th class="px-4 py-3 text-left">Supplier</th>
                                <th class="px-4 py-3 text-left">Kategori</th>
                                <th class="px-4 py-3 text-center">Jml Transaksi Valid</th>
                                <th class="px-4 py-3 text-right">Rata-rata Lead Time</th>
                                <th class="px-4 py-3 text-right">Lead Time Maksimal</th>
                                <th class="px-4 py-3 text-center">Breakdown 9 Stage</th>
                            </tr>
                        </thead>
                        <tbody id="bodyLtSupplier" class="divide-y divide-border">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">Memuat ringkasan supplier...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- 2. Level-2: Ringkasan Lead Time per Supplier - Produk --}}
            <x-card title="Level-2: Performa Lead Time per Supplier & SKU Produk" subtitle="Matriks kecepatan pemenuhan per varian/komoditas bahan baku" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" id="tblLtProduk">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[11px] uppercase">
                                <th class="px-4 py-3 text-left">Supplier</th>
                                <th class="px-4 py-3 text-left">SKU</th>
                                <th class="px-4 py-3 text-left">Nama Produk / Bahan</th>
                                <th class="px-4 py-3 text-center">Satuan</th>
                                <th class="px-4 py-3 text-center">Jml Transaksi</th>
                                <th class="px-4 py-3 text-right">Rata-rata LT</th>
                                <th class="px-4 py-3 text-right">Max LT</th>
                            </tr>
                        </thead>
                        <tbody id="bodyLtProduk" class="divide-y divide-border">
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">Memuat data produk...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- 3. Log Transaksi PoLeadTime & Invalidation --}}
            <x-card title="Log Transaksi Lead Time PO (PoLeadTime)" subtitle="100 transaksi lead time terakhir dengan opsi pembatalan validitas (Invalidate) untuk outlier" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" id="tblLtTransaksi">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[11px] uppercase">
                                <th class="px-3 py-3 text-left">No PO</th>
                                <th class="px-3 py-3 text-left">Supplier</th>
                                <th class="px-3 py-3 text-left">Waktu Selesai</th>
                                <th class="px-3 py-3 text-right">Total LT</th>
                                <th class="px-3 py-3 text-right">LT Internal</th>
                                <th class="px-3 py-3 text-right">LT Supplier</th>
                                <th class="px-3 py-3 text-right">LT Logistik</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="bodyLtTransaksi" class="divide-y divide-border">
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-muted-foreground">Memuat transaksi lead time...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Modal Detail Breakdown 9 Stage Supplier -->
    <div id="modalStageBreakdown" class="fixed inset-0 z-50 bg-black/40 hidden items-center justify-center p-4">
        <div class="bg-card rounded-xl border border-border shadow-xl max-w-2xl w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-5 py-3.5 bg-muted/50 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold text-foreground">Breakdown Performa 9 Stage</h3>
                    <p class="text-[11px] text-muted-foreground" id="modalStageSupplierName">Supplier: —</p>
                </div>
                <button type="button" onclick="closeStageBreakdownModal()" class="text-muted-foreground hover:text-foreground font-bold">✕</button>
            </div>
            <div class="p-4 max-h-[75vh] overflow-y-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[11px] uppercase">
                            <th class="px-3 py-2 text-left">Tahap (Stage)</th>
                            <th class="px-3 py-2 text-center">Jml Transaksi</th>
                            <th class="px-3 py-2 text-right">Rata-rata Hari</th>
                            <th class="px-3 py-2 text-right">Rata-rata Jam</th>
                            <th class="px-3 py-2 text-right">Max Hari</th>
                        </tr>
                    </thead>
                    <tbody id="bodyModalStages" class="divide-y divide-border">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 bg-muted/30 border-t border-border flex justify-end">
                <x-button type="button" onclick="closeStageBreakdownModal()" variant="secondary" size="xs">Tutup</x-button>
            </div>
        </div>
    </div>

    <!-- Modal Invalidate Lead Time Transaksi -->
    <div id="modalInvalidateLt" class="fixed inset-0 z-50 bg-black/40 hidden items-center justify-center p-4">
        <div class="bg-card rounded-xl border border-border shadow-xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-5 py-3.5 bg-muted/50 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold text-destructive flex items-center gap-1.5">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Batalkan Validitas Lead Time PO
                    </h3>
                    <p class="text-[11px] text-muted-foreground" id="modalInvPoNo">PO: —</p>
                </div>
                <button type="button" onclick="closeInvalidateLtModal()" class="text-muted-foreground hover:text-foreground font-bold">✕</button>
            </div>
            <form id="formInvalidateLt" onsubmit="submitInvalidateLt(event)" class="p-5 space-y-4 text-xs">
                <input type="hidden" id="invPoId">
                <div class="p-3 bg-destructive/10 text-destructive border border-destructive/20 rounded-lg text-xs leading-relaxed">
                    Tindakan ini akan mengecualikan transaksi PO ini dari perhitungan rata-rata benchmark lead time vendor, mengembalikan akumulasi durasi, dan menghitung ulang SLA.
                </div>
                <div>
                    <label class="block font-semibold text-foreground mb-1">Alasan Pembatalan / Anomali <span class="text-destructive">*</span></label>
                    <textarea id="invAlasan" rows="3" required minlength="5" placeholder="Contoh: Terjadi bencana alam banjir di pabrik supplier, bukan performa normal vendor..." class="w-full text-xs rounded-lg border border-border bg-card p-2.5 focus:ring-2 focus:ring-primary/20 text-foreground outline-none"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <x-button type="button" onclick="closeInvalidateLtModal()" variant="secondary" size="xs">Batal</x-button>
                    <x-button type="submit" variant="danger" size="xs" id="btnSubmitInvalidate">Batalkan Validitas</x-button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Quick-Edit Tanggal PO & ETA -->
    <div id="quickDateModal" class="fixed inset-0 z-50 bg-black/40 hidden items-center justify-center p-4">
        <div class="bg-card rounded-xl border border-border shadow-xl max-w-sm w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-4 py-3 bg-muted/50 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold text-foreground">Ubah Tanggal PO & ETA</h3>
                    <p class="text-[10px] text-muted-foreground" id="modalPoNumber">PO-XXXX</p>
                </div>
                <button type="button" onclick="closeQuickDateModal()" class="text-muted-foreground hover:text-foreground font-bold">✕</button>
            </div>
            <form id="quickDateForm" onsubmit="submitQuickDate(event)" class="p-4 space-y-3 text-xs">
                <input type="hidden" id="editPoId">
                <div>
                    <label class="block font-semibold text-foreground mb-1">Tanggal PO <span class="text-destructive">*</span></label>
                    <input type="date" id="editTanggal" required class="w-full text-xs py-1.5 px-3 rounded-lg border border-border bg-card focus:ring-2 focus:ring-primary/20 outline-none text-foreground">
                </div>
                <div>
                    <label class="block font-semibold text-foreground mb-1">Estimasi Kedatangan (ETA)</label>
                    <input type="date" id="editEta" class="w-full text-xs py-1.5 px-3 rounded-lg border border-border bg-card focus:ring-2 focus:ring-primary/20 outline-none text-foreground">
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <x-button type="button" onclick="closeQuickDateModal()" variant="secondary" size="xs">Batal</x-button>
                    <x-button type="submit" variant="primary" size="xs" id="btnSaveQuickDate">Simpan Perubahan</x-button>
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

        function renderStatusApPriorityBadge(data) {
            if (!data || data === '—') return '—';
            let norm = data.toLowerCase().replace(/_/g, ' ');
            let badgeClass = 'bg-muted text-muted-foreground border-border';
            let dotClass = 'bg-muted-foreground';
            let label = data.replace(/_/g, ' ');

            if (norm.includes('batal')) {
                badgeClass = 'bg-rose-50 text-rose-700 border-rose-200 font-semibold';
                dotClass = 'bg-rose-500';
                label = 'Batal';
            } else if (norm.includes('overdue')) {
                badgeClass = 'bg-rose-50 text-rose-700 border-rose-200 font-bold';
                dotClass = 'bg-rose-600 animate-pulse';
                label = 'Overdue';
            } else if (norm.includes('belum')) {
                badgeClass = 'bg-amber-50 text-amber-800 border-amber-200 font-semibold';
                dotClass = 'bg-amber-500';
                label = 'Belum Lunas';
            } else if (norm.includes('parsial')) {
                badgeClass = 'bg-blue-50 text-blue-700 border-blue-200 font-semibold';
                dotClass = 'bg-blue-500';
                label = 'Parsial';
            } else if (norm.includes('lunas')) {
                badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200 font-bold';
                dotClass = 'bg-emerald-600';
                label = 'Lunas';
            }

            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] border ${badgeClass}">
                <span class="w-1.5 h-1.5 rounded-full ${dotClass}"></span>
                ${label}
            </span>`;
        }

        function renderStatusPoBadge(data) {
            let color = 'bg-muted text-foreground border-border';
            let norm = (data || '').toLowerCase();
            if (norm.includes('diajukan')) color = 'bg-amber-100 text-amber-800 border-amber-300';
            if (norm.includes('disetujui')) color = 'bg-primary/10 text-primary border-primary/20';
            if (norm.includes('gudang')) color = 'bg-blue-100 text-blue-800 border-blue-200';
            if (norm.includes('selesai')) color = 'bg-emerald-100 text-emerald-800 border-emerald-300';
            if (norm.includes('batal')) color = 'bg-rose-100 text-rose-800 border-rose-200';
            return `<span class="px-2 py-0.5 rounded text-[10px] font-semibold border ${color}">${data}</span>`;
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
                    d.stage_aktif = $('#filter-stage-po').val();
                    d.kategori_supplier = $('#filter-kategori-supplier').val();
                }
            },
            columns: [
                {
                    data: 'no_invoice',
                    render: function(data, type, row) {
                        let invTitle = data && data !== '—' ? data : row.no_po;
                        let sub = row.no_po && row.no_po !== invTitle ? `<span class="block text-[10px] text-muted-foreground font-normal">PO: ${row.no_po}</span>` : '';
                        let revisiBadge = row.has_pending_revisi ? `<span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-300">Revisi Menunggu</span>` : '';
                        return `<span class="font-mono font-bold text-foreground">${invTitle}</span>${sub}${revisiBadge}`;
                    }
                },
                { data: 'supplier_nama', orderable: false },
                { data: 'gudang_nama', orderable: false },
                { data: 'tanggal', className: 'font-mono' },
                { data: 'eta', className: 'font-mono' },
                { data: 'tgl_aktual_tiba', className: 'font-mono' },
                {
                    data: 'status',
                    render: function(data) {
                        return renderStatusPoBadge(data);
                    }
                },
                {
                    data: 'stage_label',
                    orderable: false,
                    render: function(data, type, row) {
                        let state = row.stage_badge_state || 'berjalan';
                        let colorClass = 'bg-muted text-muted-foreground border-border';
                        if (state === 'selesai') colorClass = 'bg-emerald-50 text-emerald-700 border-emerald-300 font-semibold';
                        if (state === 'berjalan') colorClass = 'bg-blue-50 text-blue-700 border-blue-200 font-semibold';
                        if (state === 'terlambat') colorClass = 'bg-rose-50 text-rose-700 border-rose-300 font-bold';
                        if (state === 'dikembalikan') colorClass = 'bg-amber-50 text-amber-800 border-amber-300 font-bold';
                        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] border ${colorClass}">${data}</span>`;
                    }
                },
                {
                    data: 'sla',
                    orderable: false,
                    className: 'font-mono text-xs',
                    render: function(data, type, row) {
                        if (!data || data === '—') return '—';
                        if (row.stage_badge_state === 'terlambat' || String(data).includes('⚠️')) {
                            return `<span class="text-rose-600 font-bold">${data}</span>`;
                        }
                        return `<span class="text-foreground">${data}</span>`;
                    }
                },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });

        $('#filter-status-po, #filter-stage-po, #filter-kategori-supplier').on('change', function() {
            if (dtTable) dtTable.ajax.reload();
        });

        $('#btn-reset-po').on('click', function() {
            $('#filter-status-po').val('');
            $('#filter-stage-po').val('');
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
                            return `<span class="font-mono font-bold text-foreground">${invTitle}</span>${sub}`;
                        }
                    },
                    { data: 'supplier_nama', orderable: false },
                    { data: 'tanggal', className: 'font-mono' },
                    { data: 'skema_bayar' },
                    {
                        data: 'total_nilai',
                        className: 'text-right font-mono text-foreground',
                        render: function(data) { return `Rp ${data}`; }
                    },
                    {
                        data: 'total_dibayar',
                        className: 'text-right font-mono text-emerald-600 font-semibold',
                        render: function(data) { return `Rp ${data}`; }
                    },
                    {
                        data: 'sisa',
                        className: 'text-right font-mono font-bold',
                        render: function(data, type, row) {
                            if (data === '—' || data === null || data === undefined) return '—';
                            const statusNorm = (row.status_pembayaran || '').toLowerCase().trim();
                            if (statusNorm.includes('batal')) {
                                return `<span class="text-muted-foreground font-semibold font-mono">Rp 0</span>`;
                            }
                            const isLunas = data === '0' || statusNorm === 'lunas';
                            if (isLunas) {
                                return `<span class="text-emerald-600 font-semibold font-mono">Rp 0</span>`;
                            }
                            return `<span class="text-rose-600 font-bold font-mono">Rp ${data}</span>`;
                        }
                    },
                    {
                        data: 'jatuh_tempo_terdekat',
                        className: 'font-mono text-xs',
                        render: function(data, type, row) {
                            if (row.status_pembayaran === 'overdue') {
                                return `<span class="text-rose-600 font-bold inline-flex items-center gap-1">⚠️ ${data}</span>`;
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
                        return `<span class="font-mono font-bold text-amber-900">${invTitle}</span>${sub}`;
                    }
                },
                { data: 'supplier_nama', orderable: false },
                { data: 'gudang_nama', orderable: false },
                { data: 'tanggal', className: 'font-mono' },
                { data: 'eta', className: 'font-mono' },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        let label = row.stage_label || 'Menunggu Approval';
                        return `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">${label}</span>`;
                    }
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `<div class="flex items-center justify-center gap-1.5">
                            <a href="{{ url('purchasing') }}/${row.id}" class="inline-flex items-center px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-[11px] font-semibold transition shadow-2xs">
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
                $('#bodyLtSupplier').html('<tr><td colspan="6" class="px-4 py-8 text-center text-rose-500 font-semibold">Gagal memuat data lead time supplier.</td></tr>');
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
                <td class="px-4 py-3 font-semibold text-foreground">${s.supplier_nama}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-[10px] font-medium bg-muted text-muted-foreground uppercase">${s.kategori || '—'}</span></td>
                <td class="px-4 py-3 text-center font-mono font-bold">${s.jumlah_transaksi} PO</td>
                <td class="px-4 py-3 text-right font-mono font-semibold text-foreground">
                    ${s.average_lt_hari} hari
                    <span class="text-[10px] text-muted-foreground font-normal">(${s.average_lt_jam} jam)</span>
                </td>
                <td class="px-4 py-3 text-right font-mono font-bold text-amber-700">
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
                <td class="px-4 py-3 font-mono font-semibold text-primary">${p.sku}</td>
                <td class="px-4 py-3 font-medium text-foreground">${p.nama_produk}</td>
                <td class="px-4 py-3 text-center text-muted-foreground uppercase text-[10px]">${p.satuan || '—'}</td>
                <td class="px-4 py-3 text-center font-mono">${p.jumlah_transaksi} PO</td>
                <td class="px-4 py-3 text-right font-mono font-semibold text-foreground">
                    ${p.average_lt_hari} hari
                    <span class="text-[10px] text-muted-foreground font-normal">(${p.average_lt_jam} jam)</span>
                </td>
                <td class="px-4 py-3 text-right font-mono font-bold text-amber-700">${p.max_lt_hari} hari</td>
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
                ? '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Valid</span>'
                : `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200" title="${t.alasan_tidak_valid || ''}">Dibatalkan</span>`;

            let actionBtn = '—';
            if (canInvalidateLt && t.is_valid) {
                actionBtn = `<button type="button" onclick="openInvalidateLtModal('${t.po_id}', '${t.no_po}')" class="px-2 py-1 text-[11px] font-semibold text-destructive hover:bg-destructive/10 rounded transition cursor-pointer">
                    Invalidate
                </button>`;
            }

            html += `<tr class="hover:bg-muted/40 transition">
                <td class="px-3 py-2.5 font-mono font-bold text-foreground">
                    <a href="{{ url('purchasing') }}/${t.po_id}" class="hover:underline text-primary">${t.no_invoice && t.no_invoice !== '—' ? t.no_invoice : t.no_po}</a>
                    <span class="block text-[10px] text-muted-foreground font-normal">PO: ${t.no_po}</span>
                </td>
                <td class="px-3 py-2.5 text-foreground">${t.supplier_nama}</td>
                <td class="px-3 py-2.5 font-mono text-[11px] text-muted-foreground">${t.waktu_selesai}</td>
                <td class="px-3 py-2.5 text-right font-mono font-bold text-foreground">${t.total_lt_hari} hr <span class="text-[10px] font-normal text-muted-foreground">(${t.total_lt_jam} j)</span></td>
                <td class="px-3 py-2.5 text-right font-mono text-muted-foreground">${t.lt_internal_hari} hr</td>
                <td class="px-3 py-2.5 text-right font-mono text-muted-foreground">${t.lt_supplier_hari} hr</td>
                <td class="px-3 py-2.5 text-right font-mono text-muted-foreground">${t.lt_logistik_hari} hr</td>
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
                    <td class="px-3 py-2.5 text-center font-mono">${st.jumlah_transaksi} PO</td>
                    <td class="px-3 py-2.5 text-right font-mono font-semibold">${st.average_hari} hr</td>
                    <td class="px-3 py-2.5 text-right font-mono text-muted-foreground">${st.average_jam} j</td>
                    <td class="px-3 py-2.5 text-right font-mono font-bold text-amber-700">${st.max_hari} hr</td>
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
                    alert(data.message);
                }
            } else {
                alert(data.message || 'Gagal membatalkan validitas lead time.');
            }
        })
        .catch(err => {
            btn.prop('disabled', false).text('Batalkan Validitas');
            alert('Terjadi kesalahan koneksi.');
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
                    alert(data.message);
                }
            } else {
                alert(data.message || 'Gagal menyimpan perubahan.');
            }
        })
        .catch(err => {
            btn.prop('disabled', false).text('Simpan Perubahan');
            alert('Terjadi kesalahan koneksi.');
        });
    }
    </script>
    @endpush
</x-app-layout>