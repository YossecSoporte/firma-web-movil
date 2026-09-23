<?php

/**
 * Capa de almacenamiento auto-detect (Vercel Blob vs disco local)
 *
 * Detecta el entorno automáticamente:
 *   - Blob   → si existen BLOB_READ_WRITE_TOKEN o VERCEL_OIDC_TOKEN (carga _lib/store.php)
 *   - Disco  → filesystem local (/storage, /document/signed) — Docker / dev
 *
 * Rutas virtuales usadas por los endpoints:
 *   jobs/{id}.json              → storage/jobs/{id}.json
 *   jobs/                       → prefijo jobs/
 *   auth_jobs/{id}.json         → storage/auth_jobs/{id}.json
 *   auth_callbacks/{name}.json  → storage/auth_callbacks/{name}.json
 *   auth_responses/{state}.json → storage/auth_responses/{state}.json
 *   auth_certs/{state}.pem      → storage/auth_certs/{state}.pem
 *   auth_keys.json              → storage/auth_keys.json
 *   sha256_cache.json           → storage/sha256_cache.json
 *   keys/{kid}.json             → storage/keys/{kid}.json
 *   keys/                       → prefijo keys/
 *   firmeasy_keys.json          → storage/firmeasy_keys.json
 *   callbacks/{name}.json       → storage/callbacks/{name}.json
 *   callbacks/                  → prefijo callbacks/
 *   signed/{file}.pdf           → document/signed/{file}.pdf
 *   signed/                     → prefijo signed/
 *   releases/manifests/{code}.json → storage/releases/manifests/{code}.json
 *   releases/files/{code}_{name}   → storage/releases/files/{code}_{name}
 *   releases/manifests/         → prefijo releases/manifests/
 *   releases/files/             → prefijo releases/files/
 */

/**
 * ¿Estamos en Vercel Blob? (cachea el resultado; carga store.php una vez)
 */
function storage_use_blob(): bool {
    static $use = null;
    if ($use === null) {
        $use = !empty(getenv('BLOB_READ_WRITE_TOKEN') ?: getenv('VERCEL_OIDC_TOKEN'));
        if ($use) {
            require_once __DIR__ . '/store.php';
        }
    }
    return $use;
}

/**
 * Convierte una ruta virtual al path absoluto de disco.
 * Solo se usa en modo disco.
 */
function storage_disk_path(string $virtual): string {
    $root = dirname(__DIR__, 2); // raíz del proyecto

    // Prefijo → directorio base de disco
    static $map = null;
    if ($map === null) {
        $map = [
            'jobs'               => $root . '/storage/jobs',
            'auth_jobs'          => $root . '/storage/auth_jobs',
            'auth_callbacks'     => $root . '/storage/auth_callbacks',
            'auth_responses'     => $root . '/storage/auth_responses',
            'auth_certs'         => $root . '/storage/auth_certs',
            'keys'               => $root . '/storage/keys',
            'callbacks'          => $root . '/storage/callbacks',
            'signed'             => $root . '/document/signed',
            'releases/manifests' => $root . '/storage/releases/manifests',
            'releases/files'     => $root . '/storage/releases/files',
        ];
    }

    // Prefijos de archivos "sueltos" en storage/
    if ($virtual === 'auth_keys.json' || $virtual === 'sha256_cache.json' || $virtual === 'firmeasy_keys.json') {
        return $root . '/storage/' . $virtual;
    }

    foreach ($map as $prefix => $dir) {
        if (str_starts_with($virtual, $prefix . '/')) {
            return $dir . '/' . substr($virtual, strlen($prefix) + 1);
        }
    }

    throw new RuntimeException('Ruta virtual de storage no soportada: ' . $virtual);
}

/**
 * Lee un archivo. Devuelve string o false.
 */
function storage_read(string $virtual): string|false {
    if (storage_use_blob()) {
        return blobGet($virtual);
    }
    $path = storage_disk_path($virtual);
    if (!is_file($path)) return false;
    $content = file_get_contents($path);
    return $content === false ? false : $content;
}

/**
 * Devuelve el contenido como array si es JSON válido, sino null.
 */
function storage_read_json(string $virtual): ?array {
    $raw = storage_read($virtual);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * ¿Existe el archivo?
 */
function storage_exists(string $virtual): bool {
    if (storage_use_blob()) {
        return blobGet($virtual) !== false;
    }
    return is_file(storage_disk_path($virtual));
}

/**
 * Escribe un archivo. Devuelve bytes escritos o false.
 */
function storage_write(string $virtual, string $data, string $contentType = 'application/octet-stream'): int|false {
    if (storage_use_blob()) {
        try {
            $result = blobPut($virtual, $data, ['contentType' => $contentType]);
            return $result === false ? false : (int)$result['size'];
        } catch (Throwable $e) {
            return false;
        }
    }
    $path = storage_disk_path($virtual);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return file_put_contents($path, $data, LOCK_EX);
}

/**
 * Escribe un array como JSON (pretty-print).
 */
function storage_write_json(string $virtual, array $data): int|false {
    return storage_write($virtual, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'application/json');
}

/**
 * Elimina un archivo. Devuelve true si se eliminó (o no existía).
 */
function storage_delete(string $virtual): bool {
    if (storage_use_blob()) {
        $blobs = blobList(dirname($virtual) === '.' ? $virtual . '/' : dirname($virtual) . '/');
        $target = $virtual;
        $urls = [];
        foreach ($blobs as $blob) {
            if (($blob['pathname'] ?? '') === $target) {
                $urls[] = $blob['url'];
            }
        }
        if (empty($urls)) return true;
        return blobDeleteUrls($urls) > 0;
    }
    $path = storage_disk_path($virtual);
    if (!is_file($path)) return true;
    return @unlink($path);
}

/**
 * Lista archivos de un prefijo virtual.
 * Devuelve: [ {pathname, size, url} , ... ]
 */
function storage_list(string $prefix): array {
    if (storage_use_blob()) {
        return blobList($prefix);
    }
    $dir = storage_disk_path($prefix);
    $result = [];
    if (!is_dir($dir)) return $result;
    $files = glob($dir . '/*');
    if ($files === false) return $result;
    foreach ($files as $file) {
        if (!is_file($file)) continue;
        $result[] = [
            'pathname' => $prefix . '/' . basename($file),
            'size'     => filesize($file),
            'url'      => '',
        ];
    }
    return $result;
}

/**
 * Elimina todos los archivos de un prefijo (limpieza total).
 * Devuelve el número de archivos eliminados.
 */
function storage_delete_prefix(string $prefix): int {
    if (storage_use_blob()) {
        return blobDeletePrefix($prefix);
    }
    $dir = storage_disk_path($prefix);
    $deleted = 0;
    if (!is_dir($dir)) return 0;
    $files = glob($dir . '/*');
    if ($files === false) return 0;
    foreach ($files as $file) {
        if (is_file($file) && @unlink($file)) $deleted++;
    }
    return $deleted;
}