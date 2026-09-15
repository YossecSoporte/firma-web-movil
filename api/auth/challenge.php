<?php
/**
 * GET /api/auth/challenge.php?state=...
 * Devuelve nonce fresco para autenticación.
 */
const AUTH_JOBS_DIR = __DIR__ . '/../../storage/auth_jobs';
const EXPIRACION_SEGUNDOS = 600;

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$state = $_GET['state'] ?? '';
if (!$state) { http_response_code(400); echo json_encode(['error'=>'state requerido']); exit; }

// Encontrar job por state
$found = null;
foreach (glob(AUTH_JOBS_DIR.'/*.json') as $file) {
    $job = json_decode(file_get_contents($file), true);
    if (isset($job['state']) && $job['state'] === $state) {
        $found = $job;
        $jobFile = $file;
        break;
    }
}
if (!$found) { http_response_code(404); echo json_encode(['error'=>'state no encontrado']); exit; }

if (time() > $found['exp']) { http_response_code(410); echo json_encode(['error'=>'state expirado']); exit; }

// Generar nonce si no existe
if (empty($found['nonce'])) {
    $found['nonce'] = base64url_encode(random_bytes(32));
    $found['nonce_created_at'] = time();
    file_put_contents($jobFile, json_encode($found, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

echo json_encode([
    'state' => $state,
    'nonce' => $found['nonce'],
    'purpose' => 'authentication',
    'display_name' => $found['display_name'] ?? '',
    'accepted_issuers' => $found['accepted_issuers'] ?? [],
    'expires_at' => date('c', $found['exp']),
], JSON_UNESCAPED_SLASHES);

function base64url_encode($data){ return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
