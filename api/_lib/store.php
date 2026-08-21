<?php

/**
 * Vercel Blob REST helper (sin SDK oficial PHP)
 * Requiere env BLOB_READ_WRITE_TOKEN
 *
 * Funciones:
 *   blobGet($path)               → string|false (contenido) o ['url'=>..,'size'=>..] con info
 *   blobPut($path, $data, $opts) → array|false  (url, size, pathname)
 *   blobList($prefix)            → array         [{pathname, size, url}]
 *   blobDeletePrefix($prefix)    → int           (cantidad eliminada)
 *   blobDeleteUrls($urls)        → int
 *   blobGetPublicUrl($path)      → string        (URL sin auth)
 */

// ── Token y base URL ───────────────────────────────────────
function _blobToken(): string {
    $token = getenv('BLOB_READ_WRITE_TOKEN');
    if (empty($token)) {
        throw new Exception('BLOB_READ_WRITE_TOKEN no configurado');
    }
    return $token;
}

// Extraer storeId del token: vercel_blob_{storeId}_{secret}
function _blobStoreUrl(): string {
    $token = _blobToken();
    if (preg_match('/^vercel_blob_([a-z0-9]+)_/', $token, $m)) {
        return 'https://' . $m[1] . '.public.blob.vercel-storage.com';
    }
    throw new Exception('Formato de BLOB_READ_WRITE_TOKEN inválido');
}

// ── GET (leer) ─────────────────────────────────────────────
function blobGet(string $path): string|false {
    $url = _blobStoreUrl() . '/' . rawurlencode($path);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => ['Authorization: Bearer ' . _blobToken()],
        CURLOPT_TIMEOUT       => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) return $body;
    return false;
}

// Obtener metadata (size, url) sin descargar el body completo
function blobHead(string $path): ?array {
    $url = _blobStoreUrl() . '/' . rawurlencode($path);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY        => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => ['Authorization: Bearer ' . _blobToken()],
        CURLOPT_TIMEOUT       => 15,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $url  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if ($code === 200) {
        return ['url' => $url, 'size' => $size];
    }
    return null;
}

// ── PUT (escribir) ─────────────────────────────────────────
function blobPut(string $path, string $data, array $opts = []): array|false {
    $url = _blobStoreUrl() . '/' . rawurlencode($path);
    $addSuffix = ($opts['addRandomSuffix'] ?? false) ? '1' : '0';
    $contentType = $opts['contentType'] ?? 'application/octet-stream';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS    => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => [
            'Authorization: Bearer ' . _blobToken(),
            'Content-Type: ' . $contentType,
            'Content-Length: ' . strlen($data),
            'x-add-random-suffix: ' . $addSuffix,
        ],
        CURLOPT_TIMEOUT       => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $json = json_decode($resp, true);
        return [
            'url'      => $json['url'] ?? $url,
            'size'     => strlen($data),
            'pathname' => $path,
        ];
    }
    return false;
}

// ── LIST ───────────────────────────────────────────────────
function blobList(string $prefix = ''): array {
    $url = _blobStoreUrl() . '?prefix=' . rawurlencode($prefix) . '&limit=1000';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => ['Authorization: Bearer ' . _blobToken()],
        CURLOPT_TIMEOUT       => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return [];
    $json = json_decode($resp, true);
    $blobs = $json['blobs'] ?? [];

    $result = [];
    foreach ($blobs as $blob) {
        $result[] = [
            'pathname' => $blob['pathname'] ?? '',
            'size'     => $blob['size'] ?? 0,
            'url'      => $blob['url'] ?? '',
        ];
    }
    return $result;
}

// ── DELETE (por URLs) ──────────────────────────────────────
function blobDeleteUrls(array $urls): int {
    if (empty($urls)) return 0;

    $token = _blobToken();
    $deleted = 0;

    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER    => [
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT       => 15,
        ]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) $deleted++;
    }
    return $deleted;
}

// Delete por prefijo
function blobDeletePrefix(string $prefix): int {
    $blobs = blobList($prefix);
    $urls = array_column($blobs, 'url');
    return blobDeleteUrls($urls);
}

// ── URL pública (para redirect 302) ────────────────────────
function blobGetPublicUrl(string $path): string {
    return _blobStoreUrl() . '/' . rawurlencode($path);
}
