<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class CartController extends Controller
{
    public function getCart(Request $request)
    {
        $cartItems = $this->getCartItems($request);
        $total = $this->calculateTotal($cartItems);

        return response()->json([
            'data' => $cartItems,
            'total' => $total
        ]);
    }
     public function addToCart(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:' . $product->stock,
            'options' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (Auth::check()) {
            $cartItem = Cart::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'product_id' => $product->id
                ],
                [
                    'quantity' => $request->quantity,
                    'options' => $request->options
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'تمت إضافة المنتج إلى عربة التسوق',
                'data' => $cartItem
            ]);
        }

        $guestUuid = $request->cookie('guest_uuid') ?? \Illuminate\Support\Str::uuid();

        $cartItem = Cart::updateOrCreate(
            [
                'guest_uuid' => $guestUuid,
                'product_id' => $product->id
            ],
            [
                'quantity' => $request->quantity,
                'options' => $request->options
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة المنتج إلى عربة التسوق',
            'data' => $cartItem
        ])->cookie('guest_uuid', $guestUuid, 60*24*30);
    }

    public function updateCart(Request $request, Product $product)
{
    $request->validate([
        'quantity' => 'required|integer|min:1'
    ]);

    if (Auth::check()) {
        $cartItem = Cart::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'product_id' => $product->id
            ],
            ['quantity' => $request->quantity]
        );
        return response()->json($cartItem);
    }

    $guestUuid = $request->cookie('guest_uuid') ?? Str::uuid();

    $cartItem = Cart::updateOrCreate(
        [
            'guest_uuid' => $guestUuid,
            'product_id' => $product->id
        ],
        ['quantity' => $request->quantity]
    );

    return response()->json($cartItem)
        ->cookie('guest_uuid', $guestUuid, 60*24*30, null, null, false, true);
}

    public function updateQuantity(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:' . $product->stock
        ]);

        $cartItem = $this->getCartItem($product->id, $request);
        if (!$cartItem) {
            return response()->json(['message' => 'المنتج غير موجود في السلة'], 404);
        }

        $cartItem->update(['quantity' => $request->quantity]);
        return response()->json(['message' => 'تم تحديث الكمية بنجاح']);
    }

    public function removeItem(Product $product, Request $request)
    {
        $cartItem = $this->getCartItem($product->id, $request);
        if (!$cartItem) {
            return response()->json(['message' => 'المنتج غير موجود في السلة'], 404);
        }

        $cartItem->delete();
        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    // ------ الدوال المساعدة ------ //

    private function getCartItems(Request $request)
    {
        if (Auth::check()) {
            return Auth::user()->cartItems()->with('product')->get();
        } else {
            $guestUuid = $this->getGuestUuid($request);
            return $guestUuid ? Cart::with('product')->where('guest_uuid', $guestUuid)->get() : collect();
        }
    }

    private function calculateTotal($cartItems)
    {
        return $cartItems->sum(function ($item) {
            $price = $item->product->price;
            $discount = $item->product->discount ?? 0;
            $discountedPrice = $price - ($price * $discount / 100);
            return $discountedPrice * $item->quantity;
        });
    }

    private function getCartItem($productId, Request $request)
    {
        if (Auth::check()) {
            return Auth::user()->cartItems()->where('product_id', $productId)->first();
        } else {
            $guestUuid = $this->getGuestUuid($request);
            return $guestUuid ? Cart::where('guest_uuid', $guestUuid)->where('product_id', $productId)->first() : null;
        }
    }

    private function getGuestUuid(Request $request)
    {
        return $request->cookie('guest_uuid');
    }

    private function createGuestUuid()
    {
        return Str::uuid();
    }
}
