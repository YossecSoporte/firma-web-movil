<?php
require __DIR__ . '/../_helpers.php';

$body = file_get_contents('php://input');
if (empty($body)) {
    $body = json_encode(['type' => 'individual']);
}

proxyPost('/auth/token', $body);
