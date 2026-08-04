// ══════════════════════════════════════════════════════════════════════════
// Anatella Pipeline File Viewer
// Depends on globals: d3, showTip, moveTip, hideTip, basename, API
// ══════════════════════════════════════════════════════════════════════════

const ANATELLA_NODE_COLORS = {
  ReadColumnarGel:     {fill:'#dbeafe', stroke:'#1d4ed8', text:'#1e3a8a', label:'Read GEL'},
  readGel:             {fill:'#dbeafe', stroke:'#1d4ed8', text:'#1e3a8a', label:'Read GEL'},
  readCSV:             {fill:'#dbeafe', stroke:'#1d4ed8', text:'#1e3a8a', label:'Read CSV'},
  ReadOCI:             {fill:'#dbeafe', stroke:'#1d4ed8', text:'#1e3a8a', label:'Read DB'},
  writeColumnarGel:    {fill:'#dcfce7', stroke:'#15803d', text:'#14532d', label:'Write GEL'},
  writeGel:            {fill:'#dcfce7', stroke:'#15803d', text:'#14532d', label:'Write GEL'},
  writeCSV:            {fill:'#dcfce7', stroke:'#15803d', text:'#14532d', label:'Write CSV'},
  FilterRows:          {fill:'#fee2e2', stroke:'#b91c1c', text:'#7f1d1d', label:'Filter rows'},
  Calculator:          {fill:'#fef3c7', stroke:'#b45309', text:'#78350f', label:'Calculator'},
  CalculatorVectorized:{fill:'#fef3c7', stroke:'#b45309', text:'#78350f', label:'Calc (Vec)'},
  aggregate:           {fill:'#d1fae5', stroke:'#065f46', text:'#064e3b', label:'Aggregate'},
  sort:                {fill:'#ede9fe', stroke:'#6d28d9', text:'#4c1d95', label:'Sort'},
  SelectColumns:       {fill:'#f3f4f6', stroke:'#6b7280', text:'#374151', label:'Select cols'},
  Join:                {fill:'#fce7f3', stroke:'#9d174d', text:'#831843', label:'Join'},
  MultiJoin:           {fill:'#fce7f3', stroke:'#9d174d', text:'#831843', label:'MultiJoin'},
  ColumnRename:        {fill:'#f3f4f6', stroke:'#6b7280', text:'#374151', label:'Rename cols'},
  Generic:             {fill:'#f0fdf4', stroke:'#4d7c0f', text:'#365314', label:'Generic'},
  R:                   {fill:'#fdf4ff', stroke:'#7e22ce', text:'#581c87', label:'R script'},
  Append:              {fill:'#f0f9ff', stroke:'#0369a1', text:'#0c4a6e', label:'Append'},
  RowCounter:          {fill:'#f3f4f6', stroke:'#6b7280', text:'#374151', label:'Row Count'},
  RunToFinishLine:     {fill:'#fef9c3', stroke:'#ca8a04', text:'#713f12', label:'Run'},
  parallelRun:         {fill:'#ede9fe', stroke:'#6d28d9', text:'#4c1d95', label:'Sub-script'},
  NaiveDeduplicate:    {fill:'#f3f4f6', stroke:'#6b7280', text:'#374151', label:'Dedup'},
  ReplaceStrings:      {fill:'#f3f4f6', stroke:'#6b7280', text:'#374151', label:'Replace'},
  inlineTable:         {fill:'#f0fdf4', stroke:'#4d7c0f', text:'#365314', label:'Inline'},
};

// ── AI Description helpers ────────────────────────────────────────────────
// Describe the .anatella file from its pipeline operations (the nodes we already
// parse for the graph) — not from column lineage. This is both the right focus
// ("what does this pipeline do") and small, so CPU prefill stays fast.
function buildDescPrompt(scriptPath, xmlStr) {
  const nodes = (parseAnatellaXML(xmlStr || '').nodes) || [];
  function label(tag) { return (ANATELLA_NODE_COLORS[tag] && ANATELLA_NODE_COLORS[tag].label) || tag; }
  const name = (scriptPath || '').split(/[\\/]/).pop().replace(/\.anatella$/i, '');

  const readTags  = { ReadColumnarGel: 1, readGel: 1, readCSV: 1, ReadOCI: 1 };
  const writeTags = { writeColumnarGel: 1, writeGel: 1, writeCSV: 1 };
  const reads  = nodes.filter(function(n){ return readTags[n.tag];  }).map(function(n){ return n.detail; }).filter(Boolean);
  const writes = nodes.filter(function(n){ return writeTags[n.tag]; }).map(function(n){ return n.detail; }).filter(Boolean);

  // Count operations by friendly label (e.g. "3× Filter rows, 2× Aggregate").
  const counts = {};
  nodes.forEach(function(n){ const l = label(n.tag); counts[l] = (counts[l] || 0) + 1; });
  const opSummary = Object.keys(counts).map(function(k){ return counts[k] + '× ' + k; }).join(', ');

  // The steps that carry business meaning, with their parsed detail.
  const transformTags = { FilterRows: 1, aggregate: 1, Calculator: 1, CalculatorVectorized: 1, Join: 1, MultiJoin: 1, sort: 1, SelectColumns: 1 };
  const notable = [];
  nodes.forEach(function(n){
    if (n.detail && transformTags[n.tag]) notable.push(label(n.tag) + ': ' + n.detail.replace(/\n/g, '; '));
  });

  var parts = ['Pipeline: ' + name, 'Total steps: ' + nodes.length];
  if (opSummary) parts.push('Operations: ' + opSummary);
  parts.push(reads.length  ? 'Reads:\n'  + reads.slice(0, 25).map(function(f){ return '  - ' + f; }).join('\n')  : 'Reads: none');
  parts.push(writes.length ? 'Writes:\n' + writes.slice(0, 25).map(function(f){ return '  - ' + f; }).join('\n') : 'Writes: none');
  if (notable.length) parts.push('Key transformations:\n' + notable.slice(0, 20).map(function(t){ return '  - ' + t; }).join('\n'));

  return 'You are a data engineering assistant. Based on the Anatella pipeline metadata below, write a concise one-paragraph description of what this data pipeline does: what it reads, the main transformations it applies, and what it produces. Be factual and avoid speculation.\n\n' +
    parts.join('\n\n') + '\n\nDescription:';
}

