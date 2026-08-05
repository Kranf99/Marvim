<?php
// api_report.php - bulk create/update of Reports (Assets) and their KPIs.
//
// POST body: JSON, see example below. Auth: "Authorization: Bearer <token>" header,
// matched against users.apitoken in MarvimUsers.sqlite to identify the calling user.
//
// {"mode":"create","data":[{"category":1,"name":"myTestFrank","server":"Azure","department":"Sales",
//   "shortDescription":"simple basic report","longDescription":"this is a long description","kpi":[
//   {"name":"aa","description":"xxx"},{"name":"bb","description":"yyy"}]}]}
//
// mode "create": if the report (Asset) already exists, KPIs no longer listed are deleted.
// mode "update": if the report already exists, KPIs no longer listed are kept but their
//                shortDescription is prefixed with "Deleted on yyyyMMdd ".
// A report is considered to already exist when name+idserver both match an existing Asset.
//
// Optional fields (left untouched in the DB when absent from the JSON):
//   data[].shortDescription, data[].longDescription -> Assets table
//   data[].kpi[].description                        -> KPI.shortDescription
//
// data[].kpi itself is optional; when absent it is treated as an empty list, so any existing
// KPIs on the report are removed/soft-deleted per the mode rules above.

header('Content-Type: application/json');
date_default_timezone_set('Europe/Brussels');

