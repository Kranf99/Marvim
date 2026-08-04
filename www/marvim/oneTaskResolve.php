<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo '{"status":"error","msg":"not authenticated"}';
    exit;
}
$myid = (int)$_SESSION['id'];
require_once '_pe_addEvent.php';

$taskId=-1;
if (isset($_REQUEST['idasset'])) $taskId=(int)$_REQUEST['idasset'];
else
{
    header('Location: tasks.php');
    die;
}
$action='';
if (isset($_REQUEST['action'])) $action=$_REQUEST['action'];
else
{
    header('Location: oneTask.php?idasset='.$taskId.'&message=no+action+defined');
	exit();
}

$txt='';
if (isset($_REQUEST['txt'])) $txt=$_REQUEST['txt'];
else
{
    header('Location: oneTask.php?idasset='.$taskId.'&message=no+txt+description+defined');
	exit();
}
header('Content-Type: application/json');

if (!in_array($action, ['accept', 'reject'])) {
    header('Location: oneTask.php?idasset='.$taskId.'&message=invalid+action');
    exit;
}

date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode=WAL;');
$now = date('Ymd H:i:s');

// Load the task and verify it belongs to the current user
$task = $db->querySingle("SELECT * FROM Tasks WHERE id=$taskId and changeId is not NULL", true);

// todo: check the is of the assignedToUserId
//if (!$task || (int)$task['assignedToUserId'] !== $myid) || $task['completion'] > 0) 
if (!$task || $task['completion'] > 0) 
{
    $db->close();
    header('Location: oneTask.php?idasset='.$taskId.'&message=task+not+found+or+not+authorised');
    exit;
}

$tableName=$task['changeTable'];
$changeId=(int)$task['changeId'];
$changesTable = $tableName.'Changes';
//$rowId        = (int)$task['rowId'];

