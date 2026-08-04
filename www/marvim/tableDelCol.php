<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];
date_default_timezone_set('Europe/Brussels');

$params = $_REQUEST;
unset($params['advEdit']);
unset($params['idcol']);

// todo: validate that the user has the rights to delete column

$idcol=-1;
if (isset($_REQUEST['idcol'])) $idcol=$_REQUEST['idcol'];
else
{
	header('Location: table.php?advEdit=1&'.http_build_query($params));
	exit();
}

require_once '_pe_addEvent.php';

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
addEvent($db,$myid,'Delete','Columns',$idcol);

$stmt=$db->prepare('delete from columns WHERE id = :p1');
$stmt->bindValue(':p1',$idcol);
$stmt->execute();
$db->close();

header('Location: table.php?advEdit=1&'.http_build_query($params));
?>