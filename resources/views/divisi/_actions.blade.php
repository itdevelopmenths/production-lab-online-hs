<div class="flex items-center justify-center gap-3">
    @can('divisi.edit')
    <a href="{{ route('divisi.edit', $divisi) }}" class="text-primary-600 hover:text-primary-800 text-xs font-semibold">Edit</a>
    @endcan
    @can('divisi.delete')
    <button type="button" onclick="hapus('{{ route('divisi.destroy', $divisi) }}', '{{ addslashes($divisi->nama) }}')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold cursor-pointer">Hapus</button>
    @endcan
</div>