// LLM config (url/model/bearer) comes from llmSettings.php, which reads it
// from the llmSettings DB table — cached after the first fetch per page load.
let _llmCfgPromise = null;
function getLlmConfig() {
  if (!_llmCfgPromise) {
    _llmCfgPromise = fetch('../llmSettings.php', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (cfg) { return (cfg && cfg.llm) || {}; });
  }
  return _llmCfgPromise;
}

async function callLocalLLM(prompt, onToken) {
  const cfg = await getLlmConfig();
  if (!cfg.url) throw new Error('LLM not configured — see LLM Admin.');
  const headers = { 'Content-Type': 'application/json' };
  if (cfg.bearer) headers['Authorization'] = 'Bearer ' + cfg.bearer;
  const res = await fetch(cfg.url, {
    method: 'POST',
    headers: headers,
    body: JSON.stringify({ model: cfg.model || 'local-model', messages: [{ role: 'user', content: prompt }], temperature: 0.3, max_tokens: cfg.max_tokens || 8000, stream: true }),
  });
  if (!res.ok) {
    let detail = '';
    try { detail = ' — ' + JSON.stringify(await res.json()); } catch (_) {}
    throw new Error('LLM ' + res.status + detail);
  }
  const reader  = res.body.getReader();
  const decoder = new TextDecoder();
  let full = '';
  let buf  = '';
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buf += decoder.decode(value, { stream: true });
    const lines = buf.split('\n');
    buf = lines.pop();
    for (const line of lines) {
      const t = line.trim();
      if (!t || t === 'data: [DONE]') continue;
      if (t.startsWith('data: ')) {
        try {
          const chunk = JSON.parse(t.slice(6));
          const token = chunk.choices[0].delta.content || '';
          if (token) { full += token; onToken(full); }
        } catch (_) {}
      }
    }
  }
  return full;
}

// Fire a tiny request to load model weights into RAM / OS page cache so the
// user's first real "Ask" doesn't pay the cold-start penalty. Runs at most once.
let _llmWarmed = false;
async function warmupLLM() {
  if (_llmWarmed) return;
  _llmWarmed = true;
  try {
    const cfg = await getLlmConfig();
    if (!cfg.url) { _llmWarmed = false; return; }
    const headers = { 'Content-Type': 'application/json' };
    if (cfg.bearer) headers['Authorization'] = 'Bearer ' + cfg.bearer;
    await fetch(cfg.url, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify({ model: cfg.model || 'local-model', messages: [{ role: 'user', content: 'hi' }], max_tokens: 1, stream: false }),
    });
  } catch (e) { _llmWarmed = false; }  // allow a retry if the server was down
}

// Per-script prefill warmup.
// The slow part of an "Ask" is the server prefilling the (script-specific)
// prompt on CPU. By sending that exact prompt ahead of time with max_tokens:1,
// llama.cpp caches its KV state, so the user's real click becomes a cache hit
// (prefill skipped). Dwell-gated: only fires if the user stays on a script,
// so we don't prefill scripts they're merely clicking past.
let _prewarmTimer  = null;
let _prewarmedPath = null;   // path whose prompt is already primed

function schedulePrewarm(path) {
  if (_prewarmTimer) { clearTimeout(_prewarmTimer); _prewarmTimer = null; }
  if (!path || path === _prewarmedPath) return;
  _prewarmTimer = setTimeout(function () {
    _prewarmTimer = null;
    prewarmDescription(path);
  }, 1500);  // dwell window
}

async function prewarmDescription(path) {
  try {
    if (path !== _anatellaRequestedPath || !_anatellaXmlCache) return;  // navigated away
    const cfg = await getLlmConfig();
    if (!cfg.url) return;
    const prompt = buildDescPrompt(path, _anatellaXmlCache);
    const headers = { 'Content-Type': 'application/json' };
    if (cfg.bearer) headers['Authorization'] = 'Bearer ' + cfg.bearer;
    const r = await fetch(cfg.url, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify({ model: cfg.model || 'local-model', messages: [{ role: 'user', content: prompt }], temperature: 0.3, max_tokens: 1, stream: false }),
    });
    if (r.ok) _prewarmedPath = path;   // mark primed only if the server accepted it
  } catch (e) { /* server down/busy — leave unprimed; the real Ask will prefill */ }
}

// ── State ─────────────────────────────────────────────────────────────────
let _anatellaCurrentPath   = null;
let _anatellaRequestedPath = null;
let _anatellaXmlCache      = null;
let _anatellaOpen        = true;
let _anatellaZoom        = null;
let _anatellaFitTx       = null;
let _anatellaMaxZoom     = null;
let _anatellaMaxFitTx    = null;

