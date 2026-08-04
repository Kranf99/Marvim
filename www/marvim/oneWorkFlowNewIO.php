<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: ./index.html");
    exit;
}
$myid = (int)$_SESSION['id'];

if (!isset($_REQUEST['idworkflow'])) { header('Location: workflow.php'); exit; }
if (!isset($_REQUEST['idasset']))    { header('Location: workflow.php'); exit; }
if (!isset($_REQUEST['workflowDirection'])) { header('Location: workflow.php'); exit; }

$idwf    = (int)$_REQUEST['idworkflow'];
$idio    = (int)$_REQUEST['idasset'];
$isInput = ($_REQUEST['workflowDirection'] === 'input') ? 1 : 0;

// superAdmin check
$dbUsers = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READONLY);
$dbUsers->busyTimeout(5000);
$isSuperAdmin = (bool)$dbUsers->querySingle('SELECT superadmin FROM users WHERE id=' . $myid);
$dbUsers->close();

date_default_timezone_set('Europe/Brussels');
$now = date('Ymd H:i:s');
$db  = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode=WAL;');

if ($isSuperAdmin) {
    // Apply immediately
    $stmt = $db->prepare('INSERT INTO workflowIO (idWorkflow, idIO, isInput) VALUES (:idwf, :idio, :isi)');
    $stmt->bindValue(':idwf', $idwf);
    $stmt->bindValue(':idio', $idio);
    $stmt->bindValue(':isi',  $isInput);
    $stmt->execute();
}

// Log the change (pending for regular users, approved for superAdmin)
$changeStatus = $isSuperAdmin ? 'approved' : 'pending';
$cutoff       = date('Ymd H:i:s', time() - 60);

// Debounce: same user, same (idWorkflow, idIO), no task yet, < 1 min ago
$stmtD = $db->prepare(
    "SELECT changeId FROM workflowIOChanges
     WHERE idWorkflow=:idwf AND idIO=:idio AND changedByUserId=:uid
       AND changeStatus=:cs AND taskId IS NULL AND updatedAt > :cutoff
     ORDER BY changeId DESC LIMIT 1"
);
$stmtD->bindValue(':idwf',   $idwf);
$stmtD->bindValue(':idio',   $idio);
$stmtD->bindValue(':uid',    $myid);
$stmtD->bindValue(':cs',     $changeStatus);
$stmtD->bindValue(':cutoff', $cutoff);
$existing = $stmtD->execute()->fetchArray(SQLITE3_ASSOC);

if ($existing) {
    $stmtU = $db->prepare(
        "UPDATE workflowIOChanges SET changeType='add', isInput=:isi, updatedAt=:now WHERE changeId=:cid"
    );
    $stmtU->bindValue(':isi', $isInput);
    $stmtU->bindValue(':now', $now);
    $stmtU->bindValue(':cid', $existing['changeId']);
    $stmtU->execute();
} else {
    $stmtI = $db->prepare(
        "INSERT INTO workflowIOChanges (idWorkflow, idIO, isInput, changeType, changedByUserId, isSuperAdmin, changeStatus, createdAt, updatedAt)
         VALUES (:idwf, :idio, :isi, 'add', :uid, :admin, :cs, :now, :now)"
    );
    $stmtI->bindValue(':idwf',  $idwf);
    $stmtI->bindValue(':idio',  $idio);
    $stmtI->bindValue(':isi',   $isInput);
    $stmtI->bindValue(':uid',   $myid);
    $stmtI->bindValue(':admin', $isSuperAdmin ? 1 : 0);
    $stmtI->bindValue(':cs',    $changeStatus);
    $stmtI->bindValue(':now',   $now);
    $stmtI->execute();
}

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Add','Assets',$idwf);

$db->close();
header('Location: oneWorkflow.php?advEdit=1&idasset=' . $idwf);
