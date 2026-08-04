<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];

$db = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$resultUser = $db->querySingle('SELECT * FROM users WHERE id='.$myid, true);
$isSuperAdmin = ($resultUser['superadmin'] == 1);

if (!$isSuperAdmin) {
	$db->close();
	header("Location: user.php");
	exit;
}

$iduser = -1;
if (isset($_REQUEST['iduser'])) $iduser = (int)$_REQUEST['iduser'];
else {
	$db->close();
	header("Location: user.php");
	exit();
}

$stmt = $db->prepare('UPDATE users SET deleted=1 WHERE id=:p1');
$stmt->bindValue(':p1', $iduser);
$stmt->execute();

$db->close();
header("Location: user.php");
?>