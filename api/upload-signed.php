<?php

/**
 * Endpoint: api/upload-signed.php
 * Método: POST
 * Recibe un PDF firmado desde la app móvil FirmEasy en formato binario (raw body)
 * y lo guarda en document/signed/
 *
 * Uso:
 *   POST /api/upload-signed.php?file=nombre.pdf&user_id=USER123
 *   Content-Type: application/pdf   (o application/octet-stream)
 *   Body: <bytes del PDF>
 *
 * Respuesta (200):
 * {
 *   "success": true,
 *   "filename": "doc_prueba1_USER123.pdf",
 *   "size": 30541
 * }
 *
 * Parámetros (query string):
 *   - file:    nombre del archivo PDF original (ej. doc_prueba1.pdf)
 *   - user_id: identificador del usuario (ej. USER123)
 *
 * Seguridad:
 *   - Solo POST
 *   - Valida nombre de archivo (sin path traversal)
 *   - Solo extensión .pdf
 *   - Máximo 20 MB
 *   - Valida magic bytes %PDF
 */

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

// Configuración
define('MAX_FILE_SIZE', 4 * 1024 * 1024); // 4 MB (límite Vercel Hobby)
define('MIN_FILE_SIZE', 100);              // 100 bytes mínimo (un PDF válido no es tan chico)
$useBlob = !empty(getenv('BLOB_READ_WRITE_TOKEN') ?: getenv('VERCEL_OIDC_TOKEN'));
if ($useBlob) {
    require_once __DIR__ . '/_lib/store.php';
} else {
    $signedDir = realpath(__DIR__ . '/../document/signed');
    if ($signedDir === false) {
        $signedDir = __DIR__ . '/../document/signed';
        if (!is_dir($signedDir)) {
            mkdir($signedDir, 0755, true);
        }
        $signedDir = realpath($signedDir);
    }
    define('SIGNED_DIR', $signedDir);
}

// Obtener parámetros
$requestedFile = $_GET['file'] ?? '';
$userId        = $_GET['user_id'] ?? '';

if (empty($requestedFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "file" es requerido.']);
    exit;
}

if (empty($userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "user_id" es requerido.']);
    exit;
}

// Validar nombre de archivo (solo caracteres seguros, sin path traversal)
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $requestedFile) || str_contains($requestedFile, '..')) {
    http_response_code(400);
    echo json_encode(['error' => 'Nombre de archivo inválido.']);
    exit;
}

// Validar extensión .pdf
if (!preg_match('/\.pdf$/i', $requestedFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'El archivo debe tener extensión .pdf']);
    exit;
}

// Validar user_id (solo caracteres seguros)
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id inválido.']);
    exit;
}

// Leer body en binario (raw)
$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Body vacío. Se esperan los bytes del PDF.']);
    exit;
}

// Validar tamaño
$bodySize = strlen($rawBody);
if ($bodySize < MIN_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['error' => 'El body es demasiado pequeño para ser un PDF válido.']);
    exit;
}

if ($bodySize > MAX_FILE_SIZE) {
    http_response_code(413);
    echo json_encode(['error' => 'Archivo demasiado grande. Límite: 4 MB.']);
    exit;
}

// Validar magic bytes de PDF (%PDF-)
if (substr($rawBody, 0, 5) !== '%PDF-') {
    http_response_code(400);
    echo json_encode(['error' => 'El contenido no es un PDF válido (magic bytes %PDF- no encontrado).']);
    exit;
}

// Construir nombre del archivo firmado: {nombre_base}_{user_id}.pdf
$baseName = preg_replace('/\.pdf$/i', '', $requestedFile);
$signedFileName = $baseName . '_' . $userId . '.pdf';
$signedFilePath = SIGNED_DIR . '/' . $signedFileName;

// Guardar — Vercel Blob o disco local
if ($useBlob) {
    $result = blobPut('signed/' . $signedFileName, $rawBody, [
        'contentType' => 'application/pdf',
    ]);
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar el archivo firmado en Blob.']);
        exit;
    }
    $bytesWritten = $result['size'];
} else {
    $signedFilePath = SIGNED_DIR . '/' . $signedFileName;
    $bytesWritten = file_put_contents($signedFilePath, $rawBody);
    if ($bytesWritten === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar el archivo firmado.']);
        exit;
    }
}

// Respuesta exitosa
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'filename' => $signedFileName,
    'original' => $requestedFile,
    'user_id' => $userId,
    'size' => $bytesWritten
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

exit;
