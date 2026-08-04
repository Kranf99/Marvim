<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];

$word="";
if (isset($_REQUEST['word'])) $word=$_REQUEST['word'];
else
{
	header("Location: glossary.php");
	exit();
}
date_default_timezone_set('Europe/Brussels');

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$stmt=$db->prepare('select id, name from Glossary where toDelete=0 and lower(name)=:name');
$stmt->bindValue(':name',strtolower($word));
$results=$stmt->execute();
$row=$results->fetchArray(SQLITE3_ASSOC);
if ($row)
{
	echo 'Error: the name '.$word.' is already defined in the Glossary.<br><br>For example:<br>';
	while(1)
	{
		echo $row['name'].'<br>';
		$row=$results->fetchArray(SQLITE3_ASSOC);
		if (!$row) break;
	}
	echo '<br><a href="glossary.php">Back</a>';
	die;
}

$stmt=$db->prepare('insert into Glossary(name,idowner,dateCreated,dateUpdated,status,popularity,rating,toDelete) '.
	' values (:name,:idowner,:mydate,:mydate,0,0,0,0)' );
$stmt->bindValue(':name',$word);
$stmt->bindValue(':idowner',$myid);
$stmt->bindValue(':mydate',date('Ymd H:i:s'));
$stmt->execute();
$newId = $db->lastInsertRowID();

require_once 'glossaryMakeFile.php';

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Add word "'.$word.'"','Glossary',0);

$db->close();
//echo $content;
header('Location: glossaryOneDef.php?idasset='.$newId.'&newAsset=1');
?>