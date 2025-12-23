<?php

// app/Http/Controllers/Web/PaymentController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\payment;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class WebPaymentController extends Controller
{
    protected $paymobService;

    public function __construct(PaymobService $paymobService)
    {
        $this->paymobService = $paymobService;
    }

    public function showCheckout()
    {
        return view('checkout');
    }

    public function processCheckout(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
        ]);

        $order = Order::create([
            'user_id' => auth::id(),
            'total_amount' => $request->amount,
            'billing_data' => $request->only([
                'first_name', 'last_name', 'email', 'phone',
                'apartment', 'floor', 'street', 'building',
                'city', 'country'
            ])
        ]);

        $paymentData = $this->paymobService->initiatePayment($order);

        return redirect()->away($paymentData['payment_url']);
    }

    public function callback(Request $request)
    {
        if (!$this->paymobService->verifyHmac($request->all())) {
            return redirect()->route('payment.failed')->with('error', 'Invalid payment response');
        }

        $payment = Payment::where('transaction_id', $request->merchant_order_id)->first();

        if ($payment) {
            $payment->update([
                'transaction_id' => $request->txn_response_code,
                'status' => $request->success === 'true' ? 'completed' : 'failed'
            ]);

            $payment->order->update([
                'status' => $payment->status
            ]);
        }

        return $request->success === 'true'
            ? redirect()->route('payment.success')
            : redirect()->route('payment.failed');
    }
}
