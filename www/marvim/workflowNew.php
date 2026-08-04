<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];

$name="";
if (isset($_REQUEST['name'])) $name=$_REQUEST['name'];
else
{
	header("Location: workflow.php");
	exit();
}
date_default_timezone_set('Europe/Brussels');

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$idserver=$db->querySingle('select id from servers where serverType=\'Workflow\' limit 1');

$stmt=$db->prepare('insert into Assets(name,idowner,dateCreated,dateUpdated,idserver,category,status,popularity,rating) '.
	' values (:name,:idowner,:mydate,:mydate,:idserver,300,0,0,0)' );
$stmt->bindValue(':name',$name);
$stmt->bindValue(':idowner',$myid);
$stmt->bindValue(':mydate',date('Ymd H:i:s'));
$stmt->bindValue(':idserver',$idserver);
$stmt->execute();
$newId = $db->lastInsertRowID();

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Add','Assets',$newId);

$db->close();
header('Location: oneWorkflow.php?idasset='.$newId.'&newAsset=1');
?>