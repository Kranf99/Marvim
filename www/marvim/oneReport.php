<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Report</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<script>
async function saveDepartment(content,el)
{
  const id = el.dataset.id;
  var data="{\"idasset\":"+id+",\"iddept\":"+content+"}";
//  console.log(data);
  try {
    const response = await fetch('oneReportChangeDpt.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: data});
    const result = await response.text();
    console.log('Saved:', id, result);
  } catch (err) {
    console.error('Save failed:', err);
  }
}
</script>
<?php require "_pe_headerScripts.php"; ?>
</head>
<body>
<?php 
require "_pe_starter.php";
?>
<!-- Content -->
<div class="content">
    <div class="main-section">
        <div >
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <!-- <h1 class="page-title">💾 Data Sources</h1> -->
            <h1 style="font-size: 34px; color: #333">
<?php
$idAsset=-1;
if (isset($_REQUEST['idasset'])) $idAsset=$_REQUEST['idasset'];
else
{
    echo 'Error: No Asset specified.';
    die;
}
$newAsset=0;
if (isset($_REQUEST['newAsset'])) $newAsset=$_REQUEST['newAsset'];

date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$db->exec("attach database '".__DIR__ . "/../../db/MarvimUsers.sqlite' as dbu;");
$stmt = $db->prepare(
        'SELECT a.*, s.name as servername, u.name as ownername, u.email as owneremail, d.icon as icon, d.name as dptname'.
        ' ,COALESCE(ac.changeId,0) as assetChangeId,COALESCE(ac.idserver,a.idserver) as idserver_new,COALESCE(ac.name,a.name) as name_new,COALESCE(ac.shortDescription,a.shortDescription) as shortDescription_new,COALESCE(ac.longDescription,a.longDescription) as longDescription_new,COALESCE(ac.status,a.status) as status_new,COALESCE(ac.tags,a.tags) as tags_new'.
        ' FROM Assets a'.
        ' LEFT JOIN Servers s ON a.idserver=s.id'.
        ' LEFT JOIN Users u ON a.idowner=u.id'.
        ' LEFT JOIN departments d ON a.idDepartment=d.id'.
        ' LEFT JOIN AssetsChanges ac ON ac.rowId=a.id AND ac.changedByUserId='.$myid.
        ' where a.id=:ids');
$stmt->bindValue(':ids',$idAsset);
$results=$stmt->execute();
$rowAsset=$results->fetchArray(SQLITE3_ASSOC);
if (!$rowAsset) 
{
    echo 'Error: Asset '.$idAsset.' not found';
    die;
}
echo $rowAsset['icon'].' REPORT ';

if ($isSuperAdmin) $rights=8;
else
{
    $stmt = $db->prepare('select rights from userDepartmentRights where idUser=:idu and idDepartment=:iddpt');
    $stmt->bindValue(':idu',$myid);
    $stmt->bindValue(':iddpt',$rowAsset['idDepartment']);
    $results=$stmt->execute();
    $row=$results->fetchArray(SQLITE3_ASSOC);
    $rights=$row ? $row['rights']:0;
    if ($rights<1)
    {
        echo 'Error: You don\'t have the Rights to see Asset '.$idAsset.'.';
        die;
    }
    if ($rights<2) $newAsset=0;
}

if (!$newAsset) echo $rowAsset['name']; // todo: afficher file,database,application,API
else echo '<div style="display:inline" class="editable" data-columnname2="name" data-tablename="Assets" data-id="'.
    $idAsset.'">'.$rowAsset['name'].'</div>';

    echo '</h1><div>';
if ($rights>=4)
    echo '<a id="addButton" class="server-hide-btn deleteicon" style="display:none;text-decoration:none;" href="oneReportNewKPI.php?'.
	     http_build_query($_REQUEST).'">Add a KPI</a> ';
if ($rights>=2)
    echo '<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit(this)">Enable Edit</button>';
?>
<button class="server-hide-btn" onclick="toggleCardView()">Toogle Card/Table View</button></div>
</div>
    <div class="breadcrumb"><a href="home.php">Home</a> / <a href="report.php">Reports</a>
<?php
echo '/ <a href="">'.$rowAsset['name'].'</a>'
?>                        
    </div>
</div>
<div style='overflow-x: auto;'>
<div class="edit-panel">
    <div class="edit-field">
        <label>Short Description</label>
        <div class="text-input-style">
<?php
echo '<div class="editable" data-columnname="shortDescription" data-tablename="Assets" data-id="'.
    $idAsset.'" data-highlight="'.($rowAsset['shortDescription']!=$rowAsset['shortDescription_new']).
    '">'.$rowAsset['shortDescription_new'].'</div>';
?>
        </div>
    </div>
<?php
require "_pe_selectDpt.php";
?>
    <div class="edit-field">
        <label>Tags</label>
        <div class="text-input-style">
<?php
echo '<div class="editable" data-columnname2="tags" data-tablename="Assets" data-id="'.
    $idAsset.'" data-highlight="'.($rowAsset['tags']!=$rowAsset['tags_new']).
    '">'.$rowAsset['tags_new'].'</div>';
?>
        </div>
    </div>
    <div>
        <div class="status-row">
            <label class="status-label">Status</label>
<?php
echo '<div class="status-buttons" data-columnname="status" data-tablename="Assets" data-id="'.
    $idAsset.'">';
?>            
                <input type="radio" name="status" id="status-uncertified" value="uncertified" class="toxradio" onclick="saveContent(0,0,this.parentElement);"
<?php if ($rowAsset['status']==0) echo 'checked'; ?>
                >
                <label for="status-uncertified" class="status-btn status-uncertified">Uncertified</label> 
                <input type="radio" name="status" id="status-certified" value="certified" class="toxradio" onclick="saveContent(1,0,this.parentElement);"
<?php if ($rowAsset['status']==1) echo 'checked'; ?>
                > 
                <label for="status-certified" class="status-btn status-certified">Certified</label> 
                <input type="radio" name="status" id="status-do-not-use" value="do-not-use" class="toxradio" onclick="saveContent(2,0,this.parentElement);"
<?php if ($rowAsset['status']==2) echo 'checked'; ?>
                > 
                <label for="status-do-not-use" class="status-btn status-do-not-use">Do Not Use</label> 
            </div>
        </div>
    </div>
            
    <div class="edit-field edit-field--stacked">
        <label>Long Description</label>
<?php
echo '<textarea id="editorMain" rows="1" data-columnname="longDescription" data-tablename="Assets" data-id="'.
    $idAsset.'">'.$rowAsset['longDescription_new'].'</textarea></div>';
if ($myid!=$rowAsset['idowner'])
{
?>
    <div class="status-buttons">
        <input type="button" name="status" id="status-request-access" value="request-access" class="status-btn status-request-access" 
        style="min-width: 120px;padding: 10px 16px;border-radius: 6px;font-size: 13px;font-weight: 500;cursor: pointer;border: 2px solid transparent;"/> 
    </div>
<?php
}
echo '<div id="extradata" style="display:flex;flex-wrap:wrap;gap:10px;margin:14px 0 6px;">';
echo '<div class="extradata">&#128100; <em>Owner</em>: '.$rowAsset['ownername'].' (id: '.$rowAsset['idowner'].' ; email: '.$rowAsset['owneremail'].
     ') </div><div class="extradata">&#128421;&#65039; <em>Server</em>: '.
     '<select class="toxradio" data-columnname="idserver" data-tablename="Assets" data-id="'.
        $idAsset.'" onchange="saveContent(this.value,0,this);">';
$stmt2=$db->prepare('select id,name from servers where serverType=\'Reporting\'');
$results=$stmt2->execute();
for(;;)
{
    $row=$results->fetchArray(SQLITE3_ASSOC);
    if (!$row) break;
    echo '<option value="'.$row['id'].'"';
    if ($row['id']==$rowAsset['idserver']) echo ' selected';
    echo '>'. htmlspecialchars($row['name']).'</option>';
}
echo '</select>';
$row=null;
$results->finalize();
$stmt2->close();

echo '</div><div class="extradata">&#128336; <em>Last update</em>: '.
     $rowAsset['dateUpdated'].'</div><div class="extradata">&#9889; <em>Creation date</em>: '.$rowAsset['dateCreated'].'</div>';
?>
    </div>
</div>
<!-- History -->
<div class="history-section">
<h2>All KPI's</h2>
<?php
$hiddenUrlParameters='<input type="hidden" name="idasset" value="'.$idAsset.
    '"><input type="hidden" name="newAsset" value="'.$newAsset.'">';
$sql='SELECT a.*, la.liketype'.
     ',COALESCE(kc.name,a.name) as name_new,COALESCE(kc.shortDescription,a.shortDescription) as shortDescription_new,COALESCE(kc.status,a.status) as status_new,COALESCE(kc.tags,a.tags) as tags_new'.
     ' from KPI a'.
     ' LEFT JOIN likesKPI la ON a.id=la.idassetorcolumn AND la.iduser='.$myid.
     ' LEFT JOIN KPIChanges kc ON kc.rowId=id AND kc.fromAssetChangeId='.$rowAsset['assetChangeId'].
     ' where idasset='.$_REQUEST['idasset'];
$filterOnAssetTable=false;
require "_pe_filters.php";
?>
<br><br>
    <table id="dataTable" class="table-asset">
        <thead class="thead-asset">
            <tr class="tr-asset">
                <th class="th-asset" style="width:0px"></th>
                <th class="th-asset"><?php echoSortUrl('name','KPI Name') ?></th>
                <th class="th-asset"><?php echoSortUrl('description','Description') ?></th>
                <th class="th-asset" style="width: 0px;"><?php echoSortUrl('status','Status') ?></th>
                <th class="th-asset" style="width: 80px;"><?php echoSortUrl('popularity','Popularity') ?></th>
                <th class="th-asset" style="text-align: center;"><?php echoSortUrl('rating','Rating') ?></th>
            </tr>
        </thead>
        <tbody>
<?php
for($i=0;$i<count($array_rows);$i++)
{
// this is an error to use "htmlspecialchars()" anywhere here:
	$row=$array_rows[$i];
    echo '<tr class="tr-asset"><td></td><td><div class="history-title">';
    if ($rights>=4)
        echo '<a style="text-decoration:none;display:none" class="deleteicon" href="oneReportDelKPI.php?idkpi='.
                $row['id'].'&'.http_build_query($_REQUEST).'"><img src="ressources/delete.svg" height="20px" style="vertical-align: middle;"/></a>';
    echo '<div class="editable" style="display:inline" data-id="'.$row['id'].
            '" data-columnname2="name" data-tablename="KPI">'.($row['name_new']).
            '</div></div></td><td><div class="editable" data-id="'.$row['id'].
            '" data-columnname="shortDescription" data-tablename="KPI" data-highlight="'.
            ($row['shortDescription_new']!=$row['shortDescription']).'">'.
        	($row['shortDescription_new']).' </div></td><td style="text-align: center;"><div class="statusEdit" data-id="'.$row['id'].
            '" data-status="'.$row['status_new'].'" data-tablename="KPI">'.
            getStatusDisplay($row['status_new']).'</div></td><td style="width: 80px;"><div class="popularity-bar">'.
            '<div class="popularity-fill" style="width: '.$row['popularity'].
            '%"></div></div></td><td style="text-align: center;"><div onclick="addlike(this,'.$row['id'].',\'KPI\')">';
    if ($row['rating']!=0) { echo $row['rating'].' '; }
    if ($row['liketype']==1) echo '<img src="ressources/like.svg" height="15px"/>';
    else  echo '<img src="ressources/nolike.svg" height="15px"/>';
    echo '</div></td></tr>'."\n";   
}
$results->finalize();
?>
            </tbody>
    </table>
    </div></div>
</div>

<!-- Right sidebar -->
<div class="sidebar-right">

<!-- Tab links -->
<div class="tab">
  <button class="tablinks" onclick="openTab(event,'ChatTab')" id="defaultTab">Conversation</button>
  <button class="tablinks" onclick="openTab(event,'ActivityTab')">Activity</button>
</div>

<!-- Tab content -->
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
<?php
//echo $rowAsset['longDescription']."<br>";
//echo $rowAsset['longDescription_new']."<br>";
?>
<script>
<?php
if ($rowAsset['longDescription']!=$rowAsset['longDescription_new'])
    echo 'initHugeRTEEditMain("#aeecff");';
else echo 'initHugeRTEEditMain();';

if ((isset($_REQUEST['edit']))||($newAsset))
{
  echo 'enableDisableEdit(document.getElementById("enableDisableButton"));';
}
?>
</script>
</body>