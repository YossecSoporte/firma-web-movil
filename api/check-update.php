<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
$base = dirname(__DIR__);
$manifestDir = $base.'/storage/releases/manifests';
$files = glob($manifestDir.'/*.json');
$releases = [];
foreach($files as $f){
  $j = json_decode(file_get_contents($f), true);
  if($j) $releases[] = $j;
}
usort($releases, fn($a,$b)=> ($b['version_code'] ?? 0) <=> ($a['version_code'] ?? 0));
$input = json_decode(file_get_contents('php://input'), true);
$current = intval($input['version_code'] ?? 0);
$latest = $releases[0] ?? null;
if (!$latest) { echo json_encode(['update_available'=>false]); exit; }
$needs = $current < $latest['version_code'];
echo json_encode([
  'update_available' => $needs,
  'current_version_code' => $current,
  'latest' => $latest,
  'all' => array_slice($releases,0,2)
]);
