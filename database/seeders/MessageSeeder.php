<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Message;
use App\Models\User;
use Faker\Factory as Faker;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Optionnel : récupère un user existant
        $user = User::first();

        for ($i = 0; $i < 10; $i++) {
            Message::create([
                'user_id' => $user ? $user->id : 1, // ou null si tu veux
                'sender' => $faker->company,
                'title' => $faker->sentence(5),
                'content' => $faker->paragraph(3),
            ]);
        }
    }
}
