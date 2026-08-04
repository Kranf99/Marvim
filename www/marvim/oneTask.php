<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Task</title>
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
            <h1 style="font-size: 34px; color: #333">TASK 
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
$db->exec("attach database '" . __DIR__ . "/../../db/MarvimUsers.sqlite' as dbu;");
$stmt = $db->prepare('select a.*, u.name as ownername, u.email as owneremail from Tasks a'.
        ' LEFT JOIN Users u ON a.assignedToUserId=u.id '.
        ' where a.id=:ids');
$stmt->bindValue(':ids',$idAsset);
$results=$stmt->execute();
$rowAsset=$results->fetchArray(SQLITE3_ASSOC);
if (!$rowAsset)
{
    echo 'Error: Asset '.$idAsset.' not found';
    die;
}

if (!$newAsset) echo $rowAsset['name'];
else echo '<div style="display:inline" class="editable" data-columnname2="name" data-tablename="assets" data-id="'.
    $idAsset.'">'.$rowAsset['name'].'</div>';

echo '</h1><div><a id="addButton" class="server-hide-btn deleteicon" style="display:none;text-decoration:none;" href="oneTaskNewMilestone.php?'.
	 http_build_query($_REQUEST).'">Add a Milestone</a> ';
?>
<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit(this)">Enable Edit</button>
<button class="server-hide-btn" onclick="toggleCardView()">Toogle Card/Table View</button></div>
</div>
    <div class="breadcrumb"><a href="home.php">Home</a> / <a href="task.php">Tasks</a>
<?php
echo '/ <a href="">'.$rowAsset['name'].'</a>'
?>
    </div>
</div>
<div style='overflow-x: auto;'>
<div class="edit-panel">
<?php    

function getFieldChanges($r)
{
    $table='';
    if ($r['name']&&($r['name']!=$r['name_old'])) 
        $table.='<tr class="tr-asset"><td>Name</td><td>'.($r['name_old']).'</td><td>'.($r['name']).'</td></tr>';

    if ($r['shortDescription']&&($r['shortDescription']!=$r['shortDescription_old'])) 
        $table.='<tr class="tr-asset"><td>Short Description</td><td>'.($r['shortDescription_old']).'</td><td>'.($r['shortDescription']).'</td></tr>';

    if ($r['longDescription']&&($r['longDescription']!=$r['longDescription_old'])) 
        $table.='<tr class="tr-asset"><td>Long Description</td><td>'.($r['longDescription_old']).'</td><td>'.($r['longDescription']).'</td></tr>';

    if ($r['status']&&($r['status']!=$r['status_old'])) 
        $table.='<tr class="tr-asset"><td>Status</td><td>'.htmlspecialchars($r['status_old']).'</td><td>'.htmlspecialchars($r['status']).'</td></tr>';

    if ($r['tags']&&($r['tags']!=$r['tags_old'])) 
        $table.='<tr class="tr-asset"><td>Tags</td><td>'.htmlspecialchars($r['tags_old']).'</td><td>'.htmlspecialchars($r['tags']).'</td></tr>';

    if (isset($r['schema'])&&$r['schema']&&($r['schema']!=$r['schema_old']))
        $table.='<tr class="tr-asset"><td>Schema</td><td>'.htmlspecialchars($r['schema_old']).'</td><td>'.htmlspecialchars($r['schema']).'</td></tr>';

    if (isset($r['idserver'])&&$r['idserver']&&($r['idserver']!=$r['idserver_old'])) 
        $table.='<tr class="tr-asset"><td>Server</td><td>'.htmlspecialchars($r['server_old']).'</td><td>'.htmlspecialchars($r['server']).'</td></tr>';
    return $table;
}

