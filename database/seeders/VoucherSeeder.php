<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Voucher;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $user = User::first();

        for ($i = 0; $i < 10; $i++) {
            Voucher::create([
                'user_id' => $user->id,
                'title' => $faker->sentence(3),
                'sub_title' => $faker->sentence(6),
                'code' => strtoupper($faker->lexify('BON????')),
                'valeur' => $faker->numberBetween(1000, 10000),
                'date' => $faker->date(),
                'until' => $faker->date(),
                'status' => $faker->randomElement(['actif', 'inactif']),
            ]);
        }
    }
}
