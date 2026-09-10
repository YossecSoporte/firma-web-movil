<?php
/**
 * Endpoint: api/upload-signed-fail.php
 * Simula una subida de PDF firmado que SIEMPRE falla.
 * Para pruebas de manejo de errores en la app móvil.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

http_response_code(500);
echo json_encode([
    'success' => false,
    'error' => 'Error simulado: la subida del PDF firmado ha fallado intencionalmente.',
    'code' => 'UPLOAD_FAIL_SIMULATED'
]);
exit;
