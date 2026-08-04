<?php
/**
 * extract_api.php — Anatella script extraction, persisted directly in MarvimDB.
 *
 * Extraction (extractFromAnatellaFile) parses one .anatella XML and returns
 * inputs/outputs/called scripts/variables. save_script persists the result:
 *   Assets        — script + every IO endpoint (identity: idserver, schema=dir, name=file)
 *   workflowIO    — isInput 0=output, 1=input, 2=anatellaRun (relationship only)
 *   LineageIO     — per-node detail for a workflowIO row; source='extract'
 *   Columns       — one row per (script asset, variable name)
 *   ColumnTransformations — per-node op history (create/update/rename/renameDynamic)
 *   WorkflowMeta  — stats + pipelineXmlSrvPath (server-side .anatella copy)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set('Europe/Brussels');
session_start();
require_once __DIR__ . '/lineage_store.php';

try {
    $pdo = lin_marvimPdo();
    lin_ensureSchema($pdo);
    if (isset($_SESSION['id']) && (int)$_SESSION['id'] > 0) {
        lin_setDefaultOwner((int)$_SESSION['id']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try { switch ($action) {

    // ── List all extracted scripts (full paths) ──────────────────────────
    case 'list_scripts':
        $rows = $pdo->query(
            "SELECT a.schema || '/' || a.name AS p FROM Assets a
             JOIN WorkflowMeta w ON w.idAsset = a.id
             WHERE a.isCurrentValue=1 ORDER BY p"
        )->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($rows);
        break;

    // ── Extract info from one file (no DB write) ─────────────────────────
    case 'extract_script':
        $path = isset($_GET['path']) ? $_GET['path'] : '';
        if ($path === '') { http_response_code(400); echo json_encode(array('error' => 'Missing path')); exit; }
        echo json_encode(extractFromAnatellaFile($path));
        break;

    // ── Save extraction result for one file (POST JSON body) ─────────────
    case 'save_script':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['path'])) {
            http_response_code(400);
            echo json_encode(array('error' => 'Invalid payload'));
            exit;
        }
        $idServer = isset($data['idServer']) ? (int)$data['idServer'] : 0;
        if ($idServer <= 0) {
            http_response_code(400);
            echo json_encode(array('error' => 'idServer is required (select a Marvim server before extraction)'));
            exit;
        }
        if (isset($data['idDepartment']) && (int)$data['idDepartment'] > 0) {
            lin_setDefaultDepartment((int)$data['idDepartment']);
        }
        $assetId = saveScriptData($pdo, $data, $idServer);
        echo json_encode(array('ok' => true, 'path' => $data['path'], 'idAsset' => $assetId));
        break;

    // ── Current lineage-schema summary ────────────────────────────────────
    case 'schema_info':
        $info = array();
        foreach (array('Assets', 'workflowIO', 'LineageIO', 'Columns', 'ColumnTransformations', 'WorkflowMeta', 'DatamartProjects') as $t) {
            $cols = $pdo->query("PRAGMA table_info(\"$t\")")->fetchAll(PDO::FETCH_ASSOC);
            $cnt  = $pdo->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
            $names = array();
            foreach ($cols as $c) $names[] = $c['name'];
            $info[] = array('name' => $t, 'columns' => $names, 'rows' => (int)$cnt);
        }
        echo json_encode($info);
        break;

    // ── Return raw XML for a file (for pipeline visualizer) ──────────────
    case 'get_xml':
        $path = isset($_GET['path']) ? $_GET['path'] : '';
        if ($path === '') { http_response_code(400); echo json_encode(array('error' => 'Missing path')); exit; }
        $fpath = str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!file_exists($fpath)) {
            // fall back to the server-side copy
            $srv = srvCopyForPath($pdo, $path);
            if ($srv !== null && file_exists($srv)) $fpath = $srv;
        }
        if (!file_exists($fpath)) {
            http_response_code(404);
            echo json_encode(array('error' => 'File not found: ' . $fpath));
            exit;
        }
        $xml = file_get_contents($fpath);
        echo json_encode(array('xml' => $xml, 'path' => str_replace('\\', '/', $fpath)));
        break;

    // ── Open file in Anatella ─────────────────────────────────────────────
    case 'open_script':
        $path = isset($_GET['path']) ? $_GET['path'] : '';
        if ($path === '') { http_response_code(400); echo json_encode(array('error' => 'Missing path')); exit; }
        $ANATELLA_EXE = 'C:\\soft\\TIMi\\bin\\Anatella.exe';
        $fpath = str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!file_exists($fpath)) { echo json_encode(array('error' => 'File not found: ' . $fpath)); exit; }
        if (!file_exists($ANATELLA_EXE)) { echo json_encode(array('error' => 'Anatella.exe not found')); exit; }
        $cmd = 'START "" /B "' . $ANATELLA_EXE . '" "' . $fpath . '"';
        pclose(popen($cmd, 'r'));
        echo json_encode(array('ok' => true, 'path' => $fpath));
        break;

    // ── Extraction stats ───────────────────────────────────────────────────
    case 'extract_stats':
        $done   = (int)$pdo->query('SELECT COUNT(*) FROM WorkflowMeta')->fetchColumn();
        $errors = (int)$pdo->query('SELECT COUNT(*) FROM WorkflowMeta WHERE hasError=1')->fetchColumn();
        echo json_encode(array('total' => $done, 'done' => $done, 'errors' => $errors));
        break;

    // ── Wipe everything extractor-owned (assets are kept) ─────────────────
    case 'reset_extracted':
        $pdo->exec('BEGIN');
        $pdo->exec("DELETE FROM LineageIO WHERE source='extract'");
        // workflowIO rows left with zero LineageIO rows are extractor-only
        // relationships (manual rows never had one) — drop them too.
        $pdo->exec('DELETE FROM workflowIO WHERE id NOT IN (SELECT DISTINCT idWorkflowIO FROM LineageIO)');
        $pdo->exec('DELETE FROM ColumnTransformations');
        $pdo->exec('DELETE FROM Columns WHERE idasset IN (SELECT id FROM Assets WHERE category>=200)');
        $pdo->exec('DELETE FROM WorkflowMeta');
        $pdo->exec('COMMIT');
        echo json_encode(array('ok' => true, 'note' => 'extract-owned rows deleted; Assets kept'));
        break;

    // ── Recursively scan a directory for .anatella files ─────────────────
    case 'scan_dir':
        $root = isset($_GET['root']) ? $_GET['root'] : '';
        if ($root === '') { http_response_code(400); echo json_encode(array('error' => 'Missing root')); exit; }
        $root = str_replace('/', DIRECTORY_SEPARATOR, $root);
        if (!is_dir($root)) {
            http_response_code(404);
            echo json_encode(array('error' => 'Directory not found: ' . $root));
            exit;
        }
        $files = array();
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $f) {
            if (strtolower($f->getExtension()) === 'anatella') {
                $files[] = str_replace('\\', '/', $f->getPathname());
            }
        }
        sort($files);
        echo json_encode($files);
        break;

    // ── Datamart projects ──────────────────────────────────────────────────
    case 'save_datamart':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) { http_response_code(400); echo json_encode(array('error' => 'Invalid JSON')); exit; }
        $company  = isset($data['company'])   ? trim($data['company'])   : '';
        $client   = isset($data['client'])    ? trim($data['client'])    : '';
        $project  = isset($data['project'])   ? trim($data['project'])   : '';
        $datamart = isset($data['datamart'])  ? trim($data['datamart'])  : '';
        $rootDir  = isset($data['root_dir'])  ? trim($data['root_dir'])  : '';
        $repoName = isset($data['repo_name']) ? trim($data['repo_name']) : '';
        if ($company === '' || $project === '' || $datamart === '' || $rootDir === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'company, project, datamart and root_dir are required'));
            exit;
        }
        $sel = $pdo->prepare('SELECT id FROM DatamartProjects WHERE company=? AND client=? AND project=? AND datamart=? LIMIT 1');
        $sel->execute(array($company, $client, $project, $datamart));
        $id = $sel->fetchColumn();
        if ($id === false) {
            $pdo->prepare('INSERT INTO DatamartProjects (company, client, project, datamart, root_dir, repo_name, created_at)
                           VALUES (?,?,?,?,?,?,?)')
                ->execute(array($company, $client, $project, $datamart, $rootDir, $repoName, date('Y-m-d H:i:s')));
            $id = $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE DatamartProjects SET root_dir=?, repo_name=? WHERE id=?')
                ->execute(array($rootDir, $repoName, $id));
        }
        echo json_encode(array('ok' => true, 'id' => (int)$id));
        break;

    case 'list_datamarts':
        $stmt = $pdo->query(
            'SELECT id, company, client, project, datamart, root_dir, repo_name, created_at
             FROM DatamartProjects ORDER BY company, client, project, datamart'
        );
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ── List Marvim servers (extraction target selector) ──────────────────
    case 'get_marvim_servers':
        $rows = $pdo->query(
            "SELECT id, name, serverType FROM servers
             WHERE isCurrentValue=1 AND name IS NOT NULL AND name != '' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        break;

    // ── List departments the logged-in user has rights on (extraction target selector) ──
    case 'get_marvim_departments':
        $myid = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
        if ($myid > 0) {
            $stmt = $pdo->prepare(
                'SELECT d.id, d.name FROM departments d
                 INNER JOIN userDepartmentRights ud ON ud.idDepartment = d.id
                 WHERE ud.idUser = ?
                 ORDER BY d.sortorder, d.name'
            );
            $stmt->execute(array($myid));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = array();
        }
        // No session / no rights row anywhere yet: fall back to the full list
        // rather than leaving the picker empty and blocking extraction.
        if (empty($rows)) {
            $rows = $pdo->query('SELECT id, name FROM departments ORDER BY sortorder, name')
                        ->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($rows);
        break;

    default:
        http_response_code(400);
        echo json_encode(array('error' => 'Unknown action: ' . $action));
} } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
}

// ─── Server-copy fallback lookup ──────────────────────────────────────────────

function srvCopyForPath($pdo, $path) {
    $fwd = collapseDots(str_replace('\\', '/', $path));
    list($dir, $file) = lin_splitPath($fwd);
    $stmt = $pdo->prepare(
        'SELECT w.pipelineXmlSrvPath FROM Assets a
         JOIN WorkflowMeta w ON w.idAsset=a.id
         WHERE a.schema=? COLLATE NOCASE AND a.name=? COLLATE NOCASE AND a.isCurrentValue=1
         LIMIT 1'
    );
    $stmt->execute(array($dir, $file));
    $rel = $stmt->fetchColumn();
    if (!$rel) {
        $stmt = $pdo->prepare(
            'SELECT w.pipelineXmlSrvPath FROM Assets a
             JOIN WorkflowMeta w ON w.idAsset=a.id
             WHERE a.name=? COLLATE NOCASE AND a.isCurrentValue=1 LIMIT 1'
        );
        $stmt->execute(array($file));
        $rel = $stmt->fetchColumn();
    }
    return $rel ? lin_srvPathToDisk($rel) : null;
}

// ─── Core extraction ──────────────────────────────────────────────────────────

function extractFromAnatellaFile($rawPath) {
    $path = str_replace('/', DIRECTORY_SEPARATOR, $rawPath);
    $norm = str_replace('\\', '/', $rawPath);

    $empty = array('rtfl_count'=>0,'total_nodes'=>0,'connected_nodes'=>0,
                   'inputs'=>array(),'outputs'=>array(),'called_scripts'=>array(),'variables'=>array());

    if (!file_exists($path)) {
        return array_merge(array('error' => 'File not found', 'path' => $norm), $empty);
    }

    $xmlStr = file_get_contents($path);
    if ($xmlStr === false) {
        return array_merge(array('error' => 'Cannot read file', 'path' => $norm), $empty);
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $ok = $doc->loadXML($xmlStr);
    libxml_clear_errors();

    if (!$ok) {
        return array_merge(array('error' => 'XML parse failed', 'path' => $norm), $empty);
    }

    // ── Build node index ──────────────────────────────────────────────────
    $nodes = array();
    $actionsEl = $doc->getElementsByTagName('ACTIONS')->item(0);
    if (!$actionsEl) {
        return array_merge(array('error' => 'No ACTIONS element', 'path' => $norm), $empty);
    }
    foreach ($actionsEl->childNodes as $el) {
        if ($el->nodeType !== XML_ELEMENT_NODE) continue;
        $idx = (int)$el->getAttribute('idx');
        $nodes[$idx] = $el;
    }

    // ── Adjacency (reverse for BFS; per-pin feeders for ColumnRename) ─────
    $revAdj  = array();
    $fwdAdj  = array();
    $pinFeed = array();   // dest idx => array(pin => src idx), only pins >= 1
    $connEl = $doc->getElementsByTagName('CONNECTORS')->item(0);
    if ($connEl) {
        foreach ($connEl->getElementsByTagName('Connection') as $c) {
            $src = (int)$c->getAttribute('idxSrc');
            $dst = (int)$c->getAttribute('idxDest');
            $pin = $c->hasAttribute('idxPinIn') ? (int)$c->getAttribute('idxPinIn') : 0;
            if (!isset($revAdj[$dst])) $revAdj[$dst] = array();
            $revAdj[$dst][] = $src;
            if (!isset($fwdAdj[$src])) $fwdAdj[$src] = array();
            $fwdAdj[$src][] = $dst;
            if ($pin >= 1) {
                if (!isset($pinFeed[$dst])) $pinFeed[$dst] = array();
                $pinFeed[$dst][$pin] = $src;
            }
        }
    }

    // ── Find RunToFinishLine nodes ────────────────────────────────────────
    $rtflIdxs = array();
    foreach ($nodes as $idx => $el) {
        if ($el->tagName === 'RunToFinishLine') $rtflIdxs[] = $idx;
    }

    // ── Backward BFS → all nodes that feed into a RunToFinishLine ────────
    $connected = array();
    $queue = $rtflIdxs;
    foreach ($rtflIdxs as $i) $connected[$i] = true;
    while (!empty($queue)) {
        $cur = array_shift($queue);
        foreach ((isset($revAdj[$cur]) ? $revAdj[$cur] : array()) as $pred) {
            if (!isset($connected[$pred])) {
                $connected[$pred] = true;
                $queue[] = $pred;
            }
        }
    }

    // ── Extract information from connected nodes only ─────────────────────
    $readTags  = array('ReadColumnarGel','readGel','readCSV','readParquetFile',
                       'readExcel','readJSON','readHTTP','readSFTP');
    $writeTags = array('writeColumnarGel','writeGel','writeCSV','writeParquetFile',
                       'writeExcel','writeJSON','writeSFTP');

    $inputs        = array();
    $outputs       = array();
    $calledScripts = array();
    $variables     = array();

    foreach ($nodes as $idx => $el) {
        if (!isset($connected[$idx])) continue;
        $tag = $el->tagName;

        // ── File inputs ──────────────────────────────────────────────────
        if (in_array($tag, $readTags)) {
            $fn = $el->getAttribute('fileName');
            if ($fn === '') $fn = $el->getAttribute('file');
            if ($fn !== '') $inputs[] = array('idx'=>$idx,'type'=>$tag,'file'=>$fn,'is_query'=>false);

        } elseif ($tag === 'ReadOCI' || $tag === 'readOCI') {
            $table = $el->getAttribute('table');
            $conn  = $el->getAttribute('odbc') ? $el->getAttribute('odbc') : $el->getAttribute('connection');
            $qEl   = $el->getElementsByTagName('query')->item(0);
            $qText = $qEl ? trim($qEl->textContent) : '';
            $label = $conn ? $conn . '.' . $table : $table;
            if ($label === '') $label = $qText;
            if ($label !== '') $inputs[] = array('idx'=>$idx,'type'=>'ReadOCI','file'=>$label,'is_query'=>true);
        }

        // ── File outputs ─────────────────────────────────────────────────
        if (in_array($tag, $writeTags)) {
            $fn = $el->getAttribute('file');
            if ($fn === '') $fn = $el->getAttribute('fileName');
            if ($fn !== '') $outputs[] = array('idx'=>$idx,'type'=>$tag,'file'=>$fn);

        } elseif ($tag === 'WriteToDB' || $tag === 'writeOCI') {
            $tbl  = $el->getAttribute('table') ? $el->getAttribute('table') : $el->getAttribute('tableName');
            $conn = $el->getAttribute('odbc')  ? $el->getAttribute('odbc')  : $el->getAttribute('connection');
            $label = $conn ? $conn . '.' . $tbl : $tbl;
            if ($label !== '') $outputs[] = array('idx'=>$idx,'type'=>$tag,'file'=>$label,'is_query'=>true);
        }

        // ── Called sub-scripts ───────────────────────────────────────────
        if ($tag === 'parallelRun' || $tag === 'callScript') {
            foreach ($el->getElementsByTagName('anatellaGraph') as $g) {
                $s = trim($g->textContent);
                if ($s !== '') $calledScripts[] = array('idx'=>$idx,'type'=>$tag,'script'=>$s);
            }
        }

        // ── Variable creations / updates (Calculator) ────────────────────
        if ($tag === 'Calculator' || $tag === 'CalculatorVectorized') {
            foreach ($el->getElementsByTagName('OutputVar') as $ov) {
                $name = $ov->getAttribute('name');
                $meta = $ov->getAttribute('meta');   // 'U'=update existing, ''=new
                $expr = trim($ov->textContent);
                if ($name !== '') {
                    $variables[] = array(
                        'idx'        => $idx,
                        'type'       => 'Calculator',
                        'op'         => ($meta === 'U') ? 'update' : 'create',
                        'before'     => null,
                        'after'      => $name,
                        'expression' => $expr
                    );
                }
            }
        }

        // ── Column renames ───────────────────────────────────────────────
        if ($tag === 'ColumnRename') {
            $renames = extractColumnRename($el, $idx, $nodes, $pinFeed);
            foreach ($renames as $r) $variables[] = $r;
        }
    }

    // Serialize ACTIONS + CONNECTORS for offline pipeline reconstruction
    $pipelineXml = '<pipeline>';
    if ($actionsEl) $pipelineXml .= $doc->saveXML($actionsEl);
    $connEl2 = $doc->getElementsByTagName('CONNECTORS')->item(0);
    if ($connEl2) $pipelineXml .= $doc->saveXML($connEl2);
    $pipelineXml .= '</pipeline>';

    // var_targets: still computed for the extract-UI preview card (not persisted)
    $varTargets  = array();
    $varNodeIdxs = array();
    foreach ($variables as $v) $varNodeIdxs[$v['idx']] = true;
    foreach ($varNodeIdxs as $startIdx => $unused) {
        $visited = array($startIdx => true);
        $queue   = array($startIdx);
        $targets = array();
        while (!empty($queue)) {
            $cur = array_shift($queue);
            foreach ((isset($fwdAdj[$cur]) ? $fwdAdj[$cur] : array()) as $nxt) {
                if (isset($visited[$nxt])) continue;
                $visited[$nxt] = true;
                if (!isset($nodes[$nxt])) continue;
                $nxtTag = $nodes[$nxt]->tagName;
                if (in_array($nxtTag, $writeTags)) {
                    $fn = $nodes[$nxt]->getAttribute('file');
                    if ($fn === '') $fn = $nodes[$nxt]->getAttribute('fileName');
                    if ($fn !== '') $targets[$nxt] = array('tag'=>$nxtTag, 'file'=>$fn, 'idx'=>$nxt);
                } elseif ($nxtTag === 'WriteToDB' || $nxtTag === 'writeOCI') {
                    $tbl  = $nodes[$nxt]->getAttribute('table') ? $nodes[$nxt]->getAttribute('table') : $nodes[$nxt]->getAttribute('tableName');
                    $conn = $nodes[$nxt]->getAttribute('odbc')  ? $nodes[$nxt]->getAttribute('odbc')  : $nodes[$nxt]->getAttribute('connection');
                    $lbl  = $conn ? $conn . '.' . $tbl : $tbl;
                    if ($lbl !== '') $targets[$nxt] = array('tag'=>$nxtTag, 'file'=>$lbl, 'idx'=>$nxt);
                }
                $queue[] = $nxt;
            }
        }
        foreach ($variables as $v) {
            if ($v['idx'] !== $startIdx) continue;
            foreach ($targets as $t) {
                $varTargets[] = array(
                    'node_idx'    => $startIdx,
                    'var_after'   => $v['after'],
                    'target_file' => $t['file'],
                    'target_tag'  => $t['tag'],
                    'target_idx'  => $t['idx'],
                );
            }
        }
    }

    return array(
        'path'            => $norm,
        'ok'              => true,
        'rtfl_count'      => count($rtflIdxs),
        'total_nodes'     => count($nodes),
        'connected_nodes' => count($connected),
        'inputs'          => $inputs,
        'outputs'         => $outputs,
        'called_scripts'  => $calledScripts,
        'variables'       => $variables,
        'var_targets'     => $varTargets,
        'pipeline_xml'    => $pipelineXml,
    );
}

/**
 * ColumnRename handling — both modes:
 *   mode 0: before/after pairs inline in the node XML.
 *   mode 1: mapping table (columns Before/After) on the second input pin.
 *           inlineTable feeder → static pairs; anything else → 'renameDynamic'
 *           marker (mapping computed at runtime, not statically known).
 */
