@props([
    'auditable',
    'title' => 'Riwayat Jejak Audit & Log Status Dokumen',
    'abilities' => ['audit.view'],
])

@if (auth()->user()?->canany($abilities))
@php
    $audits = $auditable->operationalAudits()->with('user')->get();
@endphp

<x-card class="tbl-modern" :title="$title" subtitle="Catatan kronologis perubahan status, persetujuan, dan mutasi dokumen" :noPadding="true">
    <x-slot:icon>
        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </x-slot:icon>
    @if($audits->isEmpty())
        <div class="p-8 text-center text-sm text-text-muted flex flex-col items-center justify-center">
            <svg class="size-8 text-text-muted/40 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Belum ada aktivitas audit yang tercatat untuk dokumen ini.
        </div>
    @else
        <div class="overflow-x-auto focus:outline-none" tabindex="0" role="region" aria-label="Riwayat Audit Operasional">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr class="text-text-muted text-[12px] font-medium">
                        <th class="px-4 py-3 text-left w-36">Waktu</th>
                        <th class="px-4 py-3 text-center w-28">Status / Event</th>
                        <th class="px-4 py-3 text-left">Aktivitas &amp; Rincian Perubahan</th>
                        <th class="px-4 py-3 text-left w-40">Pelaksana</th>
                        <th class="px-4 py-3 text-left w-28">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8]/70 dark:divide-gray-800">
                    @foreach($audits as $audit)
                    <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-4 py-3 tabular-nums text-text-muted whitespace-nowrap align-top text-xs">
                            {{ $audit->created_at ? $audit->created_at->format('d/m/Y H:i:s') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap align-top">
                            {!! $audit->event_badge !!}
                        </td>
                        <td class="px-4 py-3 text-xs text-text-active align-top leading-relaxed">
                            {!! $audit->formatted_diff !!}
                        </td>
                        <td class="px-4 py-3 text-xs align-top">
                            <div class="font-medium text-text-active leading-tight">{{ $audit->user_name ?? 'Sistem' }}</div>
                            @if($audit->user_role)
                                <div class="text-[10px] text-text-muted capitalize">{{ $audit->user_role }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 tabular-nums text-text-muted text-[11px] whitespace-nowrap align-top">
                            {{ $audit->ip_address ?: '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
@endif