function getColumnChanges($r)
{
    $t=[null,null,null,null]; $nc=0;
    if ($r['cname']&&($r['cname']!=$r['cname_old'])) 
    {
        $t[0]='<td>Name</td><td>'.htmlspecialchars($r['cname_old']).'</td><td>'.htmlspecialchars($r['cname']).'</td>';
        $nc++;
    }

//    echo 'sd='.$r['cshortDescription']."<br>";
//    echo 'sdo='.$r['cshortDescription_old']."<br>";

    if ($r['cshortDescription']&&($r['cshortDescription']!=$r['cshortDescription_old'])) 
    {
        $t[$nc]='<td>Short Description</td><td>'.htmlspecialchars($r['cshortDescription_old']).'</td><td>'.htmlspecialchars($r['cshortDescription']).'</td>';
        $nc++;
    }

    if ($r['cstatus']&&($r['cstatus']!=$r['cstatus_old'])) 
    {
        $t[$nc]='<td>Status</td><td>'.htmlspecialchars($r['cstatus_old']).'</td><td>'.htmlspecialchars($r['cstatus']).'</td>';
        $nc++;
    }

    if ($r['ctags']&&($r['ctags']!=$r['ctags_old'])) 
    {
        $t[$nc]='<td>Tags</td><td>'.htmlspecialchars($r['ctags_old']).'</td><td>'.htmlspecialchars($r['ctags']).'</td>';
        $nc++;
    }

    if (!$nc) return '';
    $ctable='<tr class="tr-asset" rowspan="'.$nc.'"><td>'.$r['cname_old'].'</td>'.$t[0].'</tr>'."\n";
    if ($nc>1) $ctable.='<tr class="tr-asset">'.$t[1].'</tr>'."\n";
    if ($nc>2) $ctable.='<tr class="tr-asset">'.$t[2].'</tr>'."\n";
    if ($nc>3) $ctable.='<tr class="tr-asset">'.$t[3].'</tr>'."\n";
    return $ctable;
}

