<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Buyer;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Password untuk semua akun
     */
    private const DEFAULT_PASSWORD = 'Password@!';

    /**
     * Warna untuk buyer badges
     */
    private array $buyerColors = [
        'Dian Sholihat' => ['color' => '#e8eaed', 'text_color' => '#000000'],
        'Tathu RA' => ['color' => '#d4edbc', 'text_color' => '#000000'],
        'Eva Sepsilia Sari' => ['color' => '#ffcfc9', 'text_color' => '#000000'],
        'Ato Heryanto' => ['color' => '#ffc8aa', 'text_color' => '#000000'],
        'Mail Marzuki' => ['color' => '#ffe5a0', 'text_color' => '#000000'],
        'Mutia Virgiana' => ['color' => '#bfe1f6', 'text_color' => '#000000'],
        'Ade Sunarya' => ['color' => '#e8eaed', 'text_color' => '#000000'],
        'Gugun GT' => ['color' => '#e6cff2', 'text_color' => '#000000'],
        'Erik Erdiana' => ['color' => '#3d3d3d', 'text_color' => '#ffffff'],
        'Dicky Setiagraha' => ['color' => '#b10202', 'text_color' => '#ffffff'],
        'Erwin Herdiana' => ['color' => '#753800', 'text_color' => '#ffffff'],
        'Akbar Faturahman' => ['color' => '#473822', 'text_color' => '#ffffff'],
        'Eggy Baharudin' => ['color' => '#11734b', 'text_color' => '#ffffff'],
        'Heru Winata Praja' => ['color' => '#0a53a8', 'text_color' => '#ffffff'],
        'Nawang Wulan' => ['color' => '#215a6c', 'text_color' => '#ffffff'],
        'Cholida Maranani' => ['color' => '#5a3286', 'text_color' => '#ffffff'],
    ];

    public function run(): void
    {
        // Get departments
        $pbj1 = Department::where('name', 'PBJ1')->first();
        $pbj2 = Department::where('name', 'PBJ2')->first();
        $pjdp = Department::where('name', 'PJDP')->first();
        $vm = Department::where('name', 'VM')->first();

        // ========== ADMIN ==========
        $this->createUser('admin', 'Administrator', 'admin', null);
        $this->createUser('7150574', 'Rona Kurniawan', 'admin', $vm);

        // ========== STAFF ==========
        $this->createUser('3123084', 'Ronald Irwanto', 'staff', null);
        $this->createUser('05070275', 'Titin Haryati', 'staff', null);

        // ========== AVP ==========
        $this->createUser('3942055', 'Andrisol', 'avp', $pbj1);
        $this->createUser('3123090', 'Guntur Gumilar', 'avp', $pbj2);

        // ========== BUYERS (16 orang sesuai gambar) ==========
        
        // PBJ1 Buyers
        $this->createUser('3082563', 'Dicky Setiagraha', 'buyer', $pbj1);
        $this->createUser('3942046', 'Ato Heryanto', 'buyer', $pbj1);
        $this->createUser('3072505', 'Cholida Maranani', 'buyer', $pbj1);
        $this->createUser('3092810', 'Heru Winata Praja', 'buyer', $pbj1);
        $this->createUser('3092794', 'Eva Sepsilia Sari', 'buyer', $pbj1);
        $this->createUser('07221061', 'Nawang Wulan', 'buyer', $pbj1);

        // PBJ2 Buyers
        $this->createUser('3102923', 'Gugun GT', 'buyer', $pbj2);
        $this->createUser('3082603', 'Dian Sholihat', 'buyer', $pbj2);
        $this->createUser('3102950', 'Erwin Herdiana', 'buyer', $pbj2);
        $this->createUser('3102929', 'Tathu RA', 'buyer', $pbj2);
        $this->createUser('07221058', 'Erik Erdiana', 'buyer', $pbj2);
        $this->createUser('04231222', 'Eggy Baharudin', 'buyer', $pbj2);
        $this->createUser('07221059', 'Mutia Virgiana', 'buyer', $pbj2);

        // PJDP Buyers
        $this->createUser('3102942', 'Mail Marzuki', 'buyer', $pjdp);
        $this->createUser('3042327', 'Ade Sunarya', 'buyer', $pjdp);
        $this->createUser('3102955', 'Akbar Faturahman', 'buyer', $pjdp);
    }

    /**
     * Create or update user with department assignment and buyer record
     */
    private function createUser(string $username, string $name, string $role, ?Department $department): void
    {
        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => $role,
                'is_active' => true,
            ]
        );

        // Attach to department if provided and not already attached
        if ($department && !$user->departments()->where('departments.id', $department->id)->exists()) {
            $user->departments()->attach($department->id);
        }

        // Create buyer record if role is buyer
        if ($role === 'buyer') {
            $colors = $this->buyerColors[$name] ?? $this->generateRandomColor();
            
            Buyer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $name,
                    'color' => $colors['color'],
                    'text_color' => $colors['text_color'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Generate random pastel color for buyers without predefined colors
     */
    private function generateRandomColor(): array
    {
        $hue = rand(0, 360);
        $saturation = rand(25, 45);
        $lightness = rand(75, 85);
        
        $color = $this->hslToHex($hue, $saturation, $lightness);
        
        return [
            'color' => $color,
            'text_color' => '#000000',
        ];
    }

    /**
     * Convert HSL to Hex color
     */
    private function hslToHex(int $h, int $s, int $l): string
    {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        if ($h < 60) {
            $r = $c; $g = $x; $b = 0;
        } elseif ($h < 120) {
            $r = $x; $g = $c; $b = 0;
        } elseif ($h < 180) {
            $r = 0; $g = $c; $b = $x;
        } elseif ($h < 240) {
            $r = 0; $g = $x; $b = $c;
        } elseif ($h < 300) {
            $r = $x; $g = 0; $b = $c;
        } else {
            $r = $c; $g = 0; $b = $x;
        }

        $r = round(($r + $m) * 255);
        $g = round(($g + $m) * 255);
        $b = round(($b + $m) * 255);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
