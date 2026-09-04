<?php
header('Content-Type: application/json; charset=utf-8');

$results = [];

// Test 1: curl to httpbin
$ch = curl_init('https://httpbin.org/get');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$resp1 = curl_exec($ch);
$code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err1 = curl_error($ch);
curl_close($ch);
$results['httpbin'] = ['code' => $code1, 'ok' => $code1 === 200, 'error' => $err1 ?: null];

// Test 2: curl to vercel.com
$ch = curl_init('https://vercel.com');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_NOBODY => true,
]);
curl_exec($ch);
$code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err2 = curl_error($ch);
curl_close($ch);
$results['vercel_com'] = ['code' => $code2, 'ok' => $code2 > 0, 'error' => $err2 ?: null];

// Test 3: stream_context_create
$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$resp3 = @file_get_contents('https://httpbin.org/get', false, $ctx);
$results['stream'] = ['ok' => $resp3 !== false, 'error' => $resp3 === false ? error_get_last()['message'] ?? 'failed' : null];

echo json_encode($results, JSON_PRETTY_PRINT);
