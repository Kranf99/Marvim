<?php
// Always start this first
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= (int) $_SESSION['id'];

$servertime = $_SERVER['REQUEST_TIME'];
$timeout_duration = 120*60; // for a 2 hours timeout, specified in seconds
if (isset($_SESSION['LAST_ACTIVITY']) && 
   ($servertime - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) 
{
    session_unset();
    @session_destroy();
    echo '<html><head><title>Session expired</title></head><body>Session expired.<br/><a href="index.html">Re-Login again</a></body></html>';
    exit;
}
$_SESSION['LAST_ACTIVITY'] = $servertime;
$db = new SQLite3(__DIR__ .'/../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$resultUser = $db->querySingle('SELECT * FROM users WHERE id='.$myid,true);
$db->close();
$isSuperAdmin=($resultUser['superadmin']==1);
?>
