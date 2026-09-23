<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Job-Id');
require_once __DIR__ . '/../_lib/storage.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
$jobId = $_SERVER['HTTP_X_JOB_ID'] ?? ($payload['job'] ?? '');

$logFile = 'auth_callbacks/' . $jobId . '_' . date('Y-m-d_H-i-s') . '.json';
storage_write_json($logFile, [
    'received_at' => date('c'),
    'job' => $jobId,
    'payload' => $payload,
    'headers' => getallheaders()
]);

http_response_code(200);
echo json_encode(['received' => true]);
