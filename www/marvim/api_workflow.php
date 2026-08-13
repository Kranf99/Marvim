<?php
// api_workflow.php - bulk create/update of Workflow Assets and their input/output links (workflowIO).
//
// POST body: JSON, see example below. Auth: "Authorization: Bearer <token>" header,
// matched against users.apitoken in MarvimUsers.sqlite to identify the calling user.
//
// {"mode":"create","data":[{"category":200,"schema":"F:/TIMi/marvim/graphsToSendTomarvim/","name":"csv.anatella",
//   "server":"THUNDERBOLT","department":"Sales",
//   "IO":[{"name":"census-income.zip","schema":"F:/","server":"THUNDERBOLT","direction":1},
//         {"name":"census-income-clean.csv","schema":"F:/","server":"THUNDERBOLT","direction":0}]}]}
//
// Each entry in "IO" is an existing Asset linked to the workflow via the workflowIO table;
// "direction":1 means the Asset is an input of the workflow, "direction":0 means it's an output.
//
// mode "create": if the workflow (Asset) already exists, IO links no longer listed are deleted.
// mode "update": if the workflow already exists, IO links no longer listed are kept.
// A workflow is considered to already exist when name+idserver+schema all match an existing Asset.
//
// Every Asset referenced in "IO" must already exist (matched by name+server+schema) -
// this endpoint does not create Storage Assets, use api_storage.php for that first.
//
// Optional fields (left untouched in the DB when absent from the JSON):
//   data[].shortDescription, data[].longDescription -> Assets table
//
// Optional field data[].file: base64-encoded content of the .anatella pipeline file. When
// present, it is base64-decoded and saved under lineage/anatella/<datetime>.anatella, and a
// row is (re)created in WorkflowMeta with pipelineXmlSrvPath set to that relative path.

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

// Decoded data[].file content, keyed by entry index, when that optional field is present.
$decodedFiles = array();

foreach ($data as $i => $entry)
{
    if (!isset($entry['category'])) sendError(400, 'data['.$i.'] is missing field "category".');
    if (!isset($entry['schema'])) sendError(400, 'data['.$i.'] is missing field "schema".');
    if (!isset($entry['name'])) sendError(400, 'data['.$i.'] is missing field "name".');
    if (!isset($entry['server'])) sendError(400, 'data['.$i.'] is missing field "server".');
    if (!isset($entry['department'])) sendError(400, 'data['.$i.'] is missing field "department".');
    if (!isset($entry['IO'])) sendError(400, 'data['.$i.'] is missing field "IO".');
    if (!is_array($entry['IO'])) sendError(400, 'data['.$i.'].IO must be an array.');
    foreach ($entry['IO'] as $j => $io)
    {
        if (!is_array($io)) sendError(400, 'data['.$i.'].IO['.$j.'] must be an object.');
        if (!isset($io['schema'])) sendError(400, 'data['.$i.'].IO['.$j.'] is missing field "schema".');
        if (!isset($io['name']) || $io['name'] === '') sendError(400, 'data['.$i.'].IO['.$j.'] is missing field "name".');
        if (!isset($io['server']) || $io['server'] === '') sendError(400, 'data['.$i.'].IO['.$j.'] is missing field "server".');
        if (!isset($io['direction']) || ($io['direction'] != 0 && $io['direction'] != 1))
            sendError(400, 'data['.$i.'].IO['.$j.'].direction must be 1 (input) or 0 (output).');
    }
    $cat = (int)$entry['category'];
    if ($cat < 200) sendError(400, 'data['.$i.'].category must be a Workflow category (>=200).');

    if (isset($entry['file']))
    {
        if (!is_string($entry['file']) || $entry['file'] === '')
            sendError(400, 'data['.$i.'].file must be a non-empty base64-encoded string.');
        $decoded = base64_decode($entry['file'], true);
        if ($decoded === false) sendError(400, 'data['.$i.'].file is not valid base64.');
        $decodedFiles[$i] = $decoded;
    }
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

// Preload every known server (keyed case-insensitively), used to resolve the servers referenced
// by inputs/outputs. Those may be of any serverType, since IO Assets can live on Storage servers
// of any kind - so this cache is intentionally not restricted by serverType.

// Also Preload only "Workflow" servers (keyed case-insensitively on name), used to resolve/auto-create
// the workflow's own server. Restricted to serverType='Workflow' so a name shared with a server
// of another type (e.g. a "Files" server) isn't mistaken for the workflow server of the same name.
$serverCache = array();
$workflowServerCache = array();
$res = $db->query('SELECT id, name, serverType FROM servers');
while ($row = $res->fetchArray(SQLITE3_ASSOC))
{
    $serverCache[strtolower($row['name'])] = (int)$row['id'];
    if ($row['serverType']=='Workflow')
        $workflowServerCache[strtolower($row['name'])] = (int)$row['id'];
}

// Resolve every input/output reference to an existing Asset id, and check department rights,
// so nothing is written to the DB on a rejected request.
$resolvedIO = array();
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

    foreach ($entry['IO'] as $j => $io)
    {
        $serverKey = strtolower($io['server']);
        if (!array_key_exists($serverKey, $serverCache))
        {
            $db->close();
            sendError(400, 'Unknown server "'.$io['server'].'" referenced by data['.$i.'].IO['.$j.'].');
        }

        $stmt = $db->prepare('SELECT id FROM Assets WHERE name=:n AND idserver=:s AND schema=:sc');
        $stmt->bindValue(':n', $io['name']);
        $stmt->bindValue(':s', $serverCache[$serverKey]);
        $stmt->bindValue(':sc', $io['schema']);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row)
        {
            $db->close();
            sendError(400, 'data['.$i.'].IO['.$j.'] references an unknown Asset (name="'.$io['name'].
                '", server="'.$io['server'].'", schema="'.$io['schema'].'"). Register it first, e.g. via api_storage.php.');
        }
        $resolvedIO[$i][$j] = (int)$row['id'];
    }
}

