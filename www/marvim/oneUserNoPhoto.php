<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];
$returnPage = isset($_POST['return']) ? $_POST['return'] : 'oneUser';

$idUser = isset($_POST['iduser']) ? intval($_POST['iduser']) : 0;
if ($idUser == 0)
{
    header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=ERROR%20no%20iduser%20found?");
    exit();
}

if (!$isSuperAdmin && $idUser != $myid)
{
    header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=ERROR%20Cannot%20change%20user%20profile");
    exit();
}
unlink($resultUser['imageFile']);
$db = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$stmt=$db->prepare('UPDATE users SET imageFile = \'ressources/defaultavatar.svg\' WHERE id = :p2');
$stmt->bindValue(':p2', $idUser);
$stmt->execute();
$db->close();
header('Location: '.$returnPage.'.php?message=OK%20Profile%20Picture%20removed%20successfully');
?>