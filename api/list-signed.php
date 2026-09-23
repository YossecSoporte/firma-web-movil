<?php

/**
 * Endpoint: api/list-signed.php
 * Método: GET
 * Lista los PDFs firmados guardados en document/signed/
 *
 * Uso:
 *   GET /api/list-signed.php
 *   GET /api/list-signed.php?original=doc_prueba1.pdf   (filtra por nombre original)
 *
 * Respuesta:
 * {
 *   "success": true,
 *   "count": 2,
 *   "files": [
 *     { "filename": "doc_prueba1_USER123.pdf", "size": 30541, "modified": 1786200000, "url": "/api/download-signed.php?file=doc_prueba1_USER123.pdf" },
 *     ...
 *   ]
 * }
 */

// Capa de almacenamiento auto-detect (Vercel Blob / disco)
require_once __DIR__ . '/_lib/storage.php';
$useBlob = storage_use_blob();

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use GET.']);
    exit;
}

// Filtro opcional por nombre original
$originalFilter = $_GET['original'] ?? '';

if ($useBlob) {
    $files = [];
    $blobs = storage_list('signed/');
    foreach ($blobs as $blob) {
        $filename = basename($blob['pathname']);
        if (!preg_match('/\.pdf$/i', $filename) || $filename === '.gitkeep') continue;

        if ($originalFilter !== '') {
            $baseOriginal = preg_replace('/\.pdf$/i', '', $originalFilter);
            if (!str_starts_with($filename, $baseOriginal . '_')) continue;
        }

        $files[] = [
            'filename' => $filename,
            'size'     => $blob['size'],
            'modified' => 0,
            'url'      => '/api/download-signed.php?file=' . rawurlencode($filename),
        ];
    }
} else {
    $signedDir = realpath(__DIR__ . '/../document/signed');
    if ($signedDir === false || !is_dir($signedDir)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'count' => 0, 'files' => []], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $files = [];
    $iterator = new DirectoryIterator($signedDir);
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && preg_match('/\.pdf$/i', $fileInfo->getFilename()) && !$fileInfo->isDot()) {
            // Saltar .gitkeep u otros no-PDF
            if ($fileInfo->getFilename() === '.gitkeep') continue;

            $filename = $fileInfo->getFilename();

            // Aplicar filtro si se pasó el parámetro "original"
            if ($originalFilter !== '') {
                $baseOriginal = preg_replace('/\.pdf$/i', '', $originalFilter);
                if (!str_starts_with($filename, $baseOriginal . '_')) {
                    continue;
                }
            }

            $files[] = [
                'filename' => $filename,
                'size'     => $fileInfo->getSize(),
                'modified' => $fileInfo->getMTime(),
                'url'      => '/api/download-signed.php?file=' . rawurlencode($filename),
            ];
        }
    }
}

// Ordenar por fecha de modificación (más reciente primero)
usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'count' => count($files),
    'files' => $files
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

exit;
