(async function checkPipelineAvailable() {
  var script = <?php echo json_encode($scriptFull); ?>;
  if (!script) return;
  try {
    var res  = await fetch('lineage/lineage_api.php?action=graph_for_script&depth=direct&script=' + encodeURIComponent(script), {cache:'no-store'});
    var data = await res.json();
    _llmHasData = (data.rows && data.rows.length > 0) ||
                  (data.rel_before && data.rel_before.length > 0) ||
                  (data.rel_after  && data.rel_after.length  > 0) ||
                  (data.db_inputs  && data.db_inputs.length  > 0) ||
                  (data.called_scripts && data.called_scripts.length > 0);
  } catch(e) {}
})();

async function llmAutoCompleteWorkflow()
{
  var btn    = document.getElementById('llm-autocomplete-btn');
    var script = <?php echo json_encode($scriptFull); ?>;
    var name   = <?php echo json_encode(isset($rowAsset['name']) ? $rowAsset['name'] : ''); ?>;

    // 2. get Lineage data
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
    var calledScripts = linData.called_scripts || [];
    if (calledScripts.length) parts.push('\nScripts executed by this pipeline:\n' + calledScripts.map(function(f){return '- '+f;}).join('\n'));
    parts.push('\nWrite 2–4 paragraphs describing: (1) purpose and role of this workflow, (2) what data it reads and from where, (3) what it produces and where it goes, (4) how it fits in the broader pipeline.' + (calledScripts.length ? ' (5) the scripts it executes.' : '') + ' Be specific about file names. Professional style, English only.');
    parts=parts.join('\n');

    llmAutoComplete(parts);
}
