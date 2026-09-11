<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('role.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_\-\s]+$/|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Kode Identifikasi Peran (Key)',
            'display_name' => 'Nama Tampilan Peran',
            'description' => 'Deskripsi & Catatan Tanggung Jawab',
            'permissions' => 'Daftar Wewenang',
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Kode identifikasi peran hanya boleh memuat huruf, angka, garis bawah (_), atau strip (-).',
            'name.unique' => 'Kode peran ini sudah terdaftar. Silakan gunakan kode peran yang lain.',
        ];
    }
}
