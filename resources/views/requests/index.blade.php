<x-app-layout title="Permintaan Barang (Request)" subtitle="Permintaan bahan baku operasional & produk jadi fulfillment">
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
    }">
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="text-lg font-semibold text-foreground tracking-tight leading-tight">Daftar Permintaan Barang (Request)</h2>
                    <p class="text-sm text-muted-foreground mt-0.5">Permintaan stok antar divisi: Bahan Baku Operasional Lab & Distribusi Fulfillment</p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <select id="filterJenis" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none cursor-pointer">
                        <option value="">Semua Jenis Permintaan</option>
                        <option value="request_bahan">Bahan Baku (Operasional)</option>
                        <option value="request_produk_jadi">Produk Jadi (Fulfillment)</option>
                    </select>

                    <select id="filterStatus" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none cursor-pointer">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="diajukan">Diajukan</option>
                        <option value="disetujui">Disetujui</option>
                        <option value="diproses">Diproses</option>
                        <option value="selesai">Selesai</option>
                        <option value="ditolak">Ditolak</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </select>

                    @can('rt.create')
                    <x-button href="{{ route('requests.create') }}" variant="primary">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </x-slot:icon>
                        Buat Request Baru
                    </x-button>
                    @endcan
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table id="tblRequests" class="w-full text-sm">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                            <th class="px-4 py-3 text-left">No Request</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Jenis</th>
                            <th class="px-4 py-3 text-left">Gudang Pemohon</th>
                            <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                            <th class="px-4 py-3 text-left">Batch Produksi</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>

        {{-- Modal Tolak Request --}}
        <x-modal name="openRejectModal" maxWidth="md">
            <x-slot:header>
                <div class="px-6 py-5 border-b border-border/60 bg-rose-50 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 border border-rose-200">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-foreground">Tolak Permintaan Barang</h3>
                            <p class="text-xs text-muted-foreground" x-text="targetNo"></p>
                        </div>
                    </div>
                </div>
            </x-slot:header>
            <form method="POST" :action="actionUrl" class="space-y-4 p-6">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-foreground mb-1">Alasan Penolakan <span class="text-destructive">*</span></label>
                    <textarea name="alasan" x-model="alasan" required rows="3" class="w-full text-xs rounded-lg border border-border p-2 focus:ring-2 focus:ring-primary/20" placeholder="Jelaskan alasan penolakan permintaan..."></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <x-button type="button" variant="outline" @click="openRejectModal = false">Batal</x-button>
                    <x-button type="submit" variant="danger">Tolak Request</x-button>
                </div>
            </form>
        </x-modal>

        {{-- Modal Batalkan Request --}}
        <x-modal name="openCancelModal" maxWidth="md">
            <x-slot:header>
                <div class="px-6 py-5 border-b border-border/60 bg-amber-50 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 border border-amber-200">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-foreground">Batalkan Permintaan</h3>
                            <p class="text-xs text-muted-foreground" x-text="targetNo"></p>
                        </div>
                    </div>
                </div>
            </x-slot:header>
            <form method="POST" :action="actionUrl" class="space-y-4 p-6">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-foreground mb-1">Alasan Pembatalan (Opsional)</label>
                    <textarea name="alasan" x-model="alasan" rows="3" class="w-full text-xs rounded-lg border border-border p-2 focus:ring-2 focus:ring-primary/20" placeholder="Alasan pembatalan..."></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <x-button type="button" variant="outline" @click="openCancelModal = false">Kembali</x-button>
                    <x-button type="submit" variant="danger">Batalkan Request</x-button>
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
                    { data: 'no_request', name: 'no_request', className: 'font-mono text-xs font-semibold text-primary-600' },
                    { data: 'tanggal_request', name: 'tanggal_request' },
                    { data: 'jenis', name: 'jenis' },
                    { data: 'pemohon', name: 'gudangPemohon.nama' },
                    { data: 'tujuan', name: 'gudangTujuan.nama' },
                    { data: 'batch_no', name: 'batch.no_batch', className: 'font-mono text-xs' },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
                ],
                order: [[0, 'desc']]
            });

            $('#filterJenis, #filterStatus').on('change', function() {
                table.draw();
            });
        });
    </script>
    @endpush
</x-app-layout>

