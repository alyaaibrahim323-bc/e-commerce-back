<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => 'إلكترونيات', 'slug' => 'electronics'],
            ['name' => 'ملابس', 'slug' => 'clothing'],
            ['name' => 'أحذية', 'slug' => 'shoes'],
            ['name' => 'أجهزة منزلية', 'slug' => 'home-appliances'],
            ['name' => 'مستلزمات التجميل', 'slug' => 'beauty'],
            ['name' => 'مستلزمات رياضية', 'slug' => 'sports'],
            ['name' => 'كتب', 'slug' => 'books'],
            ['name' => 'ألعاب أطفال', 'slug' => 'toys'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}

