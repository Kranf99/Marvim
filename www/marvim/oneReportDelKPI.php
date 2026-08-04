<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$params = $_REQUEST;
unset($params['idkpi']);
unset($params['edit']);

$myid= $_SESSION['id'];

// todo: validate that the user has the rights to delete kpi

$idkpi=-1;
if (isset($_REQUEST['idkpi'])) $idkpi=$_REQUEST['idkpi'];
else
{
	header('Location: oneReport.php?edit=1&'.http_build_query($params));
	exit();
}
require_once '_pe_addEvent.php';

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
addEvent($db,$myid,'Change ','KPI',$idkpi);

$stmt=$db->prepare('delete from KPI WHERE id = :p1');
$stmt->bindValue(':p1',$idkpi);
$stmt->execute();
$db->close();

header('Location: oneReport.php?edit=1&'.http_build_query($params));
?>