// ── XML parse ─────────────────────────────────────────────────────────────
function parseAnatellaXML(xmlStr) {
  const doc = new DOMParser().parseFromString(xmlStr, 'text/xml');
  const nodes = [], conns = [];
  const actions = doc.querySelector('ACTIONS');
  if (!actions) return { nodes, conns };
  const _ser = new XMLSerializer();

  for (const el of actions.children) {
    const tag = el.tagName;
    const idx = parseInt(el.getAttribute('idx'));
    const x   = parseFloat(el.getAttribute('x') || 0);
    const y   = parseFloat(el.getAttribute('y') || 0);
    let detail = '';

    const fileExt = /\.(c?gel_anatella|csv|gel)$/i;
    function cleanPath(p) { return (p || '').replace(/\\/g, '/').split('/').pop().replace(fileExt, ''); }

    if (tag === 'Calculator' || tag === 'CalculatorVectorized') {
      const ovs = el.querySelectorAll('OutputVar');
      detail = [...ovs].map(v => v.getAttribute('name') + ' = ' + v.textContent.trim().slice(0, 24)).slice(0, 3).join('\n');
    } else if (tag === 'FilterRows') {
      const ex = el.querySelector('Expression');
      detail = ex ? ex.textContent.trim().slice(0, 60) : '';
    } else if (tag === 'aggregate') {
      const gbs = el.querySelectorAll('GroupBy v');
      detail = [...gbs].map(v => v.getAttribute('name')).slice(0, 3).join(', ');
    } else if (tag === 'sort') {
      const fs = el.querySelectorAll('field');
      detail = [...fs].map(f => (f.getAttribute('type') === '9' || f.getAttribute('type') === 'Z' ? '↓ ' : '↑ ') + f.textContent).slice(0, 4).join(', ');
    } else if (tag === 'ReadColumnarGel' || tag === 'readGel') {
      detail = cleanPath(el.getAttribute('fileName'));
    } else if (tag === 'readCSV') {
      detail = cleanPath(el.getAttribute('fileName'));
    } else if (tag === 'writeColumnarGel' || tag === 'writeGel') {
      detail = cleanPath(el.getAttribute('file') || el.getAttribute('fileName'));
    } else if (tag === 'writeCSV') {
      detail = cleanPath(el.getAttribute('file') || el.getAttribute('fileName'));
    } else if (tag === 'ReadOCI') {
      detail = el.getAttribute('tableName') || el.getAttribute('queryName') || 'DB query';
    } else if (tag === 'Join') {
      detail = 'key: ' + (el.getAttribute('keyA') || '');
    } else if (tag === 'MultiJoin') {
      const joins = el.querySelectorAll('Join');
      detail = [...joins].map(j => j.getAttribute('mainKey') || j.getAttribute('slaveKey')).filter(Boolean).slice(0, 3).join(', ');
    } else if (tag === 'SelectColumns') {
      const cols = el.querySelectorAll('c');
      detail = [...cols].map(c => c.textContent).slice(0, 4).join(', ') + (cols.length > 4 ? '…' : '');
    } else if (tag === 'ColumnRename') {
      const pairs = el.querySelectorAll('c');
      const names = [...pairs].map(c => c.textContent).filter(Boolean);
      detail = names.slice(0, 3).join(' → ');
    } else if (tag === 'parallelRun') {
      const attrPath = el.getAttribute('anatellaGraph');
      const rdPaths  = [...el.querySelectorAll('RunData > anatellaGraph')].map(n => cleanPath(n.textContent));
      const allPaths = attrPath ? [cleanPath(attrPath)] : rdPaths;
      detail = allPaths.slice(0, 3).join(', ') + (allPaths.length > 3 ? '…' : '');
    } else if (tag === 'Generic') {
      detail = el.getAttribute('longName') || el.getAttribute('id') || '';
    } else if (tag === 'R') {
      detail = 'R script';
    }

    if (!isNaN(idx)) nodes.push({ idx, tag, x, y, detail, xml: _ser.serializeToString(el) });
  }

  const connEl = doc.querySelector('CONNECTORS');
  if (connEl) {
    for (const c of connEl.querySelectorAll('Connection')) {
      conns.push({
        src: parseInt(c.getAttribute('idxSrc')),
        dst: parseInt(c.getAttribute('idxDest')),
        pi:  parseInt(c.getAttribute('idxPinIn') || 0),
      });
    }
  }
  return { nodes, conns };
}

// ── Layout ────────────────────────────────────────────────────────────────
function layoutFromAnatellaCoords(nodes, conns) {
  const NW = 134, NH = 46, GAP_X = 24, ROW_H = NH + 16;
  if (!nodes.length) return { positions: {}, NW, NH, maxX: 200, maxY: 100 };

  const connsArr = conns || [];

  const inDeg = {}, outAdj = {}, predAdj = {};
  for (const n of nodes) { inDeg[n.idx] = 0; outAdj[n.idx] = []; predAdj[n.idx] = []; }
  for (const c of connsArr) {
    if (inDeg[c.dst] !== undefined) inDeg[c.dst]++;
    if (outAdj[c.src] !== undefined) outAdj[c.src].push(c.dst);
    if (predAdj[c.dst] !== undefined) predAdj[c.dst].push(c.src);
  }

  const level = {}, inDegCopy = {};
  for (const n of nodes) { inDegCopy[n.idx] = inDeg[n.idx]; level[n.idx] = 0; }
  const topoQueue = nodes.filter(n => inDeg[n.idx] === 0).map(n => n.idx);
  let head = 0;
  while (head < topoQueue.length) {
    const cur = topoQueue[head++];
    for (const nxt of outAdj[cur]) {
      const nl = level[cur] + 1;
      if (nl > level[nxt]) level[nxt] = nl;
      if (--inDegCopy[nxt] === 0) topoQueue.push(nxt);
    }
  }

  const posY = {};
  const sources = nodes.filter(n => inDeg[n.idx] === 0);
  sources.sort((a, b) => a.y - b.y);
  sources.forEach((n, i) => { posY[n.idx] = i * ROW_H + 16; });

  for (const idx of topoQueue) {
    if (posY[idx] !== undefined) continue;
    const preds = predAdj[idx].filter(p => posY[p] !== undefined);
    posY[idx] = preds.length
      ? preds.reduce((s, p) => s + posY[p], 0) / preds.length
      : 16;
  }

  const byLevel = {};
  for (const n of nodes) {
    const l = level[n.idx];
    if (!byLevel[l]) byLevel[l] = [];
    byLevel[l].push(n.idx);
  }
  for (const idxs of Object.values(byLevel)) {
    idxs.sort((a, b) => (posY[a] || 0) - (posY[b] || 0) || a - b);
    for (let i = 1; i < idxs.length; i++) {
      const need = (posY[idxs[i - 1]] || 0) + ROW_H;
      if ((posY[idxs[i]] || 0) < need) posY[idxs[i]] = need;
    }
  }

  const positions = {};
  for (const n of nodes) {
    positions[n.idx] = {
      x: level[n.idx] * (NW + GAP_X) + 16,
      y: Math.round(posY[n.idx] || 16),
    };
  }

  const maxX = Math.max(...nodes.map(n => positions[n.idx].x)) + NW + 16;
  const maxY = Math.max(...nodes.map(n => positions[n.idx].y)) + NH + 16;
  return { positions, NW, NH, maxX, maxY };
}

