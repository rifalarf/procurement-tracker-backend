<?php

namespace Database\Seeders;

use App\Models\Buyer;
use Illuminate\Database\Seeder;

class BuyerSeeder extends Seeder
{
    public function run(): void
    {
        $buyers = [
            ['name' => 'Dian Sholihat', 'color' => '#e8eaed'],
            ['name' => 'Tathu RA', 'color' => '#d4edbc'],
            ['name' => 'Eva Sepsilia Sari', 'color' => '#ffcfc9'],
            ['name' => 'Ato Heryanto', 'color' => '#ffc8aa'],
            ['name' => 'Mail Marzuki', 'color' => '#ffe5a0'],
            ['name' => 'Mutia Virgiana', 'color' => '#bfe1f6'],
            ['name' => 'Ade Sunarya', 'color' => '#e8eaed'],
            ['name' => 'Gugun GT', 'color' => '#e6cff2'],
            ['name' => 'Erik Erdiana', 'color' => '#3d3d3d'],
            ['name' => 'Dicky Setiagraha', 'color' => '#b10202'],
            ['name' => 'Erwin Herdiana', 'color' => '#753800'],
            ['name' => 'Akbar Faturahman', 'color' => '#473822'],
            ['name' => 'Eggy Baharudin', 'color' => '#11734b'],
            ['name' => 'Heru Winata Praja', 'color' => '#0a53a8'],
            ['name' => 'Nawang Wulan', 'color' => '#215a6c'],
            ['name' => 'Cholida Maranani', 'color' => '#5a3286'],
        ];

        foreach ($buyers as $buyer) {
            Buyer::updateOrCreate(
                ['name' => $buyer['name']],
                $buyer
            );
        }
    }
}
