<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require_once __DIR__.'/_lib/storage.php';
$releases = [];
foreach (storage_list('releases/manifests/') as $entry) {
  if (!str_ends_with($entry['pathname'], '.json')) continue;
  $j = storage_read_json('releases/manifests/' . basename($entry['pathname']));
  if ($j) $releases[] = $j;
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