if ($action === 'accept') 
{
    // ── column-based tables: apply latest snapshot ───────────────────────────
    $r = $db->querySingle("SELECT * FROM $changesTable WHERE changeId=$changeId", true);
    if (!$r) {
        $db->close();
        header('Location: oneTask.php?idasset='.$taskId.'&message=no+changes+found');
        exit;
    }

//    foreach ($r as $key => $value) {
//        echo $key .'='.$value. "\n";
//    }

    $colCopyTableMap = [
        'Assets'   => ['schema_old','idserver_old','name_old','shortDescription_old',
            'longDescription_old','status_old','idowner_old','tags_old','category'],
        'Glossary' => ['shortDescription_old','longDescription_old','status_old','tags_old'],
        'servers'  => ['name_old','serverType_old','description_old','tags_old']
    ];
    $colOrigTableMap = [
        'Assets'   => ['schema','idserver','name','shortDescription',
            'longDescription','status','idowner','tags','category'],
        'Glossary' => ['shortDescription','longDescription','status','tags'],
        'servers'  => ['name','serverType','description','tags']
    ];

    $co=$colCopyTableMap[$tableName];
    $cn=$colOrigTableMap[$tableName];
    $len=count($co);
    $sql='';
    for($i=0;$i<$len;$i++)
    { 
        $n=$cn[$i];
        $ncontent=$r[$n];
        if (($ncontent!=null)&&($ncontent!=$r[$co[$i]]))
            $sql.=$n.'=:'.$n.',';
    }
    if (strlen($sql)>0)
    {
        $sql = substr($sql, 0, -1);
        $sql='UPDATE '.$tableName.' SET '.$sql.' WHERE id=:id';
//        echo $sql;
//        die;
        $stmtApply = $db->prepare($sql);
        for($i=0;$i<$len;$i++)
        { 
            $n=$cn[$i];
            $ncontent=$r[$n];
            if (($ncontent!=null)&&($ncontent!=$r[$co[$i]]))
                $stmtApply->bindValue(':'.$n, $ncontent);
        }
        $stmtApply->bindValue(':id', $r['rowId']);
        if ($stmtApply->execute()===false)
        {
            $db->close();
            header('Location: oneTask.php?idasset='.$taskId.'&message=unable+to+apply+changes');
            exit;
        }
    }

    $tt=(int)$task['taskType'];
    if (($tableName=='Assets')&&(($tt==510)||($tt==610)||($tt==520)||($tt==620)))
    {
        $kpiSql='';
        if (($tt==510)||($tt==610))
            $kpiSql='SELECT * FROM KPIChanges WHERE fromAssetChangeId=:aid';
        else
            $kpiSql='SELECT * FROM ColumnsChanges WHERE fromAssetChangeId=:aid';
        $kpiStmt = $db->prepare($kpiSql);
        $kpiStmt->bindValue(':aid', $changeId);
        $kpiResults = $kpiStmt->execute();
        while ($kc = $kpiResults->fetchArray(SQLITE3_ASSOC))
        {
            $a=0; $kpiSql='';
            if ($kc['name']!==null && ($kc['name']!== $kc['name_old'])) 
                { $kpiSql.='name=:name,'; $a+=1; }
            if ($kc['shortDescription']!==null && ($kc['shortDescription']!==$kc['shortDescription_old'])) 
                { $kpiSql.='shortDescription=:shortDescription,'; $a+=2; }
            if ($kc['status']!== null && ($kc['status']!== $kc['status_old'])) 
                {  $kpiSql.='status=:status,'; $a+=4; }
            if ($kc['tags']!== null && ($kc['tags']!==$kc['tags_old'])) 
                { $kpiSql.='tags=:tags,'; $a+=8; }

            if (strlen($kpiSql) > 0)
            {
                $kpiSql=substr($kpiSql,0,-1);
                if (($tt==510)||($tt==610))
                    $kpiSql='UPDATE KPI SET '.$kpiSql.' WHERE id=:id';
                else
                    $kpiSql='UPDATE Columns SET '.$kpiSql.' WHERE id=:id';

                echo 'kpiSQL='.$kpiSql;
                $kpiApply = $db->prepare($kpiSql);
                $kpiApply->bindValue(':id', $kc['rowId']);
                if (($a&1)!=0) $kpiApply->bindValue(':name',             $kc['name']);
                if (($a&2)!=0) $kpiApply->bindValue(':shortDescription', $kc['shortDescription']);
                if (($a&4)!=0) $kpiApply->bindValue(':status',           $kc['status']);
                if (($a&8)!=0) $kpiApply->bindValue(':tags',             $kc['tags']);
                $kpiApply->execute();
            }
        }
    }

    $stmtTask = $db->prepare("UPDATE Tasks SET changeId=NULL, description=:txt, completion=2, dateFinished=:now WHERE id=:tid");
    $stmtTask->bindValue(':now', $now);
    $stmtTask->bindValue(':txt', $txt);
    $stmtTask->bindValue(':tid', $taskId);
    $stmtTask->execute();

    addEvent($db,$myid,'Accept changes from','Tasks',$taskId);
} else // REJECT
{
    $stmtTask = $db->prepare("UPDATE Tasks SET changeId=NULL, description=:txt, completion=1, dateFinished=:now WHERE id=:tid");
    $stmtTask->bindValue(':now', $now);
    $stmtTask->bindValue(':txt', $txt);
    $stmtTask->bindValue(':tid', $taskId);
    $stmtTask->execute();
    addEvent($db,$myid,'Reject changes from','Tasks',$taskId);
}

$stmtStatus = $db->prepare("DELETE FROM $changesTable WHERE changeId=:tid");
$stmtStatus->bindValue(':tid', $changeId);
$stmtStatus->execute();

if ($tableName=='Assets')
{
    $tt=(int)$task['taskType'];
    if (($tt==510)||($tt==610))
    {
        $stmtStatus = $db->prepare('DELETE FROM KPIChanges WHERE fromAssetChangeId=:aid');
        $stmtStatus->bindValue(':aid', $changeId);
        $stmtStatus->execute();
    } else if (($tt==520)||($tt==620))
    {
        $stmtStatus = $db->prepare('DELETE FROM ColumnsChanges WHERE fromAssetChangeId=:aid');
        $stmtStatus->bindValue(':aid', $changeId);
        $stmtStatus->execute();
    }
}

$db->close();
header('Location: oneTask.php?idasset='.$taskId.'&message=ok');

