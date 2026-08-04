<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Users</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<script>
function filterByDept(val)
{
  var url = new URL(window.location.href);
  url.searchParams.set('idDpt', val);
  window.location.href = url.toString();
}
var editing=true;
function enableDisableUserEdit(b) 
{
  if (editing) 
  {
    if (b) b.innerText = "Enable editor";
    editables=document.querySelectorAll('.deleteicon');
    for (i=0; i<editables.length; i++)
      editables[i].style.display="none";
  } else 
  {
    if (b) b.innerText = "Disable editor";
    editables=document.querySelectorAll('.deleteicon');
    for (i=0; i<editables.length; i++)
      editables[i].style.display="inline";
  }
  editing=!editing;
}
</script>
</head>
<body>
<?php 
require "_pe_starter.php";

$idUserRight=0;
if (isset($_REQUEST['rights'])) $idUserRight=$_REQUEST['rights'];
$idDpt=0;
if (isset($_REQUEST['idDpt'])) $idDpt=$_REQUEST['idDpt'];

date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
?>
<!-- Content -->
<div class="content">
    <div class="main-section">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="font-size: 34px; color: #333">Users</h1>
<div>
<a class="server-hide-btn deleteicon" style="display:none; text-decoration:none;" 
href="oneUserAdd.php">Add User</a> <button id="enableDisableButton" 
class="server-hide-btn" onclick="enableDisableUserEdit(this)">Enable Edit</button>
</div>
</div>
<div class="breadcrumb"><a href="home.php">Home</a> / <a href="user.php?sortcolumn=name">user</a>
<?php
if ($idUserRight==0) 
{
?>
</div></div><div class="server-section" id="serverSection"><div class="server-cards-grid">
    
<div class="server-card">
    <a style="text-decoration:none;" href="?rights=16">
    <div class="server-card-icon">&#11088;</div>
    <div class="server-card-name">SuperAdmins</div></a>
</div>
<div class="server-card">
    <a style="text-decoration:none;" href="?rights=8">
    <div class="server-card-icon">&#9889;</div>
    <div class="server-card-name">Admin</div></a>
</div>
<div class="server-card">
    <a style="text-decoration:none;" href="?rights=4">
    <div class="server-card-icon">&#10133;</div>
    <div class="server-card-name">Creators</div></a>
</div>
<div class="server-card">
    <a style="text-decoration:none;" href="?rights=2">
    <div class="server-card-icon">&#9998;</div>
    <div class="server-card-name">Documentors</div></a>
</div>
<div class="server-card">
    <a style="text-decoration:none;" href="?rights=1">
    <div class="server-card-icon">&#128065;</div>
    <div class="server-card-name">Viewers</div></a>
</div>

<?php
} else
{
    echo ' / <a href="?rights='.$idUserRight.'">';
    if ($idUserRight==16) echo 'SuperAdmins</a>';
    else if ($idUserRight==8) echo 'Admin</a>';
    else if ($idUserRight==4) echo 'Creators</a>';
    else if ($idUserRight==2) echo 'Documentors</a>';
    else if ($idUserRight==1) echo 'Viewers</a>';
}

echo '</div></div><div class="edit-field"><label>Select Department</label>'.
        '<select class="toxradio" onchange="filterByDept(this.value)">'.
        '<option value="0">All Departments</option>';
$stmt=$db->prepare('SELECT id, name, icon FROM departments ORDER BY sortorder ASC');
$results=$stmt->execute();
for(;;)
{
    $row=$results->fetchArray(SQLITE3_ASSOC);
    if (!$row) break;
    echo '<option value="'.$row['id'].'"';
    if ($row['id']==$idDpt) echo ' selected';
    echo '>'.$row['icon'].' '.htmlspecialchars($row['name']).'</option>';
}
echo '</select>';
$results->finalize();
$stmt->close();
?>
</div>
<!-- History -->
<div class="history-section" style="overflow-x: auto;">

<?php
if ($idUserRight==0) echo '<h2>All Users</h2>';
$db->exec("attach database '" . __DIR__ . "/../../db/MarvimUsers.sqlite' as dbu;");
$hiddenUrlParameters='<input type="hidden" name="rights" value="'.$idUserRight.'">';
$sql='SELECT a.*, ud.rights as rights, a.superadmin from dbu.Users a '.
    'LEFT JOIN userDepartmentRights ud ON ud.idUser=a.id WHERE a.deleted=0 AND (a.superadmin=1 OR (1=1';
// if ($isSuperAdmin)
// {
    if ($idUserRight>0) $sql.=' and COALESCE(ud.rights,0)>='.$idUserRight;
    if ($idDpt>0) $sql.=' and ud.idDepartment='.$idDpt;
    $sql.='))';
// } else
// {
//     $sql.=
//         'INNER JOIN userDepartmentRights ud2 ON ud2.idDepartment=a.idDepartment '.
//         ' where ud2.idUser='.$myid;
//     if ($idUserRight>0) $sql.=' and ud.rights>='.$idUserRight;
// }

$f1='';
if (isset($_REQUEST['filter1'])) $f1=$_REQUEST['filter1'];
?>
<div class="filters">
    <form style="display: contents;" method="GET" action="" id="filterForm">
    <input type="text" class="filter-input" id="filterInput1" name="filter1" placeholder="Search names"
<?php
echo ' value="'.$f1.'"'
?>
        >
    <input type="hidden" id="filterInput2" name="filter2">
    <input type="hidden" id="filterInput3" name="filter3">
</form>
<button class="clear-btn" onclick="clearFilter()">CLEAR</button>
</div>
<div class="server-cards-grid">

<?php
// todo: create cards on table for small screens
if ($f1!='')
{
//    $sql=$sql.' AND name LIKE \'%'.$f1.'%\''; // todo: use bind with dad
    $sql.=' AND a.name LIKE :f1'; // todo: use bind with dad
}
$sql.=' ORDER BY name ASC, id ASC, rights DESC';
//echo $sql;
$stmt = $db->prepare($sql);
if ($f1!='') $stmt->bindValue(':f1', '%'.$f1.'%');
$results=$stmt->execute();
$rowPrev['id']=-1;

while(1)
{
	$row=$results->fetchArray(SQLITE3_ASSOC);
	if (!$row) break;

    if ($rowPrev['id']==$row['id'])
        continue;
	$rowPrev=$row;
	
    echo '<div class="server-card">';
    if ($isSuperAdmin)
        echo '<a style="text-decoration:none;" href="oneUserDelete.php?iduser='.$row['id'].'">'.
            '<div class="server-card-edit deleteicon"><img src="ressources/delete.svg" height="16px"/></div></a>';
    echo '<a style="text-decoration:none;" href="oneUser.php?iduser='.$row['id'].'">'.
        '<div class="server-card-icon"><img src="'.$row['imageFile'].
        '" width="110px" height="110px" style="border-radius: 50%;"/></div>'.
        '<div class="server-card-name">';

    if ($row['superadmin']==1) echo '&#11088; ';
    else
    {
        $uright=$row['rights'];
        if ($uright==8) echo '&#9889; ';
        else if ($uright==4) echo '&#10133;	';
        else if ($uright==2) echo '&#9998; ';
        else if ($uright==1) echo '&#128065; ';
    }
    echo htmlspecialchars($row['name']).'</div>'.
            '</a></div>'."\n";
}
$results->finalize();
$db->close();
?>
</div></div></div></div></div></div>
<script>
enableDisableUserEdit(null);
const handleMobileView=null;
</script>
<?php require '_pe_tableJSFilter.html'; ?>
</body>