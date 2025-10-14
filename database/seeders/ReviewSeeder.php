<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\User;
use App\Models\Order;
use Faker\Factory as Faker;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        $users = User::all();
        $orders = Order::all();

        if ($users->isEmpty() || $orders->isEmpty()) {
            return;
        }

        foreach (range(1, 10) as $i) {
            Review::create([
                'user_id' => $users->random()->id,
                'order_id' => $orders->random()->id,
                'content' => $faker->sentence(10),
                'rating' => $faker->numberBetween(3, 5),
            ]);
        }
    }
}
