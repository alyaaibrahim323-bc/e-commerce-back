<?php

// app/Http/Controllers/Api/FavoriteController.php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function toggleFavorite(Request $request, Product $product)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $existing = Favorite::where('user_id', $user->id)
                                ->where('product_id', $product->id)
                                ->first();

            if ($existing) {
                $existing->delete();
                return response()->json(['message' => 'تمت الإزالة من المفضلة']);
            } else {
                Favorite::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id
                ]);
                return response()->json(['message' => 'تمت الإضافة إلى المفضلة']);
            }
        }

        $guestUuid = $request->cookie('guest_uuid') ?? Str::uuid();
        $existing = Favorite::where('guest_uuid', $guestUuid)
                            ->where('product_id', $product->id)
                            ->first();

        if ($existing) {+
            $existing->delete();
            $response = response()->json(['message' => 'تمت الإزالة من المفضلة']);
        } else {
            Favorite::create([
                'guest_uuid' => $guestUuid,
                'product_id' => $product->id
            ]);
            $response = response()->json(['message' => 'تمت الإضافة إلى المفضلة']);
        }

        return $response->cookie('guest_uuid', $guestUuid, 60*24*30);
    }

    public function getFavorites(Request $request) {
        if (auth()->check()) {
            $favorites = Favorite::with('product')->get();
        } else {
            $guestUuid = $request->cookie('guest_uuid');
            $favorites = Favorite::where('guest_uuid', $guestUuid)->with('product')->get();
        }

        return response()->json(['data' => $favorites]);
    }

    public function getFavoriteProducts(Request $request) {
        if (auth()->check()) {
            $products = Product::whereHas('favorites', function ($query) {
                $query->where('user_id', auth()->id());
            })->get();
        } else {
            $guestUuid = $request->cookie('guest_uuid');
            $products = Product::whereHas('favorites', function ($query) use ($guestUuid) {
                $query->where('guest_uuid', $guestUuid);
            })->get();
        }

        return response()->json(['data' => $products]);
    }

}
