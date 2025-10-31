<?php

namespace Database\Seeders;

use App\Models\Admin;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('jjXtLN26@')
        ]);

        Admin::create([
            'name' => 'Admin',
            'email' => 'groupnol@market.com',
            'password' => Hash::make('Nol229'),
        ]);

        $this->call([
            MessageSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
            PromoSeeder::class,
            VoucherSeeder::class,
            ReviewSeeder::class,
            FavoriteSeeder::class,
            RecentViewSeeder::class,
        ]);
    }
}
