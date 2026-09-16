<div class="flex items-center justify-center gap-3">
    @can('produk.edit')
    <a href="{{ route('varian.edit', $v) }}" class="text-primary-600 hover:text-primary-800 text-xs font-semibold">Edit</a>
    @endcan
    @can('produk.delete')
    <button type="button" onclick="hapus('{{ route('varian.destroy', $v) }}')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold">Hapus</button>
    @endcan
</div>
