<?php
/**
 * api/generar-auth.php
 * POST: genera job de autenticación y devuelve deep link firmeasy://auth?data=...
 * Cifrado AES-256-GCM con ENCRYPTION_KEY.
 */

const STORAGE_DIR = __DIR__ . '/../storage/auth_jobs';
const AUTH_KEYS_FILE = __DIR__ . '/../storage/auth_keys.json';
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
$jti = generateUuidV4();
$now = time();
$exp = $now + EXPIRACION_SEGUNDOS;

$jobData = [
    'job' => $job,
    'jti' => $jti,
    'exp' => $exp,
    'purpose' => 'authentication',
    'display_name' => $display_name,
    'accepted_issuers' => $accepted_issuers,
    'created_at' => $now,
];

$storageFile = STORAGE_DIR . '/' . $job . '.json';
file_put_contents($storageFile, json_encode($jobData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$claims = [
    'iss' => 'firmeasy-web',
    'aud' => 'firmeasy-signer',
    'purpose' => 'authentication',
    'iat' => $now,
    'nbf' => $now,
    'exp' => $exp,
    'jti' => $jti,
    'job' => $job,
    'display_name' => $display_name,
    'accepted_issuers' => $accepted_issuers,
];

$claimsJson = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$signature = signEd25519($claimsJson, AUTH_KEYS_FILE);

// Envelope firmado
$envelope = [
    'claims' => $claims,
    'sig' => base64url_encode($signature),
];

// Cifrar envelope
$payload = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$blob = encryptAesGcm($payload, $ENCRYPTION_KEY);
$deepLink = 'firmeasy://auth?data=' . $blob;

header('Content-Type: application/json; charset=utf-8');
$keys = json_decode(file_get_contents(AUTH_KEYS_FILE), true);
echo json_encode([
    'job' => $job,
    'jti' => $jti,
    'exp' => $exp,
    'deep_link' => $deepLink,
    'data' => $blob,
    'display_name' => $display_name,
    'claims' => $claims,
    'public_key' => $keys['public_key'] ?? null,
], JSON_UNESCAPED_SLASHES);

function encryptAesGcm(string $plaintext, string $key): string {
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ciphertext === false) throw new Exception('Error encriptando');
    $blob = $iv . $ciphertext . $tag;
    return rtrim(strtr(base64_encode($blob), '+/', '-_'), '=');
}
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}
function signEd25519(string $message, string $keysFile): string {
    if (!file_exists($keysFile)) throw new Exception('auth_keys.json no encontrado');
    $keys = json_decode(file_get_contents($keysFile), true);
    if (empty($keys['secret_key'])) throw new Exception('secret_key ausente');
    $secret = sodium_base642bin($keys['secret_key'], SODIUM_BASE64_VARIANT_ORIGINAL);
    // sodium_crypto_sign_detached requiere secreto de firma de 64 bytes
    $signature = sodium_crypto_sign_detached($message, $secret);
    return $signature;
}
function generateUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
