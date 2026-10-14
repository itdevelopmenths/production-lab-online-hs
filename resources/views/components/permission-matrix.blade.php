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
    <div class="bg-card border border-border rounded-xl p-4 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="size-9 rounded-lg bg-muted flex items-center justify-center text-foreground">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <div>
                <div class="text-sm font-semibold text-foreground tracking-tight">Matriks Hak Akses & Wewenang</div>
                <div class="text-xs text-muted-foreground mt-0.5">
                    <span class="font-bold text-foreground font-mono" x-text="selected.length"></span> dari 
                    <span class="font-bold text-foreground font-mono">{{ $totalCount }}</span> wewenang tercentang
                </div>
            </div>
        </div>

        @if(!$readonly)
        <div class="flex items-center gap-2">
            <button type="button" @click="selectAll()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-card text-foreground hover:bg-accent border border-border transition-colors cursor-pointer shadow-xs">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Pilih Semua
            </button>
            <button type="button" @click="deselectAll()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-card text-muted-foreground hover:text-foreground hover:bg-accent border border-border transition-colors cursor-pointer shadow-xs">
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
        <div class="border border-border rounded-xl bg-card shadow-xs overflow-hidden">
            {{-- Domain Header Banner --}}
            <div class="bg-muted/40 px-5 py-3 border-b border-border flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-primary"></span>
                    <h3 class="text-xs font-bold text-foreground uppercase tracking-wider">{{ $domain }}</h3>
                </div>
                <span class="text-xs font-medium text-muted-foreground">
                    {{ count($modules) }} Modul
                </span>
            </div>

            {{-- Modules Inside Domain --}}
            <div class="divide-y divide-border">
                @foreach ($modules as $moduleKey => $moduleData)
                    @php
                        $actionCount = count($moduleData['actions']);
                        $jsActions = json_encode($moduleData['actions']);
                    @endphp
                    <div class="p-5 transition-colors hover:bg-muted/20">
                        {{-- Module Subheader --}}
                        <div class="flex items-center justify-between gap-3 mb-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-semibold text-foreground">{{ $moduleData['label'] }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono tracking-tight border"
                                      :class="moduleSelectedCount({{ $jsActions }}) === {{ $actionCount }} ? 'bg-primary text-primary-foreground border-primary' : (moduleSelectedCount({{ $jsActions }}) > 0 ? 'bg-muted text-foreground border-border' : 'bg-muted/50 text-muted-foreground border-border')">
                                    <span x-text="moduleSelectedCount({{ $jsActions }})"></span>/{{ $actionCount }}
                                </span>
                            </div>

                            @if(!$readonly)
                            <button type="button" 
                                    @click="toggleModule({{ $jsActions }})"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-foreground hover:underline px-2 py-1 rounded-md transition cursor-pointer">
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
                                        'view' => 'bg-muted text-muted-foreground border-border',
                                        'create' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
                                        'edit', 'manage' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
                                        'delete', 'cancel' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
                                        default => 'bg-muted text-foreground border-border'
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
                                <label class="relative flex items-start gap-3 p-3 rounded-xl border transition-colors cursor-pointer select-none"
                                       :class="selected.includes('{{ $actionKey }}') ? 'bg-muted/70 border-foreground/30 shadow-xs' : 'bg-card border-border hover:bg-muted/30'">
                                    <div class="flex items-center h-4 mt-0.5">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $actionKey }}" 
                                               x-model="selected"
                                               @if($readonly) disabled @endif
                                               class="size-4 rounded-md border-input text-foreground focus:ring-ring shadow-xs cursor-pointer disabled:cursor-not-allowed">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                            <span class="inline-block px-1.5 py-0.25 text-[9px] font-bold rounded-md uppercase tracking-wider border {{ $badgeClass }}">
                                                {{ $badgeLabel }}
                                            </span>
                                        </div>
                                        <div class="text-xs font-semibold text-foreground leading-snug">
                                            {{ $actionLabel }}
                                        </div>
                                        <div class="text-[10px] text-muted-foreground font-mono tracking-tight mt-0.5 truncate">
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
