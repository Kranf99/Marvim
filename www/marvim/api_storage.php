<?php
// api_storage.php - bulk create/update of Storage Assets (tables) and their Columns.
//
// POST body: JSON, see example below. Auth: "Authorization: Bearer <token>" header,
// matched against users.apitoken in MarvimUsers.sqlite to identify the calling user.
//
// {"mode":"create","data":[{"category":102,"schema":"F:/","name":"census-income.zip",
//   "server":"THUNDERBOLT","department":"Sales","columns":[{"name":"age"},{"name":"class of worker"}, ...]}]}
//
// mode "create": if the table (Asset) already exists, columns no longer listed are deleted.
// mode "update": if the table already exists, columns no longer listed are kept but their
//                shortDescription is prefixed with "Deleted on yyyyMMdd ".
// mode "add":    if the table already exists, columns no longer listed are kept untouched
//                and new columns are added.
// A table is considered to already exist when name+idserver+schema all match an existing Asset.
//
// Optional fields (left untouched in the DB when absent from the JSON):
//   data[].shortDescription, data[].longDescription           -> Assets table
//   data[].columns[].shortDescription, .completeness, .cleanliness -> Columns table

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
if ($mode !== 'create' && $mode !== 'update' && $mode !== 'add') sendError(400, 'mode must be "create", "update" or "add".');

$data = (isset($payload['data']) && is_array($payload['data'])) ? $payload['data'] : null;
if ($data === null) sendError(400, '"data" must be an array.');