$tt=(int)$rowAsset['taskType'];
$txt='';
if (($tt>500)&&($rowAsset['changeId']!=null))
{
    if ($rowAsset['changeTable']=='Assets')
    {
        if (($tt==510)||($tt==610)||($tt==520)||($tt==620))
        {
            $sql=' SELECT a.*, c.changeId as cchangeId, c.name as cname,c.name_old as cname_old, '.
                ' c.shortDescription as cshortDescription, c.shortDescription_old as cshortDescription_old,'.
                ' c.status as cstatus, c.status_old as cstatus_old,'.
                ' c.tags as ctags, c.tags_old as ctags_old,'.
                ' u.name as uname, u.email as uemail FROM AssetsChanges a ';
            if (($tt==510)||($tt==610))
                $sql.=' LEFT JOIN KPIChanges c ON c.fromAssetChangeId=a.changeId';
            else if (($tt==520)||($tt==620))
                $sql.=' LEFT JOIN columnsChanges c ON c.fromAssetChangeId=a.changeId';
        } else
            $sql=' SELECT a.*,'.
                 ' u.name as uname, u.email as uemail FROM AssetsChanges a ';
        $sql.=' LEFT JOIN dbu.users u on u.id=a.changedByUserId'.
            ' WHERE a.changeId=:changeID';
    //    echo $sql;
    //    echo $rowAsset['changeId'];

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':changeID',$rowAsset['changeId']);
        $results=$stmt->execute();
        $rr=$results->fetchArray(SQLITE3_ASSOC);

        if ($rr)
        {
            $txt='<h1 style="font-size:22px;color:#1e3a8a;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #e5e7eb;">Report: '.htmlspecialchars($rr['name_old']).' changed</h1>';
            if ($tt<600)
            {
                $txt.='<div class="status-uncertified" style="padding:12px 16px;border-radius:6px;margin-bottom:16px;">Your input is required to approve or dismiss the proposed changes.</div>';
            } else
            {
                $txt.='<div class="status-request-access" style="padding:12px 16px;border-radius:6px;margin-bottom:16px;">This is a notification for your information.</div>';
            }
            $txt.='<p style="margin-bottom:20px;color:#64748b;font-size:14px;">Changed by <strong style="color:#374151;">'.htmlspecialchars($rr['uname']).'</strong>'.
                    ' ('.htmlspecialchars($rr['uemail']).') on '.$rr['updatedAt'].'.</p>';

            $table=getFieldChanges($rr);
            if ($table!='')
            {
                $txt.='<h2 style="font-size:16px;color:#475569;margin:20px 0 10px;">Changed field(s) inside Report:</h2>'.
                        '<table class="table-asset table-view"><thead class="thead-asset"><tr><th class="th-asset">Field</th><th class="th-asset">Old Value</th><th class="th-asset">New Value</th></tr></thead><tbody>'."\n".
                        $table.'</tbody></table>';
            }

            if (($tt==510)||($tt==610)||($tt==520)||($tt==620))
            {
                $ctable='';
                while ($rr)
                {
                    $ctable.=getColumnChanges($rr);
                    $rr=$results->fetchArray(SQLITE3_ASSOC);
                }
                if ($ctable!='')
                {
                    $txt.='<h2 style="font-size:16px;color:#475569;margin:20px 0 10px;">Changed KPI definition(s):</h2>'.
                            '<table class="table-asset table-view"><thead class="thead-asset"><tr><th class="th-asset">Column Name</th><th class="th-asset">Field</th><th class="th-asset">Old Value</th><th class="th-asset">New Value</th></tr></thead><tbody>'."\n".
                            $ctable.'</tbody></table>';
                }
            }
        }
    } else if ($rowAsset['changeTable']=='Glossary')
    {
        $sql=' SELECT a.*,u.name as uname, u.email as uemail FROM GlossaryChanges a ';
        $sql.=' LEFT JOIN dbu.users u on u.id=a.changedByUserId'.
            ' WHERE a.changeId=:changeID';
    //    echo $sql;
    //    echo $rowAsset['changeId'];

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':changeID',$rowAsset['changeId']);
        $results=$stmt->execute();
        $rr=$results->fetchArray(SQLITE3_ASSOC);

        if ($rr)
        {
            $txt='<h1 style="font-size:22px;color:#1e3a8a;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #e5e7eb;">Report: '.htmlspecialchars($rr['name_old']).' changed</h1>';
            if ($tt<600)
            {
                $txt.='<div class="status-uncertified" style="padding:12px 16px;border-radius:6px;margin-bottom:16px;">Your input is required to approve or dismiss the proposed changes.</div>';
            } else
            {
                $txt.='<div class="status-request-access" style="padding:12px 16px;border-radius:6px;margin-bottom:16px;">This is a notification for your information.</div>';
            }
            $txt.='<p style="margin-bottom:20px;color:#64748b;font-size:14px;">Changed by <strong style="color:#374151;">'.htmlspecialchars($rr['uname']).'</strong>'.
                    ' ('.htmlspecialchars($rr['uemail']).') on '.$rr['updatedAt'].'.</p>';

            $table=getFieldChanges($rr);
            if ($table!='')
            {
                $txt.='<h2 style="font-size:16px;color:#475569;margin:20px 0 10px;">Changed field(s) inside Report:</h2>'.
                        '<table class="table-asset table-view"><thead class="thead-asset"><tr><th class="th-asset">Field</th><th class="th-asset">Old Value</th><th class="th-asset">New Value</th></tr></thead><tbody>'."\n".
                        $table.'</tbody></table>';
            }
        }
    }
}

