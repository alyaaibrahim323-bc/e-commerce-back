<?php

// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;



class Product extends Model
{
    use HasFactory;




    protected $fillable = [
    'uuid',
    'name',
    'slug',
    'description',
    'price',
    'discount_price',
    'stock',
    'category_id',
    'images',
    'is_active',
];

protected $casts = [
    'images' => 'array',
];

public function category() {
    return $this->belongsTo(Category::class);
}

public function getFinalPriceAttribute() {
    return $this->discount_price ?? $this->price;
}

public function favorites() {
    return $this->hasMany(Favorite::class);
}


    public function orderItems() {
        return $this->hasMany(OrderItem::class);
    }

    public function getFinalPriceAttribute()
    {
        return $this->discount_price ?: $this->price;
    }

    public function getIsAvailableAttribute()
    {
        return $this->is_active && $this->stock > 0;
    }
}
