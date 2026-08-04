<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];
date_default_timezone_set('Europe/Brussels');

$name="";
if (isset($_REQUEST['name'])) $name=$_REQUEST['name'];
else
{
	header("Location: report.php");
	exit();
}
$iddept="";
if (isset($_REQUEST['iddept'])) $iddept=$_REQUEST['iddept'];
else
{
	header("Location: report.php");
	exit();
}
date_default_timezone_set('Europe/Brussels');

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$idserver=$db->querySingle('select id from servers where serverType=\'Reporting\' limit 1');
$stmt=$db->prepare('insert into Assets(name,idDepartment,category,idowner,dateCreated,dateUpdated,status,popularity,rating,idserver) '.
	' values (:name,:iddept,1,:idowner,:mydate,:mydate,0,0,0,:idserver)' );
$stmt->bindValue(':name',$name);
$stmt->bindValue(':iddept',$iddept);
$stmt->bindValue(':idowner',$myid);
$stmt->bindValue(':mydate',date('Ymd H:i:s'));
$stmt->bindValue(':idserver',$idserver);
$stmt->execute();
$newId = $db->lastInsertRowID();

$stmt=$db->prepare('UPDATE departments SET n=n+1 WHERE id = :iddept');
$stmt->bindValue(':iddept', $iddept);
$stmt->execute();

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Add','Assets',$newId);

$db->close();
header('Location: oneReport.php?idasset='.$newId.'&newAsset=1');
?>