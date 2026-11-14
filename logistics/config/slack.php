<?php

return [
    'notifications' => [
        'new_user' => env('SLACK_NEW_USER_CHANNEL'),
        'payment' => env('SLACK_PAYMENT_CHANNEL', env('SLACK_PAYMEENT_CHANNEL')),
        'account_activation' => env('SLACK_ACCOUNT_ACTIVATION_CHANNEL'),
        'shipments' => env('SLACK_SHIPMENTS_CHANNEL'),
    ],
];