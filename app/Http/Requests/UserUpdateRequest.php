<?php

namespace App\Http\Requests;

use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(PermissionCatalogService $catalogService): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$userId}",
            'divisi' => 'nullable|string|max:100',
            'divisi_id' => 'nullable|exists:divisi,id',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'warehouse_access_type' => 'nullable|in:global,restricted',
            'gudang_ids' => 'nullable|array',
            'gudang_ids.*' => 'exists:gudang,id',
            'primary_gudang_id' => 'nullable|exists:gudang,id',
            'permissions' => 'nullable|array',
            'permissions.*' => $catalogService->permissionItemRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.*.in' => 'Wewenang yang dipilih (:input) tidak terdaftar dalam katalog sistem.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'divisi' => 'Divisi',
            'password' => 'Kata Sandi',
            'role' => 'Peran',
            'warehouse_access_type' => 'Tipe Akses Gudang',
            'gudang_ids' => 'Fasilitas Gudang',
            'primary_gudang_id' => 'Gudang Utama',
            'permissions' => 'Wewenang Khusus',
        ];
    }
}
