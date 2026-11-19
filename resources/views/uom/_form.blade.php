@php
    $u = $uom ?? null;
    $rawFaktor = old('faktor_konversi', $u?->faktor_konversi ?? 1);
    $displayFaktor = ($rawFaktor !== null && $rawFaktor !== '') ? ((float) $rawFaktor == (int) $rawFaktor ? (int) $rawFaktor : (float) $rawFaktor) : 1;
@endphp
<div class="space-y-3">
    <x-form-section card title="Identitas Satuan Unit" subtitle="Kode, nama, dan kategori satuan">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
            <x-form-field name="kode" label="Kode Satuan" :required="true" help="Huruf kecil, misal: pcs, ml, kg">
                <x-input name="kode" variant="seamless" value="{{ old('kode', $u?->kode) }}" placeholder="misal: ml" :required="true" />
            </x-form-field>

            <x-form-field name="nama" label="Nama Satuan" :required="true" help="Nama lengkap satuan">
                <x-input name="nama" variant="seamless" value="{{ old('nama', $u?->nama) }}" placeholder="misal: Mililiter" :required="true" />
            </x-form-field>

            <div class="md:col-span-2">
                <x-form-field name="kategori" label="Kategori Dimensi" help="Klasifikasi besaran fisik unit">
                    <x-select name="kategori" variant="seamless" placeholder="— Pilih kategori —">
                        @foreach(['Volume' => 'Volume (Cairan / Larutan)', 'Massa' => 'Massa / Berat (Bubuk / Padatan)', 'Satuan Hitung' => 'Satuan Hitung (Pieces / Unit)', 'Kemasan' => 'Wadah Kemasan (Botol / Box / Drum)', 'Lainnya' => 'Lainnya'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('kategori', $u?->kategori) === $v)>{{ $l }}</option>
                        @endforeach
                    </x-select>
                </x-form-field>
            </div>
        </div>
    </x-form-section>

    <x-form-section card title="Konversi Satuan & Status" subtitle="Konversi ke satuan pokok dan status">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
            <x-form-field name="satuan_dasar" label="Satuan Pokok" help="Isi jika unit ini turunan, misal Drum → ml">
                <x-select name="satuan_dasar" variant="seamless" placeholder="— Mandiri —">
                    <option value="" @selected(empty(old('satuan_dasar', $u?->satuan_dasar)))>— Satuan Mandiri —</option>
                    <option value="ml" @selected(old('satuan_dasar', $u?->satuan_dasar) === 'ml')>Mililiter (ml) · Cairan</option>
                    <option value="gr" @selected(old('satuan_dasar', $u?->satuan_dasar) === 'gr')>Gram (gr) · Padatan</option>
                    <option value="pcs" @selected(old('satuan_dasar', $u?->satuan_dasar) === 'pcs')>Pieces (pcs) · Hitungan</option>
                </x-select>
            </x-form-field>

            <x-form-field name="faktor_konversi" label="Faktor Konversi" help="Jumlah satuan pokok per 1 unit. Contoh: 1 kg = 1000 gr">
                <x-input type="number" step="any" min="0.0001" name="faktor_konversi" variant="seamless" value="{{ $displayFaktor }}" placeholder="1" />
            </x-form-field>

            <div class="md:col-span-2">
                <x-form-field name="deskripsi" label="Keterangan / Deskripsi" help="Opsional">
                    <x-textarea name="deskripsi" rows="2" variant="seamless" placeholder="Catatan peruntukan satuan..." :value="old('deskripsi', $u?->deskripsi)" />
                </x-form-field>
            </div>

            <div class="md:col-span-2 pt-1">
                <x-checkbox name="is_active" label="Status Aktif Satuan" :checked="filter_var(old('is_active', $u?->is_active ?? true), FILTER_VALIDATE_BOOLEAN)" help="Hanya satuan aktif yang bisa dipilih di master produk" />
            </div>
        </div>
    </x-form-section>
</div>
