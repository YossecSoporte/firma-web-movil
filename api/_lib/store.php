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
// Autenticación (en orden de prioridad):
//   1. env BLOB_READ_WRITE_TOKEN (token estático)
//   2. env VERCEL_OIDC_TOKEN + env BLOB_STORE_ID (conexión OIDC del store)
//   3. constante BLOB_TOKEN_FALLBACK (hardcodeada, último recurso)

function _blobRwToken(): string {
    $t = getenv('BLOB_READ_WRITE_TOKEN');
    return !empty($t) ? $t : (defined('BLOB_TOKEN_FALLBACK') ? BLOB_TOKEN_FALLBACK : '');
}

function _blobOidcToken(): string {
    $t = getenv('VERCEL_OIDC_TOKEN');
    return $t ?: '';
}

function _blobEnvStoreId(): string {
    $id = getenv('BLOB_STORE_ID');
    if (!empty($id)) {
        return preg_replace('/^store_/', '', trim($id));
    }
    return '';
}

function _blobAuthHeaders(): array {
    $rw = _blobRwToken();
    if ($rw !== '') {
        return ['Authorization: Bearer ' . $rw];
    }
    $oidc = _blobOidcToken();
    $storeId = _blobEnvStoreId();
    if ($oidc !== '' && $storeId !== '') {
        return [
            'Authorization: Bearer ' . $oidc,
            'x-vercel-blob-store-id: ' . $storeId,
        ];
    }
    throw new Exception('Sin credenciales de Vercel Blob (ni token ni OIDC)');
}

function _blobStoreId(): string {
    // Del env BLOB_STORE_ID o del token RW: vercel_blob_{storeId}_{secret}
    $envId = _blobEnvStoreId();
    if ($envId !== '') return $envId;
    $rw = _blobRwToken();
    if ($rw !== '' && preg_match('/^vercel_blob_([a-z0-9]+)_/', $rw, $m)) {
        return $m[1];
    }
    throw new Exception('No se pudo determinar el store id');
}

function _blobStoreUrl(): string {
    return 'https://' . _blobStoreId() . '.public.blob.vercel-storage.com';
}

// ── GET (leer) ─────────────────────────────────────────────
function blobGet(string $path): string|false {
    $url = _blobStoreUrl() . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => _blobAuthHeaders(),
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
    $url = _blobStoreUrl() . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY        => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => _blobAuthHeaders(),
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
    $url = _blobStoreUrl() . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    $addSuffix = ($opts['addRandomSuffix'] ?? false) ? '1' : '0';
    $contentType = $opts['contentType'] ?? 'application/octet-stream';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS    => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => array_merge(_blobAuthHeaders(), [
            'Content-Type: ' . $contentType,
            'Content-Length: ' . strlen($data),
            'x-add-random-suffix: ' . $addSuffix,
        ]),
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
    throw new Exception("Blob PUT fallo (HTTP $code) a $url: " . substr((string)$resp, 0, 300));
}

// ── LIST ───────────────────────────────────────────────────
function blobList(string $prefix = ''): array {
    $url = _blobStoreUrl() . '?prefix=' . rawurlencode($prefix) . '&limit=1000';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER    => _blobAuthHeaders(),
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

    $headers = _blobAuthHeaders();
    $deleted = 0;

    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER    => $headers,
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
