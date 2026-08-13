<?php

/**
 * Endpoint: api/download.php
 * Método: GET
 * Descarga un archivo PDF ubicado en la carpeta document/
 *
 * Uso:
 *   GET /api/download.php?file=nombre-del-archivo.pdf
 *
 * Seguridad:
 *   - Solo permite archivos dentro de /document
 *   - Bloquea path traversal (../, \:, nulos)
 *   - Valida extensión .pdf
 *   - Valida tamaño máximo configurable
 */

// Configuración
$documentDir = realpath(__DIR__ . '/../document');
if ($documentDir === false) {
    throw new RuntimeException('Directorio document/ no encontrado');
}
define('DOCUMENT_DIR', $documentDir);
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20 MB

/**
 * Valida el nombre de archivo (formato, caracteres, path traversal).
 * Retorna el path esperado si el formato es válido, o false si hay error de validación.
 */
function validateFilePath(string $requestedFile): string|false
{
    // Bloquear path traversal y caracteres peligrosos
    if (
        !preg_match('/^[a-zA-Z0-9._-]+$/', $requestedFile) ||  // solo caracteres seguros
        str_contains($requestedFile, '..') ||                   // sin subdirectorios
        str_starts_with($requestedFile, '.')                     // sin archivos ocultos
    ) {
        return false;
    }

    // Validar extensión .pdf
    if (!preg_match('/\.pdf$/i', $requestedFile)) {
        return false;
    }

    return DOCUMENT_DIR . '/' . $requestedFile;
}

// --- Validación de entrada ---
$requestedFile = $_GET['file'] ?? '';

if (empty($requestedFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro "file" es requerido.']);
    exit;
}

// Validar formato del nombre de archivo
$filePath = validateFilePath($requestedFile);

if ($filePath === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Nombre de archivo inválido o no permitido.']);
    exit;
}

// Verificar que el archivo existe
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Archivo no encontrado.']);
    exit;
}

// Verificar que está dentro del directorio permitido (defensa en profundidad)
$realPath = realpath($filePath);
if ($realPath === false || !str_starts_with($realPath, DOCUMENT_DIR)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado.']);
    exit;
}

$fileSize = filesize($filePath);
if ($fileSize === false || $fileSize > MAX_FILE_SIZE) {
    http_response_code(403);
    echo json_encode(['error' => 'Archivo demasiado grande. Límite: ' . MAX_FILE_SIZE . ' bytes.']);
    exit;
}

// --- Servir el archivo ---
$mimeType = mime_content_type($filePath) ?: 'application/pdf';
$fileName   = basename($filePath);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');

// Soporte para range requests (resumir descargas interrumpidas)
if (isset($_SERVER['HTTP_RANGE'])) {
    servePartialContent($filePath, $fileSize);
} else {
    readfile($filePath);
}

exit;

/**
 * Sirve el archivo parcialmente (para range requests).
 */
function servePartialContent(string $filePath, int $fileSize): void
{
    $bytes = str_replace('bytes=', '', $_SERVER['HTTP_RANGE']);
    $range = explode('-', $bytes);

    $start = $range[0] !== '' ? $range[0] : 0;
    $end   = isset($range[1]) && $range[1] !== '' ? $range[1] : $fileSize - 1;

    if (!ctype_digit((string)$start) || !ctype_digit((string)$end)) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    $start = (int)$start;
    $end   = (int)$end;

    if ($start > $end || $start >= $fileSize || $end >= $fileSize) {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }

    header('HTTP/1.1 206 Partial Content');
    header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $fileSize));
    header('Content-Length: ' . ($end - $start + 1));

    $handle = fopen($filePath, 'r');
    fseek($handle, $start);

    $remainder = $end - $start + 1;
    $chunkSize = 1024 * 8; // 8KB

    while ($remainder > 0 && !feof($handle)) {
        $chunk = fread($handle, min($chunkSize, $remainder));
        echo $chunk;
        $remainder -= strlen($chunk);
        flush();
    }

    fclose($handle);
}
