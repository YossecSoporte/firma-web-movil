<?php

/**
 * Endpoint: api/list-pdfs.php
 * Método: GET
 * Lista los archivos PDF disponibles en la carpeta document/
 */

$documentDir = realpath(__DIR__ . '/../document');
if ($documentDir === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Directorio document/ no encontrado']);
    exit;
}

$files = [];
$iterator = new DirectoryIterator($documentDir);
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.pdf$/i', $fileInfo->getFilename())) {
        $files[] = [
            'filename' => $fileInfo->getFilename(),
            'size' => $fileInfo->getSize(),
            'modified' => $fileInfo->getMTime(),
        ];
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