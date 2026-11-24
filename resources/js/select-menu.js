/**
 * Dropdown menu kustom untuk semua <select> (kecuali `multiple` atau yang bertanda `data-native`).
 * Select asli tetap ada (tersembunyi) sebagai sumber nilai, jadi form, DataTables
 * dan handler `change` jQuery tetap bekerja tanpa perubahan.
 * Pengecualian: tambahkan `data-native` pada select untuk memakai tampilan bawaan browser.
 * Select internal SweetAlert (`.swal2-select`) selalu dikecualikan, karena SweetAlert
 * menyembunyikannya sendiri dan hanya memakainya untuk input bertipe select.
 */
const SELECTOR = 'select:not([multiple]):not([data-native]):not(.swal2-select)';
const CHEVRON = '<svg class="dd-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 7.75 10 12.25l4.5-4.5"/></svg>';
const CHECK = '<svg class="dd-check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 10.5 3.5 3.5 7.5-8"/></svg>';

let openInstance = null;
const syncers = new Set();

function enhance(select) {
    if (select.dataset.ddReady) return;
    select.dataset.ddReady = '1';

    const wrap = document.createElement('div');
    wrap.className = 'dd';
    // lebar penuh hanya bila select memang w-full tanpa varian responsif (sm:w-auto dst.)
    if (select.classList.contains('w-full') && !/\b(sm|md|lg|xl):w-/.test(select.className)) wrap.classList.add('dd-block');
    if (/(^|\s)h-8(\s|$)/.test(select.className)) wrap.classList.add('dd-sm');
    // select di dalam form / modal (bukan tabel atau bar filter): ikut gaya field form
    const inForm = !!select.closest('form, [role="dialog"], [aria-modal="true"]') && !select.closest('.tbl-modern') && !/^filter/.test(select.id || '');
    if (inForm) {
        wrap.classList.add('dd-block', 'dd-field');
        if (/\bborder-0\b|\bbg-transparent\b/.test(select.className)) wrap.classList.add('dd-seamless');
        if (/\bh-11\b/.test(select.className)) wrap.classList.add('dd-lg');
        else if (/\bh-8(\.5)?\b|\bh-7\b/.test(select.className)) wrap.classList.add('dd-sm');
    }

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'dd-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const aria = select.getAttribute('aria-label');
    if (aria) trigger.setAttribute('aria-label', aria);
    trigger.innerHTML = '<span class="dd-label"></span>' + CHEVRON;
    const label = trigger.querySelector('.dd-label');

    const menu = document.createElement('div');
    menu.className = 'dd-menu';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;

    select.parentNode.insertBefore(wrap, select);
    wrap.append(trigger, menu, select);
    select.classList.add('dd-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    let items = [];
    let active = -1;

    const sync = () => {
        const opt = select.options[select.selectedIndex];
        label.textContent = opt ? opt.textContent.trim() : '';
        trigger.disabled = select.disabled;
        wrap.classList.toggle('is-placeholder', !select.value);
        wrap.classList.toggle('dd-error', /\bborder-red-500\b/.test(select.className));
    };

    const build = () => {
        menu.textContent = '';
        items = Array.from(select.options).map((opt, i) => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'dd-item';
            el.setAttribute('role', 'option');
            el.dataset.index = String(i);
            el.disabled = opt.disabled;
            const selected = i === select.selectedIndex;
            el.setAttribute('aria-selected', selected ? 'true' : 'false');
            if (selected) el.classList.add('is-selected');
            el.innerHTML = '<span class="dd-item-text"></span>' + CHECK;
            el.firstChild.textContent = opt.textContent.trim();
            menu.appendChild(el);
            return el;
        });
    };

    const highlight = (i, scroll = true) => {
        items.forEach((el) => el.classList.remove('is-active'));
        active = i;
        const el = items[i];
        if (!el) return;
        el.classList.add('is-active');
        if (scroll) el.scrollIntoView({ block: 'nearest' });
    };

    const place = () => {
        const r = trigger.getBoundingClientRect();
        menu.style.minWidth = Math.max(r.width, 88) + 'px';
        menu.style.left = r.left + 'px';
        // trigger di sisi kanan layar: rata kanan agar daftar tidak melewati tepi kartu
        if (r.left > window.innerWidth * 0.6) menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
        const below = window.innerHeight - r.bottom;
        const h = Math.min(menu.scrollHeight, 288);
        if (below < h + 16 && r.top > below) {
            menu.style.top = 'auto';
            menu.style.bottom = (window.innerHeight - r.top + 6) + 'px';
            menu.dataset.side = 'up';
        } else {
            menu.style.bottom = 'auto';
            menu.style.top = (r.bottom + 6) + 'px';
            menu.dataset.side = 'down';
        }
        // jaga agar tidak keluar dari sisi kanan layar
        const overflow = menu.getBoundingClientRect().right - (window.innerWidth - 8);
        if (overflow > 0) menu.style.left = Math.max(8, r.left - overflow) + 'px';
    };

    const close = (refocus = false) => {
        if (menu.hidden) return;
        menu.hidden = true;
        wrap.appendChild(menu); // kembalikan dari body (portal)
        trigger.setAttribute('aria-expanded', 'false');
        wrap.classList.remove('is-open');
        if (openInstance === api) openInstance = null;
        if (refocus) trigger.focus();
    };

    const open = () => {
        if (select.disabled || !menu.hidden) return;
        if (openInstance) openInstance.close();
        build();
        // pindahkan ke body agar tidak terpengaruh transform / overflow modal
        document.body.appendChild(menu);
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        wrap.classList.add('is-open');
        place();
        highlight(select.selectedIndex);
        openInstance = api;
    };

    const choose = (i) => {
        const opt = select.options[i];
        if (!opt || opt.disabled) return;
        const changed = select.selectedIndex !== i;
        select.selectedIndex = i;
        sync();
        close(true);
        if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const step = (dir) => {
        let i = active;
        do { i += dir; } while (items[i] && items[i].disabled);
        if (items[i]) highlight(i);
    };

    const api = { close, reposition: place };

    trigger.addEventListener('click', () => (menu.hidden ? open() : close()));
    trigger.addEventListener('keydown', (e) => {
        if (['ArrowDown', 'ArrowUp'].includes(e.key) && menu.hidden) { e.preventDefault(); open(); }
    });
    menu.addEventListener('mousemove', (e) => {
        const el = e.target.closest('.dd-item');
        if (el && !el.disabled) highlight(Number(el.dataset.index), false);
    });
    menu.addEventListener('click', (e) => {
        const el = e.target.closest('.dd-item');
        if (el) choose(Number(el.dataset.index));
    });
    wrap.addEventListener('keydown', (e) => {
        if (menu.hidden) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); step(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); step(-1); }
        else if (e.key === 'Home') { e.preventDefault(); highlight(items.findIndex((el) => !el.disabled)); }
        else if (e.key === 'End') { e.preventDefault(); highlight(items.length - 1 - [...items].reverse().findIndex((el) => !el.disabled)); }
        else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(active); }
        else if (e.key === 'Escape') { e.preventDefault(); close(true); }
        else if (e.key === 'Tab') close();
    });

    // kode lain (mis. tombol reset: $('#x').val('')) mengubah value tanpa event change
    const proto = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    Object.defineProperty(select, 'value', {
        configurable: true,
        get() { return proto.get.call(this); },
        set(v) { proto.set.call(this, v); sync(); },
    });
    // opsi / status disabled bisa berubah dari Alpine (x-for, :disabled)
    new MutationObserver(sync).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'class'] });
    select.addEventListener('change', sync);
    if (select.form) select.form.addEventListener('reset', () => setTimeout(sync, 0));
    syncers.add(sync);
    select.addEventListener('focus', () => trigger.focus());
    sync();
}

function scan(root = document) {
    root.querySelectorAll(SELECTOR).forEach(enhance);
}

document.addEventListener('mousedown', (e) => {
    if (openInstance && !e.target.closest('.dd, .dd-menu')) openInstance.close();
});
window.addEventListener('resize', () => openInstance && openInstance.close());
window.addEventListener('scroll', (e) => {
    if (openInstance && !(e.target instanceof Element && e.target.closest('.dd-menu'))) openInstance.close();
}, true);

const start = () => {
    scan();
    new MutationObserver((records) => {
        for (const r of records) {
            r.addedNodes.forEach((n) => {
                if (n.nodeType !== 1) return;
                if (n.matches?.(SELECTOR)) enhance(n);
                else scan(n);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
else start();

// Alpine (x-model) mengubah option.selected tanpa event: sinkronkan label secara berkala
setInterval(() => syncers.forEach((fn) => fn()), 300);
