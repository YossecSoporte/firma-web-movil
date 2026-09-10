<?php
/**
 * Endpoint: api/download-fail.php
 * Simula una descarga de PDF que SIEMPRE falla.
 * Para pruebas de manejo de errores en la app móvil.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => 'Error simulado: la descarga del PDF ha fallado intencionalmente.',
    'code' => 'DOWNLOAD_FAIL_SIMULATED'
]);
exit;
