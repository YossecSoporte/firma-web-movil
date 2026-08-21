<?php

/**
 * Endpoint: api/job.php
 * Método: GET
 * Consulta la configuración completa de un job por su ID
 *
 * Uso: GET /api/job/{job}
 * Ejemplo: GET /api/job/4aee0cff-e8ad-4b85-8c78-ad0f8997803f
 *
 * Respuesta exitosa (200):
 * {
 *   "job": "...",
 *   "nonce": "...",
 *   "exp": 1786140125,
 *   "kid": "default",
 *   "configuration": { ... },
 *   "documents": [{ ... }],
 *   "created_at": 1786139525
 * }
 *
 * Respuestas de error:
 * 404 - Job no encontrado
 * 410 - Job expirado
 */

// Configuración
const STORAGE_DIR = __DIR__ . '/../storage/jobs';
const TOKEN_FIJO = 'tkn_ind_yiaLpkwq42LIfgTp1GHhjzifHcjusTzT';

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

// Obtener job ID de la URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Buscar el segmento después de "job"
$jobId = null;
for ($i = 0; $i < count($pathParts); $i++) {
    if ($pathParts[$i] === 'job' && isset($pathParts[$i + 1])) {
        $jobId = $pathParts[$i + 1];
        break;
    }
}

if (empty($jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Job ID requerido en la URL: /api/job/{job}']);
    exit;
}

// Validar formato UUID básico
if (!preg_match('/^[a-f0-9-]{36}$/i', $jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de Job ID inválido']);
    exit;
}

// Leer job — Vercel Blob o disco local
if (!empty(getenv('BLOB_READ_WRITE_TOKEN'))) {
    require_once __DIR__ . '/_lib/store.php';
    $content = blobGet('jobs/' . $jobId . '.json');
    if ($content === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Job no encontrado']);
        exit;
    }
} else {
    $storageFile = STORAGE_DIR . '/' . $jobId . '.json';
    if (!file_exists($storageFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'Job no encontrado']);
        exit;
    }
    $content = file_get_contents($storageFile);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error leyendo job']);
        exit;
    }
}

$jobData = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Job corrupto']);
    exit;
}

// Verificar expiración
if (isset($jobData['exp']) && $jobData['exp'] < time()) {
    // Opcional: borrar archivo expirado
    // @unlink($storageFile);
    http_response_code(410);
    echo json_encode(['error' => 'Job expirado']);
    exit;
}

// Ocultar campos internos en la respuesta
// exp y token NO se retornan - la app móvil los obtiene del blob descifrado
unset($jobData['exp'], $jobData['token'], $jobData['nonce'], $jobData['kid'], $jobData['created_at']);

// Devolver configuración completa
header('Content-Type: application/json; charset=utf-8');
echo json_encode($jobData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

exit;