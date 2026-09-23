<?php

/**
 * Endpoint: api/upload-release.php
 * Sube un instalador (.exe/.apk/.bin) y su manifest como nueva release.
 * POST multipart: file_key "file", form fields: version_code, version_name, notes, file_name
 * Requiere header Authorization: Bearer <UPLOAD_TOKEN>
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/_lib/storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Solo POST']);
    exit;
}

$uploadToken = getenv('UPLOAD_RELEASE_TOKEN') ?: 'firmeasy-upload-2024';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$provided = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $provided = $m[1];
} elseif (!empty($_POST['token'])) {
    $provided = $_POST['token'];
}
if ($provided !== $uploadToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de subida inválido']);
    exit;
}

$versionCode = isset($_POST['version_code']) ? (int)$_POST['version_code'] : 0;
$version = trim($_POST['version'] ?? '');
$mandatory = (isset($_POST['mandatory']) && $_POST['mandatory'] === '1');
if ($versionCode <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'version_code (int > 0) es requerido']);
    exit;
}

if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Archivo "file" requerido (multipart/form-data)']);
    exit;
}

$tmpFile = $_FILES['file']['tmp_name'];
$originalName = preg_replace('/[^A-Za-z0-9._-]/', '', basename($_FILES['file']['name']));
if ($originalName === '') {
    $originalName = 'installer_' . $versionCode . '.bin';
}

$content = file_get_contents($tmpFile);
if ($content === false || strlen($content) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No se pudo leer el archivo subido (vacío)']);
    exit;
}
$sha256 = hash('sha256', $content);

$fileKey = $versionCode . '_' . $originalName;

// Persistir fichero + manifest (Blob o disco local)
if (storage_write('releases/files/' . $fileKey, $content, 'application/octet-stream') === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error guardando el instalador en almacenamiento']);
    exit;
}

$notes = trim($_POST['release_notes'] ?? '');
$baseUrl = rtrim(getenv('BASE_URL_EXTERNO') ?: 'http://localhost:8081', '/');
$manifest = [
    'version'             => $version ?: ('v' . $versionCode),
    'version_code'        => $versionCode,
    'version_name'        => $version,
    'release_date'        => $_POST['release_date'] ?? date('c'),
    'mandatory'           => $mandatory,
    'min_supported_version' => $_POST['min_supported_version'] ?? '',
    'release_notes'       => $notes,
    'notes'               => $notes,
    'signature'           => $_POST['signature'] ?? '',
    'file_name'           => $originalName,
    'sha256'              => $sha256,
    'size'                => strlen($content),
    'size_bytes'          => strlen($content),
    'download_url'        => $baseUrl . '/api/download-release.php?code=' . $versionCode,
    'uploaded_at'         => date('c'),
    'created_at'          => date('c'),
];

if (storage_write_json('releases/manifests/' . $versionCode . '.json', $manifest) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error guardando el manifest']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'      => true,
    'version'      => $manifest['version'],
    'version_code' => $versionCode,
    'file_name'    => $originalName,
    'sha256'       => $sha256,
    'size'         => strlen($content),
], JSON_UNESCAPED_SLASHES);