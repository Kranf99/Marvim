<?php 
require '_pe_checkSession.php';

if (!$isSuperAdmin) {
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body style="font-family:sans-serif;padding:40px;color:#c00">Access denied — super-admin only.</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvin - LLM Admin</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<style>
.llm-section {
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:8px;
    padding:20px 24px;
    margin-bottom:20px;
}
.llm-section h2 {
    font-size:16px;
    font-weight:700;
    color:#475569;
    margin:0 0 16px;
    padding-bottom:10px;
    border-bottom:1px solid #e2e8f0;
}
.llm-row {
    display:flex;
    flex-direction:column;
    margin-bottom:14px;
}
.llm-row label {
    font-size:12px;
    font-weight:600;
    color:#64748b;
    text-transform:uppercase;
    letter-spacing:.04em;
    margin-bottom:5px;
}
.llm-row input[type="text"],
.llm-row input[type="number"],
.llm-row input[type="password"] {
    width:100%;
    box-sizing:border-box;
    border:1px solid #cbd5e1;
    border-radius:6px;
    padding:8px 10px;
    font-size:13px;
    font-family:inherit;
    color:#1e293b;
    background:#f8fafc;
    transition:border-color .15s;
}
.llm-row input:focus {
    outline:none;
    border-color:#3b82f6;
    background:#fff;
}
.llm-row-inline {
    display:flex;
    gap:16px;
}
.llm-row-inline .llm-row {
    flex:1;
}
.llm-radio-group {
    display:flex;
    gap:20px;
    align-items:center;
    padding-top:4px;
}
.llm-radio-group label {
    font-size:13px;
    font-weight:400;
    color:#334155;
    text-transform:none;
    letter-spacing:0;
    display:flex;
    align-items:center;
    gap:5px;
    cursor:pointer;
}
.llm-actions {
    display:flex;
    align-items:center;
    gap:12px;
    margin-top:6px;
    flex-wrap:wrap;
}
.llm-btn {
    padding:7px 18px;
    border-radius:6px;
    border:none;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
    transition:opacity .15s;
}
.llm-btn:disabled { opacity:.5; cursor:default; }
.llm-btn-primary { background:#1976D2; color:#fff; }
.llm-btn-primary:hover:not(:disabled) { background:#1565c0; }
.llm-btn-secondary { background:#e2e8f0; color:#334155; }
.llm-btn-secondary:hover:not(:disabled) { background:#cbd5e1; }
.llm-btn-green { background:#16a34a; color:#fff; }
.llm-btn-green:hover:not(:disabled) { background:#15803d; }
.llm-status {
    font-size:13px;
    color:#64748b;
}
.llm-result {
    display:none;
    margin-top:10px;
    padding:10px 14px;
    background:#0f172a;
    border:1px solid #1e3a5f;
    border-radius:6px;
    font-family:monospace;
    font-size:11px;
    color:#93c5fd;
    max-height:200px;
    overflow-y:auto;
    line-height:1.8;
}
.llm-result .ok  { color:#86efac; }
.llm-result .err { color:#fca5a5; }
.breadcrumb { font-size:13px; color:#94a3b8; margin:4px 0 18px; }
.breadcrumb a { color:#64748b; text-decoration:none; }
.breadcrumb a:hover { text-decoration:underline; }
.llm-hint { font-size:11px; color:#94a3b8; margin-top:3px; }
</style>
</head>
<body>
<?php require "_pe_starter.php"; ?>
<!-- Content -->
<div class="content">
  <div class="main-section">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      <h1 style="font-size:28px;color:#333;margin:0;">⚙️ LLM Administration</h1>
    </div>
    <div class="breadcrumb">
      <a href="home.php">Home</a> / LLM Admin
    </div>

    <!-- ── Section 1: Server ─────────────────────────────────── -->
    <div class="llm-section">
      <h2>Local LLM Server (llama-server.exe)</h2>

      <div class="llm-row">
        <label>Executable path</label>
        <input type="text" id="srvExe" placeholder="C:/soft/TIMi/llama/cpu/llama-server.exe">
        <div class="llm-hint">Full path to llama-server.exe</div>
      </div>

      <div class="llm-row">
        <label>GGUF model file</label>
        <input type="text" id="srvGguf" list="ggufList"
               placeholder="C:/soft/TIMi/llama/model.gguf">
        <datalist id="ggufList"></datalist>
        <div class="llm-hint" id="ggufHint">Type a path or pick a file detected on disk</div>
      </div>

      <div class="llm-row-inline">
        <div class="llm-row">
          <label>Port</label>
          <input type="number" id="srvPort" placeholder="8081" min="1024" max="65535">
        </div>
        <div class="llm-row">
          <label>Context size (tokens)</label>
          <input type="number" id="srvCtx" placeholder="8192" min="256" max="131072">
        </div>
      </div>

      <div class="llm-row">
        <label>Console window</label>
        <div class="llm-radio-group">
          <label>
            <input type="radio" name="srvConsole" value="persistent" checked>
            Persistent (cmd window stays open)
          </label>
          <label>
            <input type="radio" name="srvConsole" value="hidden">
            Hidden
          </label>
        </div>
      </div>

      <div class="llm-actions">
        <button class="llm-btn llm-btn-green" id="launchBtn" onclick="launchServer()">
          ▶ Run Local Server
        </button>
        <span class="llm-status" id="launchStatus"></span>
      </div>
    </div>

    <!-- ── Section 2: LLM API ────────────────────────────────── -->
    <div class="llm-section">
      <h2>LLM API Connection</h2>

      <div class="llm-row">
        <label>API URL (OpenAI-compatible)</label>
        <input type="text" id="llmUrl"
               placeholder="http://localhost:8081/v1/chat/completions">
      </div>

      <div class="llm-row-inline">
        <div class="llm-row">
          <label>Model name</label>
          <input type="text" id="llmModel" placeholder="local-model">
        </div>
        <div class="llm-row">
          <label>Max tokens</label>
          <input type="number" id="llmMaxTokens" placeholder="4096" min="100" max="32000">
        </div>
      </div>

      <div class="llm-row">
        <label>Bearer token / API key (optional)</label>
        <input type="password" id="llmBearer" placeholder="sk-… or leave blank">
      </div>

      <div class="llm-actions">
        <button class="llm-btn llm-btn-secondary" id="testBtn" onclick="testConnection()">
          ⚡ Test connection
        </button>
        <span class="llm-status" id="testStatus"></span>
      </div>
      <div class="llm-result" id="testResult"></div>
    </div>

    <!-- ── Save ──────────────────────────────────────────────── -->
    <div class="llm-actions" style="margin-bottom:30px;">
      <button class="llm-btn llm-btn-primary" id="saveBtn" onclick="saveSettings()">
        💾 Save settings
      </button>
      <span class="llm-status" id="saveStatus"></span>
    </div>
<br>&nbsp;
  </div>
</div>
</div><!-- main-content -->
</div><!-- container -->

<script>
// ── Load config on page load ─────────────────────────────────────────────────
async function loadConfig() {
  try {
    const res = await fetch('llmSettings.php', { cache: 'no-store' });
    const cfg = await res.json();

    const srv = cfg.server || {};
    document.getElementById('srvExe').value  = srv.exe   || '';
    document.getElementById('srvGguf').value = srv.gguf  || '';
    document.getElementById('srvPort').value = srv.port  || 8081;
    document.getElementById('srvCtx').value  = srv.ctx   || 8192;
    const mode = srv.console || 'persistent';
    const radio = document.querySelector('input[name="srvConsole"][value="' + mode + '"]');
    if (radio) radio.checked = true;

    const llm = cfg.llm || {};
    document.getElementById('llmUrl').value       = llm.url        || '';
    document.getElementById('llmModel').value     = llm.model      || '';
    document.getElementById('llmMaxTokens').value = llm.max_tokens || 4096;
    document.getElementById('llmBearer').value    = llm.bearer     || '';

  } catch (e) {
    setStatus('saveStatus', 'Could not load config: ' + e.message, 'err');
  }
}

// ── Launch llama-server.exe ──────────────────────────────────────────────────
async function launchServer() {
  const btn = document.getElementById('launchBtn');
  btn.disabled = true;
  setStatus('launchStatus', 'Launching…', 'neutral');

  try {
    const res = await fetch('llmLocalRun.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        exe:     document.getElementById('srvExe').value.trim(),
        gguf:    document.getElementById('srvGguf').value.trim(),
        port:    parseInt(document.getElementById('srvPort').value)  || 8081,
        ctx:     parseInt(document.getElementById('srvCtx').value)   || 8192,
        console: document.querySelector('input[name="srvConsole"]:checked').value,
      })
    });
    const data = await res.json();
    if (data.ok) {
      setStatus('launchStatus',
        'Server launched on port ' + data.port + '. Auto-testing in 6 s…', 'ok');
      setTimeout(function() { testConnection(true); }, 6000);
    } else {
      setStatus('launchStatus', data.error || 'Launch failed', 'err');
    }
  } catch (e) {
    setStatus('launchStatus', 'Error: ' + e.message, 'err');
  }
  btn.disabled = false;
}

// ── Test LLM connection ──────────────────────────────────────────────────────
async function testConnection(fromLaunch) {
  const btn    = document.getElementById('testBtn');
  const result = document.getElementById('testResult');
  btn.disabled = true;
  result.style.display = 'none';
  setStatus('testStatus', 'Testing…', 'neutral');

  const url    = document.getElementById('llmUrl').value.trim();
  const model  = document.getElementById('llmModel').value.trim();
  const bearer = document.getElementById('llmBearer').value.trim();

  const qs = 'url='   + encodeURIComponent(url)
           + '&model='+ encodeURIComponent(model)
           + (bearer ? '&bearer=' + encodeURIComponent(bearer) : '');

  try {
    const res  = await fetch('llmTestConnection.php?' + qs, { cache: 'no-store' });
    const data = await res.json();

    if (data.ok) {
      setStatus('testStatus', 'Connected — model: ' + data.model, 'ok');
      if (fromLaunch)
        setStatus('launchStatus', 'Server launched and responding.', 'ok');
    } else {
      setStatus('testStatus', 'Connection failed', 'err');
      if (fromLaunch)
        setStatus('launchStatus', 'Server launched but not yet responding. Try Test again shortly.', 'err');
    }

    let html = '';
    (data.steps || []).forEach(function(s) {
      const cls = s.ok ? 'ok' : 'err';
      const icon = s.ok ? '✓' : '✗';
      html += '<div class="' + cls + '">' + icon + ' ' + escHtml(s.label) + ' — ' + s.ms + ' ms';
      if (s.error)  html += ' — ' + escHtml(s.error);
      if (s.answer) html += ' — reply: "' + escHtml(s.answer) + '"';
      if (s.models && s.models.length)
        html += '<br>&nbsp;&nbsp;models: ' + escHtml(s.models.join(', '));
      if (s.detail)
        html += '<br><span style="color:#7fa8c8">' + escHtml(s.detail) + '</span>';
      html += '</div>';
    });
    result.innerHTML = html;
    result.style.display = 'block';
  } catch (e) {
    setStatus('testStatus', 'Error: ' + e.message, 'err');
  }
  btn.disabled = false;
}

// ── Save settings ────────────────────────────────────────────────────────────
async function saveSettings() {
  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  setStatus('saveStatus', 'Saving…', 'neutral');

  const payload = {
    llm: {
      url:        document.getElementById('llmUrl').value.trim(),
      model:      document.getElementById('llmModel').value.trim(),
      max_tokens: parseInt(document.getElementById('llmMaxTokens').value) || 4096,
      bearer:     document.getElementById('llmBearer').value.trim(),
    },
    server: {
      exe:     document.getElementById('srvExe').value.trim(),
      gguf:    document.getElementById('srvGguf').value.trim(),
      port:    parseInt(document.getElementById('srvPort').value)  || 8081,
      ctx:     parseInt(document.getElementById('srvCtx').value)   || 8192,
      console: document.querySelector('input[name="srvConsole"]:checked').value,
    }
  };

  try {
    const res  = await fetch('llmSettings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.ok) {
      setStatus('saveStatus', 'Saved.', 'ok');
    } else {
      setStatus('saveStatus', data.error || 'Save failed', 'err');
    }
  } catch (e) {
    setStatus('saveStatus', 'Error: ' + e.message, 'err');
  }
  btn.disabled = false;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function setStatus(id, msg, type) {
  var el = document.getElementById(id);
  el.textContent = msg;
  el.style.color = type === 'ok'      ? '#16a34a'
                 : type === 'err'     ? '#dc2626'
                 : /* neutral */        '#64748b';
}

function escHtml(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadConfig();
</script>
</body>
</html>
