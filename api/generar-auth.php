<?php
/**
 * api/generar-auth.php
 * POST: genera job de autenticación y devuelve deep link firmeasy://auth?data=...
 * Cifrado AES-256-GCM con ENCRYPTION_KEY.
 */

const STORAGE_DIR = __DIR__ . '/../storage/auth_jobs';
const EXPIRACION_SEGUNDOS = 600;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Método no permitido']); exit; }

$ENCRYPTION_KEY_B64 = getenv('ENCRYPTION_KEY');
if (empty($ENCRYPTION_KEY_B64)) { http_response_code(500); echo json_encode(['error'=>'ENCRYPTION_KEY no configurada']); exit; }
$ENCRYPTION_KEY = base64_decode($ENCRYPTION_KEY_B64);
if (strlen($ENCRYPTION_KEY) !== 32) { http_response_code(500); echo json_encode(['error'=>'ENCRYPTION_KEY debe ser 32 bytes']); exit; }

$BASE_URL_EXTERNO = rtrim(getenv('BASE_URL_EXTERNO') ?: 'http://localhost:8081', '/');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) { http_response_code(400); echo json_encode(['error'=>'JSON inválido']); exit; }

$display_name = $data['display_name'] ?? 'Autenticación FirmEasy';
$accepted_issuers = $data['accepted_issuers'] ?? [];
if (!is_array($accepted_issuers)) $accepted_issuers = [];

$job = generateUuidV4();
$exp = time() + EXPIRACION_SEGUNDOS;

$jobData = [
    'job' => $job,
    'exp' => $exp,
    'purpose' => 'authentication',
    'display_name' => $display_name,
    'accepted_issuers' => $accepted_issuers,
    'created_at' => time(),
];

$storageFile = STORAGE_DIR . '/' . $job . '.json';
file_put_contents($storageFile, json_encode($jobData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$payload = json_encode([
    'job' => $job,
    'exp' => $exp,
    'purpose' => 'authentication',
    'display_name' => $display_name,
    'accepted_issuers' => $accepted_issuers,
]);

$blob = encryptAesGcm($payload, $ENCRYPTION_KEY);
$deepLink = 'firmeasy://auth?data=' . $blob;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'job' => $job,
    'exp' => $exp,
    'deep_link' => $deepLink,
    'data' => $blob,
    'display_name' => $display_name,
], JSON_UNESCAPED_SLASHES);

function encryptAesGcm(string $plaintext, string $key): string {
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ciphertext === false) throw new Exception('Error encriptando');
    $blob = $iv . $ciphertext . $tag;
    return rtrim(strtr(base64_encode($blob), '+/', '-_'), '=');
}
function generateUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
