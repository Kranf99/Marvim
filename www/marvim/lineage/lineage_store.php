<?php
/**
 * lineage_store.php — shared write layer for lineage data in MarvimDB.sqlite.
 *
 * Replaces the old lineage.sqlite persistence. Conventions (see REFACTOR_PLAN.md):
 *   Assets identity  = (idserver, schema, name); schema is the directory only
 *                      (canonical absolute, forward slashes, no trailing slash),
 *                      name is the file name. For DB tables schema = db schema.
 *   workflowIO       : isInput 0=output, 1=input, 2=anatellaRun(call); one row
 *                      per distinct (idWorkflow,idIO,isInput) relationship —
 *                      shared by manual edits and the extractor.
 *   LineageIO        : 1-to-many detail per workflowIO row (a script can touch
 *                      the same file from several pipeline nodes); nodeID,
 *                      actionTag, isDbQuery, source ('manual'|'extract'),
 *                      extractedAt. Only 'extract' rows are ever written here
 *                      by the extractor; manual edits leave it empty.
 *   Columns          : one row per (idasset=script asset, name=variable); no
 *                      provenance tracking — re-extraction replaces every
 *                      row on the asset that isn't in the fresh result.
 *   ColumnTransformations : per-node operation history (create/update/rename/renameDynamic).
 *   WorkflowMeta     : per-script stats + pipelineXmlSrvPath (server-side .anatella copy).
 *
 * PHP 5.6 / SQLite 3.8 compatible (no UPSERT, no expression indexes).
 */

require_once __DIR__ . '/path_utils.php';

define('LIN_CAT_FILE',   102);
define('LIN_CAT_DBTAB',  122);
define('LIN_CAT_SCRIPT', 200);
define('LIN_SUBCAT_DYNAMIC', 3);   // Assets.subCategory marker for dynamic-path endpoints

function lin_marvimDbPath() {
    $env = getenv('Marvim_DB');                 // test override
    return $env !== false && $env !== '' ? $env : __DIR__ . '/../../../db/MarvimDB.sqlite';
}

function lin_marvimPdo() {
    $pdo = new PDO('sqlite:' . lin_marvimDbPath());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
    return $pdo;
}

function lin_now() {
    return date('Ymd H:i:s');   // marvim-native timestamp format
}

/**
 * Default department applied to assets created by the extractor.
 * Marvim list pages INNER JOIN userDepartmentRights on idDepartment, so a
 * NULL department makes an asset invisible to non-superadmin users.
 */
function lin_setDefaultDepartment($id) {
    $GLOBALS['LIN_DEFAULT_DEPT'] = (int)$id;
}
function lin_defaultDepartment() {
    return isset($GLOBALS['LIN_DEFAULT_DEPT']) && $GLOBALS['LIN_DEFAULT_DEPT'] > 0
        ? $GLOBALS['LIN_DEFAULT_DEPT'] : 1;
}

/**
 * Owner (MarvimUsers id) applied to assets created/updated by the extractor.
 * idowner=0 makes an asset editable/attributable to nobody in the UI, and
 * some list/permission paths key off it — always set it to the logged-in user.
 */
function lin_setDefaultOwner($id) {
    $GLOBALS['LIN_DEFAULT_OWNER'] = (int)$id;
}
function lin_defaultOwner() {
    return isset($GLOBALS['LIN_DEFAULT_OWNER']) && $GLOBALS['LIN_DEFAULT_OWNER'] > 0
        ? $GLOBALS['LIN_DEFAULT_OWNER'] : 0;
}

// ─── Path helpers ─────────────────────────────────────────────────────────────

/** Strip trailing slash from a directory (keep 'c:/' for drive roots). */
function lin_normDir($dir) {
    $dir = rtrim(str_replace('\\', '/', $dir), '/');
    if (preg_match('#^[A-Za-z]:$#', $dir)) $dir .= '/';
    return $dir;
}

/** Split a display path into array(schema=dir, name=file). */
function lin_splitPath($display) {
    $display = str_replace('\\', '/', $display);
    $pos = strrpos($display, '/');
    if ($pos === false) return array('', $display);
    return array(lin_normDir(substr($display, 0, $pos)), substr($display, $pos + 1));
}

