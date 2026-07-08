// ── Auto Complete (LLM) ──────────────────────────────────────────────────────
var _llmHasData = false;

function _updateAutoCompleteBtn() {
  var btn = document.getElementById('llm-autocomplete-btn');
  if (!btn) return;
  var ed = window.hugerte && hugerte.get('editorMain');
  var editorOn = ed && ed.mode.get() === 'design';
  btn.style.display = (_llmHasData && editorOn) ? 'inline-block' : 'none';
}

// ── Lineage iframe navigation (click on a .anatella node in the pipeline/graph) ──
window.addEventListener('message', async function(ev) {
  if (!ev.data || ev.data.type !== 'lineage-navigate' || !ev.data.path) return;
  try {
    var r = await fetch('lineage/find_asset.php?path=' + encodeURIComponent(ev.data.path), {cache:'no-store'});
    var d = await r.json();
    if (d && d.id) {
      window.location.href = 'oneWorkflow.php?idasset=' + d.id;
    } else {
      alert('This script is not registered as a Marvin asset yet: ' + ev.data.path);
    }
  } catch (e) {
    alert('Could not navigate to script: ' + e.message);
  }
});

(async function checkPipelineAvailable() {
  var script = <?php echo json_encode($scriptFull); ?>;
  if (!script) return;
  try {
    var res  = await fetch('lineage/lineage_api.php?action=graph_for_script&depth=direct&script=' + encodeURIComponent(script), {cache:'no-store'});
    var data = await res.json();
    _llmHasData = (data.rows && data.rows.length > 0) ||
                  (data.rel_before && data.rel_before.length > 0) ||
                  (data.rel_after  && data.rel_after.length  > 0) ||
                  (data.db_inputs  && data.db_inputs.length  > 0);
    _updateAutoCompleteBtn();
    if (window.hugerte) {
      hugerte.on('AddEditor', function(e) {
        if (e.editor.id === 'editorMain') {
          e.editor.on('SwitchMode', _updateAutoCompleteBtn);
        }
      });
    }
  } catch(e) {}
})();

