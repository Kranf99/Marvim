<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];
$json = file_get_contents('php://input');
$data = json_decode($json, true);

require_once '_pe_addEvent.php';

$idasset=-1;
if (isset($data['idasset'])) $idasset=$data['idasset'];
else
{
	echo 'don\'t be evil (no idasset)';
    exit(500);
}
$iddept=-1;
if (isset($data['iddept'])) $iddept=$data['iddept'];
else
{
	echo 'don\'t be evil (no iddept)';
    exit(500);
}
date_default_timezone_set('Europe/Brussels');

// todo: validate that the user has the rights to change dpt

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

$stmt=$db->prepare('select idDepartment from Assets where id=:ida');
$stmt->bindValue(':ida',$idasset);
$results=$stmt->execute();
$rowAsset=$results->fetchArray(SQLITE3_ASSOC);
if (!$rowAsset) 
{
	echo 'don\'t be evil (no known asset)';
    exit(500);
}
$oldIddpt=$rowAsset['idDepartment'];
if ($oldIddpt!=$iddept)
{
	$stmt=$db->prepare('UPDATE departments SET n=n-1 WHERE id = :iddept');
	$stmt->bindValue(':iddept', $oldIddpt);
	$stmt->execute();
	$stmt=$db->prepare('UPDATE departments SET n=n+1 WHERE id = :iddept');
	$stmt->bindValue(':iddept', $iddept);
	$stmt->execute();
	$stmt=$db->prepare('UPDATE Assets SET idDepartment=:iddept WHERE id = :ida');
	$stmt->bindValue(':ida', $idasset);
	$stmt->bindValue(':iddept', $iddept);
	$stmt->execute();

	addEvent($db,$myid,'Change dpt from','Assets',$idasset);
}
$db->close();

header('Content-Type: application/json');
echo '{"status":"ok"}';
?>