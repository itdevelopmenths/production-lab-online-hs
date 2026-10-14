@props([
    'auditable',
    'title' => 'Riwayat Jejak Audit & Log Status Dokumen',
])

@php
    $audits = $auditable->operationalAudits()->with('user')->get();
@endphp

<x-card :title="$title" subtitle="Catatan kronologis perubahan status, persetujuan, dan mutasi dokumen" :noPadding="true">
    @if($audits->isEmpty())
        <div class="p-8 text-center text-sm text-muted-foreground flex flex-col items-center justify-center">
            <svg class="size-8 text-muted-foreground/40 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Belum ada aktivitas audit yang tercatat untuk dokumen ini.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                        <th class="px-4 py-3 text-left w-36">Waktu</th>
                        <th class="px-4 py-3 text-center w-28">Status / Event</th>
                        <th class="px-4 py-3 text-left">Aktivitas & Rincian Perubahan</th>
                        <th class="px-4 py-3 text-left w-40">Pelaksana</th>
                        <th class="px-4 py-3 text-left w-28">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($audits as $audit)
                    <tr class="hover:bg-muted/40 transition-colors">
                        <td class="px-4 py-3 font-mono text-muted-foreground whitespace-nowrap align-top text-xs">
                            {{ $audit->created_at ? $audit->created_at->format('d/m/Y H:i:s') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap align-top">
                            {!! $audit->event_badge !!}
                        </td>
                        <td class="px-4 py-3 text-xs text-foreground align-top leading-relaxed">
                            {!! $audit->formatted_diff !!}
                        </td>
                        <td class="px-4 py-3 text-xs align-top">
                            <div class="font-semibold text-foreground leading-tight">{{ $audit->user_name ?? 'Sistem' }}</div>
                            @if($audit->user_role)
                                <div class="text-[10px] text-muted-foreground capitalize">{{ $audit->user_role }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-muted-foreground text-[11px] whitespace-nowrap align-top">
                            {{ $audit->ip_address ?: '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
