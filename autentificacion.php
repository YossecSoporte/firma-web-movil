<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FirmEasy - Autenticación</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:16px;background:#f5f5f5}
.card{background:#fff;border-radius:12px;padding:24px;width:100%;max-width:720px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
h1{font-size:1.5rem;margin-bottom:8px;color:#1a1a1a;text-align:center}
p.subtitle{color:#666;margin-bottom:20px;font-size:.9rem;text-align:center}
label{display:block;margin:12px 0 6px;font-weight:500;color:#333}
input,textarea{width:100%;padding:10px 12px;border:1px solid #ced4da;border-radius:6px;font-size:.95rem}
button{background:#0066cc;color:#fff;border:none;padding:12px 18px;border-radius:6px;font-weight:600;cursor:pointer}
button:disabled{opacity:.6;cursor:not-allowed}
pre{background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;font-size:.75rem;word-break:break-all;margin-top:16px}
.status{margin-top:12px;padding:10px;border-radius:6px;display:none}
.status.ok{background:#e8f5e9;color:#2e7d32}
.status.err{background:#fdeaea;color:#c0392b}
</style>
</head>
<body>
<div class="card">
<h1>Autenticación FirmEasy</h1>
<p class="subtitle">Genera deep link firmeasy://auth con cifrado AES-256-GCM</p>

<label>Display name</label>
<input id="displayName" value="Ingresar como Organización Acme">

<label>Accepted issuers (JSON array)</label>
<textarea id="issuers" rows="3">["CN=ACME CA,O=ACME,C=PE"]</textarea>

<button id="btnGen">Generar deep link</button>
<div id="status" class="status"></div>
<pre id="output"></pre>
</div>
<script>
const API_URL = '/api/generar-auth.php';
document.getElementById('btnGen').onclick = async () => {
  const status = document.getElementById('status');
  const output = document.getElementById('output');
  status.style.display='none';
  output.textContent='';
  try {
    const display_name = document.getElementById('displayName').value.trim();
    let accepted_issuers = [];
    try { accepted_issuers = JSON.parse(document.getElementById('issuers').value); } catch(e){ throw new Error('Accepted issuers debe ser JSON array'); }
    const resp = await fetch(API_URL, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({display_name, accepted_issuers})});
    const json = await resp.json();
    if(!resp.ok) throw new Error(json.error || 'Error');
    status.className='status ok';
    status.textContent=`Job ${json.job} generado, expira ${new Date(json.exp*1000).toLocaleString()}`;
    status.style.display='block';
    output.textContent = `Deep link:\n${json.deep_link}\n\nJSON:\n${JSON.stringify(json,null,2)}`;
    // Intentar abrir deep link
    setTimeout(()=>{ window.location.href = json.deep_link; }, 500);
  } catch(err){
    status.className='status err';
    status.textContent = err.message;
    status.style.display='block';
  }
};
</script>
</body>
</html>
