<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Workflow</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php require "_pe_headerScripts.php"; ?>
<script>
function enableAdvEdit(el)
{
    el.innerText = "Advanced Edit ON";
    var i,editables=document.querySelectorAll('.advdelete');
    for (i=0; i<editables.length; i++)
        editables[i].style.display="inline";
}

function enableDisableEdit2(a)
{
    enableDisableEdit(a);
    if (!editing)
    {
        var el = document.getElementById('advEditButton');
        el.innerText = "Advanced Edit OFF";
        var i,editables=document.querySelectorAll('.advdelete');
        for (i=0; i<editables.length; i++)
            editables[i].style.display="none";
    }
}
</script>
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
    echo 'Error: No workflow specified.';
    die;
}
$newAsset=0;
if (isset($_REQUEST['newAsset'])) $newAsset=$_REQUEST['newAsset'];

date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$db->exec("attach database '".__DIR__ . "/../../db/MarvimUsers.sqlite' as dbu;");
$stmt = $db->prepare(
    'select a.*, s.name as servername, u.name as ownername, u.email as owneremail'.
    ' ,COALESCE(ac.shortDescription,a.shortDescription) as shortDescription_new,COALESCE(ac.longDescription,a.longDescription) as longDescription_new,COALESCE(ac.schema,a.schema) as schema_new,COALESCE(ac.tags,a.tags) as tags_new'.
    ' from Assets a'.
    ' LEFT JOIN Servers s ON a.idserver=s.id'.
    ' LEFT JOIN Users u ON a.idowner=u.id'.
    ' LEFT JOIN AssetsChanges ac ON ac.rowId=a.id AND ac.changedByUserId='.$myid.
    ' where a.id=:ids');
$stmt->bindValue(':ids',$idAsset);
$results=$stmt->execute();
$rowAsset=$results->fetchArray(SQLITE3_ASSOC);
if (!$rowAsset) 
{
    echo 'Error: Workflow '.$idAsset.' not found';
    die;
}
echo getIcon($rowAsset['category']).' Workflow ';
if (!$newAsset) echo $rowAsset['name']; // todo: afficher file,database,application,API
else echo '<div style="display:inline" class="editable" data-columnname2="name" data-tablename="Assets" data-id="'.
    $idAsset.'">'.$rowAsset['name'].'</div>';

echo '</h1><div><a class="server-hide-btn advdelete" style="display:none;text-decoration:none;" href="oneWorkFlowAddIO.php?idworkflow='.$idAsset.'&workflowDirection=input">Add Input</a> '.
    '<a class="server-hide-btn advdelete" style="display:none;text-decoration:none;" href="oneWorkFlowAddIO.php?idworkflow='.$idAsset.'&workflowDirection=output">Add Output</a> ';
?>
<button id="advEditButton" class="server-hide-btn deleteicon" onclick="enableAdvEdit(this)">Advanced Edit OFF</button>
<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit2(this)">Enable Edit</button>
<button class="server-hide-btn" onclick="toggleCardView()">Toogle Card/Table View</button></div>
</div>
    <div class="breadcrumb"><a href="home.php">Home</a> / <a href="workflow.php">Workflows</a>
<?php
echo '/ <a href="?idasset='.$idAsset.'">'.$rowAsset['name'].'</a>'
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
    <div class="edit-field">
        <label>FilePath</label>
        <div class="text-input-style">
<?php
echo '<div class="editable" data-columnname2="schema" data-tablename="Assets" data-id="'.
    $idAsset.'" data-highlight="'.($rowAsset['schema']!=$rowAsset['schema_new']).
    '">'.$rowAsset['schema_new'].'</div>';
?>            
        </div>
    </div>
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
   <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px;"><label>Long Description</label>
 <button id="llm-autocomplete-btn" onclick="llmAutoCompleteWorkflow()"
        style="display:none;padding:3px 12px;border:1px solid #10B981;border-radius:5px;
            background:#D1FAE5;color:#065F46;font-size:12px;font-weight:600;cursor:pointer;
            white-space:nowrap;line-height:1.6;">&#10024; Auto Complete</button>
            <span id="llm-autocomplete-status" style="font-size:12px;color:#64748b;"></span>
</div>
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
     ') </div><div class="extradata">&#128421;&#65039; <em>Server</em>: ';

echo '<select class="toxradio" data-columnname="idserver" data-tablename="Assets" data-id="'.
    $idAsset.'" onchange="saveContent(this.value,0,this);">';
    $stmt2=$db->prepare('select id,name from servers where serverType=\'Workflow\'');
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
<h2>All Inputs</h2>
<?php
function getIconColumn($dt)
{
    // U(String), K(key,unsigned int), F(double)
    return $dt; // todo: return an img for K and F
}

