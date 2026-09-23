<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const KEYS_DIR = __DIR__ . '/../storage/keys';

// Capa de almacenamiento auto-detect (Vercel Blob / disco)
require_once __DIR__ . '/_lib/storage.php';

$keys = [];
foreach (storage_list('keys/') as $entry) {
    $data = storage_read_json('keys/' . basename($entry['pathname']));
    if (!empty($data['kid'])) {
        $keys[] = $data;
    }
}

usort($keys, function ($a, $b) {
    return strcmp($a['kid'] ?? '', $b['kid'] ?? '');
});

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['keys' => $keys], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