// ── Render pipeline into any SVG element ─────────────────────────────────
function renderAnatellaPipeline(xml, svgEl, propsEl) {
  const { nodes, conns } = parseAnatellaXML(xml);
  const svgD3 = d3.select(svgEl);
  svgD3.selectAll('*').remove();

  if (!nodes.length) {
    svgD3.append('text').attr('x', 12).attr('y', 30)
      .attr('font-size', 13).attr('fill', '#94A3B8')
      .text('No nodes found in pipeline XML.');
    return null;
  }

  const { positions, NW, NH, maxX, maxY } = layoutFromAnatellaCoords(nodes, conns);

  const outDeg = {}, revAdj = {};
  for (const n of nodes) { outDeg[n.idx] = 0; revAdj[n.idx] = []; }
  for (const c of conns) {
    if (outDeg[c.src] !== undefined) outDeg[c.src]++;
    if (revAdj[c.dst] !== undefined) revAdj[c.dst].push(c.src);
  }
  let finalIdxs = nodes.filter(n => n.tag === 'RunToFinishLine').map(n => n.idx);
  if (finalIdxs.length === 0)
    finalIdxs = nodes.filter(n => outDeg[n.idx] === 0).map(n => n.idx);
  const reachSink = new Set();
  const sinkQ = finalIdxs.slice();
  for (const idx of sinkQ) reachSink.add(idx);
  for (let qi = 0; qi < sinkQ.length; qi++) {
    for (const prev of revAdj[sinkQ[qi]]) {
      if (!reachSink.has(prev)) { reachSink.add(prev); sinkQ.push(prev); }
    }
  }

  const maxPinAt = {};
  for (const c of conns) {
    if (maxPinAt[c.dst] === undefined || c.pi > maxPinAt[c.dst]) maxPinAt[c.dst] = c.pi;
  }

  const mkId = 'an-arr-' + svgEl.id;
  svgD3.append('defs').html(
    `<marker id="${mkId}" viewBox="0 0 10 10" refX="8" refY="5"
       markerWidth="6" markerHeight="6" orient="auto-start-reverse">
       <path d="M2 1L8 5L2 9" fill="none" stroke="#9ca3af" stroke-width="1.5"
             stroke-linecap="round" stroke-linejoin="round"/>
     </marker>`
  );

  const root = svgD3.append('g').attr('class', 'an-root');

  for (const c of conns) {
    const sp = positions[c.src], dp = positions[c.dst];
    if (!sp || !dp) continue;
    const x1 = sp.x + NW, y1 = sp.y + NH / 2;
    const nPins = (maxPinAt[c.dst] || 0) + 1;
    const pinFrac = nPins === 1 ? 0.5 : (c.pi + 0.5) / nPins;
    const x2 = dp.x, y2 = dp.y + NH * pinFrac;
    const cx = (x1 + x2) / 2;
    const edgeLive = reachSink.has(c.src) && reachSink.has(c.dst);
    root.append('path')
      .attr('d', `M${x1} ${y1} C${cx} ${y1} ${cx} ${y2} ${x2} ${y2}`)
      .attr('fill', 'none')
      .attr('stroke', c.pi > 0 ? '#db2777' : '#9ca3af')
      .attr('stroke-width', 1.3)
      .attr('opacity', edgeLive ? 0.7 : 0.25)
      .attr('marker-end', `url(#${mkId})`)
      .attr('stroke-dasharray', c.pi > 0 ? '4 2' : null);
  }

  let _selRect = null, _selCfg = null;
  function _deselect() {
    if (_selRect) { _selRect.attr('stroke-width', 1).attr('stroke', _selCfg.stroke); _selRect = null; _selCfg = null; }
  }
  if (propsEl) {
    svgD3.on('click.props', () => { _deselect(); propsEl.classList.add('hidden'); });
    const closeBtn = propsEl.querySelector('.an-props-close');
    if (closeBtn) closeBtn.onclick = () => { _deselect(); propsEl.classList.add('hidden'); };
  }

  for (const n of nodes) {
    const p = positions[n.idx]; if (!p) continue;
    const cfg = ANATELLA_NODE_COLORS[n.tag] || { fill: '#f3f4f6', stroke: '#9ca3af', text: '#374151', label: n.tag };
    const g = root.append('g').attr('class', 'an-node')
      .attr('data-idx', n.idx)
      .style('cursor', propsEl ? 'pointer' : 'default')
      .attr('opacity', reachSink.has(n.idx) ? 1 : 0.35);

    g.append('rect')
      .attr('x', p.x + 2).attr('y', p.y + 2).attr('width', NW).attr('height', NH)
      .attr('rx', 7).attr('fill', cfg.stroke).attr('opacity', 0.13);

    const rect = g.append('rect')
      .attr('x', p.x).attr('y', p.y).attr('width', NW).attr('height', NH)
      .attr('rx', 7).attr('fill', cfg.fill).attr('stroke', cfg.stroke).attr('stroke-width', 1);

    g.append('rect')
      .attr('x', p.x + NW - 22).attr('y', p.y + 1).attr('width', 20).attr('height', 13)
      .attr('rx', 4).attr('fill', cfg.stroke).attr('opacity', 0.18);
    g.append('text')
      .attr('x', p.x + NW - 12).attr('y', p.y + 7.5)
      .attr('text-anchor', 'middle').attr('dominant-baseline', 'central')
      .attr('font-size', 9).attr('fill', cfg.text)
      .attr('font-weight', 600).attr('font-family', 'monospace')
      .text(n.idx);

    if (n.detail) {
      g.append('text')
        .attr('x', p.x + 7).attr('y', p.y + 14)
        .attr('dominant-baseline', 'central')
        .attr('font-size', 9.5).attr('font-weight', 400)
        .attr('fill', cfg.text).attr('opacity', 0.7)
        .attr('font-family', 'system-ui,sans-serif')
        .text(cfg.label);
      const dLine = n.detail.split('\n')[0];
      const dShort = dLine.length > 22 ? dLine.slice(0, 21) + '…' : dLine;
      g.append('text')
        .attr('x', p.x + 7).attr('y', p.y + 31)
        .attr('dominant-baseline', 'central')
        .attr('font-size', 10.5).attr('font-weight', 600)
        .attr('fill', cfg.text).attr('font-family', 'monospace')
        .text(dShort);
    } else {
      g.append('text')
        .attr('x', p.x + 7).attr('y', p.y + NH / 2)
        .attr('dominant-baseline', 'central')
        .attr('font-size', 12).attr('font-weight', 500)
        .attr('fill', cfg.text).attr('font-family', 'system-ui,sans-serif')
        .text(cfg.label);
    }

    const tipText = cfg.label + '  #' + n.idx + (n.detail ? '\n' + n.detail : '');
    g.on('mouseenter', ev => { if (_selRect !== rect) rect.attr('stroke-width', 2); showTip(ev, tipText); })
     .on('mousemove',  moveTip)
     .on('mouseleave', ()  => { if (_selRect !== rect) rect.attr('stroke-width', 1); hideTip(); });
    if (propsEl) {
      g.on('click.props', ev => {
        ev.stopPropagation();
        _deselect();
        _selRect = rect; _selCfg = cfg;
        rect.attr('stroke-width', 2.5).attr('stroke', '#2563EB');
        showAnatellaNodeProps(n, propsEl);
      });
    }
  }

  const W = svgEl.clientWidth  || svgEl.parentElement.clientWidth  || 800;
  const H = svgEl.clientHeight || svgEl.parentElement.clientHeight || 260;
  const sc = Math.min((W - 20) / maxX, (H - 20) / maxY, 1.4);
  const tx = (W - maxX * sc) / 2;
  const ty = (H - maxY * sc) / 2;
  const fitTx = d3.zoomIdentity.translate(tx, ty).scale(sc);

  const zoom = d3.zoom().scaleExtent([0.05, 5])
    .on('zoom', e => root.attr('transform', e.transform));
  svgD3.call(zoom).call(zoom.transform, fitTx);

  return { zoom, fitTx };
}

