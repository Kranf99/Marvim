<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];
date_default_timezone_set('Europe/Brussels');

$db = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
//$db->exec("attach database '".getcwd()."/db/users.sqlite' as uu;");
$resultUser = $db->querySingle('SELECT * FROM users WHERE id='.$myid,true);
$db->close();
$isSuperAdmin=($resultUser['superadmin']==1);

$ida=-1;
if (isset($_REQUEST['idasset'])) $ida=$_REQUEST['idasset'];
else
{
	header("Location: storage.php");
	exit();
}

// todo: check function that validates that the user has the rights to delete the storage

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

if (!$isSuperAdmin)
{
	$stmt = $db->prepare('select udr.rights from userDepartmentRights udr'.
						' INNER JOIN Assets a ON udr.idDepartment=a.idDepartment'.
						' where udr.idUser=:idu and a.id=:ida');
	$stmt->bindValue(':idu',$myid);
	$stmt->bindValue(':ida',$ida);
	$results=$stmt->execute();
	$row=$results->fetchArray(SQLITE3_ASSOC);
	$rights=$row ? $row['rights']:0;
	if ($rights<4)
	{
		echo 'Error: You don\'t have the Rights to delete Asset '.$ida.'.<br><a href="storage.php">Go Back</a>';
		die;
	}
}

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Delete','Assets',$ida);

$stmt=$db->prepare('delete from columns WHERE idasset = :p1');
$stmt->bindValue(':p1',$ida);
$stmt->execute();
$stmt=$db->prepare('delete from assets WHERE id = :p1');
$stmt->bindValue(':p1',$ida);
$stmt->execute();

$db->close();
header("Location: storage.php");
?>