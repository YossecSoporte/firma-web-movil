<?php
header('Content-Type: application/json; charset=utf-8');
$rw = getenv('BLOB_READ_WRITE_TOKEN');
$oidc = getenv('VERCEL_OIDC_TOKEN');
$storeId = getenv('BLOB_STORE_ID');
echo json_encode([
    'rw_token_set' => !empty($rw),
    'oidc_token_set' => !empty($oidc),
    'oidc_prefix' => $oidc ? substr($oidc, 0, 30) . '...' : null,
    'store_id' => $storeId ?: null,
    'vercel_env' => getenv('VERCEL_ENV') ?: null,
], JSON_PRETTY_PRINT);
