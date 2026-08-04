<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: ./index.html");
    exit;
}
$myid = $_SESSION['id'];

$name = '';
if (isset($_REQUEST['name'])) $name = $_REQUEST['name'];
else {
    header("Location: task.php");
    exit();
}

$priority = '';
if (isset($_REQUEST['priority'])) $priority = $_REQUEST['priority'];

$urgency  = 1;
$taskType = 100;
if     ($priority == 'High priority Workflows')   $urgency  = 3;
else if ($priority == 'Medium priority Workflows') $urgency  = 2;
else if ($priority == 'Low priority Workflows')    $urgency  = 1;
else if ($priority == 'Reviews')                 { $taskType = 500; $urgency = 0; }
else if ($priority == 'Compute Governance KPI')  { $taskType = 258; $urgency = 0; }

date_default_timezone_set('Europe/Brussels');

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$stmt = $db->prepare(
    'INSERT INTO Tasks(name,assignedToUserId,requestedByUserId,dateCreated,dateUpdated,status,urgency,taskType,completion,rating)'.
    ' VALUES (:name,:myid,:myid,:mydate,:mydate,0,:urgency,:taskType,0,0)');
$stmt->bindValue(':name',     $name);
$stmt->bindValue(':myid',     $myid);
$stmt->bindValue(':mydate',   date('Ymd H:i:s'));
$stmt->bindValue(':urgency',  $urgency);
$stmt->bindValue(':taskType', $taskType);
$stmt->execute();
$newId = $db->lastInsertRowID();

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Add','Tasks',$newId);

$db->close();
header('Location: oneTask.php?idasset='.$newId.'&newAsset=1');
?>
