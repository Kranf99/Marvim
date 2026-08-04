<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Definition</title>
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
$stmt = $db->prepare('select a.*, u.name as ownername, u.email as owneremail'.
        ' ,COALESCE(ac.changeId,0) as assetChangeId,COALESCE(ac.name,a.name) as name_new,COALESCE(ac.shortDescription,a.shortDescription) as shortDescription_new,COALESCE(ac.longDescription,a.longDescription) as longDescription_new,COALESCE(ac.status,a.status) as status_new,COALESCE(ac.tags,a.tags) as tags_new'.
        ' from Glossary a'.
        ' LEFT JOIN Users u ON a.idowner=u.id'.
        ' LEFT JOIN GlossaryChanges ac ON ac.rowId=a.id AND ac.changedByUserId='.$myid.
        ' where a.id=:ids');
$stmt->bindValue(':ids',$idAsset);
$results=$stmt->execute();
$rowAsset=$results->fetchArray(SQLITE3_ASSOC);
if (!$rowAsset)
{
    echo 'Error: Asset '.$idAsset.' not found';
    die;
}

echo' DEFINITION ';
if (!$newAsset) echo htmlspecialchars($rowAsset['name_new']);
else echo '<div style="display:inline" class="editable" data-columnname2="name" data-tablename="Glossary" data-id="'.
    $idAsset.'">'.$rowAsset['name_new'].'</div>';
?></h1>
<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit(this)">Enable Edit</button>
</div>
    <div class="breadcrumb"><a href="home.php">Home</a> / <a href="glossary.php">Glossary</a>
<?php
echo '/ '.$rowAsset['name_new']
?>                        
    </div>
</div>
<div style='overflow-x: auto;'>
<div class="edit-panel">
    <div class="edit-field">
        <label>Short Description</label>
        <div class="text-input-style">
<?php
echo '<div class="editable" data-columnname="shortDescription" data-tablename="Glossary" data-id="'.
    $idAsset.'" data-highlight="'.($rowAsset['shortDescription']!=$rowAsset['shortDescription_new']).
    '">'.$rowAsset['shortDescription_new'].'</div>';
?>            
        </div>
    </div>
    <div class="edit-field">
        <label>Tags</label>
        <div class="text-input-style">
<?php
echo '<div class="editable" data-columnname2="tags" data-tablename="Glossary" data-id="'.
    $idAsset.'" data-highlight="'.($rowAsset['tags']!=$rowAsset['tags_new']).
    '">'.$rowAsset['tags_new'].'</div>';
?>
        </div>
    </div>
    <div>
        <div class="status-row">
            <label class="status-label">Status</label>
<?php
echo '<div class="status-buttons" data-columnname="status" data-tablename="Glossary" data-id="'.
    $idAsset.'">';
?>            
                <input type="radio" name="status" id="status-uncertified" value="uncertified" class="toxradio" onclick="saveContent(0,0,this.parentElement);"
<?php if ($rowAsset['status_new']==0) echo 'checked'; ?>
                >
                <label for="status-uncertified" class="status-btn status-uncertified">Uncertified</label>
                <input type="radio" name="status" id="status-certified" value="certified" class="toxradio" onclick="saveContent(1,0,this.parentElement);"
<?php if ($rowAsset['status_new']==1) echo 'checked'; ?>
                >
                <label for="status-certified" class="status-btn status-certified">Certified</label>
                <input type="radio" name="status" id="status-do-not-use" value="do-not-use" class="toxradio" onclick="saveContent(2,0,this.parentElement);"
<?php if ($rowAsset['status_new']==2) echo 'checked'; ?>
                > 
                <label for="status-do-not-use" class="status-btn status-do-not-use">Do Not Use</label> 
            </div>
        </div>
    </div>
            
    <div class="edit-field edit-field--stacked">
        <label>Long Description</label>
<?php
echo '<textarea id="editorMain" rows="1" data-columnname="longDescription" data-tablename="Glossary" data-id="'.
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
     ') </div><div class="extradata">&#128336; <em>Last update</em>: '.
     $rowAsset['dateUpdated'].'</div><div class="extradata">&#9889; <em>Creation date</em>: '.$rowAsset['dateCreated'].'</div>';
?>
</div></div></div></div>
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