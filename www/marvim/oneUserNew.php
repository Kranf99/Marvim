<?php
require '_pe_checkSession.php';
date_default_timezone_set('Europe/Brussels');

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$isAdmin = (bool) $db->querySingle('SELECT 1 FROM userDepartmentRights WHERE idUser='.$myid.' AND rights>=8 LIMIT 1');
$db->close();

if (!$isSuperAdmin && !$isAdmin)
{
    header('Location: oneUserAdd.php?message=ERROR%20Not%20enough%20rights');
    exit();
}

$name     = isset($_POST['name'])     ? trim($_POST['name'])     : '';
$email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
$password = isset($_POST['password']) ? $_POST['password']       : '';

if ($name === '' || strpos($email, '@') === false || $password === '')
{
    header('Location: oneUserAdd.php?message=ERROR%20Invalid%20name%2C%20email%20or%20password&name='.urlencode($name).'&email='.urlencode($email));
    exit();
}

$dbu = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READWRITE);
$dbu->busyTimeout(5000);

$stmtCheck = $dbu->prepare('SELECT id FROM users WHERE email=:p1 and deleted=0 LIMIT 1');
$stmtCheck->bindValue(':p1', $email);
$existing = $stmtCheck->execute()->fetchArray(SQLITE3_ASSOC);
if ($existing)
{
    $dbu->close();
    header('Location: oneUserAdd.php?message=ERROR%20A%20user%20with%20this%20email%20already%20exists&name='.urlencode($name).'&email='.urlencode($email));
    exit();
}

$stmt = $dbu->prepare('INSERT INTO users (name, email, password, imageFile, deleted, superadmin,dateCreated,dateUpdated) VALUES (:p1, :p2, :p3, \'ressources/defaultavatar.svg\',0, 0,:mydate,:mydate)');
$stmt->bindValue(':p1', $name);
$stmt->bindValue(':p2', $email);
$stmt->bindValue(':p3', password_hash($password, PASSWORD_DEFAULT));
$stmt->bindValue(':mydate',date('Ymd H:i:s'));
$stmt->execute();

$newId = $dbu->lastInsertRowID();
$dbu->close();

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
require_once '_pe_addEvent.php';
addEvent($db,$myid,'Added User in','users',0);
$db->close();

header('Location: oneUser.php?iduser='.$newId.'&message=OK%20User%20created%20successfully');
?>