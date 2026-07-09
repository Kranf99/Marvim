<?php
/**
 * lineage_api.php — lineage graph queries over MarvinDB (Assets / workflowIO /
 * LineageIO / Columns / ColumnTransformations / WorkflowMeta / DatamartProjects).
 *
 * The UI contract is unchanged: scripts and files are addressed by path
 * strings and edges are returned as display-path strings; internally every
 * join runs on Assets ids via workflowIO (isInput 0=output, 1=input,
 * 2=anatellaRun). workflowIO is relationship-only; LineageIO carries the
 * extractor's per-node detail (nodeID, actionTag, isDbQuery, source,
 * extractedAt) and can have several rows per workflowIO relationship.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/lineage_store.php';

try {
    $pdo = lin_marvinPdo();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => 'Cannot open database: ' . $e->getMessage()));
    exit;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Human/graph display path for an asset row (schema, name, category, subCategory). */
function lapi_display($row) {
    $schema = isset($row['schema']) ? (string)$row['schema'] : '';
    $name   = isset($row['name'])   ? (string)$row['name']   : '';
    $cat    = isset($row['category']) ? (int)$row['category'] : 0;
    $sub    = isset($row['subCategory']) ? (int)$row['subCategory'] : 0;
    if ($cat >= 120 && $cat <= 199) {                    // DB table / query
        return ($schema === '' || $schema === 'DBQUERY') ? $name : $schema . '.' . $name;
    }
    if ($sub === LIN_SUBCAT_DYNAMIC) return $schema;     // dynamic expr: schema holds it whole
    if ($schema === '') return $name;
    return $schema . '/' . $name;
}

