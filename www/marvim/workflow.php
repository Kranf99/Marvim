<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Workflow</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php require "_pe_headerScripts.php"; ?>
</head>
<body>
<?php 
require "_pe_starter.php";
require "_pe_serverIcons.php";   // serverIconSrc() / serverDefaultIcon() / serverIconHtml()

$idserver=0;
$filter='';
if (isset($_REQUEST['idserver'])) $idserver=(int)$_REQUEST['idserver'];

// An alias is just another name for a real server, and only real servers get a card --
// so a real server's page must also show the assets attached to its aliases, otherwise
// those assets would be unreachable from anywhere. Needs the "LEFT JOIN servers s" that
// both $sql branches below carry.
if ($idserver!=0)
    $filter.=' and (a.idserver='.(int)$idserver.
             ' or s.finalShortcutToServerID='.(int)$idserver.')';
?>
<!-- Content -->
<div class="content">
<div class="main-section">
<div>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
<h1 style="font-size: 34px; color: #333">Workflows</h1>
<div><a class="server-hide-btn deleteicon" style="display:none; text-decoration:none;" href="workflowAdd.php">Add Workflow</a>
<?php
// Only worth showing when the server grid is actually on the page: same $idserver==0
// condition that renders #serverSection below.
if ($idserver==0)
    echo '<button class="server-hide-btn" onclick="document.getElementById(\'serverSection\').classList.toggle(\'hidden\')">Hide/Show Server List</button> ';
?>
<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit(this)">Enable Edit</button>
<button class="server-hide-btn" onclick="toggleCardView()">Toogle Card/Table View</button></div>
</div>
<div class="breadcrumb"><a href="home.php">Home</a> / <a href="workflow.php">Workflow</a>
<?php
date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
if ($idserver==0) 
{
    $idAsset=0;
    echo '</div></div><div class="server-section" id="serverSection"><div class="server-cards-grid">';
    
    // Only real, physical servers get a card: an alias is just another name for one of
    // these. COALESCE because a row never processed by serverSave.php holds NULL, and
    // a plain "=0" would silently hide it. Tested on finalShortcutToServerID and not on
    // shortcutToServerID: the latter stores a mix of '', NULL and 0, and '' defeats
    // both "=0" and COALESCE.
    $sql='SELECT id, name, icon, serverType from servers where servertype=\'Workflow\''.
         ' and COALESCE(finalShortcutToServerID,0)=0';
    $stmt = $db->prepare($sql);
    $results=$stmt->execute();
    while(1)
    {
        $row=$results->fetchArray(SQLITE3_ASSOC);
        if (!$row) break;
//        for($i=0;$i<21;$i++)
        // servers.icon holds just a filename picked on servers.php, e.g. 'Python.svg'.
        // Empty (or naming a file no longer on disk) falls back to this serverType's
        // default, which for some types is an emoji rather than an image -- so let
        // serverIconHtml() decide between <img> and <span>.
        $ic=serverIconSrc($row['serverType'],$row['icon']);
        if ($ic=='') $ic=serverDefaultIcon($row['serverType']);
        echo '<div class="server-card">'.
                '<a style="text-decoration:none;" href="servers.php?idserver='.$row['id'].'">'.
                '<div class="server-card-edit">✏️</div></a>'.
                '<a style="text-decoration:none;" href="?idserver='.$row['id'].'">'.
                '<div class="server-card-icon">'.serverIconHtml($ic).'</div>'.
                '<div class="server-card-name">'.$row['name'].'</div></a></div>';
    }
} else
{
    $sql='SELECT name from servers where id=:ids';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':ids',$idserver);
    $results=$stmt->execute();
    $row=$results->fetchArray(SQLITE3_ASSOC);
    echo ' / <a href="?idserver='.$idserver.'">'.$row['name'].'</a>';
    $idAsset=1;
}
?>
</div></div>
<!-- History -->
<div class="history-section" style="overflow-x: auto;">
<h2>All Worflows</h2>       
<?php
$hiddenUrlParameters='<input type="hidden" name="idserver" value="'.$idserver.'">';
$sql='SELECT a.*, la.liketype';
if($isSuperAdmin==1) 
    $sql.=', 2 as rights'.
    ',a.name as name_new,a.shortDescription as shortDescription_new,a.status as status_new'.
    ' from Assets a'.
    ' LEFT JOIN likesAssets la ON a.id=la.idassetorcolumn and la.iduser='.$myid.
    ' LEFT JOIN departments d ON a.idDepartment=d.id'.
    ' LEFT JOIN servers s ON a.idserver=s.id'.
    ' where a.category>=200 '.$filter;
else
{
    $sql.=', ud.rights as rights'.
    ',COALESCE(ac.name,a.name) as name_new,COALESCE(ac.shortDescription,a.shortDescription) as shortDescription_new,COALESCE(ac.status,a.status) as status_new'.
    ' from Assets a'.
    ' LEFT JOIN likesAssets la ON a.id=la.idassetorcolumn and la.iduser='.$myid.
    ' LEFT JOIN departments d ON a.idDepartment=d.id'.
    ' LEFT JOIN servers s ON a.idserver=s.id'.
    ' LEFT JOIN AssetsChanges ac ON ac.rowId=a.id AND ac.changedByUserId='.$myid.
    ' INNER JOIN userDepartmentRights ud ON a.idDepartment=ud.idDepartment and ud.idUser='.$myid.
    ' where a.category>=200 '.$filter;
}
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
    echo '<tr class="tr-asset ';
    if (($isSuperAdmin==0)&&(
        ($row['name']!=$row['name_new'])||
        ($row['shortDescription']!=$row['shortDescription_new'])||
        ($row['status']!=$row['status_new'])))
        echo ' highlighted';
    echo '"><td class="history-icon" style="text-align: center;">'.
            getIcon($row['category']).'</td><td><a style="text-decoration:none;" href="workFlowDelete.php?idasset='.
            $row['id'].'"><img class="deleteicon" src="ressources/delete.svg" height="20px" style="vertical-align: middle; padding-right:5px;"/></a>'.
            '<a class="history-title" style="text-decoration:none;" href="oneWorkflow.php?idasset='.
            $row['id'].'">'.htmlspecialchars($row['name_new']).'</a></td><td>';
    if ($row['rights']>=2)
        echo '<div class="editable" data-id="'.$row['id'].
        	'" data-columnname="shortDescription" data-tablename="Assets">'.
        	($row['shortDescription_new']).' </div>';
    else
        echo $row['shortDescription'];
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
$db->exec("attach database '" . __DIR__ . "/../../db/MarvimUsers.sqlite' as dbu;");
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