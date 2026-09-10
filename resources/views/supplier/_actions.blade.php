<div class="flex items-center gap-3">
    @can('supplier.edit')
    <a href="{{ route('supplier.edit', $s) }}" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Edit</a>
    @endcan
    @can('supplier.delete')
    <button onclick="hapus('{{ route('supplier.destroy', $s) }}')" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
    @endcan
</div>
