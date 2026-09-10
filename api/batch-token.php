<?php
/**
 * Endpoint: api/batch-token.php
 * Método: GET
 * Obtiene un token de integración para firma en bloque CSV
 * desde la API de FirmEasy (enterprise).
 *
 * Respuesta:
 * { "token_integration": "tkn_xxx", "exp": 1234567890 }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const FIRMEASY_AUTH_URL = 'https://enterprise.digital.firmeasy.legal/api/v1/auth/token';
const EXPIRACION_SEGUNDOS = 600;

$apiKey = getenv('FIRMEASY_API_KEY');
if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'FIRMEASY_API_KEY no configurada en entorno']);
    exit;
}

$ch = curl_init(FIRMEASY_AUTH_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['type' => 'batch']),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey,
    ],
    CURLOPT_TIMEOUT        => 15,
]);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false || $code !== 200) {
    http_response_code(502);
    echo json_encode(['error' => "Error obteniendo token de FirmEasy (HTTP $code)", 'detail' => $err]);
    exit;
}

$json = json_decode($resp, true);
if (empty($json['token'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Respuesta sin token de FirmEasy', 'raw' => $resp]);
    exit;
}

$expiresIn = $json['expires_in'] ?? 300;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'token_integration' => $json['token'],
    'expires_in' => $expiresIn,
    'exp' => time() + $expiresIn
], JSON_UNESCAPED_SLASHES);
exit;
