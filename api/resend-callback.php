<?php

/**
 * Endpoint: api/resend-callback.php
 * Reenvía un callback para un job existente
 * POST { "job": "uuid", "type": "success"|"error" }
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Solo POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobId = $input['job'] ?? '';
$type = $input['type'] ?? 'success';

if (empty($jobId) || !preg_match('/^[a-f0-9-]{36}$/i', $jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Job ID inválido']);
    exit;
}

$jobFile = __DIR__ . '/../storage/jobs/' . $jobId . '.json';
if (!file_exists($jobFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Job no encontrado']);
    exit;
}

$jobData = json_decode(file_get_contents($jobFile), true);
if (empty($jobData['callback'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Este job no tiene callback configurado']);
    exit;
}

$success = ($type === 'success');
$payload = [
    'success' => $success,
    'code' => $success ? 200 : 422,
    'message' => $success ? 'PDF firmado exitosamente (reenvío manual)' : 'Error al firmar documento (reenvío manual)',
    'job' => $jobId,
    'data' => []
];

foreach ($jobData['documents'] ?? [] as $doc) {
    $payload['data'][] = [
        'document_code' => $doc['document_code'] ?? '',
        'name_pdf' => $doc['name_pdf'] ?? $doc['file'] ?? '',
        'status' => $success ? 'signed' : 'error',
        'message' => $success ? 'Firmado exitosamente' : 'Error simulado',
    ];
}

$url = $jobData['callback'] . (str_contains($jobData['callback'], '?') ? '&' : '?') . 'token=' . rawurlencode($jobData['token'] ?? '');

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . ($jobData['token'] ?? ''),
        'X-Job-Id: ' . $jobId,
    ],
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');

if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Error de red: ' . $curlError]);
    exit;
}

echo json_encode([
    'success' => true,
    'http_code' => $httpCode,
    'response' => json_decode($response, true),
    'sent_to' => $url,
    'payload' => $payload,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