/** Split a DB endpoint label ("conn.table", "table" or query text) into (schema, name). */
function lin_dbLabelSplit($label) {
    $label = trim($label);
    if (preg_match('/^([\w$#]+)\.([\w$#.]+)$/', $label, $m)) {
        return array($m[1], $m[2]);
    }
    return array('DBQUERY', $label);
}

// ─── Schema (idempotent) ──────────────────────────────────────────────────────

/**
 * Ensure the MarvimDB lineage schema delta exists. Safe to call on every request.
 * NOTE: does NOT create the unique indexes — the migration script does, after
 * cleaning legacy duplicates (see migrate_lineage_v2.php).
 */
function lin_ensureSchema($pdo) {
    // Assets.idProject
    try { $pdo->exec('ALTER TABLE Assets ADD COLUMN idProject INTEGER'); } catch (Exception $e) {}
    $pdo->exec('CREATE TABLE IF NOT EXISTS WorkflowMeta (
        idAsset            INTEGER PRIMARY KEY,
        rtflCount          INTEGER DEFAULT 0,
        totalNodes         INTEGER DEFAULT 0,
        connectedNodes     INTEGER DEFAULT 0,
        hasError           INTEGER DEFAULT 0,
        errorMsg           TEXT,
        serverName         TEXT,
        pipelineXmlSrvPath TEXT,
        isSnapshotOnly     INTEGER DEFAULT 0,
        extractedAt        TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS ColumnTransformations (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        idColumn       INTEGER NOT NULL,
        idColumnBefore INTEGER,
        op             TEXT NOT NULL,
        expression     TEXT,
        nodeID         INTEGER,
        actionTag      TEXT,
        extractedAt    TEXT
    )');
    try { $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ct_col ON ColumnTransformations(idColumn)'); } catch (Exception $e) {}

    // workflowIO rebuild: keep only the relationship columns. Triggered either
    // by the old (pre-LineageIO) shape lacking a PK, or by the transitional
    // shape that still carries nodeID/actionTag/isDbQuery/source/extractedAt.
    $wfCols = array();
    foreach ($pdo->query('PRAGMA table_info(workflowIO)')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $wfCols[strtolower($c['name'])] = true;
    }
    $needsRebuild = !isset($wfCols['id']) || isset($wfCols['source']);
    if ($needsRebuild) {
        $pdo->exec('BEGIN');
        $pdo->exec('CREATE TABLE workflowIO_new (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            idWorkflow INTEGER NOT NULL,
            idIO       INTEGER NOT NULL,
            isInput    INTEGER NOT NULL,
            UNIQUE(idWorkflow, idIO, isInput)
        )');
        $pdo->exec('INSERT OR IGNORE INTO workflowIO_new (idWorkflow, idIO, isInput)
                    SELECT DISTINCT idWorkflow, idIO, isInput FROM workflowIO');

        $pdo->exec('CREATE TABLE IF NOT EXISTS LineageIO (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            idWorkflowIO INTEGER NOT NULL,
            nodeID       INTEGER,
            actionTag    TEXT,
            isDbQuery    INTEGER NOT NULL DEFAULT 0,
            source       TEXT    NOT NULL DEFAULT \'extract\',
            extractedAt  TEXT
        )');
        // Carry every old detail row forward, resolved to its new workflowIO.id.
        if (isset($wfCols['nodeid'])) {
            $pdo->exec("INSERT INTO LineageIO (idWorkflowIO, nodeID, actionTag, isDbQuery, source, extractedAt)
                        SELECT wn.id, wo.nodeID, wo.actionTag, wo.isDbQuery,
                               COALESCE(wo.source, 'manual'), wo.extractedAt
                        FROM workflowIO wo
                        JOIN workflowIO_new wn
                          ON wn.idWorkflow = wo.idWorkflow AND wn.idIO = wo.idIO AND wn.isInput = wo.isInput");
        }
        $pdo->exec('DROP TABLE workflowIO');
        $pdo->exec('ALTER TABLE workflowIO_new RENAME TO workflowIO');
        $pdo->exec('COMMIT');
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS LineageIO (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        idWorkflowIO INTEGER NOT NULL,
        nodeID       INTEGER,
        actionTag    TEXT,
        isDbQuery    INTEGER NOT NULL DEFAULT 0,
        source       TEXT    NOT NULL DEFAULT \'extract\',
        extractedAt  TEXT
    )');
    try { $pdo->exec('CREATE INDEX IF NOT EXISTS idx_wfio_io ON workflowIO(idIO, isInput)'); } catch (Exception $e) {}
    try { $pdo->exec('CREATE INDEX IF NOT EXISTS idx_wfio_wf ON workflowIO(idWorkflow, isInput)'); } catch (Exception $e) {}
    try { $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lio_wf ON LineageIO(idWorkflowIO)'); } catch (Exception $e) {}
}

// ─── Assets ───────────────────────────────────────────────────────────────────

function lin_findAssetId($pdo, $idserver, $schema, $name) {
    $stmt = $pdo->prepare(
        'SELECT id FROM Assets
         WHERE idserver=? AND schema=? COLLATE NOCASE AND name=? COLLATE NOCASE
           AND isCurrentValue=1 LIMIT 1'
    );
    $stmt->execute(array($idserver, $schema, $name));
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

/**
 * Find-or-create an asset by identity triple. $opts: category (required for
 * insert), subCategory, idProject, idowner. Updates idProject/subCategory on
 * existing rows only when a non-null value is supplied.
 */
function lin_upsertAsset($pdo, $idserver, $schema, $name, $opts = array()) {
    $id = lin_findAssetId($pdo, $idserver, $schema, $name);
    $now = lin_now();
    if ($id === null) {
        $stmt = $pdo->prepare(
            'INSERT INTO Assets (category, subCategory, idDepartment, idserver, "schema", name, idProject, status,
                                 popularity, rating, idowner, dateCreated, dateUpdated,
                                 isCurrentValue, validityFrom, validityTo)
             VALUES (?,?,?,?,?,?,?,0,0,0,?,?,?,1,?,\'99991231 23:59:59\')'
        );
        $stmt->execute(array(
            isset($opts['category'])    ? $opts['category']       : LIN_CAT_FILE,
            isset($opts['subCategory']) ? $opts['subCategory']    : 0,
            isset($opts['idDepartment']) && $opts['idDepartment'] ? $opts['idDepartment'] : lin_defaultDepartment(),
            $idserver, $schema, $name,
            isset($opts['idProject'])   ? $opts['idProject']      : null,
            isset($opts['idowner']) && $opts['idowner'] ? $opts['idowner'] : lin_defaultOwner(),
            $now, $now, $now
        ));
        return (int)$pdo->lastInsertId();
    }
    $sets = array(); $vals = array();
    if (isset($opts['idProject']) && $opts['idProject'] !== null) { $sets[] = 'idProject=?';   $vals[] = $opts['idProject']; }
    if (isset($opts['subCategory']) && $opts['subCategory'] !== null && $opts['subCategory'] !== 0) {
        $sets[] = 'subCategory=?'; $vals[] = $opts['subCategory'];
    }
    $owner = isset($opts['idowner']) && $opts['idowner'] ? $opts['idowner'] : lin_defaultOwner();
    if ($owner) { $sets[] = 'idowner=?'; $vals[] = $owner; }
    if (!empty($sets)) {
        $vals[] = $id;
        $pdo->prepare('UPDATE Assets SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals);
    }
    return $id;
}

/** Longest DatamartProjects.root_dir prefix matching the (forward-slash) path, or null. */
function lin_matchProject($pdo, $pathFwd) {
    static $roots = null;
    if ($roots === null) {
        $roots = array();
        try {
            foreach ($pdo->query('SELECT id, root_dir FROM DatamartProjects')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $roots[] = array('id' => (int)$r['id'],
                                 'root' => strtolower(lin_normDir($r['root_dir'])) . '/');
            }
        } catch (Exception $e) {}
    }
    $p = strtolower(str_replace('\\', '/', $pathFwd));
    $best = null; $bestLen = 0;
    foreach ($roots as $r) {
        if (strpos($p, $r['root']) === 0 && strlen($r['root']) > $bestLen) {
            $best = $r['id']; $bestLen = strlen($r['root']);
        }
    }
    return $best;
}

/** Upsert the asset for a script (.anatella) path. Returns id. */
function lin_upsertScriptAsset($pdo, $idserver, $scriptPathFwd) {
    $scriptPathFwd = collapseDots(str_replace('\\', '/', $scriptPathFwd));
    list($dir, $file) = lin_splitPath($scriptPathFwd);
    return lin_upsertAsset($pdo, $idserver, $dir, $file, array(
        'category'  => LIN_CAT_SCRIPT,
        'idProject' => lin_matchProject($pdo, $scriptPathFwd),
    ));
}

/**
 * Upsert the asset for one IO endpoint of a script.
 * Returns array(id, isDbQuery, isDynamic).
 */
function lin_upsertEndpointAsset($pdo, $idserver, $rawPath, $scriptPathFwd, $isDbQuery) {
    if ($isDbQuery) {
        list($sch, $nm) = lin_dbLabelSplit($rawPath);
        $id = lin_upsertAsset($pdo, $idserver, $sch, $nm, array('category' => LIN_CAT_DBTAB));
        return array($id, 1, 0);
    }
    $c = canonicalizeFilePath($rawPath, $scriptPathFwd);
    if ($c['key'] === null) {                       // dynamic expression
        $raw = trim((string)$rawPath);
        $id = lin_upsertAsset($pdo, $idserver, $raw, basename(str_replace('\\', '/', $raw)), array(
            'category' => LIN_CAT_FILE, 'subCategory' => LIN_SUBCAT_DYNAMIC,
        ));
        return array($id, 0, 1);
    }
    list($dir, $file) = lin_splitPath($c['display']);
    $cat = preg_match('/\.anatella$/i', $file) ? LIN_CAT_SCRIPT : LIN_CAT_FILE;
    $id = lin_upsertAsset($pdo, $idserver, $dir, $file, array(
        'category'  => $cat,
        'idProject' => lin_matchProject($pdo, $c['display']),
    ));
    return array($id, 0, 0);
}

/**
 * Resolve a called-script reference to its Assets id: canonical (dir,name)
 * match first, then basename match among known scripts, else create.
 */
function lin_resolveScriptAsset($pdo, $idserver, $rawPath, $callerFwd) {
    $c = canonicalizeFilePath($rawPath, $callerFwd);
    if ($c['key'] !== null) {
        list($d, $f) = lin_splitPath($c['display']);
        $id = lin_findAssetId($pdo, $idserver, $d, $f);
        if ($id !== null) return $id;
    }
    $bn = basename(str_replace('\\', '/', $rawPath));
    $stmt = $pdo->prepare('SELECT id FROM Assets WHERE category=' . LIN_CAT_SCRIPT . '
                           AND idserver=? AND name=? COLLATE NOCASE AND isCurrentValue=1 LIMIT 1');
    $stmt->execute(array($idserver, $bn));
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int)$id;
    list($id2, , ) = lin_upsertEndpointAsset($pdo, $idserver, $rawPath, $callerFwd, 0);
    return $id2;
}

// ─── workflowIO / LineageIO ───────────────────────────────────────────────────

/** Find-or-create the (idWorkflow,idIO,isInput) relationship row. Returns its id. */
function lin_findOrCreateWorkflowIO($pdo, $idWorkflow, $idIO, $isInput) {
    $sel = $pdo->prepare('SELECT id FROM workflowIO WHERE idWorkflow=? AND idIO=? AND isInput=? LIMIT 1');
    $sel->execute(array($idWorkflow, $idIO, $isInput));
    $id = $sel->fetchColumn();
    if ($id !== false) return (int)$id;
    $pdo->prepare('INSERT INTO workflowIO (idWorkflow, idIO, isInput) VALUES (?,?,?)')
        ->execute(array($idWorkflow, $idIO, $isInput));
    return (int)$pdo->lastInsertId();
}

/**
 * Replace the extractor-owned IO rows of one workflow.
 * $rows: array of array(idIO, isInput, nodeID, actionTag, isDbQuery).
 * One (idWorkflow,idIO,isInput) relationship can carry several LineageIO rows
 * (the same file touched from multiple pipeline nodes) — workflowIO itself
 * always has exactly one row per relationship, reused across re-extractions.
 */
function lin_replaceExtractedIO($pdo, $idWorkflow, $rows) {
    // Relationship rows this workflow previously got from extraction (i.e. had
    // at least one 'extract' LineageIO row) — captured before we touch anything,
    // so we can tell afterwards which ones this pass did NOT reconfirm.
    $prevExtract = $pdo->prepare(
        "SELECT DISTINCT idWorkflowIO FROM LineageIO li
         JOIN workflowIO wf ON wf.id = li.idWorkflowIO
         WHERE wf.idWorkflow=? AND li.source='extract'"
    );
    $prevExtract->execute(array($idWorkflow));
    $prevExtractIds = array_flip($prevExtract->fetchAll(PDO::FETCH_COLUMN));

    $pdo->exec('DELETE FROM LineageIO WHERE idWorkflowIO IN (
                    SELECT id FROM workflowIO WHERE idWorkflow=' . (int)$idWorkflow . '
                ) AND source=\'extract\'');

    $now = lin_now();
    $ins = $pdo->prepare(
        "INSERT INTO LineageIO (idWorkflowIO, nodeID, actionTag, isDbQuery, source, extractedAt)
         VALUES (?,?,?,?,'extract',?)"
    );
    $keepWfIds = array();
    foreach ($rows as $r) {
        $wfId = lin_findOrCreateWorkflowIO($pdo, $idWorkflow, $r['idIO'], $r['isInput']);
        $keepWfIds[$wfId] = true;
        $ins->execute(array($wfId,
                            isset($r['nodeID']) ? $r['nodeID'] : null,
                            isset($r['actionTag']) ? $r['actionTag'] : null,
                            isset($r['isDbQuery']) ? $r['isDbQuery'] : 0,
                            $now));
    }

    // Any relationship this workflow previously got from extraction but this
    // pass did NOT reconfirm no longer exists in the script — remove it
    // (manual rows never appear in $prevExtractIds, so they're untouched).
    $del = $pdo->prepare('DELETE FROM workflowIO WHERE id=?');
    foreach (array_keys($prevExtractIds) as $wfId) {
        if (!isset($keepWfIds[$wfId])) $del->execute(array($wfId));
    }
}

// ─── Columns / ColumnTransformations ─────────────────────────────────────────

function lin_findOrCreateColumn($pdo, $idAsset, $name) {
    $sel = $pdo->prepare('SELECT id FROM Columns WHERE idasset=? AND name=? AND isCurrentValue=1 LIMIT 1');
    $sel->execute(array($idAsset, $name));
    $id = $sel->fetchColumn();
    if ($id !== false) return (int)$id;
    $now = lin_now();
    $ins = $pdo->prepare(
        "INSERT INTO Columns (idasset, name, status, popularity, rating, idowner,
                              dateCreated, dateUpdated, isCurrentValue, validityFrom, validityTo)
         VALUES (?,?,0,0,0,0,?,?,1,?,'99991231 23:59:59')"
    );
    $ins->execute(array($idAsset, $name, $now, $now, $now));
    return (int)$pdo->lastInsertId();
}

/**
 * Replace the variables + transformations of one script asset.
 * $vars: extractor format — array of array(idx, type, op, before, after, expression).
 * NOTE: no origin tracking — any pre-existing Columns row on this asset whose
 * name isn't in the fresh extraction result is deleted, curated or not.
 */
function lin_replaceExtractedVars($pdo, $idAsset, $vars) {
    // Wipe old transformations for this asset's columns.
    $pdo->prepare('DELETE FROM ColumnTransformations
                   WHERE idColumn IN (SELECT id FROM Columns WHERE idasset=?)')
        ->execute(array($idAsset));

    // Collect the new identity set (afters + befores).
    $names = array();
    foreach ($vars as $v) {
        $after  = isset($v['after'])  ? (string)$v['after']  : '';
        $before = isset($v['before']) ? (string)$v['before'] : '';
        if ($after !== '')  $names[$after]  = true;
        if ($before !== '') $names[$before] = true;
    }

    // Drop columns on this asset that no longer exist in the extraction result.
    $keep = array_keys($names);
    $sel = $pdo->prepare("SELECT id, name FROM Columns WHERE idasset=? AND isCurrentValue=1");
    $sel->execute(array($idAsset));
    $del = $pdo->prepare('DELETE FROM Columns WHERE id=?');
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!isset($names[$row['name']])) $del->execute(array($row['id']));
    }

    // Identity map + transformations.
    $idOf = array();
    foreach ($keep as $nm) $idOf[$nm] = lin_findOrCreateColumn($pdo, $idAsset, $nm);

    $now = lin_now();
    $ins = $pdo->prepare(
        'INSERT INTO ColumnTransformations (idColumn, idColumnBefore, op, expression, nodeID, actionTag, extractedAt)
         VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($vars as $v) {
        $after  = isset($v['after'])  ? (string)$v['after']  : '';
        $before = isset($v['before']) ? (string)$v['before'] : '';
        if ($after === '') continue;
        $ins->execute(array(
            $idOf[$after],
            ($before !== '' && isset($idOf[$before])) ? $idOf[$before] : null,
            isset($v['op']) ? $v['op'] : 'update',
            isset($v['expression']) ? $v['expression'] : null,
            isset($v['idx']) ? $v['idx'] : null,
            isset($v['type']) ? $v['type'] : null,
            $now
        ));
    }
}

// ─── WorkflowMeta ─────────────────────────────────────────────────────────────

function lin_upsertWorkflowMeta($pdo, $idAsset, $meta) {
    $pdo->prepare('DELETE FROM WorkflowMeta WHERE idAsset=?')->execute(array($idAsset));
    $pdo->prepare(
        'INSERT INTO WorkflowMeta (idAsset, rtflCount, totalNodes, connectedNodes, hasError,
                                   errorMsg, serverName, pipelineXmlSrvPath, isSnapshotOnly, extractedAt)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute(array(
        $idAsset,
        isset($meta['rtflCount'])          ? (int)$meta['rtflCount']      : 0,
        isset($meta['totalNodes'])         ? (int)$meta['totalNodes']     : 0,
        isset($meta['connectedNodes'])     ? (int)$meta['connectedNodes'] : 0,
        isset($meta['hasError'])           ? (int)$meta['hasError']       : 0,
        isset($meta['errorMsg'])           ? $meta['errorMsg']            : null,
        isset($meta['serverName'])         ? $meta['serverName']          : null,
        isset($meta['pipelineXmlSrvPath']) ? $meta['pipelineXmlSrvPath']  : null,
        isset($meta['isSnapshotOnly'])     ? (int)$meta['isSnapshotOnly'] : 0,
        isset($meta['extractedAt'])        ? $meta['extractedAt']         : lin_now(),
    ));
}

// ─── Server-side .anatella store ─────────────────────────────────────────────

/**
 * Store the server-side copy for a script asset.
 * Copies $srcPath when readable; otherwise writes $fallbackXml (pipeline snapshot).
 * Returns array(relPath|null, isSnapshotOnly).
 */
function lin_storeAnatellaCopy($idserver, $assetId, $srcPath, $fallbackXml = null) {
    $base = getenv('LIN_ANATELLA_DIR');         // test override
    if ($base === false || $base === '') $base = __DIR__ . '/anatella';
    $dir = $base . '/' . (int)$idserver;
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return array(null, 0);
    $target = $dir . '/' . (int)$assetId . '.anatella';
    $rel    = 'lineage/anatella/' . (int)$idserver . '/' . (int)$assetId . '.anatella';

    $srcNorm = $srcPath !== null ? str_replace('/', DIRECTORY_SEPARATOR, $srcPath) : null;
    if ($srcNorm !== null && @is_readable($srcNorm)) {
        // atomic-ish: write to temp then rename
        $tmp = $target . '.part';
        if (@copy($srcNorm, $tmp)) {
            if (@rename($tmp, $target) || (@unlink($target) && @rename($tmp, $target))) {
                return array($rel, 0);
            }
            @unlink($tmp);
        }
    }
    if ($fallbackXml !== null && $fallbackXml !== '') {
        if (@file_put_contents($target, $fallbackXml) !== false) return array($rel, 1);
    }
    return array(null, 0);
}

/** Resolve a WorkflowMeta.pipelineXmlSrvPath to an absolute filesystem path. */
function lin_srvPathToDisk($relPath) {
    // stored as 'lineage/anatella/<idserver>/<id>.anatella', relative to marvim/
    return __DIR__ . '/../' . $relPath;
}
