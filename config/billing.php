<?php

return [
    'razorpay' => [
        'key_id'     => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'me_handle'  => env('RAZORPAY_ME_HANDLE'),
        'me_url'     => 'https://razorpay.me/@' . env('RAZORPAY_ME_HANDLE', ''),
    ],

    'plans' => [
        'free' => [
            'name'     => 'Free',
            'slug'     => 'free',
            'price'    => 0,
            'currency' => 'INR',
            'interval' => 'month',
            'features' => [
                '3 pages',
                '1 workspace',
                'Basic support',
            ],
        ],
        'pro' => [
            'name'     => 'Pro',
            'slug'     => 'pro',
            'price'    => 49900, // paise (₹499)
            'currency' => 'INR',
            'interval' => 'month',
            'features' => [
                'Unlimited pages',
                '5 workspaces',
                'Custom domain',
                'GitHub sync & webhooks',
                'Priority support',
            ],
        ],
        'business' => [
            'name'     => 'Business',
            'slug'     => 'business',
            'price'    => 99900, // paise (₹999)
            'currency' => 'INR',
            'interval' => 'month',
            'features' => [
                'Everything in Pro',
                'Unlimited workspaces',
                'Team members (up to 10)',
                'White-label portfolio',
                'Dedicated support',
            ],
        ],
    ],
];
