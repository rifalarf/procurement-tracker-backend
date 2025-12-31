<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BuyerUserSeeder extends Seeder
{
    public function run(): void
    {
        $pbj1 = Department::where('name', 'PBJ1')->first();
        $pbj2 = Department::where('name', 'PBJ2')->first();

        $data = [
            'PBJ1' => [
                'Ato Heryanto', 'Cholida Maranani', 'Dicky Setiagraha', 'Erik Erdiana', 
                'Gugun GT', 'Heru Winata Praja', 'Mutia Virgiana', 'Nawang Wulan', 'Tathu RA'
            ],
            'PBJ2' => [
                'Akbar Faturahman', 'Ato Heryanto', 'Dian Sholihat', 'Dicky Setiagraha', 
                'Eggy Baharudin', 'Erik Erdiana', 'Erwin Herdiana', 'Gugun GT', 
                'Heru Winata Praja', 'Mutia Virgiana', 'Tathu RA'
            ]
        ];

        foreach ($data as $deptName => $users) {
            $department = ($deptName === 'PBJ1') ? $pbj1 : $pbj2;
            
            if (!$department) continue;

            foreach ($users as $name) {
                // Generate username: lowercase, no spaces
                $username = Str::lower(str_replace(' ', '', $name));
                
                $user = User::updateOrCreate(
                    ['username' => $username],
                    [
                        'name' => $name,
                        'password' => Hash::make($username),
                        'role' => 'buyer',
                        'is_active' => true,
                    ]
                );

                // Attach to department if not already attached
                if (!$user->departments()->where('departments.id', $department->id)->exists()) {
                    $user->departments()->attach($department->id);
                }
            }
        }
    }
}