// ── Node properties panel ─────────────────────────────────────────────────
function showAnatellaNodeProps(node, propsEl) {
  const cfg = ANATELLA_NODE_COLORS[node.tag] || { label: node.tag };
  propsEl.querySelector('.an-props-type').textContent = cfg.label;
  propsEl.querySelector('.an-props-idx').textContent  = '#' + node.idx;

  const bodyEl = propsEl.querySelector('.an-props-body');
  const tmpDoc = new DOMParser().parseFromString('<r>' + node.xml + '</r>', 'text/xml');
  const el = tmpDoc.documentElement.firstElementChild;

  bodyEl.innerHTML = el ? buildNodePropsHTML(el, node.tag) : '<div class="an-props-empty">No data.</div>';

  const tabs   = [...bodyEl.querySelectorAll('.an-props-tab')];
  const panels = [...bodyEl.querySelectorAll('.an-props-formula')];
  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => {
      tabs.forEach((t, j) => { t.classList.toggle('active', j === i); panels[j].style.display = j === i ? '' : 'none'; });
    });
  });

  propsEl.classList.remove('hidden');
}

function buildNodePropsHTML(el, tag) {
  const e = s => s == null ? '' : String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const skip = new Set(['idx', 'x', 'y']);

  function attrRows(element, extraSkip) {
    const rows = [];
    const ex = extraSkip || new Set();
    for (const a of element.attributes) {
      if (skip.has(a.name) || ex.has(a.name) || a.value === '') continue;
      rows.push(`<tr><td>${e(a.name)}</td><td>${e(a.value)}</td></tr>`);
    }
    return rows.length ? `<table class="an-props-table"><tbody>${rows.join('')}</tbody></table>` : '';
  }
  function sec(label) { return `<div class="an-props-sec">${label}</div>`; }
  function code(text) { return `<div class="an-props-code">${e(text)}</div>`; }

  if (tag === 'Calculator' || tag === 'CalculatorVectorized') {
    const ovs = [...el.querySelectorAll('OutputVar')];
    if (!ovs.length) return '<div class="an-props-empty">No output variables defined.</div>';
    const tabs = ovs.map((ov, i) =>
      `<div class="an-props-tab${i === 0 ? ' active' : ''}">${e(ov.getAttribute('name') || 'var_' + i)}</div>`
    ).join('');
    const formulas = ovs.map((ov, i) => {
      const name = ov.getAttribute('name') || '';
      const meta = ov.getAttribute('meta') || '';
      const expr = ov.textContent.trim();
      return `<div class="an-props-formula" style="${i > 0 ? 'display:none' : ''}">
        <div class="an-props-finfo">${meta === 'U' ? '✏ Updates' : '＋ Creates'} · <strong>${e(name)}</strong></div>
        <div class="an-props-code">${e(expr)}</div></div>`;
    }).join('');
    return `<div class="an-props-tabs">${tabs}</div>${formulas}`;
  }

  if (tag === 'FilterRows') {
    const ex = el.querySelector('Expression');
    return attrRows(el) + (ex ? sec('Expression') + code(ex.textContent.trim()) : '');
  }

  if (tag === 'sort') {
    const fields = [...el.querySelectorAll('field')];
    if (!fields.length) return attrRows(el);
    const rows = fields.map(f => {
      const desc = f.getAttribute('type') === '9' || f.getAttribute('type') === 'Z';
      return `<tr><td>${desc ? '↓ DESC' : '↑ ASC'}</td><td>${e(f.textContent.trim())}</td></tr>`;
    }).join('');
    return sec('Sort Fields') + `<table class="an-props-table"><tbody>${rows}</tbody></table>`;
  }

  if (tag === 'aggregate') {
    let html = attrRows(el);
    const gbs = [...el.querySelectorAll('GroupBy v, GroupBy c')];
    if (gbs.length) {
      const rows = gbs.map(v => `<tr><td>group by</td><td>${e(v.getAttribute('name') || v.textContent)}</td></tr>`).join('');
      html += sec('Group By') + `<table class="an-props-table"><tbody>${rows}</tbody></table>`;
    }
    const aggs = [...el.querySelectorAll('Aggregation, Agg')];
    if (aggs.length) {
      const rows = aggs.map(a => `<tr><td>${e(a.getAttribute('type') || a.getAttribute('func') || '')}</td><td>${e(a.getAttribute('outputName') || a.getAttribute('name') || a.getAttribute('output') || '')}</td></tr>`).join('');
      html += sec('Aggregations') + `<table class="an-props-table"><tbody>${rows}</tbody></table>`;
    }
    return html;
  }

  if (tag === 'SelectColumns') {
    const cols = [...el.querySelectorAll('c')];
    if (!cols.length) return attrRows(el);
    const rows = cols.map((c, i) => `<tr><td>${i + 1}</td><td>${e(c.textContent)}</td></tr>`).join('');
    return sec(`Columns (${cols.length})`) + `<table class="an-props-table"><tbody>${rows}</tbody></table>`;
  }

  if (tag === 'ColumnRename') {
    const cs = [...el.querySelectorAll('c')];
    const renames = [...el.querySelectorAll('rename,Rename')];
    if (renames.length) {
      const rows = renames.map(r => `<tr><td>${e(r.getAttribute('from') || r.getAttribute('old') || '')}</td><td>→ ${e(r.getAttribute('to') || r.getAttribute('new') || '')}</td></tr>`).join('');
      return sec('Renames') + `<table class="an-props-table"><tbody>${rows}</tbody></table>`;
    }
    if (cs.length) {
      let rows = '';
      for (let i = 0; i + 1 < cs.length; i += 2)
        rows += `<tr><td>${e(cs[i].textContent)}</td><td>→ ${e(cs[i + 1].textContent)}</td></tr>`;
      if (!rows && cs.length)
        rows = cs.map(c => `<tr><td colspan="2">${e(c.textContent)}</td></tr>`).join('');
      return sec('Renames') + `<table class="an-props-table"><tbody>${rows}</tbody></table>`;
    }
    return attrRows(el);
  }

  if (tag === 'MultiJoin') {
    let html = attrRows(el);
    const joins = [...el.querySelectorAll('Join')];
    if (joins.length) {
      const rows = joins.map((j, i) => {
        const mk = j.getAttribute('mainKey') || j.getAttribute('masterKey') || '';
        const sk = j.getAttribute('slaveKey') || j.getAttribute('joinKey') || '';
        return `<tr><td>Join ${i + 1}</td><td>Main: ${e(mk)} / Slave: ${e(sk)}</td></tr>`;
      }).join('');
      html += sec('Join Keys') + `<table class="an-props-table"><tbody>${rows}</tbody></table>`;
    }
    return html;
  }

  if (tag === 'ReadOCI') {
    const q = el.querySelector('query,Query,SQL,sql');
    return attrRows(el) + (q && q.textContent.trim() ? sec('SQL Query') + code(q.textContent.trim()) : '');
  }

  if (tag === 'R') {
    const s = el.querySelector('code,script,rScript,RScript,body');
    return attrRows(el) + (s && s.textContent.trim() ? sec('R Script') + code(s.textContent.trim()) : '');
  }

  if (tag === 'ReplaceStrings') {
    let html = attrRows(el);
    const pairs = [...el.querySelectorAll('pair,replace,Replace,item')];
    if (pairs.length) {
      const rows = pairs.map(p => {
        const from = p.getAttribute('from') || p.getAttribute('search') || p.getAttribute('old') || '';
        const to   = p.getAttribute('to')   || p.getAttribute('replace') || p.getAttribute('new') || '';
        return `<tr><td>${e(from)}</td><td>→ ${e(to)}</td></tr>`;
      }).join('');
      html += sec(`Replacements (${pairs.length})`) + `<table class="an-props-table"><tbody>${rows}</tbody></table>`;
    }
    return html;
  }

  if (tag === 'inlineTable') {
    let html = attrRows(el);
    const rows = [...el.querySelectorAll('row,r,Row')];
    if (rows.length) {
      const preview = rows.slice(0, 30).map(r => `<tr><td colspan="2">${e(r.textContent.trim())}</td></tr>`).join('');
      const more = rows.length > 30 ? `<tr><td colspan="2" style="color:#94A3B8">… ${rows.length - 30} more rows</td></tr>` : '';
      html += sec(`Inline Data (${rows.length} rows)`) + `<table class="an-props-table"><tbody>${preview}${more}</tbody></table>`;
    }
    return html;
  }

  if (tag === 'parallelRun' || tag === 'callScript') {
    // Collect all scripts: either from the anatellaGraph attribute (single) or
    // from one or more <RunData><anatellaGraph> children (parallel case)
    const attrPath  = el.getAttribute('anatellaGraph');
    const runDatas  = [...el.querySelectorAll('RunData')];
    let html = attrRows(el, new Set(['anatellaGraph']));

    if (attrPath) {
      html = `<table class="an-props-table"><tbody><tr><td>script</td><td>${e(attrPath)}</td></tr></tbody></table>` + html;
    } else if (runDatas.length) {
      // One row per RunData block
      const scriptRows = runDatas.map((rd, i) => {
        const graph = (rd.querySelector('anatellaGraph') || {}).textContent || '';
        const extras = [...rd.children]
          .filter(c => c.tagName !== 'anatellaGraph')
          .map(c => `<span style="color:#94A3B8;font-size:.68rem"> · ${e(c.tagName)}: ${e(c.textContent.trim())}</span>`)
          .join('');
        return `<tr><td>#${i + 1}</td><td>${e(graph)}${extras}</td></tr>`;
      }).join('');
      html = sec(`Scripts (${runDatas.length})`) +
             `<table class="an-props-table"><tbody>${scriptRows}</tbody></table>` + html;
    }
    return html || '<div class="an-props-empty">No parameters.</div>';
  }

  return attrRows(el) || '<div class="an-props-empty">No parameters.</div>';
}

