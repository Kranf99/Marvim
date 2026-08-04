<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Glossary</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php require "_pe_headerScripts.php"; ?>
</head>
<body>
<?php 
require "_pe_starter.php";
?>
<!-- Content -->
<div class="content">
    <div class="main-section">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h1 style="font-size: 34px; color: #333">Glossary</h1>
<div><a class="server-hide-btn deleteicon" style="display:none; text-decoration:none;" href="glossaryAdd.php">Add New Definition</a>
<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit(this)">Enable Edit</button>
<button class="server-hide-btn" onclick="toggleCardView()">Toogle Card/Table View</button></div>
</div></div>
<div class="breadcrumb"><a href="home.php">Home</a> / Glossary
</div>
<!-- History -->
<div class="history-section" style="overflow-x: auto;">
<h2>All Definitions</h2>           
<?php

date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);

$hiddenUrlParameters='';
$sql='SELECT a.*, la.liketype';
if ($isSuperAdmin==1)
    $sql.=' from Glossary a'.
        ' LEFT JOIN likesGlossary la ON a.id=la.idassetorcolumn and la.iduser='.$myid.
        ' where toDelete=0 ';
else
    $sql.=',COALESCE(gc.name,a.name) as name_new,COALESCE(gc.shortDescription,a.shortDescription) as shortDescription_new,COALESCE(gc.longDescription,a.longDescription) as longDescription_new,COALESCE(gc.status,a.status) as status_new,COALESCE(gc.tags,a.tags) as tags_new'.
        ' from Glossary a'.
        ' LEFT JOIN likesGlossary la ON a.id=la.idassetorcolumn and la.iduser='.$myid.
        ' LEFT JOIN GlossaryChanges gc ON gc.rowId=a.id AND gc.changedByUserId='.$myid.
        ' where toDelete=0 ';
$filterOnAssetTable=true;
require "_pe_filters.php";
?>
<br><br>
    <table class="table-asset" id="dataTable">
        <thead class="thead-asset">
            <tr class="tr-asset">
                <th class="th-asset" style="width:0"></th>
                <th class="th-asset"><?php echoSortUrl('name','Word') ?></th>
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
        ($row['name']!=$row['name_new'])||
        ($row['shortDescription']!=$row['shortDescription_new'])||
        ($row['longDescription']!=$row['longDescription_new'])||
        ($row['status']!=$row['status_new'])||
        ($row['tags']!=$row['tags_new'])))
        echo " highlighted";
    echo '"><td style=""width:0"></td><td>'.
            '<a style="text-decoration:none;" href="glossaryDelete.php?idasset='.
            $row['id'].'" onclick="return confirm(\'Are you sure you want to delete this Word from the Glossary?\')"><img class="deleteicon" src="ressources/delete.svg" height="20px" style="vertical-align: middle; padding-right:5px;"/></a>'.
            '<a class="history-title" style="text-decoration:none;" href="glossaryOneDef.php?idasset='.
            $row['id'].'">'.htmlspecialchars($row['name']).'</a></td><td><div class="editable" data-id="'.$row['id'].
        	'" data-columnname="shortDescription" data-tablename="Glossary">'.
        	($row['shortDescription']).' </div></td><td style="text-align: center;">'.
            getStatusDisplay($row['status']).'</td><td style="width: 80px;"><div class="popularity-bar">'.
            '<div class="popularity-fill" style="width: '.$row['popularity'].
            '%"></div></div></td><td style="text-align: center;">'.
            '<div onclick="addlike(this,'.$row['id'].',\'Glossary\')">';
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
<button class="tablinks" onclick="openTab(event,'ActivityTab')" id="defaultTab">Activity</button>
</div>

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
<?php 
$idAsset=0;
require '_pe_footer.php'; 
require '_pe_tableJSFilter.html'; 
?>
</body>