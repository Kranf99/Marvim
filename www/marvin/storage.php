<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvin - Storages</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php require "_pe_headerScripts.php"; ?>
</head>
<body>
<?php 
require "_pe_starter.php";

$dt='';
$idserver=0;
$filter='';
if (isset($_REQUEST['datatype'])) $dt=$_REQUEST['datatype'];
if (isset($_REQUEST['idserver'])) $idserver=$_REQUEST['idserver'];

if ($dt=='Files') $filter="AND a.category>=100 and a.category<120";
else if ($dt=='Data Bases') $filter="AND a.category>=120 and a.category<140";
else if ($dt=='Applications') $filter="AND a.category>=140 and a.category<160";
else if ($dt=='API\'s') $filter="AND a.category>=160 and a.category<199";
else $filter="AND a.category>=100 and a.category<199";

if ($idserver!=0) $filter.=" and a.idserver=".(int)$idserver;
?>
<!-- Content -->
<div class="content">
    <div class="main-section">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="font-size: 34px; color: #333">
<?php
if ($dt=="") echo 'Storage</h1>';
else echo $dt.'</h1>';
echo '<div><a class="server-hide-btn deleteicon" style="display:none; text-decoration:none;" href="storageAdd.php?datatype='.$dt.'">Add Table</a> ';
if ($dt!="") 
{
    if ($idserver==0)
        echo '<button class="server-hide-btn" onclick="document.getElementById(\'serverSection\').classList.toggle(\'hidden\')">Hide/Show Server List</button> ';
}
?>
<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit(this)">Enable Edit</button>
<button class="server-hide-btn" onclick="toggleCardView()">Toogle Card/Table View</button></div>
</div>
<div class="breadcrumb"><a href="home.php">Home</a> / <a href="storage.php">Storage</a>
<?php
date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvinDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$idAsset=-1;
if ($dt!="") 
{
	echo '/ <a href="?datatype='.$dt.'">'.$dt.'</a>';
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
//        for($i=0;$i<21;$i++)
            echo '<div class="server-card">'.
                 '<a style="text-decoration:none;" href="editServer.php?idserver='.$row['id'].'">'.
                 '<div class="server-card-edit">✏️</div></a>'.
                 '<a style="text-decoration:none;" href="?datatype='.$dt.'&idserver='.$row['id'].'">'.
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
        echo ' / <a href="?datatype='.$dt.'&idserver='.$idserver.'">'.$row['name'].'</a>';
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
                        <a class="card" href="?datatype=Files" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/file.svg" height="35px"/></div>
                            <div class="card-title">Files</div>
                        </a>
                        <a class="card" href="?datatype=Data%20Bases" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/database.svg" height="35px"/></div>
                            <div class="card-title">Data Bases</div>
                        </a>
                        <a class="card" href="?datatype=Applications" style="color:#475569; text-decoration:none;">
                            <div class="card-icon">🔧</div>
                            <div class="card-title">Applications </div>
                        </a>
                        <a class="card" href="?datatype=API%27s" style="color:#475569; text-decoration:none;">
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
    '"><input type="hidden" name="idserver" value="'.$idserver.'">';
if($isSuperAdmin==1)
    $sql='SELECT a.*, la.liketype, 2 as rights '.
        ',a.shortDescription as shortDescription_new,a.status as status_new'.
        ' from Assets a'.
        ' LEFT JOIN likesAssets la ON a.id=la.idassetorcolumn and la.iduser='.$myid.
        ' where 1=1 '.$filter;
else
    $sql='SELECT a.*, la.liketype, ud.rights as rights'.
        ',COALESCE(ac.idserver,a.idserver) as idserver_new,COALESCE(ac.name,a.name) as name_new,COALESCE(ac.shortDescription,a.shortDescription) as shortDescription_new,COALESCE(ac.longDescription,a.longDescription) as longDescription_new,COALESCE(ac.status,a.status) as status_new,COALESCE(ac.tags,a.tags) as tags_new'.
        ' from Assets a'.
        ' LEFT JOIN likesAssets la ON a.id=la.idassetorcolumn and la.iduser='.$myid.
        ' LEFT JOIN AssetsChanges ac ON ac.rowId=a.id AND ac.changedByUserId='.$myid.
        ' INNER JOIN userDepartmentRights ud ON a.idDepartment=ud.idDepartment'.
        ' where ud.idUser='.$myid.' '.$filter;
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

    // and name like '%csv%'");
for($i=0;$i<count($array_rows);$i++)
{
	$row=$array_rows[$i];
    echo '<tr class="tr-asset';
    if (($isSuperAdmin==0)&&(
        ($row['idserver']!=$row['idserver_new'])||
        ($row['name']!=$row['name_new'])||
        ($row['shortDescription']!=$row['shortDescription_new'])||
        ($row['longDescription']!=$row['longDescription_new'])||
        ($row['status']!=$row['status_new'])||
        ($row['tags']!=$row['tags_new'])))
        echo " highlighted";
    echo '"><td class="history-icon" style="text-align: center;">'.
            getIcon($row['category']).'</td><td><a style="text-decoration:none;" href="storageDelete.php?idasset='.
            $row['id'].'" onclick="return confirm(\'Are you sure you want to delete this Storage Asset?\')"><img class="deleteicon" src="ressources/delete.svg" height="20px" style="vertical-align: middle; padding-right:5px;"/></a>'.
            '<a class="history-title" style="text-decoration:none;" href="table.php?idasset='.
            $row['id'].'">'.htmlspecialchars($row['name']).'</a></td><td>';
    if ($row['rights']>=2)
        echo '<div class="editable" data-id="'.$row['id'].
                '" data-columnname="shortDescription" data-tablename="Assets">'.
                ($row['shortDescription_new']).' </div>';
    else echo ($row['shortDescription']);
    echo '</td><td style="text-align: center;">'.
            getStatusDisplay($row['status_new']).'</td><td style="width: 80px;"><div class="popularity-bar">'.
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
<?php    
if ($idAsset>0) 
{ ?>
<button class="tablinks" onclick="openTab(event,'ChatTab')" id="defaultTab">Conversation</button>
<button class="tablinks" onclick="openTab(event,'ActivityTab')">Activity</button>
</div>
<div id="ChatTab" class="tabcontent">
  <div class="mainchat">
    <div class="chat-container">
        <div class="chat-messages" id="messages"></div>
        <div class="chat-input-container">
            <input type="text" class="chat-input" id="messageInput" placeholder="Type a message..." />
            <button class="chatsend-button" id="sendButton">&#10148;</button>
        </div>
    </div>
</div>
</div>
<?php 
} else        
  echo '<button class="tablinks" onclick="openTab(event,\'ActivityTab\')" id="defaultTab">Activity</button></div>';
?>

<?php 
$db->exec("attach database '" . __DIR__ . "/../../db/MarvinUsers.sqlite' as dbu;");
require_once '_pe_recentActivity.php';
$db->close();
?>
</div>
                    </div>
                </div>
            </div>
    </div>
<?php require '_pe_footer.php'; ?>
<?php require '_pe_tableJSFilter.html'; ?>
</body>