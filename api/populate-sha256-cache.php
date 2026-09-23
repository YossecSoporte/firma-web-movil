<?php
/**
 * Script one-shot: pre-calcula SHA-256 de todos los PDFs en document/
 * y guarda el caché en storage/sha256_cache.json.
 *
 * Uso: curl http://localhost:8081/api/populate-sha256-cache.php
 *      o ejecutar: php api/populate-sha256-cache.php (desde la raíz del proyecto)
 */

define('DOC_DIR', __DIR__ . '/../document');
require_once __DIR__ . '/_lib/storage.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

if (!is_dir(DOC_DIR)) {
    echo json_encode(['error' => 'Directorio document/ no encontrado']);
    exit;
}

$cache = storage_read_json('sha256_cache.json') ?? [];

$files = glob(DOC_DIR . '/*.pdf');
if ($files === false || count($files) === 0) {
    echo json_encode(['error' => 'No se encontraron PDFs en document/']);
    exit;
}

$hashed = 0;
$skipped = 0;

foreach ($files as $filePath) {
    $fileName = basename($filePath);

    if (isset($cache[$fileName])) {
        $skipped++;
        continue;
    }

    $hash = hash_file('sha256', $filePath);
    if ($hash === false) {
        echo json_encode(['error' => "Error calculando hash de $fileName"]);
        exit;
    }

    $cache[$fileName] = $hash;
    $hashed++;
}

storage_write_json('sha256_cache.json', $cache);

echo json_encode([
    'ok' => true,
    'total_pdfs' => count($files),
    'hashed' => $hashed,
    'skipped' => $skipped,
    'cache_entries' => count($cache)
], JSON_UNESCAPED_SLASHES);
