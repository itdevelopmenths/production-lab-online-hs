<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Satu user per role SC Online (PRD §2). Password default: "password".
        $users = [
            ['Administrator', 'admin@heavenscent.id', 'manager', 'manajemen', 'global'],
            ['Andyka (Manager)', 'manager@heavenscent.id', 'manager', 'manajemen', 'global'],
            ['Rina (Purchasing)', 'purchasing@heavenscent.id', 'purchasing', 'purchasing', 'global'],
            ['Budi (Gudang)', 'gudang@heavenscent.id', 'gudang', 'gudang', 'restricted'],
            ['Dedi (Operasional)', 'operasional@heavenscent.id', 'operasional', 'produksi', 'restricted'],
            ['Sari (Fulfillment)', 'fulfillment@heavenscent.id', 'fulfillment', 'fulfillment', 'restricted'],
        ];

        $divisiMap = \App\Models\Divisi::pluck('id', 'kode')->all();

        foreach ($users as [$name, $email, $role, $divisi, $accessType]) {
            $divisiId = $divisiMap[$divisi] ?? null;
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'divisi' => $divisi, 'divisi_id' => $divisiId, 'warehouse_access_type' => $accessType, 'password' => bcrypt('password')],
            );
            $user->update(['divisi' => $divisi, 'divisi_id' => $divisiId, 'warehouse_access_type' => $accessType]);
            $user->syncRoles([$role]);
        }
    }
}