function sendError($code, $msg)
{
    http_response_code($code);
    echo json_encode(array('error' => $msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError(405, 'Only POST is supported.');

// --- Authenticate via "Authorization: Bearer <token>" ---
$authHeader = '';
require __DIR__.'/_pe_checkBearerToken.php';

// --- Parse and validate the JSON payload ---
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) sendError(400, 'Invalid or missing JSON body.');

$mode = isset($payload['mode']) ? $payload['mode'] : '';
if ($mode !== 'create' && $mode !== 'update') sendError(400, 'mode must be "create" or "update".');

$data = (isset($payload['data']) && is_array($payload['data'])) ? $payload['data'] : null;
if ($data === null) sendError(400, '"data" must be an array.');

foreach ($data as $i => $entry)
{
    if (!isset($entry['category'])) sendError(400, 'data['.$i.'] is missing field "category".');
    if (!isset($entry['name']) || $entry['name'] === '') sendError(400, 'data['.$i.'] is missing field "name".');
    if (!isset($entry['server']) || $entry['server'] === '') sendError(400, 'data['.$i.'] is missing field "server".');
    if (!isset($entry['department'])) sendError(400, 'data['.$i.'] is missing field "department".');
    if (isset($entry['kpi']))
    {
        if (!is_array($entry['kpi'])) sendError(400, 'data['.$i.'].kpi must be an array.');
        foreach ($entry['kpi'] as $j => $kpi)
        {
            if (!is_array($kpi) || !isset($kpi['name']) || $kpi['name'] === '')
                sendError(400, 'data['.$i.'].kpi['.$j.'] must be an object with a "name" field.');
        }
    }
    $cat = (int)$entry['category'];
    if ($cat < 0 || $cat >= 100) sendError(400, 'data['.$i.'].category must be a Report category (0-99).');
}

$db = new SQLite3(__DIR__.'/../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
require_once __DIR__.'/_pe_addEvent.php';

// --- Resolve departments and check rights up-front, so nothing is written on a rejected request ---
$deptCache = array();
$res = $db->query('SELECT id, name FROM departments');
while ($row = $res->fetchArray(SQLITE3_ASSOC))
    $deptCache[$row['name']] = (int)$row['id'];

$rightsCache = array();
if (!$isSuperAdmin)
{
    $stmt = $db->prepare('SELECT idDepartment, rights FROM userDepartmentRights WHERE idUser=:u');
    $stmt->bindValue(':u', $myid);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC))
        $rightsCache[(int)$row['idDepartment']] = (int)$row['rights'];
}

// Preload every known server (keyed case-insensitively), then auto-create any server
// referenced in the payload that's missing.
$serverCache = array();
$res = $db->query('SELECT id, name FROM servers');
while ($row = $res->fetchArray(SQLITE3_ASSOC))
    $serverCache[strtolower($row['name'])] = (int)$row['id'];

$db->close();

$db = new SQLite3(__DIR__.'/../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

foreach ($data as $i => $entry)
{
    $deptName = $entry['department'];
    if (!array_key_exists($deptName, $deptCache))
    {
        $db->close();
        sendError(400, 'Unknown department "'.$deptName.'" (data['.$i.']).');
    }

    if (!$isSuperAdmin)
    {
        $rights = isset($rightsCache[$deptCache[$deptName]]) ? $rightsCache[$deptCache[$deptName]] : 0;
        if ($rights < 4)
        {
            $db->close();
            sendError(403, 'No creator rights in department "'.$deptName.'" (data['.$i.']).');
        }
    }
}

$results = array();
$today = date('Ymd');
$now = date('Ymd H:i:s');

foreach ($data as $entry)
{
    $serverName = $entry['server'];
    $serverKey = strtolower($serverName);
    if (array_key_exists($serverKey, $serverCache)) continue;

    $stmt = $db->prepare('INSERT INTO servers(name,serverType,idowner,dateCreated,dateUpdated) VALUES(:n,:st,:o,:c,:u)');
    $stmt->bindValue(':n', $serverName);
    $stmt->bindValue(':st', 'Reporting');
    $stmt->bindValue(':o', $myid);
    $stmt->bindValue(':c', $now);
    $stmt->bindValue(':u', $now);
    $stmt->execute();
    $serverCache[$serverKey] = (int)$db->lastInsertRowID();
    addEvent($db, $myid, 'Add', 'servers', $serverCache[$serverKey]);
}

foreach ($data as $entry)
{
    $category = (int)$entry['category'];
    $name = $entry['name'];
    $idDept = $deptCache[$entry['department']];
    $kpis = isset($entry['kpi']) ? $entry['kpi'] : array();
    $idserver = $serverCache[strtolower($entry['server'])];

    // A report is the "same" report when name+idserver match an existing Asset.
    $stmt = $db->prepare('SELECT id FROM Assets WHERE name=:n AND idserver=:s');
    $stmt->bindValue(':n', $name);
    $stmt->bindValue(':s', $idserver);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($row)
    {
        $idAsset = (int)$row['id'];
        $setSql = 'category=:cat, idDepartment=:dep, dateUpdated=:u';
        if (isset($entry['shortDescription'])) $setSql .= ', shortDescription=:sd';
        if (isset($entry['longDescription'])) $setSql .= ', longDescription=:ld';
        $stmt = $db->prepare('UPDATE Assets SET '.$setSql.' WHERE id=:id');
        $stmt->bindValue(':cat', $category);
        $stmt->bindValue(':dep', $idDept);
        $stmt->bindValue(':u', $now);
        if (isset($entry['shortDescription'])) $stmt->bindValue(':sd', $entry['shortDescription']);
        if (isset($entry['longDescription'])) $stmt->bindValue(':ld', $entry['longDescription']);
        $stmt->bindValue(':id', $idAsset);
        $stmt->execute();
        addEvent($db, $myid, 'Update', 'Assets', $idAsset);
        $action = 'updated';
    }
    else
    {
        $stmt = $db->prepare('INSERT INTO Assets(idDepartment,category,idserver,name,shortDescription,longDescription,status,popularity,rating,idowner,dateCreated,dateUpdated) '.
            'VALUES(:dep,:cat,:s,:n,:sd,:ld,0,0,0,:o,:c,:u)');
        $stmt->bindValue(':dep', $idDept);
        $stmt->bindValue(':cat', $category);
        $stmt->bindValue(':s', $idserver);
        $stmt->bindValue(':n', $name);
        $stmt->bindValue(':sd', isset($entry['shortDescription']) ? $entry['shortDescription'] : null);
        $stmt->bindValue(':ld', isset($entry['longDescription']) ? $entry['longDescription'] : null);
        $stmt->bindValue(':o', $myid);
        $stmt->bindValue(':c', $now);
        $stmt->bindValue(':u', $now);
        $stmt->execute();
        $idAsset = (int)$db->lastInsertRowID();

        $stmt = $db->prepare('UPDATE departments SET n=n+1 WHERE id=:id');
        $stmt->bindValue(':id', $idDept);
        $stmt->execute();

        addEvent($db, $myid, 'Add', 'Assets', $idAsset);
        $action = 'created';
    }

    // Existing KPIs for this Asset, keyed by name.
    $existingKpis = array();
    $stmt = $db->prepare('SELECT id, name, shortDescription FROM KPI WHERE idasset=:a');
    $stmt->bindValue(':a', $idAsset);
    $res = $stmt->execute();
    while ($r = $res->fetchArray(SQLITE3_ASSOC))
        $existingKpis[$r['name']] = $r;

    $jsonKpiNames = array();
    $added = 0;
    $removed = 0;

    foreach ($kpis as $kpi)
    {
        $kpiName = $kpi['name'];
        $jsonKpiNames[$kpiName] = true;
        if (isset($existingKpis[$kpiName]))
        {
            if (isset($kpi['description']))
            {
                $stmt = $db->prepare('UPDATE KPI SET shortDescription=:sd, dateUpdated=:u WHERE id=:id');
                $stmt->bindValue(':sd', $kpi['description']);
                $stmt->bindValue(':u', $now);
                $stmt->bindValue(':id', $existingKpis[$kpiName]['id']);
                $stmt->execute();
            }
            continue;
        }
        $stmt = $db->prepare('INSERT INTO KPI(idasset,name,shortDescription,status,popularity,rating,idowner,dateCreated,dateUpdated) '.
            'VALUES(:a,:n,:sd,0,0,0,:o,:c,:u)');
        $stmt->bindValue(':a', $idAsset);
        $stmt->bindValue(':n', $kpiName);
        $stmt->bindValue(':sd', isset($kpi['description']) ? $kpi['description'] : null);
        $stmt->bindValue(':o', $myid);
        $stmt->bindValue(':c', $now);
        $stmt->bindValue(':u', $now);
        $stmt->execute();
        $added++;
    }

    foreach ($existingKpis as $kpiName => $kpiRow)
    {
        if (isset($jsonKpiNames[$kpiName])) continue;
        if ($mode === 'create')
        {
            $stmt = $db->prepare('DELETE FROM KPI WHERE id=:id');
            $stmt->bindValue(':id', $kpiRow['id']);
            $stmt->execute();
        }
        else // update: soft-delete by tagging the shortDescription, unless already tagged
        {
            if (strpos((string)$kpiRow['shortDescription'], 'Deleted on ') === 0) continue;
            $newDesc = 'Deleted on '.$today.' '.$kpiRow['shortDescription'];
            $stmt = $db->prepare('UPDATE KPI SET shortDescription=:d, dateUpdated=:u WHERE id=:id');
            $stmt->bindValue(':d', $newDesc);
            $stmt->bindValue(':u', $now);
            $stmt->bindValue(':id', $kpiRow['id']);
            $stmt->execute();
        }
        $removed++;
    }

    $results[] = array('name'=>$name,'assetId'=>$idAsset,'action'=>$action,'kpiAdded'=>$added,'kpiRemoved'=>$removed);
}

$db->close();

echo json_encode(array('status'=>'ok','results'=>$results));
?>
