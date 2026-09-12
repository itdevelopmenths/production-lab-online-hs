<x-app-layout title="Purchasing">
    @php
        $canSeePrice = $canSeePrice ?? auth()->user()->can('purchasing.price.view');
    @endphp

    <x-page-header
        title="Purchase Order"
        subtitle="Daftar pesanan pengadaan bahan baku, kemasan, estimasi kedatangan, dan buku tagihan pembayaran"
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

    <div x-data="{ activeTab: 'po' }">
        <!-- Tab Navigation (Requirement 8) -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6">
                <button
                    type="button"
                    @click="activeTab = 'po'"
                    :class="activeTab === 'po' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Daftar Purchase Order
                </button>

                @if($canSeePrice)
                <button
                    type="button"
                    @click="activeTab = 'ap'"
                    :class="activeTab === 'ap' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Buku Tagihan & Pembayaran (AP)
                </button>
                @endif
            </nav>
        </div>

        <!-- TAB 1: Daftar Purchase Order -->
        <div x-show="activeTab === 'po'" class="transition-opacity duration-150">
            <x-card title="Semua Dokumen Purchase Order" :noPadding="true">
                <div class="overflow-x-auto p-2">
                    <table id="tbl" class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase">
                                <th class="px-3 py-2.5 text-left">No PO / Invoice</th>
                                <th class="px-3 py-2.5 text-left">Supplier</th>
                                <th class="px-3 py-2.5 text-left">Lokasi Gudang</th>
                                <th class="px-3 py-2.5 text-left">Tgl PO</th>
                                <th class="px-3 py-2.5 text-left">ETA</th>
                                <th class="px-3 py-2.5 text-left">Tgl Tiba Fisik</th>
                                <th class="px-3 py-2.5 text-left">Status PO</th>
                                @if($canSeePrice)
                                    <th class="px-3 py-2.5 text-left">Status AP</th>
                                    <th class="px-3 py-2.5 text-right">Nilai Tagihan</th>
                                    <th class="px-3 py-2.5 text-right">Sisa Tagihan</th>
                                @endif
                                <th class="px-3 py-2.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </x-card>
        </div>

        <!-- TAB 2: Tagihan & Pembayaran AP (Requirement 8) -->
        @if($canSeePrice)
        <div x-show="activeTab === 'ap'" x-cloak class="transition-opacity duration-150">
            <x-card title="Buku Rekapitulasi Tagihan Vendor (Accounts Payable)" subtitle="Daftar status pelunasan dan jatuh tempo pembayaran ke supplier" :noPadding="true">
                <div class="overflow-x-auto p-2">
                    <table id="tblAp" class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase">
                                <th class="px-3 py-2.5 text-left">No PO</th>
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
    </div>

    <!-- Modal Quick-Edit Tanggal PO & ETA (Requirement 9) -->
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

    $(function(){
        const canSeePrice = {{ $canSeePrice ? 'true' : 'false' }};

        const columns = [
            {
                data: 'no_po',
                render: function(data, type, row) {
                    let inv = row.no_invoice && row.no_invoice !== '—' ? `<span class="block text-[10px] text-gray-400 font-normal">${row.no_invoice}</span>` : '';
                    return `<span class="font-mono font-bold text-gray-900">${data}</span>${inv}`;
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
                    let color = 'bg-gray-100 text-gray-800';
                    if (data.toLowerCase().includes('diajukan')) color = 'bg-amber-100 text-amber-800';
                    if (data.toLowerCase().includes('disetujui')) color = 'bg-primary-100 text-primary-800';
                    if (data.toLowerCase().includes('gudang')) color = 'bg-blue-100 text-blue-800';
                    if (data.toLowerCase().includes('selesai')) color = 'bg-emerald-100 text-emerald-800';
                    if (data.toLowerCase().includes('batal')) color = 'bg-rose-100 text-rose-800';
                    return `<span class="px-2 py-0.5 rounded text-[10px] font-semibold ${color}">${data}</span>`;
                }
            }
        ];

        if (canSeePrice) {
            columns.push(
                {
                    data: 'status_pembayaran',
                    render: function(data) {
                        if (!data || data === '—') return '—';
                        let color = 'bg-gray-100 text-gray-700';
                        if (data.toLowerCase().includes('lunas')) color = 'bg-emerald-100 text-emerald-800 font-bold';
                        if (data.toLowerCase().includes('parsial')) color = 'bg-blue-100 text-blue-800 font-bold';
                        if (data.toLowerCase().includes('overdue')) color = 'bg-rose-100 text-rose-800 font-bold';
                        return `<span class="px-1.5 py-0.5 rounded text-[10px] ${color}">${data}</span>`;
                    }
                },
                { data: 'total_nilai', className: 'text-right font-mono', searchable: false, orderable: false },
                { data: 'sisa', className: 'text-right font-mono font-semibold', searchable: false, orderable: false }
            );
        }

        columns.push({ data: 'action', orderable: false, searchable: false, className: 'text-center' });

        dtTable = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("purchasing.data") }}',
            columns: columns
        });

        if (canSeePrice && $('#tblAp').length) {
            dtApTable = $('#tblAp').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route("purchasing.data-ap") }}',
                columns: [
                    { data: 'no_po', className: 'font-mono font-bold' },
                    { data: 'no_invoice', className: 'font-mono text-gray-600' },
                    { data: 'supplier_nama', orderable: false },
                    { data: 'tanggal', className: 'font-mono' },
                    { data: 'skema_bayar' },
                    { data: 'total_nilai', className: 'text-right font-mono' },
                    { data: 'total_dibayar', className: 'text-right font-mono text-emerald-600' },
                    { data: 'sisa', className: 'text-right font-mono font-bold text-rose-600' },
                    { data: 'jatuh_tempo_terdekat', className: 'font-mono' },
                    {
                        data: 'status_pembayaran',
                        render: function(data) {
                            let color = 'bg-gray-100 text-gray-700';
                            if (data === 'lunas') color = 'bg-emerald-100 text-emerald-800 font-bold';
                            if (data === 'parsial') color = 'bg-blue-100 text-blue-800 font-bold';
                            if (data === 'overdue') color = 'bg-rose-100 text-rose-800 font-bold';
                            return `<span class="px-1.5 py-0.5 rounded text-[10px] capitalize ${color}">${data.replace('_',' ')}</span>`;
                        }
                    },
                    { data: 'action', orderable: false, searchable: false, className: 'text-center' }
                ]
            });
        }
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