// ── Load pipeline from DB snapshot (fallback when file is not on disk) ───
async function loadAnatellaPanelFromDB(scriptPath) {
  const panel     = document.getElementById('anatella-panel');
  const nameEl    = document.getElementById('anatella-panel-name');
  const statusEl  = document.getElementById('anatella-status');
  const svgEl     = document.getElementById('anatella-svg');
  const zoomCtrls = document.getElementById('an-zoom-ctrls');

  if (typeof _anCollapsed !== 'undefined' && _anCollapsed) {
    panel.classList.remove('an-collapsed');
    _anCollapsed = false;
    document.getElementById('anatella-toggle-icon').textContent = '▼';
  }
  panel.style.display = '';
  panel.style.height  = (typeof _anatellaH !== 'undefined' ? _anatellaH : 260) + 'px';
  warmupLLM();   // panel (and AI strip) now visible — preheat the local model
  const rh = document.getElementById('an-resize-handle');
  if (rh) rh.style.display = '';
  nameEl.textContent       = basename(scriptPath) + ' — DB snapshot';
  statusEl.style.display   = '';
  statusEl.className       = 'anatella-status';
  statusEl.textContent     = 'Loading pipeline from database…';
  zoomCtrls.style.display  = 'none';
  document.getElementById('an-props').classList.add('hidden');
  d3.select(svgEl).selectAll('*').remove();

  try {
    const res  = await fetch(`${API}?action=pipeline_from_db&script=${encodeURIComponent(scriptPath)}`);
    const data = await res.json();
    if (data.error) {
      statusEl.textContent = '✗ ' + data.error;
      statusEl.className   = 'anatella-status err';
      return;
    }
    _anatellaCurrentPath   = null;   // file path unknown / unavailable
    _anatellaRequestedPath = scriptPath;
    schedulePrewarm(scriptPath);     // prime the LLM prompt cache for this script
    _anatellaXmlCache      = data.pipeline_xml;
    statusEl.style.display = 'none';
    const result = renderAnatellaPipeline(data.pipeline_xml, svgEl, document.getElementById('an-props'));
    if (result) {
      _anatellaZoom  = result.zoom;
      _anatellaFitTx = result.fitTx;
      zoomCtrls.style.display = '';
    }
    if (typeof onAnatellaLoaded === 'function') onAnatellaLoaded();
  } catch (e) {
    statusEl.textContent = '✗ ' + e.message;
    statusEl.className   = 'anatella-status err';
  }
}