if (($tt>=500)&&($tt<600))
{ 
    if ($rowAsset['changeId']!=null)
    { ?>
<form method="POST" action="oneTaskResolve.php">
  <input type="hidden" name="idasset" value="<?= (int)$idAsset ?>">
  <input type="hidden" name="txt"     value="<?= htmlspecialchars($txt, ENT_QUOTES) ?>">
  <div style="display:flex;gap:10px;">
    <button type="submit" name="action" value="accept" class="btn-accept" onclick="return confirm('Accept these changes and apply them?')">&#10003; Accept Changes</button>
    <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Reject these changes?')">&#10007; Reject Changes</button>
  </div>
</form>
<?php
    } else 
    {
        echo '<div style="display:flex;gap:10px;">';
        if ($rowAsset['completion']==2)
            echo '<button class="btn-accept">&#10003; Changes accepted</button>';
        else
            echo '<button class="btn-reject">&#10007; Changes Rejected </button>';
        echo '</div>';
    }
    echo '&nbsp;<div class="edit-field edit-field--stacked">';
} else if ($tt==100) { ?>
&nbsp;    
<div>
        <div class="status-row" style="padding-bottom:10px">
            <label class="status-label">Priority</label>
<?php
echo '<div class="status-buttons" data-columnname="urgency" data-tablename="Tasks" data-id="'.
    $idAsset.'">';
?>
                <input type="radio" name="urgency" id="priority-low" value="1" class="toxradio" onclick="saveContent(1,0,this.parentElement);"
<?php if ($rowAsset['urgency']==1) echo 'checked'; ?>
                >
                <label for="priority-low" class="status-btn status-certified">Low</label>
                <input type="radio" name="urgency" id="priority-medium" value="2" class="toxradio" onclick="saveContent(2,0,this.parentElement);"
<?php if ($rowAsset['urgency']==2) echo 'checked'; ?>
                >
                <label for="priority-medium" class="status-btn status-uncertified">Medium</label>
                <input type="radio" name="urgency" id="priority-high" value="3" class="toxradio" onclick="saveContent(3,0,this.parentElement);"
<?php if ($rowAsset['urgency']==3) echo 'checked'; ?>
                >
                <label for="priority-high" class="status-btn status-do-not-use">High</label>
            </div>
        </div>
    </div>
<?php } 
if ($tt>=500)
{
    echo '<div class="edit-field"><label>Description</label><div class="text-input-style">';
    if ($rowAsset['changeId']==null) echo $rowAsset['description'];
    else echo $txt;
    echo '</div></div></div>';
}
?>
    <div>
        <div class="status-row" style="padding-bottom:10px">
            <label class="status-label">Status</label>
<?php
echo '<div class="status-buttons" data-columnname="status" data-tablename="Tasks" data-id="'.
    $idAsset.'">';
?>
                <input type="radio" name="status" id="status-uncertified" value="pending" class="toxradio" onclick="saveContent(0,0,this.parentElement);"
<?php if ($rowAsset['status']==0) echo 'checked'; ?>
                >
                <label for="status-uncertified" class="status-btn status-uncertified">pending</label>
                <input type="radio" name="status" id="status-certified" value="on-track" class="toxradio" onclick="saveContent(1,0,this.parentElement);"
<?php if ($rowAsset['status']==1) echo 'checked'; ?>
                >
                <label for="status-certified" class="status-btn status-certified">On-Track</label>
                <input type="radio" name="status" id="status-do-not-use" value="Late" class="toxradio" onclick="saveContent(2,0,this.parentElement);"
<?php if ($rowAsset['status']==2) echo 'checked'; ?>
                >
                <label for="status-do-not-use" class="status-btn status-do-not-use">Late</label>
            </div>
        </div>
    </div>
     <div class="edit-field">
        <label>Completion</label>
        <div class="text-input-style">
<?php
echo '<div class="editable" data-columnname2="completion" data-tablename="Tasks" data-id="'.
    $idAsset.'">'.$rowAsset['completion'].'</div>';
?>
        </div>
    </div>
     <div class="edit-field edit-field--stacked">
        <label>Execution Notes</label>
<?php
echo '<textarea id="editorMain" rows="1" data-columnname="ExecutionNotes" data-tablename="Tasks" data-id="'.
    $idAsset.'">'.$rowAsset['ExecutionNotes'].'</textarea></div>';
