<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Edit Profile</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="stylesheet" href="ressources/styleUser.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
    <?php 
require "_pe_starter.php";
$targetUser=$resultUser;
$idUser=$myid;
$isAdmin=false;
$canEditPersonal=true;
$editPersonalPass=true;
$returnPage='profile';
require "_pe_editProfile.php";
?>
</div>
</div>
</div>
</body>
</html>