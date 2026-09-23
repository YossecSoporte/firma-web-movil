<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Capa de almacenamiento auto-detect (Vercel Blob / disco)
require_once __DIR__ . '/_lib/storage.php';
$useBlob = storage_use_blob();

$callbackDir = realpath(__DIR__ . '/../storage/callbacks');
if ($callbackDir === false && !$useBlob) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'callbacks' => []]);
    exit;
}

$jobFilter = $_GET['job'] ?? '';

// GET summaries
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $summaries = [];
    $entries = storage_list('callbacks/');

    foreach ($entries as $entry) {
        $base = basename($entry['pathname']);
        if (!str_ends_with($base, '_summary.json')) continue;

        $summary = storage_read_json('callbacks/' . $base);
        if (!$summary) continue;

        if (!empty($jobFilter) && ($summary['job'] ?? '') !== $jobFilter) continue;

        // Get job info if available
        $jobId = $summary['job'] ?? '';
        $jobInfo = null;
        $jd = storage_read_json('jobs/' . $jobId . '.json');
        if ($jd !== null) {
            $jobInfo = [
                'token' => $jd['token'] ?? '',
                'callback_url' => $jd['callback'] ?? '',
                'document_count' => count($jd['documents'] ?? []),
                'created_at' => $jd['created_at'] ?? '',
            ];
        }

        // Count individual log files for this job
        $logCount = 0;
        foreach ($entries as $e2) {
            $b2 = basename($e2['pathname']);
            if ($b2 === $base) continue;
            if (str_starts_with($b2, $jobId . '_')) $logCount++;
        }

        $summaries[] = [
            'job' => $jobId,
            'success' => $summary['success'] ?? false,
            'code' => $summary['code'] ?? 0,
            'message' => $summary['message'] ?? '',
            'last_callback_at' => $summary['last_callback_at'] ?? null,
            'callback_count' => count($summary['callbacks'] ?? []),
            'log_files' => max(0, $logCount),
            'job_info' => $jobInfo,
            'data' => $summary['callbacks'][count($summary['callbacks']) - 1]['data'] ?? [],
        ];
    }

    // Sort by last_callback_at desc
    usort($summaries, function ($a, $b) {
        return ($b['last_callback_at'] ?? '') <=> ($a['last_callback_at'] ?? '');
    });

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'callbacks' => $summaries], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// DELETE — limpiar todos los callbacks
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $deleted = storage_delete_prefix('callbacks/');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
