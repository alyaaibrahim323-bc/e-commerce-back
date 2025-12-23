<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PaymobService
{
    private $client;
    private $apiKey;
    private $cardIntegrationId;
    private $hmacSecret;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://accept.paymob.com/api/',
            'timeout' => 30,
        ]);

        $this->apiKey = config('services.paymob.api_key');
        $this->cardIntegrationId = config('services.paymob.card_integration_id');
        $this->hmacSecret = config('services.paymob.hmac_secret');
    }

    public function getAuthToken()
    {
        try {
            $response = $this->client->post('auth/tokens', [
                'json' => [
                    'api_key' => config('services.paymob.api_key')
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            if (!isset($body['token'])) {
                throw new \Exception('لم يتم استلام token من Paymob');
            }

            return $body['token'];

        } catch (\Exception $e) {
            throw new \Exception("خطأ في مصادقة Paymob: " . $e->getMessage());
        }
    }

    public function createOrder($token, $amount, $merchantOrderId)
    {
        try {
            $response = $this->client->post('ecommerce/orders', [
                'json' => [
                    'auth_token' => $token,
                    'delivery_needed' => false,
                    'amount_cents' => $amount * 100,
                    'currency' => 'EGP',
                    'merchant_order_id' => $merchantOrderId,
                ]
            ]);

            return json_decode($response->getBody());
        } catch (\Exception $e) {
            Log::error('Paymob Order Error: '.$e->getMessage());
            throw $e;
        }
    }

    public function getPaymentKey($token, $orderId, $amount, $billingData)
    {
        try {
            $response = $this->client->post('acceptance/payment_keys', [
                'json' => [
                    'auth_token' => $token,
                    'amount_cents' => $amount * 100,
                    'expiration' => 3600,
                    'order_id' => $orderId,
                    'billing_data' => $billingData,
                    'currency' => 'EGP',
                    'integration_id' => $this->cardIntegrationId,
                ]
            ]);

            return json_decode($response->getBody())->token;
        } catch (\Exception $e) {
            Log::error('Paymob Payment Key Error: '.$e->getMessage());
            throw $e;
        }
    }

    public function verifyCallback($data)
    {
        $receivedHmac = $data['hmac'];
        $data = array_filter($data, fn($k) => $k !== 'hmac', ARRAY_FILTER_USE_KEY);

        ksort($data);
        $queryString = http_build_query($data);
        $calculatedHmac = hash_hmac('sha512', $queryString, $this->hmacSecret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }
}
