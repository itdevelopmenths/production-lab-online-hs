<x-app-layout title="Master Klasifikasi ABC">
    <div x-data="klasifikasiAbcPage()">
        <x-page-header
            title="Master Klasifikasi ABC & Buffer Hari"
            subtitle="Pengaturan bobot buffer lead time per klasifikasi produk impor & lokal (PRD v2.2)"
            :breadcrumbs="['Master Data' => null, 'Klasifikasi ABC' => null]"
        >
            <x-slot:actions>
                @can('analisa.manage')
                <x-button type="button" @click="openAddModal()" variant="primary" size="sm">
                    <x-slot:icon>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Tambah Klasifikasi
                </x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-card title="Daftar Klasifikasi ABC" subtitle="Pengaturan buffer lead time dan bobot prioritas pengadaan" :noPadding="true">
            <div class="overflow-x-auto">
                <table id="tbl-klasifikasi-abc" class="w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Kode</th>
                            <th class="px-4 py-3 text-left">Nama Klasifikasi</th>
                            <th class="px-4 py-3 text-left">Tambahan Buffer</th>
                            <th class="px-4 py-3 text-left">Deskripsi</th>
                            <th class="px-4 py-3 text-left">Penggunaan</th>
                            <th class="px-4 py-3 text-center w-28">Status</th>
                            <th class="px-4 py-3 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>

        <!-- Modal Kelola SKU Klasifikasi ABC -->
        <div
            x-show="showSkuModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-xs transition-opacity"
            x-cloak
            @keydown.escape.window="closeSkuModal()"
        >
            <div
                class="bg-card rounded-xl shadow-lg border border-border w-full max-w-3xl overflow-hidden flex flex-col max-h-[88vh]"
                @click.away="closeSkuModal()"
            >
                <div class="px-5 py-4 border-b border-border flex items-center justify-between shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-foreground">Kelola SKU Klasifikasi ABC</h3>
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-primary/10 text-primary border border-primary/20" x-text="activeAbc ? activeAbc.nama : ''"></span>
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Pilih atau keluarkan SKU Bahan Baku / Kemasan pada klasifikasi ini.
                        </p>
                    </div>
                    <button type="button" @click="closeSkuModal()" class="text-muted-foreground hover:text-foreground transition cursor-pointer">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5 space-y-5 overflow-y-auto">
                    @can('analisa.manage')
                    <!-- Bagian 1: Tambah SKU -->
                    <div class="border border-border rounded-xl p-4 bg-muted/20 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider">Tambah SKU ke Klasifikasi Ini</h4>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    x-model.debounce.250ms="skuSearch"
                                    @input="fetchAvailableSkus()"
                                    placeholder="Cari kode SKU atau nama..."
                                    class="px-3 py-1.5 text-xs rounded-lg border border-border bg-background text-foreground w-48 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                >
                                <select
                                    x-model="skuProfilFilter"
                                    @change="fetchAvailableSkus()"
                                    class="px-2.5 py-1.5 text-xs rounded-lg border border-border bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="">Semua Profil</option>
                                    <option value="impor">Hanya Impor</option>
                                    <option value="unset">Belum Diset</option>
                                    <option value="lokal">Hanya Lokal</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="hasNonImporSelected()" class="p-2.5 rounded-lg border border-amber-300 bg-amber-50 text-amber-800 text-[11px]">
                            Perhatian: SKU berprofil <strong>Lokal</strong> atau <strong>Belum Diset</strong> yang dipilih akan otomatis dipindahkan ke profil <strong>Impor</strong> (dan dihapus dari daftar Analisa Lokal agar tidak tampil ganda).
                        </div>

                        <div class="max-h-48 overflow-y-auto border border-border rounded-lg bg-background divide-y divide-border/60">
                            <template x-if="loadingAvailableSkus">
                                <div class="py-6 text-center text-xs text-muted-foreground">Memuat daftar kandidat SKU...</div>
                            </template>
                            <template x-if="!loadingAvailableSkus && availableSkus.length === 0">
                                <div class="py-6 text-center text-xs text-muted-foreground">Tidak ada kandidat SKU yang sesuai pencarian.</div>
                            </template>
                            <template x-for="cand in availableSkus" :key="cand.id">
                                <label class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-muted/40 cursor-pointer text-xs">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <input type="checkbox" :value="cand.id" x-model="selectedSkuIds" class="rounded border-border text-primary focus:ring-primary/20 cursor-pointer">
                                        <span class="font-mono font-semibold text-foreground shrink-0" x-text="cand.sku"></span>
                                        <span class="text-foreground truncate" x-text="cand.nama"></span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border"
                                              :class="cand.is_impor ? 'bg-purple-50 text-purple-700 border-purple-200' : (cand.profil_analisa === 'lokal' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-zinc-100 text-zinc-600 border-zinc-200')"
                                              x-text="cand.is_impor ? 'Impor' : (cand.profil_analisa === 'lokal' ? 'Lokal' : 'Belum Diset')"></span>
                                        <template x-if="cand.klasifikasi_abc_kode">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-muted text-foreground border border-border" x-text="'Saat ini: ' + cand.klasifikasi_abc_kode"></span>
                                        </template>
                                    </div>
                                </label>
                            </template>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[11px] text-muted-foreground">
                                Terpilih: <strong class="text-foreground" x-text="selectedSkuIds.length"></strong> SKU
                            </span>
                            <button
                                type="button"
                                @click="assignSelectedSkus()"
                                :disabled="selectedSkuIds.length === 0 || assigningSkus"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-primary-foreground bg-primary hover:bg-primary/90 rounded-lg shadow-xs transition disabled:opacity-50 cursor-pointer"
                            >
                                <span x-text="assigningSkus ? 'Menyimpan...' : 'Tambahkan SKU Terpilih (' + selectedSkuIds.length + ')'"></span>
                            </button>
                        </div>
                    </div>
                    @endcan

                    <!-- Bagian 2: Daftar SKU Terdaftar -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider">
                                Daftar SKU pada Klasifikasi Ini (<span x-text="assignedSkus.length"></span>)
                            </h4>
                        </div>
                        <div class="overflow-x-auto border border-border rounded-lg">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-muted/60 text-muted-foreground text-[11px] uppercase border-b border-border">
                                    <tr>
                                        <th class="px-3 py-2">SKU</th>
                                        <th class="px-3 py-2">Nama Produk</th>
                                        <th class="px-3 py-2">Tipe</th>
                                        <th class="px-3 py-2">Profil</th>
                                        @can('analisa.manage')
                                        <th class="px-3 py-2 text-center w-24">Aksi</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60 bg-card">
                                    <template x-if="loadingAssignedSkus">
                                        <tr><td colspan="5" class="py-6 text-center text-muted-foreground">Memuat daftar SKU...</td></tr>
                                    </template>
                                    <template x-if="!loadingAssignedSkus && assignedSkus.length === 0">
                                        <tr><td colspan="5" class="py-6 text-center text-muted-foreground">Belum ada SKU yang ditetapkan ke klasifikasi ini.</td></tr>
                                    </template>
                                    <template x-for="skuItem in assignedSkus" :key="skuItem.id">
                                        <tr class="hover:bg-muted/30">
                                            <td class="px-3 py-2 font-mono font-semibold text-foreground" x-text="skuItem.sku"></td>
                                            <td class="px-3 py-2 text-foreground" x-text="skuItem.nama"></td>
                                            <td class="px-3 py-2 uppercase text-[10px] font-semibold text-muted-foreground" x-text="skuItem.tipe"></td>
                                            <td class="px-3 py-2 uppercase text-[10px] font-semibold" x-text="skuItem.profil_analisa || 'impor'"></td>
                                            @can('analisa.manage')
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" @click="unassignSku(skuItem)" class="px-2 py-1 rounded text-[11px] font-medium text-destructive hover:bg-destructive/10 transition cursor-pointer">
                                                    Keluarkan
                                                </button>
                                            </td>
                                            @endcan
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Form Tambah / Edit Klasifikasi ABC -->
        <div
            x-show="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-xs transition-opacity"
            x-cloak
            @keydown.escape.window="closeModal()"
        >
            <div
                class="bg-card rounded-xl shadow-lg border border-border w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150"
                @click.away="closeModal()"
            >
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-foreground" x-text="isEdit ? 'Edit Klasifikasi ABC' : 'Tambah Klasifikasi ABC'"></h3>
                    <button type="button" @click="closeModal()" class="text-muted-foreground hover:text-foreground transition cursor-pointer">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="saveItem()" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Kode Klasifikasi <span class="text-destructive">*</span></label>
                        <input
                            type="text"
                            x-model="form.kode"
                            placeholder="misal: a, b, c, wajib_a"
                            class="w-full px-3 py-2 text-xs rounded-lg border border-border bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 font-mono lowercase text-foreground"
                            required
                        >
                        <p class="text-[11px] text-muted-foreground mt-1">Gunakan huruf kecil atau underscore (contoh: wajib_a, a, b, c).</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Nama Klasifikasi <span class="text-destructive">*</span></label>
                        <input
                            type="text"
                            x-model="form.nama"
                            placeholder="misal: A - Fast Moving"
                            class="w-full px-3 py-2 text-xs rounded-lg border border-border bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 text-foreground"
                            required
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Tambahan Buffer (Hari) <span class="text-destructive">*</span></label>
                            <input
                                type="number"
                                min="0"
                                max="365"
                                x-model.number="form.tambahan_buffer_hari"
                                class="w-full px-3 py-2 text-xs rounded-lg border border-border bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 font-mono text-foreground"
                                required
                            >
                            <p class="text-[11px] text-muted-foreground mt-1">Ditambahkan ke Buffer Days.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Warna Badge <span class="text-destructive">*</span></label>
                            <select
                                x-model="form.warna_badge"
                                class="w-full px-3 py-2 text-xs rounded-lg border border-border bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 text-foreground"
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
                        <label class="block text-xs font-semibold text-foreground mb-1">Deskripsi</label>
                        <textarea
                            x-model="form.deskripsi"
                            rows="2"
                            placeholder="Keterangan kategori prioritas pengadaan..."
                            class="w-full px-3 py-2 text-xs rounded-lg border border-border bg-background focus:outline-none focus:ring-2 focus:ring-primary/20 text-foreground"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            type="checkbox"
                            id="chk-active"
                            x-model="form.is_active"
                            class="rounded border-border text-primary focus:ring-primary/20"
                        >
                        <label for="chk-active" class="text-xs font-medium text-foreground cursor-pointer">Aktif digunakan dalam analisa stok</label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                        <button
                            type="button"
                            @click="closeModal()"
                            class="px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted border border-border rounded-lg transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold text-primary-foreground bg-primary hover:bg-primary/90 rounded-lg shadow-xs transition disabled:opacity-50 cursor-pointer"
                        >
                            <svg x-show="saving" class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-primary-foreground" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
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
            showSkuModal: false,
            activeAbc: null,
            assignedSkus: [],
            availableSkus: [],
            selectedSkuIds: [],
            skuSearch: '',
            skuProfilFilter: '',
            loadingAssignedSkus: false,
            loadingAvailableSkus: false,
            assigningSkus: false,
            form: {
                id: null,
                kode: '',
                nama: '',
                tambahan_buffer_hari: 0,
                warna_badge: 'gray',
                deskripsi: '',
                is_active: true
            },
            async openSkuModal(item){
                this.activeAbc = item;
                this.selectedSkuIds = [];
                this.skuSearch = '';
                this.skuProfilFilter = '';
                this.showSkuModal = true;
                await Promise.all([this.fetchAssignedSkus(), this.fetchAvailableSkus()]);
            },
            closeSkuModal(){
                this.showSkuModal = false;
            },
            hasNonImporSelected(){
                const selectedSet = new Set(this.selectedSkuIds.map(id => Number(id)));
                return this.availableSkus.some(c => selectedSet.has(Number(c.id)) && !c.is_impor);
            },
            async fetchAssignedSkus(){
                if (!this.activeAbc) return;
                this.loadingAssignedSkus = true;
                try {
                    const res = await fetch('{{ url("klasifikasi-abc") }}/' + this.activeAbc.id + '/skus', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const d = await res.json();
                    this.assignedSkus = d.data || [];
                } catch (e) {
                    this.assignedSkus = [];
                } finally {
                    this.loadingAssignedSkus = false;
                }
            },
            async fetchAvailableSkus(){
                if (!this.activeAbc) return;
                this.loadingAvailableSkus = true;
                try {
                    const params = new URLSearchParams({
                        exclude_klasifikasi_id: this.activeAbc.id
                    });
                    if (this.skuSearch.trim()) params.set('q', this.skuSearch.trim());
                    if (this.skuProfilFilter) params.set('profil', this.skuProfilFilter);

                    const res = await fetch('{{ route("klasifikasi-abc.available-skus") }}?' + params.toString(), {
                        headers: { 'Accept': 'application/json' }
                    });
                    const d = await res.json();
                    this.availableSkus = d.data || [];
                } catch (e) {
                    this.availableSkus = [];
                } finally {
                    this.loadingAvailableSkus = false;
                }
            },
            async assignSelectedSkus(){
                if (!this.activeAbc || this.selectedSkuIds.length === 0) return;
                this.assigningSkus = true;
                try {
                    const res = await fetch('{{ url("klasifikasi-abc") }}/' + this.activeAbc.id + '/skus', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            produk_ids: this.selectedSkuIds.map(Number)
                        })
                    });
                    const d = await res.json();
                    if (res.ok && d.success) {
                        this.selectedSkuIds = [];
                        await Promise.all([this.fetchAssignedSkus(), this.fetchAvailableSkus()]);
                        $('#tbl-klasifikasi-abc').DataTable().ajax.reload(null, false);
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: d.message, timer: 1500, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: d.message || 'Gagal menetapkan SKU.', confirmButtonColor: '#18181b' });
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Kesalahan Sistem', text: 'Gagal menghubungi server.', confirmButtonColor: '#18181b' });
                } finally {
                    this.assigningSkus = false;
                }
            },
            async unassignSku(skuItem){
                if (!this.activeAbc || !skuItem) return;
                try {
                    const res = await fetch('{{ url("klasifikasi-abc") }}/' + this.activeAbc.id + '/skus/' + skuItem.id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const d = await res.json();
                    if (res.ok && d.success) {
                        await Promise.all([this.fetchAssignedSkus(), this.fetchAvailableSkus()]);
                        $('#tbl-klasifikasi-abc').DataTable().ajax.reload(null, false);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: d.message || 'Gagal melepas SKU.', confirmButtonColor: '#18181b' });
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Kesalahan Sistem', text: 'Gagal menghubungi server.', confirmButtonColor: '#18181b' });
                }
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
                            confirmButtonColor: '#18181b'
                        });
                    }
                } catch(e){
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Terjadi kesalahan saat menghubungi server.',
                        confirmButtonColor: '#18181b'
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
            confirmButtonColor: '#18181b',
            cancelButtonColor: '#71717a',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'rounded-xl border border-border shadow-lg',
                confirmButton: 'rounded-lg text-xs font-medium px-4 py-2',
                cancelButton: 'rounded-lg text-xs font-medium px-4 py-2'
            }
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
                            confirmButtonColor: '#18181b'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Gagal menghubungi server.',
                        confirmButtonColor: '#18181b'
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
