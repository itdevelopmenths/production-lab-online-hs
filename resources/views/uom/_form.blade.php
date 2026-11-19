@php
    $u = $uom ?? null;
    $rawFaktor = old('faktor_konversi', $u?->faktor_konversi ?? 1);
    $displayFaktor = ($rawFaktor !== null && $rawFaktor !== '') ? ((float) $rawFaktor == (int) $rawFaktor ? (int) $rawFaktor : (float) $rawFaktor) : 1;
@endphp
<div class="space-y-3">
    <x-form-section card title="Identitas Satuan Unit" subtitle="Kode simbol resmi, nama lengkap, serta kategori dimensi fisik">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
            <x-form-field name="kode" label="Kode Satuan (Simbol)" :required="true" help="Singkatan resmi satuan (huruf kecil, misal: pcs, ml, gr, kg, l, botol)">
                <x-input name="kode" variant="seamless" value="{{ old('kode', $u?->kode) }}" placeholder="misal: ml" :required="true" />
            </x-form-field>

            <x-form-field name="nama" label="Nama Satuan Lengkap" :required="true" help="Nama deskriptif unit pengukuran">
                <x-input name="nama" variant="seamless" value="{{ old('nama', $u?->nama) }}" placeholder="misal: Mililiter" :required="true" />
            </x-form-field>

            <div class="md:col-span-2">
                <x-form-field name="kategori" label="Kategori Dimensi" help="Klasifikasi besaran fisik unit">
                    <x-select name="kategori" variant="seamless" placeholder="— Pilih Kategori Dimensi (Opsional) —">
                        @foreach(['Volume' => 'Volume (Cairan / Larutan)', 'Massa' => 'Massa / Berat (Bubuk / Padatan)', 'Satuan Hitung' => 'Satuan Hitung (Pieces / Unit)', 'Kemasan' => 'Wadah Kemasan (Botol / Box / Drum)', 'Lainnya' => 'Lainnya'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('kategori', $u?->kategori) === $v)>{{ $l }}</option>
                        @endforeach
                    </x-select>
                </x-form-field>
            </div>
        </div>
    </x-form-section>

    <x-form-section card title="Konversi Satuan & Status" subtitle="Relasi konversi ke satuan pokok laboratorium dan status aktivasi">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
            <x-form-field name="satuan_dasar" label="Satuan Pokok Acuan (Basis)" help="Satuan dasar laboratorium jika unit ini adalah wadah/kemasan turunan">
                <x-select name="satuan_dasar" variant="seamless" placeholder="— Pilih Satuan Pokok / Mandiri —">
                    <option value="" @selected(empty(old('satuan_dasar', $u?->satuan_dasar)))>— Satuan Mandiri (Pokok) —</option>
                    <option value="ml" @selected(old('satuan_dasar', $u?->satuan_dasar) === 'ml')>Mililiter (ml) — Cairan / Larutan</option>
                    <option value="gr" @selected(old('satuan_dasar', $u?->satuan_dasar) === 'gr')>Gram (gr) — Bubuk / Padatan</option>
                    <option value="pcs" @selected(old('satuan_dasar', $u?->satuan_dasar) === 'pcs')>Pieces (pcs) — Satuan Hitung / Wadah</option>
                </x-select>
            </x-form-field>

            <x-form-field name="faktor_konversi" label="Faktor Konversi Bawaan" help="Kuantitas satuan pokok per 1 unit ini (misal: 1 Galon = 19000 ml, 1 Drum = 200000 ml, 1 kg = 1000 gr)">
                <x-input type="number" step="any" min="0.0001" name="faktor_konversi" variant="seamless" value="{{ $displayFaktor }}" placeholder="1" />
            </x-form-field>

            <div class="md:col-span-2">
                <x-form-field name="deskripsi" label="Keterangan / Deskripsi" help="Catatan tambahan mengenai konversi atau standar penggunaan">
                    <x-textarea name="deskripsi" rows="2" variant="seamless" placeholder="Catatan peruntukan satuan..." :value="old('deskripsi', $u?->deskripsi)" />
                </x-form-field>
            </div>

            <div class="md:col-span-2 pt-1">
                <x-checkbox name="is_active" label="Status Aktif Satuan" :checked="filter_var(old('is_active', $u?->is_active ?? true), FILTER_VALIDATE_BOOLEAN)" help="Satuan aktif dapat dipilih dalam pembuatan dan pengeditan master produk" />
            </div>
        </div>
    </x-form-section>
</div>
