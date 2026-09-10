<div class="flex items-center gap-3">
    @can('produk.edit')
    <a href="{{ route('produk.edit', $p) }}" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Edit</a>
    @endcan
    @can('produk.delete')
    <button onclick="hapus('{{ route('produk.destroy', $p) }}')" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
    @endcan
</div>
