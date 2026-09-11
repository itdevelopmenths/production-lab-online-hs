@props([
    'catalog' => [],
    'activePermissions' => [],
    'readonly' => false,
])

@php
    $allKeys = [];
    foreach ($catalog as $domain => $modules) {
        foreach ($modules as $moduleKey => $moduleData) {
            foreach (array_keys($moduleData['actions']) as $permKey) {
                $allKeys[] = $permKey;
            }
        }
    }
    $totalCount = count($allKeys);
@endphp

<div x-data="{
    selected: {{ json_encode(array_values($activePermissions)) }},
    allKeys: {{ json_encode($allKeys) }},
    activeDomain: 'all',

    selectAll() {
        this.selected = [...this.allKeys];
    },
    deselectAll() {
        this.selected = [];
    },
    toggleModule(actions) {
        const actionKeys = Object.keys(actions);
        const allSelected = actionKeys.every(k => this.selected.includes(k));
        if (allSelected) {
            this.selected = this.selected.filter(k => !actionKeys.includes(k));
        } else {
            const toAdd = actionKeys.filter(k => !this.selected.includes(k));
            this.selected = [...this.selected, ...toAdd];
        }
    },
    isModuleAllSelected(actions) {
        const actionKeys = Object.keys(actions);
        return actionKeys.length > 0 && actionKeys.every(k => this.selected.includes(k));
    },
    isModulePartiallySelected(actions) {
        const actionKeys = Object.keys(actions);
        const count = actionKeys.filter(k => this.selected.includes(k)).length;
        return count > 0 && count < actionKeys.length;
    },
    moduleSelectedCount(actions) {
        const actionKeys = Object.keys(actions);
        return actionKeys.filter(k => this.selected.includes(k)).length;
    }
}" class="space-y-6">

    {{-- Top Action & Filter Toolbar --}}
    <div class="bg-white border border-gray-200 rounded-sm p-3.5 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-sm bg-primary-50 border border-primary-200 flex items-center justify-center text-primary-700">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-800 uppercase tracking-wider">Matriks Hak Akses & Wewenang</div>
                <div class="text-xs text-gray-500 mt-0.5">
                    <span class="font-bold text-primary-700 font-mono" x-text="selected.length"></span> dari 
                    <span class="font-bold text-gray-700 font-mono">{{ $totalCount }}</span> wewenang tercentang
                </div>
            </div>
        </div>

        @if(!$readonly)
        <div class="flex items-center gap-2">
            <button type="button" @click="selectAll()" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-sm bg-primary-50 text-primary-700 hover:bg-primary-100 border border-primary-200 transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Pilih Semua
            </button>
            <button type="button" @click="deselectAll()" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-sm bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200 transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Kosongkan
            </button>
        </div>
        @endif
    </div>

    {{-- Domain Categories Loop --}}
    @foreach ($catalog as $domain => $modules)
        <div class="border border-gray-200 rounded-sm bg-white shadow-2xs overflow-hidden">
            {{-- Domain Header Banner --}}
            <div class="bg-gray-50/80 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-primary-600"></span>
                    <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider">{{ $domain }}</h3>
                </div>
                <span class="text-[11px] font-medium text-gray-500">
                    {{ count($modules) }} Modul
                </span>
            </div>

            {{-- Modules Inside Domain --}}
            <div class="divide-y divide-gray-100">
                @foreach ($modules as $moduleKey => $moduleData)
                    @php
                        $actionCount = count($moduleData['actions']);
                        $jsActions = json_encode($moduleData['actions']);
                    @endphp
                    <div class="p-4 transition hover:bg-gray-50/30">
                        {{-- Module Subheader --}}
                        <div class="flex items-center justify-between gap-3 mb-3.5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-gray-900">{{ $moduleData['label'] }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono tracking-tight"
                                      :class="moduleSelectedCount({{ $jsActions }}) === {{ $actionCount }} ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : (moduleSelectedCount({{ $jsActions }}) > 0 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-gray-100 text-gray-600')">
                                    <span x-text="moduleSelectedCount({{ $jsActions }})"></span>/{{ $actionCount }}
                                </span>
                            </div>

                            @if(!$readonly)
                            <button type="button" 
                                    @click="toggleModule({{ $jsActions }})"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:text-primary-900 px-2 py-0.75 rounded-sm hover:bg-primary-50 transition border border-transparent hover:border-primary-200">
                                <span x-text="isModuleAllSelected({{ $jsActions }}) ? 'Batal Pilih Semua' : 'Pilih Semua Modul Ini'"></span>
                            </button>
                            @endif
                        </div>

                        {{-- Action Grid --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2.5">
                            @foreach ($moduleData['actions'] as $actionKey => $actionLabel)
                                @php
                                    $actionType = last(explode('.', $actionKey));
                                    $badgeClass = match($actionType) {
                                        'view' => 'bg-slate-100 text-slate-700 border-slate-200',
                                        'create' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'edit', 'manage' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'delete', 'cancel' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        default => 'bg-indigo-50 text-indigo-700 border-indigo-200'
                                    };
                                    $badgeLabel = match($actionType) {
                                        'view' => 'READ',
                                        'create' => 'CREATE',
                                        'edit' => 'UPDATE',
                                        'manage' => 'MANAGE',
                                        'delete' => 'DELETE',
                                        'cancel' => 'CANCEL',
                                        default => strtoupper($actionType)
                                    };
                                @endphp
                                <label class="relative flex items-start gap-2.5 p-2.5 rounded-sm border transition cursor-pointer select-none"
                                       :class="selected.includes('{{ $actionKey }}') ? 'bg-primary-50/40 border-primary-300 ring-1 ring-primary-200' : 'bg-white border-gray-200 hover:border-gray-300'">
                                    <div class="flex items-center h-4 mt-0.5">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $actionKey }}" 
                                               x-model="selected"
                                               @if($readonly) disabled @endif
                                               class="w-4 h-4 rounded-sm border-gray-300 text-primary-600 focus:ring-primary-500 shadow-2xs cursor-pointer disabled:cursor-not-allowed">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                            <span class="inline-block px-1.5 py-0.25 text-[9px] font-bold rounded-xs uppercase tracking-wider border {{ $badgeClass }}">
                                                {{ $badgeLabel }}
                                            </span>
                                        </div>
                                        <div class="text-xs font-medium text-gray-900 leading-snug">
                                            {{ $actionLabel }}
                                        </div>
                                        <div class="text-[10px] text-gray-400 font-mono tracking-tight mt-0.5 truncate">
                                            {{ $actionKey }}
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