async function llmAutoComplete() {
  var btn    = document.getElementById('llm-autocomplete-btn');
  var status = document.getElementById('llm-autocomplete-status');
  btn.disabled = true;
  btn.textContent = '⏳ Loading…';
  status.textContent = '';

  var script = <?php echo json_encode($scriptFull); ?>;
  var name   = <?php echo json_encode(isset($rowAsset['name']) ? $rowAsset['name'] : ''); ?>;

  try {
    // 1. LLM config
    var cfgRes = await fetch('llmSettings.php', {cache:'no-store'});
    var cfg    = await cfgRes.json();
    if (!cfg.llm || !cfg.llm.url) {
      status.textContent = 'LLM not configured — see LLM Admin.';
      btn.disabled = false; btn.textContent = '✨ Auto Complete'; return;
    }

    // 2. Lineage data
    btn.textContent = '⏳ Reading pipeline…';
    var linRes  = await fetch('lineage/lineage_api.php?action=graph_for_script&depth=direct&script=' + encodeURIComponent(script), {cache:'no-store'});
    var linData = await linRes.json();
    var focal   = linData.focal || script;

    var inputs = [], outputs = [], seenI = {}, seenO = {};
    (linData.rows || []).forEach(function(row) {
      var src = row.source, dst = row.destination, mid = row.s0;
      if (mid === focal) {
        if (src && !seenI[src]) { seenI[src] = 1; inputs.push(src); }
        if (dst && !seenO[dst]) { seenO[dst] = 1; outputs.push(dst); }
      } else if (src === focal) {
        if (dst && !seenO[dst]) { seenO[dst] = 1; outputs.push(dst); }
      } else if (dst === focal) {
        if (src && !seenI[src]) { seenI[src] = 1; inputs.push(src); }
      }
    });

    // 3. Build prompt
    var parts = [];
    parts.push('You are a data pipeline documentation assistant. Write a professional description in 200 words for the following workflow.\n');
    parts.push('Workflow name: ' + name);
    parts.push('Script file: ' + script);
    if (inputs.length)  parts.push('\nInput data sources:\n'  + inputs.map(function(f){return '- '+f;}).join('\n'));
    if ((linData.db_inputs||[]).length) parts.push('\nDatabase / SQL queries:\n' + linData.db_inputs.map(function(f){return '- '+f;}).join('\n'));
    if (outputs.length) parts.push('\nOutput destinations:\n' + outputs.map(function(f){return '- '+f;}).join('\n'));
    if ((linData.rel_before||[]).length) parts.push('\nUpstream scripts (produce inputs):\n' + linData.rel_before.slice(0,8).map(function(f){return '- '+f;}).join('\n'));
    if ((linData.rel_after||[]).length)  parts.push('\nDownstream scripts (consume outputs):\n' + linData.rel_after.slice(0,8).map(function(f){return '- '+f;}).join('\n'));
    parts.push('\nWrite 2–4 paragraphs describing: (1) purpose and role of this workflow, (2) what data it reads and from where, (3) what it produces and where it goes, (4) how it fits in the broader pipeline. Be specific about file names. Professional style, English only.');

    // 4. Stream into HugeRTE
    btn.textContent = '⏳ Generating…';
    var llmUrl  = cfg.llm.url;
    var headers = {'Content-Type':'application/json'};
    if (cfg.llm.bearer) headers['Authorization'] = 'Bearer ' + cfg.llm.bearer;

    var ed = (window.hugerte && hugerte.get('editorMain')) || null;
    if (ed) ed.mode.set('design');
    var existingHtml = ed ? ed.getContent() : '';
    var existingText = ed ? ed.getContent({format:'text'}).trim() : '';
    if (existingText) {
      parts.push('\nThe field already contains the following text. Do NOT rewrite it — only write new paragraphs to add after it:\n"""\n' + existingText + '\n"""\nWrite only the continuation.');
    }

    var llmRes = await fetch(llmUrl, {
      method: 'POST', headers: headers,
      body: JSON.stringify({
        model: cfg.llm.model || 'local-model',
        messages: [{role:'user', content: parts.join('\n')}],
        max_tokens: cfg.llm.max_tokens || 4096,
        temperature: 0.7, stream: true
      })
    });
    if (!llmRes.ok) throw new Error('LLM HTTP ' + llmRes.status);

    var fullText = '', renderTimer = null;

    function renderNow() {
      renderTimer = null;
      if (!ed) return;
      var newPart = '<p>' + fullText.replace(/\n\n+/g,'</p><p>').replace(/\n/g,'<br>') + '</p>';
      ed.setContent(existingHtml + newPart);
    }

    var reader = llmRes.body.getReader(), decoder = new TextDecoder(), buf = '';
    for (;;) {
      var chunk = await reader.read();
      if (chunk.done) break;
      buf += decoder.decode(chunk.value, {stream:true});
      var lines = buf.split('\n'); buf = lines.pop();
      for (var i = 0; i < lines.length; i++) {
        var ln = lines[i].trim();
        if (!ln.startsWith('data: ')) continue;
        var json = ln.slice(6); if (json === '[DONE]') continue;
        try {
          var obj   = JSON.parse(json);
          var delta = obj.choices && obj.choices[0] && obj.choices[0].delta && obj.choices[0].delta.content || '';
          if (delta) { fullText += delta; if (!renderTimer) renderTimer = setTimeout(renderNow, 150); }
        } catch(e2) {}
      }
    }
    if (renderTimer) { clearTimeout(renderTimer); }
    renderNow();

    // Save long description
    var ta = document.getElementById('editorMain');
    if (ed && ta) 
    {
      var t=ed.getContent();
      myEditorContent = t;
      saveContent(t, ed.getContent({ format: 'text' }), ta, ed);
    }

    // 5. Short description — built from pipeline outputs
    var outNames = outputs.map(function(p){ return p.replace(/\\/g,'/').split('/').pop(); });
    var shortText = outNames.length ? 'Generate ' + outNames.join(', ') + '.' : '';
    if (shortText) {
      var sdEl = document.querySelector('.editable[data-columnname="shortDescription"]');
      if (sdEl) 
        { sdEl.textContent = shortText; 
          myEditorContent = shortText;
          saveContent(shortText, shortText, sdEl, null); 
        }
    }

    btn.disabled = false; btn.textContent = '✨ Auto Complete';
    status.textContent = shortText ? 'Done — short description also updated.' : 'Done.';
    setTimeout(function(){ status.textContent = ''; }, 5000);

  } catch(e) {
    btn.disabled = false; btn.textContent = '✨ Auto Complete';
    status.textContent = 'Error: ' + e.message;
    console.error('llmAutoComplete:', e);
  }
}
