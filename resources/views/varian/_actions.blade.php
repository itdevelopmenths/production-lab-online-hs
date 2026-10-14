<div class="flex items-center justify-center gap-2">
    @can('produk.edit')
    <a href="{{ route('varian.edit', $v) }}" class="text-xs font-medium text-foreground hover:underline">Edit</a>
    @endcan
    @can('produk.delete')
    <button type="button" onclick="hapus('{{ route('varian.destroy', $v) }}')" class="text-xs font-medium text-destructive hover:underline cursor-pointer">Hapus</button>
    @endcan
</div>
