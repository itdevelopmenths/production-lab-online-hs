import Alpine from 'alpinejs';
import Swal from 'sweetalert2';

window.Alpine = Alpine;
window.Swal = Swal;

window.productSearchSelect = function(config) {
    return {
        open: false,
        search: '',
        page: 1,
        hasMore: false,
        total: 0,
        loading: false,
        items: [],
        name: config ? (config.name || '') : '',
        selectedId: config ? (config.selectedId || '') : '',
        selectedItem: config ? (config.selectedItem || null) : null,
        filterTipe: config ? (config.filterTipe || null) : null,
        supplierId: config ? (config.supplierId || null) : null,
        _lastFilterSignature: null,
        placeholder: config ? (config.placeholder || '— Pilih Produk —') : '— Pilih Produk —',
        url: config ? (config.url || '/produk/select-data') : '/produk/select-data',

        _resolveFilterTipe() {
            return (typeof this.filterTipe === 'function') ? this.filterTipe() : this.filterTipe;
        },

        _resolveSupplierId() {
            let sId = (typeof this.supplierId === 'function') ? this.supplierId() : this.supplierId;
            if (!sId) {
                const suppSelect = document.querySelector('select[name="supplier_id"]');
                if (suppSelect && suppSelect.value) {
                    sId = suppSelect.value;
                }
            }
            return sId || null;
        },

        _filterSignature() {
            return JSON.stringify({
                tipe: this._resolveFilterTipe() ?? null,
                supplier: this._resolveSupplierId(),
            });
        },

        async init() {
            if (this.selectedItem && !this.selectedId) {
                this.selectedId = this.selectedItem.id;
            }
            this._lastFilterSignature = this._filterSignature();
        },

        async toggle() {
            this.open = !this.open;
            if (this.open) {
                window.dispatchEvent(new CustomEvent('hs-dropdown-opened', { detail: this.name }));
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                });
                const currentSignature = this._filterSignature();
                if (this._lastFilterSignature !== currentSignature) {
                    this.items = [];
                    this.page = 1;
                    this._lastFilterSignature = currentSignature;
                }
                if (this.items.length === 0) {
                    await this.fetchItems(1, false);
                }
            } else {
                window.dispatchEvent(new CustomEvent('hs-dropdown-closed', { detail: this.name }));
            }
        },

        async fetchItems(page = 1, append = false) {
            this.loading = true;
            try {
                let baseUrl = this.url || '/produk/select-data';
                let q = (this.search || '').trim();
                let url = `${baseUrl}?page=${page}&q=${encodeURIComponent(q)}`;
                let fTipe = this._resolveFilterTipe();
                if (fTipe) {
                    if (Array.isArray(fTipe)) {
                        fTipe.forEach(t => url += `&tipe[]=${encodeURIComponent(t)}`);
                    } else {
                        url += `&tipe[]=${encodeURIComponent(fTipe)}`;
                    }
                }
                let sId = this._resolveSupplierId();
                if (sId) {
                    url += `&supplier_id=${encodeURIComponent(sId)}`;
                }
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('Network error fetching select-data');
                const data = await res.json();
                this.page = data.current_page || 1;
                this.hasMore = !!data.has_more;
                this.total = data.total || 0;
                if (append) {
                    this.items = [...this.items, ...(data.items || [])];
                } else {
                    this.items = data.items || [];
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },

        onSearch(event) {
            if (event && event.target) {
                this.search = event.target.value;
            }
            clearTimeout(this._debounce);
            this._debounce = setTimeout(() => {
                this.fetchItems(1, false);
            }, 200);
        },

        loadMore() {
            if (this.hasMore && !this.loading) {
                this.fetchItems(this.page + 1, true);
            }
        },

        select(item) {
            this.selectedId = item ? item.id : '';
            this.selectedItem = item || null;
            this.open = false;
            window.dispatchEvent(new CustomEvent('hs-dropdown-closed', { detail: this.name }));
            this.$dispatch('product-selected', item);
        },

        clear() {
            this.selectedId = '';
            this.selectedItem = null;
            this.search = '';
            this.open = false;
            window.dispatchEvent(new CustomEvent('hs-dropdown-closed', { detail: this.name }));
            this.$dispatch('product-selected', null);
            this.fetchItems(1, false);
        }
    };
};

Alpine.data('productSearchSelect', window.productSearchSelect);

Alpine.start();
