<?php
header('Content-Type: application/json; charset=utf-8');
$token = getenv('BLOB_READ_WRITE_TOKEN');
echo json_encode([
    'blob_token_set' => !empty($token),
    'blob_token_prefix' => $token ? substr($token, 0, 20) . '...' : null,
    'vercel_env' => getenv('VERCEL_ENV') ?: null,
    'all_blob_vars' => array_keys(array_filter(getenv(), function ($v, $k) {
        return stripos($k, 'BLOB') !== false;
    }, ARRAY_FILTER_USE_BOTH))
], JSON_PRETTY_PRINT);
