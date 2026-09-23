<?php

/**
 * Endpoint: api/callback.php
 *
 * POST — Recibe callbacks del sistema de firma cuando un documento se procesa.
 * GET  — Consulta el estado del callback por job ID (sin polling).
 *
 * === POST ===
 * Headers:
 *   - Authorization: Bearer {token}
 *   - X-Job-Id: {job_id}
 * Body (JSON):
 *   {
 *     "success": true|false,
 *     "code": 200|422|...,
 *     "message": "...",
 *     "job": "uuid",
 *     "data": [{ "document_code": "...", "name_pdf": "...", "status": "signed|error", "message": "..." }]
 *   }
 * Respuesta: { "received": true }
 *
 * === GET ===
 * GET /api/callback.php?job={job_id}
 * Respuesta (con callback):
 *   {
 *     "received": true,
 *     "job": "uuid",
 *     "success": true|false,
 *     "code": 200,
 *     "message": "...",
 *     "data": [...],
 *     "last_callback_at": "2026-09-05T..."
 *   }
 * Respuesta (sin callback):
 *   { "received": false, "job": "uuid" }
 */

// Capa de almacenamiento auto-detect (Vercel Blob / disco)
require_once __DIR__ . '/_lib/storage.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Job-Id');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// GET — Consultar estado del callback
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jobId = $_GET['job'] ?? '';

    if (empty($jobId) || !preg_match('/^[a-f0-9-]{36}$/i', $jobId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parámetro "job" requerido (UUID válido)']);
        exit;
    }

    $summary = storage_read_json('callbacks/' . $jobId . '_summary.json');

    header('Content-Type: application/json; charset=utf-8');

    if ($summary !== null) {
        echo json_encode([
            'received' => true,
            'job' => $summary['job'] ?? $jobId,
            'success' => $summary['success'] ?? false,
            'code' => $summary['code'] ?? 0,
            'message' => $summary['message'] ?? '',
            'data' => ($summary['callbacks'][count($summary['callbacks']) - 1]['data'] ?? []),
            'last_callback_at' => $summary['last_callback_at'] ?? null
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['received' => false, 'job' => $jobId], JSON_UNESCAPED_SLASHES);
    }
    exit;
}

// POST — Recibir callback
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use GET o POST.']);
    exit;
}

// Leer body
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

// Validar campos requeridos
$success = $payload['success'] ?? null;
$code    = $payload['code'] ?? null;
$message = $payload['message'] ?? '';
$jobId   = $payload['job'] ?? '';
$data    = $payload['data'] ?? [];

if ($success === null || $code === null || empty($jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos requeridos: success, code, job']);
    exit;
}

if (!preg_match('/^[a-f0-9-]{36}$/i', $jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de job ID inválido']);
    exit;
}

// Validar token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$bearerToken = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $bearerToken = $m[1];
}

$jobData = storage_read_json('jobs/' . $jobId . '.json');
if ($jobData !== null) {
    if (!empty($bearerToken) && ($jobData['token'] ?? '') !== $bearerToken) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido']);
        exit;
    }
}

// Guardar log individual (Vercel Blob / disco)
$timestamp = date('Y-m-d_H-i-s');
$logFile = 'callbacks/' . $jobId . '_' . $timestamp . '.json';

$logEntry = [
    'received_at' => date('c'),
    'job' => $jobId,
    'success' => (bool) $success,
    'code' => $code,
    'message' => $message,
    'data' => $data,
    'token' => $bearerToken ?: null,
    'raw' => $payload
];

storage_write_json($logFile, $logEntry);

// Guardar resumen consolidado por job
$summary = storage_read_json('callbacks/' . $jobId . '_summary.json') ?? [];
$summary['job'] = $jobId;
$summary['last_callback_at'] = date('c');
$summary['success'] = (bool) $success;
$summary['code'] = $code;
$summary['message'] = $message;

if (!isset($summary['callbacks'])) {
    $summary['callbacks'] = [];
}
$summary['callbacks'][] = [
    'at' => date('c'),
    'success' => (bool) $success,
    'code' => $code,
    'message' => $message,
    'data' => $data
];

storage_write_json('callbacks/' . $jobId . '_summary.json', $summary);

// Respuesta
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['received' => true], JSON_UNESCAPED_SLASHES);

exit;
