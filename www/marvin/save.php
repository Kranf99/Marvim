<?php
require '_pe_apiAuth.php';

// ── init ─────────────────────────────────────────────────────────

date_default_timezone_set('Europe/Brussels');
require_once '_pe_startCRON.php';
require_once '_pe_addEvent.php';
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// ── whitelists ───────────────────────────────────────────────────────────────
$checkCN= array(
    'Assets'=> array(
        'name'=>1,
        'category'=>1,
        'shortDescription'=>1,
        'longDescription'=>1,
        'idserver'=>1,
        'schema'=>1,
        'tags'=>1,
        'status'=>1
    ),
    'Columns'=> array(
        'name'=>1,
        'shortDescription'=>1,
        'status'=>1,
        'tags'=>1
    ),
    'Glossary'=> array(
        'name'=>1,
        'shortDescription'=>1,
        'longDescription'=>1,
        'tags'=>1,
        'status'=>1
    ),
    'KPI'=> array(
        'name'=>1,
        'shortDescription'=>1,
        'status'=>1,
        'tags'=>1
    ),
    'Milestones'=> array(
        'name'=>1,
        'shortDescription'=>1,
        'dueDate'=>1,
        'status'=>1,
        'Completion'=>1
    ),
    'Tasks'=> array(
        'name'=>1,
        'ExecutionNotes'=>1,
        'dateDeadline'=>1,
        'status'=>1,
        'urgency'=>1,
        'completion'=>1
    ),
    'servers'=>array(
        'name'=>1,
        'serverType'=>1,
        'description'=>1,
        'tags'=>1
    ),
    'welcome'=> array(
        'title'=>1,
        'message'=>1
    )
);

// Columns snapshotted in *Changes tables (must exist as real DB columns)
/*
$trackedCols = [
    'assets'   => ['name','shortDescription','longDescription','status','tags','schema','idserver'],
    'columns'  => ['name','shortDescription','status','tags'],
    'Glossary' => ['name','shortDescription','longDescription','status','tags'],
    'KPI'      => ['name','shortDescription','status','tags'],
    'servers'  => ['name','serverType','description','tags'],
];
*/

// *Changes table name per tracked table
$changesTableMap = [
    'Assets'   => 'AssetsChanges',
    'Columns'  => 'ColumnsChanges',
    'Glossary' => 'GlossaryChanges',
    'KPI'      => 'KPIChanges',
    'servers'  => 'serversChanges'
];

// ── validate inputs ──────────────────────────────────────────────────────────
$tablename=''; 
if (array_key_exists('tablename',$data)) $tablename=$data['tablename'];

if (!array_key_exists($tablename, $checkCN)) {
    echo 'don\'t be evil (no such table)';
    exit(500);
}

$cn=''; if (array_key_exists('columnname',$data)) $cn=$data['columnname'];
$content=''; if (array_key_exists('content',$data)) $content=$data['content'];
$cn2=''; if (array_key_exists('columnname2',$data)) $cn2=$data['columnname2'];
$content2=''; if (array_key_exists('content2',$data)) $content2=$data['content2'];

if (strlen($cn)  > 0 && !array_key_exists($cn,  $checkCN[$tablename])) 
{
    echo 'don\'t be evil (no such column)';  
    exit(500); 
}
if (strlen($cn2) > 0 && !array_key_exists($cn2, $checkCN[$tablename])) 
{ 
    echo 'don\'t be evil (no such column2)'; 
    exit(500); 
}
if (strlen($cn) == 0 && strlen($cn2) == 0) 
{
    echo 'don\'t be evil (no columns to update?)';
    exit(500); 
}

$rowId=-1; if (array_key_exists('id',$data)) $rowId=(int)$data['id'];
if ($rowId<=0)
{
    echo 'don\'t be evil (no id to update?)';
    exit(500); 
}

