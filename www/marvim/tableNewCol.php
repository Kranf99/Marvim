<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];
$params = $_REQUEST;
unset($params['advEdit']);

// todo: validate that the user has the rights to create a column

$idasset="";
if (isset($_REQUEST['idasset'])) $idasset=$_REQUEST['idasset'];
else
{
	header('Location: table.php?advEdit=1&'.http_build_query($params));
	exit();
}
date_default_timezone_set('Europe/Brussels');

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$stmt=$db->prepare('insert into Columns(idasset,name,datatype,status,popularity,rating,idowner,dateCreated,dateUpdated) '.
	' values (:ida,\'_new_column_\',\'U\',0,0,0,:idowner,:mydate,:mydate)' );
$stmt->bindValue(':ida',$idasset);
$stmt->bindValue(':idowner',$myid);
$stmt->bindValue(':mydate',date('Ymd H:i:s'));
$stmt->execute();

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Add Column in','Assets',$idasset);

$db->close();
header('Location: table.php?advEdit=1&'.http_build_query($params));
?>