function extractColumnRename($el, $idx, $nodes, $pinFeed) {
    $out = array();

    // Inline before/after lists (mode 0)
    $befEl = $aftEl = null;
    foreach ($el->childNodes as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) continue;
        if ($child->tagName === 'before') $befEl = $child;
        if ($child->tagName === 'after')  $aftEl = $child;
    }
    $bCols = array(); $aCols = array();
    if ($befEl && $aftEl) {
        foreach ($befEl->getElementsByTagName('c') as $c) $bCols[] = $c->textContent;
        foreach ($aftEl->getElementsByTagName('c') as $c) $aCols[] = $c->textContent;
    }
    $cnt = max(count($bCols), count($aCols));
    for ($i = 0; $i < $cnt; $i++) {
        $b = isset($bCols[$i]) ? $bCols[$i] : '';
        $a = isset($aCols[$i]) ? $aCols[$i] : '';
        if ($b !== $a && ($b !== '' || $a !== '')) {
            $out[] = array(
                'idx' => $idx, 'type' => 'ColumnRename', 'op' => 'rename',
                'before' => $b !== '' ? $b : null,
                'after'  => $a !== '' ? $a : $b,
                'expression' => null
            );
        }
    }
    if (!empty($out)) return $out;

    // Mode 1: mapping table on pin >= 1
    $feederIdx = null;
    if (isset($pinFeed[$idx])) {
        foreach ($pinFeed[$idx] as $pin => $src) { $feederIdx = $src; break; }
    }
    if ($feederIdx === null || !isset($nodes[$feederIdx])) return $out;
    $feeder = $nodes[$feederIdx];
    $ftag   = $feeder->tagName;

    if ($ftag === 'inlineTable') {
        // constant mapping table: resolve statically
        $colNames = array();
        $cn = $feeder->getElementsByTagName('ColumnNames')->item(0);
        if ($cn) foreach ($cn->getElementsByTagName('c') as $c) $colNames[] = trim($c->textContent);
        $bi = 0; $ai = 1;
        foreach ($colNames as $i2 => $n2) {
            if (preg_match('/before/i', $n2)) $bi = $i2;
            if (preg_match('/after/i',  $n2)) $ai = $i2;
        }
        $rowsEl = $feeder->getElementsByTagName('Rows')->item(0);
        if ($rowsEl) {
            foreach ($rowsEl->getElementsByTagName('r') as $r) {
                $cells = array();
                foreach ($r->getElementsByTagName('c') as $c) $cells[] = $c->textContent;
                $b = isset($cells[$bi]) ? trim($cells[$bi]) : '';
                $a = isset($cells[$ai]) ? trim($cells[$ai]) : '';
                if ($b === '' && $a === '') continue;
                if ($b === $a) continue;
                $out[] = array(
                    'idx' => $idx, 'type' => 'ColumnRename', 'op' => 'rename',
                    'before' => $b !== '' ? $b : null,
                    'after'  => $a !== '' ? $a : $b,
                    'expression' => null
                );
            }
        }
        return $out;
    }

    // Dynamic mapping — record the fact + the feeder's formula when available
    $expr = null;
    if ($ftag === 'Calculator' || $ftag === 'CalculatorVectorized') {
        $exprs = array();
        foreach ($feeder->getElementsByTagName('OutputVar') as $ov) {
            $e = trim($ov->textContent);
            if ($e !== '') $exprs[] = $ov->getAttribute('name') . ' = ' . $e;
        }
        if (!empty($exprs)) $expr = implode(' ; ', $exprs);
    }
    $out[] = array(
        'idx' => $idx, 'type' => 'ColumnRename:' . $ftag, 'op' => 'renameDynamic',
        'before' => null, 'after' => '*dynamicRename*',
        'expression' => $expr
    );
    return $out;
}

