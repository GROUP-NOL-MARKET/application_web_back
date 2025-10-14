<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first(); // Prend le premier utilisateur
        $products = Product::take(5)->get(); // Prend 5 produits existants

        if (!$user || $products->isEmpty()) {
            $this->command->warn('Aucun utilisateur ou produit trouvé. Exécute d’abord UserSeeder et ProductSeeder.');
            return;
        }

        // Crée 3 commandes pour l’utilisateur
        for ($i = 0; $i < 3; $i++) {
            // Sélectionne 2 à 3 produits aléatoires
            $selectedProducts = $products->random(rand(2, 3))->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name ?? 'Produit inconnu',
                    'quantite' => rand(1, 3),
                    'price' => $product->price ?? 1000,
                    'status' => ['en_cours', 'livrée', 'annulée'][rand(0, 2)],
                ];
            })->toArray();

            // Calcule le total
            $total = collect($selectedProducts)->sum(function ($p) {
                return $p['quantite'] * $p['price'];
            });

            // Crée la commande
            Order::create([
                'user_id' => $user->id,
                'produits' => $selectedProducts, // JSON automatiquement casté
                'total' => $total,
                'status' => ['en_attente', 'en_cours', 'livrée'][rand(0, 2)],
            ]);
        }

        $this->command->info('✅ Commandes créées avec succès avec produits JSON !');
    }
}
