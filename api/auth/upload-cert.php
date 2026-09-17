<?php
const CERT_DIR = __DIR__ . '/../../storage/auth_certs';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$state = $input['state'] ?? '';
$cert = $input['certificate'] ?? '';
if (!$state || !$cert) { http_response_code(400); echo json_encode(['error'=>'state y certificate requeridos']); exit; }
if (!is_dir(CERT_DIR)) mkdir(CERT_DIR, 0755, true);
file_put_contents(CERT_DIR.'/'.$state.'.pem', $cert);
echo json_encode(['ok'=>true]);
