<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            DivisiSeeder::class,
            UserSeeder::class,
            UomSeeder::class,
            MasterDataSeeder::class,
        ]);

        if (!app()->environment('testing')) {
            $this->call(ProductVarianBomSeeder::class);
        }
    }
}
