<?php
/**
 * path_utils.php — canonicalize Anatella file references into stable JOIN keys.
 *
 * Anatella stores file references relative to the *script's own directory*, using a
 * ":/" marker for "script-relative". Because scripts sit at different folder depths,
 * the same physical file is written with different "../" counts, so exact string
 * matching fails to link producer -> consumer. Resolving each reference against its
 * script directory collapses every spelling to one absolute key.
 *
 * Shared by extract_api.php (populate file_key at insert/backfill) and
 * lineage_api.php (join the lineage graph on file_key).
 */

/**
 * @param string $filePath   Raw file_path as captured from the Anatella XML.
 * @param string $scriptPath Absolute path of the script that referenced it.
 * @return array key: lowercased absolute path for JOINs (null if unresolvable)
 *               display: resolved path for humans (original if unresolvable)
 *               status: 'resolved' | 'absolute' | 'bare' | 'dynamic'
 */
function canonicalizeFilePath($filePath, $scriptPath) {
    $raw = trim((string)$filePath);
    if ($raw === '') return array('key' => null, 'display' => $raw, 'status' => 'dynamic');

    // Dynamic expression (variable concatenation) — cannot resolve statically.
    if ($raw[0] === '>' || strpos($raw, '"') !== false || strpos($raw, '+') !== false)
        return array('key' => null, 'display' => $raw, 'status' => 'dynamic');

    $p = str_replace('\\', '/', $raw);
    if (substr($p, 0, 2) === ':/') $p = substr($p, 2);   // strip Anatella script-relative marker

    if (preg_match('#^[A-Za-z]:/#', $p)) {               // already absolute (drive letter)
        $abs = collapseDots($p);
        return array('key' => strtolower($abs), 'display' => $abs, 'status' => 'absolute');
    }

    $scriptDir = dirname(str_replace('\\', '/', $scriptPath));   // resolve against script's own dir
    $hasDir    = (strpos($p, '/') !== false);
    $resolved  = collapseDots($scriptDir . '/' . ltrim($p, '/'));
    return array('key' => strtolower($resolved), 'display' => $resolved,
                 'status' => $hasDir ? 'resolved' : 'bare');   // 'bare' = filename only, best-effort
}

/** Collapse ../ ./ and duplicate slashes without touching the filesystem. */
function collapseDots($path) {
    $path = str_replace('\\', '/', $path); $prefix = '';
    if (preg_match('#^([A-Za-z]:)/#', $path, $m)) { $prefix = $m[1]; $path = substr($path, strlen($m[1])); }
    $out = array();
    foreach (explode('/', $path) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') { if (!empty($out)) array_pop($out); continue; }
        $out[] = $seg;
    }
    return $prefix . '/' . implode('/', $out);
}

/** Convenience: just the key (or null). */
function fileKey($filePath, $scriptPath) {
    $c = canonicalizeFilePath($filePath, $scriptPath);
    return $c['key'];
}

/**
 * Translate an incoming (possibly relative) file reference from the UI into a
 * canonical {key, display}. Prefers an existing ScriptIO row so we recover the
 * script context needed to resolve relative paths; falls back to treating the
 * value as already-canonical.
 */
function resolveFileEntry($pdo, $fileFwd) {
    $bwd = str_replace('/', '\\', $fileFwd);
    $st = $pdo->prepare(
        "SELECT file_key, script_path, file_path FROM ScriptIO
         WHERE (file_path=? OR file_path=?) AND file_key IS NOT NULL LIMIT 1"
    );
    $st->execute(array($fileFwd, $bwd));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return array(
            'key'     => $row['file_key'],
            'display' => canonicalizeFilePath($row['file_path'], $row['script_path'])['display'],
        );
    }
    // The value may already be a canonical/absolute path (no owning row found).
    $c = canonicalizeFilePath($fileFwd, '');
    return array(
        'key'     => ($c['key'] !== null) ? $c['key'] : strtolower($fileFwd),
        'display' => ($c['status'] === 'absolute') ? $c['display'] : $fileFwd,
    );
}
