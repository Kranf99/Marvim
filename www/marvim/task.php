<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Tasks</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php require "_pe_headerScripts.php"; ?>
</head>
<body>
<?php
require "_pe_starter.php";

$dt='';
$filter='';
if (isset($_REQUEST['priority'])) $dt=$_REQUEST['priority'];

if ($dt=='Reviews') $filter="AND taskType>=500 and taskType<700";
else if ($dt=='High priority Workflows') $filter="AND urgency=3";
else if ($dt=='Medium priority Workflows') $filter="AND urgency=2";
else if ($dt=='Low priority Workflows') $filter="AND urgency=1";
else if ($dt=='Compute Governance KPI') $filter="AND taskType=258";
?>
<!-- Content -->
<div class="content">
    <div class="main-section">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="font-size: 34px; color: #333">
<?php
if ($dt=="") echo 'Tasks</h1>';
else echo $dt.'</h1>';
echo '<div><a class="server-hide-btn deleteicon" style="display:none; text-decoration:none;" href="taskAdd.php?priority='.$dt.'">Add Task</a> ';
?>
<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit(this)">Enable Edit</button>
<button class="server-hide-btn" onclick="toggleCardView()">Toogle Card/Table View</button></div>
</div>
<div class="breadcrumb"><a href="home.php">Home</a> / <a href="task.php">Tasks</a>
<?php
date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$db->exec("attach database '" . __DIR__ . "/../../db/MarvimUsers.sqlite' as dbu;");
if ($dt!="")
{
	echo '/ <a href="?priority='.$dt.'">'.$dt.'</a>';
    echo '</div></div>';
} else
{
?>
        </div>
    </div>
    <!-- Cards -->
    <div class="cards-grid">
        <a class="card" href="?priority=Reviews" style="color:#475569; text-decoration:none;">
            <div class="card-icon">📋</div>
            <div class="card-title">Review &amp; Validate changes</div>
        </a>
        <a class="card" href="?priority=High%20priority%20Workflows" style="color:#475569; text-decoration:none;">
            <div class="card-icon">🔴</div>
            <div class="card-title">High priority Workflows</div>
        </a>
        <a class="card" href="?priority=Medium%20priority%20Workflows" style="color:#475569; text-decoration:none;">
            <div class="card-icon">🟡</div>
            <div class="card-title">Medium priority Workflows</div>
        </a>
        <a class="card" href="?priority=Low%20priority%20Workflows" style="color:#475569; text-decoration:none;">
            <div class="card-icon">🟢</div>
            <div class="card-title">Low priority Workflows</div>
        </a>
        <a class="card" href="?priority=Compute%20Governance%20KPI" style="color:#475569; text-decoration:none;">
            <div class="card-icon">📐</div>
            <div class="card-title">Compute Governance KPI</div>
        </a>
    </div>
<?php } ?>
    <!-- History -->
    <div class="history-section" style="overflow-x: auto;">
<?php
if ($dt=="") echo '<h2>All Tasks</h2>';

$hiddenUrlParameters='<input type="hidden" name="priority" value="'.$dt.'">';
$sql='SELECT a.*, la.liketype, u.name as assignedToUser, uu.name as requestedByUser from Tasks a '.
    ' LEFT JOIN dbu.users u ON u.id=a.assignedToUserId'.
    ' LEFT JOIN dbu.users uu ON uu.id=a.requestedByUserId'.
    ' LEFT JOIN likesTasks la ON a.id=la.idassetorcolumn and la.iduser='.$myid.
    ' where assignedToUserId='.$myid.' '.$filter;
$filterOnAssetTable=false;
require "_pe_filters.php";
?>
<br><br>
    <table class="table-asset" id="dataTable">
        <thead class="thead-asset">
            <tr class="tr-asset">
                <th class="th-asset" style="text-align: center; width:80px"><?php echoSortUrl('category','Icon') ?></th>
                <th class="th-asset" style="text-align: center;"><?php echoSortUrl('dateCreated','Created on') ?></th>
                <th class="th-asset"><?php echoSortUrl('name','Name') ?></th>
                <th class="th-asset" style="text-align: center;"><?php echoSortUrl('assignedToUserId','Assigned To') ?></th>
                <th class="th-asset" style="text-align: center;"><?php echoSortUrl('requestedByUserId','Requested By') ?></th>
                <th class="th-asset" style="text-align: center;"><?php echoSortUrl('status','Status') ?></th>
                <th class="th-asset" style="width: 80px;"><?php echoSortUrl('completion','Completion') ?></th>
                <th class="th-asset" style="text-align: center;"><?php echoSortUrl('rating','Rating') ?></th>
            </tr>
        </thead>
        <tbody>
<?php

for($i=0;$i<count($array_rows);$i++)
{
	$row=$array_rows[$i];
    echo '<tr class="tr-asset"><td class="history-icon" style="text-align: center;">';
    $tt=$row['taskType'];
    if ($tt<500)
    {
        if ($tt==258) echo '📐';
        else 
        {
            switch($row['urgency'])
            {
            case 2: echo '🟡'; break;
            case 3: echo '🔴'; break;
            default: echo '🟢'; break;
            }
        }
    } else echo '📋';
    echo '</td><td>'.$row['dateCreated'].
            '</td><td><a style="text-decoration:none;" href="taskDelete.php?idasset='.
            $row['id'].'" onclick="return confirm(\'Are you sure you want to delete this task?\')"><img class="deleteicon" src="ressources/delete.svg" height="20px" style="vertical-align: middle; padding-right:5px;"/></a>'.
            '<a class="history-title" style="text-decoration:none;" href="oneTask.php?idasset='.
            $row['id'].'">'.htmlspecialchars($row['name']).'</a>';
    if ($row['assignedToUserId']==$myid) $tt='ME';
    else $tt=$row['assignedToUser'];
    echo '</td><td>'.$tt.'</td><td>';
    if ($row['requestedByUserId']==$myid) $tt='ME';
    else $tt=$row['requestedByUser'];
   echo $tt.'</td><td style="text-align: center;">'.
            getStatusDisplay($row['status']).'</td><td style="width: 80px;"><div class="popularity-bar">'.
            '<div class="popularity-fill" style="width: '.$row['completion'].
            '%"></div></div></td><td style="text-align: center;">'.
            '<div onclick="addlike(this,'.$row['id'].',\'Tasks\')">';
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
require_once '_pe_recentActivity.php';
$db->close();
$idAsset=0;
?>
</div>
                    </div>
                </div>
            </div>
    </div>
<?php require '_pe_footer.php'; ?>
<?php require '_pe_tableJSFilter.html'; ?>
</body>