// ── Load & render pipeline panel ─────────────────────────────────────────
async function loadAnatellaPanel(scriptPath) {
  const panel     = document.getElementById('anatella-panel');
  const nameEl    = document.getElementById('anatella-panel-name');
  const statusEl  = document.getElementById('anatella-status');
  const svgEl     = document.getElementById('anatella-svg');
  const zoomCtrls = document.getElementById('an-zoom-ctrls');

  // Expand + size the panel if first load or previously collapsed
  if (typeof _anCollapsed !== 'undefined' && _anCollapsed) {
    panel.classList.remove('an-collapsed');
    _anCollapsed = false;
    document.getElementById('anatella-toggle-icon').textContent = '▼';
  }
  panel.style.display  = '';
  panel.style.height   = (typeof _anatellaH !== 'undefined' ? _anatellaH : 260) + 'px';
  warmupLLM();   // panel (and AI strip) now visible — preheat the local model
  const rh = document.getElementById('an-resize-handle');
  if (rh) rh.style.display = '';
  nameEl.textContent  = basename(scriptPath);
  statusEl.style.display = '';
  statusEl.className  = 'anatella-status';
  statusEl.textContent = 'Loading…';
  zoomCtrls.style.display = 'none';
  document.getElementById('an-props').classList.add('hidden');
  d3.select(svgEl).selectAll('*').remove();

  try {
    const res  = await fetch(`${API}?action=anatella_file&script=${encodeURIComponent(scriptPath)}`);
    const data = await res.json();
    if (data.error) {
      // File not found on disk — try the DB snapshot
      statusEl.textContent = data.error + ' · trying database snapshot…';
      return loadAnatellaPanelFromDB(scriptPath);
    }
    _anatellaCurrentPath   = data.path;
    _anatellaRequestedPath = scriptPath;
    schedulePrewarm(scriptPath);     // prime the LLM prompt cache for this script
    _anatellaXmlCache      = data.xml;
    statusEl.style.display = 'none';
    const result = renderAnatellaPipeline(data.xml, svgEl, document.getElementById('an-props'));
    if (result) {
      _anatellaZoom  = result.zoom;
      _anatellaFitTx = result.fitTx;
      zoomCtrls.style.display = '';
    }
    if (typeof onAnatellaLoaded === 'function') onAnatellaLoaded();
  } catch (e) {
    statusEl.textContent = '✗ ' + e.message;
    statusEl.className   = 'anatella-status err';
  }
}

// ── Extract Calculator OutputVars from cached XML ─────────────────────────
function extractCalculatorVars() {
  if (!_anatellaXmlCache) return [];
  const { nodes } = parseAnatellaXML(_anatellaXmlCache);
  const vars = [];
  for (const n of nodes) {
    if (n.tag !== 'Calculator' && n.tag !== 'CalculatorVectorized') continue;
    const tmpDoc = new DOMParser().parseFromString('<r>' + n.xml + '</r>', 'text/xml');
    const el = tmpDoc.documentElement.firstElementChild;
    if (!el) continue;
    for (const ov of el.querySelectorAll('OutputVar')) {
      vars.push({
        ID:         n.idx,
        After:      ov.getAttribute('name') || '',
        Before:     '',
        op:         ov.getAttribute('meta') === 'U' ? 'update' : 'create',
        expression: ov.textContent.trim(),
      });
    }
  }
  return vars;
}

