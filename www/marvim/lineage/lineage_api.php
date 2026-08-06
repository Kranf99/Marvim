<?php
/**
 * lineage_api.php — lineage graph queries over MarvimDB (Assets / workflowIO /
 * LineageIO / Columns / ColumnTransformations / WorkflowMeta / DatamartProjects).
 *
 * The UI contract is unchanged: scripts and files are addressed by path
 * strings and edges are returned as display-path strings; internally every
 * join runs on Assets ids via workflowIO (isInput 0=output, 1=input,
 * 2=anatellaRun). workflowIO is relationship-only; LineageIO carries the
 * extractor's per-node detail (nodeID, actionTag, isDbQuery, source,
 * extractedAt) and can have several rows per workflowIO relationship.
 *
 * This endpoint is read-only, so the database is opened directly through the
 * SQLite3 class in SQLITE3_OPEN_READONLY mode (no PDO, no write access).
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/lineage_store.php';

try {
    $db = new SQLite3(lin_marvimDbPath(), SQLITE3_OPEN_READONLY);
    $db->busyTimeout(5000);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('error' => 'Cannot open database: ' . $e->getMessage()));
    exit;
}

// ─── SQLite3 helpers (mirror the PDO prepare/execute/fetch* shapes we used) ──

/** Bind a 0-indexed params array onto a prepared SQLite3Stmt (1-based positions). */
function lapi_bind($stmt, $params) {
    $i = 1;
    foreach ($params as $p) {
        if (is_int($p))            $type = SQLITE3_INTEGER;
        elseif (is_float($p))      $type = SQLITE3_FLOAT;
        elseif ($p === null)       $type = SQLITE3_NULL;
        else                       $type = SQLITE3_TEXT;
        $stmt->bindValue($i, $p, $type);
        $i++;
    }
}

/** Prepare a statement once (for reuse across a loop of executions). */
function lapi_prep($db, $sql) {
    $stmt = $db->prepare($sql);
    if ($stmt === false) throw new Exception('Prepare failed: ' . $db->lastErrorMsg());
    return $stmt;
}

/** Run a one-off parameterized query, returning the SQLite3Result. */
function lapi_exec($db, $sql, $params = array()) {
    $stmt = lapi_prep($db, $sql);
    lapi_bind($stmt, $params);
    $result = $stmt->execute();
    if ($result === false) throw new Exception('Query failed: ' . $db->lastErrorMsg());
    return $result;
}

/** Re-execute an already-prepared statement with fresh params; all rows, first column only. */
function lapi_execCol($stmt, $params) {
    $stmt->reset();
    lapi_bind($stmt, $params);
    $result = $stmt->execute();
    if ($result === false) throw new Exception('Query failed');
    $out = array();
    while (($row = $result->fetchArray(SQLITE3_NUM)) !== false) $out[] = $row[0];
    return $out;
}

/** All rows of a one-off query, as associative arrays. */
function lapi_all($db, $sql, $params = array()) {
    $result = lapi_exec($db, $sql, $params);
    $rows = array();
    while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) $rows[] = $row;
    return $rows;
}

/** First row of a one-off query, as an associative array, or null. */
function lapi_row($db, $sql, $params = array()) {
    $result = lapi_exec($db, $sql, $params);
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row === false ? null : $row;
}

/** All values of the first column of a one-off query. */
function lapi_col($db, $sql, $params = array()) {
    $result = lapi_exec($db, $sql, $params);
    $out = array();
    while (($row = $result->fetchArray(SQLITE3_NUM)) !== false) $out[] = $row[0];
    return $out;
}

