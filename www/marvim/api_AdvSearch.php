<?php
// --- Authentication ---
// If an "Authorization" header is present, authenticate via Bearer token (same as
// api_report.php). Otherwise, fall back to the regular session-based authentication.
$authHeader = '';
if (function_exists('getallheaders'))
{
    foreach (getallheaders() as $k => $v)
        if (strcasecmp($k, 'Authorization') === 0) { $authHeader = $v; break; }
}
if ($authHeader === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
if ($authHeader === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
$authHeader=trim($authHeader);
if ($authHeader === '')
    require __DIR__.'/_pe_apiAuth.php';
else
    require __DIR__.'/_pe_checkBearerToken.php';

$q = isset($_REQUEST['q']) ? trim($_REQUEST['q']) : '';
if ($q === '') {
    echo '[]';
    exit;
}

$searchReport = isset($_REQUEST['report']) && $_REQUEST['report']=='1';
$searchStorage = isset($_REQUEST['storage']) && $_REQUEST['storage']=='1';
$searchWorkflow = isset($_REQUEST['workflow']) && $_REQUEST['workflow']=='1';
$searchGlossary = isset($_REQUEST['glossary']) && $_REQUEST['glossary']=='1';
$searchTasks = isset($_REQUEST['tasks']) && $_REQUEST['tasks']=='1';
$searchName = isset($_REQUEST['name']) && $_REQUEST['name']=='1';
$searchShortDescription = isset($_REQUEST['shortDescription']) && $_REQUEST['shortDescription']=='1';
$searchColumns = isset($_REQUEST['columns']) && $_REQUEST['columns']=='1';

$db = new SQLite3(__DIR__.'/../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);

function getAssetIcon($cat)
{
    if ($cat < 100) return 'ressources/reports.svg';
    if ($cat < 120) return 'ressources/file.svg';
    if ($cat < 200) return 'ressources/database.svg';
    return 'ressources/anatella.svg';
}

echo '[';
$first = true;

// --- Assets: Reports / Storage / Workflows ---
$catFilter = array();
if ($searchReport) $catFilter[] = '(a.category<100)';
if ($searchStorage) $catFilter[] = '(a.category>=100 AND a.category<200)';
if ($searchWorkflow) $catFilter[] = '(a.category>=200)';

$textFilter = array();
if ($searchName) $textFilter[] = 'a.name LIKE :q';
if ($searchShortDescription) $textFilter[] = 'a.shortDescription LIKE :q';

if (count($catFilter)>0 && count($textFilter)>0) {
    $catClause = '('.implode(' OR ', $catFilter).')';
    $textClause = '('.implode(' OR ', $textFilter).')';
    if ($isSuperAdmin)
        $sql='SELECT a.id, a.name, a.shortDescription, a.category from Assets a'.
            ' where '.$catClause.' AND '.$textClause.
            ' ORDER BY a.popularity DESC LIMIT 30';
    else
        $sql='SELECT a.id, a.name, a.shortDescription, a.category from Assets a'.
            ' INNER JOIN userDepartmentRights ud ON a.idDepartment=ud.idDepartment'.
            ' where ud.idUser=:myid AND '.$catClause.' AND '.$textClause.
            ' ORDER BY a.popularity DESC LIMIT 30';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':q', '%'.$q.'%');
    if (!$isSuperAdmin) $stmt->bindValue(':myid', $myid);
    $results = $stmt->execute();
    while (1) {
        $row = $results->fetchArray(SQLITE3_ASSOC);
        if (!$row) break;
        $cat = $row['category'];
        if ($cat < 100) { $type='Report'; $url='oneReport.php?idasset='.$row['id']; }
        else if ($cat < 200) { $type='Storage'; $url='table.php?idasset='.$row['id']; }
        else { $type='Workflow'; $url='oneWorkflow.php?idasset='.$row['id']; }
        if (!$first) echo ',';
        $first = false;
        echo '{"id":'.json_encode($row['id']).
            ',"type":'.json_encode($type).
            ',"name":'.json_encode($row['name']).
            ',"shortDescription":'.json_encode($row['shortDescription']).
            ',"icon":'.json_encode(getAssetIcon($cat)).
            ',"url":'.json_encode($url).'}';
    }
}

// --- Columns (only when searching Storage) ---
if ($searchStorage && $searchColumns && count($textFilter)>0) {
    $colTextFilter = array();
    if ($searchName) $colTextFilter[] = 'c.name LIKE :q';
    if ($searchShortDescription) $colTextFilter[] = 'c.shortDescription LIKE :q';
    $colTextClause = '('.implode(' OR ', $colTextFilter).')';
    if ($isSuperAdmin)
        $sql='SELECT c.id, c.name, c.shortDescription, c.idasset from Columns c'.
            ' where '.$colTextClause.
            ' ORDER BY c.popularity DESC LIMIT 30';
    else
        $sql='SELECT c.id, c.name, c.shortDescription, c.idasset from Columns c'.
            ' INNER JOIN Assets a ON a.id=c.idasset'.
            ' INNER JOIN userDepartmentRights ud ON a.idDepartment=ud.idDepartment'.
            ' where ud.idUser=:myid AND '.$colTextClause.
            ' ORDER BY c.popularity DESC LIMIT 30';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':q', '%'.$q.'%');
    if (!$isSuperAdmin) $stmt->bindValue(':myid', $myid);
    $results = $stmt->execute();
    while (1) {
        $row = $results->fetchArray(SQLITE3_ASSOC);
        if (!$row) break;
        if (!$first) echo ',';
        $first = false;
        echo '{"id":'.json_encode($row['id']).
            ',"type":'.json_encode('Column').
            ',"name":'.json_encode($row['name']).
            ',"shortDescription":'.json_encode($row['shortDescription']).
            ',"icon":'.json_encode('ressources/columns.svg').
            ',"url":'.json_encode('table.php?idasset='.$row['idasset']).'}';
    }
}

// --- Glossary ---
if ($searchGlossary && count($textFilter)>0) {
    $glTextFilter = array();
    if ($searchName) $glTextFilter[] = 'g.name LIKE :q';
    if ($searchShortDescription) $glTextFilter[] = 'g.shortDescription LIKE :q';
    $glTextClause = '('.implode(' OR ', $glTextFilter).')';
    $sql='SELECT g.id, g.name, g.shortDescription from Glossary g'.
        ' where g.toDelete=0 AND '.$glTextClause.
        ' ORDER BY g.popularity DESC LIMIT 30';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':q', '%'.$q.'%');
    $results = $stmt->execute();
    while (1) {
        $row = $results->fetchArray(SQLITE3_ASSOC);
        if (!$row) break;
        if (!$first) echo ',';
        $first = false;
        echo '{"id":'.json_encode($row['id']).
            ',"type":'.json_encode('Glossary').
            ',"name":'.json_encode($row['name']).
            ',"shortDescription":'.json_encode($row['shortDescription']).
            ',"icon":'.json_encode('ressources/glossary.svg').
            ',"url":'.json_encode('glossaryOneDef.php?idasset='.$row['id']).'}';
    }
}

// --- Tasks (only the Name field exists on this table) ---
if ($searchTasks && $searchName) {
    $sql='SELECT t.id, t.name from Tasks t'.
        ' where t.assignedToUserId=:myid AND t.name LIKE :q'.
        ' ORDER BY t.rating DESC LIMIT 30';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':q', '%'.$q.'%');
    $stmt->bindValue(':myid', $myid);
    $results = $stmt->execute();
    while (1) {
        $row = $results->fetchArray(SQLITE3_ASSOC);
        if (!$row) break;
        if (!$first) echo ',';
        $first = false;
        echo '{"id":'.json_encode($row['id']).
            ',"type":'.json_encode('Task').
            ',"name":'.json_encode($row['name']).
            ',"shortDescription":'.json_encode('').
            ',"icon":'.json_encode('ressources/tasks.svg').
            ',"url":'.json_encode('oneTask.php?idasset='.$row['id']).'}';
    }
}

echo ']';
$db->close();