function applyToMainTable(SQLite3 $db, $table, $id, $cn, $content, $cn2, $content2)
{
    $sql='';
    if (strlen($cn))  $sql.=$cn.'=:content';
    if (strlen($cn2)) { 
        if ($sql!='') $sql.=',';
        $sql.=$cn2.'=:content2';
    }
    $stmt = $db->prepare('UPDATE '.$table.' SET '.$sql.' WHERE id=:id');
    if (strlen($cn))  $stmt->bindValue(':content',  $content);
    if (strlen($cn2)) $stmt->bindValue(':content2', $content2);
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    return $db->changes();
}

$nChanges = 0;
$db = new SQLite3('../../db/MarvinDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode=WAL;');

if (array_key_exists($tablename,$changesTableMap)) 
{
    // ── change-tracked table ──────────────────────────────────────────────────
    $changesTable = $changesTableMap[$tablename];
//    $dataCols     = $trackedCols[$tablename];
//    $changeStatus = $isSuperAdmin ? 'approved' : 'pending';

    // check if rowID exists
    if (($tablename=='Columns')||($tablename=='KPI'))
        $sql='SELECT idowner,idasset FROM ' . $tablename . ' WHERE id=:id';
    else
        $sql='SELECT idowner FROM ' . $tablename . ' WHERE id=:id';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $rowId);
    $baseRow = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$baseRow) {
        $db->close();
        header('Content-Type: application/json');
        echo '{"status":"error","msg":"row not found"}';
        exit;
    }

    // SuperAdmin: also apply to main table immediately
    if (($isSuperAdmin)||($baseRow['idowner']==$myid))
    {
        $n = applyToMainTable($db, $tablename, $rowId, $cn, $content, $cn2, $content2);
        if ($n>0) 
        {
            $nChanges=1;
            addEvent($db,$myid,'Changed',$tablename,$rowId);
        }
    } else
    {
        // Find Latest pending/approved change for this user (no task yet) — used as snapshot base
        $sql='SELECT changeId FROM '.$changesTable.' WHERE rowId=:rowId'.
                ' AND changedByUserId=:changedByUserId';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':rowId',           $rowId);
        $stmt->bindValue(':changedByUserId', $myid);
        $results=$stmt->execute();
        $rr=$results->fetchArray(SQLITE3_ASSOC);

        if ($rr)
        {
            $sql='';
            if (strlen($cn))  $sql=$cn.'=:content';
            if (strlen($cn2)) {
                if ($sql!='') $sql.=',';
                $sql.=$cn2.'=:content2';
            }
            $stmt = $db->prepare('UPDATE '.$changesTable.' SET '.$sql.',updatedAt=:updatedAt WHERE changeId=:changeId');
            if (strlen($cn))  $stmt->bindValue(':content',  $content);
            if (strlen($cn2)) $stmt->bindValue(':content2', $content2);
            $stmt->bindValue(':updatedAt', date('Ymd H:i:s'));
            $stmt->bindValue(':changeId', $rr['changeId']);
            $stmt->execute();
            $nChanges=$db->changes();
        } else 
        {
            if (($tablename=='Columns')||($tablename=='KPI'))
            {
                $stmt = $db->prepare(
                    ' SELECT changeId FROM AssetsChanges WHERE rowId=:rowId'.
                    ' AND changedByUserId=:changedByUserId');
                $stmt->bindValue(':rowId',           $baseRow['idasset']);
                $stmt->bindValue(':changedByUserId', $myid);
                $results=$stmt->execute();
                $r2=$results->fetchArray(SQLITE3_ASSOC);
                if ($r2)
                    $fromAssetChangeId=$r2['changeId'];
                else
                {
                    $sql='INSERT INTO AssetsChanges'.
                    ' (rowId,taskId,changeStatus,changedByUserId,needCheck,updatedAt,'.
                    'schema_old,idserver_old,name_old,shortDescription_old,'.
                    'longDescription_old,status_old,idowner_old,tags_old,category'.
                    ') SELECT id,null,\'Pending\',:changedByUserId,:needCheck,:updatedAt,'.
                    'schema,idserver,name,shortDescription,'.
                    'longDescription,status,idowner,tags,category'.
                    ' FROM Assets WHERE id=:rowId';
                    $stmt = $db->prepare($sql);
                    $stmt->bindValue(':rowId',           $baseRow['idasset']);
                    $stmt->bindValue(':changedByUserId', $myid);
                    $stmt->bindValue(':needCheck',      (!$isSuperAdmin));
                    $stmt->bindValue(':updatedAt',       date('Ymd H:i:s'));
                    $stmt->execute();
                    $fromAssetChangeId = (int)$db->lastInsertRowID();
                }
            }

            // Insert new snapshot row
            $sql=''; $sql2='';
            if (strlen($cn))  { $sql =','.$cn;  $sql2 =',:content';  }
            if (strlen($cn2)) { $sql.=','.$cn2; $sql2.=',:content2'; }

            $colCopyTableMap = [
                'Assets'   => ',taskId,changeStatus,schema_old,idserver_old,name_old,shortDescription_old,'.
                    'longDescription_old,status_old,idowner_old,tags_old,category',
                'Columns'  => ',name_old,shortDescription_old,status_old,tags_old,fromAssetChangeId',
                'Glossary' => ',taskId,name_old,shortDescription_old,longDescription_old,status_old,tags_old,idowner_old',
                'KPI'      => ',name_old,shortDescription_old,status_old,tags_old,fromAssetChangeId',
                'servers'  => ',name_old,serverType_old,description_old,tags_old'
            ];
            $colOrigTableMap = [
                'Assets'   => ',null,\'Pending\',schema,idserver,name,shortDescription,'.
                    'longDescription,status,idowner,tags,category',
                'Columns'  => ',name,shortDescription,status,tags,:assetChangeId',
                'Glossary' => ',null,name,shortDescription,longDescription,status,tags,idowner',
                'KPI'      => ',name,shortDescription,status,tags,:assetChangeId',
                'servers'  => ',name,serverType,description,tags'
            ];
            $sql='INSERT INTO '.$changesTable.
                    ' (rowId,changedByUserId,needCheck,updatedAt'.
                    $sql.$colCopyTableMap[$tablename].
                    ') SELECT :rowId,:changedByUserId,:needCheck,:updatedAt'.
                    $sql2.$colOrigTableMap[$tablename].
                    ' FROM '.$tablename.' WHERE id=:rowId';
//            echo $sql;
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':rowId',           $rowId);
            $stmt->bindValue(':changedByUserId', $myid);
            $stmt->bindValue(':needCheck',      (!$isSuperAdmin));
            $stmt->bindValue(':updatedAt',       date('Ymd H:i:s'));
            if (($tablename=='Columns')||($tablename=='KPI'))
                $stmt->bindValue(':assetChangeId',$fromAssetChangeId);
            if (strlen($cn))  $stmt->bindValue(':content',  $content);
            if (strlen($cn2)) $stmt->bindValue(':content2', $content2);
            $stmt->execute();
            $nChanges=$db->changes();
        }

        if ($nChanges)
        {
            if (($tablename=='Columns')||($tablename=='KPI'))
                addEvent($db,$myid,'Submit changes in','Assets',$baseRow['idasset']);
            else
                addEvent($db,$myid,'Submit changes in',$tablename,$rowId);
        }
    }
} else {
    // non-tracked table: direct update
    $nChanges = applyToMainTable($db, $tablename, $rowId, $cn, $content, $cn2, $content2);
    if ($nChanges)
        addEvent($db,$myid,'Changed',$tablename,$rowId);
}

$db->close();
header('Content-Type: application/json');
if (!$nChanges)
{
    echo '{"status":"error"}';
} else {
    startMarvinCRON();
    echo '{"status":"ok"}';
}