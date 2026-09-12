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
            ['Administrator', 'admin@heavenscent.id', 'manager', 'manajemen'],
            ['Andyka (Manager)', 'manager@heavenscent.id', 'manager', 'manajemen'],
            ['Rina (Purchasing)', 'purchasing@heavenscent.id', 'purchasing', 'purchasing'],
            ['Budi (Gudang)', 'gudang@heavenscent.id', 'gudang', 'gudang'],
            ['Dedi (Operasional)', 'operasional@heavenscent.id', 'operasional', 'produksi'],
            ['Sari (Fulfillment)', 'fulfillment@heavenscent.id', 'fulfillment', 'fulfillment'],
        ];

        foreach ($users as [$name, $email, $role, $divisi]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'divisi' => $divisi, 'password' => bcrypt('password')],
            );
            $user->update(['divisi' => $divisi]);
            $user->syncRoles([$role]);
        }
    }
}
