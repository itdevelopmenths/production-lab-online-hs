import Alpine from 'alpinejs';
import Swal from 'sweetalert2';

window.Alpine = Alpine;
window.Swal = Swal;

window.hsTheme = {
    current() {
        try {
            const stored = localStorage.getItem('hs.theme');
            if (stored === 'dark' || stored === 'light') {
                return stored;
            }
        } catch (e) {}

        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    },

    isDark() {
        return this.current() === 'dark' || document.documentElement.classList.contains('dark');
    },

    set(mode) {
        const next = mode === 'dark' ? 'dark' : 'light';
        const isDark = next === 'dark';

        try {
            localStorage.setItem('hs.theme', next);
        } catch (e) {}

        document.documentElement.classList.toggle('dark', isDark);
        document.documentElement.style.colorScheme = next;

        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#030712' : '#F9FAFB');
        }

        window.dispatchEvent(new CustomEvent('hs-theme-changed', { detail: { theme: next } }));
        return next;
    },

    toggle() {
        return this.set(this.isDark() ? 'light' : 'dark');
    },
};

window.productSearchSelect = function(config) {
    return {
        open: false,
        search: '',
        page: 1,
        hasMore: false,
        total: 0,
        loading: false,
        items: [],
        panelStyle: 'position: fixed; z-index: 9999;',
        _boundReposition: null,
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
            this._boundReposition = () => {
                if (this.open) {
                    this.updatePosition();
                }
            };
            this.$watch('open', (isOpen) => {
                if (isOpen) {
                    this._bindListeners();
                    this.$nextTick(() => this.updatePosition());
                } else {
                    this._unbindListeners();
                }
            });
        },

        _bindListeners() {
            if (!this._boundReposition) return;
            window.addEventListener('scroll', this._boundReposition, true);
            window.addEventListener('resize', this._boundReposition);
        },

        _unbindListeners() {
            if (!this._boundReposition) return;
            window.removeEventListener('scroll', this._boundReposition, true);
            window.removeEventListener('resize', this._boundReposition);
        },

        updatePosition() {
            const trigger = this.$refs && this.$refs.trigger ? this.$refs.trigger : null;
            if (!trigger) return;
            const rect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            const width = Math.min(Math.max(rect.width, 280), window.innerWidth - 16);
            const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));

            if (spaceBelow < 280 && spaceAbove > spaceBelow) {
                const bottom = Math.max(8, window.innerHeight - rect.top + 6);
                this.panelStyle = `position: fixed; left: ${left}px; bottom: ${bottom}px; width: ${width}px; z-index: 9999;`;
            } else {
                const top = Math.max(8, rect.bottom + 6);
                this.panelStyle = `position: fixed; left: ${left}px; top: ${top}px; width: ${width}px; z-index: 9999;`;
            }
        },

        close() {
            if (this.open) {
                this.open = false;
                window.dispatchEvent(new CustomEvent('hs-dropdown-closed', { detail: this.name }));
            }
        },

        handleOutsideClick(event) {
            if (!this.open) return;
            const panel = this.$refs && this.$refs.panel ? this.$refs.panel : null;
            if (this.$el.contains(event.target)) return;
            if (panel && panel.contains(event.target)) return;
            this.close();
        },

        async toggle() {
            this.open = !this.open;
            if (this.open) {
                window.dispatchEvent(new CustomEvent('hs-dropdown-opened', { detail: this.name }));
                this.$nextTick(() => {
                    this.updatePosition();
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
            }, 300);
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
