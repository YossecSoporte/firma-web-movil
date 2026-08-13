<?php

/**
 * Endpoint: api/token.php
 * Método: GET
 * Devuelve el token para un job específico
 *
 * Uso: GET /api/token/{job}
 * Ejemplo: GET /api/token/810371a9-a3bf-4398-a3f9-4ca299c62bd2
 *
 * Respuesta exitosa (200):
 * {
 *   "token": "tkn_ind_yiaLpkwq42LIfgTp1GHhjzifHcjusTzT"
 * }
 *
 * Respuestas de error:
 * 404 - Job no encontrado
 * 410 - Job expirado
 */

// Configuración
const STORAGE_DIR = __DIR__ . '/../storage/jobs';

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

// Buscar el segmento después de "token"
$jobId = null;
for ($i = 0; $i < count($pathParts); $i++) {
    if ($pathParts[$i] === 'token' && isset($pathParts[$i + 1])) {
        $jobId = $pathParts[$i + 1];
        break;
    }
}

if (empty($jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Job ID requerido en la URL: /api/token/{job}']);
    exit;
}

// Validar formato UUID básico
if (!preg_match('/^[a-f0-9-]{36}$/i', $jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de Job ID inválido']);
    exit;
}

// Leer archivo
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

$jobData = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Job corrupto']);
    exit;
}

// Verificar expiración
if (isset($jobData['exp']) && $jobData['exp'] < time()) {
    http_response_code(410);
    echo json_encode(['error' => 'Job expirado']);
    exit;
}

// Devolver el token
$token = 'tkn_ind_yiaLpkwq42LIfgTp1GHhjzifHcjusTzT';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'token' => $token
], JSON_UNESCAPED_SLASHES);

exit;