<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../_lib/storage.php';
$state = $_GET['state'] ?? '';
if (!$state) { http_response_code(400); echo json_encode(['error'=>'state requerido']); exit; }
$data = storage_read_json('auth_responses/' . $state . '.json');
if ($data === null) { echo json_encode(['received'=>false]); exit; }
echo json_encode($data);
