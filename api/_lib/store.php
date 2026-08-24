<?php

/**
 * Vercel Blob REST helper (protocolo del SDK oficial @vercel/blob)
 *
 * Autenticación (en orden de prioridad):
 *   1. env BLOB_READ_WRITE_TOKEN (token estático)
 *   2. env VERCEL_OIDC_TOKEN + env BLOB_STORE_ID (conexión OIDC)
 *
 * Endpoints (según SDK oficial):
 *   - Escritura/listado/borrado → https://vercel.com/api/blob/ (control-plane)
 *     con header x-vercel-blob-store-id
 *   - Lectura → https://{storeId}.private.blob.vercel-storage.com/{path}
 *
 * Funciones:
 *   blobGet($path)               → string|false
 *   blobPut($path, $data, $opts) → array (url, size, pathname)
 *   blobList($prefix)            → array [{pathname, size, url}]
 *   blobDeletePrefix($prefix)    → int
 *   blobDeleteUrls($urls)        → int
 */

function _blobRwToken(): string {
    $t = getenv('BLOB_READ_WRITE_TOKEN');
    return $t ?: '';
}

function _blobOidcToken(): string {
    $t = getenv('VERCEL_OIDC_TOKEN');
    return $t ?: '';
}

function _blobStoreId(): string {
    $id = getenv('BLOB_STORE_ID');
    if (!empty($id)) {
        return preg_replace('/^store_/', '', trim($id));
    }
    $rw = _blobRwToken();
    if ($rw !== '' && preg_match('/^vercel_blob_([a-z0-9]+)_/', $rw, $m)) {
        return $m[1];
    }
    throw new Exception('No se pudo determinar el store id de Vercel Blob');
}

function _blobBearer(): string {
    $rw = _blobRwToken();
    if ($rw !== '') return $rw;
    $oidc = _blobOidcToken();
    if ($oidc !== '') return $oidc;
    throw new Exception('Sin credenciales de Vercel Blob (ni RW token ni OIDC)');
}

// Control-plane API (escritura, listado, borrado)
function _blobApiUrl(string $query = ''): string {
    return 'https://vercel.com/api/blob/' . $query;
}

function _blobAuthHeaders(): array {
    $storeId = _blobStoreId();
    return [
        'Authorization: Bearer ' . _blobBearer(),
        'x-vercel-blob-store-id: ' . $storeId,
        'x-api-version: 12',
    ];
}

// Host de lectura (CDN del store)
function _blobReadUrl(string $path): string {
    $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
    return 'https://' . _blobStoreId() . '.private.blob.vercel-storage.com/' . $encoded;
}

function _blobPathnameEncoded(string $path): string {
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

// ── GET (leer) ─────────────────────────────────────────────
function blobGet(string $path): string|false {
    $ch = curl_init(_blobReadUrl($path));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => _blobAuthHeaders(),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) return $body;
    return false;
}

// Metadata (size) sin descargar el contenido
function blobHead(string $path): ?array {
    $url = _blobReadUrl($path);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => _blobAuthHeaders(),
        CURLOPT_TIMEOUT        => 15,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    if ($code === 200) {
        return ['url' => $url, 'size' => $size];
    }
    return null;
}

// ── PUT (escribir) ─────────────────────────────────────────
function blobPut(string $path, string $data, array $opts = []): array|false {
    $addSuffix = !empty($opts['addRandomSuffix']) ? '1' : '0';
    $contentType = $opts['contentType'] ?? 'application/octet-stream';

    $url = _blobApiUrl('?pathname=' . rawurlencode($path));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array_merge(_blobAuthHeaders(), [
            'x-vercel-blob-access: private',
            'x-content-type: ' . $contentType,
            'Content-Length: ' . strlen($data),
            'x-add-random-suffix: ' . $addSuffix,
        ]),
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        $json = json_decode($resp, true);
        return [
            'url'      => $json['url'] ?? '',
            'size'     => strlen($data),
            'pathname' => $json['pathname'] ?? $path,
        ];
    }
    throw new Exception("Blob PUT fallo (HTTP $code): " . substr((string)$resp . $err, 0, 300));
}

// ── LIST ───────────────────────────────────────────────────
function blobList(string $prefix = ''): array {
    $url = _blobApiUrl('?prefix=' . rawurlencode($prefix) . '&limit=1000');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => _blobAuthHeaders(),
        CURLOPT_TIMEOUT        => 30,
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

// ── DELETE ─────────────────────────────────────────────────
function blobDeleteUrls(array $urls): int {
    if (empty($urls)) return 0;

    $ch = curl_init(_blobApiUrl('/delete'));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode(['urls' => array_values($urls)]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array_merge(_blobAuthHeaders(), [
            'Content-Type: application/json',
        ]),
        CURLOPT_TIMEOUT        => 30,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) return count($urls);
    return 0;
}

function blobDeletePrefix(string $prefix): int {
    $blobs = blobList($prefix);
    $urls = array_column($blobs, 'url');
    return blobDeleteUrls($urls);
}
