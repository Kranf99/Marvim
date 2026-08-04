<?php
// do not add these 2 rows back: something freezes during page reload when these 2 lines are there.
//session_start();
//if (!isset($_SESSION['id'])) exit; 

date_default_timezone_set('Europe/Brussels');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Prevent script timeout
set_time_limit(0);
ini_set('max_execution_time', 0);

// Database setup
$db = new SQLite3('..\..\db\chatForPush.sqlite',SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000); // Wait up to 5 seconds if database is locked
// Enable WAL mode for better concurrent access
$db->exec('PRAGMA journal_mode=WAL');

$lastId = isset($_REQUEST['after']) ? intval($_REQUEST['after']) : 0;
$idAsset = isset($_REQUEST['idasset']) ? intval($_REQUEST['idasset']) : 0;
$lastKeepAlive = 0;
$keepAliveInterval = 15; // Send keep-alive every 15 seconds
$twoMinutes=new DateInterval('PT2M');
$checkpointCounterIter = 0;
$checkpointCounterRows = 0;
$lastMessageTime=time(); 

// Prepare statement once
$stmt = $db->prepare('SELECT * FROM messages WHERE idasset=:idasset AND id>:lastId ORDER BY timestamp ASC');

while (true) {
    // Send keep-alive comment
    if (time() - $lastKeepAlive >= $keepAliveInterval) {
        echo ": keep-alive\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
        $lastKeepAlive = time();
    }
    
    // Check for new messages
    $stmt->reset();
    $stmt->bindValue(':lastId', $lastId, SQLITE3_INTEGER);
    $stmt->bindValue(':idasset', $idAsset, SQLITE3_INTEGER);
    $results = $stmt->execute();
    
    $messagesSent = false;
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) 
    {
        $messagesSent = true;
        $checkpointCounterRows++;
        $lastId = $row['id'];
        
        echo "data: " . json_encode($row) . "\n\n";
        if (ob_get_level()>0) ob_flush();
        flush();
    }
    $results->finalize();
    
    // Check if connection is still alive
    if (connection_aborted()) {
        break;
    }

    // Adaptive sleep: if no messages in last 120 seconds, wait 3 seconds, otherwise 0.1 seconds
    if ($messagesSent) 
    {
        $lastMessageTime=time(); 
        usleep(100000); // 0.1 seconds
    } else
    {
        if ((time()-$lastMessageTime)>120)
            usleep(3000000); // 3 seconds
        else 
            usleep(100000); // 0.1 seconds
    }
    
    // Periodically checkpoint the WAL file to prevent unlimited growth
    $checkpointCounterIter++;
    if (($checkpointCounterIter>=300)||($checkpointCounterRows>=200))  // Checkpoint every 300 iterations
    { 
        $nowdate=new DateTime();
        $nowdate->sub($twoMinutes);
        $db->query('DELETE FROM messages WHERE timestamp<\''.$nowdate->format('Y-m-d H:i:s').'\'');

        $db->exec('PRAGMA wal_checkpoint(PASSIVE)');
        $checkpointCounter=0;
        $checkpointCounterRows=0;
    }
}

$stmt->close();
$db->close();
?>