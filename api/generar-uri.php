<?php

/**
 * Endpoint: api/generar-uri.php
 * Método: POST
 * Genera un job para firma y devuelve la URI firmeasy://
 *
 * Entrada (JSON body) - FORMATO SIMPLIFICADO:
 * {
 *   "configuration": {
 *     "signature_type": "basic",
 *     "signature_reason": "Acepto el contenido del documento",
 *     "generate_request": "NOMBRE EMPRESA"
 *   },
 *   "documents": [
 *     {
 *       "file": "doc_prueba1.pdf",           // nombre del archivo en document/
 *       "user_id": "USER123",                // para construir URL "to"
 *       "doc_sha256": "a1b2c3d4e5f6...",     // opcional, se calcula si no viene
 *       "settings": { ... }
 *     }
 *   ]
 * }
 *
 * El API construye automáticamente:
 *   from = BASE_URL_EXTERNO + "/api/download.php?file=" + file
 *   to   = BASE_URL_EXTERNO + "/api/upload-signed.php?file=" + file + "&user_id=" + user_id
 *
 * Respuesta:
 * {
 *   "uri": "firmeasy://sign?job=...&nonce=...&exp=...&kid=default&token=...",
 *   "job": "...",
 *   "nonce": "...",
 *   "exp": 1786140125,
 *   "token": "tkn_ind_yiaLpkwq42LIfgTp1GHhjzifHcjusTzT"
 * }
 */

// Configuración
const STORAGE_DIR = __DIR__ . '/../storage/jobs';
const TOKEN_FIJO = 'tkn_ind_yiaLpkwq42LIfgTp1GHhjzifHcjusTzT';
const KID = 'default';
const EXPIRACION_SEGUNDOS = 600; // 10 minutos

// Base URL del sistema externo (configurar via variable de entorno BASE_URL_EXTERNO)
// Ejemplos: 'https://192.168.8.0:5000', 'https://tu-dominio.com'
$BASE_URL_EXTERNO = rtrim(getenv('BASE_URL_EXTERNO') ?: 'http://10.21.132.143:8081', '/');

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

// Leer JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

// Validar estructura básica
if (!isset($data['configuration']) || !isset($data['documents']) || !is_array($data['documents']) || count($data['documents']) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Estructura inválida: se requiere "configuration" y "documents[]" con al menos 1 elemento.']);
    exit;
}

$doc = $data['documents'][0]; // Solo el primer documento

// Validar campos requeridos del documento (formato simplificado)
$required = ['file', 'user_id', 'settings'];
foreach ($required as $field) {
    if (!isset($doc[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Campo requerido faltante en documento: $field"]);
        exit;
    }
}

$fileName = basename($doc['file']);
$userId = $doc['user_id'];

// Validar que el archivo existe en document/
$filePath = __DIR__ . '/../document/' . $fileName;
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => "Archivo no encontrado en document/: $fileName"]);
    exit;
}

// Calcular doc_sha256 si no viene
$docSha256 = $doc['doc_sha256'] ?? '';
if (empty($docSha256)) {
    $docSha256 = hash_file('sha256', $filePath);
    if ($docSha256 === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error calculando SHA-256 del PDF']);
        exit;
    }
} elseif (!preg_match('/^[a-f0-9]{64}$/i', $docSha256)) {
    http_response_code(400);
    echo json_encode(['error' => 'doc_sha256 debe ser 64 caracteres hexadecimales']);
    exit;
}

// Construir URLs completas hacia el sistema externo
$fromUrl = $BASE_URL_EXTERNO . '/api/download.php?file=' . rawurlencode($fileName);
$toUrl   = $BASE_URL_EXTERNO . '/api/upload-signed.php?file=' . rawurlencode($fileName) . '&user_id=' . rawurlencode($userId);

// Generar job, nonce, exp
$job = generateUuidV4();
$nonce = bin2hex(random_bytes(16));
$exp = time() + EXPIRACION_SEGUNDOS;

// Preparar datos para guardar (con URLs completas ya construidas)
$jobData = [
    'job' => $job,
    'nonce' => $nonce,
    'exp' => $exp,
    'kid' => KID,
    'token' => TOKEN_FIJO,
    'configuration' => $data['configuration'],
    'documents' => [
        [
            'from' => $fromUrl,
            'to' => $toUrl,
            'doc_sha256' => $docSha256,
            'settings' => $doc['settings']
        ]
    ],
    'created_at' => time()
];

// Guardar en archivo JSON
$storageFile = STORAGE_DIR . '/' . $job . '.json';
if (!file_put_contents($storageFile, json_encode($jobData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
    http_response_code(500);
    echo json_encode(['error' => 'Error guardando job en almacenamiento']);
    exit;
}

// Construir URI - job es la URL completa al endpoint /api/job/{job}
$jobUrl = $BASE_URL_EXTERNO . '/api/job/' . $job;
$uri = "firmeasy://sign?job=" . rawurlencode($jobUrl) . "&nonce=$nonce&exp=$exp&kid=" . KID . "&token=" . rawurlencode(TOKEN_FIJO);

// Respuesta
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'uri' => $uri,
    'job' => $job,
    'nonce' => $nonce,
    'exp' => $exp,
    'token' => TOKEN_FIJO
], JSON_UNESCAPED_SLASHES);

/**
 * Genera UUID v4 RFC 4122
 */
function generateUuidV4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

exit;