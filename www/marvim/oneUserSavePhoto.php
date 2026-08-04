<?php
require '_pe_checkSession.php';

$returnPage = (isset($_POST['return']) && $_POST['return'] != '') ? $_POST['return'] : 'oneUser';

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

$maxFileSize = 1 * 1024 * 1024; // 1MB
$allowedExtensions = ['jpg', 'jpeg'];

if (!file_exists('Avatar'))
    mkdir('Avatar', 0755, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=Error%20No%20Data");
    exit();
}

if (!isset($_FILES['profile_picture']))
{
    header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=Error%20No%20Data%20File");
    exit();
}

$file = $_FILES['profile_picture'];

if ($file['error'] !== UPLOAD_ERR_OK)
{
    header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=Error%20Uploading%20file%20-%20".urlencode($file['error']));
    exit();
}

if ($file['size'] > $maxFileSize)
{
    header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=Error%20-%20File%20size%20is%20above%201%20MB");
    exit();
}

$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions))
{
    header("Location: oneUser.php?iduser=".$idUser."&message=Error%20-%20Only%20JPG%2FJPEG%20files%20are%20allowed");
    exit();
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'image/jpeg')
{
    header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=Error%20-%20Invalid%20file%20type.%20Only%20JPEG%20images%20are%20allowed");
    exit();
}

$targetPath = 'Avatar/profile_'.$idUser.'_'.time().'.'.$fileExtension;

if (!move_uploaded_file($file['tmp_name'], $targetPath))
{
    header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=Error%20-%20Failed%20to%20move%20uploaded%20file");
    exit();
}

$db = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$targetUser = $db->querySingle('SELECT imageFile FROM users WHERE id='.$idUser, true);
$db->close();

if ($targetUser && $targetUser['imageFile'] != '')
    @unlink($targetUser['imageFile']);

$db = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$stmt = $db->prepare('UPDATE users SET imageFile = :p1 WHERE id = :p2');
$stmt->bindValue(':p1', $targetPath);
$stmt->bindValue(':p2', $idUser);
$stmt->execute();
$db->close();

header("Location: '.$returnPage.'.php?iduser=".$idUser."&message=OK%20Profile%20picture%20updated%20successfully");