if ($myid!=$rowAsset['assignedToUserId'])
{
?>
    <div class="status-buttons">
        <input type="button" name="status" id="status-request-access" value="request-access" class="status-btn status-request-access"
        style="min-width: 120px;padding: 10px 16px;border-radius: 6px;font-size: 13px;font-weight: 500;cursor: pointer;border: 2px solid transparent;"/>
    </div>
<?php
}
echo '<div id="extradata" style="display:flex;flex-wrap:wrap;gap:10px;margin:14px 0 6px;">';
echo '<div class="extradata">&#128100; <em>Owner</em>: '.$rowAsset['ownername'].' (id: '.$rowAsset['assignedToUserId'].' ; email: '.$rowAsset['owneremail'].
     ') </div><div class="extradata">&#128336; <em>Last update</em>: '.
     $rowAsset['dateUpdated'].'</div><div class="extradata">&#9889; <em>Creation date</em>: '.$rowAsset['dateCreated'].'</div>';
?>
    </div>
</div>
<!-- Milestones -->
<div class="history-section">
<h2>All Milestones</h2>
<?php
$hiddenUrlParameters='<input type="hidden" name="idasset" value="'.$idAsset.
    '"><input type="hidden" name="newAsset" value="'.$newAsset.'">';
$sql='SELECT a.* from Milestones a '.
     ' where idasset='.$_REQUEST['idasset'].' ';
$filterOnAssetTable=false;
require "_pe_filters.php";
?>
<br><br>
    <table id="dataTable" class="table-asset">
        <thead class="thead-asset">
            <tr class="tr-asset">
                <th class="th-asset" style="width:0px"></th>
                <th class="th-asset"><?php echoSortUrl('name','Milestone Name') ?></th>
                <th class="th-asset"><?php echoSortUrl('description','Description') ?></th>
                <th class="th-asset" style="width: 8px;"><?php echoSortUrl('dueDate','Due Date') ?></th>
                <th class="th-asset" style="width: 0px;"><?php echoSortUrl('status','Status') ?></th>
                <th class="th-asset" style="width: 0px;"><?php echoSortUrl('completion','Completion') ?></th>
            </tr>
        </thead>
        <tbody>
<?php
for($i=0;$i<count($array_rows);$i++)
{
	$row=$array_rows[$i];
    echo '<tr class="tr-asset"><td></td><td><div class="history-title">'.
            '<a style="text-decoration:none;display:none" class="deleteicon" href="oneTaskDelMilestone.php?idmilestone='.
            $row['id'].'&'.http_build_query($_REQUEST).'"><img src="ressources/delete.svg" height="20px" style="vertical-align: middle;"/></a>'.
            '<div class="editable" style="display:inline" data-id="'.$row['id'].
            '" data-columnname2="name" data-tablename="Milestones">'.($row['name']).
            '</div></div></td><td><div class="editable" data-id="'.$row['id'].
            '" data-columnname="shortDescription" data-tablename="Milestones">'.
        	($row['shortDescription']).' </div></td><td style="text-align: center;"><div class="editable" data-id="'.$row['id'].
            '" data-columnname="dueDate" data-tablename="Milestones">'.
            ($row['dueDate']).'</div></td><td style="text-align: center;"><div class="statusEdit" data-id="'.$row['id'].
            '" data-status="'.$row['status'].'" data-tablename="Milestones">'.
            getStatusDisplay($row['status']).'</div></td><td style="width: 80px;"><div class="popularity-bar">'.
            '<div class="popularity-fill" style="width: '.$row['Completion'].
            '%"></div></div></td></tr>'."\n";
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
<script>
initHugeRTEEditMain();
<?php
if ((isset($_REQUEST['edit']))||($newAsset))
{
  echo 'enableDisableEdit(document.getElementById("enableDisableButton"));';
}
?>
</script>
</body>