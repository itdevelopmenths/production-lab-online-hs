{{-- Modal Detail Snapshot Riwayat --}}
<x-modal name="showSnapshotModal" maxWidth="xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight">Detail Snapshot Riwayat</h3>
                <p class="text-xs text-[#667085] dark:text-gray-400 font-mono mt-0.5" x-text="activeSnapshot ? activeSnapshot.session_id + ' · ' + activeSnapshot.item_label : ''"></p>
            </div>
            <button type="button" @click="showSnapshotModal = false" class="text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white transition cursor-pointer p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <div class="p-6 max-h-[60vh] overflow-y-auto bg-[#F5F5F5] dark:bg-gray-950 custom-scrollbar">
        <dl class="grid grid-cols-2 gap-3 text-xs">
            <template x-if="activeSnapshot && activeSnapshot.detail">
                <template x-for="[key, val] in Object.entries(activeSnapshot.detail).filter(([k]) => k !== 'varian_detail')" :key="key">
                    <div class="bg-white dark:bg-gray-900 p-3.5 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                        <dt class="text-[10px] uppercase font-semibold text-[#667085] dark:text-gray-400 tracking-wider" x-text="key.replace(/_/g, ' ')"></dt>
                        <dd class="mt-1 font-mono text-[#101828] dark:text-white font-bold text-sm" x-text="typeof val === 'number' ? (/(lead_time|buffer|lt|hari)/i.test(key) ? formatLt(val) + ' Hari' : formatNumber(val)) : (val ?? '-')"></dd>
                    </div>
                </template>
            </template>
        </dl>
    </div>

    <div class="px-6 py-3.5 bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-gray-800 flex justify-end">
        <x-button type="button" @click="showSnapshotModal = false" variant="secondary" size="sm">
            Tutup
        </x-button>
    </div>
</x-modal>
