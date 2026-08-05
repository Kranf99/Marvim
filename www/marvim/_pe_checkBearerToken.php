<?php
// _pe_checkBearerToken.php - Authenticate an API request via an "Authorization: Bearer <token>"
// header, matched against users.apitoken in MarvimUsers.sqlite to identify the calling user.
//
// On success: sets $myid (int) and $isSuperAdmin (bool) for the calling user.
// On failure: sends a 401 response with a JSON {"error": ...} body and exits.
//
// Usage: require '_pe_checkBearerToken.php';

header('Content-Type: application/json');

if ($authHeader === '')
{
    if (function_exists('getallheaders'))
    {
        foreach (getallheaders() as $k => $v)
            if (strcasecmp($k, 'Authorization') === 0) { $authHeader = $v; break; }
    }
    if ($authHeader === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    if ($authHeader === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];

    $authHeader = trim($authHeader);
}
$token = '';
if (strcasecmp(substr($authHeader, 0, 7), 'Bearer ') === 0) $token = trim(substr($authHeader, 7));
if ($token === '')
{
    http_response_code(401);
    echo json_encode(array('error' => 'Missing or malformed Authorization: Bearer header.'));
    exit;
}

$dbu = new SQLite3(__DIR__.'/../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READONLY);
$dbu->busyTimeout(5000);
$stmt = $dbu->prepare('SELECT id, superadmin FROM users WHERE apitoken=:tok AND (deleted IS NULL OR deleted=0)');
$stmt->bindValue(':tok', $token);
$userRow = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
$dbu->close();
if (!$userRow)
{
    http_response_code(401);
    echo json_encode(array('error' => 'Invalid Authorization Token.'));
    exit;
}
$myid = (int)$userRow['id'];
$isSuperAdmin = ($userRow['superadmin'] == 1);
?>