foreach ($data as $i => $entry)
{
    if (!isset($entry['category'])) sendError(400, 'data['.$i.'] is missing field "category".');
    if (!isset($entry['schema'])) sendError(400, 'data['.$i.'] is missing field "schema".');
    if (!isset($entry['name'])) sendError(400, 'data['.$i.'] is missing field "name".');
    if (!isset($entry['server'])) sendError(400, 'data['.$i.'] is missing field "server".');
    if (!isset($entry['department'])) sendError(400, 'data['.$i.'] is missing field "department".');
    if (!isset($entry['columns'])) sendError(400, 'data['.$i.'] is missing field "columns".');
    if (!is_array($entry['columns'])) sendError(400, 'data['.$i.'].columns must be an array.');
    foreach ($entry['columns'] as $j => $col)
    {
        if (!is_array($col) || !isset($col['name']) || $col['name'] === '')
            sendError(400, 'data['.$i.'].columns['.$j.'] must be an object with a "name" field.');
        if (isset($col['completeness']) && !is_numeric($col['completeness']))
            sendError(400, 'data['.$i.'].columns['.$j.'].completeness must be numeric.');
        if (isset($col['cleanliness']) && !is_numeric($col['cleanliness']))
            sendError(400, 'data['.$i.'].columns['.$j.'].cleanliness must be numeric.');
    }
    $cat = (int)$entry['category'];
    if ($cat < 100 || $cat > 199) sendError(400, 'data['.$i.'].category must be a Storage category (100-199).');
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

// Preload every known server (keyed case-insensitively on name+serverType, since the same
// name can be reused across servers of different types), then auto-create any server
// referenced in the payload that's missing.
$serverCache = array();
$res = $db->query('SELECT id, name, serverType FROM servers');
while ($row = $res->fetchArray(SQLITE3_ASSOC))
    $serverCache[strtolower($row['name']).'|'.strtolower($row['serverType'])] = (int)$row['id'];

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

function serverTypeForCategory($category)
{
    if ($category < 120) return 'Files';
    if ($category < 140) return 'Data Bases';
    if ($category < 160) return 'Applications';
    return "API's";
}

//$db->exec('BEGIN');
$results = array();
$today = date('Ymd');
$now = date('Ymd H:i:s');

foreach ($data as $entry)
{
    $serverName = $entry['server'];
    $serverType = serverTypeForCategory((int)$entry['category']);
    $serverKey = strtolower($serverName).'|'.strtolower($serverType);
    if (array_key_exists($serverKey, $serverCache)) continue;

    $stmt = $db->prepare('INSERT INTO servers(name,serverType,idowner,dateCreated,dateUpdated) VALUES(:n,:st,:o,:c,:u)');
    $stmt->bindValue(':n', $serverName);
    $stmt->bindValue(':st', $serverType);
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
    $schema = $entry['schema'];
    $name = $entry['name'];
    $idDept = $deptCache[$entry['department']];
    $columns = $entry['columns'];
    $idserver = $serverCache[strtolower($entry['server']).'|'.strtolower(serverTypeForCategory($category))];

    // A table is the "same" table when name+idserver+schema match an existing Asset.
    $stmt = $db->prepare('SELECT id FROM Assets WHERE name=:n AND idserver=:s AND schema=:sc');
    $stmt->bindValue(':n', $name);
    $stmt->bindValue(':s', $idserver);
    $stmt->bindValue(':sc', $schema);
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
        $stmt = $db->prepare('INSERT INTO Assets(idDepartment,category,schema,idserver,name,shortDescription,longDescription,status,popularity,rating,idowner,dateCreated,dateUpdated) '.
            'VALUES(:dep,:cat,:sc,:s,:n,:sd,:ld,0,0,0,:o,:c,:u)');
        $stmt->bindValue(':dep', $idDept);
        $stmt->bindValue(':cat', $category);
        $stmt->bindValue(':sc', $schema);
        $stmt->bindValue(':s', $idserver);
        $stmt->bindValue(':n', $name);
        $stmt->bindValue(':sd', isset($entry['shortDescription']) ? $entry['shortDescription'] : null);
        $stmt->bindValue(':ld', isset($entry['longDescription']) ? $entry['longDescription'] : null);
        $stmt->bindValue(':o', $myid);
        $stmt->bindValue(':c', $now);
        $stmt->bindValue(':u', $now);
        $stmt->execute();
        $idAsset = (int)$db->lastInsertRowID();
        addEvent($db, $myid, 'Add', 'Assets', $idAsset);
        $action = 'created';
    }

    // Existing columns for this Asset, keyed by name.
    $existingCols = array();
    $stmt = $db->prepare('SELECT id, name, shortDescription FROM Columns WHERE idasset=:a');
    $stmt->bindValue(':a', $idAsset);
    $res = $stmt->execute();
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) 
        $existingCols[$r['name']] = $r;

    $jsonColNames = array();
    $added = 0;
    $removed = 0;

    foreach ($columns as $col)
    {
        $colName = $col['name'];
        $jsonColNames[$colName] = true;
        if (isset($existingCols[$colName]))
        {
            if (isset($col['shortDescription']) || isset($col['completeness']) || isset($col['cleanliness']))
            {
                $setSql = array('dateUpdated=:u');
                if (isset($col['shortDescription'])) $setSql[] = 'shortDescription=:sd';
                if (isset($col['completeness'])) $setSql[] = 'completeness=:cp';
                if (isset($col['cleanliness'])) $setSql[] = 'cleanliness=:cl';
                $stmt = $db->prepare('UPDATE Columns SET '.implode(', ', $setSql).' WHERE id=:id');
                $stmt->bindValue(':u', $now);
                if (isset($col['shortDescription'])) $stmt->bindValue(':sd', $col['shortDescription']);
                if (isset($col['completeness'])) $stmt->bindValue(':cp', $col['completeness']);
                if (isset($col['cleanliness'])) $stmt->bindValue(':cl', $col['cleanliness']);
                $stmt->bindValue(':id', $existingCols[$colName]['id']);
                $stmt->execute();
            }
            continue;
        }
        $stmt = $db->prepare('INSERT INTO Columns(idasset,name,shortDescription,completeness,cleanliness,status,popularity,rating,idowner,dateCreated,dateUpdated) '.
            'VALUES(:a,:n,:sd,:cp,:cl,0,0,0,:o,:c,:u)');
        $stmt->bindValue(':a', $idAsset);
        $stmt->bindValue(':n', $colName);
        $stmt->bindValue(':sd', isset($col['shortDescription']) ? $col['shortDescription'] : null);
        $stmt->bindValue(':cp', isset($col['completeness']) ? $col['completeness'] : null);
        $stmt->bindValue(':cl', isset($col['cleanliness']) ? $col['cleanliness'] : null);
        $stmt->bindValue(':o', $myid);
        $stmt->bindValue(':c', $now);
        $stmt->bindValue(':u', $now);
        $stmt->execute();
        $added++;
    }

    foreach ($existingCols as $colName => $colRow)
    {
        if (isset($jsonColNames[$colName])) continue;
        if ($mode === 'create')
        {
            $stmt = $db->prepare('DELETE FROM Columns WHERE id=:id');
            $stmt->bindValue(':id', $colRow['id']);
            $stmt->execute();
        }
        else if ($mode === 'update') // soft-delete by tagging the shortDescription, unless already tagged
        {
            if (strpos((string)$colRow['shortDescription'], 'Deleted on ') === 0) continue;
            $newDesc = 'Deleted on '.$today.' '.$colRow['shortDescription'];
            $stmt = $db->prepare('UPDATE Columns SET shortDescription=:d, dateUpdated=:u WHERE id=:id');
            $stmt->bindValue(':d', $newDesc);
            $stmt->bindValue(':u', $now);
            $stmt->bindValue(':id', $colRow['id']);
            $stmt->execute();
        }
        else continue; // add: leave untouched, don't count as removed
        $removed++;
    }

    $results[] = array('name'=>$name,'assetId'=>$idAsset,'action'=>$action,'columnsAdded'=>$added,'columnsRemoved'=>$removed);
}

//$db->exec('COMMIT');
$db->close();

echo json_encode(array('status'=>'ok','results'=>$results));
?>
