<div class="flex items-center justify-center gap-3">
    @can('uom.edit')
    <a href="{{ route('uom.edit', $u) }}" class="text-primary-600 hover:text-primary-800 text-xs font-semibold">Edit</a>
    @endcan
    @can('uom.delete')
    <button type="button" onclick="hapus('{{ route('uom.destroy', $u) }}')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold">Hapus</button>
    @endcan
</div>
