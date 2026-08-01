<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note: model events are intentionally left enabled. Category relies on a
     * saving hook to generate its slug.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CatalogSeeder::class,
            StockMovementSeeder::class,
        ]);
    }
}
