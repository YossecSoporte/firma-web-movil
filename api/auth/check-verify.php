<?php
const AUTH_RESP_DIR = __DIR__ . '/../../storage/auth_responses';
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
$state = $_GET['state'] ?? '';
if (!$state) { http_response_code(400); echo json_encode(['error'=>'state requerido']); exit; }
$file = AUTH_RESP_DIR.'/'.$state.'.json';
if (!file_exists($file)) { echo json_encode(['received'=>false]); exit; }
$data = json_decode(file_get_contents($file), true);
echo json_encode($data);
