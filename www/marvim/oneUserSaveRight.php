<?php
require '_pe_checkSession.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['iduser']))
{
    http_response_code(400);
    echo 'don\'t be evil (no iduser)';
    exit();
}
if (!isset($data['iddept']))
{
    http_response_code(400);
    echo 'don\'t be evil (no iddept)';
    exit();
}
if (!isset($data['rights']))
{
    http_response_code(400);
    echo 'don\'t be evil (no rights)';
    exit();
}

$idUser = intval($data['iduser']);
$idDpt  = intval($data['iddept']);
$rights = intval($data['rights']);

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

if (!$isSuperAdmin)
{
    $stmt = $db->prepare('SELECT rights FROM userDepartmentRights WHERE idUser = :uid AND idDepartment = :did');
    $stmt->bindValue(':uid', $myid);
    $stmt->bindValue(':did', $idDpt);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    $stmt->close();
    $myRights = $row ? $row['rights'] : 0;
    if ($myRights < 8)
    {
        $db->close();
        http_response_code(403);
        echo 'don\'t be evil (not admin of this department and not superAdmin)';
        exit();
    }
}

if ($rights == 0)
{
    $stmt = $db->prepare('DELETE FROM userDepartmentRights WHERE idUser = :uid AND idDepartment = :did');
    $stmt->bindValue(':uid', $idUser);
    $stmt->bindValue(':did', $idDpt);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $db->prepare('UPDATE userDepartmentRights SET rights = :r WHERE idUser = :uid AND idDepartment = :did');
    $stmt->bindValue(':r', $rights);
    $stmt->bindValue(':uid', $idUser);
    $stmt->bindValue(':did', $idDpt);
    $stmt->execute();
    $stmt->close();
    if ($db->changes() == 0)
    {
        $stmt = $db->prepare('INSERT INTO userDepartmentRights (idUser, idDepartment, rights) VALUES (:uid, :did, :r)');
        $stmt->bindValue(':uid', $idUser);
        $stmt->bindValue(':did', $idDpt);
        $stmt->bindValue(':r', $rights);
        $stmt->execute();
        $stmt->close();
    }
}

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Change rights of','users',0);
$db->close();

header('Content-Type: application/json');
echo '{"status":"ok"}';
