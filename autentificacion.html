<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FirmEasy - Autenticación</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:linear-gradient(180deg,#f8fafc,#eef2f7);min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:32px}
.card{background:#fff;border-radius:16px;padding:28px;width:100%;max-width:860px;box-shadow:0 10px 30px rgba(0,0,0,.08);border:1px solid #e5e7eb}
h1{font-size:1.8rem;margin-bottom:6px;color:#0f172a;text-align:center}
p.subtitle{color:#64748b;margin-bottom:24px;font-size:.95rem;text-align:center}
.grid{display:grid;grid-template-columns:1fr;gap:20px}
label{display:block;margin:10px 0 6px;font-weight:600;color:#334155;font-size:.9rem}
input,textarea{width:100%;max-width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:.95rem;background:#f8fafc;transition:border .2s;overflow-wrap:anywhere}
input:focus,textarea:focus{outline:none;border-color:#2563eb;background:#fff}
button{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;padding:12px 18px;border-radius:10px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(37,99,235,.25);white-space:nowrap}
button:hover{opacity:.95}
button:disabled{opacity:.6;cursor:not-allowed}
pre{background:#0f172a;color:#e2e8f0;border-radius:10px;padding:14px;font-size:.75rem;word-break:break-word;white-space:pre-wrap;margin-top:16px;max-height:260px;overflow:auto}
.status{margin-top:12px;padding:12px 14px;border-radius:10px;display:none;font-weight:600}
.status.ok{background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7}
.status.err{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}
.section{margin-top:28px;padding-top:20px;border-top:1px dashed #e2e8f0}
.section h2{font-size:1.1rem;color:#1e293b;margin-bottom:12px}
@media(max-width:800px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="card">
<h1>Autenticación FirmEasy</h1>
<p class="subtitle">Genera deep link firmeasy://auth con cifrado AES-256-GCM</p>

<div class="grid">
<div>
<label>Display name</label>
<input id="displayName" value="Ingresar como Organización Acme">
<label>Accepted issuers (JSON array)</label>
<textarea id="issuers" rows="4">["CN=AC RAIZ001, O=RENIEC, C=PE","CN=FirmEasy SubCA, O=GIRASOL PE SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA, C=PE","CN=ECEP-RENIEC CAClass 2,O=Registro Nacional de Identificación y Estado Civil,C=PE"]</textarea>
</div>
<div>
<div style="height:100%;display:flex;flex-direction:column;justify-content:flex-start">
<button id="btnGen" style="margin-top:28px;width:100%">Generar deep link</button>
<div id="status" class="status"></div>
<pre id="output"></pre>
</div>
</div>
</div>

<div class="section">
<h2>Test: certificado de prueba</h2>
<div class="grid">
<div>
<label>State</label>
<input id="testState">
<label>Certificado .cer / .pem</label>
<input type="file" id="certFile" accept=".cer,.pem">
<button id="btnUploadCert">Subir certificado</button>
</div>
<div>
<div id="status2" class="status"></div>
<pre id="output2"></pre>
</div>
</div>
<p style="font-size:.85rem;color:#64748b;margin-top:8px">Con el certificado subido, cuando la app haga POST a /api/auth/submit.php con state y signature, la web validará automáticamente contra el .cer guardado.</p>
<label>Resultado de verificación</label>
<div id="statusVerify" class="status"></div>
<pre id="outputVerify"></pre>
</div>

</div>
<script>
const API_URL = '/api/generar-auth.php';
const hideIfEmpty = (el)=>{ if(!el.textContent.trim()) el.style.display='none'; };
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
    document.getElementById('testState').value = json.state;
    setTimeout(()=>{ window.location.href = json.deep_link; }, 500);
  } catch(err){
    status.className='status err';
    status.textContent = err.message;
    status.style.display='block';
  }
};

document.getElementById('btnUploadCert').onclick = async () => {
  const status = document.getElementById('status2');
  const output = document.getElementById('output2');
  const state = document.getElementById('testState').value.trim();
  const file = document.getElementById('certFile').files[0];
  if (!state || !file) { alert('State y certificado requeridos'); return; }
  const text = await file.text();
  const resp = await fetch('/api/auth/upload-cert.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({state, certificate: text})});
  const json = await resp.json();
  status.className='status ok';
  status.textContent='Certificado guardado para este state';
  status.style.display='block';
  output.textContent = JSON.stringify(json,null,2);
  startVerifyPoll(state);
};

async function startVerifyPoll(state){
  const status = document.getElementById('statusVerify');
  const output = document.getElementById('outputVerify');
  const check = async ()=>{
    try{
      const resp = await fetch('/api/auth/check-verify.php?state=' + encodeURIComponent(state));
      const json = await resp.json();
      if(json.received){
        status.className = json.verification_ok ? 'status ok' : 'status err';
        status.textContent = json.verification_ok ? 'Autenticación verificada' : 'Verificación fallida: '+ (json.verification_error||'');
        status.style.display='block';
        output.textContent = JSON.stringify(json,null,2);
      }
    }catch(e){}
  };
  check();
  setInterval(check, 3000);
}


</script>
</body>
</html>
