<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'PBJ1', 'description' => 'Pengadaan Barang dan Jasa 1'],
            ['name' => 'PBJ2', 'description' => 'Pengadaan Barang dan Jasa 2'],
            ['name' => 'PJDP', 'description' => 'Pengadaan Jasa Distribusi & Sarana'],
            ['name' => 'VM', 'description' => 'Vendor Management & Expediting'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['name' => $dept['name']],
                $dept
            );
        }
    }
}
