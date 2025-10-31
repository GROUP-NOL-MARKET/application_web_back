<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PromoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('promos')->insert([
            [
                'product_id' => 1,
                'initial_price' => 15000,
                'new_price' => 12000,
                'pourcentage_vendu' => 40,
                'start_at' => $now,
                'end_at' => $now->copy()->addDays(5),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'product_id' => 2,
                'initial_price' => 20000,
                'new_price' => 16000,
                'pourcentage_vendu' => 70,
                'start_at' => $now,
                'end_at' => $now->copy()->addDays(7),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'product_id' => 3,
                'initial_price' => 10000,
                'new_price' => 8500,
                'pourcentage_vendu' => 55,
                'start_at' => $now,
                'end_at' => $now->copy()->addDays(3),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'product_id' => 4,
                'initial_price' => 25000,
                'new_price' => 20000,
                'pourcentage_vendu' => 20,
                'start_at' => $now,
                'end_at' => $now->copy()->addDays(10),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
