@props([
    'catalog' => [],
    'activePermissions' => [],
    'readonly' => false,
    'locked' => [],
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
    $normalizedActive = array_values(array_intersect((array) $activePermissions, $allKeys));
@endphp

<script>
    function permissionMatrixState(activePermissions, allKeys, locked) {
        return {
            locked: locked || [],
            selected: Array.from(new Set((activePermissions || []).concat(locked || []))),
            allKeys: allKeys || [],
            activeDomain: 'all',

            selectAll() {
                this.selected = this.allKeys.slice();
            },
            deselectAll() {
                this.selected = this.locked.slice();
            },
            toggleModule(actions) {
                const actionKeys = Object.keys(actions);
                const self = this;
                const allSelected = actionKeys.every(function(k) { return self.selected.includes(k); });
                if (allSelected) {
                    this.selected = this.selected.filter(function(k) { return !actionKeys.includes(k) || self.locked.includes(k); });
                } else {
                    const toAdd = actionKeys.filter(function(k) { return !self.selected.includes(k); });
                    this.selected = this.selected.concat(toAdd);
                }
            },
            isModuleAllSelected(actions) {
                const actionKeys = Object.keys(actions);
                const self = this;
                return actionKeys.length > 0 && actionKeys.every(function(k) { return self.selected.includes(k); });
            },
            isModulePartiallySelected(actions) {
                const actionKeys = Object.keys(actions);
                const self = this;
                const count = actionKeys.filter(function(k) { return self.selected.includes(k); }).length;
                return count > 0 && count < actionKeys.length;
            },
            moduleSelectedCount(actions) {
                const actionKeys = Object.keys(actions);
                const self = this;
                return actionKeys.filter(function(k) { return self.selected.includes(k); }).length;
            },
            moduleBadgeClass(actions, total) {
                const count = this.moduleSelectedCount(actions);
                if (count === total) {
                    return 'bg-neutral-900 text-white border-neutral-900 dark:bg-white dark:text-neutral-900 dark:border-white';
                }
                if (count > 0) {
                    return 'bg-neutral-100 dark:bg-gray-800 text-text-active border-[#E8E8E8] dark:border-gray-700';
                }
                return 'bg-[#fafafa] dark:bg-gray-900 text-text-muted border-[#E8E8E8] dark:border-gray-800';
            }
        };
    }
</script>

<div x-data="permissionMatrixState({{ Js::from($normalizedActive) }}, {{ Js::from($allKeys) }}, {{ Js::from($locked) }})" class="space-y-6">

    {{-- Top Action & Filter Toolbar --}}
    <div class="bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="size-9 rounded-[8px] bg-[#fafafa] dark:bg-gray-800 border border-[#E8E8E8] dark:border-gray-700 flex items-center justify-center text-text-active">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <div>
                <div class="text-sm font-medium text-text-active tracking-tight">Matriks Hak Akses &amp; Wewenang</div>
                <div class="text-xs text-text-muted mt-0.5">
                    <span class="font-medium text-text-active tabular-nums" x-text="selected.length"></span> dari 
                    <span class="font-medium text-text-active tabular-nums">{{ $totalCount }}</span> wewenang tercentang
                </div>
            </div>
        </div>

        @if(!$readonly)
        <div class="flex items-center gap-2">
            <button type="button" @click="selectAll()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-[8px] bg-white dark:bg-gray-900 text-text-active hover:bg-[#fafafa] dark:hover:bg-gray-800 border border-[#E8E8E8] dark:border-gray-800 transition-colors cursor-pointer">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Pilih Semua
            </button>
            <button type="button" @click="deselectAll()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-[8px] bg-white dark:bg-gray-900 text-text-muted hover:text-text-active hover:bg-[#fafafa] dark:hover:bg-gray-800 border border-[#E8E8E8] dark:border-gray-800 transition-colors cursor-pointer">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Kosongkan
            </button>
        </div>
        @endif
    </div>

    {{-- Domain Categories Loop --}}
    @foreach ($catalog as $domain => $modules)
        <div class="border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] bg-white dark:bg-gray-900 shadow-xs overflow-hidden">
            {{-- Domain Header Banner --}}
            <div class="bg-[#fafafa] dark:bg-gray-800/60 px-5 py-3 border-b border-[#E8E8E8] dark:border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-neutral-900 dark:bg-white"></span>
                    <h3 class="text-xs font-semibold text-text-active uppercase tracking-wider">{{ $domain }}</h3>
                </div>
                <span class="text-xs font-medium text-text-muted">
                    {{ count($modules) }} Modul
                </span>
            </div>

            {{-- Modules Inside Domain --}}
            <div class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                @foreach ($modules as $moduleKey => $moduleData)
                    @php
                        $actionCount = count($moduleData['actions']);
                        $jsActions = json_encode($moduleData['actions']);
                    @endphp
                    <div class="p-5 transition-colors hover:bg-[#fafafa]/50 dark:hover:bg-gray-800/30">
                        {{-- Module Subheader --}}
                        <div class="flex items-center justify-between gap-3 mb-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-medium text-text-active">{{ $moduleData['label'] }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium tabular-nums tracking-tight border"
                                      :class="moduleBadgeClass({{ $jsActions }}, {{ $actionCount }})">
                                    <span x-text="moduleSelectedCount({{ $jsActions }})"></span>/{{ $actionCount }}
                                </span>
                            </div>

                            @if(!$readonly)
                            <button type="button" 
                                    @click="toggleModule({{ $jsActions }})"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-text-active hover:underline px-2 py-1 rounded-[6px] transition cursor-pointer">
                                <span x-text="isModuleAllSelected({{ $jsActions }}) ? 'Batal Pilih Semua' : 'Pilih Semua Modul Ini'"></span>
                            </button>
                            @endif
                        </div>

                        {{-- Action Grid --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            @foreach ($moduleData['actions'] as $actionKey => $actionLabel)
                                @php
                                    $actionType = last(explode('.', $actionKey));
                                    $badgeClass = match($actionType) {
                                        'view' => 'bg-neutral-100 dark:bg-gray-800 text-text-muted border-[#E8E8E8] dark:border-gray-700',
                                        'create' => 'bg-card text-foreground dark:text-foreground border-border',
                                        'edit', 'manage' => 'bg-card text-foreground dark:text-foreground border-border',
                                        'delete', 'cancel' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
                                        default => 'bg-neutral-100 dark:bg-gray-800 text-text-active border-[#E8E8E8] dark:border-gray-700'
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
                                <label class="relative flex items-start gap-3 p-3 rounded-[8px] border transition-colors {{ $readonly ? 'cursor-default' : 'cursor-pointer' }} select-none"
                                       :class="selected.includes('{{ $actionKey }}') ? 'bg-neutral-100/80 dark:bg-gray-800/80 border-neutral-900 dark:border-white' : 'bg-white dark:bg-gray-900 border-[#E8E8E8] dark:border-gray-800 hover:bg-[#fafafa] dark:hover:bg-gray-800/40'">
                                    <div class="flex items-center h-4 mt-0.5">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $actionKey }}" 
                                               x-model="selected"
                                               @if($readonly || in_array($actionKey, $locked, true)) disabled @endif
                                               @if(in_array($actionKey, $locked, true)) data-locked-permission="{{ $actionKey }}" @endif
                                               class="size-4 rounded border-gray-300 dark:border-gray-700 text-neutral-900 dark:text-white focus:ring-0 cursor-pointer disabled:cursor-not-allowed">
                                        @if(in_array($actionKey, $locked, true))
                                            <input type="hidden" name="permissions[]" value="{{ $actionKey }}">
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                            <span class="inline-block px-1.5 py-0.25 text-[9px] font-medium rounded-[6px] uppercase tracking-wider border {{ $badgeClass }}">
                                                {{ $badgeLabel }}
                                            </span>
                                        </div>
                                        <div class="text-xs font-medium text-text-active leading-snug">
                                            {{ $actionLabel }}
                                        </div>
                                        <div class="text-[10px] text-text-muted tabular-nums tracking-tight mt-0.5 truncate">
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