function displayIO($sql,$ii)     
{
    global $db;
    global $idAsset;
    echo '<table id="dataTable" class="table-asset table-view"><thead class="thead-asset">'.
        '<tr class="tr-asset">'.
        '<th class="th-asset" style="text-align: center, width:80px">Icon</th>'.
        '<th class="th-asset">'.$ii.' Name</th>'.
        '<th class="th-asset">Description</th>'.
        '<th class="th-asset" style="text-align: center, width: 50px;">Status</th>'.
        '<th class="th-asset" style="width: 80px;">Popularity</th>'.
        '<th class="th-asset" style="text-align: center;">_</th>'.
        '</tr></thead><tbody>';

    $stmt = $db->prepare($sql);
    $results=$stmt->execute();
    while(1)
    {
    // this is an error to use "htmlspecialchars()" anywhere here:
        $row=$results->fetchArray(SQLITE3_ASSOC);
        if (!$row) break;
        echo '<tr class="tr-asset"><td class="history-iconcol" style="text-align: center;">'.
                getIcon($row['datatype']).
                '<a style="text-decoration:none;display:none" class="advdelete" href="oneWorkFlowDelIO.php?idworkflow='.$idAsset.'&idasset='.
                $row['id'].'&workflowDirection='.$ii.'"><img src="ressources/delete.svg" height="20px" style="vertical-align: middle;"/></a>'.
                '</td><td><div class="history-title">'.
                '<a style="text-decoration:none;" href="table.php?idasset='.$row['id'].'">'.($row['name']).
                '</a></td><td><div>'.$row['shortDescription'].' </div></td><td style="text-align: center;"><div>'.
                getStatusDisplay($row['status']).'</div></td><td style="width: 80px;"><div class="popularity-bar">'.
                '<div class="popularity-fill" style="width: '.$row['popularity'].
                '%"></div></div></td></tr>'."\n";   
    }
    $results->finalize();
    echo '</tbody></table>';
}

$sql='SELECT a.category as datatype, a.id as id, a.name as name, a.shortDescription as shortDescription, a.status as status, a.popularity as popularity, 0 as rating, 0 as liketype from workflowIO wf '.
     'LEFT JOIN Assets a ON a.id=wf.idIO '.
     ' where wf.isInput=1 and wf.idWorkflow='.$_REQUEST['idasset'].' ';
displayIO($sql,'Input');
?>
<br><br>
<h2>All Ouputs</h2>
<?php
$sql='SELECT a.category as datatype, a.id as id, a.name as name, a.shortDescription as shortDescription, a.status as status, a.popularity as popularity, 0 as rating, 0 as liketype from workflowIO wf '.
     'LEFT JOIN Assets a ON a.id=wf.idIO '.
     ' where wf.isInput=0 and wf.idWorkflow='.$_REQUEST['idasset'].' ';
displayIO($sql,'Output');

// Full script path for the lineage viewer/API: schema holds the directory only,
// the file name lives in name (schema may still be a full path on old rows).
$scriptFull = (string)$rowAsset['schema_new'];
if ($scriptFull !== '' && $rowAsset['name'] !== ''
    && strtolower(substr($scriptFull, -strlen($rowAsset['name']))) !== strtolower($rowAsset['name'])) {
    $scriptFull = rtrim($scriptFull, '/') . '/' . $rowAsset['name'];
}
?>
</div>

<!-- Lineage graph embed ──────────────────────────────────────────────── -->
<div class="history-section" id="lineage-embed-section">
<h2>Lineage Graph</h2>
<div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
  <iframe id="lineage-iframe"
    src="lineage/lineage_viewer.html?context=workflow&script=<?php echo urlencode($scriptFull); ?>"
    style="width:100%;height:600px;border:none;display:block;"
    allow="same-origin">
  </iframe>
</div>
</div>
</div>
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
<script>
const el=document.getElementById("defaultTab");
if (el) el.click();
// Add listener for changes
if (handleMobileView)
{
    mediaQuery.addListener(handleMobileView);
    handleMobileView(mediaQuery);
}
initHugeRTEEditMain();
<?php
if ((isset($_REQUEST['advEdit']))||($newAsset))
{
  echo 'enableDisableEdit(document.getElementById("enableDisableButton")); '.
       'enableAdvEdit(document.getElementById("advEditButton"));';
}
require 'lineage/llmCommon.js';
require 'lineage/llmWorkFlow.js';
?>
</script>
</body>