<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvin - Select Storage Asset</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php require "_pe_headerScripts.php"; ?>
</head>
<body>
<?php
require "_pe_starter.php";

if (!isset($_REQUEST['idworkflow']) || !isset($_REQUEST['workflowDirection'])) 
{ 
    echo 'Error: idworkflow and workflowDirection are required.'; 
    die; 
}
$idworkflow=$_REQUEST['idworkflow'];
$workflowDirection=$_REQUEST['workflowDirection'];

$dt='';
$idserver=0;
$filter='';
if (isset($_REQUEST['datatype'])) $dt=$_REQUEST['datatype'];
if (isset($_REQUEST['idserver'])) $idserver=$_REQUEST['idserver'];

if ($dt=='Files') $filter="AND category>=100 and category<120";
else if ($dt=='Data Bases') $filter="AND category>=120 and category<140";
else if ($dt=='Applications') $filter="AND category>=140 and category<160";
else if ($dt=='API\'s') $filter="AND category>=160 and category<199";
else $filter="AND category>=100 and category<199";

if ($idserver!=0) $filter.=" and a.idserver=".(int)$idserver;

$baseParams='idworkflow='.$idworkflow.'&workflowDirection='.$workflowDirection;

date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvinDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$stmt = $db->prepare('select a.* from Assets a where a.id=:ids');
$stmt->bindValue(':ids',$idworkflow);
$results=$stmt->execute();
$rowWF=$results->fetchArray(SQLITE3_ASSOC);
if (!$rowWF) 
{
    echo 'Error: Workflow '.$idworkflow.' not found';
    die;
}

?>
<!-- Content -->
<div class="content">
    <div class="main-section">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="font-size: 34px; color: #333">
<?php
if ($dt=="") echo 'Select '.$workflowDirection.' table for Workflow '.$rowWF['name'].'</h1>';
else echo htmlspecialchars($dt).'</h1>';
echo '<div>';
if ($dt!="" && $idserver==0)
    echo '<button class="server-hide-btn" onclick="document.getElementById(\'serverSection\').classList.toggle(\'hidden\')">Hide/Show Server List</button> ';
?>
<button class="server-hide-btn" onclick="toggleCardView()">Toogle Card/Table View</button></div>
</div>
<div class="breadcrumb"><a href="home.php">Home</a> / <a href="workflow.php">Workflows</a>
<?php
echo ' / <a href="oneWorkflow.php?idasset='.$idworkflow.'">'.$rowWF['name'].'</a> / <a href="?'.$baseParams.'">Select '.$workflowDirection.' Table</a>';

$idAsset=-1;
if ($dt!="")
{
	echo ' / <a href="?'.$baseParams.'&datatype='.urlencode($dt).'">'.htmlspecialchars($dt).'</a>';
	if ($idserver==0)
	{
		echo '</div></div><div class="server-section" id="serverSection"><div class="server-cards-grid">';

		$sql='SELECT id, name from servers where servertype=:st';
		$stmt = $db->prepare($sql);
		$stmt->bindValue(':st',$dt);
		$results=$stmt->execute();
		while(1)
	    {
	        $row=$results->fetchArray(SQLITE3_ASSOC);
	        if (!$row) break;
            echo '<div class="server-card">'.
                 '<a style="text-decoration:none;" href="?'.$baseParams.'&datatype='.urlencode($dt).'&idserver='.$row['id'].'">'.
                 '<div class="server-card-icon">🖥️</div>'.
                 '<div class="server-card-name">'.$row['name'].'</div></a></div>';
	    }
	} else
	{
        $sql='SELECT name, idasset from servers where id=:ids';
		$stmt = $db->prepare($sql);
		$stmt->bindValue(':ids',$idserver);
		$results=$stmt->execute();
        $row=$results->fetchArray(SQLITE3_ASSOC);
        echo ' / <a href="?'.$baseParams.'&datatype='.urlencode($dt).'&idserver='.$idserver.'">'.htmlspecialchars($row['name']).'</a>';
        $idAsset=$row['idasset'];
	}
    echo '</div></div>';
} else
{
?>
        </div>
    </div>
    <!-- Cards -->
    <div class="cards-grid">
        <a class="card" href="?<?=$baseParams?>&datatype=Files" style="color:#475569; text-decoration:none;">
            <div class="card-icon"><img src="ressources/file.svg" height="35px"/></div>
            <div class="card-title">Files</div>
        </a>
        <a class="card" href="?<?=$baseParams?>&datatype=Data%20Bases" style="color:#475569; text-decoration:none;">
            <div class="card-icon"><img src="ressources/database.svg" height="35px"/></div>
            <div class="card-title">Data Bases</div>
        </a>
        <a class="card" href="?<?=$baseParams?>&datatype=Applications" style="color:#475569; text-decoration:none;">
            <div class="card-icon">🔧</div>
            <div class="card-title">Applications </div>
        </a>
        <a class="card" href="?<?=$baseParams?>&datatype=API%27s" style="color:#475569; text-decoration:none;">
            <div class="card-icon">☁️</div>
            <div class="card-title">API's</div>
        </a>
    </div>
<?php } ?>
<!-- History -->
<div class="history-section" style="overflow-x: auto;">
<?php
if ($dt=="") echo '<h2>All Schemas, Tables and Views</h2>';

