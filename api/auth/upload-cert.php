<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
require_once __DIR__ . '/../_lib/storage.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$state = preg_replace('/[^A-Za-z0-9_-]/', '', $input['state'] ?? '');
$cert = $input['certificate'] ?? '';
if (!$state || !$cert) { http_response_code(400); echo json_encode(['error'=>'state y certificate requeridos']); exit; }
storage_write('auth_certs/' . $state . '.pem', $cert, 'application/x-pem-file');
echo json_encode(['ok'=>true]);
