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

        // Définition des catégories et leurs sous-catégories
        $categories = [
            "Electroménager" => ["Matériels Nasco"],
            "Produits Locaux" => ["Produits locaux"],
            "Produits Frais" => [
                "Fromages-Fruits frais-Légumes",
                "yaourt",
                "Produits congélés",
                "Surgélés-Crêmerie fraîche",
                "Glâces et crêmes glacées",
                "Charcuterie volaille poisson",
                "Produits Locaux frais",
            ],
            "Epicerie" => [
                "Petit déjeuner",
                "Céréales-Corn Flakes-pain grillé",
                "Biscuits gâteaux",
                "Amuse gueules",
                "Pains et viennoiseries",
                "Bonbons-chocolat",
                "Conserves-plats cuisinés",
                "Pâtes alimentaires-riz-purée",
                "Assaisonnement-condiments",
                "Huile-vinaigre",
                "Sardine",
                "Produits du monde",
            ],
            "Droguerie" => [
                " Monde de Bébé",
                "Prêt à porter",
                "Fournitures scolaires",
                "Hygiène dentaire",
                "Rasage",
                "Produits ménagers",
                "Soins de beauté",
                "Mouchoirs",
                "Désodorisant-insecticide",
                "Hygiène féminine",
            ],
            "Divers" => ["Chewing Gum", "Piles-rasoirs", "Papeterie", "Ampoule"],
            "Boissons" => [
                "Vins",
                "Spiriteux",
                "Jus de fruits",
                "Eaux minérales",
                "Sirop",
                "Soft Drink",
                "Cidre",
                "Champagnes",
                "Bière et panaché",
            ],
            "Animalerie" => [
                "Nourriture pour chiens et chats"
            ]
        ];

        foreach ($categories as $category => $subCategories) {
            foreach ($subCategories as $subCategory) {

                // Génère entre 1 et 5 produits par sous-catégorie
                $productCount = rand(1, 5);

                for ($i = 0; $i < $productCount; $i++) {
                    Product::create([
                        'name' => ucfirst($faker->words(3, true)),
                        'reference' => $faker->unique()->numberBetween(20000, 150000),
                        'family' => $faker->randomElement([
                            'Produits Locaux',
                            'Produits frais',
                            'Boissons',
                        ]),
                        'disponibility' => $faker->randomElement(['disponible', 'non disponible']),
                        'category' => $category,
                        'sous_category' => $subCategory,
                        'price' => $faker->numberBetween(500, 150000),
                        'quantity' => $faker->numberBetween(500, 1500),
                        'selled' => $faker->numberBetween(500, 1500),
                        'description' => $faker->sentence(12),
                        'image' => $faker->imageUrl(640, 480, 'products', true, $category),
                    ]);
                }
            }
        }
    }
}
