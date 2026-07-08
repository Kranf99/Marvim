<?php
// Session check for JSON API endpoints: same rules as _pe_checkSession.php,
// but failures echo '[]' instead of redirecting/printing an HTML page.
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo '{"error":"Not logged in."}';
    exit;
}
$myid = (int) $_SESSION['id'];

$servertime = $_SERVER['REQUEST_TIME'];
$timeout_duration = 120*60; // for a 2 hours timeout, specified in seconds
if (isset($_SESSION['LAST_ACTIVITY']) &&
   ($servertime - $_SESSION['LAST_ACTIVITY']) > $timeout_duration)
{
    session_unset();
    @session_destroy();
    http_response_code(401);
    echo '{"error":"Session expired, please log in again."}';
    exit;
}
$_SESSION['LAST_ACTIVITY'] = $servertime;

$dbu = new SQLite3(__DIR__.'/../../db/MarvinUsers.sqlite', SQLITE3_OPEN_READONLY);
$dbu->busyTimeout(5000);
$resultUser = $dbu->querySingle('SELECT superadmin FROM users WHERE id='.$myid, true);
$isSuperAdmin = ($resultUser['superadmin']==1);
$dbu->close();
?>
