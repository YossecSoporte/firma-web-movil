<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/_lib/storage.php';
$code = isset($_GET['code']) ? preg_replace('/[^0-9]/','',$_GET['code']) : '';
if (!$code) { http_response_code(400); exit('code required'); }
$manifest = storage_read_json('releases/manifests/' . $code . '.json');
if ($manifest === null) { http_response_code(404); exit('manifest not found'); }
$fileName = $manifest['file_name'] ?? 'installer.bin';

if (storage_use_blob()) {
    $content = storage_read('releases/files/' . $code . '_' . $fileName);
    if ($content === false) { http_response_code(404); exit('file not found'); }
    $size = strlen($content);
} else {
    $base = dirname(__DIR__);
    $releasesDir = $base.'/storage/releases/files';
    $path = $releasesDir.'/'.$code.'_'.$fileName;
    if (!is_file($path)) { http_response_code(404); exit('file not found'); }
    $size = filesize($path);
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mime = $ext==='exe' ? 'application/octet-stream' : 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.$fileName.'"');
header('Content-Length: '.$size);
if (storage_use_blob()) {
    echo $content;
} else {
    readfile($path);
}
