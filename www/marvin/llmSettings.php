<?php
// LLM server settings store for Marvin — GET returns config, POST saves it.
// Mirrors Prediktor/webSettings.php but uses Marvin's session + a JSON output
// on every code path (no HTML redirects, so the client always gets JSON).
require '_pe_apiAuth.php';

$configFile = __DIR__ . '/Avatar/llmConfig.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $filtered = array('llm' => array(
        'url'        => isset($llm['url'])    ? trim($llm['url'])   : '',
        'model'      => isset($llm['model'])  ? trim($llm['model']) : '',
        'max_tokens' => $maxTokens,
        'bearer'     => isset($llm['bearer']) ? trim($llm['bearer']) : '',
    ));
    if (isset($data['server']) && is_array($data['server'])) {
        $srv  = $data['server'];
        $port = isset($srv['port']) ? intval($srv['port']) : 8081;
        $ctx  = isset($srv['ctx'])  ? intval($srv['ctx'])  : 8192;
        if ($port < 1024 || $port > 65535) $port = 8081;
        if ($ctx  < 256   || $ctx  > 131072) $ctx = 8192;
        $filtered['server'] = array(
            'exe'     => isset($srv['exe'])  ? trim($srv['exe'])  : '',
            'gguf'    => isset($srv['gguf']) ? trim($srv['gguf']) : '',
            'port'    => $port,
            'ctx'     => $ctx,
            'console' => (isset($srv['console']) && $srv['console'] === 'hidden') ? 'hidden' : 'persistent',
        );
    }
    $json = json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($configFile, $json) === false) {
        http_response_code(500);
        echo '{"error":"Could not write config file"}';
        exit;
    }
    echo json_encode(array('ok' => true));
} else {
    $cfg = @json_decode(@file_get_contents($configFile), true);
    if (!is_array($cfg)) $cfg = array();
    echo json_encode($cfg);
}