// ── Pan SVG to a node and flash a highlight ring ──────────────────────────
function highlightAnatellaNode(idx) {
  const svgEl = document.getElementById('anatella-svg');
  if (!svgEl) return;

  const grp = d3.select(svgEl).select(`g.an-node[data-idx="${idx}"]`);
  if (grp.empty()) return;

  const rects = grp.selectAll('rect').nodes();
  if (rects.length < 2) return;
  const mr = d3.select(rects[1]);
  const x = +mr.attr('x'), y = +mr.attr('y');
  const w = +mr.attr('width'), h = +mr.attr('height');

  // Pan the SVG to center the node (keep current zoom level)
  if (_anatellaZoom) {
    const W = svgEl.clientWidth, H = svgEl.clientHeight;
    const sc = d3.zoomTransform(svgEl).k;
    const tx = W / 2 - (x + w / 2) * sc;
    const ty = H / 2 - (y + h / 2) * sc;
    d3.select(svgEl).transition().duration(380)
      .call(_anatellaZoom.transform, d3.zoomIdentity.translate(tx, ty).scale(sc));
  }

  // Insert a fading highlight ring behind the node's children
  d3.select(svgEl).selectAll('.an-node-hl').remove();
  grp.insert('rect', ':first-child')
    .attr('class', 'an-node-hl')
    .attr('x', x - 4).attr('y', y - 4)
    .attr('width', w + 8).attr('height', h + 8)
    .attr('rx', 11).attr('fill', 'none')
    .attr('stroke', '#2563EB').attr('stroke-width', 3).attr('opacity', 1)
    .transition().delay(380).duration(1200).attr('opacity', 0).remove();
}

// ── Wire up all pipeline panel event listeners ────────────────────────────
function initAnatellaViewer() {
  // Collapse/expand toggle
  document.getElementById('anatella-toggle').addEventListener('click', e => {
    if (e.target.closest('#anatella-open-btn') || e.target.closest('#anatella-max-btn')) return;
    _anatellaOpen = !_anatellaOpen;
    document.getElementById('anatella-scroll').style.display  = _anatellaOpen ? '' : 'none';
    document.getElementById('anatella-status').style.display  = _anatellaOpen ? '' : 'none';
    document.getElementById('anatella-toggle-icon').classList.toggle('open', _anatellaOpen);
  });

  // Open in Anatella
  document.getElementById('anatella-open-btn').addEventListener('click', async () => {
    if (!_anatellaCurrentPath) return;
    const btn    = document.getElementById('anatella-open-btn');
    const status = document.getElementById('anatella-status');
    btn.disabled = true;
    btn.textContent = 'Opening…';
    status.style.display = '';
    status.className = 'anatella-status';
    status.textContent = '';
    try {
      const res  = await fetch(`${API}?action=open_script&path=${encodeURIComponent(_anatellaCurrentPath)}`);
      const data = await res.json();
      if (data.ok) {
        status.textContent = '✓ Anatella launched';
        status.className   = 'anatella-status ok';
      } else {
        status.textContent = '✗ ' + (data.error || 'Error');
        status.className   = 'anatella-status err';
      }
    } catch (e) {
      status.textContent = '✗ ' + e.message;
      status.className   = 'anatella-status err';
    }
    btn.disabled = false;
    btn.textContent = '▶ Open in Anatella';
  });

  // Vertical strip — click to expand the AI panel
  document.getElementById('ai-desc-strip').addEventListener('click', () => {
    document.getElementById('ai-desc-panel').classList.remove('collapsed');
  });

  // Generate AI description (button inside the panel header)
  document.getElementById('ai-desc-gen-btn').addEventListener('click', async () => {
    const path   = _anatellaRequestedPath;
    if (!path) return;
    // The real call prefills (and caches) this prompt itself, so cancel any
    // pending dwell-prewarm and mark it primed to avoid a redundant request.
    if (_prewarmTimer) { clearTimeout(_prewarmTimer); _prewarmTimer = null; }
    _prewarmedPath = path;
    const btn    = document.getElementById('ai-desc-gen-btn');
    const textEl = document.getElementById('ai-desc-text');
    btn.disabled    = true;
    btn.textContent = '…';
    textEl.classList.remove('ai-desc-empty');
    textEl.textContent = 'Generating…';
    try {
      const prompt = buildDescPrompt(path, _anatellaXmlCache);
      await callLocalLLM(prompt, function(text) { textEl.textContent = text; });
    } catch (e) {
      textEl.textContent = '✗ ' + e.message;
    }
    btn.disabled  = false;
    btn.innerHTML = '&#9654; Ask';
  });

  // Collapse button — returns AI panel to vertical strip
  document.getElementById('ai-desc-close').addEventListener('click', () => {
    document.getElementById('ai-desc-panel').classList.add('collapsed');
  });

  // Panel zoom controls
  document.getElementById('an-zoom-in').addEventListener('click', () => {
    if (_anatellaZoom) d3.select('#anatella-svg').transition().duration(220).call(_anatellaZoom.scaleBy, 1.35);
  });
  document.getElementById('an-zoom-out').addEventListener('click', () => {
    if (_anatellaZoom) d3.select('#anatella-svg').transition().duration(220).call(_anatellaZoom.scaleBy, 0.74);
  });
  document.getElementById('an-zoom-fit').addEventListener('click', () => {
    if (_anatellaZoom && _anatellaFitTx)
      d3.select('#anatella-svg').transition().duration(280).call(_anatellaZoom.transform, _anatellaFitTx);
  });

  // Maximize button
  document.getElementById('anatella-max-btn').addEventListener('click', e => {
    e.stopPropagation();
    openMaximize('pipeline');
  });

  // Maximize overlay zoom controls (pipeline)
  document.getElementById('max-zoom-in').addEventListener('click', () => {
    if (_anatellaMaxZoom) d3.select('#max-an-svg').transition().duration(220).call(_anatellaMaxZoom.scaleBy, 1.35);
  });
  document.getElementById('max-zoom-out').addEventListener('click', () => {
    if (_anatellaMaxZoom) d3.select('#max-an-svg').transition().duration(220).call(_anatellaMaxZoom.scaleBy, 0.74);
  });
  document.getElementById('max-zoom-fit').addEventListener('click', () => {
    if (_anatellaMaxZoom && _anatellaMaxFitTx)
      d3.select('#max-an-svg').transition().duration(280).call(_anatellaMaxZoom.transform, _anatellaMaxFitTx);
  });
}
