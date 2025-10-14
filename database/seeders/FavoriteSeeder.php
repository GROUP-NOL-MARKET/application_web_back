<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Favorite;
use App\Models\User;
use App\Models\Product;
use Faker\Factory as Faker;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $user = User::first();
        $products = Product::all();

        if ($user && $products->count()) {
            foreach ($products->random(min(10, $products->count())) as $product) {
                Favorite::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                ]);
            }
        }
    }
}