/** First value of the first column of a one-off query, or false if no row. */
function lapi_scalar($db, $sql, $params = array()) {
    $result = lapi_exec($db, $sql, $params);
    $row = $result->fetchArray(SQLITE3_NUM);
    return $row === false ? false : $row[0];
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
function lapi_asset($db, $id) {
    static $cache = array();
    if (isset($cache[$id])) return $cache[$id];
    $row = lapi_row($db, 'SELECT id, category, subCategory, "schema", name FROM Assets WHERE id=? LIMIT 1', array((int)$id));
    $cache[$id] = $row ? $row : null;
    return $cache[$id];
}

function lapi_displayOfId($db, $id) {
    $row = lapi_asset($db, $id);
    return $row ? lapi_display($row) : ('#' . $id);
}

/** Resolve a path string to a script asset (category >= 200). Returns [id, display] or [null, path]. */
function lapi_resolveScript($db, $script) {
    $fwd = collapseDots(str_replace('\\', '/', $script));
    list($dir, $file) = lin_splitPath($fwd);
    $row = lapi_row($db, 'SELECT id, category, subCategory, "schema", name FROM Assets
                           WHERE schema=? COLLATE NOCASE AND name=? COLLATE NOCASE
                             AND category>=200 AND isCurrentValue=1 LIMIT 1', array($dir, $file));
    if (!$row) {
        $row = lapi_row($db, 'SELECT id, category, subCategory, "schema", name FROM Assets
                               WHERE name=? COLLATE NOCASE AND category>=200 AND isCurrentValue=1 LIMIT 1', array($file));
    }
    if (!$row) return array(null, $fwd);
    return array((int)$row['id'], lapi_display($row));
}

/** Resolve a path string to any asset. Returns [id, display] or [null, path]. */
function lapi_resolveFile($db, $file) {
    $fwd = collapseDots(str_replace('\\', '/', $file));
    list($dir, $nm) = lin_splitPath($fwd);
    $row = lapi_row($db, 'SELECT id, category, subCategory, "schema", name FROM Assets
                           WHERE schema=? COLLATE NOCASE AND name=? COLLATE NOCASE AND isCurrentValue=1 LIMIT 1', array($dir, $nm));
    if (!$row) {
        $row = lapi_row($db, 'SELECT id, category, subCategory, "schema", name FROM Assets
                               WHERE name=? COLLATE NOCASE AND isCurrentValue=1 LIMIT 1', array($nm));
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
function lapi_ioOf($db, $wfId, $isInput, $dbQuery = null) {
    $sql = 'SELECT DISTINCT a.id, a.category, a.subCategory, a."schema", a.name
            FROM workflowIO wf JOIN Assets a ON a.id = wf.idIO
            WHERE wf.idWorkflow=? AND wf.isInput=?';
    if ($dbQuery !== null) $sql .= ' AND ' . lapi_dbQueryExists('wf.id', $dbQuery);
    $sql .= ' ORDER BY a."schema", a.name';
    $out = array();
    foreach (lapi_all($db, $sql, array((int)$wfId, (int)$isInput)) as $r) {
        $out[] = array('id' => (int)$r['id'], 'display' => lapi_display($r));
    }
    return $out;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
switch ($action) {
    case 'datamarts':
        echo json_encode(lapi_all($db,
            'SELECT id, company, client, project, datamart, root_dir, repo_name
             FROM DatamartProjects ORDER BY company, client, project, datamart'
        ));
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
        $rows = array();
        foreach (lapi_all($db, $sql, $params) as $r) $rows[] = lapi_display($r);
        sort($rows, SORT_FLAG_CASE | SORT_STRING);
        echo json_encode(array_values(array_unique($rows)));
        break;

    case 'scripts':
        $rows = array();
        foreach (lapi_all($db, 'SELECT a.id, a.category, a.subCategory, a."schema", a.name
                                FROM Assets a JOIN WorkflowMeta w ON w.idAsset=a.id
                                WHERE a.isCurrentValue=1 ORDER BY a."schema", a.name') as $r) {
            $rows[] = lapi_display($r);
        }
        echo json_encode($rows);
        break;

    case 'graph_for_script':
        $script = isset($_GET['script']) ? $_GET['script'] : '';
        if ($script === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'Missing script parameter'));
            exit;
        }
        list($fid, $resolved) = lapi_resolveScript($db, $script);
        $depth = isset($_GET['depth']) ? $_GET['depth'] : 'direct';

        $depRows = array(); $rel_before = array(); $rel_after = array();
        $up_edges = array(); $down_edges = array(); $dbIns = array();
        $calledScripts = array();

        if ($fid !== null) {
            $inputs  = lapi_ioOf($db, $fid, 1, 0);
            $outputs = lapi_ioOf($db, $fid, 0);
            foreach (lapi_ioOf($db, $fid, 1, 1) as $d) $dbIns[] = $d['display'];

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
                $prodStmt = lapi_prep($db,
                    'SELECT DISTINCT idWorkflow FROM workflowIO WHERE idIO=? AND isInput=0 AND idWorkflow<>?');
                foreach ($inputs as $x) {
                    foreach (lapi_execCol($prodStmt, array($x['id'], $fid)) as $p) {
                        $up_edges[] = array('source' => lapi_displayOfId($db, (int)$p),
                                            's0' => $x['display'], 'destination' => $resolved);
                    }
                }
                $consStmt = lapi_prep($db,
                    'SELECT DISTINCT idWorkflow FROM workflowIO wf WHERE idIO=? AND isInput=1
                     AND ' . lapi_dbQueryExists('wf.id', 0) . ' AND idWorkflow<>?');
                foreach ($outputs as $x) {
                    foreach (lapi_execCol($consStmt, array($x['id'], $fid)) as $c) {
                        $down_edges[] = array('source' => $resolved, 's0' => $x['display'],
                                              'destination' => lapi_displayOfId($db, (int)$c));
                    }
                }
            } elseif ($depth === 'full') {
                $prodStmt = lapi_prep($db, 'SELECT DISTINCT idWorkflow FROM workflowIO WHERE idIO=? AND isInput=0');
                $sInStmt  = lapi_prep($db, 'SELECT DISTINCT idIO FROM workflowIO wf WHERE idWorkflow=? AND isInput=1 AND ' . lapi_dbQueryExists('wf.id', 0));
                $consStmt = lapi_prep($db, 'SELECT DISTINCT idWorkflow FROM workflowIO wf WHERE idIO=? AND isInput=1 AND ' . lapi_dbQueryExists('wf.id', 0));
                $sOutStmt = lapi_prep($db, 'SELECT DISTINCT idIO FROM workflowIO WHERE idWorkflow=? AND isInput=0');

                $seenU = array(); $queueU = array();
                foreach ($inputs as $x) if (!isset($seenU[$x['id']])) { $seenU[$x['id']] = true; $queueU[] = $x['id']; }
                while (!empty($queueU)) {
                    $K = array_shift($queueU);
                    $D = lapi_displayOfId($db, $K);
                    foreach (lapi_execCol($prodStmt, array($K)) as $p) {
                        $p = (int)$p;
                        if ($p === $fid) continue;
                        $pD = lapi_displayOfId($db, $p);
                        $pIns = lapi_execCol($sInStmt, array($p));
                        if (empty($pIns)) {
                            $up_edges[] = array('source' => $pD, 'destination' => $D);
                        } else {
                            foreach ($pIns as $pk) {
                                $pk = (int)$pk;
                                $up_edges[] = array('source' => lapi_displayOfId($db, $pk), 's0' => $pD, 'destination' => $D);
                                if (!isset($seenU[$pk])) { $seenU[$pk] = true; $queueU[] = $pk; }
                            }
                        }
                    }
                }

                $seenD = array(); $queueD = array();
                foreach ($outputs as $x) if (!isset($seenD[$x['id']])) { $seenD[$x['id']] = true; $queueD[] = $x['id']; }
                while (!empty($queueD)) {
                    $K = array_shift($queueD);
                    $D = lapi_displayOfId($db, $K);
                    foreach (lapi_execCol($consStmt, array($K)) as $c) {
                        $c = (int)$c;
                        if ($c === $fid) continue;
                        $cD = lapi_displayOfId($db, $c);
                        $cOuts = lapi_execCol($sOutStmt, array($c));
                        if (empty($cOuts)) {
                            $down_edges[] = array('source' => $D, 'destination' => $cD);
                        } else {
                            foreach ($cOuts as $ck) {
                                $ck = (int)$ck;
                                $down_edges[] = array('source' => $D, 's0' => $cD, 'destination' => lapi_displayOfId($db, $ck));
                                if (!isset($seenD[$ck])) { $seenD[$ck] = true; $queueD[] = $ck; }
                            }
                        }
                    }
                }
            }

            // callers (scripts that anatellaRun this one) / callees
            foreach (lapi_col($db, 'SELECT DISTINCT idWorkflow FROM workflowIO WHERE idIO=? AND isInput=2', array($fid)) as $p) {
                if ((int)$p === $fid) continue;
                $rel_before[] = lapi_displayOfId($db, (int)$p);
            }
            foreach (lapi_col($db, 'SELECT DISTINCT idIO FROM workflowIO WHERE idWorkflow=? AND isInput=2', array($fid)) as $p) {
                if ((int)$p === $fid) continue;
                $rel_after[] = lapi_displayOfId($db, (int)$p);
                $calledScripts[] = lapi_displayOfId($db, (int)$p);
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
        list($fileId, ) = lapi_resolveFile($db, $file);
        $upstream = array(); $downstream = array();
        $scriptsSeen = array();   // asset id => true (scripts appearing as s0)

        if ($fileId !== null) {
            $inScrStmt   = lapi_prep($db, 'SELECT DISTINCT idWorkflow FROM workflowIO WHERE idIO=? AND isInput=0');
            $outScrStmt  = lapi_prep($db, 'SELECT DISTINCT idWorkflow FROM workflowIO wf WHERE idIO=? AND isInput=1 AND ' . lapi_dbQueryExists('wf.id', 0));
            $inFilesStmt = lapi_prep($db, 'SELECT DISTINCT idIO FROM workflowIO wf WHERE idWorkflow=? AND isInput=1 AND ' . lapi_dbQueryExists('wf.id', 0));
            $outFilesStmt= lapi_prep($db, 'SELECT DISTINCT idIO FROM workflowIO WHERE idWorkflow=? AND isInput=0');

            $seenUp = array($fileId => true); $queueUp = array($fileId);
            while (!empty($queueUp)) {
                $cur = array_shift($queueUp);
                $curD = lapi_displayOfId($db, $cur);
                foreach (lapi_execCol($inScrStmt, array($cur)) as $scr) {
                    $scr = (int)$scr; $scriptsSeen[$scr] = true;
                    $scrD = lapi_displayOfId($db, $scr);
                    foreach (lapi_execCol($inFilesStmt, array($scr)) as $k) {
                        $k = (int)$k;
                        $upstream[] = array('source' => lapi_displayOfId($db, $k), 's0' => $scrD, 'destination' => $curD);
                        if (!isset($seenUp[$k])) { $seenUp[$k] = true; $queueUp[] = $k; }
                    }
                }
            }

            $seenDown = array($fileId => true); $queueDown = array($fileId);
            while (!empty($queueDown)) {
                $cur = array_shift($queueDown);
                $curD = lapi_displayOfId($db, $cur);
                foreach (lapi_execCol($outScrStmt, array($cur)) as $scr) {
                    $scr = (int)$scr; $scriptsSeen[$scr] = true;
                    $scrD = lapi_displayOfId($db, $scr);
                    foreach (lapi_execCol($outFilesStmt, array($scr)) as $k) {
                        $k = (int)$k;
                        $downstream[] = array('source' => $curD, 's0' => $scrD, 'destination' => lapi_displayOfId($db, $k));
                        if (!isset($seenDown[$k])) { $seenDown[$k] = true; $queueDown[] = $k; }
                    }
                }
            }
        }

        // call edges among scripts in the BFS graph
        $callEdges = array();
        if (!empty($scriptsSeen)) {
            foreach (lapi_all($db, 'SELECT idWorkflow, idIO FROM workflowIO WHERE isInput=2') as $r) {
                $a = (int)$r['idWorkflow']; $b = (int)$r['idIO'];
                if (isset($scriptsSeen[$a]) && isset($scriptsSeen[$b])) {
                    $callEdges[] = array('caller' => lapi_displayOfId($db, $a),
                                         'callee' => lapi_displayOfId($db, $b));
                }
            }
        }

        echo json_encode(array('upstream' => $upstream, 'downstream' => $downstream, 'call_edges' => $callEdges));
        break;

    case 'project_graph':
        $project = isset($_GET['project']) ? $_GET['project'] : '';

        $allProjects = lapi_col($db,
            "SELECT DISTINCT project FROM DatamartProjects WHERE project IS NOT NULL AND project != '' ORDER BY project"
        );

        if ($project === '' || !in_array($project, $allProjects)) {
            $project = count($allProjects) > 0 ? $allProjects[0] : '';
        }

        $edges = array(); $edgeNodes = array(); $edgeKeys = array();
        $scriptGroup = array();   // asset id => group id
        $groupLabels = array();

        if ($project !== '') {
            foreach (lapi_all($db, 'SELECT id, datamart FROM DatamartProjects WHERE project=? ORDER BY id', array($project)) as $dm) {
                $grpNum = (int)$dm['id'];
                $groupLabels[(string)$grpNum] = $dm['datamart'];

                // call edges within this datamart
                foreach (lapi_all($db,
                    'SELECT wf.idWorkflow AS A, wf.idIO AS B FROM workflowIO wf
                     JOIN Assets s ON s.id = wf.idWorkflow
                     WHERE wf.isInput=2 AND s.idProject=?', array($grpNum)) as $r) {
                    $a = lapi_displayOfId($db, (int)$r['A']);
                    $b = lapi_displayOfId($db, (int)$r['B']);
                    $edges[] = array('A' => $a, 'B' => $b, 'grp' => (string)$grpNum);
                    $edgeNodes[$a] = true; $edgeNodes[$b] = true;
                    $edgeKeys[$a . "\x1f" . $b] = true;
                }

                // every script of the datamart (isolated nodes included)
                foreach (lapi_col($db,
                    'SELECT a.id FROM Assets a JOIN WorkflowMeta w ON w.idAsset=a.id
                     WHERE a.idProject=? AND a.isCurrentValue=1 ORDER BY a."schema", a.name', array($grpNum)) as $sid) {
                    $sid = (int)$sid;
                    if (!isset($scriptGroup[$sid])) $scriptGroup[$sid] = (string)$grpNum;
                    $d = lapi_displayOfId($db, $sid);
                    if (!isset($edgeNodes[$d])) {
                        $edges[] = array('A' => $d, 'B' => '', 'grp' => (string)$grpNum);
                        $edgeNodes[$d] = true;
                    }
                }
            }

            // file-flow edges: producer -> consumer through a shared asset
            if (!empty($scriptGroup)) {
                foreach (lapi_all($db,
                    'SELECT DISTINCT o.idWorkflow AS producer, i.idWorkflow AS consumer
                     FROM workflowIO o
                     JOIN workflowIO i ON o.idIO = i.idIO
                     WHERE o.isInput=0 AND i.isInput=1 AND ' . lapi_dbQueryExists('i.id', 0) . '
                       AND o.idWorkflow <> i.idWorkflow') as $r) {
                    $pa = (int)$r['producer']; $pb = (int)$r['consumer'];
                    if (!isset($scriptGroup[$pa]) || !isset($scriptGroup[$pb])) continue;
                    $a = lapi_displayOfId($db, $pa);
                    $b = lapi_displayOfId($db, $pb);
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
        list($fid, ) = lapi_resolveScript($db, $script);
        if ($fid === null) { echo json_encode(array()); break; }
        echo json_encode(lapi_all($db,
            'SELECT ct.nodeID AS "ID", cb.name AS "Before", c.name AS "After", ct.op, ct.expression
             FROM ColumnTransformations ct
             JOIN Columns c ON c.id = ct.idColumn
             LEFT JOIN Columns cb ON cb.id = ct.idColumnBefore
             WHERE c.idasset = ?
             ORDER BY CAST(ct.nodeID AS INTEGER), c.name', array($fid)));
        break;

    case 'script_io':
        $script = isset($_GET['script']) ? $_GET['script'] : '';
        if ($script === '') {
            echo json_encode(array('inputs' => array(), 'outputs' => array(), 'calls' => array(), 'meta' => null));
            break;
        }
        list($fid, ) = lapi_resolveScript($db, $script);
        $result = array('inputs' => array(), 'outputs' => array(), 'calls' => array(), 'meta' => null);
        if ($fid !== null) {
            // One row per LineageIO detail (a script can touch the same file
            // from several pipeline nodes); relationships with no detail at
            // all (pure manual edits) still get one row via the LEFT JOIN.
            foreach (lapi_all($db,
                'SELECT wf.isInput, li.nodeID AS node_idx, li.actionTag AS action_tag,
                        COALESCE(li.isDbQuery, 0) AS is_db_query,
                        a.id, a.category, a.subCategory, a."schema", a.name
                 FROM workflowIO wf
                 JOIN Assets a ON a.id = wf.idIO
                 LEFT JOIN LineageIO li ON li.idWorkflowIO = wf.id
                 WHERE wf.idWorkflow=? ORDER BY wf.isInput, a."schema", a.name', array($fid)) as $r) {
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
            $meta = lapi_row($db,
                'SELECT rtflCount AS rtfl_count, totalNodes AS total_nodes, connectedNodes AS connected_nodes
                 FROM WorkflowMeta WHERE idAsset=? LIMIT 1', array($fid));
            if ($meta) {
                $meta['input_count']  = count($result['inputs']);
                $meta['output_count'] = count($result['outputs']);
                $meta['call_count']   = count($result['calls']);
                $meta['var_count'] = (int)lapi_scalar($db, "SELECT COUNT(*) FROM Columns WHERE idasset=?", array($fid));
                $result['meta'] = $meta;
            }
        }
        echo json_encode($result);
        break;

    case 'variables':
        echo json_encode(lapi_col($db,
            "SELECT DISTINCT c.name FROM Columns c
             JOIN Assets a ON a.id = c.idasset
             WHERE a.category>=200 AND c.name IS NOT NULL AND c.name != '' AND c.name != '*dynamicRename*'
             ORDER BY c.name"
        ));
        break;

    case 'scripts_for_variable':
        $var = isset($_GET['var']) ? $_GET['var'] : '';
        if ($var === '') { echo json_encode(array()); break; }
        $rows = array();
        foreach (lapi_all($db,
            'SELECT DISTINCT a.id, a.category, a.subCategory, a."schema", a.name
             FROM Columns c JOIN Assets a ON a.id = c.idasset
             WHERE c.name = ? AND a.category>=200 AND a.isCurrentValue=1
             ORDER BY a."schema", a.name', array($var)) as $r) {
            $rows[] = lapi_display($r);
        }
        echo json_encode($rows);
        break;

    case 'anatella_file':
        $script = isset($_GET['script']) ? $_GET['script'] : '';
        if ($script === '') {
            http_response_code(400);
            echo json_encode(array('error' => 'Missing script parameter'));
            exit;
        }
        list($fid, $display) = lapi_resolveScript($db, $script);
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
            $rel = lapi_scalar($db, 'SELECT pipelineXmlSrvPath FROM WorkflowMeta WHERE idAsset=? LIMIT 1', array($fid));
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
        list($fid, ) = lapi_resolveScript($db, $script);
        if ($fid === null) {
            echo json_encode(array('error' => 'Script not found — run extraction first'));
            break;
        }
        $rel = lapi_scalar($db, 'SELECT pipelineXmlSrvPath FROM WorkflowMeta WHERE idAsset=? LIMIT 1', array($fid));
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

    case 'get_marvim_servers':
        echo json_encode(lapi_all($db,
            "SELECT id, name, serverType FROM servers WHERE isCurrentValue=1 AND name IS NOT NULL AND name != '' ORDER BY name"
        ));
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
