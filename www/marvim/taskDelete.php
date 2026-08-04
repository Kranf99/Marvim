<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];
date_default_timezone_set('Europe/Brussels');

$ida=-1;
if (isset($_REQUEST['idasset'])) $ida=(int)$_REQUEST['idasset'];
else
{
	header("Location: task.php");
	exit();
}
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

$stmtTask = $db->prepare('SELECT changeId, changeTable, taskType FROM Tasks WHERE id = :ida');
$stmtTask->bindValue(':ida', $ida, SQLITE3_INTEGER);
$task = $stmtTask->execute()->fetchArray(SQLITE3_ASSOC);
if ($task && !empty($task['changeId']))
{
    $changeId    = (int)$task['changeId'];
    $changedTable = $task['changeTable'];

    $stmt = $db->prepare('DELETE FROM '.$changedTable.'Changes WHERE changeId = :cid');
    $stmt->bindValue(':cid', $changeId);
    $stmt->execute();

    if ($changedTable === 'Assets')
    {
	    $tt=(int)$task['taskType'];
        if (($tt == 510) || ($tt == 610))
        {
            $stmt = $db->prepare('DELETE FROM KPIChanges WHERE fromAssetChangeId = :cid');
            $stmt->bindValue(':cid', $changeId);
            $stmt->execute();
        } else if (($tt == 520) || ($tt == 620))
        {
            $stmt = $db->prepare('DELETE FROM ColumnsChanges WHERE fromAssetChangeId = :cid');
            $stmt->bindValue(':cid', $changeId);
            $stmt->execute();
        }
    }
}

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Delete','Tasks',$ida);

$stmt = $db->prepare('DELETE FROM likesTasks WHERE idassetorcolumn = :p1');
$stmt->bindValue(':p1', $ida);
$stmt->execute();
$stmt = $db->prepare('DELETE FROM Tasks WHERE id = :p1');
$stmt->bindValue(':p1', $ida);
$stmt->execute();
$db->close();
header("Location: task.php");
?>
