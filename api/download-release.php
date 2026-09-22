<?php
header('Access-Control-Allow-Origin: *');
$base = dirname(__DIR__);
$releasesDir = $base.'/storage/releases/files';
$manifestDir = $base.'/storage/releases/manifests';
$code = isset($_GET['code']) ? preg_replace('/[^0-9]/','',$_GET['code']) : '';
if (!$code) { http_response_code(400); exit('code required'); }
$manifestPath = $manifestDir.'/'.$code.'.json';
if (!is_file($manifestPath)) { http_response_code(404); exit('manifest not found'); }
$manifest = json_decode(file_get_contents($manifestPath), true);
$fileName = $manifest['file_name'] ?? 'installer.bin';
$path = $releasesDir.'/'.$code.'_'.$fileName;
if (!is_file($path)) { http_response_code(404); exit('file not found'); }
$size = filesize($path);
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mime = $ext==='exe' ? 'application/octet-stream' : 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.$fileName.'"');
header('Content-Length: '.$size);
readfile($path);
