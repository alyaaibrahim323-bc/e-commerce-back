<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        $name = $this->faker->unique()->word; // ضمان اسم فريد
        return [
            'uuid' => Str::uuid(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name . '-' . Str::random(5)), // إضافة جزء عشوائي لتجنب التكرار
            'description' => $this->faker->sentence(10),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'discount_price' => $this->faker->optional()->randomFloat(2, 5, 900),
            'stock' => $this->faker->numberBetween(0, 100),
            'category_id' => \App\Models\Category::inRandomOrder()->first()->id ?? 1,
            'images' => json_encode([
                'https://via.placeholder.com/300x300.png?text=Product+Image',
                'https://via.placeholder.com/400x400.png?text=Product+Image'
            ]),
            'is_active' => $this->faker->boolean(),
        ];
    }
}


