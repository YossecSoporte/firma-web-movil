<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const KEYS_DIR = __DIR__ . '/../storage/keys';

$keys = [];
if (is_dir(KEYS_DIR)) {
    $files = glob(KEYS_DIR . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!empty($data['kid'])) {
            $keys[] = $data;
        }
    }
}

usort($keys, function ($a, $b) {
    return strcmp($a['kid'] ?? '', $b['kid'] ?? '');
});

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['keys' => $keys], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