$hiddenUrlParameters='<input type="hidden" name="datatype" value="'.$dt.
    '"><input type="hidden" name="idserver" value="'.$idserver.
    '"><input type="hidden" name="idworkflow" value="'.$idworkflow.
    '"><input type="hidden" name="workflowDirection" value="'.$workflowDirection.'">';
$sql='SELECT a.*, la.liketype from Assets a '.
    'LEFT JOIN likesAssets la ON a.id=la.idassetorcolumn and la.iduser='.$myid.
    ' where 1=1 '.$filter;
$filterOnAssetTable=true;
require "_pe_filters.php";
?>
<br><br>
    <table class="table-asset" id="dataTable">
        <thead class="thead-asset">
            <tr class="tr-asset">
                <th class="th-asset" style="text-align: center; width:80px"><?php echoSortUrl('category','Icon') ?></th>
                <th class="th-asset"><?php echoSortUrl('name','Name') ?></th>
                <th class="th-asset"><?php echoSortUrl('shortDescription','Description') ?></th>
                <th class="th-asset" style="text-align: center;"><?php echoSortUrl('status','Status') ?></th>
                <th class="th-asset" style="width: 80px;"><?php echoSortUrl('popularity','Popularity') ?></th>
                <th class="th-asset" style="text-align: center;"><?php echoSortUrl('rating','Rating') ?></th>
            </tr>
        </thead>
        <tbody>
<?php

for($i=0;$i<count($array_rows);$i++)
{
	$row=$array_rows[$i];
    echo '<tr class="tr-asset"><td class="history-icon" style="text-align: center;">'.
            getIcon($row['category']).'</td><td><a class="history-title" style="text-decoration:none;" href="oneWorkFlowNewIO.php?idasset='.
            $row['id'].'&'.$baseParams.'">'.htmlspecialchars($row['name']).'</a></td><td><div data-id="'.$row['id'].
        	'" data-columnname="shortDescription" data-tablename="assets">'.
        	($row['shortDescription']).' </div></td><td style="text-align: center;">'.
            getStatusDisplay($row['status']).'</td><td style="width: 80px;"><div class="popularity-bar">'.
            '<div class="popularity-fill" style="width: '.$row['popularity'].
            '%"></div></div></td><td style="text-align: center;">'.
            '<div onclick="addlike(this,'.$row['id'].',\'Assets\')">';
    if ($row['rating']!=0) { echo $row['rating'].' '; }
    if ($row['liketype']==1) echo '<img src="ressources/like.svg" height="15px"/>';
    else  echo '<img src="ressources/nolike.svg" height="15px"/>';
    echo '</div></td></tr>'."\n\n";
}
$results->finalize();
?>
            </tbody>
    </table>
    </div>
</div>

<!-- Right sidebar -->
<div class="sidebar-right">

<!-- Tab links -->
<div class="tab">
<button class="tablinks" onclick="openTab(event,'ActivityTab')" id="defaultTab">Activity</button></div>

<?php 
$db->exec("attach database '" . __DIR__ . "/../../db/MarvinUsers.sqlite' as dbu;");
require_once '_pe_recentActivity.php';
$db->close();
?>
</div></div></div></div></div>
<?php require '_pe_footer.php'; ?>
<?php require '_pe_tableJSFilter.html'; ?>
</body>