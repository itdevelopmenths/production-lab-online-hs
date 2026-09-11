<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('role.manage') ?? false;
    }

    public function rules(): array
    {
        $roleParam = $this->route('role');
        $role = $roleParam instanceof Role ? $roleParam : Role::findOrFail($roleParam);

        $rules = [
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];

        // Hanya peran non-sistem yang dapat memperbarui kode identitas name
        if (!$role->is_system) {
            $rules['name'] = [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_\-\s]+$/',
                Rule::unique('roles', 'name')->ignore($role->id),
            ];
        }

        return $rules;
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
