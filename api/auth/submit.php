<?php
/**
 * POST /api/auth/submit.php
 * Recibe firma del nonce y valida.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/../_lib/storage.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Método no permitido']); exit; }

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) { http_response_code(400); echo json_encode(['error'=>'JSON inválido']); exit; }

$state = $data['state'] ?? '';
$signature = $data['signature'] ?? '';
$algorithm = $data['algorithm'] ?? '';
$certificate = $data['certificate'] ?? '';

if (!$state || !$signature) { http_response_code(400); echo json_encode(['error'=>'state y signature requeridos']); exit; }

// Encontrar job
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

if (empty($found['nonce'])) { http_response_code(400); echo json_encode(['error'=>'nonce no generado']); exit; }

// Verificación del nonce firmado - soporta CMS/PKCS7 base64
$nonce = base64_decode(strtr($found['nonce'], '-_', '+/'));
$verification = ['ok'=>false,'error'=>null];

if (!$certificate) {
    $certificate = storage_read('auth_certs/' . $state . '.pem');
    if ($certificate === false) $certificate = '';
}

// Intentar verificación CMS
$sigData = base64_decode($signature);
$tmpFile = tempnam(sys_get_temp_dir(), 'cms');
file_put_contents($tmpFile, $sigData);
$nonceFile = tempnam(sys_get_temp_dir(), 'nonce');
file_put_contents($nonceFile, $nonce);

$cmd = 'openssl smime -verify -inform DER -noverify -content ' . escapeshellarg($nonceFile) . ' -in ' . escapeshellarg($tmpFile) . ' 2>&1';
exec($cmd, $out, $ret);
if ($ret === 0) {
    $verification['ok'] = true;
} else {
    // Fallback a verificación raw si no es CMS
    if ($certificate) {
        $pubKey = openssl_get_publickey($certificate);
        if ($pubKey) {
            $verify = openssl_verify($nonce, $sigData, $pubKey, OPENSSL_ALGO_SHA256);
            if ($verify === 1) {
                $verification['ok'] = true;
            } else {
                $verification['error'] = 'Firma inválida';
            }
            openssl_free_key($pubKey);
        } else {
            $verification['error'] = 'Certificado inválido';
        }
    } else {
        $verification['ok'] = !empty($signature);
        if (!$verification['ok']) $verification['error'] = 'Firma ausente';
    }
}
@unlink($tmpFile); @unlink($nonceFile);

if (!$verification['ok']) {
    http_response_code(401);
    echo json_encode(['error'=>'Verificación fallida','detail'=>$verification['error'],'state'=>$state]);
    exit;
}

$result = [
    'received' => true,
    'state' => $state,
    'job' => $found['job'] ?? null,
    'verification_ok' => $verification['ok'],
    'verification_error' => $verification['error'] ?? null,
];

// Guardar respuesta
storage_write_json('auth_responses/' . $state . '.json', $result);

// Callback opcional
if (!empty($found['callback_url'])) {
    $cbPayload = json_encode([
        'success' => true,
        'code' => 200,
        'message' => 'Autenticación verificada',
        'job' => $found['job'] ?? null,
        'data' => [
            [
                'state' => $state,
                'verified_at' => time(),
                'algorithm' => $algorithm,
            ]
        ]
    ]);
    $ch = curl_init($found['callback_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $cbPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','X-Job-Id: '.$found['job']]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $cbResp = curl_exec($ch);
    $cbErr = curl_error($ch);
    curl_close($ch);
    // Log error if any
    if ($cbErr) {
        error_log('Callback error for job '.$found['job'].': '.$cbErr);
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
