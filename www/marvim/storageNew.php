<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];

$tablename="";
if (isset($_REQUEST['tablename'])) $tablename=$_REQUEST['tablename'];
else
{
	header("Location: storage.php");
	exit();
}
$category=0;
if (isset($_REQUEST['type'])) $category=$_REQUEST['type'];
else
{
	header("Location: storage.php");
	exit();
}
$idDepartment=0;
if (isset($_REQUEST['idDpt'])) $idDepartment=$_REQUEST['idDpt'];
else
{
	header("Location: storage.php");
	exit();
}
date_default_timezone_set('Europe/Brussels');

// todo: validate that the user has the rights to add a new storage

if ($category<120) $serverType="Files";
else if ($category<140) $serverType="Data Bases";
else if ($category<160) $serverType="Applications";
else $serverType="APIs";

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$idserver=$db->querySingle('select id from servers where serverType=\''.$serverType.'\' limit 1');
$stmt=$db->prepare('insert into Assets(name,idDepartment,category,idowner,dateCreated,dateUpdated,status,popularity,rating,idserver) '.
	' values (:name,:idDepartment,:category,:idowner,:mydate,:mydate,0,0,0,:idserver)' );
$stmt->bindValue(':name',$tablename);
$stmt->bindValue(':idDepartment',$idDepartment);
$stmt->bindValue(':category',$category);
$stmt->bindValue(':idowner',$myid);
$stmt->bindValue(':mydate',date('Ymd H:i:s'));
$stmt->bindValue(':idserver',$idserver);
$stmt->execute();
$newId = $db->lastInsertRowID();

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Add','Assets',$newId);

$db->close();
header('Location: table.php?idasset='.$newId.'&newAsset=1');
?>