<div class="flex items-center justify-center gap-2">
    <a href="{{ route('purchasing.show', $po) }}" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Detail</a>
    @if(!$po->isLunas() && !in_array($po->status, ['dibatalkan']))
        <a href="{{ route('purchasing.show', $po) }}#form-pembayaran" class="text-emerald-600 hover:text-emerald-800 text-xs font-semibold">Bayar</a>
    @endif
</div>
