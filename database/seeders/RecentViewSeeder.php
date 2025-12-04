<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RecentView;
use App\Models\User;
use App\Models\Product;
use Faker\Factory as Faker;

class RecentViewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Récupérer tous les utilisateurs et produits existants
        $users = User::all();
        $products = Product::all();

        // Si pas d’utilisateurs ou de produits, on arrête
        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->warn(' Aucun utilisateur ou produit trouvé — impossible de créer des vues récentes.');
            return;
        }

        // Créer 20 vues récentes aléatoires
        for ($i = 0; $i < 20; $i++) {
            RecentView::create([
                'user_id' => $users->random()->id,
                'product_id' => $products->random()->id,
                'expires_at' => $faker->dateTimeBetween('now', '14 days'),
                'viewed_at' => $faker->dateTimeBetween('-10 days', 'now'),
                'created_at' => $faker->dateTimeBetween('-10 days', 'now'),
            ]);
        }
    }
}