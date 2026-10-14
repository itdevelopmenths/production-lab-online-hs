{{-- Modal Detail Snapshot Riwayat --}}
<x-modal name="showSnapshotModal" maxWidth="xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-border bg-card flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-foreground tracking-tight">Detail Snapshot Riwayat</h3>
                <p class="text-xs text-muted-foreground font-mono mt-0.5" x-text="activeSnapshot ? activeSnapshot.session_id + ' · ' + activeSnapshot.item_label : ''"></p>
            </div>
            <button type="button" @click="showSnapshotModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <div class="p-6 max-h-[60vh] overflow-y-auto">
        <dl class="grid grid-cols-2 gap-3 text-xs">
            <template x-if="activeSnapshot && activeSnapshot.detail">
                <template x-for="[key, val] in Object.entries(activeSnapshot.detail).filter(([k]) => k !== 'varian_detail')" :key="key">
                    <div class="bg-muted/40 p-3 rounded-xl border border-border shadow-xs">
                        <dt class="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider" x-text="key.replace(/_/g, ' ')"></dt>
                        <dd class="mt-1 font-mono text-foreground font-bold text-sm" x-text="typeof val === 'number' ? formatNumber(val) : (val ?? '-')"></dd>
                    </div>
                </template>
            </template>
        </dl>
    </div>

    <div class="px-6 py-3.5 bg-muted/30 border-t border-border flex justify-end">
        <x-button type="button" @click="showSnapshotModal = false" variant="outline" size="sm">
            Tutup
        </x-button>
    </div>
</x-modal>
