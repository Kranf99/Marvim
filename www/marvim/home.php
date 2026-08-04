<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Home</title>
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
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
    <h1 style="font-size: 34px; color: #64748b;">Home</h1><div>
	<button id="enableDisableButton" class="server-hide-btn" onclick="enableDisableEdit(this)">Enable Edit</button>
	</div></div>
                    <!-- Cards -->
                    <div class="cards-grid">
                        <a class="card" href="report.php" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/reports.svg" height="50px"/></div>
                            <div class="card-title">Reports</div>
                        </a>
                        <a class="card" href="storage.php" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/database.svg" height="55px"/></div>
                            <div class="card-title">Storage</div>
                        </a>
                        <a class="card" href="workflow.php" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/anatella.svg" height="55px"/></div>
                            <div class="card-title">Workflows</div>
                        </a>
                        <a class="card" href="glossary.php" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/glossary.svg" width="56px" height="52px"/></div>
                            <div class="card-title">Glossary</div>
                        </a>
                        <a class="card" href="lineage/lineage.php" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/lineage.svg" height="52px"/></div>
                            <div class="card-title">Lineage</div>
                        </a>
                        <a class="card" href="task.php" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/tasks.svg" height="46px" height="55px"/></div>
                            <div class="card-title">Tasks</div>
                        </a>
                        <a class="card" href="user.php" style="color:#475569; text-decoration:none;">
                            <div class="card-icon"><img src="ressources/users.svg" height="46px" height="55px"/></div>
                            <div class="card-title">People</div>
                        </a>
                    </div>

                    <!-- History -->
                    <div class="history-section" style="overflow-x: auto;">
                        <h2>Your History</h2>
<?php
date_default_timezone_set('Europe/Brussels');

function getIconHome($table,$at)
{
    if ($table=="Assets") return getIcon($at);
    if ($table=="Tasks")
    {
        if ($at<320) return '<font size="38px">📋</font>';
        if ($at<340) return '<font size="38px">🔴</font>';
        if ($at<360) return '<font size="38px">🟡</font>';
        if ($at<380) return '<font size="38px">🟢</font>';
        return '<font size="38px">📐</font>';
    }
    if ($table=="Glossary")
        return '<img src="ressources/glossary.svg" height="40px" style="vertical-align: middle;"/>';
    if ($table=="User")
        return '<img src="ressources/users.svg" height="40px" style="vertical-align: middle;"/>';
    return '?';
}

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$db->exec("attach database '" . __DIR__ . "/../../db/MarvimUsers.sqlite' as dbu;");
$results = $db->query('SELECT a.*, u.name as username, u.imagefile as ifile'.
    ' from Activities a LEFT JOIN dbu.Users u ON u.id=a.userid '.
    'where userid='.$myid.' ORDER BY timestamp DESC LIMIT 100');

for($i=0;$i<100;$i++)
{
	$row=$results->fetchArray(SQLITE3_ASSOC);
	if (!$row) break;
    echo '<div class="history-item"><div class="history-icon">'.
         getIconHome($row['tablename'],$row['assetCategory']).
        '</div><div class="history-content"><div class="history-title">'.
        $row['username'].
        '</div><div class="history-description">'.
        $row['description'].
        '</div></div><div class="history-time">'.
        getHumanElapsedTime($row['timestamp']).
        '</div></div>'."\n";
}
$results->finalize();
?>                        
                    </div>
                </div>

                <!-- Right sidebar -->
                <div class="sidebar-right">
                    <div class="sidebar-section">
                        <!--
                        <div style="display: flex; align-items: center;">
                            <div class="action-buttons" style="margin-left: auto;">
                                <button class="btn">✏️ EDIT</button>
                            </div>
                        </div> -->
							<div class="welcome-message">
                            	<div class="editable" data-id="1" data-columnname="title" data-tablename="welcome">
<?php
$results = $db->query('SELECT title,message from welcome');
$row=$results->fetchArray(SQLITE3_ASSOC);
echo $row['title'];
?>                                
                            </div></div>
    						<textarea id="editorMain" data-id="1" data-columnname="message" data-tablename="welcome">
<?php
echo $row['message'];
$results->finalize();
?>
                            </textarea>
                    </div>

                    <div class="sidebar-section">


<!-- Tab links -->
<div class="tab">
  <button class="tablinks" onclick="openTab(event,'ActivityTab')" id='defaultTab'>Activity</button>
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
    </div>
<?php 
$idAsset=0;
require '_pe_footer.php'; 
?>
<script>
initHugeRTEEditMain();
document.getElementById("defaultTab").click();
</script>
</body></html>
