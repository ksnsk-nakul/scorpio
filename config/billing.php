<?php

return [
    'razorpay' => [
        'key_id'     => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'me_handle'  => env('RAZORPAY_ME_HANDLE'),
        'me_url'     => 'https://razorpay.me/@' . env('RAZORPAY_ME_HANDLE', ''),
    ],

    /*
     * Per-plan quota limits.
     * null = unlimited. Checked server-side in controllers.
     */
    'limits' => [
        'free'     => ['skills' => 10, 'service_cards' => 3,    'projects' => 5],
        'pro'      => ['skills' => 40, 'service_cards' => 10,   'projects' => 20],
        'business' => ['skills' => null, 'service_cards' => null, 'projects' => null],
        'admin'    => ['skills' => null, 'service_cards' => null, 'projects' => null],
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
                '10 skills · 3 service cards',
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
                '40 skills · 10 service cards',
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
                'Unlimited skills & service cards',
                'Unlimited workspaces',
                'Team members (up to 10)',
                'White-label portfolio',
                'Dedicated support',
            ],
        ],
    ],
];
