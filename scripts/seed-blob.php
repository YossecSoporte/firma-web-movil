<?php

/**
 * Seed: sube los archivos de storage/ al Vercel Blob store.
 *
 * Uso (en el contenedor Docker con el token del entorno de Vercel):
 *   docker exec -e BLOB_READ_WRITE_TOKEN=<token> firmeasy-web php /var/www/html/scripts/seed-blob.php
 *
 * Sube:
 *   - storage/firmeasy_keys.json      → firmeasy_keys.json
 *   - storage/sha256_cache.json       → sha256_cache.json
 *   - storage/auth_keys.json          → auth_keys.json
 *   - storage/keys/*.json             → keys/{kid}.json
 *   - storage/callbacks/*.json        → callbacks/{name}.json
 *   - storage/callbacks/*_summary.json→ callbacks/{name}.json
 *   - storage/jobs/*.json             → jobs/{job}.json
 *   - storage/auth_jobs/*.json        → auth_jobs/{job}.json
 *   - storage/auth_callbacks/*.json   → auth_callbacks/{name}.json
 *   - storage/auth_responses/*.json   → auth_responses/{state}.json
 *   - storage/auth_certs/*.pem        → auth_certs/{state}.pem
 */

require_once __DIR__ . '/../api/_lib/storage.php';

if (getenv('BLOB_READ_WRITE_TOKEN') === false && getenv('VERCEL_OIDC_TOKEN') === false) {
    fwrite(STDERR, "ERROR: no hay BLOB_READ_WRITE_TOKEN/VERCEL_OIDC_TOKEN en entorno\n");
    exit(1);
}
if (!storage_use_blob()) {
    fwrite(STDERR, "ERROR: storage usa disco (sin env de blob)\n");
    exit(1);
}

function putSeed(string $virtual, string $file, string $contentType = 'application/json'): void {
    if (!is_file($file)) {
        echo "SKIP (no existe) $virtual <- $file\n";
        return;
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        echo "SKIP (no legible) $virtual <- $file\n";
        return;
    }
    try {
        $r = blobPut($virtual, $raw, ['contentType' => $contentType]);
        echo "OK   $virtual (" . ($r['size'] ?? 0) . " bytes)\n";
    } catch (Throwable $e) {
        echo "FAIL $virtual <- {$e->getMessage()}\n";
    }
}

$root = dirname(__DIR__) . '/storage';

// Archivos raíz de storage/
putSeed('firmeasy_keys.json',  $root . '/firmeasy_keys.json');
putSeed('sha256_cache.json',   $root . '/sha256_cache.json');
putSeed('auth_keys.json',      $root . '/auth_keys.json');

// keys/
foreach ((glob($root . '/keys/*.json') ?: []) as $f) {
    putSeed('keys/' . basename($f), $f);
}

// callbacks/
foreach ((glob($root . '/callbacks/*.json') ?: []) as $f) {
    putSeed('callbacks/' . basename($f), $f);
}

// jobs/
foreach ((glob($root . '/jobs/*.json') ?: []) as $f) {
    putSeed('jobs/' . basename($f), $f);
}

// auth_jobs/
foreach ((glob($root . '/auth_jobs/*.json') ?: []) as $f) {
    putSeed('auth_jobs/' . basename($f), $f);
}

// auth_callbacks/
foreach ((glob($root . '/auth_callbacks/*.json') ?: []) as $f) {
    putSeed('auth_callbacks/' . basename($f), $f);
}

// auth_responses/
foreach ((glob($root . '/auth_responses/*.json') ?: []) as $f) {
    putSeed('auth_responses/' . basename($f), $f);
}

// auth_certs/
foreach ((glob($root . '/auth_certs/*.pem') ?: []) as $f) {
    putSeed('auth_certs/' . basename($f), $f, 'application/x-pem-file');
}

echo "\nSeed completado.\n";