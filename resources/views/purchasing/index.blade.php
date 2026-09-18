<x-app-layout title="Purchasing">
    @php
        $user = auth()->user();
        $canSeePrice = $canSeePrice ?? $user->can('purchasing.price.view');
        $canApprove = $user->can('purchasing.approve');
        $canSeeApprovalTab = $canSeeApprovalTab ?? ($user->hasAnyRole(['manager', 'purchasing', 'admin_holding', 'super_admin']) || $canApprove);
        $pendingApprovalCount = $pendingApprovalCount ?? ($canSeeApprovalTab ? \App\Models\PurchaseOrder::where('status', 'diajukan')->count() : 0);

        // Setup default active tab
        $defaultTab = request('tab', 'po');
        if (! $canSeeApprovalTab && $defaultTab === 'approval') {
            $defaultTab = 'po';
        }
        if (! $canSeePrice && $defaultTab === 'ap') {
            $defaultTab = 'po';
        }
    @endphp

    <x-page-header
        title="Purchase Order"
        subtitle="Daftar pesanan pengadaan bahan baku, kemasan, estimasi kedatangan, buku tagihan, dan antrean persetujuan"
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

    <div x-data="{ activeTab: '{{ $defaultTab }}' }">
        {{-- Tab Navigation (PO, Buku Tagihan AP, Antrean Approval) --}}
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6">
                {{-- TAB 1: Daftar Purchase Order --}}
                <button
                    type="button"
                    @click="activeTab = 'po'"
                    :class="activeTab === 'po' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Daftar Purchase Order
                </button>

                {{-- TAB 2: Buku Tagihan & Pembayaran (AP) --}}
                @if($canSeePrice)
                <button
                    type="button"
                    @click="activeTab = 'ap'; $nextTick(() => { if (dtApTable) dtApTable.columns.adjust().draw(false); })"
                    :class="activeTab === 'ap' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Buku Tagihan (AP)
                </button>
                @endif

                {{-- TAB 3: Antrean Approval (Default: Manager & Purchasing) --}}
                @if($canSeeApprovalTab)
                <button
                    type="button"
                    @click="activeTab = 'approval'; $nextTick(() => { if (dtApprovalTable) dtApprovalTable.columns.adjust().draw(false); })"
                    :class="activeTab === 'approval' ? 'border-amber-600 text-amber-700 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Antrean Approval
                    @if($pendingApprovalCount > 0)
                        <span class="px-1.5 py-0.5 text-[10px] font-bold font-mono rounded-full bg-amber-100 text-amber-800 border border-amber-300">
                            {{ $pendingApprovalCount }}
                        </span>
                    @endif
                </button>
                @endif

                {{-- TAB 4: Riwayat Audit Purchasing --}}
                @canany(['purchasing.audit', 'audit.view'])
                <button
                    type="button"
                    @click="activeTab = 'audit'; $nextTick(() => { if (window.tblOperationalAuditPurchasing) window.tblOperationalAuditPurchasing.columns.adjust().draw(false); })"
                    :class="activeTab === 'audit' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Audit
                </button>
                @endcanany
            </nav>
        </div>

        <!-- TAB 1: Daftar Purchase Order -->
        <div x-show="activeTab === 'po'" class="transition-opacity duration-150">
            <!-- Toolbar Filter PO (Filter Status AP dipindah ke Tab Tagihan) -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3 rounded-sm border border-gray-200 mb-3 shadow-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <label for="filter-status-po" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Filter Status PO</label>
                        <select id="filter-status-po" class="rounded-sm border-gray-300 text-xs py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 min-w-[180px]">
                            <option value="">— Semua Status PO —</option>
                            <option value="draft">Draft</option>
                            <option value="diajukan">Diajukan (Menunggu Approval)</option>
                            <option value="disetujui">Disetujui (Gudang)</option>
                            <option value="dikirim_ke_gudang">Dikirim ke Gudang</option>
                            <option value="selesai">Selesai</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                </div>
                <div>
                    <button type="button" id="btn-reset-po" class="text-xs text-gray-500 hover:text-gray-800 underline font-medium cursor-pointer">
                        Reset Filter
                    </button>
                </div>
            </div>

            <x-card title="Semua Dokumen Purchase Order" :noPadding="true">
                <div class="overflow-x-auto p-2">
                    <table id="tbl" class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase">
                                <th class="px-3 py-2.5 text-left">No Invoice</th>
                                <th class="px-3 py-2.5 text-left">Supplier</th>
                                <th class="px-3 py-2.5 text-left">Lokasi</th>
                                <th class="px-3 py-2.5 text-left">Tgl PO</th>
                                <th class="px-3 py-2.5 text-left">ETA</th>
                                <th class="px-3 py-2.5 text-left">Tgl Tiba</th>
                                <th class="px-3 py-2.5 text-left">Status PO</th>
                                <th class="px-3 py-2.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </x-card>
        </div>

        <!-- TAB 2: Buku Tagihan & Pembayaran (AP) -->
        @if($canSeePrice)
        <div x-show="activeTab === 'ap'" x-cloak class="transition-opacity duration-150">
            <!-- Toolbar Filter AP (Filter status AP terpusat di sini) -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3 rounded-sm border border-gray-200 mb-3 shadow-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <label for="filter-status-bayar-ap" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Filter Status Bayar / AP</label>
                        <select id="filter-status-bayar-ap" class="rounded-sm border-gray-300 text-xs py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 min-w-[200px]">
                            <option value="">— Semua Status Bayar —</option>
                            <option value="overdue">🔴 Overdue (Jatuh Tempo - Prioritas)</option>
                            <option value="belum_lunas">🟡 Belum Lunas</option>
                            <option value="parsial">🔵 Parsial (Sebagian)</option>
                            <option value="lunas">🟢 Lunas</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-skema-bayar-ap" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Filter Skema Transaksi</label>
                        <select id="filter-skema-bayar-ap" class="rounded-sm border-gray-300 text-xs py-1.5 px-2.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 min-w-[160px]">
                            <option value="">— Semua Skema —</option>
                            <option value="cash">Tunai (Cash)</option>
                            <option value="tempo">Tempo</option>
                            <option value="termin">Termin</option>
                        </select>
                    </div>
                </div>
                <div>
                    <button type="button" id="btn-reset-ap" class="text-xs text-gray-500 hover:text-gray-800 underline font-medium cursor-pointer">
                        Reset Filter
                    </button>
                </div>
            </div>

            <x-card title="Buku Rekapitulasi Tagihan Vendor (Accounts Payable)" subtitle="Daftar status pelunasan, jadwal jatuh tempo, dan sisa hutang dagang supplier" :noPadding="true">
                <div class="overflow-x-auto p-2">
                    <table id="tblAp" class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase">
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
        <div x-show="activeTab === 'approval'" x-cloak class="transition-opacity duration-150">
            <div class="mb-3 p-3 bg-amber-50/80 border border-amber-200 rounded-sm text-xs flex items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center gap-2 text-amber-900">
                    <span class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>Tabel ini khusus menyaring dokumen PO dengan status <strong>Diajukan</strong> untuk mempercepat peninjauan & persetujuan Manager serta monitoring Purchasing.</span>
                </div>
                <div class="shrink-0 font-mono text-xs font-semibold text-amber-800">
                    Menunggu: <span class="px-2 py-0.5 bg-amber-200/80 rounded font-bold">{{ $pendingApprovalCount }}</span> dokumen
                </div>
            </div>

            <x-card title="Antrean Persetujuan Purchase Order" subtitle="Daftar pesanan pengadaan yang menunggu persetujuan otorisasi manager" :noPadding="true">
                <div class="overflow-x-auto p-2">
                    <table id="tblApproval" class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase">
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

        <!-- TAB 4: Riwayat Audit Purchasing -->
        @canany(['purchasing.audit', 'audit.view'])
        <div x-show="activeTab === 'audit'" x-cloak class="transition-opacity duration-150">
            <x-operational-audit-tab module="purchasing" title="Log Riwayat Audit Dokumen Purchasing" subtitle="Rekaman otomatis siklus PO, revisi nilai pengadaan, persetujuan manager, penerimaan fisik gudang, dan pembayaran" />
        </div>
        @endcanany
    </div>

    <!-- Modal Quick-Edit Tanggal PO & ETA -->
    <div id="quickDateModal" class="fixed inset-0 z-50 bg-black/40 hidden items-center justify-center p-4">
        <div class="bg-white rounded-sm border border-gray-200 shadow-xl max-w-sm w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold text-gray-900">Ubah Tanggal PO & ETA</h3>
                    <p class="text-[10px] text-gray-500" id="modalPoNumber">PO-XXXX</p>
                </div>
                <button type="button" onclick="closeQuickDateModal()" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
            </div>
            <form id="quickDateForm" onsubmit="submitQuickDate(event)" class="p-4 space-y-3 text-xs">
                <input type="hidden" id="editPoId">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Tanggal PO <span class="text-rose-500">*</span></label>
                    <input type="date" id="editTanggal" required class="w-full text-xs py-1.5 rounded-sm border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Estimasi Kedatangan (ETA)</label>
                    <input type="date" id="editEta" class="w-full text-xs py-1.5 rounded-sm border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
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

    $(function(){
        const canSeePrice = {{ $canSeePrice ? 'true' : 'false' }};

        function renderStatusApPriorityBadge(data) {
            if (!data || data === '—') return '—';
            let norm = data.toLowerCase().replace(/_/g, ' ');
            let badgeClass = 'bg-gray-100 text-gray-700 border border-gray-200';
            let dotClass = 'bg-gray-400';
            let label = data.replace(/_/g, ' ');

            if (norm.includes('overdue')) {
                badgeClass = 'bg-rose-50 text-rose-700 border border-rose-200 font-bold';
                dotClass = 'bg-rose-600 animate-pulse';
                label = 'Overdue';
            } else if (norm.includes('belum')) {
                badgeClass = 'bg-amber-50 text-amber-800 border border-amber-200 font-semibold';
                dotClass = 'bg-amber-500';
                label = 'Belum Lunas';
            } else if (norm.includes('parsial')) {
                badgeClass = 'bg-blue-50 text-blue-700 border border-blue-200 font-semibold';
                dotClass = 'bg-blue-500';
                label = 'Parsial';
            } else if (norm.includes('lunas')) {
                badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold';
                dotClass = 'bg-emerald-600';
                label = 'Lunas';
            }

            return `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-sm text-[11px] ${badgeClass}">
                <span class="w-1.5 h-1.5 rounded-full ${dotClass}"></span>
                ${label}
            </span>`;
        }

        function renderStatusPoBadge(data) {
            let color = 'bg-gray-100 text-gray-800';
            let norm = (data || '').toLowerCase();
            if (norm.includes('diajukan')) color = 'bg-amber-100 text-amber-800';
            if (norm.includes('disetujui')) color = 'bg-primary-100 text-primary-800';
            if (norm.includes('gudang')) color = 'bg-blue-100 text-blue-800';
            if (norm.includes('selesai')) color = 'bg-emerald-100 text-emerald-800';
            if (norm.includes('batal')) color = 'bg-rose-100 text-rose-800';
            return `<span class="px-2 py-0.5 rounded text-[10px] font-semibold ${color}">${data}</span>`;
        }

        // ==========================================
        // 1. DataTables: Daftar Purchase Order (8 Kolom)
        // No Invoice | Supplier | Lokasi | Tgl PO | ETA | Tgl Tiba | Status PO | Aksi
        // ==========================================
        dtTable = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("purchasing.data") }}',
                data: function(d) {
                    d.status_po = $('#filter-status-po').val();
                }
            },
            columns: [
                {
                    data: 'no_invoice',
                    render: function(data, type, row) {
                        let invTitle = data && data !== '—' ? data : row.no_po;
                        let sub = row.no_po && row.no_po !== invTitle ? `<span class="block text-[10px] text-gray-400 font-normal">PO: ${row.no_po}</span>` : '';
                        return `<span class="font-mono font-bold text-gray-900">${invTitle}</span>${sub}`;
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
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });

        $('#filter-status-po').on('change', function() {
            if (dtTable) dtTable.ajax.reload();
        });

        $('#btn-reset-po').on('click', function() {
            $('#filter-status-po').val('');
            if (dtTable) dtTable.ajax.reload();
        });

        // ==========================================
        // 2. DataTables: Buku Tagihan AP (10 Kolom)
        // No Invoice | Supplier | Tgl PO | Skema | Total Tagihan | Telah Dibayar | Sisa Tagihan | Jatuh Tempo | Status Bayar | Aksi
        // ==========================================
        if (canSeePrice && $('#tblAp').length) {
            dtApTable = $('#tblAp').DataTable({
                processing: true,
                serverSide: true,
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
                            let sub = row.no_po && row.no_po !== invTitle ? `<span class="block text-[10px] text-gray-400 font-normal">PO: ${row.no_po}</span>` : '';
                            return `<span class="font-mono font-bold text-gray-900">${invTitle}</span>${sub}`;
                        }
                    },
                    { data: 'supplier_nama', orderable: false },
                    { data: 'tanggal', className: 'font-mono' },
                    { data: 'skema_bayar' },
                    {
                        data: 'total_nilai',
                        className: 'text-right font-mono text-gray-800',
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
                            const isLunas = data === '0' || (row.status_pembayaran && row.status_pembayaran.toLowerCase().trim() === 'lunas');
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
                            return `<span class="text-gray-700">${data}</span>`;
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
        // Menyaring data PO dengan status 'diajukan'
        // ==========================================
        dtApprovalTable = $('#tblApproval').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("purchasing.data") }}',
                data: function(d) {
                    d.status_po = 'diajukan';
                }
            },
            columns: [
                {
                    data: 'no_invoice',
                    render: function(data, type, row) {
                        let invTitle = data && data !== '—' ? data : row.no_po;
                        let sub = row.no_po && row.no_po !== invTitle ? `<span class="block text-[10px] text-gray-400 font-normal">PO: ${row.no_po}</span>` : '';
                        return `<span class="font-mono font-bold text-amber-900">${invTitle}</span>${sub}`;
                    }
                },
                { data: 'supplier_nama', orderable: false },
                { data: 'gudang_nama', orderable: false },
                { data: 'tanggal', className: 'font-mono' },
                { data: 'eta', className: 'font-mono' },
                {
                    data: 'status',
                    render: function() {
                        return '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">Menunggu Approval</span>';
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
