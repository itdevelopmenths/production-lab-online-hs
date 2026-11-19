{{--
    Pengganti alert() / confirm() bawaan browser. Dipasang sekali di app-layout.

    window.appDialog.alert(message, { title, tone, confirmText })       -> Promise<void>
    window.appDialog.confirm(message, { title, tone, confirmText, cancelText }) -> Promise<boolean>

    tone: 'danger' | 'warning' (default) | 'info'. Argumen pertama boleh berupa objek opsi (dengan key `message`).
--}}
<div x-data="appDialogHost()" x-init="init()" @keydown.escape.window="open && dismiss()">
    <x-modal name="open" maxWidth="sm" bodyClass="bg-white dark:bg-gray-900 p-5 sm:p-6 text-sm text-text-base">
        <div class="flex flex-col items-center gap-4 text-center">
            <span class="size-11 rounded-full border flex items-center justify-center shrink-0" :class="toneIcon" aria-hidden="true">
                <svg x-show="tone !== 'info'" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                <svg x-show="tone === 'info'" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11.25v5.25m0-8.25h.008M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </span>
            <div class="min-w-0 max-w-76 space-y-2 text-balance">
                <h3 class="text-base font-semibold text-text-active tracking-tight leading-snug" x-text="title"></h3>
                <p class="text-sm text-text-base leading-relaxed whitespace-pre-line" x-text="message"></p>
            </div>
        </div>

        <x-slot:footer>
            <div class="w-full grid gap-2" :class="kind === 'confirm' ? 'grid-cols-2' : 'grid-cols-1'">
                <button type="button" x-ref="cancelBtn" x-show="kind === 'confirm'" @click="dismiss()"
                        class="px-3.5 py-2 text-sm font-medium rounded-lg border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-text-active hover:bg-gray-50 dark:hover:bg-gray-800 transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900/20 dark:focus-visible:ring-white/30"
                        x-text="cancelText"></button>
                <button type="button" x-ref="okBtn" @click="accept()"
                        class="px-3.5 py-2 text-sm font-medium rounded-lg transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                        :class="tone === 'danger'
                            ? 'bg-rose-600 hover:bg-rose-700 text-white focus-visible:ring-rose-600/40'
                            : 'bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-900 focus-visible:ring-gray-900/30 dark:focus-visible:ring-white/40'"
                        x-text="confirmText"></button>
            </div>
        </x-slot:footer>
    </x-modal>
</div>

<script>
    function appDialogHost() {
        return {
            open: false,
            kind: 'alert',
            tone: 'warning',
            title: '',
            message: '',
            confirmText: 'Mengerti',
            cancelText: 'Batal',
            resolver: null,

            get toneIcon() {
                return {
                    danger: 'bg-rose-50 dark:bg-rose-500/10 border-rose-100 dark:border-rose-500/20 text-rose-600 dark:text-rose-400',
                    warning: 'bg-card dark:bg-card border-border dark:border-border text-foreground dark:text-foreground',
                    info: 'bg-gray-50 dark:bg-gray-800 border-[#E8E8E8] dark:border-border text-text-muted',
                }[this.tone] || '';
            },

            init() {
                const norm = (msg, opts) => (msg !== null && typeof msg === 'object')
                    ? msg
                    : Object.assign({ message: msg == null ? '' : String(msg) }, opts || {});

                window.appDialog = {
                    alert: (msg, opts) => this.ask('alert', norm(msg, opts)),
                    confirm: (msg, opts) => this.ask('confirm', norm(msg, opts)),
                };

                // Tutup lewat backdrop / Esc harus ikut menyelesaikan promise (confirm -> false).
                this.$watch('open', (isOpen) => { if (!isOpen) this.settle(false); });
            },

            ask(kind, o) {
                this.settle(false);
                this.kind = kind;
                this.tone = o.tone || (kind === 'confirm' ? 'warning' : 'warning');
                this.title = o.title || (kind === 'confirm' ? 'Konfirmasi' : 'Perhatian');
                this.message = o.message || '';
                this.confirmText = o.confirmText || (kind === 'confirm' ? 'Lanjutkan' : 'Mengerti');
                this.cancelText = o.cancelText || 'Batal';
                this.open = true;
                this.$nextTick(() => {
                    const target = (kind === 'confirm' && this.tone === 'danger') ? this.$refs.cancelBtn : this.$refs.okBtn;
                    target && target.focus();
                });
                return new Promise((resolve) => { this.resolver = resolve; });
            },

            settle(value) {
                if (this.resolver) {
                    const r = this.resolver;
                    this.resolver = null;
                    r(this.kind === 'confirm' ? value : undefined);
                }
            },

            accept() {
                this.settle(true);
                this.open = false;
            },

            dismiss() {
                this.settle(false);
                this.open = false;
            },
        };
    }
</script>