/** Fetch one asset row (id → display fields). */
function lapi_asset($pdo, $id) {
    static $cache = array();
    if (isset($cache[$id])) return $cache[$id];
    $stmt = $pdo->prepare('SELECT id, category, subCategory, "schema", name FROM Assets WHERE id=? LIMIT 1');
    $stmt->execute(array($id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $cache[$id] = $row ? $row : null;
    return $cache[$id];
}

function lapi_displayOfId($pdo, $id) {
    $row = lapi_asset($pdo, $id);
    return $row ? lapi_display($row) : ('#' . $id);
}

/** Resolve a path string to a script asset (category >= 200). Returns [id, display] or [null, path]. */
function lapi_resolveScript($pdo, $script) {
    $fwd = collapseDots(str_replace('\\', '/', $script));
    list($dir, $file) = lin_splitPath($fwd);
    $stmt = $pdo->prepare('SELECT id, category, subCategory, "schema", name FROM Assets
                           WHERE schema=? COLLATE NOCASE AND name=? COLLATE NOCASE
                             AND category>=200 AND isCurrentValue=1 LIMIT 1');
    $stmt->execute(array($dir, $file));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $stmt = $pdo->prepare('SELECT id, category, subCategory, "schema", name FROM Assets
                               WHERE name=? COLLATE NOCASE AND category>=200 AND isCurrentValue=1 LIMIT 1');
        $stmt->execute(array($file));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) return array(null, $fwd);
    return array((int)$row['id'], lapi_display($row));
}

/** Resolve a path string to any asset. Returns [id, display] or [null, path]. */
function lapi_resolveFile($pdo, $file) {
    $fwd = collapseDots(str_replace('\\', '/', $file));
    list($dir, $nm) = lin_splitPath($fwd);
    $stmt = $pdo->prepare('SELECT id, category, subCategory, "schema", name FROM Assets
                           WHERE schema=? COLLATE NOCASE AND name=? COLLATE NOCASE AND isCurrentValue=1 LIMIT 1');
    $stmt->execute(array($dir, $nm));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $stmt = $pdo->prepare('SELECT id, category, subCategory, "schema", name FROM Assets
                               WHERE name=? COLLATE NOCASE AND isCurrentValue=1 LIMIT 1');
        $stmt->execute(array($nm));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) return array(null, $fwd);
    return array((int)$row['id'], lapi_display($row));
}

/**
 * isDbQuery lives in LineageIO now (1-to-many per workflowIO row); a
 * relationship counts as "db query" if ANY of its LineageIO rows say so.
 * Used to build the "AND ... isDbQuery=0/1" fragments inline in raw SQL.
 */
function lapi_dbQueryExists($wfIdCol, $want) {
    $cmp = $want ? '=1' : '=0';
    if (!$want) {
        // "not a db query": true both when no LineageIO row exists at all
        // (manual edits) and when none of its rows mark it as one.
        return "NOT EXISTS (SELECT 1 FROM LineageIO li WHERE li.idWorkflowIO=$wfIdCol AND li.isDbQuery=1)";
    }
    return "EXISTS (SELECT 1 FROM LineageIO li WHERE li.idWorkflowIO=$wfIdCol AND li.isDbQuery=1)";
}

/** IO asset lists of one workflow: array of [idIO, display] per direction. */
function lapi_ioOf($pdo, $wfId, $isInput, $dbQuery = null) {
    $sql = 'SELECT DISTINCT a.id, a.category, a.subCategory, a."schema", a.name
            FROM workflowIO wf JOIN Assets a ON a.id = wf.idIO
            WHERE wf.idWorkflow=? AND wf.isInput=?';
    if ($dbQuery !== null) $sql .= ' AND ' . lapi_dbQueryExists('wf.id', $dbQuery);
    $sql .= ' ORDER BY a."schema", a.name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($wfId, $isInput));
    $out = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = array('id' => (int)$r['id'], 'display' => lapi_display($r));
    }
    return $out;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
switch ($action) {
    case 'datamarts':
        $stmt = $pdo->query(
            'SELECT id, company, client, project, datamart, root_dir, repo_name
             FROM DatamartProjects ORDER BY company, client, project, datamart'
        );
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'destinations':
    case 'sources':
        $isInput = $action === 'sources' ? 1 : 0;
        $root    = isset($_GET['root']) ? rtrim(str_replace('\\', '/', $_GET['root']), '/') : '';
        $sql = 'SELECT DISTINCT a.id, a.category, a.subCategory, a."schema", a.name
                FROM workflowIO wf
                JOIN Assets a  ON a.id = wf.idIO
                JOIN Assets s  ON s.id = wf.idWorkflow
                WHERE wf.isInput=?' . ($isInput ? ' AND ' . lapi_dbQueryExists('wf.id', 0) : '');
        $params = array($isInput);
        if ($root !== '') {
            $sql .= ' AND (s."schema"=? COLLATE NOCASE OR s."schema" LIKE ? COLLATE NOCASE)';
            $params[] = $root;
            $params[] = $root . '/%';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[] = lapi_display($r);
        sort($rows, SORT_FLAG_CASE | SORT_STRING);
        echo json_encode(array_values(array_unique($rows)));
        break;

    case 'scripts':
        $rows = array();
        $q = $pdo->query('SELECT a.id, a.category, a.subCategory, a."schema", a.name
                          FROM Assets a JOIN WorkflowMeta w ON w.idAsset=a.id
                          WHERE a.isCurrentValue=1 ORDER BY a."schema", a.name');
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[] = lapi_display($r);
        echo json_encode($rows);
        break;

    case 'graph_for_script':
        $script = isset($_GET['script']) ? $_GET['script'] : '';
        if ($script === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'Missing script parameter'));
            exit;
        }
        list($fid, $resolved) = lapi_resolveScript($pdo, $script);
        $depth = isset($_GET['depth']) ? $_GET['depth'] : 'direct';

        $depRows = array(); $rel_before = array(); $rel_after = array();
        $up_edges = array(); $down_edges = array(); $dbIns = array();
        $calledScripts = array();

        if ($fid !== null) {
            $inputs  = lapi_ioOf($pdo, $fid, 1, 0);
            $outputs = lapi_ioOf($pdo, $fid, 0);
            foreach (lapi_ioOf($pdo, $fid, 1, 1) as $d) $dbIns[] = $d['display'];

            $insD = array();  foreach ($inputs as $x)  $insD[]  = $x['display'];
            $outsD = array(); foreach ($outputs as $x) $outsD[] = $x['display'];

            if (!empty($insD) && !empty($outsD)) {
                foreach ($insD as $in) foreach ($outsD as $out)
                    $depRows[] = array('source' => $in, 's0' => $resolved, 'destination' => $out);
            } elseif (empty($insD)) {
                foreach ($outsD as $out) $depRows[] = array('source' => $resolved, 'destination' => $out);
            } else {
                foreach ($insD as $in)  $depRows[] = array('source' => $in, 'destination' => $resolved);
            }

            if ($depth === 'immediate') {
                $prodStmt = $pdo->prepare(
                    'SELECT DISTINCT idWorkflow FROM workflowIO WHERE idIO=? AND isInput=0 AND idWorkflow<>?');
                foreach ($inputs as $x) {
                    $prodStmt->execute(array($x['id'], $fid));
                    foreach ($prodStmt->fetchAll(PDO::FETCH_COLUMN) as $p) {
                        $up_edges[] = array('source' => lapi_displayOfId($pdo, (int)$p),
                                            's0' => $x['display'], 'destination' => $resolved);
                    }
                }
                $consStmt = $pdo->prepare(
                    'SELECT DISTINCT idWorkflow FROM workflowIO wf WHERE idIO=? AND isInput=1
                     AND ' . lapi_dbQueryExists('wf.id', 0) . ' AND idWorkflow<>?');
                foreach ($outputs as $x) {
                    $consStmt->execute(array($x['id'], $fid));
                    foreach ($consStmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
                        $down_edges[] = array('source' => $resolved, 's0' => $x['display'],
                                              'destination' => lapi_displayOfId($pdo, (int)$c));
                    }
                }
            } elseif ($depth === 'full') {
                $prodStmt = $pdo->prepare('SELECT DISTINCT idWorkflow FROM workflowIO WHERE idIO=? AND isInput=0');
                $sInStmt  = $pdo->prepare('SELECT DISTINCT idIO FROM workflowIO wf WHERE idWorkflow=? AND isInput=1 AND ' . lapi_dbQueryExists('wf.id', 0));
                $consStmt = $pdo->prepare('SELECT DISTINCT idWorkflow FROM workflowIO wf WHERE idIO=? AND isInput=1 AND ' . lapi_dbQueryExists('wf.id', 0));
                $sOutStmt = $pdo->prepare('SELECT DISTINCT idIO FROM workflowIO WHERE idWorkflow=? AND isInput=0');

                $seenU = array(); $queueU = array();
                foreach ($inputs as $x) if (!isset($seenU[$x['id']])) { $seenU[$x['id']] = true; $queueU[] = $x['id']; }
                while (!empty($queueU)) {
                    $K = array_shift($queueU);
                    $D = lapi_displayOfId($pdo, $K);
                    $prodStmt->execute(array($K));
                    foreach ($prodStmt->fetchAll(PDO::FETCH_COLUMN) as $p) {
                        $p = (int)$p;
                        if ($p === $fid) continue;
                        $pD = lapi_displayOfId($pdo, $p);
                        $sInStmt->execute(array($p));
                        $pIns = $sInStmt->fetchAll(PDO::FETCH_COLUMN);
                        if (empty($pIns)) {
                            $up_edges[] = array('source' => $pD, 'destination' => $D);
                        } else {
                            foreach ($pIns as $pk) {
                                $pk = (int)$pk;
                                $up_edges[] = array('source' => lapi_displayOfId($pdo, $pk), 's0' => $pD, 'destination' => $D);
                                if (!isset($seenU[$pk])) { $seenU[$pk] = true; $queueU[] = $pk; }
                            }
                        }
                    }
                }

                $seenD = array(); $queueD = array();
                foreach ($outputs as $x) if (!isset($seenD[$x['id']])) { $seenD[$x['id']] = true; $queueD[] = $x['id']; }
                while (!empty($queueD)) {
                    $K = array_shift($queueD);
                    $D = lapi_displayOfId($pdo, $K);
                    $consStmt->execute(array($K));
                    foreach ($consStmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
                        $c = (int)$c;
                        if ($c === $fid) continue;
                        $cD = lapi_displayOfId($pdo, $c);
                        $sOutStmt->execute(array($c));
                        $cOuts = $sOutStmt->fetchAll(PDO::FETCH_COLUMN);
                        if (empty($cOuts)) {
                            $down_edges[] = array('source' => $D, 'destination' => $cD);
                        } else {
                            foreach ($cOuts as $ck) {
                                $ck = (int)$ck;
                                $down_edges[] = array('source' => $D, 's0' => $cD, 'destination' => lapi_displayOfId($pdo, $ck));
                                if (!isset($seenD[$ck])) { $seenD[$ck] = true; $queueD[] = $ck; }
                            }
                        }
                    }
                }
            }

            // callers (scripts that anatellaRun this one) / callees
            $callerStmt = $pdo->prepare('SELECT DISTINCT idWorkflow FROM workflowIO WHERE idIO=? AND isInput=2');
            $callerStmt->execute(array($fid));
            foreach ($callerStmt->fetchAll(PDO::FETCH_COLUMN) as $p) {
                if ((int)$p === $fid) continue;
                $rel_before[] = lapi_displayOfId($pdo, (int)$p);
            }
            $calleeStmt = $pdo->prepare('SELECT DISTINCT idIO FROM workflowIO WHERE idWorkflow=? AND isInput=2');
            $calleeStmt->execute(array($fid));
            foreach ($calleeStmt->fetchAll(PDO::FETCH_COLUMN) as $p) {
                if ((int)$p === $fid) continue;
                $rel_after[] = lapi_displayOfId($pdo, (int)$p);
                $calledScripts[] = lapi_displayOfId($pdo, (int)$p);
            }
        }

        echo json_encode(array(
            'focal' => $resolved, 'rows' => $depRows,
            'rel_before' => array_values(array_unique($rel_before)),
            'rel_after'  => array_values(array_unique($rel_after)),
            'db_inputs'  => $dbIns,
            'called_scripts' => array_values(array_unique($calledScripts)),
            'up_edges' => $up_edges, 'down_edges' => $down_edges, 'depth' => $depth,
        ));
        break;

    case 'full_graph':
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        if ($file === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'Missing file parameter'));
            exit;
        }
        list($fileId, ) = lapi_resolveFile($pdo, $file);
        $upstream = array(); $downstream = array();
        $scriptsSeen = array();   // asset id => true (scripts appearing as s0)

        if ($fileId !== null) {
            $inScrStmt   = $pdo->prepare('SELECT DISTINCT idWorkflow FROM workflowIO WHERE idIO=? AND isInput=0');
            $outScrStmt  = $pdo->prepare('SELECT DISTINCT idWorkflow FROM workflowIO wf WHERE idIO=? AND isInput=1 AND ' . lapi_dbQueryExists('wf.id', 0));
            $inFilesStmt = $pdo->prepare('SELECT DISTINCT idIO FROM workflowIO wf WHERE idWorkflow=? AND isInput=1 AND ' . lapi_dbQueryExists('wf.id', 0));
            $outFilesStmt= $pdo->prepare('SELECT DISTINCT idIO FROM workflowIO WHERE idWorkflow=? AND isInput=0');

            $seenUp = array($fileId => true); $queueUp = array($fileId);
            while (!empty($queueUp)) {
                $cur = array_shift($queueUp);
                $curD = lapi_displayOfId($pdo, $cur);
                $inScrStmt->execute(array($cur));
                foreach ($inScrStmt->fetchAll(PDO::FETCH_COLUMN) as $scr) {
                    $scr = (int)$scr; $scriptsSeen[$scr] = true;
                    $scrD = lapi_displayOfId($pdo, $scr);
                    $inFilesStmt->execute(array($scr));
                    foreach ($inFilesStmt->fetchAll(PDO::FETCH_COLUMN) as $k) {
                        $k = (int)$k;
                        $upstream[] = array('source' => lapi_displayOfId($pdo, $k), 's0' => $scrD, 'destination' => $curD);
                        if (!isset($seenUp[$k])) { $seenUp[$k] = true; $queueUp[] = $k; }
                    }
                }
            }

            $seenDown = array($fileId => true); $queueDown = array($fileId);
            while (!empty($queueDown)) {
                $cur = array_shift($queueDown);
                $curD = lapi_displayOfId($pdo, $cur);
                $outScrStmt->execute(array($cur));
                foreach ($outScrStmt->fetchAll(PDO::FETCH_COLUMN) as $scr) {
                    $scr = (int)$scr; $scriptsSeen[$scr] = true;
                    $scrD = lapi_displayOfId($pdo, $scr);
                    $outFilesStmt->execute(array($scr));
                    foreach ($outFilesStmt->fetchAll(PDO::FETCH_COLUMN) as $k) {
                        $k = (int)$k;
                        $downstream[] = array('source' => $curD, 's0' => $scrD, 'destination' => lapi_displayOfId($pdo, $k));
                        if (!isset($seenDown[$k])) { $seenDown[$k] = true; $queueDown[] = $k; }
                    }
                }
            }
        }

        // call edges among scripts in the BFS graph
        $callEdges = array();
        if (!empty($scriptsSeen)) {
            $q = $pdo->query('SELECT idWorkflow, idIO FROM workflowIO WHERE isInput=2');
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $a = (int)$r['idWorkflow']; $b = (int)$r['idIO'];
                if (isset($scriptsSeen[$a]) && isset($scriptsSeen[$b])) {
                    $callEdges[] = array('caller' => lapi_displayOfId($pdo, $a),
                                         'callee' => lapi_displayOfId($pdo, $b));
                }
            }
        }

        echo json_encode(array('upstream' => $upstream, 'downstream' => $downstream, 'call_edges' => $callEdges));
        break;

    case 'project_graph':
        $project = isset($_GET['project']) ? $_GET['project'] : '';

        $allProjects = $pdo->query(
            "SELECT DISTINCT project FROM DatamartProjects WHERE project IS NOT NULL AND project != '' ORDER BY project"
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($project === '' || !in_array($project, $allProjects)) {
            $project = count($allProjects) > 0 ? $allProjects[0] : '';
        }

        $edges = array(); $edgeNodes = array(); $edgeKeys = array();
        $scriptGroup = array();   // asset id => group id
        $groupLabels = array();

        if ($project !== '') {
            $dmStmt = $pdo->prepare('SELECT id, datamart FROM DatamartProjects WHERE project=? ORDER BY id');
            $dmStmt->execute(array($project));
            foreach ($dmStmt->fetchAll(PDO::FETCH_ASSOC) as $dm) {
                $grpNum = (int)$dm['id'];
                $groupLabels[(string)$grpNum] = $dm['datamart'];

                // call edges within this datamart
                $callStmt = $pdo->prepare(
                    'SELECT wf.idWorkflow AS A, wf.idIO AS B FROM workflowIO wf
                     JOIN Assets s ON s.id = wf.idWorkflow
                     WHERE wf.isInput=2 AND s.idProject=?');
                $callStmt->execute(array($grpNum));
                foreach ($callStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $a = lapi_displayOfId($pdo, (int)$r['A']);
                    $b = lapi_displayOfId($pdo, (int)$r['B']);
                    $edges[] = array('A' => $a, 'B' => $b, 'grp' => (string)$grpNum);
                    $edgeNodes[$a] = true; $edgeNodes[$b] = true;
                    $edgeKeys[$a . "\x1f" . $b] = true;
                }

                // every script of the datamart (isolated nodes included)
                $smStmt = $pdo->prepare(
                    'SELECT a.id FROM Assets a JOIN WorkflowMeta w ON w.idAsset=a.id
                     WHERE a.idProject=? AND a.isCurrentValue=1 ORDER BY a."schema", a.name');
                $smStmt->execute(array($grpNum));
                foreach ($smStmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                    $sid = (int)$sid;
                    if (!isset($scriptGroup[$sid])) $scriptGroup[$sid] = (string)$grpNum;
                    $d = lapi_displayOfId($pdo, $sid);
                    if (!isset($edgeNodes[$d])) {
                        $edges[] = array('A' => $d, 'B' => '', 'grp' => (string)$grpNum);
                        $edgeNodes[$d] = true;
                    }
                }
            }

            // file-flow edges: producer -> consumer through a shared asset
            if (!empty($scriptGroup)) {
                $flowStmt = $pdo->query(
                    'SELECT DISTINCT o.idWorkflow AS producer, i.idWorkflow AS consumer
                     FROM workflowIO o
                     JOIN workflowIO i ON o.idIO = i.idIO
                     WHERE o.isInput=0 AND i.isInput=1 AND ' . lapi_dbQueryExists('i.id', 0) . '
                       AND o.idWorkflow <> i.idWorkflow');
                foreach ($flowStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $pa = (int)$r['producer']; $pb = (int)$r['consumer'];
                    if (!isset($scriptGroup[$pa]) || !isset($scriptGroup[$pb])) continue;
                    $a = lapi_displayOfId($pdo, $pa);
                    $b = lapi_displayOfId($pdo, $pb);
                    $ek = $a . "\x1f" . $b;
                    if (isset($edgeKeys[$ek])) continue;
                    $edgeKeys[$ek] = true;
                    $edges[] = array('A' => $a, 'B' => $b, 'grp' => $scriptGroup[$pa]);
                    $edgeNodes[$a] = true; $edgeNodes[$b] = true;
                }
            }
        }

        echo json_encode(array('edges' => $edges, 'projects' => $allProjects,
                               'project' => $project, 'groupLabels' => $groupLabels));
        break;

    case 'actions':
        $script = isset($_GET['script']) ? $_GET['script'] : '';
        if ($script === '') { echo json_encode(array()); break; }
        list($fid, ) = lapi_resolveScript($pdo, $script);
        if ($fid === null) { echo json_encode(array()); break; }
        $stmt = $pdo->prepare(
            'SELECT ct.nodeID AS "ID", cb.name AS "Before", c.name AS "After", ct.op, ct.expression
             FROM ColumnTransformations ct
             JOIN Columns c ON c.id = ct.idColumn
             LEFT JOIN Columns cb ON cb.id = ct.idColumnBefore
             WHERE c.idasset = ?
             ORDER BY CAST(ct.nodeID AS INTEGER), c.name');
        $stmt->execute(array($fid));
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'script_io':
        $script = isset($_GET['script']) ? $_GET['script'] : '';
        if ($script === '') {
            echo json_encode(array('inputs' => array(), 'outputs' => array(), 'calls' => array(), 'meta' => null));
            break;
        }
        list($fid, ) = lapi_resolveScript($pdo, $script);
        $result = array('inputs' => array(), 'outputs' => array(), 'calls' => array(), 'meta' => null);
        if ($fid !== null) {
            // One row per LineageIO detail (a script can touch the same file
            // from several pipeline nodes); relationships with no detail at
            // all (pure manual edits) still get one row via the LEFT JOIN.
            $stmt = $pdo->prepare(
                'SELECT wf.isInput, li.nodeID AS node_idx, li.actionTag AS action_tag,
                        COALESCE(li.isDbQuery, 0) AS is_db_query,
                        a.id, a.category, a.subCategory, a."schema", a.name
                 FROM workflowIO wf
                 JOIN Assets a ON a.id = wf.idIO
                 LEFT JOIN LineageIO li ON li.idWorkflowIO = wf.id
                 WHERE wf.idWorkflow=? ORDER BY wf.isInput, a."schema", a.name');
            $stmt->execute(array($fid));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $row = array(
                    'direction'   => $r['isInput'] == 1 ? 'input' : ($r['isInput'] == 0 ? 'output' : 'call'),
                    'file_path'   => lapi_display($r),
                    'action_tag'  => $r['action_tag'],
                    'is_db_query' => (int)$r['is_db_query'],
                    'node_idx'    => $r['node_idx'],
                    'idAsset'     => (int)$r['id'],
                );
                if ($r['isInput'] == 1)      $result['inputs'][]  = $row;
                elseif ($r['isInput'] == 0)  $result['outputs'][] = $row;
                else                         $result['calls'][]   = $row;
            }
            $stmt = $pdo->prepare(
                'SELECT rtflCount AS rtfl_count, totalNodes AS total_nodes, connectedNodes AS connected_nodes
                 FROM WorkflowMeta WHERE idAsset=? LIMIT 1');
            $stmt->execute(array($fid));
            $meta = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($meta) {
                $meta['input_count']  = count($result['inputs']);
                $meta['output_count'] = count($result['outputs']);
                $meta['call_count']   = count($result['calls']);
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM Columns WHERE idasset=?");
                $cnt->execute(array($fid));
                $meta['var_count'] = (int)$cnt->fetchColumn();
                $result['meta'] = $meta;
            }
        }
        echo json_encode($result);
        break;

    case 'variables':
        $rows = $pdo->query(
            "SELECT DISTINCT c.name FROM Columns c
             JOIN Assets a ON a.id = c.idasset
             WHERE a.category>=200 AND c.name IS NOT NULL AND c.name != '' AND c.name != '*dynamicRename*'
             ORDER BY c.name"
        )->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($rows);
        break;

    case 'scripts_for_variable':
        $var = isset($_GET['var']) ? $_GET['var'] : '';
        if ($var === '') { echo json_encode(array()); break; }
        $stmt = $pdo->prepare(
            'SELECT DISTINCT a.id, a.category, a.subCategory, a."schema", a.name
             FROM Columns c JOIN Assets a ON a.id = c.idasset
             WHERE c.name = ? AND a.category>=200 AND a.isCurrentValue=1
             ORDER BY a."schema", a.name');
        $stmt->execute(array($var));
        $rows = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[] = lapi_display($r);
        echo json_encode($rows);
        break;

    case 'anatella_file':
        $script = isset($_GET['script']) ? $_GET['script'] : '';
        if ($script === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'Missing script parameter'));
            exit;
        }
        list($fid, $display) = lapi_resolveScript($pdo, $script);
        $fullPath = false;

        // 1. original location on disk
        $scriptNorm = str_replace('/', DIRECTORY_SEPARATOR, $display);
        if (preg_match('/^[A-Za-z]:/', $display) && file_exists($scriptNorm)) {
            $fullPath = $scriptNorm;
        }
        // 2. raw passed path
        if (!$fullPath) {
            $rawNorm = str_replace('/', DIRECTORY_SEPARATOR, $script);
            if (preg_match('/^[A-Za-z]:/', $script) && file_exists($rawNorm)) $fullPath = $rawNorm;
        }
        // 3. server-side copy
        if (!$fullPath && $fid !== null) {
            $stmt = $pdo->prepare('SELECT pipelineXmlSrvPath FROM WorkflowMeta WHERE idAsset=? LIMIT 1');
            $stmt->execute(array($fid));
            $rel = $stmt->fetchColumn();
            if ($rel) {
                $p = lin_srvPathToDisk($rel);
                if (file_exists($p)) $fullPath = $p;
            }
        }
        if (!$fullPath) {
            echo json_encode(array('error' => 'Script not found', 'basename' => basename($display)));
            exit;
        }
        $xml = file_get_contents($fullPath);
        echo json_encode(array('xml' => $xml, 'path' => str_replace('\\', '/', $fullPath)));
        break;

    case 'pipeline_from_db':
        $script = isset($_GET['script']) ? $_GET['script'] : '';
        if ($script === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'Missing script parameter'));
            exit;
        }
        list($fid, ) = lapi_resolveScript($pdo, $script);
        if ($fid === null) {
            echo json_encode(array('error' => 'Script not found — run extraction first'));
            break;
        }
        $stmt = $pdo->prepare('SELECT pipelineXmlSrvPath FROM WorkflowMeta WHERE idAsset=? LIMIT 1');
        $stmt->execute(array($fid));
        $rel = $stmt->fetchColumn();
        if (!$rel) {
            echo json_encode(array('error' => 'No pipeline copy on the server — re-run extraction to capture it'));
            break;
        }
        $p = lin_srvPathToDisk($rel);
        if (!file_exists($p)) {
            echo json_encode(array('error' => 'Pipeline copy missing on disk: ' . $rel));
            break;
        }
        echo json_encode(array('pipeline_xml' => file_get_contents($p)));
        break;

    case 'open_script':
        $path = isset($_GET['path']) ? $_GET['path'] : '';
        if ($path === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'Missing path parameter'));
            exit;
        }
        $ANATELLA_EXE = 'C:\\soft\\TIMi\\bin\\Anatella.exe';
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!file_exists($path)) {
            echo json_encode(array('error' => 'File not found: ' . $path));
            exit;
        }
        if (!file_exists($ANATELLA_EXE)) {
            echo json_encode(array('error' => 'Anatella.exe not found at: ' . $ANATELLA_EXE));
            exit;
        }
        $cmd = 'START "" /B "' . $ANATELLA_EXE . '" "' . $path . '"';
        pclose(popen($cmd, 'r'));
        echo json_encode(array('ok' => true, 'path' => $path));
        break;

    case 'get_marvin_servers':
        $rows = $pdo->query(
            "SELECT id, name, serverType FROM servers WHERE isCurrentValue=1 AND name IS NOT NULL AND name != '' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        break;

    case 'register_assets':
        // Registration is now automatic during save_script; kept for UI compatibility.
        echo json_encode(array('ok' => true, 'note' => 'Assets are registered automatically during extraction — nothing to do.'));
        break;

    default:
        http_response_code(400);
        echo json_encode(array('error' => 'Unknown action'));
        break;
}
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => $e->getMessage()));
}
