<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FirmEasy — Callbacks</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; min-height: 100vh; }
        .header { background: #fff; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,.06); flex-wrap: wrap; gap: 12px; }
        .header h1 { font-size: 20px; font-weight: 700; color: #0066cc; }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: .2s; }
        .btn:hover { opacity: .85; }
        .btn-blue { background: #0066cc; color: #fff; }
        .btn-red { background: #dc3545; color: #fff; }
        .btn-green { background: #28a745; color: #fff; }
        .btn-gray { background: #6c757d; color: #fff; }
        .filter-bar { padding: 12px 24px; background: #fff; border-bottom: 1px solid #e9ecef; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .filter-bar input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; flex: 1; min-width: 200px; }
        .stats { padding: 8px 24px; background: #fff; border-bottom: 1px solid #e9ecef; font-size: 13px; color: #666; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 24px; }
        .empty { text-align: center; padding: 60px 20px; color: #999; }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }

        /* Table */
        .table-wrap { overflow-x: auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid #e9ecef; }
        td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: top; }
        tr:hover td { background: #f8f9fa; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-error { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .job-id { font-family: monospace; font-size: 11px; color: #0066cc; cursor: pointer; word-break: break-all; }
        .job-id:hover { text-decoration: underline; }
        .timestamp { font-size: 12px; color: #888; white-space: nowrap; }
        .doc-count { font-weight: 700; color: #0066cc; }
        .msg-preview { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .actions-cell { white-space: nowrap; }
        .actions-cell .btn { padding: 4px 10px; font-size: 11px; margin-right: 4px; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5); z-index: 1000; justify-content: center; align-items: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; border-radius: 12px; max-width: 700px; width: 100%; max-height: 80vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,.2); }
        .modal-header { padding: 16px 20px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 16px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }
        .modal-close:hover { color: #333; }
        .modal-body { padding: 20px; }
        .modal-body pre { background: #f8f9fa; padding: 12px; border-radius: 8px; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }

        /* Toast */
        .toast { position: fixed; bottom: 20px; right: 20px; background: #333; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 13px; z-index: 2000; display: none; }
        .toast.show { display: block; animation: fadeInUp .3s; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 640px) {
            .header { padding: 12px 16px; }
            .container { padding: 0 12px; margin: 12px auto; }
            .filter-bar { padding: 8px 12px; }
            table { font-size: 12px; }
            th, td { padding: 8px 10px; }
            .msg-preview { max-width: 140px; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Callbacks Recibidos</h1>
    <div class="header-actions">
        <button class="btn btn-blue" onclick="loadCallbacks()">Actualizar</button>
        <button class="btn btn-red" onclick="clearAll()">Limpiar Todo</button>
        <a href="/" class="btn btn-gray">Volver</a>
    </div>
</div>

<div class="filter-bar">
    <input type="text" id="filterJob" placeholder="Filtrar por Job ID..." oninput="filterTable()">
    <button class="btn btn-blue" onclick="document.getElementById('filterJob').value=''; filterTable();">Limpiar Filtro</button>
</div>

<div class="stats" id="statsBar">Cargando...</div>

<div class="container">
    <div id="callbackList">
        <div class="empty"><div class="empty-icon">📭</div>Cargando callbacks...</div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Detalle del Callback</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailBody"></div>
    </div>
</div>

<!-- Resend Modal -->
<div class="modal-overlay" id="resendModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Reenviar Callback</h3>
            <button class="modal-close" onclick="closeResendModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:12px">Selecciona el resultado del callback a reenviar:</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                <button class="btn btn-green" onclick="resendCallback('success')">Reenviar Éxito (200)</button>
                <button class="btn btn-red" onclick="resendCallback('error')">Reenviar Error (422)</button>
            </div>
            <div id="resendStatus" style="display:none"></div>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const API_BASE = '/api/list-callbacks.php';
    const CALLBACK_API = '/api/callback.php';
    let allCallbacks = [];
    let resendJobId = null;

    function showToast(msg) {
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast show';
        setTimeout(function() { t.className = 'toast'; }, 3000);
    }

    function formatTime(ts) {
        if (!ts) return '—';
        var d = new Date(ts);
        return d.toLocaleDateString('es-PE') + ' ' + d.toLocaleTimeString('es-PE');
    }

    function loadCallbacks() {
        fetch(API_BASE, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                allCallbacks = data.callbacks || [];
                renderTable(allCallbacks);
                updateStats(allCallbacks);
            })
            .catch(function(err) {
                document.getElementById('callbackList').innerHTML =
                    '<div class="empty"><div class="empty-icon">⚠️</div>Error cargando callbacks: ' + err.message + '</div>';
            });
    }

    function renderTable(list) {
        var el = document.getElementById('callbackList');
        if (!list.length) {
            el.innerHTML = '<div class="empty"><div class="empty-icon">📭</div>No hay callbacks registrados</div>';
            return;
        }
        var html = '<div class="table-wrap"><table>';
        html += '<thead><tr><th>Job ID</th><th>Estado</th><th>Código</th><th>Mensaje</th><th>Docs</th><th>Callbacks</th><th>Último</th><th>Acciones</th></tr></thead><tbody>';
        list.forEach(function(cb) {
            var badge = cb.success
                ? '<span class="badge badge-success">Éxito</span>'
                : '<span class="badge badge-error">Error</span>';
            var codeBadge = cb.code >= 200 && cb.code < 300
                ? '<span class="badge badge-success">' + cb.code + '</span>'
                : '<span class="badge badge-error">' + cb.code + '</span>';
            html += '<tr data-job="' + cb.job + '">';
            html += '<td><span class="job-id" onclick="showDetail(\'' + cb.job + '\')" title="' + cb.job + '">' + cb.job.substring(0, 8) + '…</span></td>';
            html += '<td>' + badge + '</td>';
            html += '<td>' + codeBadge + '</td>';
            html += '<td class="msg-preview" title="' + (cb.message || '').replace(/"/g, '&quot;') + '">' + (cb.message || '—') + '</td>';
            html += '<td class="doc-count">' + (cb.job_info ? cb.job_info.document_count : '—') + '</td>';
            html += '<td>' + cb.callback_count + '</td>';
            html += '<td class="timestamp">' + formatTime(cb.last_callback_at) + '</td>';
            html += '<td class="actions-cell">';
            html += '<button class="btn btn-blue" onclick="showDetail(\'' + cb.job + '\')">Ver</button>';
            html += '<button class="btn btn-green" onclick="openResend(\'' + cb.job + '\')">Reenviar</button>';
            html += '</td></tr>';
        });
        html += '</tbody></table></div>';
        el.innerHTML = html;
    }

    function updateStats(list) {
        var total = list.length;
        var success = list.filter(function(c) { return c.success; }).length;
        var error = total - success;
        var totalDocs = list.reduce(function(sum, c) { return sum + (c.job_info ? c.job_info.document_count : 0); }, 0);
        document.getElementById('statsBar').textContent =
            total + ' callbacks | ' + success + ' éxito | ' + error + ' error | ' + totalDocs + ' documentos procesados';
    }

    function filterTable() {
        var q = document.getElementById('filterJob').value.trim().toLowerCase();
        if (!q) { renderTable(allCallbacks); updateStats(allCallbacks); return; }
        var filtered = allCallbacks.filter(function(c) { return c.job.toLowerCase().includes(q); });
        renderTable(filtered);
        updateStats(filtered);
    }

    function showDetail(jobId) {
        fetch(CALLBACK_API + '?job=' + encodeURIComponent(jobId), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(cb) {
                var html = '';
                html += '<p><strong>Job:</strong> <code>' + (cb.job || jobId) + '</code></p>';
                html += '<p><strong>Estado:</strong> ' + (cb.success ? '<span class="badge badge-success">Éxito</span>' : '<span class="badge badge-error">Error</span>') + '</p>';
                html += '<p><strong>Código HTTP:</strong> ' + (cb.code || '—') + '</p>';
                html += '<p><strong>Mensaje:</strong> ' + (cb.message || '—') + '</p>';
                html += '<p><strong>Último callback:</strong> ' + formatTime(cb.last_callback_at) + '</p>';
                html += '<hr style="margin:12px 0">';
                html += '<h4 style="margin-bottom:8px">Documentos:</h4>';
                if (cb.data && cb.data.length) {
                    cb.data.forEach(function(d, i) {
                        var sBadge = d.status === 'signed'
                            ? '<span class="badge badge-success">Firmado</span>'
                            : '<span class="badge badge-error">Error</span>';
                        html += '<p>' + (i + 1) + '. ' + (d.name_pdf || '—') + ' — ' + sBadge + ' ' + (d.message || '') + '</p>';
                    });
                } else {
                    html += '<p style="color:#999">Sin datos de documentos</p>';
                }
                html += '<hr style="margin:12px 0">';
                html += '<h4 style="margin-bottom:8px">JSON Completo:</h4>';
                html += '<pre>' + JSON.stringify(cb, null, 2) + '</pre>';
                document.getElementById('detailBody').innerHTML = html;
                document.getElementById('detailModal').classList.add('active');
            })
            .catch(function(err) {
                showToast('Error: ' + err.message);
            });
    }

    function closeModal() {
        document.getElementById('detailModal').classList.remove('active');
    }

    function openResend(jobId) {
        resendJobId = jobId;
        document.getElementById('resendStatus').style.display = 'none';
        document.getElementById('resendModal').classList.add('active');
    }

    function closeResendModal() {
        document.getElementById('resendModal').classList.remove('active');
        resendJobId = null;
    }

    function resendCallback(type) {
        if (!resendJobId) return;
        var statusEl = document.getElementById('resendStatus');
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<p style="color:#0066cc">Reenviando...</p>';

        fetch('/api/resend-callback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job: resendJobId, type: type }),
            credentials: 'same-origin'
        })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success) {
                    statusEl.innerHTML = '<p style="color:#28a745">Callback reenviado (HTTP ' + resp.http_code + '). <a href="#" onclick="loadCallbacks(); closeResendModal();">Ver lista</a></p>';
                    showToast('Callback reenviado');
                } else {
                    statusEl.innerHTML = '<p style="color:#dc3545">Error: ' + (resp.error || JSON.stringify(resp)) + '</p>';
                }
            })
            .catch(function(err) {
                statusEl.innerHTML = '<p style="color:#dc3545">Error de red: ' + err.message + '</p>';
            });
    }

    function clearAll() {
        if (!confirm('¿Eliminar TODOS los logs de callback?')) return;
        fetch(API_BASE, { method: 'DELETE', credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                showToast(data.deleted + ' archivos eliminados');
                loadCallbacks();
            })
            .catch(function(err) { showToast('Error: ' + err.message); });
    }

    // Click outside modal to close
    document.getElementById('detailModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.getElementById('resendModal').addEventListener('click', function(e) {
        if (e.target === this) closeResendModal();
    });

    loadCallbacks();
</script>

</body>
</html>
