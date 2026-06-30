<?php

return [
    /*
     * Disk used for all uploads. 'public' serves via the storage symlink.
     * Override with MEDIA_DISK=s3 for cloud storage.
     */
    'disk' => env('MEDIA_DISK', 'public'),

    /*
     * Upload path templates per context.
     * {user} is replaced with the authenticated user's ID at runtime.
     */
    'paths' => [
        'pages'         => 'users/{user}/pages',
        'service_cards' => 'users/{user}/services',
        'products'      => 'users/{user}/products',
        'avatar'        => 'users/{user}/avatar',
        'default'       => 'users/{user}/uploads',
    ],
];
