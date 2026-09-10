<div class="flex items-center gap-3">
    @can('gudang.edit')
    <a href="{{ route('gudang.edit', $g) }}" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Edit</a>
    @endcan
    @can('gudang.delete')
    <button onclick="hapus('{{ route('gudang.destroy', $g) }}')" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
    @endcan
</div>
