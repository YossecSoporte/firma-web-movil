<?php

/**
 * Endpoint: api/clear-signed.php
 * Método: POST (o GET con ?confirm=1)
 * Elimina todos los PDFs firmados de document/signed/ (limpieza total)
 *
 * Uso:
 *   POST /api/clear-signed.php
 *   GET  /api/clear-signed.php?confirm=1
 *
 * Respuesta (200):
 * { "success": true, "deleted": 3 }
 */

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$useBlob = !empty(getenv('BLOB_READ_WRITE_TOKEN'));
if ($useBlob) {
    require_once __DIR__ . '/_lib/store.php';
    $deleted = blobDeletePrefix('signed/');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'deleted' => $deleted
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$signedDir = realpath(__DIR__ . '/../document/signed');
if ($signedDir === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'deleted' => 0, 'message' => 'Directorio no existe']);
    exit;
}

$deleted = 0;
$iterator = new DirectoryIterator($signedDir);
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && !$fileInfo->isDot() && $fileInfo->getFilename() !== '.gitkeep') {
        if (@unlink($fileInfo->getPathname())) {
            $deleted++;
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'deleted' => $deleted
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

exit;
