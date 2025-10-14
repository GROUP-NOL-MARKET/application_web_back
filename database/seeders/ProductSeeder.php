<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // On génère 15 produits de test
        for ($i = 0; $i < 15; $i++) {
            Product::create([
                'name' => $faker->words(3, true),
                'reference' => $faker->numberBetween(20000, 150000),
                'family' => $faker->randomElement([
                    'Produits Locaux',
                    'Produits frais',
                    'Boissons',
                ]),
                'disponibility' => $faker->randomElement([
                    'disponible',
                    'non disponible',
                ]),
                'category' => $faker->randomElement([
                    "Electroménager",
                    "Produits Locaux",
                    "Produits Frais",
                    "Epicerie",
                    "Droguerie",
                    "Divers",
                    "Boissons",

                ]),
                'sous_category' => $faker->randomElement(
                    [
                        'TV',
                        'Biscuits',
                        'Gâteaux',
                        'Poudre',
                    ]
                ),
                'price' => $faker->numberBetween(5000, 150000),
                'description' => $faker->paragraph(2),
                'image' => $faker->imageUrl(640, 480, 'technics', true, 'Produit'),
            ]);
        }
    }
}
