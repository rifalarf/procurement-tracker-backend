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
        // PBJ1
        'Dicky Setiagraha' => ['color' => '#fef9c3', 'text_color' => '#854d0e'], // Yellow
        'Ato Heryanto' => ['color' => '#dcfce7', 'text_color' => '#166534'],     // Green
        'Cholida Maranani' => ['color' => '#dbeafe', 'text_color' => '#1e40af'],  // Blue
        'Heru Winata Praja' => ['color' => '#ffedd5', 'text_color' => '#9a3412'], // Orange
        'Eva Sepsilia Sari' => ['color' => '#f3e8ff', 'text_color' => '#6b21a8'],  // Purple
        'Nawang Wulan Jannatul Firdaus' => ['color' => '#f1f5f9', 'text_color' => '#334155'], // Gray

        // PBJ2
        'Gugun Gunara Taupik' => ['color' => '#dcfce7', 'text_color' => '#166534'],  // Green
        'Dian Sholihat' => ['color' => '#f3e8ff', 'text_color' => '#6b21a8'],        // Purple
        'Erwin Herdiyana' => ['color' => '#ffedd5', 'text_color' => '#9a3412'],      // Orange
        'Tathu Rabiatul A' => ['color' => '#fef9c3', 'text_color' => '#854d0e'],     // Yellow
        'Mutia Virginia' => ['color' => '#fef9c3', 'text_color' => '#854d0e'],       // Yellow

        // PJDP
        'Ade Sunarya' => ['color' => '#f3e8ff', 'text_color' => '#6b21a8'],          // Purple
        'Maryono' => ['color' => '#dbeafe', 'text_color' => '#1e40af'],              // Blue
        'Aditya Pratama Putra' => ['color' => '#dcfce7', 'text_color' => '#166534'], // Green

        // VM
        'Annafi Rohadi' => ['color' => '#dbeafe', 'text_color' => '#1e40af'],        // Blue
        'Muhamad Adam Zamzami' => ['color' => '#ffedd5', 'text_color' => '#9a3412'], // Orange
        'Bambang Ahmad Makmur' => ['color' => '#dcfce7', 'text_color' => '#166534'], // Green
        'Rona Kurniawan' => ['color' => '#f3e8ff', 'text_color' => '#6b21a8'],       // Purple
        'Debora Geraldyn Br Tobing' => ['color' => '#fef9c3', 'text_color' => '#854d0e'], // Yellow

        // Dep. PBJ
        'Ronald Irwanto' => ['color' => '#dbeafe', 'text_color' => '#1e40af'],       // Blue
        'Dep. Pengadaan Barang & Jasa' => ['color' => '#f1f5f9', 'text_color' => '#334155'], // Gray

        // Staff
        'Titin Haryati' => ['color' => '#f3e8ff', 'text_color' => '#6b21a8'],        // Purple
    ];

    public function run(): void
    {
        // Get departments
        $pbj1 = Department::where('name', 'PBJ1')->first();
        $pbj2 = Department::where('name', 'PBJ2')->first();
        $pjdp = Department::where('name', 'PJDP')->first();
        $vm = Department::where('name', 'VM')->first();
        $depPbj = Department::where('name', 'Dep. PBJ')->first();
        $staff = Department::where('name', 'Staff')->first();

        // ========== ADMIN ==========
        $this->createUser('admin', 'Administrator', 'admin', null);

        // ========== AVP ==========
        $this->createUser('3032158', 'M. Dedy Arissandi', 'avp', $vm);
        $this->createUser('3942055', 'Andrisol', 'avp', $pbj1);
        $this->createUser('3123090', 'Guntur Gumilar', 'avp', $pbj2);
        $this->createUser('3143275', 'Dewi Yuliana Maharani', 'avp', $pjdp);

        // ========== BUYERS ==========

        // PJDP Buyers
        $this->createUser('3042327', 'Ade Sunarya', 'buyer', $pjdp);
        $this->createUser('3052374', 'Maryono', 'buyer', $pjdp);
        $this->createUser('3123163', 'Aditya Pratama Putra', 'buyer', $pjdp);

        // PBJ1 Buyers
        $this->createUser('3072505', 'Cholida Maranani', 'buyer', $pbj1);
        $this->createUser('3082563', 'Dicky Setiagraha', 'buyer', $pbj1);
        $this->createUser('3092794', 'Eva Sepsilia Sari', 'buyer', $pbj1);
        $this->createUser('3092810', 'Heru Winata Praja', 'buyer', $pbj1);
        $this->createUser('3942046', 'Ato Heryanto', 'buyer', $pbj1);
        $this->createUser('07221061', 'Nawang Wulan Jannatul Firdaus', 'buyer', $pbj1);

        // PBJ2 Buyers
        $this->createUser('3082603', 'Dian Sholihat', 'buyer', $pbj2);
        $this->createUser('3102923', 'Gugun Gunara Taupik', 'buyer', $pbj2);
        $this->createUser('3102929', 'Tathu Rabiatul A', 'buyer', $pbj2);
        $this->createUser('3102950', 'Erwin Herdiyana', 'buyer', $pbj2);
        $this->createUser('07221059', 'Mutia Virginia', 'buyer', $pbj2);

        // VM Buyers
        $this->createUser('3102945', 'Annafi Rohadi', 'buyer', $vm);
        $this->createUser('3123110', 'Muhamad Adam Zamzami', 'buyer', $vm);
        $this->createUser('3921960', 'Bambang Ahmad Makmur', 'buyer', $vm);
        $this->createUser('07150574', 'Rona Kurniawan', 'buyer', $vm);
        $this->createUser('07251218', 'Debora Geraldyn Br Tobing', 'buyer', $vm);

        // Dep. PBJ Buyers
        $this->createUser('3123084', 'Ronald Irwanto', 'buyer', $depPbj);
        $this->createUser('C006210000', 'Dep. Pengadaan Barang & Jasa', 'buyer', $depPbj);

        // Staff Buyers
        $this->createUser('05070275', 'Titin Haryati', 'buyer', $staff);
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
            $r = $c;
            $g = $x;
            $b = 0;
        } elseif ($h < 120) {
            $r = $x;
            $g = $c;
            $b = 0;
        } elseif ($h < 180) {
            $r = 0;
            $g = $c;
            $b = $x;
        } elseif ($h < 240) {
            $r = 0;
            $g = $x;
            $b = $c;
        } elseif ($h < 300) {
            $r = $x;
            $g = 0;
            $b = $c;
        } else {
            $r = $c;
            $g = 0;
            $b = $x;
        }

        $r = round(($r + $m) * 255);
        $g = round(($g + $m) * 255);
        $b = round(($b + $m) * 255);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
