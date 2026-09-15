<?php
/**
 * POST /api/auth/submit.php
 * Recibe firma del nonce y valida.
 */
const AUTH_JOBS_DIR = __DIR__ . '/../../storage/auth_jobs';
const AUTH_RESP_DIR = __DIR__ . '/../../storage/auth_responses';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Método no permitido']); exit; }

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) { http_response_code(400); echo json_encode(['error'=>'JSON inválido']); exit; }

$state = $data['state'] ?? '';
$signature = $data['signature'] ?? '';
$algorithm = $data['algorithm'] ?? '';

if (!$state || !$signature) { http_response_code(400); echo json_encode(['error'=>'state y signature requeridos']); exit; }

// Encontrar job
$found = null; $jobFile = null;
foreach (glob(AUTH_JOBS_DIR.'/*.json') as $file) {
    $job = json_decode(file_get_contents($file), true);
    if (isset($job['state']) && $job['state'] === $state) {
        $found = $job;
        $jobFile = $file;
        break;
    }
}
if (!$found) { http_response_code(404); echo json_encode(['error'=>'state no encontrado']); exit; }

if (empty($found['nonce'])) { http_response_code(400); echo json_encode(['error'=>'nonce no generado']); exit; }
// Aquí iría verificación real de firma CMS contra nonce con certificado del usuario.
// Por ahora simulamos éxito si signature está presente.

$result = [
    'state' => $state,
    'status' => 'success',
    'verified_at' => time(),
    'algorithm' => $algorithm,
    'job' => $found['job'] ?? null,
];

// Guardar respuesta
if (!is_dir(AUTH_RESP_DIR)) mkdir(AUTH_RESP_DIR, 0755, true);
file_put_contents(AUTH_RESP_DIR.'/'.$state.'.json', json_encode($result, JSON_PRETTY_PRINT));

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
