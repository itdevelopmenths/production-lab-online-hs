<x-app-layout title="Request & Transfer" subtitle="Logistik mutasi internal, permintaan bahan baku, dan pengiriman produk jadi">
    <div x-data="{
        openCancelModal: false,
        cancelAction: '',
        cancelNo: '',
        alasan: '',
        isSubmitting: false,
        initCancel(action, no) {
            this.cancelAction = action;
            this.cancelNo = no;
            this.alasan = '';
            this.isSubmitting = false;
            this.openCancelModal = true;
        }
    }" @open-cancel-modal.window="initCancel($event.detail.action, $event.detail.no)">
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="text-lg font-semibold text-foreground tracking-tight leading-tight">Daftar Dokumen Mutasi & Transfer</h2>
                    <p class="text-sm text-muted-foreground mt-0.5">Logistik mutasi internal, permintaan bahan baku, dan distribusi antar gudang</p>
                </div>
                <div class="flex items-center gap-2.5">
                    <select id="jenisFilter" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none cursor-pointer">
                        <option value="">Semua Jenis Mutasi</option>
                        <option value="req_bahan">Request Bahan</option>
                        <option value="retur_bahan">Retur Bahan</option>
                        <option value="kirim_produk_jadi">Kirim Produk Jadi</option>
                        <option value="antar_fulfillment">Antar Fulfillment</option>
                        <option value="retur_produk_jadi">Retur Produk Jadi</option>
                    </select>

                    @can('rt.create')
                    <x-button href="{{ route('rt.create') }}" variant="primary">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </x-slot:icon>
                        Buat Transfer Baru
                    </x-button>
                    @endcan
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table id="tbl" class="w-full text-sm">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                            <th class="px-4 py-3 text-left">No Transaksi</th>
                            <th class="px-4 py-3 text-left">Jenis</th>
                            <th class="px-4 py-3 text-left">Gudang Asal</th>
                            <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>

        {{-- Modal Pembatalan Mutasi & Transfer --}}
        <x-modal name="openCancelModal" maxWidth="md">
            <x-slot:header>
                <div class="px-6 py-5 border-b border-border/60 bg-destructive/5 flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-10 rounded-xl bg-destructive/10 text-destructive flex items-center justify-center shrink-0 border border-destructive/20">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-foreground tracking-tight">Batalkan Dokumen Mutasi</h3>
                            <p class="text-xs text-muted-foreground truncate" x-text="cancelNo"></p>
                        </div>
                    </div>
                    <button type="button" @click="openCancelModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent shrink-0">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </x-slot:header>

            <form method="POST" :action="cancelAction" @submit="if(alasan.trim().length >= 5) { isSubmitting = true } else { $event.preventDefault() }" class="space-y-4">
                @csrf
                <input type="hidden" name="aksi" value="cancel">

                <div class="p-3.5 bg-destructive/10 border border-destructive/20 rounded-xl text-xs text-destructive space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Peringatan Pembatalan Dokumen
                    </div>
                    <p class="text-destructive/90 leading-relaxed text-[11px]">
                        Setelah dibatalkan, status dokumen transfer akan menjadi <strong>Dibatalkan</strong>. Pengeluaran dan penerimaan fisik barang tidak dapat dilanjutkan.
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="alasan_pembatalan_index" class="text-xs font-semibold text-foreground">
                            Alasan Pembatalan <span class="text-destructive">*</span>
                        </label>
                        <span class="text-[10px] text-muted-foreground">Wajib diisi</span>
                    </div>
                    <textarea
                        id="alasan_pembatalan_index"
                        name="alasan"
                        x-model="alasan"
                        rows="3"
                        required
                        minlength="5"
                        maxlength="500"
                        placeholder="Tuliskan alasan pembatalan dokumen mutasi ini (minimal 5 karakter)..."
                        class="w-full rounded-xl border border-border bg-card p-3 text-xs text-foreground placeholder:text-muted-foreground/50 focus:border-destructive focus:ring-2 focus:ring-destructive/20 outline-none transition"
                    ></textarea>
                    <div class="flex items-center justify-between mt-1 text-[11px]">
                        <span class="text-muted-foreground">Minimal 5 karakter</span>
                        <span :class="alasan.trim().length >= 5 ? 'text-emerald-600 font-semibold' : 'text-muted-foreground'" x-text="alasan.trim().length + ' / 500 karakter'"></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-border/60 flex items-center justify-end gap-2.5">
                    <x-button type="button" variant="secondary" size="sm" @click="openCancelModal = false">
                        Kembali
                    </x-button>
                    <x-button type="submit" variant="danger" size="sm" ::disabled="isSubmitting || alasan.trim().length < 5" ::class="{ 'opacity-50 cursor-not-allowed': alasan.trim().length < 5, 'opacity-75 cursor-not-allowed pointer-events-none': isSubmitting }">
                        <span x-show="!isSubmitting">Konfirmasi Batalkan Dokumen</span>
                        <span x-show="isSubmitting" x-cloak class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Membatalkan...
                        </span>
                    </x-button>
                </div>
            </form>
        </x-modal>
    </div>

    @push('scripts')
    <script>
    $(function(){
        var t = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            order: [[5, 'desc']],
            ajax: {
                url: '{{ route("rt.data") }}',
                data: function(d) {
                    d.jenis = $('#jenisFilter').val();
                }
            },
            columns: [
                { data: 'no_transaksi', className: 'px-4 py-3.5 font-mono text-xs font-semibold text-foreground' },
                { data: 'jenis', className: 'px-4 py-3.5 font-medium text-foreground' },
                { data: 'asal', orderable: false, searchable: false, className: 'px-4 py-3.5 text-xs text-muted-foreground' },
                { data: 'tujuan', orderable: false, searchable: false, className: 'px-4 py-3.5 text-xs text-muted-foreground' },
                { data: 'status', className: 'px-4 py-3.5 text-center' },
                { data: 'created_at', className: 'px-4 py-3.5 text-xs text-muted-foreground' },
                { data: 'action', orderable: false, searchable: false, className: 'px-4 py-3.5 text-center whitespace-nowrap' }
            ]
        });
        $('#jenisFilter').on('change', () => t.ajax.reload());

        $('#tbl').on('click', '.btn-cancel-rt', function(e) {
            e.preventDefault();
            var action = $(this).data('action');
            var no = $(this).data('no');
            window.dispatchEvent(new CustomEvent('open-cancel-modal', { detail: { action: action, no: no } }));
        });
    });
    </script>
    @endpush
</x-app-layout>
