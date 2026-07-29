<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvin - Edit User</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="stylesheet" href="ressources/styleUser.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
<?php
require "_pe_starter.php";

$idUser = isset($_GET['iduser']) ? intval($_GET['iduser']) : 0;
if ($idUser == 0)
{
    echo '<div class="specialcontent"><p>No user specified.</p></div>';
    die;
}

$db = new SQLite3('../../db/MarvinDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$myRights = (int) $db->querySingle('SELECT MAX(rights) FROM userDepartmentRights WHERE idUser='.$myid.' LIMIT 1');

if (!$isSuperAdmin && ($myRights<8) && $idUser != $myid)
{
    echo '<div class="specialcontent"><p>Access denied: You are not admin in any department.</p></div>';
    die;
}

$dbu = new SQLite3('../../db/MarvinUsers.sqlite', SQLITE3_OPEN_READONLY);
$dbu->busyTimeout(5000);
$targetUser = $dbu->querySingle('SELECT * FROM users WHERE id='.$idUser, true);
$dbu->close();
if (!$targetUser)
{
    echo '<div class="specialcontent"><p>User not found.</p></div>';
    die;
}

// Only superadmin or the user themselves can edit personal data (photo, name, email)
$canEditPersonal = $isSuperAdmin || ($idUser == $myid);
$editPersonalPass=false;
$returnPage='oneUser';
require "_pe_editProfile.php";

if (!$targetUser['superadmin'])
{
    if ($isSuperAdmin)
        $sql = 'SELECT d.*, COALESCE(ud.rights,0) as rights, 8 as editrights FROM departments d'.
            ' LEFT JOIN userDepartmentRights ud ON ud.idDepartment=d.id and ud.idUser='.$idUser.
            ' ORDER BY d.sortorder ASC';
    else 
        $sql = 'SELECT d.*, COALESCE(ud.rights,0) as rights, COALESCE(udd.rights,0) as editrights FROM departments d'.
            ' LEFT JOIN userDepartmentRights ud ON ud.idDepartment=d.id and ud.idUser='.$idUser.
            ' LEFT JOIN userDepartmentRights udd ON udd.idDepartment=d.id and udd.idUser='.$myid.
            ' WHERE (ud.rights>=1 OR udd.rights>=8) ORDER BY d.sortorder ASC';
    $stmtDpts = $db->prepare($sql);
    $resDpts = $stmtDpts->execute();
    ?>
    <div class="form-section">
        <h3>Department Rights</h3>
        <table class="rights-table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Rights</th>
                </tr>
            </thead>
            <tbody>
    <?php 
    while ($row = $resDpts->fetchArray(SQLITE3_ASSOC))
    { 
        echo '<tr><td>'.$row['icon'].' '.htmlspecialchars($row['name']).'</td><td>'.
            '<select data-id="'.$row['id'].'"';
        if ((!$isSuperAdmin)&&(($row['editrights']<8)||($myid==$idUser))) echo ' disabled';
        echo ' onchange="saveUserRight(this.value, this)">';
        $right = $row['rights'];
        echo '<option value="0" '.($right==0?'selected':'').'>no rights</option>';
        echo '<option value="1" '.($right==1?'selected':'').'>viewer</option>';
        echo '<option value="2" '.($right==2?'selected':'').'>documentor</option>';
        echo '<option value="4" '.($right==4?'selected':'').'>creator</option>';
        echo '<option value="8" '.($right==8?'selected':'').'>admin</option>';
        echo '</select></td></tr>';
    }
    echo '</tbody></table>';
}
?>
</div>
</div>
</div>

<script>
async function saveUserRight(content, el)
{
    const idDpt = el.dataset.id;
<?php
echo 'var data="{\"iduser\":'.$idUser.',\"iddept\":"+idDpt+",\"rights\":"+content+"}";';
?>
    try {
        const response = await fetch('oneUserSaveRight.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: data});
        const result = await response.text();
        console.log('Saved:', idDpt, result);
    } catch (err) {
        console.error('Save failed:', err);
    }
}
</script>
</body>
</html>
