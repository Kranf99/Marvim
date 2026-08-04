<?php
require '_pe_apiAuth.php';

if (!$isSuperAdmin) {
	echo '{"ok":false,"error":"Access denied — super-admin only."}';
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$url    = isset($_GET['url'])    ? trim($_GET['url'])    : '';
$model  = isset($_GET['model'])  ? trim($_GET['model'])  : 'local-model';
$bearer = isset($_GET['bearer']) ? trim($_GET['bearer']) : '';

if ($url === '') {
    echo '{"ok":false,"error":"Missing URL"}';
    exit;
}

$auth  = $bearer !== '' ? "Authorization: Bearer {$bearer}\r\n" : '';
$steps = [];

function _llm_request($method, $url, $auth, $body = null, $timeout = 6) {
    $headers = "Accept: application/json\r\n" . $auth;
    if ($body !== null) {
        $headers .= "Content-Type: application/json\r\n"
                  . "Content-Length: " . strlen($body) . "\r\n";
    }
    $opts = ['http' => [
        'method'        => $method,
        'header'        => $headers,
        'timeout'       => $timeout,
        'ignore_errors' => true,
    ]];
    if ($body !== null) $opts['http']['content'] = $body;
    $ctx     = stream_context_create($opts);
    $t0      = microtime(true);
    $resp    = @file_get_contents($url, false, $ctx);
    $ms      = (int)round((microtime(true) - $t0) * 1000);
    $rawHdrs = isset($http_response_header) ? $http_response_header : [];
    $code    = null;
    foreach ($rawHdrs as $h) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) { $code = (int)$m[1]; break; }
    }
    return ['body' => $resp, 'code' => $code, 'ms' => $ms, 'headers' => $rawHdrs];
}

// ── Normalize: derive base, models URL, and completions URL ─────────────────
// Strip any /v1/... suffix to get the bare host:port base
$urlBase        = rtrim(preg_replace('#/v1(/.*)?$#i', '', rtrim($url, '/')), '/');
$modelsUrl      = $urlBase . '/v1/models';
$completionsUrl = (stripos($url, '/chat/completions') !== false)
                ? $url
                : $urlBase . '/v1/chat/completions';

// ── Step 1: health / models endpoint ────────────────────────────────────────
$r    = _llm_request('GET', $modelsUrl, $auth);
$reachable = ($r['body'] !== false && $r['code'] === 200);

$modelsList = [];
if ($reachable) {
    $parsed = @json_decode($r['body'], true);
    if (isset($parsed['data']) && is_array($parsed['data'])) {
        // OpenAI /v1/models format
        foreach ($parsed['data'] as $m) {
            if (isset($m['id'])) $modelsList[] = $m['id'];
        }
    } elseif (isset($parsed['models']) && is_array($parsed['models'])) {
        // Ollama /api/tags format (fallback)
        foreach ($parsed['models'] as $m) {
            if (isset($m['name'])) $modelsList[] = $m['name'];
            elseif (isset($m['id'])) $modelsList[] = $m['id'];
        }
    }
}

$steps[] = [
    'label'  => 'GET ' . $modelsUrl,
    'code'   => $r['code'],
    'ms'     => $r['ms'],
    'ok'     => $reachable,
    'error'  => $r['body'] === false
                    ? 'Server unreachable (timeout or connection refused)'
                    : ($r['code'] !== 200 ? 'HTTP ' . $r['code'] : null),
    'detail' => $r['body'] !== false ? mb_substr((string)$r['body'], 0, 300) : null,
    'models' => $modelsList ?: null,
];

// ── Step 2: minimal chat completion ─────────────────────────────────────────
$payload = json_encode([
    'model'       => $model,
    'messages'    => [['role' => 'user', 'content' => 'Reply with exactly one word: OK']],
    'max_tokens'  => 8,
    'temperature' => 0,
]);

$r2    = _llm_request('POST', $completionsUrl, $auth, $payload, 15);
$parsed2   = ($r2['body'] !== false) ? @json_decode($r2['body'], true) : null;
$answer    = isset($parsed2['choices'][0]['message']['content'])
             ? trim($parsed2['choices'][0]['message']['content']) : null;
$chatOk    = ($r2['code'] === 200 && $answer !== null);

$steps[] = [
    'label'  => 'POST ' . $completionsUrl,
    'code'   => $r2['code'],
    'ms'     => $r2['ms'],
    'ok'     => $chatOk,
    'answer' => $answer,
    'error'  => $r2['body'] === false
                    ? 'Server unreachable'
                    : (!$chatOk ? ('HTTP ' . $r2['code'] . ' — unexpected response') : null),
    'detail' => (!$chatOk && $r2['body'] !== false)
                    ? mb_substr((string)$r2['body'], 0, 400) : null,
];

echo json_encode(['ok' => $chatOk, 'model' => $model, 'steps' => $steps], JSON_UNESCAPED_UNICODE);
