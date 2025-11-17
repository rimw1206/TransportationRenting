<?php
// ============================================
// gateway/config/services.php
// ============================================

return [
    // Route mapping: route prefix => service name
    'routes' => [
        '/auth'             => 'customer',
        '/users'            => 'customer',
        '/kyc'              => 'customer',
        '/payment-methods'  => 'customer',
        '/profile'          => 'customer',
        '/rental-history'   => 'customer',

        '/vehicles'         => 'vehicle',
        '/maintenance'      => 'vehicle',
        '/vehicle-usage'    => 'vehicle',

        '/rentals'          => 'rental',
        '/contracts'        => 'rental',
        '/promotions'       => 'rental',
        '/bookings'         => 'rental',

        '/orders'           => 'order',
        '/tracking'         => 'order',
        '/cancellations'    => 'order',

        '/payments'         => 'payment',
        '/transactions'     => 'payment',
        '/invoices'         => 'payment',
        '/refunds'          => 'payment',

        '/notifications'    => 'notification',
    ],

    'services' => [
        'customer'      => 8001,
        'vehicle'       => 8002,
        'rental'        => 8003,
        'order'         => 8004,
        'payment'       => 8005,
        'notification'  => 8006,
    ],

    'public_routes' => [
        '/auth/register',
        '/register',
        '/auth',
        '/auth/verify-email',
        '/auth/resend-verification',
        '/auth/login',
        '/auth/refresh-token',
        '/auth/forgot-password',
        '/auth/reset-password',
        '/vehicles',
        '/vehicles/search',
        '/promotions',
    ],

    'admin_routes' => [
        '/users/list',
        '/vehicles/create',
        '/vehicles/*/delete',
        '/maintenance/create',
        '/orders/assign-driver',
        '/kyc/verify',
    ],

    'timeouts' => [
        'customer'      => 5,
        'vehicle'       => 5,
        'rental'        => 8,
        'order'         => 5,
        'payment'       => 15,
        'notification'  => 3,
        'default'       => 5,
    ],

    'rate_limit' => [
        'enabled'       => true,
        'max_requests'  => 100,
        'window'        => 60,
    ],

    'cors' => [
        'enabled'         => true,
        'allowed_origins' => ['http://localhost:3000', 'http://localhost:8080'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'credentials'     => true,
    ],
];
