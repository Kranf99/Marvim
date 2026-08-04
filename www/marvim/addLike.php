<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// UPDATE assets SET description=%1 where id=%2
$checkCN= array(
    'Assets'=>'likesAssets',
    'Columns'=>'likesColumns',
    'KPI'=>'likesKPI',
    'Glossary'=>'likesGlossary',
    'Tasks'=>'likesTasks'
);
$tablename=$data['table'];
if (!array_key_exists($tablename,$checkCN))
{
    echo 'don\'t be evil (no such table)';
    exit(500);
}
$tablelike=$checkCN[$tablename];

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

$row=$db->querySingle('SELECT liketype from '.$tablelike.' where iduser='.$myid.' AND idassetorcolumn='.$data['id']);
if ($row==1)
{
    // there was already a like , so we do --
    $stmt=$db->prepare('UPDATE '.$tablename.' SET rating = rating-1 WHERE id = :ida');
    $stmt->bindValue(':ida', $data['id']);
    $stmt->execute();
    
//    $db->query('DELETE from '.$tablelike.' where iduser='.$myid.' AND idassetorcolumn='.$data['id']);
    $stmt=$db->prepare('DELETE from '.$tablelike.' where iduser=:idu AND idassetorcolumn=:ida');
    $stmt->bindValue(':idu', $myid);
    $stmt->bindValue(':ida', $data['id']);
    $stmt->execute();
    $blue=0;
} else
{
    // there was no like , so we do ++

    $stmt=$db->prepare('UPDATE '.$tablename.' SET rating = rating+1 WHERE id = :ida');
    $stmt->bindValue(':ida', $data['id']);
    $stmt->execute();
    
//    $db->query('INSERT INTO '.$tablelike.'(iduser,idassetorcolumn,liketype) VALUES('.$myid.','.$data['id'].',1)');
    $stmt=$db->prepare('INSERT INTO '.$tablelike.'(iduser,idassetorcolumn,liketype) VALUES(:idu,:ida,1)');
    $stmt->bindValue(':idu', $myid);
    $stmt->bindValue(':ida', $data['id']);
    $stmt->execute();
    $blue=1;
}
$stmt=$db->prepare('select rating from '.$tablename.' WHERE id = :ida');
$stmt->bindValue(':ida', $data['id']);
$results=$stmt->execute();
$row = $results->fetchArray();

header('Content-Type: application/json');
if ($db->changes() > 0) echo '{"status":"ok","counter":'.$row['rating'].',"blue":'.$blue.'}';
else echo '{"status":"error"}';
$db->close();
?>