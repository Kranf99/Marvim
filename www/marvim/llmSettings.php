<?php
// LLM server settings store for Marvim — GET returns the config (any logged-in
// user, needed by the Ask AI / Auto Complete panels to reach the LLM), POST
// saves it (super-admin only). Both read/write the llmSettings table of
// MarvimDB.sqlite. JSON output on every code path (no HTML redirects, so the
// client always gets JSON).
require '_pe_checkSessionApi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $db = new SQLite3(__DIR__ . '/../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
    $db->busyTimeout(5000);
    $settings = $db->querySingle('SELECT * FROM llmSettings', true);
    $db->close();
    if (!is_array($settings)) $settings = array();
    echo json_encode(array(
        'llm' => array(
            'url'        => isset($settings['llmUrl'])       ? $settings['llmUrl']       : '',
            'model'      => isset($settings['llmModel'])     ? $settings['llmModel']     : '',
            'max_tokens' => isset($settings['llmMaxTokens'])  ? intval($settings['llmMaxTokens']) : 4096,
            'bearer'     => isset($settings['llmBearer'])     ? $settings['llmBearer']    : '',
        ),
        'server' => array(
            'exe'  => isset($settings['srvExe'])  ? $settings['srvExe']  : '',
            'gguf' => isset($settings['srvGguf']) ? $settings['srvGguf'] : '',
            'port' => isset($settings['srvPort']) ? intval($settings['srvPort']) : 8081,
            'ctx'  => isset($settings['srvCtx'])  ? intval($settings['srvCtx'])  : 8192,
        ),
    ));
    exit;
}

if (!$isSuperAdmin) {
    http_response_code(403);
    echo '{"error":"Forbidden"}';
    exit;
}
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data) || !isset($data['llm']) || !is_array($data['llm'])) {
    http_response_code(400);
    echo '{"error":"Invalid JSON"}';
    exit;
}
$llm = $data['llm'];
$maxTokens = isset($llm['max_tokens']) ? intval($llm['max_tokens']) : 900;
if ($maxTokens < 1) $maxTokens = 900;
$llmUrl    = isset($llm['url'])    ? trim($llm['url'])    : '';
$llmModel  = isset($llm['model'])  ? trim($llm['model'])  : '';
$llmBearer = isset($llm['bearer']) ? trim($llm['bearer']) : '';

$srv  = (isset($data['server']) && is_array($data['server'])) ? $data['server'] : array();
$port = isset($srv['port']) ? intval($srv['port']) : 8081;
$ctx  = isset($srv['ctx'])  ? intval($srv['ctx'])  : 8192;
if ($port < 1024 || $port > 65535)  $port = 8081;
if ($ctx  < 256   || $ctx  > 131072) $ctx = 8192;
$srvExe  = isset($srv['exe'])  ? trim($srv['exe'])  : '';
$srvGguf = isset($srv['gguf']) ? trim($srv['gguf']) : '';

$db = new SQLite3(__DIR__ . '/../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

$hasRow = $db->querySingle('SELECT COUNT(*) FROM llmSettings');
if ($hasRow > 0) {
    $stmt = $db->prepare('UPDATE llmSettings SET srvExe=:srvExe, srvGguf=:srvGguf, srvPort=:srvPort, srvCtx=:srvCtx, llmUrl=:llmUrl, llmModel=:llmModel, llmMaxTokens=:llmMaxTokens, llmBearer=:llmBearer');
} else {
    $stmt = $db->prepare('INSERT INTO llmSettings (srvExe, srvGguf, srvPort, srvCtx, llmUrl, llmModel, llmMaxTokens, llmBearer) VALUES (:srvExe, :srvGguf, :srvPort, :srvCtx, :llmUrl, :llmModel, :llmMaxTokens, :llmBearer)');
}
$stmt->bindValue(':srvExe', $srvExe);
$stmt->bindValue(':srvGguf', $srvGguf);
$stmt->bindValue(':srvPort', $port, SQLITE3_INTEGER);
$stmt->bindValue(':srvCtx', $ctx, SQLITE3_INTEGER);
$stmt->bindValue(':llmUrl', $llmUrl);
$stmt->bindValue(':llmModel', $llmModel);
$stmt->bindValue(':llmMaxTokens', $maxTokens, SQLITE3_INTEGER);
$stmt->bindValue(':llmBearer', $llmBearer);
$ok = $stmt->execute();
$db->close();

if ($ok === false) {
    http_response_code(500);
    echo '{"error":"Could not write to database"}';
    exit;
}
echo json_encode(array('ok' => true));
?>
