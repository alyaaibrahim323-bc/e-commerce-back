<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{// إضافة الثوابت هنا بدلاً من تعريفها كدوال
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    public function checkout(Request $request)
    {
        return DB::transaction(function () use ($request) {
            // 1. جلب عناصر السلة
            $cartItems = $this->getCartItems($request);

            // تسجيل للفحص
            Log::info('Cart items at checkout', [
                'user_id' => Auth::id(),
                'guest_uuid' => $this->getGuestUuid($request),
                'count' => $cartItems->count(),
            ]);

            // 2. التحقق من أن السلة ليست فارغة
            if ($cartItems->isEmpty()) {
                Log::error('Empty cart during checkout', [
                    'user_id' => Auth::id(),
                    'guest_uuid' => $this->getGuestUuid($request)
                ]);
                return response()->json(['message' => 'السلة فارغة'], 400);
            }

            // 3. التحقق من توفر الكمية
            $this->validateStock($cartItems);

            // 4. حساب الإجمالي
            $total = $this->calculateTotal($cartItems);

            // 5. إنشاء الطلب
            $order = Order::create([
                'user_id' => Auth::id(),
                'guest_uuid' => $this->getGuestUuid($request),
                'total' => $total,
                'status' => self::STATUS_PENDING,
                'address_id' => $request->address_id ?? null
            ]);


            // 6. إضافة العناصر
            foreach ($cartItems as $cartItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->product->price,
                ]);

                // 7. خصم الكمية من المخزون
                $cartItem->product->decrement('stock', $cartItem->quantity);
            }

            // 8. حذف السلة
            $this->clearCart($request);

            // 9. تسجيل تتبع الحالة الأولي
            OrderTracking::create([
                'order_id' => $order->id,
                'status' => self::STATUS_PENDING,
                'notes' => 'تم إنشاء الطلب'
            ]);

            // 10. إرجاع الطلب
            return response()->json([
                'message' => 'تم إنشاء الطلب بنجاح',
                'order' => $order->load('orderItems.product')
            ]);
        });
    }

   public function trackOrder($orderId)
{
    $order = Order::with(['orderItems.product', 'payment', 'trackingHistory'])
                ->find($orderId);

    if (!$order) {
        return response()->json(['message' => 'الطلب غير موجود'], 404);
    }

    if (Auth::check() && Auth::id() !== $order->user_id) {
        return response()->json(['message' => 'غير مصرح بالوصول لهذا الطلب'], 403);
    }

    if (!Auth::check() && $order->guest_uuid !== request()->cookie('guest_uuid')) {
        return response()->json(['message' => 'غير مصرح بالوصول لهذا الطلب'], 403);
    }

    return response()->json([
        'order_id' => $order->id,
        'current_status' => $order->status,
        'tracking_number' => $order->tracking_number,
        'last_updated' => $order->updated_at,
        'tracking_history' => $order->trackingHistory,
        'order_details' => [
            'total' => $order->total,
            'items' => $order->orderItems,
            'payment_status' => $order->payment->status ?? 'unknown'
        ]
    ]);
}

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:shipped,delivered',
            'tracking_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:255'
        ]);

        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'غير مصرح بهذا الإجراء'], 403);
        }

        $order->update(['status' => $request->status]);

        $tracking = OrderTracking::create([
            'order_id' => $order->id,
            'status' => $request->status,
            'notes' => $request->notes ?? 'تحديث حالة الطلب'
        ]);

        if ($request->tracking_number) {
            $order->update(['tracking_number' => $request->tracking_number]);
        }

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'new_status' => $order->status,
            'tracking_number' => $order->tracking_number,
            'tracking_record' => $tracking
        ]);
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

    private function validateStock($cartItems)
    {
        foreach ($cartItems as $item) {
            if ($item->product->stock < $item->quantity) {
                abort(422, "الكمية غير متوفرة لـ {$item->product->name}");
            }
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
    private function getGuestUuid(Request $request)
    {
        return $request->cookie('guest_uuid');
    }

    private function clearCart(Request $request)
    {
        if (Auth::check()) {
            Auth::user()->cartItems()->delete();
        } else {
            $guestUuid = $this->getGuestUuid($request);
            if ($guestUuid) {
                Cart::where('guest_uuid', $guestUuid)->delete();
            }
        }
    }
}
