<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Add Report</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
<?php require "_pe_starter.php"; ?>
<div class="content">
<div class="main-section">
    <h1 style="font-size: 34px; color: #333">Add Report</h1>
    <div class="breadcrumb">
        <a href="home.php">Home</a> /
        <a href="report.php">Reports</a> / Create Report
    </div>

<?php
$iddept = 0;
if (isset($_REQUEST['iddept'])) $iddept = (int)$_REQUEST['iddept'];

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
if ($isSuperAdmin) 
    $sql='SELECT id, name, icon FROM departments ORDER BY sortorder ASC';
else 
    $sql='SELECT d.id, d.name, d.icon FROM departments d INNER JOIN userDepartmentRights ud ON ud.idDepartment=d.id WHERE ud.idUser='.$myid.' and ud.rights>=4 ORDER BY sortorder ASC';
$results = $db->query($sql);
$noRows=true;
while ($row = $results->fetchArray(SQLITE3_ASSOC))
{
    if ($noRows) echo '<p>Select Department:</p><div class="cards-grid">';
    $noRows=false;
    $rid=$row['id'];
    echo '<a class="card dept-card" id="dept-'.$rid.'" href="?iddept='.$rid.'" style="color:#475569; text-decoration:none;';
    if ($iddept==$rid) echo 'border:2px solid #11F;';
    echo '"><div class="card-icon">'.$row['icon'].'</div>'.
         '<div class="card-title">'.htmlspecialchars($row['name']).'</div>'.
         '</a>';
}
$db->close();
if ($noRows)
{
    echo 'You have no "Creator" Rights in any departement<br><a href="report.php">Go Back</a>';
    die;
}
echo '</div>';
if ($iddept>0) echo '<div id="form-report">';
else echo '<div id="form-report" style="display:none">';
?>
<br>
    <div class="edit-field">
        <label for="reportname">Report name:</label>
        <input type="text" id="reportname" name="reportname" placeholder="Enter report name" class="text-input-style">
    </div>
    <button type="button" class="btn btn-save" onclick="submitCreateReport()">Create New Report</button>
</div>
</div>
</div>

<script>
function submitCreateReport() 
{
    var el=document.getElementById('reportname');
    var tn = el.value.trim();
    if (!tn) 
    { 
        alert('Please enter a report name.'); 
        el.focus(); 
        return; 
    }
<?php    
echo 'window.location.href=\'reportNew.php?iddept='.$iddept.'&name=\'+encodeURIComponent(tn);';
?>
}
</script>
</body>
