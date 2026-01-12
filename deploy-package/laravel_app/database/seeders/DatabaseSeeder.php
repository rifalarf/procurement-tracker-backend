<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            StatusSeeder::class,
            AdminUserSeeder::class,
            // Note: BuyerSeeder and BuyerUserSeeder removed
            // Users should be imported via Admin Dashboard after deployment
        ]);
    }
}
