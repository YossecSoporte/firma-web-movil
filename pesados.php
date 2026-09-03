<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Documentos Pesados - FirmEasy</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      min-height: 100vh; display: flex; flex-direction: column; align-items: center;
      padding: 16px; background: #f5f5f5;
    }
    .card {
      background: #fff; border-radius: 12px; padding: 24px; width: 100%;
      max-width: 800px; box-shadow: 0 2px 12px rgba(0,0,0,.08);
    }
    h1 { font-size: 1.5rem; margin-bottom: 8px; color: #1a1a1a; text-align: center; }
    p.subtitle { color: #666; margin-bottom: 20px; font-size: .9rem; text-align: center; }

    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
    .toolbar .count { font-size: .85rem; color: #6c757d; }
    .refresh-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; font-size: .82rem; font-weight: 500;
      background: #fff; color: #0066cc; border: 1px solid #0066cc; border-radius: 6px;
      cursor: pointer; transition: all .15s;
    }
    .refresh-btn:hover { background: #0066cc; color: #fff; }
    .refresh-btn svg { width: 14px; height: 14px; }
    .refresh-btn .spin { animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .btn-sign-batch { background: #fd7e14; color: #fff; border-color: #fd7e14; }
    .btn-sign-batch:hover:not(:disabled) { background: #e06a0d; border-color: #e06a0d; }

    .table-wrap { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
      background: #f8f9fa; padding: 12px 10px; text-align: left;
      font-size: .78rem; font-weight: 600; color: #495057;
      border-bottom: 2px solid #dee2e6; text-transform: uppercase; letter-spacing: .03em;
    }
    tbody td { padding: 14px 10px; border-bottom: 1px solid #e9ecef; font-size: .9rem; color: #212529; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #f8f9fa; }

    .doc-name { font-weight: 500; }
    .doc-size { color: #6c757d; font-size: .85rem; }
    .doc-status {
      display: inline-block; padding: 3px 10px; border-radius: 12px;
      font-size: .75rem; font-weight: 500;
    }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-signed { background: #d4edda; color: #155724; }

    .actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .btn-action {
      display: inline-flex; align-items: center; justify-content: center; gap: 5px;
      padding: 7px 12px; font-size: .82rem; font-weight: 500;
      border: 1px solid transparent; border-radius: 6px;
      cursor: pointer; text-decoration: none; transition: all .15s; white-space: nowrap;
    }
    .btn-action:disabled { opacity: .5; cursor: not-allowed; }
    .btn-view { background: #e9ecef; color: #495057; border-color: #dee2e6; }
    .btn-view:hover:not(:disabled) { background: #dee2e6; }
    .btn-sign { background: #0066cc; color: #fff; }
    .btn-sign:hover:not(:disabled) { background: #0052a3; }
    .btn-view-signed { background: #28a745; color: #fff; }
    .btn-view-signed:hover:not(:disabled) { background: #218838; }
    .btn-action svg { width: 14px; height: 14px; flex-shrink: 0; }

    .status-bar {
      margin-top: 16px; padding: 12px 16px; border-radius: 8px;
      font-size: .85rem; display: none;
    }
    .status-bar.info { background: #e7f1ff; color: #0052a3; }
    .status-bar.error { background: #fdeaea; color: #c0392b; }
    .status-bar.success { background: #e8f5e9; color: #2e7d32; }

    .deep-link-display {
      display: none; margin-top: 16px; padding: 12px; background: #f7fafc;
      border-radius: 8px; font-size: .75rem; text-align: left; word-break: break-all;
      font-family: monospace; border: 1px solid #e2e8f0;
    }
    .footer { margin-top: 20px; font-size: .75rem; color: #999; text-align: center; }
    .empty-state { text-align: center; padding: 40px 20px; color: #6c757d; }

    .modal-overlay {
      display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4);
      z-index: 1000; align-items: center; justify-content: center; padding: 16px;
    }
    .modal-overlay.open { display: flex; }
    .modal {
      background: #fff; border-radius: 12px; padding: 24px; width: 100%; max-width: 400px;
      box-shadow: 0 10px 40px rgba(0,0,0,.15); animation: modalIn .15s ease-out;
    }
    @keyframes modalIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .modal h3 { margin-bottom: 16px; font-size: 1.1rem; color: #1a1a1a; text-align: center; }
    .modal .form-group { margin-bottom: 14px; }
    .modal label { display: block; margin-bottom: 6px; font-size: .85rem; font-weight: 500; color: #333; }
    .modal select {
      width: 100%; padding: 10px 12px; font-size: .9rem; border: 1px solid #ced4da; border-radius: 6px;
      background: #fff; color: #212529; transition: border-color .15s, box-shadow .15s;
    }
    .modal select:focus { outline: none; border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,.15); }
    .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; }
    .modal-btn {
      padding: 10px 20px; font-size: .88rem; font-weight: 500; border-radius: 6px;
      cursor: pointer; transition: all .15s; border: 1px solid transparent;
    }
    .modal-btn.cancel { background: #fff; color: #6c757d; border-color: #dee2e6; }
    .modal-btn.cancel:hover { background: #f8f9fa; }
    .modal-btn.confirm { background: #0066cc; color: #fff; }
    .modal-btn.confirm:hover { background: #0052a3; }
    .modal-btn:disabled { opacity: .6; cursor: not-allowed; }

    @media (max-width: 640px) {
      body { padding: 12px; }
      .card { padding: 18px; }
      h1 { font-size: 1.3rem; }
      .toolbar { flex-direction: column; align-items: stretch; }
      .toolbar > div { width: 100%; }
      .toolbar > div > button { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Documentos Pesados</h1>
    <p class="subtitle">Solo archivos de alto peso para pruebas de rendimiento.</p>

    <div class="toolbar">
      <span id="docCount" class="count">Cargando...</span>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <button id="btnBatchSign" class="refresh-btn btn-sign-batch" type="button" disabled>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          <span id="batchLabel">Firmar todas las hojas</span>
        </button>
        <button id="btnRefresh" class="refresh-btn" type="button">
          <svg id="refreshIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
          <span id="refreshLabel">Actualizar</span>
        </button>
      </div>
    </div>

    <div class="table-wrap">
      <table id="docsTable">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Tamaño</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="docsBody">
          <tr><td colspan="4" class="empty-state">Cargando documentos...</td></tr>
        </tbody>
      </table>
    </div>

    <div id="statusBar" class="status-bar"></div>

    <div id="deepLinkDisplay" class="deep-link-display">
      <strong>URI generada:</strong>
      <div id="deepLinkUri" style="margin-top: 4px;"></div>
      <div style="margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
        <strong>JSON del job:</strong>
        <pre id="deepLinkJson" style="margin: 4px 0 0; white-space: pre-wrap; font-size: .7rem; color: #334155;"></pre>
      </div>
    </div>

    <div class="footer">Requiere app FirmEasy instalada en este dispositivo.</div>
  </div>

  <!-- Modal Certificate Type -->
  <div id="signModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal">
      <h3 id="modalTitle">Configurar firma</h3>
      <div class="form-group">
        <label for="modalCertType">Tipo de certificado</label>
        <select id="modalCertType">
          <option value="all">Todos (DNI + Certificado)</option>
          <option value="dni">Solo DNI</option>
          <option value="certificado">Solo Certificado</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="modal-btn cancel" id="modalCancel">Cancelar</button>
        <button type="button" class="modal-btn confirm" id="modalConfirm">Continuar</button>
      </div>
    </div>
  </div>

<script>
(function () {
  const HEAVY_FILES = ['doc_prueba6.pdf', 'doc_prueba8.pdf'];
  const API_URL             = '/api/generar-uri.php';
  const LIST_URL            = '/api/list-pdfs.php';
  const LIST_SIGNED_URL     = '/api/list-signed.php';
  const DOWNLOAD_URL        = '/api/download.php';
  const DOWNLOAD_SIGNED_URL = '/api/download-signed.php';
  const CLEAR_SIGNED_URL    = '/api/clear-signed.php';
  const FALLBACK_URL        = 'app-no-instalada.php';
  const FALLBACK_DELAY_MS   = 3500;
  const VISIBILITY_GRACE_MS = 5000;

  const tbody       = document.getElementById('docsBody');
  const statusBar   = document.getElementById('statusBar');
  const docCount    = document.getElementById('docCount');
  const btnRefresh  = document.getElementById('btnRefresh');
  const refreshIcon = document.getElementById('refreshIcon');
  const refreshLabel = document.getElementById('refreshLabel');
  const btnBatch    = document.getElementById('btnBatchSign');
  let pollingTimer  = null;
  let signedMap = {};

  const ICO_VIEW   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
  const ICO_SIGN   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>';
  const ICO_SIGNED = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
  const ICO_WAIT   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>';

  function showStatus(msg, type) {
    statusBar.textContent = msg;
    statusBar.className = 'status-bar ' + type;
    statusBar.style.display = msg ? 'block' : 'none';
  }

  function startPolling(selectedFile, timeoutSec) {
    if (pollingTimer) clearInterval(pollingTimer);
    showStatus('Firma en progreso... vuelve a esta pestaña al terminar.', 'info');
    const max = (timeoutSec || 60) * 1000;
    const start = Date.now();
    pollingTimer = setInterval(function () {
      fetch(LIST_SIGNED_URL + '?original=' + encodeURIComponent(selectedFile), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success && data.files && data.files.length > 0) {
            clearInterval(pollingTimer);
            pollingTimer = null;
            showStatus('PDF firmado detectado. Actualizando lista...', 'success');
            setTimeout(function () { showStatus('', 'info'); }, 3000);
            loadPdfList();
          } else if (Date.now() - start > max) {
            clearInterval(pollingTimer);
            pollingTimer = null;
            showStatus('Tiempo de espera agotado. Recarga manual si el PDF está firmado.', 'error');
            setTimeout(function () { showStatus('', 'info'); }, 5000);
          }
        })
        .catch(function () { /* retry */ });
    }, 5000);
  }

  function stopPolling() {
    if (pollingTimer) { clearInterval(pollingTimer); pollingTimer = null; }
  }

  function formatBytes(b) {
    if (b === 0) return '0 B';
    const k = 1024, sizes = ['B','KB','MB','GB'];
    const i = Math.floor(Math.log(b) / Math.log(k));
    return parseFloat((b / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
  }

  function baseName(f) { return f.replace(/\.pdf$/i, ''); }
  function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function escapeAttr(s) { return String(s).replace(/"/g,'"').replace(/'/g,'&#39;').replace(/</g,'<').replace(/>/g,'>'); }

  async function loadSignedList() {
    try {
      const r = await fetch(LIST_SIGNED_URL, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const data = await r.json();
      signedMap = {};
      if (data.success && data.files) {
        data.files.forEach(function(f) {
          const base = f.filename.replace(/_[^_]+\.pdf$/i, '');
          signedMap[base] = { filename: f.filename, url: f.url, size: f.size, modified: f.modified };
        });
      }
    } catch (e) { console.warn('No se pudo cargar lista de firmados:', e.message); }
  }

  async function loadPdfList() {
    showStatus('', 'info');
    tbody.innerHTML = '<tr><td colspan="4" class="empty-state">Cargando documentos...</td></tr>';

    await loadSignedList();

    try {
      const resp = await fetch(LIST_URL, { credentials: 'same-origin' });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const data = await resp.json();

      if (!data.success || data.files.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">No hay PDFs disponibles</td></tr>';
        docCount.textContent = '0 documentos';
        return;
      }

      var visibleFiles = data.files.filter(function(f) { return HEAVY_FILES.indexOf(f.filename) !== -1; });
      docCount.textContent = visibleFiles.length + ' documento' + (visibleFiles.length !== 1 ? 's' : '');

      var pendingCount = visibleFiles.filter(function(f) { return !signedMap.hasOwnProperty(baseName(f.filename)); }).length;
      btnBatch.disabled = pendingCount < 1;

      tbody.innerHTML = visibleFiles.map(function(f) {
        const base = baseName(f.filename);
        const isSigned = signedMap.hasOwnProperty(base);
        const si = signedMap[base];
        const signedUrl = si ? (DOWNLOAD_SIGNED_URL + '?file=' + encodeURIComponent(si.filename)) : '#';
        const viewUrl = DOWNLOAD_URL + '?file=' + encodeURIComponent(f.filename);
        return '<tr>'
          + '<td class="doc-name">' + escapeHtml(f.filename) + '</td>'
          + '<td class="doc-size">' + formatBytes(f.size) + '</td>'
          + '<td>' + (isSigned ? '<span class="doc-status status-signed">Firmado</span>' : '<span class="doc-status status-pending">Pendiente</span>') + '</td>'
          + '<td><div class="actions">'
          +   '<a class="btn-action btn-view" href="' + viewUrl + '" target="_blank" rel="noopener">' + ICO_VIEW + ' Ver PDF</a>'
          +   '<button class="btn-action btn-sign" data-file="' + escapeAttr(f.filename) + '">' + ICO_SIGN + ' Firmar</button>'
          +   (isSigned
                ? '<a class="btn-action btn-view-signed" href="' + signedUrl + '" target="_blank" rel="noopener">' + ICO_SIGNED + ' Ver firmado</a>'
                : '<button class="btn-action btn-view-signed" disabled>' + ICO_SIGNED + ' Ver firmado</button>')
          + '</div></td></tr>';
      }).join('');

      document.querySelectorAll('.btn-sign').forEach(function(btn) {
        btn.addEventListener('click', function() { showSignModal(btn.getAttribute('data-file')); });
      });

    } catch (err) {
      tbody.innerHTML = '<tr><td colspan="4" class="empty-state">Error al cargar</td></tr>';
      docCount.textContent = 'Error';
      showStatus('No se pudo cargar la lista: ' + err.message, 'error');
    }
  }

  async function refreshAll() {
    refreshIcon.classList.add('spin');
    refreshLabel.textContent = 'Limpiando...';
    btnRefresh.disabled = true;
    try {
      const resp = await fetch(CLEAR_SIGNED_URL + '?confirm=1', { method: 'POST', credentials: 'same-origin' });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
    } catch (e) { console.warn('No se pudo limpiar firmados:', e.message); }
    await loadPdfList();
    showStatus('Documentos limpiados. Estado restaurado.', 'success');
    setTimeout(function() { showStatus('', 'info'); }, 3000);
    refreshIcon.classList.remove('spin');
    refreshLabel.textContent = 'Actualizar';
    btnRefresh.disabled = false;
  }

  // ===== MODAL =====
  let pendingFile = null;
  let pendingBatch = false;
  let pendingFilesList = [];

  function showSignModal(file) {
    pendingFile = file;
    pendingBatch = false;
    document.getElementById('modalCertType').value = 'all';
    document.getElementById('signModal').classList.add('open');
  }

  function showSignModalBatch(files) {
    pendingBatch = true;
    pendingFile = null;
    pendingFilesList = files;
    document.getElementById('modalCertType').value = 'all';
    document.getElementById('signModal').classList.add('open');
  }

  function hideSignModal() {
    document.getElementById('signModal').classList.remove('open');
    pendingFile = null;
    pendingBatch = false;
    pendingFilesList = [];
  }

  document.getElementById('modalCancel').addEventListener('click', hideSignModal);
  document.getElementById('modalConfirm').addEventListener('click', async function() {
    const certificateType = document.getElementById('modalCertType').value;
    if (pendingBatch) {
      const files = pendingFilesList.slice();
      const type = certificateType;
      hideSignModal();
      await doSignBatch(files, type);
    } else if (pendingFile) {
      const file = pendingFile;
      const type = certificateType;
      hideSignModal();
      await doSign(file, type);
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideSignModal();
  });

  // ===== FIRMAR INDIVIDUAL =====
  async function doSign(selectedFile, certificateType) {
    if (!selectedFile) { showStatus('Documento no válido.', 'error'); return; }

    const btn = document.querySelector('.btn-sign[data-file="' + escapeAttr(selectedFile) + '"]');
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = ICO_WAIT + ' Preparando...'; }
    showStatus('Obteniendo URI de firma para ' + selectedFile + '...', 'info');

    const GRAPHIC_URL = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTlcZ50Ci9uRJBet3r17ORbbDGEq-adGoaPS5Hm8L07qD_okGo9F6URTWE&s=10';
    const SIG_TEXT = 'Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}';

    let data;
    try {
      const resp = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          configuration: {
            signature_type: 'basic',
            signature_reason: 'Acepto el contenido del documento',
            generate_request: 'NOMBRE EMPRESA',
            certificate_type: certificateType
          },
          token: '',
          documents: [{
            file: selectedFile, user_id: 'USER123', doc_sha256: '',
            settings: {
              vis_sig_x: 340, vis_sig_y: 693, vis_sig_width: 155, vis_sig_height: 55,
              vis_sig_page: 1, vis_sig_text_size: 10,
              vis_sig_text: SIG_TEXT,
              vis_sig_graphic: GRAPHIC_URL
            }
          }]
        }),
        credentials: 'same-origin'
      });
      if (!resp.ok) {
        const errData = await resp.json().catch(() => ({}));
        throw new Error(errData.error || 'HTTP ' + resp.status);
      }
      data = await resp.json();
    } catch (err) {
      if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
      showStatus('No se pudo obtener la URI: ' + err.message, 'error');
      return;
    }

    if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }

    const encryptedBlob = data.uri_encrypted || data.data;
    const deepLink = 'firmeasy://sign?data=' + encodeURIComponent(encryptedBlob);

    const deepLinkDisplay = document.getElementById('deepLinkDisplay');
    document.getElementById('deepLinkUri').textContent = deepLink + '\n\n(Plano: ' + (data.uri_plain || 'N/A') + ')';
    document.getElementById('deepLinkJson').textContent = JSON.stringify({ job: data.job, configuration: { signature_type: 'basic', signature_reason: 'Acepto el contenido del documento', generate_request: 'NOMBRE EMPRESA', certificate_type: certificateType }, documents: data.documents }, null, 2);
    deepLinkDisplay.style.display = 'block';

    showStatus('Abriendo app FirmEasy para firmar ' + selectedFile + '...', 'info');

    const start = Date.now();
    let fallbackTriggered = false;
    const timer = setTimeout(function () {
      if (fallbackTriggered) return;
      if (Date.now() - start < VISIBILITY_GRACE_MS && document.visibilityState === 'visible') {
        fallbackTriggered = true;
        window.location.href = FALLBACK_URL;
      }
    }, FALLBACK_DELAY_MS);

    function cancelFallback() {
      if (!fallbackTriggered) { clearTimeout(timer); fallbackTriggered = true; }
    }
    document.addEventListener('visibilitychange', function onVis() {
      if (document.visibilityState === 'hidden') cancelFallback();
    }, { once: true });
    window.addEventListener('pagehide', cancelFallback, { once: true });
    window.addEventListener('blur', cancelFallback, { once: true });

    window.location.href = deepLink;
    startPolling(selectedFile, 60);
  }

  // ===== FIRMAR EN BLOQUE (TODAS LAS HOJAS) =====
  async function doSignBatch(files, certificateType) {
    if (files.length === 0) { showStatus('No hay documentos pendientes.', 'error'); return; }

    var GRAPHIC_URL = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTlcZ50Ci9uRJBet3r17ORbbDGEq-adGoaPS5Hm8L07qD_okGo9F6URTWE&s=10';
    var SIG_TEXT = 'Firmado digitalmente por:\n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy\nMotivo: {{signature_reason}}';

    var btnBatch = document.getElementById('btnBatchSign');
    var batchLabel = document.getElementById('batchLabel');
    var origLabel = batchLabel.textContent;
    btnBatch.disabled = true;
    batchLabel.textContent = 'Preparando...';
    btnBatch.querySelector('svg').classList.add('spin');
    showStatus('Generando firma en bloque (todas las hojas) para ' + files.length + ' documentos...', 'info');

    var documents = files.map(function(file) {
      return {
        file: file, user_id: 'USER123', doc_sha256: '',
        settings: { vis_sig_page: -1, vis_sig_text: SIG_TEXT, vis_sig_graphic: GRAPHIC_URL }
      };
    });

    var data;
    try {
      var resp = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          configuration: {
            signature_type: 'basic',
            signature_reason: 'Acepto el contenido del documento',
            generate_request: 'NOMBRE EMPRESA',
            certificate_type: certificateType
          },
          token: '',
          documents: documents
        }),
        credentials: 'same-origin'
      });
      if (!resp.ok) {
        var errData = await resp.json().catch(function() { return {}; });
        throw new Error(errData.error || 'HTTP ' + resp.status);
      }
      data = await resp.json();
    } catch (err) {
      btnBatch.disabled = false;
      batchLabel.textContent = origLabel;
      btnBatch.querySelector('svg').classList.remove('spin');
      showStatus('No se pudo generar la URI en bloque: ' + err.message, 'error');
      return;
    }

    btnBatch.disabled = false;
    batchLabel.textContent = origLabel;
    btnBatch.querySelector('svg').classList.remove('spin');

    var encryptedBlob = data.uri_encrypted || data.data;
    var deepLink = 'firmeasy://sign?data=' + encodeURIComponent(encryptedBlob);

    var deepLinkDisplay = document.getElementById('deepLinkDisplay');
    document.getElementById('deepLinkUri').textContent = deepLink + '\n\n(Plano: ' + (data.uri_plain || 'N/A') + ')';
    document.getElementById('deepLinkJson').textContent = JSON.stringify({ job: data.job, configuration: { signature_type: 'basic', signature_reason: 'Acepto el contenido del documento', generate_request: 'NOMBRE EMPRESA', certificate_type: certificateType }, documents: data.documents }, null, 2);
    deepLinkDisplay.style.display = 'block';

    showStatus('Abriendo app FirmEasy para firmar ' + files.length + ' documentos...', 'info');

    var start = Date.now();
    var fallbackTriggered = false;
    var timer = setTimeout(function () {
      if (fallbackTriggered) return;
      if (Date.now() - start < VISIBILITY_GRACE_MS && document.visibilityState === 'visible') {
        fallbackTriggered = true;
        window.location.href = FALLBACK_URL;
      }
    }, FALLBACK_DELAY_MS);

    function cancelFallback() {
      if (!fallbackTriggered) { clearTimeout(timer); fallbackTriggered = true; }
    }
    document.addEventListener('visibilitychange', function onVis() {
      if (document.visibilityState === 'hidden') cancelFallback();
    }, { once: true });
    window.addEventListener('pagehide', cancelFallback, { once: true });
    window.addEventListener('blur', cancelFallback, { once: true });

    window.location.href = deepLink;
    startPolling(files[0], 90);
  }

  // ===== INIT =====
  btnRefresh.addEventListener('click', refreshAll);
  btnBatch.addEventListener('click', function() {
    var pending = [];
    document.querySelectorAll('.btn-sign').forEach(function(btn) {
      if (!btn.disabled) pending.push(btn.getAttribute('data-file'));
    });
    if (pending.length === 0) {
      showStatus('No hay documentos pendientes para firma en bloque.', 'error');
      return;
    }
    showSignModalBatch(pending);
  });

  loadPdfList();
})();
</script>
</body>
</html>
