<x-app-layout title="Permintaan Barang (Request)">
    <div x-data="{
        openRejectModal: false,
        openCancelModal: false,
        actionUrl: '',
        targetNo: '',
        alasan: '',
        initReject(url, no) {
            this.actionUrl = url;
            this.targetNo = no;
            this.alasan = '';
            this.openRejectModal = true;
        },
        initCancel(url, no) {
            this.actionUrl = url;
            this.targetNo = no;
            this.alasan = '';
            this.openCancelModal = true;
        }
    }" @open-reject-modal.window="initReject($event.detail.action, $event.detail.no)" @open-cancel-modal.window="initCancel($event.detail.action, $event.detail.no)">
        <x-page-header
            title="Permintaan Barang (Request)"
            :breadcrumbs="['Permintaan' => null]"
        >
            <x-slot:actions>
                @can('request.create')
                <x-button href="{{ route('requests.create') }}" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Buat Request Baru
                </x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <div class="mb-3.5 flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-900 px-4 py-3 rounded-[12px] border border-[#E8E8E8] dark:border-border shadow-theme-xs">
            <div class="flex flex-wrap items-center gap-2.5">
                <select id="filterJenis" class="h-8 border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-xs py-1 px-2.5 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none cursor-pointer rounded-[6px]">
                    <option value="">Semua Jenis Permintaan</option>
                    <option value="request_bahan">Bahan Baku (Operasional)</option>
                    <option value="request_produk_jadi">Produk Jadi (Fulfillment)</option>
                </select>

                <select id="filterStatus" class="h-8 border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-xs py-1 px-2.5 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none cursor-pointer rounded-[6px]">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="diajukan">Diajukan</option>
                    <option value="disetujui">Disetujui</option>
                    <option value="diproses">Diproses</option>
                    <option value="selesai">Selesai</option>
                    <option value="ditolak">Ditolak</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
            </div>

            <div class="bg-[#f3f4f6] dark:bg-gray-950 dark:border dark:border-gray-800 p-[2px] gap-[2px] rounded-[8px] h-8 inline-flex items-center max-w-full overflow-x-auto no-scrollbar"
                 x-data="{ statusFilter: '' }">
                @foreach(['' => 'Semua', 'diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'selesai' => 'Selesai'] as $sVal => $sLabel)
                    <button type="button"
                            @click="statusFilter = '{{ $sVal }}'; $('#filterStatus').val('{{ $sVal }}').trigger('change');"
                            :class="statusFilter === '{{ $sVal }}' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-theme-xs font-medium' : 'bg-transparent text-text-muted border border-transparent hover:text-text-active font-medium'"
                            class="px-2.5 py-1 h-full text-xs whitespace-nowrap transition cursor-pointer rounded-[6px]">
                        {{ $sLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        <x-card title="Daftar Permintaan Barang" subtitle="Alur permintaan bahan baku & produk jadi lintas gudang" :noPadding="true">
            <x-slot:icon>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </x-slot:icon>

            <div class="overflow-x-auto">
                <table id="tblRequests" class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">No Request</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Jenis</th>
                            <th class="px-4 py-3 text-left">Gudang Pemohon</th>
                            <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                            <th class="px-4 py-3 text-left">Batch Produksi</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center w-52">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>

        {{-- Modal Tolak Request --}}
        <x-modal name="openRejectModal" title="Tolak Permintaan Barang" maxWidth="md" :noPadding="true">
            <form method="POST" :action="actionUrl">
                @csrf
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5 space-y-3">
                    <div class="text-xs text-text-muted">Dokumen: <span class="tabular-nums font-medium text-text-active" x-text="targetNo"></span></div>
                    <x-form-field label="Alasan Penolakan" name="alasan" :required="true" align="top">
                        <textarea name="alasan" x-model="alasan" required rows="3" class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0" placeholder="Jelaskan alasan penolakan permintaan..."></textarea>
                    </x-form-field>
                </div>
                <div class="bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border px-4 py-3 flex justify-end gap-2">
                    <x-button type="button" variant="secondary" size="sm" @click="openRejectModal = false">Batal</x-button>
                    <x-button type="submit" variant="danger" size="sm">Tolak Request</x-button>
                </div>
            </form>
        </x-modal>

        {{-- Modal Batalkan Request --}}
        <x-modal name="openCancelModal" title="Batalkan Permintaan" maxWidth="md" :noPadding="true">
            <form method="POST" :action="actionUrl">
                @csrf
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5 space-y-3">
                    <div class="text-xs text-text-muted">Dokumen: <span class="tabular-nums font-medium text-text-active" x-text="targetNo"></span></div>
                    <x-form-field label="Alasan (Opsional)" name="alasan" align="top">
                        <textarea name="alasan" x-model="alasan" rows="3" class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0" placeholder="Alasan pembatalan..."></textarea>
                    </x-form-field>
                </div>
                <div class="bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border px-4 py-3 flex justify-end gap-2">
                    <x-button type="button" variant="secondary" size="sm" @click="openCancelModal = false">Kembali</x-button>
                    <x-button type="submit" variant="danger" size="sm">Batalkan Request</x-button>
                </div>
            </form>
        </x-modal>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            const table = $('#tblRequests').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("requests.data") }}',
                    data: function(d) {
                        d.jenis = $('#filterJenis').val();
                        d.status = $('#filterStatus').val();
                    }
                },
                columns: [
                    { data: 'no_request', name: 'no_request', className: 'tabular-nums text-xs font-medium text-primary-600' },
                    { data: 'tanggal_request', name: 'created_at' },
                    { data: 'jenis', name: 'jenis' },
                    { data: 'pemohon', name: 'gudangPemohon.nama' },
                    { data: 'tujuan', name: 'gudangTujuan.nama' },
                    { data: 'batch_no', name: 'batch.no_batch', className: 'tabular-nums text-xs' },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center whitespace-nowrap' }
                ],
                order: [[0, 'desc']]
            });

            $('#filterJenis, #filterStatus').on('change', function() {
                table.draw();
            });

            $('#tblRequests').on('click', '.btn-reject-req', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                const no = $(this).data('no');
                window.dispatchEvent(new CustomEvent('open-reject-modal', { detail: { action: action, no: no } }));
            });

            $('#tblRequests').on('click', '.btn-cancel-req', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                const no = $(this).data('no');
                window.dispatchEvent(new CustomEvent('open-cancel-modal', { detail: { action: action, no: no } }));
            });
        });
    </script>
    @endpush
</x-app-layout>

