<?php

namespace App\Http\Requests;

use App\Models\Divisi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DivisiUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('divisi.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->kode) && !empty($this->nama)) {
            $this->merge([
                'kode' => Str::slug($this->nama, '_'),
            ]);
        } elseif (!empty($this->kode)) {
            $this->merge([
                'kode' => Str::slug($this->kode, '_'),
            ]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', false),
        ]);
    }

    public function rules(): array
    {
        /** @var Divisi $divisi */
        $divisi = $this->route('divisi');
        $divisiId = $divisi instanceof Divisi ? $divisi->id : $divisi;

        $allowedColors = array_keys(Divisi::AVAILABLE_COLORS);

        return [
            'nama' => 'required|string|max:100',
            'kode' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                Rule::unique('divisi', 'kode')->ignore($divisiId),
            ],
            'deskripsi' => 'nullable|string|max:1000',
            'color' => ['required', 'string', Rule::in($allowedColors)],
            'is_active' => 'boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'Nama Divisi / Departemen',
            'kode' => 'Kode Identifikasi Divisi (Slug)',
            'deskripsi' => 'Deskripsi / Tanggung Jawab Divisi',
            'color' => 'Aksen Warna Badge',
            'is_active' => 'Status Aktif',
        ];
    }

    public function messages(): array
    {
        return [
            'kode.regex' => 'Kode divisi hanya boleh memuat huruf, angka, garis bawah (_), atau tanda strip (-).',
            'kode.unique' => 'Kode divisi ini sudah terdaftar. Silakan gunakan kode unik yang lain.',
        ];
    }
}
