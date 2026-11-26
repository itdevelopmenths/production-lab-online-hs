@if(app(\App\Services\Qa\QaClock::class)->enabled())
    <div class="space-y-1 rounded-lg border border-dashed border-border p-2.5 text-xs">
        <label class="block font-medium text-foreground">Durasi stage (QA)</label>
        <div class="flex items-center gap-2">
            <input type="number" name="qa_durasi_hari" min="0" max="365" value="{{ old('qa_durasi_hari') }}" placeholder="Hari" class="w-20 border border-input bg-background text-xs p-1.5 outline-none focus:border-ring focus:ring-1 focus:ring-ring rounded-[6px]">
            <input type="number" name="qa_durasi_jam" min="0" max="999" value="{{ old('qa_durasi_jam') }}" placeholder="Jam" class="w-20 border border-input bg-background text-xs p-1.5 outline-none focus:border-ring focus:ring-1 focus:ring-ring rounded-[6px]">
        </div>
        <p class="text-[11px] text-muted-foreground">Kosongkan untuk memakai waktu sekarang</p>
        @error('qa_durasi')
            <p class="text-[11px] text-destructive font-medium">{{ $message }}</p>
        @enderror
        @error('qa_durasi_hari')
            <p class="text-[11px] text-destructive font-medium">{{ $message }}</p>
        @enderror
        @error('qa_durasi_jam')
            <p class="text-[11px] text-destructive font-medium">{{ $message }}</p>
        @enderror
    </div>
@endif
