<?php
require __DIR__ . '/../_helpers.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$bearerToken = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $bearerToken = $m[1];
}

$body = file_get_contents('php://input');
proxyPost('/meter/tick', $body, $bearerToken);
