<?php

return [
    'api_id' => env('VATAN_SMS_API_ID'),
    'api_key' => env('VATAN_SMS_API_KEY', env('VATAN_SMS_API_SECRET')),
    'api_url' => env('VATAN_SMS_API_URL'),
];