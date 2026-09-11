import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Swal = Swal;
window.Chart = Chart;

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
        placeholder: config ? (config.placeholder || '— Pilih Produk —') : '— Pilih Produk —',
        url: config ? (config.url || '/produk/select-data') : '/produk/select-data',

        async init() {
            if (this.selectedItem && !this.selectedId) {
                this.selectedId = this.selectedItem.id;
            }
            if (this.selectedItem) {
                this.$dispatch('product-selected', this.selectedItem);
            }
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
                let url = `${baseUrl}?page=${page}&q=${encodeURIComponent(this.search)}`;
                if (this.filterTipe) {
                    if (Array.isArray(this.filterTipe)) {
                        this.filterTipe.forEach(t => url += `&tipe[]=${encodeURIComponent(t)}`);
                    } else {
                        url += `&tipe[]=${encodeURIComponent(this.filterTipe)}`;
                    }
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

        onSearch() {
            clearTimeout(this._debounce);
            this._debounce = setTimeout(() => {
                this.fetchItems(1, false);
            }, 250);
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
