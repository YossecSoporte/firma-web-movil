<?php
header('Content-Type: text/html; charset=utf-8');
$base = dirname(__FILE__);
$releasesDir = $base.'/storage/releases/files';
$manifestDir = $base.'/storage/releases/manifests';
if (!is_dir($releasesDir)) mkdir($releasesDir, 0777, true);
if (!is_dir($manifestDir)) mkdir($manifestDir, 0777, true);

function loadReleases(){
  global $manifestDir;
  $files = glob($manifestDir.'/*.json');
  $list = [];
  foreach($files as $f){
    $j = json_decode(file_get_contents($f), true);
    if($j) $list[] = $j;
  }
  usort($list, function($a,$b){ return ($b['version_code'] ?? 0) <=> ($a['version_code'] ?? 0); });
  return $list;
}
function saveRelease($data){
  global $manifestDir,$releasesDir;
  $code = intval($data['version_code']);
  $fileName = basename($data['file_name'] ?? 'installer.bin');
  $destPath = $releasesDir.'/'.$code.'_'.$fileName;
  $baseUrl = rtrim(getenv('BASE_URL_EXTERNO') ?: 'http://localhost:8081','/');
  // move temp uploaded file
  if (!empty($_FILES['installer']['tmp_name'])) {
    move_uploaded_file($_FILES['installer']['tmp_name'], $destPath);
    $data['file_name'] = $fileName;
    $data['size_bytes'] = filesize($destPath);
    $hash = hash_file('sha256', $destPath);
    $data['sha256'] = $hash;
    $data['download_url'] = $baseUrl.'/api/download-release.php?code='.$code;
    $data['uploaded_at'] = date('c');
    $manifestPath = $manifestDir.'/'.$code.'.json';
    file_put_contents($manifestPath, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    // keep only last 2
    $releases = loadReleases();
    if (count($releases) > 2) {
      $toDelete = array_slice($releases, 2);
      foreach($toDelete as $r){
        $oldCode = $r['version_code'];
        $oldFile = $releasesDir.'/'.$oldCode.'_'.$r['file_name'];
        @unlink($oldFile);
        @unlink($manifestDir.'/'.$oldCode.'.json');
      }
    }
    return $data;
  }
  return null;
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['installer'])) {
  $data = [
    'version' => $_POST['version'] ?? '',
    'version_code' => intval($_POST['version_code'] ?? 0),
    'release_date' => $_POST['release_date'] ?? date('c'),
    'mandatory' => isset($_POST['mandatory']) && $_POST['mandatory']==='1',
    'min_supported_version' => $_POST['min_supported_version'] ?? '',
    'release_notes' => $_POST['release_notes'] ?? '',
    'signature' => $_POST['signature'] ?? '',
  ];
  $saved = saveRelease($data);
  if ($saved) $msg = 'Instalador subido: '.$saved['version'].' ('.$saved['version_code'].')';
  else $msg = 'Error al guardar';
}
$releases = loadReleases();
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Actualización FirmEasy</title>
<style>
body{font-family:system-ui;background:#f5f5f5;margin:0;padding:24px}
.card{background:#fff;max-width:900px;margin:auto;padding:24px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
h1{margin-top:0}
label{display:block;margin:12px 0 4px;font-weight:600}
input,textarea,select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
button{background:#0066cc;color:#fff;border:0;padding:10px 16px;border-radius:6px;cursor:pointer}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{border:1px solid #e5e7eb;padding:8px;text-align:left;font-size:.9rem}
th{background:#f8fafc}
.msg{margin:10px 0;padding:10px;background:#e8f5e9;border-radius:6px}
</style></head>
<body>
<div class="card">
<h1>Gestión de actualizaciones</h1>
<?php if($msg) echo '<div class="msg">'.$msg.'</div>'; ?>
<form method="post" enctype="multipart/form-data">
<div class="grid">
<div><label>Version (semver)</label><input name="version" required></div>
<div><label>Version code</label><input name="version_code" type="number" required></div>
<div><label>Release date</label><input name="release_date" type="datetime-local"></div>
<div><label>Min supported version</label><input name="min_supported_version"></div>
</div>
<label>Instalador</label><input type="file" name="installer" required>
<label>Mandatory</label><select name="mandatory"><option value="0">No</option><option value="1">Sí</option></select>
<label>Release notes</label><textarea name="release_notes" rows="3"></textarea>
<label>Signature (base64url)</label><input name="signature">
<button type="submit">Subir instalador</button>
</form>

<h2>Versiones publicadas (máx 2)</h2>
<table>
<tr><th>Version</th><th>Code</th><th>Fecha</th><th>Size</th><th>Mandatory</th><th>Download</th></tr>
<?php foreach($releases as $r): ?>
<tr>
<td><?=htmlspecialchars($r['version'])?></td>
<td><?=$r['version_code']?></td>
<td><?=htmlspecialchars($r['release_date'] ?? '')?></td>
<td><?=number_format($r['size_bytes'] ?? 0)?></td>
<td><?= $r['mandatory'] ? 'Sí':'No' ?></td>
<td><a href="<?=$r['download_url']?>">Descargar</a></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</body>
</html>
</code></pre></html>
