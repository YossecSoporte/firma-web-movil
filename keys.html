<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FirmEasy - Gestionar Claves X25519</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; }
  .container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
  h1 { font-size: 1.5rem; margin-bottom: 8px; }
  .subtitle { color: #666; margin-bottom: 24px; font-size: 0.9rem; }
  .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.08); padding: 24px; margin-bottom: 20px; }
  .card h2 { font-size: 1.1rem; margin-bottom: 16px; color: #0066cc; }
  label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 0.85rem; }
  input, textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; font-family: monospace; }
  input:focus, textarea:focus { outline: none; border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,.15); }
  textarea { resize: vertical; min-height: 80px; }
  .btn { display: inline-block; padding: 10px 20px; border: none; border-radius: 6px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background .15s; }
  .btn-primary { background: #0066cc; color: #fff; }
  .btn-primary:hover { background: #0052a3; }
  .btn-danger { background: #dc3545; color: #fff; }
  .btn-danger:hover { background: #bd2130; }
  .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
  .mt-12 { margin-top: 12px; }
  .mt-16 { margin-top: 16px; }
  .mb-8 { margin-bottom: 8px; }
  .key-list { list-style: none; }
  .key-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
  .key-item:last-child { border-bottom: none; }
  .key-item .kid { font-weight: 600; }
  .key-item .pubkey { font-family: monospace; font-size: 0.8rem; color: #666; word-break: break-all; margin-top: 4px; }
  .key-item .created { font-size: 0.75rem; color: #999; }
  .toast { position: fixed; bottom: 20px; right: 20px; background: #28a745; color: #fff; padding: 12px 20px; border-radius: 6px; font-weight: 600; display: none; z-index: 999; }
  .toast.error { background: #dc3545; }
  .preview-box { background: #f8f9fa; border: 1px dashed #ddd; border-radius: 6px; padding: 12px; margin-top: 12px; font-family: monospace; font-size: 0.8rem; white-space: pre-wrap; word-break: break-all; }
  .session-result { background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin-top: 12px; font-family: monospace; font-size: 0.8rem; white-space: pre-wrap; }
</style>
</head>
<body>
<div class="container">
  <h1>Gestionar Claves X25519</h1>
  <p class="subtitle">Administra las claves públicas de las empresas para cifrado ECDH</p>

  <div class="card">
    <h2>Registrar / Actualizar Public Key</h2>
    <label for="kid">kid (identificador de la empresa)</label>
    <input type="text" id="kid" placeholder="ej: default, empresa-2026-09" value="default" class="mb-8">

    <label for="publicKey">Public Key (base64 X25519, 32 bytes)</label>
    <textarea id="publicKey" placeholder="Pega aquí la public_key en base64..."></textarea>

    <button class="btn btn-primary mt-12" id="btnSave">Guardar Clave</button>

    <div id="saveResult" class="preview-box mt-12" style="display:none;"></div>
  </div>

  <div class="card">
    <h2>Claves Registradas</h2>
    <ul class="key-list" id="keyList">
      <li style="color:#999; padding:10px 0;">Cargando...</li>
    </ul>
  </div>

  <div class="card">
    <h2>Probar Session Endpoint</h2>
    <p class="mb-8" style="font-size:0.85rem; color:#666;">Consulta GET /api/session.php?sid=TOKEN para ver la respuesta con public_key</p>
    <label for="testSid">SID / Token</label>
    <input type="text" id="testSid" placeholder="Token del job o sid">
    <button class="btn btn-primary btn-sm mt-12" id="btnTestSession">Consultar Session</button>
    <div id="sessionResult" class="session-result mt-12" style="display:none;"></div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function showToast(msg, isError) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast' + (isError ? ' error' : '');
  t.style.display = 'block';
  setTimeout(function() { t.style.display = 'none'; }, 3000);
}

async function loadKeys() {
  var list = document.getElementById('keyList');
  try {
    var resp = await fetch('/api/list-keys.php');
    var data = await resp.json();
    if (!data.keys || data.keys.length === 0) {
      list.innerHTML = '<li style="color:#999; padding:10px 0;">No hay claves registradas</li>';
      return;
    }
    var html = '';
    data.keys.forEach(function(k) {
      html += '<li class="key-item"><div style="flex:1;">'
        + '<div class="kid">' + escapeHtml(k.kid) + '</div>'
        + '<div class="pubkey">' + escapeHtml(k.public_key) + '</div>'
        + '<div class="created">' + escapeHtml(k.created_at || '') + '</div>'
        + '</div>'
        + '<button class="btn btn-danger btn-sm" onclick="deleteKey(\'' + escapeHtml(k.kid) + '\')">Eliminar</button>'
        + '</li>';
    });
    list.innerHTML = html;
  } catch (e) {
    list.innerHTML = '<li style="color:#dc3545; padding:10px 0;">Error cargando claves: ' + escapeHtml(e.message) + '</li>';
  }
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.getElementById('btnSave').addEventListener('click', async function() {
  var kid = document.getElementById('kid').value.trim();
  var publicKey = document.getElementById('publicKey').value.trim();

  if (!kid) { showToast('Ingresa un kid', true); return; }
  if (!publicKey) { showToast('Ingresa la public_key', true); return; }

  this.disabled = true;
  try {
    var resp = await fetch('/api/register-key.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ kid: kid, public_key: publicKey })
    });
    var data = await resp.json();
    var resultBox = document.getElementById('saveResult');
    resultBox.style.display = 'block';
    resultBox.textContent = JSON.stringify(data, null, 2);
    showToast(data.success ? 'Clave guardada correctamente' : 'Error: ' + data.error, !data.success);
    loadKeys();
  } catch (e) {
    showToast('Error: ' + e.message, true);
  }
  this.disabled = false;
});

async function deleteKey(kid) {
  if (!confirm('Eliminar la clave "' + kid + '"?')) return;
  try {
    var resp = await fetch('/api/delete-key.php?kid=' + encodeURIComponent(kid), { method: 'POST' });
    var data = await resp.json();
    showToast(data.success ? 'Clave eliminada' : 'Error: ' + data.error, !data.success);
    loadKeys();
  } catch (e) {
    showToast('Error: ' + e.message, true);
  }
}

document.getElementById('btnTestSession').addEventListener('click', async function() {
  var sid = document.getElementById('testSid').value.trim();
  if (!sid) { showToast('Ingresa un sid/token', true); return; }

  var resultBox = document.getElementById('sessionResult');
  resultBox.style.display = 'block';
  resultBox.textContent = 'Consultando...';

  try {
    var resp = await fetch('/api/session.php?sid=' + encodeURIComponent(sid));
    var data = await resp.json();
    resultBox.textContent = JSON.stringify(data, null, 2);
  } catch (e) {
    resultBox.textContent = 'Error: ' + e.message;
  }
});

loadKeys();
</script>
</body>
</html>
