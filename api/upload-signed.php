<?php

/**
 * Endpoint: api/upload-signed.php
 * Método: POST
 * Recibe un PDF firmado desde la app móvil FirmEasy en formato binario (raw body)
 * y lo guarda en document/signed/
 *
 * Parámetros (query string):
 *   - file:          nombre del archivo PDF original
 *   - user_id:       identificador del usuario
 *   - job:           UUID del job (opcional)
 *   - document_code: UUID del documento (opcional)
 *
 * Seguridad:
 *   - Bearer token validado contra job token (si job_id proporcionado)
 *   - Valida nombre de archivo (sin path traversal)
 *   - Solo extensión .pdf
 *   - Máximo 150 MB
 *   - Valida magic bytes %PDF
 */

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

// Configuración
$signedDir = realpath(__DIR__ . '/../document/signed');
if ($signedDir === false) {
    $signedDir = __DIR__ . '/../document/signed';
    if (!is_dir($signedDir)) {
        mkdir($signedDir, 0755, true);
    }
    $signedDir = realpath($signedDir);
}

define('SIGNED_DIR', $signedDir);
define('MAX_FILE_SIZE', 150 * 1024 * 1024);
define('MIN_FILE_SIZE', 100);

// Obtener parámetros
$requestedFile = $_GET['file'] ?? '';
$userId        = $_GET['user_id'] ?? '';
$jobId         = $_GET['job'] ?? '';
$documentCode  = $_GET['document_code'] ?? '';

if (empty($requestedFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "file" es requerido.']);
    exit;
}

if (empty($userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "user_id" es requerido.']);
    exit;
}

// Validar Bearer token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$bearerToken = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $bearerToken = $m[1];
}

if (!empty($bearerToken) && !empty($jobId) && preg_match('/^[a-f0-9-]{36}$/i', $jobId)) {
    $jobFile = __DIR__ . '/../storage/jobs/' . $jobId . '.json';
    if (file_exists($jobFile)) {
        $jobData = json_decode(file_get_contents($jobFile), true);
        if ($jobData && ($jobData['token'] ?? '') !== $bearerToken) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido.']);
            exit;
        }
    }
}

// Validar nombre de archivo
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $requestedFile) || str_contains($requestedFile, '..')) {
    http_response_code(400);
    echo json_encode(['error' => 'Nombre de archivo inválido.']);
    exit;
}

// Validar extensión .pdf
if (!preg_match('/\.pdf$/i', $requestedFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'El archivo debe tener extensión .pdf']);
    exit;
}

// Validar user_id
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id inválido.']);
    exit;
}

// Leer body en binario
$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Body vacío. Se esperan los bytes del PDF.']);
    exit;
}

// Validar tamaño
$bodySize = strlen($rawBody);
if ($bodySize < MIN_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['error' => 'El body es demasiado pequeño para ser un PDF válido.']);
    exit;
}

if ($bodySize > MAX_FILE_SIZE) {
    http_response_code(413);
    echo json_encode(['error' => 'Archivo demasiado grande. Límite: 150 MB.']);
    exit;
}

// Validar magic bytes
if (substr($rawBody, 0, 5) !== '%PDF-') {
    http_response_code(400);
    echo json_encode(['error' => 'El contenido no es un PDF válido (magic bytes %PDF- no encontrado).']);
    exit;
}

// Construir nombre del archivo firmado
$baseName = preg_replace('/\.pdf$/i', '', $requestedFile);
$signedFileName = $baseName . '_' . $userId . '.pdf';
$signedFilePath = SIGNED_DIR . '/' . $signedFileName;

// Guardar el archivo
$bytesWritten = file_put_contents($signedFilePath, $rawBody);
if ($bytesWritten === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar el archivo firmado.']);
    exit;
}

// Respuesta exitosa
header('Content-Type: application/json; charset=utf-8');
$response = [
    'success' => true,
    'filename' => $signedFileName,
    'original' => $requestedFile,
    'user_id' => $userId,
    'size' => $bytesWritten
];

// Actualizar estado del documento en el job y enviar callback
if (!empty($jobId) && !empty($documentCode)) {
    $jobFile = __DIR__ . '/../storage/jobs/' . $jobId . '.json';
    if (file_exists($jobFile)) {
        $jobData = json_decode(file_get_contents($jobFile), true);

        // Actualizar estado del documento
        foreach ($jobData['documents'] as &$doc) {
            if (($doc['document_code'] ?? '') === $documentCode) {
                $doc['status'] = 'signed';
                $doc['signed_at'] = date('c');
                break;
            }
        }
        unset($doc);

        // Guardar job actualizado
        file_put_contents($jobFile, json_encode($jobData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Verificar si todos los documentos están procesados
        $allDone = true;
        $callbackData = [];
        $anyError = false;
        foreach ($jobData['documents'] as $doc) {
            if ($doc['status'] === 'pending') {
                $allDone = false;
                break;
            }
            $docEntry = [
                'document_code' => $doc['document_code'],
                'name_pdf' => $doc['name_pdf'],
                'status' => $doc['status'],
            ];
            if ($doc['status'] === 'signed') {
                $docEntry['message'] = 'Firmado exitosamente';
            } else {
                $docEntry['status'] = 'error';
                $docEntry['error_code'] = $doc['error_code'] ?? 'SIGN_ERROR';
                $docEntry['message'] = $doc['message'] ?? 'Error al firmar';
                $anyError = true;
            }
            $callbackData[] = $docEntry;
        }

        // Enviar callback si todos están procesados
        if ($allDone && !empty($jobData['callback'])) {
            if ($anyError) {
                sendCallback($jobData['callback'], $jobData['token'], $jobId, false, 422, 'Uno o más documentos no pudieron firmarse', $callbackData);
            } else {
                sendCallback($jobData['callback'], $jobData['token'], $jobId, true, 200, 'PDF firmado exitosamente', $callbackData);
            }
        }
    }
}

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

exit;

/**
 * Envía el callback al integrador
 */
function sendCallback(string $callbackUrl, string $token, string $jobId, bool $success, int $code, string $message, array $data): void
{
    $callbackPayload = [
        'success' => $success,
        'code' => $code,
        'message' => $message,
        'job' => $jobId,
        'data' => $data
    ];

    $url = $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'token=' . rawurlencode($token);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($callbackPayload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'X-Job-Id: ' . $jobId,
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
