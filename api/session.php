<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use GET.']);
    exit;
}

const JOBS_DIR = __DIR__ . '/../storage/jobs';
const KEYS_DIR = __DIR__ . '/../storage/keys';

$sid = $_GET['sid'] ?? '';
if (empty($sid)) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        $sid = $m[1];
    }
}

if (empty($sid)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "sid" requerido (GET o Authorization: Bearer).']);
    exit;
}

$kid = null;
if (is_dir(JOBS_DIR)) {
    $files = glob(JOBS_DIR . '*.json');
    foreach ($files as $file) {
        $jobData = json_decode(file_get_contents($file), true);
        if (!empty($jobData['token']) && $jobData['token'] === $sid) {
            $kid = $jobData['kid'] ?? null;
            break;
        }
    }
}

if (empty($kid)) {
    $kid = 'default';
}

$keyFile = KEYS_DIR . '/' . $kid . '.json';
$publicKey = '';
if (file_exists($keyFile)) {
    $keyData = json_decode(file_get_contents($keyFile), true);
    $publicKey = $keyData['public_key'] ?? '';
}

$sessionId = sprintf(
    '%08x-%04x-%04x-%04x-%012x',
    mt_rand(0, 0xFFFFFFFF),
    mt_rand(0, 0xFFFF),
    mt_rand(0, 0xFFFF),
    mt_rand(0, 0xFFFF),
    mt_rand(0, 0xFFFFFFFFFFFF)
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'session_id' => $sessionId,
    'expires_in' => 86400,
    'security' => [
        'allowed_domains' => ['localhost', '192.168.15.23', 'staging.firmeasy.legal'],
    ],
    'tenant' => [
        'name' => 'FirmEasy Web',
    ],
    'quota' => [
        'is_unlimited' => true,
        'limit' => null,
        'used' => 0,
        'remaining' => null,
    ],
    'public_key' => $publicKey,
    'kid' => $kid,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
