<?php
return [
    'razorpay' => [
        'key_id'     => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'me_handle'  => env('RAZORPAY_ME_HANDLE'),
        'me_url'     => 'https://razorpay.me/@' . env('RAZORPAY_ME_HANDLE', ''),
    ],

    'limits' => [
        'free'       => ['pages' => 1,    'workspaces' => 1,    'projects' => 3,    'skills' => 5,    'service_cards' => 1],
        'pro'        => ['pages' => 5,    'workspaces' => 3,    'projects' => 15,   'skills' => 25,   'service_cards' => 5],
        'creator'    => ['pages' => null, 'workspaces' => null, 'projects' => null, 'skills' => null, 'service_cards' => null],
        'team'       => ['pages' => 5,    'workspaces' => 3,    'projects' => 15,   'skills' => 25,   'service_cards' => 5],
        'business'   => ['pages' => 5,    'workspaces' => 3,    'projects' => 15,   'skills' => 25,   'service_cards' => 5],
        'enterprise' => ['pages' => null, 'workspaces' => null, 'projects' => null, 'skills' => null, 'service_cards' => null],
    ],

    'features' => [
        'free'       => ['github_sync' => false, 'analytics' => false, 'seo_control' => false, 'password_pages' => false, 'scheduled_publish' => false, 'contact_attachments' => false, 'white_label' => false, 'audit_logs' => false, 'api_access' => false, 'priority_support' => false],
        'pro'        => ['github_sync' => true,  'analytics' => false, 'seo_control' => true,  'password_pages' => false, 'scheduled_publish' => false, 'contact_attachments' => false, 'white_label' => false, 'audit_logs' => false, 'api_access' => false, 'priority_support' => false],
        'creator'    => ['github_sync' => true,  'analytics' => true,  'seo_control' => true,  'password_pages' => true,  'scheduled_publish' => true,  'contact_attachments' => true,  'white_label' => false, 'audit_logs' => false, 'api_access' => false, 'priority_support' => true],
        'team'       => ['github_sync' => true,  'analytics' => false, 'seo_control' => true,  'password_pages' => false, 'scheduled_publish' => false, 'contact_attachments' => false, 'white_label' => false, 'audit_logs' => false, 'api_access' => false, 'priority_support' => false],
        'business'   => ['github_sync' => true,  'analytics' => true,  'seo_control' => true,  'password_pages' => true,  'scheduled_publish' => true,  'contact_attachments' => true,  'white_label' => true,  'audit_logs' => false, 'api_access' => false, 'priority_support' => true],
        'enterprise' => ['github_sync' => true,  'analytics' => true,  'seo_control' => true,  'password_pages' => true,  'scheduled_publish' => true,  'contact_attachments' => true,  'white_label' => true,  'audit_logs' => true,  'api_access' => true,  'priority_support' => true],
    ],

    'plans' => [
        'free' => [
            'name' => 'Free', 'slug' => 'free', 'type' => 'solo',
            'price' => 0, 'currency' => 'INR', 'interval' => 'month',
            'features' => ['1 page', '1 workspace', '3 projects', '5 skills · 1 service card', 'Basic support'],
        ],
        'pro' => [
            'name' => 'Pro', 'slug' => 'pro', 'type' => 'solo',
            'price' => 49900, 'currency' => 'INR', 'interval' => 'month',
            'badge' => 'Most Popular',
            'features' => ['5 pages', '3 workspaces', '15 projects', '25 skills · 5 service cards', 'GitHub sync & webhooks', 'SEO control per page', 'Basic support'],
        ],
        'creator' => [
            'name' => 'Creator', 'slug' => 'creator', 'type' => 'solo',
            'price' => 99900, 'currency' => 'INR', 'interval' => 'month',
            'features' => ['Everything in Pro', 'Unlimited everything', 'Analytics', 'Password-protected pages', 'Scheduled publishing', 'Contact form attachments', 'Priority support'],
        ],
        'team' => [
            'name' => 'Team', 'slug' => 'team', 'type' => 'org',
            'price' => 149900, 'currency' => 'INR', 'interval' => 'month',
            'member_limit' => 5,
            'features' => ['Up to 5 members', 'Each member gets Pro features', 'Org public page', 'Member achievements', 'GitHub sync', 'SEO control'],
        ],
        'business' => [
            'name' => 'Business', 'slug' => 'business', 'type' => 'org',
            'price' => 299900, 'currency' => 'INR', 'interval' => 'month',
            'member_limit' => 15,
            'features' => ['Up to 15 members', 'Everything in Team', 'Analytics per member', 'Password-protected pages', 'Scheduled publishing', 'White-label (no platform branding)', 'Priority support'],
        ],
        'enterprise' => [
            'name' => 'Enterprise', 'slug' => 'enterprise', 'type' => 'org',
            'price' => 'dynamic', 'currency' => 'INR', 'interval' => 'month',
            'base_price' => 499900,
            'member_limit' => null,
            'extra_member_price' => 20000,
            'extra_workspace_price' => 10000,
            'extra_page_price' => 5000,
            'included_members' => 20,
            'features' => ['Unlimited members', 'Everything in Business', 'Audit logs', 'API access', 'Bulk member import', 'Advanced org analytics', 'White-label', 'Dedicated support'],
        ],
    ],
];
