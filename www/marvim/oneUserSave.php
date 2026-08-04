<?php
require '_pe_checkSession.php';

$returnPage = isset($_POST['return']) ? $_POST['return'] : 'oneUser';

$idUser = isset($_POST['iduser']) ? intval($_POST['iduser']) : 0;
if ($idUser == 0)
{
    header('Location: '.$returnPage.'.php?iduser='.$idUser.'&message=ERROR%20no%20iduser%20found?');
    exit();
}

if (!$isSuperAdmin && $idUser != $myid)
{
    header('Location: '.$returnPage.'.php?iduser='.$idUser.'&message=ERROR%20Cannot%20change%20user%20profile');
    exit();
}

$db = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);

$emailCheck = $db->prepare('SELECT id FROM users WHERE email=:email AND id!=:id and deleted=0');
$emailCheck->bindValue(':email', $_POST['email']);
$emailCheck->bindValue(':id', $idUser);
$emailResult = $emailCheck->execute();
if ($emailResult->fetchArray(SQLITE3_ASSOC))
{
    $db->close();
    header('Location: '.$returnPage.'.php?iduser='.$idUser.'&message=ERROR%20This%20email%20is%20already%20used%20by%20another%20user');
    exit();
}

$stmt = $db->prepare('UPDATE users SET name=:p1, email=:p2, dateUpdated=:mydate WHERE id=:p3');
$stmt->bindValue(':p1', $_POST['name']);
$stmt->bindValue(':p2', $_POST['email']);
$stmt->bindValue(':p3', $idUser);
$stmt->bindValue(':mydate',date('Ymd H:i:s'));
$stmt->execute();

if (isset($_POST['newPassword']) && $_POST['newPassword'] != '')
{
    // Superadmin can set password directly; regular users must confirm their current password
    if (!$isSuperAdmin)
    {
        if (!isset($_POST['currentPassword']) || !password_verify($_POST['currentPassword'], $resultUser['password']))
        {
            $db->close();
            header('Location: '.$returnPage.'.php?iduser='.$idUser.'&message=Error%20Wrong%20current%20password');
            exit();
        }
    }
    $stmt = $db->prepare('UPDATE users SET password=:p1, dateUpdated=:mydate WHERE id=:p2');
    $stmt->bindValue(':p1', password_hash($_POST['newPassword'], PASSWORD_DEFAULT));
    $stmt->bindValue(':p2', $idUser);
    $stmt->bindValue(':mydate',date('Ymd H:i:s'));
    $stmt->execute();
    $db->close();
    header('Location: '.$returnPage.'.php?iduser='.$idUser.'&message=OK%20Profile%20and%20password%20updated%20successfully');
    exit();
}

$db->close();
header('Location: '.$returnPage.'.php?iduser='.$idUser.'&message=OK%20Profile%20updated%20successfully');
