{{-- Modal Detail Snapshot Riwayat --}}
{{-- Follows AdminLTE Modal Standard & SOLID Principles --}}
<x-modal name="showSnapshotModal" maxWidth="xl" variant="dark">
    <x-slot:header>
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-800 tracking-tight">Detail Snapshot Riwayat</h3>
                <p class="text-xs text-gray-500 font-mono mt-0.5" x-text="activeSnapshot ? activeSnapshot.session_id + ' · ' + activeSnapshot.item_label : ''"></p>
            </div>
            <button type="button" @click="showSnapshotModal = false" class="text-gray-400 hover:text-gray-600 transition cursor-pointer p-1 rounded-sm hover:bg-gray-200/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <div class="max-h-[60vh] overflow-y-auto">
        <dl class="grid grid-cols-2 gap-2.5 text-xs">
            <template x-if="activeSnapshot && activeSnapshot.detail">
                <template x-for="[key, val] in Object.entries(activeSnapshot.detail).filter(([k]) => k !== 'varian_detail')" :key="key">
                    <div class="bg-gray-50 p-2.5 rounded-sm border border-gray-200 shadow-2xs">
                        <dt class="text-[10px] uppercase font-semibold text-gray-500 tracking-wider" x-text="key.replace(/_/g, ' ')"></dt>
                        <dd class="mt-0.5 font-mono text-gray-900 font-bold" x-text="typeof val === 'number' ? formatNumber(val) : (val ?? '-')"></dd>
                    </div>
                </template>
            </template>
        </dl>
    </div>

    <div class="pt-3 mt-3 border-t border-gray-200 flex justify-end">
        <button type="button" @click="showSnapshotModal = false" class="px-3.5 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-sm hover:bg-gray-50 shadow-xs cursor-pointer">
            Tutup
        </button>
    </div>
</x-modal>
