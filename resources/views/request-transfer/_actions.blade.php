<div class="flex items-center justify-center gap-2">
    <a href="{{ route('rt.show', $rt) }}" class="text-primary-600 hover:text-primary-800 text-xs font-semibold hover:underline" title="Lihat rincian dokumen">
        Detail
    </a>

    @if($rt->status === 'draft' && auth()->user()->can('rt.create'))
    <span class="text-gray-300">|</span>
    <a href="{{ route('rt.edit', $rt) }}" class="text-amber-600 hover:text-amber-800 text-xs font-semibold hover:underline" title="Edit dokumen draft">
        Edit
    </a>
    @endif

    @php
        $canCancel = auth()->user()->can('rt.cancel') || (in_array($rt->status, ['draft', 'diajukan'], true) && (int)$rt->created_by === (int)auth()->id() && auth()->user()->can('rt.create'));
    @endphp

    @if(in_array($rt->status, ['draft', 'diajukan'], true) && $canCancel)
    <span class="text-gray-300">|</span>
    <form method="POST" action="{{ route('rt.transition', $rt) }}" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan dokumen {{ $rt->no_transaksi }}?');" class="inline">
        @csrf
        <input type="hidden" name="aksi" value="cancel">
        <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold hover:underline cursor-pointer" title="Batalkan pengajuan sebelum disetujui">
            Batalkan
        </button>
    </form>
    @endif

    @if(in_array($rt->status, ['diproses', 'dikirim', 'selesai'], true))
    <span class="text-gray-300">|</span>
    <a href="{{ route('rt.surat-jalan', $rt) }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold hover:underline flex items-center gap-0.5" title="Buka / Cetak Surat Jalan">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Surat Jalan
    </a>
    @endif
</div>
