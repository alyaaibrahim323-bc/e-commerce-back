<?php

// app/Http/Controllers/Api/ProductController.php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Http\Resources\ProductResource;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
     public function index(Request $request)
    {
        $query = Product::with('category')
            ->where('is_active', true);

        $this->applyFilters($query, $request);

        $products = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product->load('category'))
        ]);
    }

    public function productsByCategory(Category $category, Request $request)
    {
        $query = $category->products()
            ->where('is_active', true)
            ->with('category');

        $this->applyFilters($query, $request);

        $products = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug
            ],
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    // ======== الدوال المساعدة ======== //

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('in_stock') && $request->in_stock) {
            $query->where('stock', '>', 0);
        }

        $sort = $request->get('sort', 'newest');
        $sortOptions = [
            'newest' => ['created_at', 'desc'],
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'popular' => ['views', 'desc'],
        ];

        if (array_key_exists($sort, $sortOptions)) {
            $query->orderBy(...$sortOptions[$sort]);
        }
    }


    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|lt:price',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $images[] = asset("storage/$path");
            }
        }

        $product = Product::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'name' => $request->name,
            'slug' => \Illuminate\Support\Str::slug($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'discount_price' => $request->discount_price,
            'stock' => $request->stock,
            'category_id' => $request->category_id,
            'images' => $images,
        ]);

        return response()->json([
            'success' => true,
            'data' => $product,
        ], 201);
    }


    public function update(Request $request, Product $product) {


        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|lt:price',
            'stock' => 'sometimes|integer|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product->update($request->except('images'));

        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $images[] = asset("storage/$path");
            }
            $product->update(['images' => $images]);
        }

        return response()->json([
            'success' => true,
            'data' => $product->fresh(),
        ]);
    }

    public function destroy(Product $product) {

        if (!empty($product->images)) {
            $images = is_array($product->images) ? $product->images : json_decode($product->images, true);

            if (is_array($images)) {
                foreach ($images as $imageUrl) {
                    $path = str_replace(asset('storage/'), '', $imageUrl);
                    Storage::disk('public')->delete($path);
                }
            }
        }

        $product->delete();

        return response()->json(null, 204);
    }


    public function toggleFavorite(Request $request, Product $product) {

        $favorites = $request->session()->get('favorites', []);
        if (in_array($product->id, $favorites)) {
            $favorites = array_diff($favorites, [$product->id]);
        } else {
            $favorites[] = $product->id;
        }
        $request->session()->put('favorites', $favorites);

        return response()->json(['message' => 'تم التحديث']);
    }



}
