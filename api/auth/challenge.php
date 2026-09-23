<?php
/**
 * GET /api/auth/challenge.php?state=...
 * Devuelve nonce fresco para autenticación.
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../_lib/storage.php';

$state = $_GET['state'] ?? '';
if (!$state) { http_response_code(400); echo json_encode(['error'=>'state requerido']); exit; }

// Encontrar job por state
$found = null; $jobKey = null;
foreach (storage_list('auth_jobs/') as $entry) {
    $job = storage_read_json('auth_jobs/' . basename($entry['pathname']));
    if (is_array($job) && isset($job['state']) && $job['state'] === $state) {
        $found = $job;
        $jobKey = 'auth_jobs/' . basename($entry['pathname']);
        break;
    }
}
if (!$found) { http_response_code(404); echo json_encode(['error'=>'state no encontrado']); exit; }

if (time() > $found['exp']) { http_response_code(410); echo json_encode(['error'=>'state expirado']); exit; }

// Generar nonce si no existe
if (empty($found['nonce'])) {
    $found['nonce'] = base64url_encode(random_bytes(32));
    $found['nonce_created_at'] = time();
    storage_write_json($jobKey, $found);
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
