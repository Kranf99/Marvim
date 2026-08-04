<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Add Storage</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
<?php 
require "_pe_starter.php";
?>
<div class="content">
<div class="main-section">
    <h1 style="font-size: 34px; color: #333">Create Storage</h1>
    <div class="breadcrumb"><a href="home.php">Home</a> / <a href="storage.php">Storage</a> / Create Storage </div>
    <p>Select Storage type:</p>
<div class="cards-grid">
    <a class="card" id="cardfile" href="?datatype=Files" style="color:#475569; text-decoration:none;">
        <div class="card-icon"><img src="ressources/file.svg" height="35px"/></div>
        <div class="card-title">Files</div>
    </a>
    <a class="card" id="carddb" href="?datatype=Data%20Bases" style="color:#475569; text-decoration:none;">
        <div class="card-icon"><img src="ressources/database.svg" height="35px"/></div>
        <div class="card-title">Data Bases</div>
    </a>
    <a class="card" id="cardapp" href="?datatype=Applications" style="color:#475569; text-decoration:none;">
        <div class="card-icon">🔧</div>
        <div class="card-title">Applications </div>
    </a>
    <a class="card" id="cardapi" href="?datatype=API%27s" style="color:#475569; text-decoration:none;">
        <div class="card-icon">☁️</div>
        <div class="card-title">API's</div>
    </a>
</div>
<br>
<div id="form-dpt" class="edit-field" style="display:none">
<?php
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
    if ($noRows) echo '<label for="dpt-select">Owning Department:</label><select id="dpt-select" name="dpt">';
    $noRows=false;
    echo '<option value="'.$row['id'].'">'.$row['icon'].' '.htmlspecialchars($row['name']).'</option>';
}
$db->close();
if ($noRows)
{
    echo 'You have no "Creator" Rights in any departement</div><br><a href="report.php">Go Back</a>';
    die;
}
?>
</select>
</div>
<div id="form-file" class="edit-field" style="display:none">
    <label for="file-type-select">Type:</label>
    <select id="file-type-select" name="type">
        <option value="101">101 - Generic file</option>
        <option value="102">102 - File in Data Lake</option>
        <option value="103">103 - Isolated Structured File</option>
        <option value="104">104 - Meta File - Structured</option>
        <option value="105">105 - Unstructured File</option>
    </select>
</div>
<div id="form-database" class="edit-field" style="display:none">
    <label for="db-type-select">Type:</label>
    <select id="db-type-select" name="type">
        <option value="120">120 - Local Table</option>
        <option value="121">121 - Local View</option>
        <option value="122">122 - Remote Table</option>
    </select>
</div>
<div id="endOfForm" style="display:none">
<div class="edit-field">
    <label for="tablename">Table name:</label>
    <input type="text" id="tablename" name="tablename" placeholder="Enter table name" class="text-input-style">
</div>
<button type="button" class="btn btn-save" onclick="submitCreateStorage()">Create New Storage</button>
</div>
<script>
function submitCreateStorage() 
{
    var el=document.getElementById('tablename');
    var tn = el.value.trim();
    if (!tn) 
    { 
        alert('Please enter a table name.'); 
        el.focus(); 
        return; 
    }
    var mytype=0;
    if (dt=="Files") mytype=document.getElementById('file-type-select').value;
    else if (dt=="Data Bases") mytype=document.getElementById('db-type-select').value;
    else if (dt=="Applications") mytype=140;
    else if (dt=="API's") mytype=160;
    window.location.href='storageNew.php?type='+mytype+'&tablename='+encodeURIComponent(tn)+
        '&idDpt='+document.getElementById('dpt-select').value;
}

var dt = new URLSearchParams(window.location.search).get('datatype');
if (dt=="Files") 
{
    document.getElementById('cardfile').style.border = '2px solid #11F';
    document.getElementById('form-dpt').style.display = 'flex';
    document.getElementById('form-file').style.display = 'flex';
    document.getElementById('endOfForm').style.display = 'block';
} else if (dt=="Data Bases") 
{
    document.getElementById('carddb').style.border = '2px solid #11F';
    document.getElementById('form-dpt').style.display = 'flex';
    document.getElementById('form-database').style.display = 'flex';
    document.getElementById('endOfForm').style.display = 'block';
}
</script>
</div>
</body>