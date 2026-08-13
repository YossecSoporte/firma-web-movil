<?php

/**
 * Endpoint: API/generar-job.php
 * Método: GET
 * Devuelve un JSON con los parametros para armar el deep link firmeasy://sign
 *
 * JSON de respuesta:
 * {
 *   "job":   "a1b2c3d4-e5f6-4a7b-8c9d-012345678901",  // UUID v4
 *   "nonce": "cb6f8264fa46902c7bfd993dcfdb863a",       // 16 bytes aleatorios hex
 *   "exp":   1785195927,                                 // time() + 900  (15 min)
 *   "kid":   "default"                                   // key id (configurable)
 * }
 *
 * CORS: descomenta las lineas si la llamada proviene de un origen distinto.
 */

// --- CORS (descomentar si se requiere) ---
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type');
// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') http_response_code(204);

/**
 * Genera un UUID v4 RFC 4122 estándar.
 */
function generateUuidV4(): string
{
    $data = random_bytes(16);
    // Set version a 4 (random)
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // Set variant a RFC 4122
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Genera un nonce de 16 bytes expresado en hexadecimal (32 chars).
 * Anti-replay: unico por URI.
 */
function generateNonce(): string
{
    return bin2hex(random_bytes(16));
}

// --- Generación de parametros ---
$job     = generateUuidV4();
$nonce   = generateNonce();
$exp     = time() + 900;          // 15 minutos
$kid     = getenv('FIRMEASY_KID') ?: 'default';

// --- Respuesta JSON ---
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);

echo json_encode([
    'job'   => $job,
    'nonce' => $nonce,
    'exp'   => $exp,
    'kid'   => $kid
], JSON_UNESCAPED_SLASHES);

exit;
