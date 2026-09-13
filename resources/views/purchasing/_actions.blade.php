<div class="flex items-center justify-center gap-2">
    <a href="{{ route('purchasing.show', $po) }}" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Detail</a>
    @if(!empty($canEdit))
        <a href="{{ route('purchasing.edit', $po) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</a>
        <button type="button"
            onclick="openQuickDateModal('{{ $po->id }}', '{{ $po->no_po }}', '{{ $po->tanggal ? $po->tanggal->format('Y-m-d') : '' }}', '{{ $po->eta ? $po->eta->format('Y-m-d') : '' }}')"
            class="text-amber-600 hover:text-amber-800 text-xs font-medium cursor-pointer"
            title="Edit Tanggal PO & ETA">
            Edit Tgl
        </button>
    @endif
</div>
