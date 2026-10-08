<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo login for the web UI (HTTP basic auth) and a small furniture catalogue
     * that matches storage/samples/prices-sample.csv.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Pricing Team',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $catalogue = [
            ['SOFA-3S-GRY', 'Harlow 3 Seater Sofa, Grey', 89900, null],
            ['SOFA-2S-GRN', 'Harlow 2 Seater Sofa, Sage', 74900, null],
            ['BED-KS-OAK', 'Elm King Size Bed Frame, Oak', 54900, 64900],
            ['BED-DB-WHT', 'Elm Double Bed Frame, White', 44900, null],
            ['MAT-KS-HYB', 'Hybrid Pocket King Mattress', 69900, null],
            ['MAT-DB-FOAM', 'Memory Foam Double Mattress', 39900, null],
            ['TBL-DIN-6', 'Oslo 6 Seater Dining Table', 59900, null],
            ['CHR-DIN-NAT', 'Oslo Dining Chair, Natural', 12900, null],
            ['LMP-TBL-CER', 'Ceramic Table Lamp', 5500, null],
            ['CUS-LIN-SGE', 'Linen Cushion, Sage', 2400, null],
            ['THR-KNT-OAT', 'Chunky Knit Throw, Oatmeal', 3900, null],
            ['RUG-JUT-160', 'Jute Rug 160 x 230', 14900, null],
        ];

        foreach ($catalogue as [$sku, $name, $price, $compareAt]) {
            Product::create(['sku' => $sku, 'name' => $name, 'price_pence' => $price, 'compare_at_pence' => $compareAt]);
        }

        Product::factory(40)->create();
    }
}