$db->close();

$db = new SQLite3(__DIR__.'/../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

$results = array();
$now = date('Ymd H:i:s');

// Auto-create the workflow's own server, if missing.
foreach ($data as $entry)
{
    $serverName = $entry['server'];
    $serverKey = strtolower($serverName);
    if (array_key_exists($serverKey, $workflowServerCache)) continue;

    $stmt = $db->prepare('INSERT INTO servers(name,serverType,idowner,dateCreated,dateUpdated) VALUES(:n,:st,:o,:c,:u)');
    $stmt->bindValue(':n', $serverName);
    $stmt->bindValue(':st', 'Workflow');
    $stmt->bindValue(':o', $myid);
    $stmt->bindValue(':c', $now);
    $stmt->bindValue(':u', $now);
    $stmt->execute();
    $workflowServerCache[$serverKey] = (int)$db->lastInsertRowID();
    addEvent($db, $myid, 'Add', 'servers', $workflowServerCache[$serverKey]);
}

foreach ($data as $i => $entry)
{
    $category = (int)$entry['category'];
    $schema = $entry['schema'];
    $name = $entry['name'];
    $idDept = $deptCache[$entry['department']];
    $idserver = $workflowServerCache[strtolower($entry['server'])];

    // A workflow is the "same" Asset when name+idserver+schema match an existing row.
    $stmt = $db->prepare('SELECT id FROM Assets WHERE name=:n AND idserver=:s AND schema=:sc');
    $stmt->bindValue(':n', $name);
    $stmt->bindValue(':s', $idserver);
    $stmt->bindValue(':sc', $schema);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($row)
    {
        $idWorkflow = (int)$row['id'];
        $setSql = 'category=:cat, idDepartment=:dep, dateUpdated=:u';
        if (isset($entry['shortDescription'])) $setSql .= ', shortDescription=:sd';
        if (isset($entry['longDescription'])) $setSql .= ', longDescription=:ld';
        $stmt = $db->prepare('UPDATE Assets SET '.$setSql.' WHERE id=:id');
        $stmt->bindValue(':cat', $category);
        $stmt->bindValue(':dep', $idDept);
        $stmt->bindValue(':u', $now);
        if (isset($entry['shortDescription'])) $stmt->bindValue(':sd', $entry['shortDescription']);
        if (isset($entry['longDescription'])) $stmt->bindValue(':ld', $entry['longDescription']);
        $stmt->bindValue(':id', $idWorkflow);
        $stmt->execute();
        addEvent($db, $myid, 'Update', 'Assets', $idWorkflow);
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
        $idWorkflow = (int)$db->lastInsertRowID();
        addEvent($db, $myid, 'Add', 'Assets', $idWorkflow);
        $action = 'created';
    }

    if (isset($decodedFiles[$i]))
    {
        $fileDatetime = DateTime::createFromFormat('U.u', microtime(true))->format('Ymd_His_u');
        $relPath = 'lineage/anatella/'.$fileDatetime.'.anatella';
        $targetDir = __DIR__.'/lineage/anatella';
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true))
        {
            $db->close();
            sendError(500, 'Could not create directory "'.$targetDir.'" (data['.$i.']).');
        }
        if (@file_put_contents($targetDir.'/'.$fileDatetime.'.anatella', $decodedFiles[$i]) === false)
        {
            $db->close();
            sendError(500, 'Could not write pipeline file for data['.$i.'].');
        }

        $stmt = $db->prepare('DELETE FROM WorkflowMeta WHERE idAsset=:id');
        $stmt->bindValue(':id', $idWorkflow);
        $stmt->execute();

        $stmt = $db->prepare('INSERT INTO WorkflowMeta(idAsset,pipelineXmlSrvPath,extractedAt) VALUES(:id,:p,:e)');
        $stmt->bindValue(':id', $idWorkflow);
        $stmt->bindValue(':p', $relPath);
        $stmt->bindValue(':e', $now);
        $stmt->execute();
    }

    // Existing input/output links for this workflow, keyed by "idIO:isInput".
    $existingIO = array();
    $stmt = $db->prepare('SELECT id, idIO, isInput FROM workflowIO WHERE idWorkflow=:w');
    $stmt->bindValue(':w', $idWorkflow);
    $res = $stmt->execute();
    while ($r = $res->fetchArray(SQLITE3_ASSOC))
        $existingIO[$r['idIO'].':'.$r['isInput']] = $r;

    $jsonIOKeys = array();
    $added = 0;
    $removed = 0;

    foreach ($entry['IO'] as $j => $io)
    {
        $idIO = $resolvedIO[$i][$j];
        $isInput = (int)$io['direction'];
        $key = $idIO.':'.$isInput;
        $jsonIOKeys[$key] = true;
        if (isset($existingIO[$key])) continue;

        $stmt = $db->prepare('INSERT INTO workflowIO(idWorkflow,idIO,isInput) VALUES(:w,:io,:isi)');
        $stmt->bindValue(':w', $idWorkflow);
        $stmt->bindValue(':io', $idIO);
        $stmt->bindValue(':isi', $isInput);
        $stmt->execute();
        $added++;
    }

    if ($mode === 'create')
    {
        foreach ($existingIO as $key => $ioRow)
        {
            if (isset($jsonIOKeys[$key])) continue;
            $stmt = $db->prepare('DELETE FROM workflowIO WHERE id=:id');
            $stmt->bindValue(':id', $ioRow['id']);
            $stmt->execute();
            $removed++;
        }
    }

    if ($added > 0 || $removed > 0) addEvent($db, $myid, 'Update', 'Assets', $idWorkflow);

    $results[] = array('name'=>$name,'assetId'=>$idWorkflow,'action'=>$action,'ioAdded'=>$added,'ioRemoved'=>$removed);
}

$db->close();

echo json_encode(array('status'=>'ok','results'=>$results));
?>
