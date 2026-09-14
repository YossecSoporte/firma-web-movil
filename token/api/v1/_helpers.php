<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

define('FIRMEASY_BASE', 'https://enterprise.digital.firmeasy.legal/api/v1');
define('FIRMEASY_API_KEY', getenv('FIRMEASY_API_KEY') ?: 'sk_live_DvkVAvtOjMc4604AzWM8kruHtu9V4RjN');

function proxyPost($path, $body = '', $bearerToken = '') {
    $url = FIRMEASY_BASE . $path;
    $headers = [
        'Content-Type: application/json',
        'X-API-KEY: ' . FIRMEASY_API_KEY,
    ];
    if ($bearerToken) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        http_response_code(502);
        echo json_encode(['error' => 'Proxy error: ' . $error]);
        exit;
    }

    http_response_code($httpCode);
    echo $response;
    exit;
}

function proxyPostInjectKey($path, $body = '', $bearerToken = '') {
    $url = FIRMEASY_BASE . $path;
    $headers = [
        'Content-Type: application/json',
        'X-API-KEY: ' . FIRMEASY_API_KEY,
    ];
    if ($bearerToken) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        http_response_code(502);
        echo json_encode(['error' => 'Proxy error: ' . $error]);
        exit;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        http_response_code($httpCode);
        echo $response;
        exit;
    }

    $keysDir = __DIR__ . '/../../../storage/keys';
    $kid = 'default';
    $nameCode = '';
    $keyPublic = '';

    $keyFile = $keysDir . '/' . $kid . '.json';
    if (file_exists($keyFile)) {
        $keyData = json_decode(file_get_contents($keyFile), true);
        $nameCode = $keyData['kid'] ?? '';
        $keyPublic = $keyData['public_key'] ?? '';
    }

    $data['key'] = [
        'name_code' => $nameCode,
        'key_public' => $keyPublic,
    ];

    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
