<div class="flex items-center justify-center gap-3">
    @can('produk.edit')
    <a href="{{ route('kategori.edit', $k) }}" class="text-primary-600 hover:text-primary-800 text-xs font-semibold">Edit</a>
    @endcan
    @can('produk.delete')
    <button type="button" onclick="hapus('{{ route('kategori.destroy', $k) }}')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold">Hapus</button>
    @endcan
</div>
