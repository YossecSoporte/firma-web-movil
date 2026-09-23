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

// Capa de almacenamiento auto-detect (Vercel Blob / disco)
require_once __DIR__ . '/_lib/storage.php';

$kid = $_GET['kid'] ?? '';
if (empty($kid)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "kid" requerido.']);
    exit;
}

$kid = preg_replace('/[^a-zA-Z0-9_-]/', '', $kid);

if (!storage_exists('keys/' . $kid . '.json')) {
    http_response_code(404);
    echo json_encode(['error' => 'Clave no encontrada para kid: ' . $kid]);
    exit;
}

storage_delete('keys/' . $kid . '.json');

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'kid' => $kid], JSON_UNESCAPED_SLASHES);
exit;
