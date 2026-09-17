<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Job-Id');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
$jobId = $_SERVER['HTTP_X_JOB_ID'] ?? ($payload['job'] ?? '');

$logDir = __DIR__ . '/../../storage/auth_callbacks';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);

$logFile = $logDir . '/' . $jobId . '_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($logFile, json_encode([
    'received_at' => date('c'),
    'job' => $jobId,
    'payload' => $payload,
    'headers' => getallheaders()
], JSON_PRETTY_PRINT));

http_response_code(200);
echo json_encode(['received' => true]);
