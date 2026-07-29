_llmHasData = true;

async function llmAutoCompleteTable()
{
  var name   = <?php echo json_encode(isset($rowAsset['name']) ? $rowAsset['name'] : ''); ?>;
  var kind   = <?php echo json_encode($rowAsset['category']<120 ? 'Data Lake file' : 'Database schema/table'); ?>;
  var schema = <?php echo json_encode(isset($rowAsset['schema_new']) ? $rowAsset['schema_new'] : ''); ?>;
  var server = <?php echo json_encode(isset($rowAsset['servername']) ? $rowAsset['servername'] : ''); ?>;
  var tags   = <?php echo json_encode(isset($rowAsset['tags_new']) ? $rowAsset['tags_new'] : ''); ?>;
  
  // Build prompt
  var parts = [];
  parts.push('You are a data catalog documentation assistant. Write a professional description in 200 words for the following table/asset.\n');
  parts.push('Table name: ' + name);
  parts.push('Type: ' + kind);
  if (schema) parts.push('Schema / path: ' + schema);
  if (server) parts.push('Server: ' + server);
  if (tags)   parts.push('Tags: ' + tags);
  if (columns.length) parts.push('\nColumns:\n' + columns.map(function(c) {
      var line = '- ' + c.name + (c.type ? ' (' + c.type + ')' : '');
      if (c.description) line += ': ' + c.description;
      return line;
    }).join('\n'));
  parts.push('\nWrite 2–3 paragraphs describing: (1) what this table represents and its purpose, (2) what kind of data it stores based on its columns, (3) how it is likely used. Be specific about column names. Professional style, English only.');
  parts = parts.join('\n');

  llmAutoComplete(parts);
}
