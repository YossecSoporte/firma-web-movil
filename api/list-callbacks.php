<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$callbackDir = realpath(__DIR__ . '/../storage/callbacks');
if ($callbackDir === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'callbacks' => []]);
    exit;
}

$jobFilter = $_GET['job'] ?? '';

// GET summaries
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $summaries = [];
    $files = glob($callbackDir . '/*_summary.json');

    foreach ($files as $file) {
        $content = file_get_contents($file);
        $summary = json_decode($content, true);
        if (!$summary) continue;

        if (!empty($jobFilter) && ($summary['job'] ?? '') !== $jobFilter) continue;

        // Get job info if available
        $jobId = $summary['job'] ?? '';
        $jobFile = $callbackDir . '/../jobs/' . $jobId . '.json';
        $jobInfo = null;
        if (file_exists($jobFile)) {
            $jd = json_decode(file_get_contents($jobFile), true);
            $jobInfo = [
                'token' => $jd['token'] ?? '',
                'callback_url' => $jd['callback'] ?? '',
                'document_count' => count($jd['documents'] ?? []),
                'created_at' => $jd['created_at'] ?? '',
            ];
        }

        // Count individual log files for this job
        $logFiles = glob($callbackDir . '/' . $jobId . '_*.json');
        $logCount = count($logFiles) - 1; // minus the summary file itself

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
    $files = glob($callbackDir . '/*.json');
    $deleted = 0;
    foreach ($files as $file) {
        if (unlink($file)) $deleted++;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