// ─── DB persistence ───────────────────────────────────────────────────────────

/** Persist one extraction result into MarvimDB. Returns the script asset id. */
function saveScriptData($pdo, $data, $idServer) {
    $scriptFwd = collapseDots(str_replace('\\', '/', $data['path']));
    $hasError  = (isset($data['error']) && $data['error']) ? 1 : 0;

    $pdo->exec('BEGIN');
    try {
        $assetId = lin_upsertScriptAsset($pdo, $idServer, $scriptFwd);

        if (!$hasError) {
            // IO rows
            $ioList = array();
            foreach ((isset($data['inputs']) ? $data['inputs'] : array()) as $r) {
                $isQ = !empty($r['is_query']);
                list($epId, $isDb, ) = lin_upsertEndpointAsset($pdo, $idServer, $r['file'], $scriptFwd, $isQ ? 1 : 0);
                $ioList[] = array('idIO' => $epId, 'isInput' => 1, 'nodeID' => $r['idx'],
                                  'actionTag' => $r['type'], 'isDbQuery' => $isDb);
            }
            foreach ((isset($data['outputs']) ? $data['outputs'] : array()) as $r) {
                $isQ = !empty($r['is_query']);
                list($epId, $isDb, ) = lin_upsertEndpointAsset($pdo, $idServer, $r['file'], $scriptFwd, $isQ ? 1 : 0);
                $ioList[] = array('idIO' => $epId, 'isInput' => 0, 'nodeID' => $r['idx'],
                                  'actionTag' => $r['type'], 'isDbQuery' => $isDb);
            }
            foreach ((isset($data['called_scripts']) ? $data['called_scripts'] : array()) as $r) {
                $calleeId = lin_resolveScriptAsset($pdo, $idServer, $r['script'], $scriptFwd);
                $ioList[] = array('idIO' => $calleeId, 'isInput' => 2, 'nodeID' => $r['idx'],
                                  'actionTag' => $r['type'], 'isDbQuery' => 0);
            }
            lin_replaceExtractedIO($pdo, $assetId, $ioList);

            // Variables
            lin_replaceExtractedVars($pdo, $assetId,
                isset($data['variables']) ? $data['variables'] : array());
        }

        // Server-side copy + meta
        list($rel, $snap) = lin_storeAnatellaCopy($idServer, $assetId, $data['path'],
            isset($data['pipeline_xml']) ? $data['pipeline_xml'] : null);

        lin_upsertWorkflowMeta($pdo, $assetId, array(
            'rtflCount'          => isset($data['rtfl_count'])      ? $data['rtfl_count']      : 0,
            'totalNodes'         => isset($data['total_nodes'])     ? $data['total_nodes']     : 0,
            'connectedNodes'     => isset($data['connected_nodes']) ? $data['connected_nodes'] : 0,
            'hasError'           => $hasError,
            'errorMsg'           => $hasError ? $data['error'] : null,
            'serverName'         => gethostname(),
            'pipelineXmlSrvPath' => $rel,
            'isSnapshotOnly'     => $snap,
        ));

        $pdo->exec('COMMIT');
    } catch (Exception $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
    return $assetId;
}
