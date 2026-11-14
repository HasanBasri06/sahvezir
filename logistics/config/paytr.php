<?php

/**
 * Yerel geliştirmede APP_URL=http://localhost ise Laravel genelde :8000 portunda çalışır.
 */
$paytrApiUrl = (static function (): string {
    $url = rtrim((string) env('APP_URL', 'http://localhost:8000'), '/');

    if (env('APP_ENV') === 'production') {
        return $url;
    }

    $parts = parse_url($url);
    if (! is_array($parts) || empty($parts['host'])) {
        return 'http://localhost:8000';
    }

    $localHosts = ['localhost', '127.0.0.1'];
    if (! isset($parts['port']) && in_array($parts['host'], $localHosts, true)) {
        $scheme = $parts['scheme'] ?? 'http';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        return $scheme.'://'.$parts['host'].':8000'.$path;
    }

    return $url;
})();

return [
    'store_no' => env('STORE_NO'),
    'store_key' => env('STORE_KEY'),
    'store_secret_key' => env('STORE_SECRET_KEY'),
    'url' => env('PAYTR_URL') ?: env('STORE_URL', 'https://www.paytr.com'),
    'verify_ssl' => filter_var(env('PAYTR_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    'frontend_url' => rtrim(
        env('APP_ENV') === 'production'
            ? env('FRONTEND_URL', 'https://tasibul.com')
            : env('PAYTR_FRONTEND_URL', 'http://localhost:5173'),
        '/'
    ),
    'api_url' => $paytrApiUrl,
    'merchant_ok_path' => env('PAYTR_MERCHANT_OK_PATH', '/odeme/basarili'),
    'merchant_fail_path' => env('PAYTR_MERCHANT_FAIL_PATH', '/odeme/hata'),
    // PayTR 3D sonrası POST ile döner; statik SPA 405 verir — dönüş önce API'ye gelir.
    'merchant_return_ok_path' => env('PAYTR_MERCHANT_RETURN_OK_PATH', '/api/payment/return/ok'),
    'merchant_return_fail_path' => env('PAYTR_MERCHANT_RETURN_FAIL_PATH', '/api/payment/return/fail'),
    'callback_url' => $paytrApiUrl.'/api/payment/callback',
    'test_mode' => env('PAYTR_TEST_MODE', '1'),
    'non_3d' => env('PAYTR_NON_3D', '1'),
    'direct_api_path' => env('PAYTR_DIRECT_API_PATH', '/odeme'),
    'status_inquiry_path' => env('PAYTR_STATUS_INQUIRY_PATH', '/odeme/durum-sorgu'),
    'debug_on' => env('PAYTR_DEBUG_ON', env('APP_DEBUG', false) ? '1' : '0'),
    'user_ip_override' => env('PAYTR_USER_IP'),
];
