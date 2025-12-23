<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paymob' => [
        'api_key' => env('ZXlKaGJHY2lPaUpJVXpVeE1pSXNJblI1Y0NJNklrcFhWQ0o5LmV5SmpiR0Z6Y3lJNklrMWxjbU5vWVc1MElpd2ljSEp2Wm1sc1pWOXdheUk2TVRBek5qQTRNU3dpYm1GdFpTSTZJbWx1YVhScFlXd2lmUS42MzBrUk9UYkNnZmRaNWNkVjhRUm9TTF9tajBndUo0Umk3T1JLZUhqUTZDTV9QWEdDSkdxQ0VhQ1VpS2Q5R2tqb1d5c0F0YlFHbnZhNnlqMmNIeEIxQQ=='),
        'integration_id' => env('5038237'),
        // 'iframe_id' => env('PAYMOB_IFRAME_ID'),
        'hmac_secret' => env('AA8A9D31BA8BB1E8283E51D2C852FCBD'),
    ],



];
