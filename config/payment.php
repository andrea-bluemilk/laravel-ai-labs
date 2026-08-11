<?php

return [
    'default_gateway' => 'paypal',

    // The default options for every gateways.
    'default_options' => [
        'test_mode' => false,
    ],

    'gateways' => [
        'unicredit' => [
            'driver' => 'unicredit',
            'options' => [
                'server_url' => env('UNICREDIT_URL', 'https://testeps.netswgroup.it/UNI_CG_SERVICES/services'),
                'uid' => env('UNICREDIT_ID', 'UNI_ECOM'),
                'signature' => env('UNICREDIT_SIGNATURE', 'UNI_TESTKEY'),
            ],
        ],
        'paypal' => [
            'driver' => 'paypal',
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'options' => [
                'payment_action' => 'sale', // Can only be sale or authorize or order
                'settings' => [
                    'log.LogEnabled' => true,
                    'log.FileName' => storage_path().'/logs/paypal.log',
                    'log.LogLevel' => 'DEBUG',
                    'mode' => 'sandbox', // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
                ],
                'sandbox' => [
                    'client_id' => env('PAYPAL_SANDBOX_API_CLIENT_ID'),
                    'secret' => env('PAYPAL_SANDBOX_API_SECRET'),
                    'merchant_code' => env('PAYPAL_SANDBOX_MERCHANT'),
                ],
                'live' => [
                    'client_id' => env('PAYPAL_LIVE_API_CLIENT_ID'),
                    'secret' => env('PAYPAL_LIVE_API_SECRET'),
                    'merchant_code' => env('PAYPAL_LIVE_MERCHANT'),
                ],
            ],
        ],
        'stripe' => [
            'driver' => 'stripe',
            'mode' => env('STRIPE_MODE', 'sandbox'),
            'options' => [
                'sandbox' => [
                    'private_key' => env('STRIPE_SANDBOX_PRIVATE_KEY'),
                    'public_key' => env('STRIPE_SANDBOX_PUBLIC_KEY'),
                ],
                'live' => [
                    'private_key' => env('STRIPE_LIVE_PRIVATE_KEY'),
                    'public_key' => env('STRIPE_LIVE_PUBLIC_KEY'),
                ],
            ],
        ],
        // other gateways
    ],
];
