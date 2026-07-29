<?php
require '_pe_checkSession.php';
date_default_timezone_set('Europe/Brussels');

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$idUser = isset($data['iduser']) ? intval($data['iduser']) : 0;
if ($idUser == 0)
{
    http_response_code(400);
    echo json_encode(['error' => 'No user specified.']);
    exit();
}

if (!$isSuperAdmin && $idUser != $myid)
{
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit();
}

$db = new SQLite3('../../db/MarvinUsers.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

$checkStmt = $db->prepare('SELECT 1 FROM users WHERE apitoken=:token LIMIT 1');
do
{
    $newToken = bin2hex(openssl_random_pseudo_bytes(25));
    $checkStmt->bindValue(':token', $newToken);
    $res = $checkStmt->execute();
    $collision = $res->fetchArray(SQLITE3_ASSOC);
    $checkStmt->reset();
} while ($collision);
$checkStmt->close();

$stmt = $db->prepare('UPDATE users SET apitoken=:token, dateUpdated=:mydate WHERE id=:id AND (deleted IS NULL OR deleted=0)');
$stmt->bindValue(':token', $newToken);
$stmt->bindValue(':mydate', date('Ymd H:i:s'));
$stmt->bindValue(':id', $idUser);
$stmt->execute();
$changed = $db->changes();
$db->close();

if ($changed == 0)
{
    http_response_code(404);
    echo json_encode(['error' => 'User not found.']);
    exit();
}

echo json_encode(['apitoken' => $newToken]);
