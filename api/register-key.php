<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

const KEYS_DIR = __DIR__ . '/../storage/keys';

if (!is_dir(KEYS_DIR)) {
    mkdir(KEYS_DIR, 0755, true);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

if (empty($data['kid']) || empty($data['public_key'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Se requiere "kid" y "public_key".']);
    exit;
}

$kid = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['kid']);
if (empty($kid)) {
    http_response_code(400);
    echo json_encode(['error' => 'kid contiene caracteres inválidos.']);
    exit;
}

$publicKeyB64 = $data['public_key'];

$keyFile = KEYS_DIR . '/' . $kid . '.json';

file_put_contents($keyFile, json_encode([
    'kid' => $kid,
    'public_key' => $publicKeyB64,
    'created_at' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'kid' => $kid,
    'public_key' => $publicKeyB64,
], JSON_UNESCAPED_SLASHES);
exit;
