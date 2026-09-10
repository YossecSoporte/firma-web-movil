<?php
/**
 * Endpoint: api/export-csv.php
 * Método: GET
 * Genera un CSV con todos los PDFs pendientes de firma para la app móvil.
 *
 * Formato CSV (columnas):
 *   0: from          - URL completa para descargar el PDF original
 *   1: to            - URL completa para subir el PDF firmado
 *   2: name_pdf      - Nombre del archivo PDF
 *   3: vis_sig_x     - Posición X de la firma visible
 *   4: vis_sig_y     - Posición Y de la firma visible
 *   5: vis_sig_width - Ancho de la firma visible
 *   6: vis_sig_height- Alto de la firma visible
 *   7: vis_sig_text  - Texto de la firma (con \n para saltos)
 *   8: vis_sig_graphic- URL de imagen de firma
 *   9: vis_sig_page  - Página de la firma
 *  10: vis_sig_text_size - Tamaño del texto
 *  11: vis_sig_rotation - Rotación del texto
 *  12: vis_sig_visible  - true/false (si se muestra la firma)
 *
 * Parámetros query:
 *   x, y, width, height, page, text_size, rotation, graphic, text, visible
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$BASE_URL = rtrim(getenv('BASE_URL_EXTERNO') ?: 'http://localhost:8081', '/');
$DOC_DIR  = __DIR__ . '/../document';
$SIGNED_DIR = $DOC_DIR . '/signed';

// Parámetros de configuración de firma (con defaults sensatos)
$x          = isset($_GET['x'])          ? (int)$_GET['x']          : 10;
$y          = isset($_GET['y'])          ? (int)$_GET['y']          : 30;
$width      = isset($_GET['width'])      ? (int)$_GET['width']      : 210;
$height     = isset($_GET['height'])     ? (int)$_GET['height']     : 100;
$page       = isset($_GET['page'])       ? (int)$_GET['page']       : 1;
$textSize   = isset($_GET['text_size'])  ? (int)$_GET['text_size']  : 10;
$rotation   = isset($_GET['rotation'])   ? (int)$_GET['rotation']   : 0;
$graphic    = $_GET['graphic']  ?? 'https://images.unsplash.com/photo-1560361586-8242b1fc06c5?fm=jpg&q=60&w=3000&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MTJ8fGNvY2hlJTIwbnVldm98ZW58MHx8MHx8fDA%3D';
$text       = $_GET['text']     ?? '';
$visible    = $_GET['visible']  ?? 'true';
$toUrlOverride = $_GET['to_url'] ?? '';
$fromUrlOverride = $_GET['from_url'] ?? '';

// Parámetro file: generar CSV para 1 solo archivo
$singleFile = $_GET['file'] ?? '';

// Archivos excluidos de la generación
$excluded = ['test.pdf', 'doc_pruebaFirmado.pdf', 'pdf_horizontal.pdf'];

// Obtener PDFs originales
$files = glob($DOC_DIR . '/*.pdf');
if ($files === false || count($files) === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'No se encontraron PDFs en document/']);
    exit;
}

// Obtener PDFs firmados (para marcar estado)
$signedMap = [];
if (is_dir($SIGNED_DIR)) {
    $signedFiles = glob($SIGNED_DIR . '/*.pdf');
    if ($signedFiles) {
        foreach ($signedFiles as $sf) {
            $base = basename($sf);
            $original = preg_replace('/_[^_]+\.pdf$/i', '', $base);
            $signedMap[$original] = $base;
        }
    }
}

// Generar CSV
$tmpFile = tempnam(sys_get_temp_dir(), 'firma_csv_');
$handle = fopen($tmpFile, 'w');

// BOM para Excel UTF-8
fwrite($handle, "\xEF\xBB\xBF");

$pendientes = 0;

foreach ($files as $filePath) {
    $fileName = basename($filePath);

    // Si se pide 1 solo archivo, filtrar
    if (!empty($singleFile) && $fileName !== $singleFile) {
        continue;
    }

    // Saltar excluidos
    if (in_array($fileName, $excluded, true)) {
        continue;
    }

    // Saltar si ya está firmado
    $base = preg_replace('/\.pdf$/i', '', $fileName);
    if (isset($signedMap[$base])) {
        continue;
    }

    $fromUrl = !empty($fromUrlOverride)
        ? $fromUrlOverride
        : $BASE_URL . '/api/download.php?file=' . rawurlencode($fileName);
    $toUrl   = !empty($toUrlOverride)
        ? $toUrlOverride
        : $BASE_URL . '/api/upload-signed.php?file=' . rawurlencode($fileName) . '&user_id=USER123';

    // Fila CSV: from,to,name_pdf,x,y,width,height,text,graphic,page,text_size,rotation,visible
    fputcsv($handle, [
        $fromUrl,
        $toUrl,
        $fileName,
        $x,
        $y,
        $width,
        $height,
        $text,
        $graphic,
        $page,
        $textSize,
        $rotation,
        $visible
    ]);

    $pendientes++;
}

fclose($handle);

if ($pendientes === 0) {
    unlink($tmpFile);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'No hay documentos pendientes de firma', 'count' => 0]);
    exit;
}

// Enviar archivo CSV como descarga
$downloadName = 'firma_batch_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, must-revalidate');

readfile($tmpFile);
unlink($tmpFile);
exit;
