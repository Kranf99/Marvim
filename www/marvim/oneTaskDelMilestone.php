<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$params = $_REQUEST;
unset($params['idmilestone']);
unset($params['edit']);

$idmilestone=-1;
if (isset($_REQUEST['idmilestone'])) $idmilestone=$_REQUEST['idmilestone'];
else
{
	header('Location: oneTask.php?edit=1&'.http_build_query($params));
	exit();
}
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$stmt=$db->prepare('delete from Milestones WHERE id = :p1');
$stmt->bindValue(':p1',$idmilestone);
$stmt->execute();
$db->close();

header('Location: oneTask.php?edit=1&'.http_build_query($params));
?>