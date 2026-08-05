<?php
require '_pe_checkSessionApi.php';

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);

$exe     = isset($req['exe'])     ? $req['exe']     : '';
$gguf    = isset($req['gguf'])    ? $req['gguf']    : '';
$port    = intval(isset($req['port']) ? $req['port'] : 8081);
$ctx     = intval(isset($req['ctx'])  ? $req['ctx']  : 8192);
$console = (isset($req['console']) && $req['console'] === 'hidden') ? 'hidden' : 'persistent';

if (!$exe || !$gguf) {
    echo '{"error":"exe and gguf are required"}';
    exit;
}
if (!file_exists($exe)) {
    echo '{"error":"llama-server.exe not found: '.substr(json_encode($exe), 1, -1).'"}';
    exit;
}
if (!file_exists($gguf)) {
    echo '{"error":"GGUF file not found: '.substr(json_encode($gguf), 1, -1).'"}';
    exit;
}
if ($port < 1024 || $port > 65535) {
    echo '{"error":"Invalid port"}';
    exit;
}
if ($ctx < 256 || $ctx > 131072) $ctx = 8192;

// Build the command. --no-mmap --mlock: eagerly load the full model into RAM at
// startup and pin it there, so the first inference doesn't pay the lazy
// page-fault cold-start penalty.
$cmd = '"' . $exe . '" --model "' . $gguf . '" --port ' . $port
     . ' --ctx-size ' . $ctx . ' --no-mmap --mlock';

// Write a temp batch file to avoid nested cmd quoting issues.
$bat = tempnam(sys_get_temp_dir(), 'llama') . '.bat';
$batContent = '@echo off' . "\r\n" . $cmd . "\r\n";
if ($console === 'persistent') $batContent .= 'echo.' . "\r\n" . 'pause' . "\r\n";
file_put_contents($bat, $batContent);

if ($console === 'persistent') {
    pclose(popen('start "llama-server" "' . $bat . '"', 'r'));
} else {
    pclose(popen('start "" /B "' . $bat . '"', 'r'));
}

echo json_encode(array('ok' => true, 'port' => $port));
