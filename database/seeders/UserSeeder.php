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
            ['Administrator', 'admin@heavenscent.id', 'manager'],
            ['Andyka (Manager)', 'manager@heavenscent.id', 'manager'],
            ['Rina (Purchasing)', 'purchasing@heavenscent.id', 'purchasing'],
            ['Budi (Gudang)', 'gudang@heavenscent.id', 'gudang'],
            ['Dedi (Operasional)', 'operasional@heavenscent.id', 'operasional'],
            ['Sari (Fulfillment)', 'fulfillment@heavenscent.id', 'fulfillment'],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => bcrypt('password')],
            );
            $user->syncRoles([$role]);
        }
    }
}
