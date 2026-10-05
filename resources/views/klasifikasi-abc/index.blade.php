<x-app-layout title="Master Klasifikasi ABC">
    <div x-data="klasifikasiAbcPage()">
        <x-page-header
            title="Master Klasifikasi ABC & Buffer Hari"
            subtitle="Pengaturan bobot buffer lead time per klasifikasi produk impor & lokal (PRD v2.2)"
            :breadcrumbs="['Master Data' => null, 'Klasifikasi ABC' => null]"
        >
            <x-slot:actions>
                @can('analisa.manage')
                <button
                    type="button"
                    @click="openAddModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded bg-primary-600 text-white hover:bg-primary-700 shadow-sm transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah Klasifikasi Baru
                </button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-card title="Daftar Klasifikasi ABC" :noPadding="true">
            <div class="overflow-x-auto p-4">
                <table id="tbl-klasifikasi-abc" class="w-full text-xs stripe hover">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-semibold uppercase tracking-wider text-[11px]">
                            <th class="px-4 py-3 text-left">Kode</th>
                            <th class="px-4 py-3 text-left">Nama Klasifikasi</th>
                            <th class="px-4 py-3 text-left">Tambahan Buffer</th>
                            <th class="px-4 py-3 text-left">Deskripsi</th>
                            <th class="px-4 py-3 text-left">Penggunaan</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>

        <!-- Modal Form Tambah / Edit Klasifikasi ABC -->
        <div
            x-show="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-xs transition-opacity"
            x-cloak
            @keydown.escape.window="closeModal()"
        >
            <div
                class="bg-white rounded-lg shadow-xl border border-gray-200 w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150"
                @click.away="closeModal()"
            >
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <h3 class="text-sm font-bold text-gray-900" x-text="isEdit ? 'Edit Klasifikasi ABC' : 'Tambah Klasifikasi ABC'"></h3>
                    <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="saveItem()" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Kode Klasifikasi <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            x-model="form.kode"
                            placeholder="misal: a, b, c, wajib_a"
                            class="w-full px-3 py-2 text-xs rounded border border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono lowercase"
                            required
                        >
                        <p class="text-[11px] text-gray-500 mt-1">Gunakan huruf kecil atau underscore (contoh: wajib_a, a, b, c).</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Klasifikasi <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            x-model="form.nama"
                            placeholder="misal: A - Fast Moving"
                            class="w-full px-3 py-2 text-xs rounded border border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            required
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Tambahan Buffer (Hari) <span class="text-rose-500">*</span></label>
                            <input
                                type="number"
                                min="0"
                                max="365"
                                x-model.number="form.tambahan_buffer_hari"
                                class="w-full px-3 py-2 text-xs rounded border border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono"
                                required
                            >
                            <p class="text-[11px] text-gray-500 mt-1">Ditambahkan ke Buffer Days.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Warna Badge <span class="text-rose-500">*</span></label>
                            <select
                                x-model="form.warna_badge"
                                class="w-full px-3 py-2 text-xs rounded border border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="red">Merah (Wajib / Vital)</option>
                                <option value="amber">Amber (Fast Moving)</option>
                                <option value="blue">Biru (Medium)</option>
                                <option value="gray">Abu-abu (Slow Moving)</option>
                                <option value="emerald">Hijau</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Deskripsi</label>
                        <textarea
                            x-model="form.deskripsi"
                            rows="2"
                            placeholder="Keterangan kategori prioritas pengadaan..."
                            class="w-full px-3 py-2 text-xs rounded border border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            type="checkbox"
                            id="chk-active"
                            x-model="form.is_active"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        >
                        <label for="chk-active" class="text-xs font-medium text-gray-700">Aktif digunakan dalam analisa stok</label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeModal()"
                            class="px-3 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded transition disabled:opacity-50"
                        >
                            <svg x-show="saving" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span x-text="saving ? 'Menyimpan...' : 'Simpan Klasifikasi'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function klasifikasiAbcPage(){
        return {
            showModal: false,
            isEdit: false,
            saving: false,
            form: {
                id: null,
                kode: '',
                nama: '',
                tambahan_buffer_hari: 0,
                warna_badge: 'gray',
                deskripsi: '',
                is_active: true
            },
            openAddModal(){
                this.isEdit = false;
                this.form = {
                    id: null,
                    kode: '',
                    nama: '',
                    tambahan_buffer_hari: 0,
                    warna_badge: 'gray',
                    deskripsi: '',
                    is_active: true
                };
                this.showModal = true;
            },
            editItem(item){
                this.isEdit = true;
                this.form = {
                    id: item.id,
                    kode: item.kode,
                    nama: item.nama,
                    tambahan_buffer_hari: item.tambahan_buffer_hari,
                    warna_badge: item.warna_badge,
                    deskripsi: item.deskripsi || '',
                    is_active: Boolean(item.is_active)
                };
                this.showModal = true;
            },
            closeModal(){
                this.showModal = false;
            },
            async saveItem(){
                this.saving = true;
                try {
                    const url = this.isEdit
                        ? '{{ url("klasifikasi-abc") }}/' + this.form.id
                        : '{{ route("klasifikasi-abc.store") }}';
                    const method = this.isEdit ? 'PUT' : 'POST';

                    const res = await fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(this.form)
                    });
                    const d = await res.json();
                    if(res.ok && d.success){
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: d.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        this.closeModal();
                        $('#tbl-klasifikasi-abc').DataTable().ajax.reload(null, false);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan',
                            text: d.message || Object.values(d.errors || {})[0] || 'Periksa kembali data input.',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                } catch(e){
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Terjadi kesalahan saat menghubungi server.',
                        confirmButtonColor: '#dc2626'
                    });
                } finally {
                    this.saving = false;
                }
            }
        };
    }

    function hapusAbc(id, kode){
        Swal.fire({
            title: `Hapus klasifikasi '${kode}'?`,
            text: 'Klasifikasi yang digunakan pada analisa stok tidak dapat dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(r => {
            if(r.isConfirmed){
                fetch('{{ url("klasifikasi-abc") }}/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(async res => {
                    const d = await res.json();
                    if(res.ok && d.success){
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus',
                            text: d.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        $('#tbl-klasifikasi-abc').DataTable().ajax.reload(null, false);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menghapus',
                            text: d.message || 'Terjadi kesalahan.',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Gagal menghubungi server.',
                        confirmButtonColor: '#dc2626'
                    });
                });
            }
        });
    }

    $(function(){
        $('#tbl-klasifikasi-abc').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("klasifikasi-abc.data") }}',
            columns: [
                { data: 'badge', name: 'kode' },
                { data: 'nama', name: 'nama' },
                { data: 'tambahan_buffer_label', name: 'tambahan_buffer_hari', className: 'font-mono' },
                { data: 'deskripsi', name: 'deskripsi', defaultContent: '-' },
                { data: 'penggunaan_count', orderable: false, searchable: false },
                { data: 'status_badge', name: 'is_active', className: 'text-center' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ],
            order: [[2, 'desc']]
        });
    });
    </script>
    @endpush
</x-app-layout>
