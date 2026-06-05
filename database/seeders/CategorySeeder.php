<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $categories = [
            ['category_name' => 'Electronics',      'icon_identifier' => 'electronics'],
            ['category_name' => 'Wallets',          'icon_identifier' => 'wallet'],
            ['category_name' => 'Keys',             'icon_identifier' => 'keys'],
            ['category_name' => 'IDs',              'icon_identifier' => 'id-card'],
            ['category_name' => 'Accessories',      'icon_identifier' => 'accessory'],
            ['category_name' => 'Bags & Backpacks', 'icon_identifier' => 'bag'],
            ['category_name' => 'Clothing',         'icon_identifier' => 'clothing'],
            ['category_name' => 'Books',            'icon_identifier' => 'book'],
            ['category_name' => 'Stationery',       'icon_identifier' => 'stationery'],
            ['category_name' => 'Others',           'icon_identifier' => 'other'],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['category_name' => $category['category_name']],
                [
                    'icon_identifier' => $category['icon_identifier'],
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]
            );
        }
    }
}
