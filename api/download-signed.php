<?php

/**
 * Endpoint: api/download-signed.php
 * Método: GET
 * Descarga un PDF firmado desde document/signed/
 *
 * Uso:
 *   GET /api/download-signed.php?file=doc_prueba1_USER123.pdf
 *
 * Seguridad:
 *   - Solo permite archivos dentro de /document/signed
 *   - Bloquea path traversal
 *   - Valida extensión .pdf
 *   - Máximo 20 MB
 */

$useBlob = !empty(getenv('BLOB_READ_WRITE_TOKEN') ?: getenv('VERCEL_OIDC_TOKEN'));
if ($useBlob) {
    require_once __DIR__ . '/_lib/store.php';
    $signedDir = null;
} else {
    $signedDir = realpath(__DIR__ . '/../document/signed');
    if ($signedDir === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Directorio document/signed/ no encontrado']);
        exit;
    }
    define('SIGNED_DIR', $signedDir);
    define('MAX_FILE_SIZE', 20 * 1024 * 1024);
}

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use GET.']);
    exit;
}

// Validar nombre de archivo
$requestedFile = $_GET['file'] ?? '';

if (empty($requestedFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "file" es requerido.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._-]+$/', $requestedFile) || str_contains($requestedFile, '..')) {
    http_response_code(400);
    echo json_encode(['error' => 'Nombre de archivo inválido.']);
    exit;
}

if (!preg_match('/\.pdf$/i', $requestedFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'El archivo debe tener extensión .pdf']);
    exit;
}

// Vercel Blob: servir contenido vía función (store privado no permite URL directa)
if ($useBlob) {
    $content = blobGet('signed/' . $requestedFile);
    if ($content === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Archivo firmado no encontrado.']);
        exit;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $requestedFile . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: private, max-age=0');
    echo $content;
    exit;
}

// Disco local (Docker)
$filePath = SIGNED_DIR . '/' . $requestedFile;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Archivo firmado no encontrado.']);
    exit;
}

$realPath = realpath($filePath);
if ($realPath === false || !str_starts_with($realPath, SIGNED_DIR)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado.']);
    exit;
}

$fileSize = filesize($filePath);
if ($fileSize === false || $fileSize > MAX_FILE_SIZE) {
    http_response_code(403);
    echo json_encode(['error' => 'Archivo demasiado grande.']);
    exit;
}

$mimeType = mime_content_type($filePath) ?: 'application/pdf';
$fileName = basename($filePath);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');

readfile($filePath);

exit;
