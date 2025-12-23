<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
     const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_COMPLETED = 'completed';
    const PAYMENT_STATUS_FAILED = 'failed';

    const ORDER_STATUS_PROCESSING = 'processing';
    const ORDER_STATUS_CANCELLED = 'cancelled';
    private function getAuthToken()
    {
        $response = Http::post('https://accept.paymob.com/api/auth/tokens', [
            'api_key' => env('PAYMOB_API_KEY')
        ]);

        return $response->json('token');
    }

    private function createPaymobOrder($token, Order $order)
    {
        $response = Http::post('https://accept.paymob.com/api/ecommerce/orders', [
            'auth_token' => $token,
            'delivery_needed' => false,
            'amount_cents' => (int)($order->total * 100),
            'currency' => 'EGP',
            'merchant_order_id' => $order->id,
        ]);

        return $response->json('id');
    }

    private function getPaymentKey($token, $paymobOrderId, Order $order)
    {
        $billingData = [
            'first_name' => $order->user->name,
            'email' => $order->user->email,
            'phone_number' => $order->user->phone ?? '01000000000',
            'city' => 'Cairo',
            'country' => 'EG',
        ];

        $response = Http::post('https://accept.paymob.com/api/acceptance/payment_keys', [
            'auth_token' => $token,
            'amount_cents' => (int)($order->total * 100),
            'expiration' => 3600,
            'order_id' => $paymobOrderId,
            'billing_data' => $billingData,
            'currency' => 'EGP',
            'integration_id' => env('PAYMOB_CARD_INTEGRATION_ID'),
        ]);

        return $response->json('token');
    }

   public function initiatePayment(Request $request, Order $order)
   {
        $request->validate([
            'payment_method' => 'required|in:card,cash_on_delivery'
        ]);

        $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => $request->payment_method,
                'amount' => $order->total,
                'status' => self::PAYMENT_STATUS_PENDING,
                'transaction_id' => 'TEMP-' . Str::uuid()

            ]);

        $order->update(['payment_id' => $payment->id]);

        if ($request->payment_method === 'cash_on_delivery') {
                $payment->update([
                    'transaction_id' => 'COD-' . Str::uuid(),
                    'status' => self::PAYMENT_STATUS_COMPLETED
                ]);

                $order->update(['status' => self::ORDER_STATUS_PROCESSING]);

            OrderTracking::create([
                'order_id' => $order->id,
                'status' => Order::STATUS_PROCESSING,
                'notes' => 'تم تأكيد الطلب - الدفع عند التسليم'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد الطلب - الدفع عند التسليم',
                'order_status' => $order->status,
                'payment_id' => $payment->id
            ]);
        }

        try {
            $token = $this->getAuthToken();
            $paymobOrderId = $this->createPaymobOrder($token, $order);
            $paymentKey = $this->getPaymentKey($token, $paymobOrderId, $order);

            $payment->update([
                'transaction_id' => 'PMB-' . $paymobOrderId,
                'payment_details' => json_encode(['payment_key' => $paymentKey])
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => 'https://accept.paymob.com/api/acceptance/iframes/'
                                . env('PAYMOB_IFRAME_ID', 'default_id')
                                . '?payment_token=' . $paymentKey,
                'order_status' => $order->status,
                'payment_id' => $payment->id
            ]);

        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
                'transaction_id' => 'FAILED-' . Str::uuid()

            ]);

            $order->update(['status' => Order::STATUS_CANCELLED]);

            OrderTracking::create([
                'order_id' => $order->id,
                'status' => Order::STATUS_CANCELLED,
                'notes' => 'فشل في بدء عملية الدفع: ' . $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في بدء عملية الدفع: ' . $e->getMessage(),
                'order_status' => $order->status,
                'payment_id' => $payment->id
            ], 500);
        }
    }

   public function handleWebhook(Request $request)
{
    $data = $request->all();
    Log::info('Paymob Webhook Received', $data);

    $hmac = $request->header('x-hmac');
    $calculatedHmac = hash_hmac('sha512', json_encode($data), env('PAYMOB_HMAC_SECRET', ''));

    if (!hash_equals($hmac, $calculatedHmac)) {
        Log::error('Invalid HMAC', [
            'received_hmac' => $hmac,
            'calculated_hmac' => $calculatedHmac
        ]);
        return response()->json(['error' => 'Invalid HMAC'], 403);
    }

    if (isset($data['obj']) && $data['obj']['success'] === true) {
        $merchantOrderId = $data['obj']['order']['merchant_order_id'] ?? null;

        if (!$merchantOrderId) {
            Log::error('Merchant order ID missing in webhook', $data);
            return response()->json(['error' => 'Merchant order ID missing'], 400);
        }

        $order = Order::find($merchantOrderId);

        if (!$order) {
            Log::error('Order not found', ['merchant_order_id' => $merchantOrderId]);
            return response()->json(['error' => 'Order not found'], 404);
        }

        $payment = Payment::where('order_id', $order->id)->first();

        if ($payment) {
            $payment->update([
                'transaction_id' => $data['obj']['id'] ?? 'unknown',
                'status' => Payment::STATUS_COMPLETED,
                'payment_details' => json_encode($data['obj'])
            ]);
        }

        $order->update(['status' => Order::STATUS_PROCESSING]);

        OrderTracking::create([
            'order_id' => $order->id,
            'status' => Order::STATUS_PROCESSING,
            'notes' => 'تم الدفع بنجاح'
        ]);

        Log::info('Payment succeeded', [
            'order_id' => $order->id,
            'payment_id' => $payment->id ?? 'unknown',
            'transaction_id' => $data['obj']['id'] ?? 'unknown'
        ]);

        return response()->json(['success' => true]);
    }

    Log::error('Payment failed', $data);
    return response()->json(['error' => 'Payment failed'], 400);
    }

        private function ipInRange($ip, $range)
        {
            if (strpos($range, '/') === false) {
                $range .= '/32';
            }

            list($range, $netmask) = explode('/', $range, 2);
            $ipDecimal = ip2long($ip);
            $rangeDecimal = ip2long($range);
            $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
            $netmaskDecimal = ~ $wildcardDecimal;

            return (($ipDecimal & $netmaskDecimal) === ($rangeDecimal & $netmaskDecimal));
        }
}
