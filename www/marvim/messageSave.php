<?php
session_start();
if ( !isset( $_SESSION['id'] ) ) {
	header("Location: ./index.html");
    exit;
}
$myid= $_SESSION['id'];

header('Content-Type: application/json');
date_default_timezone_set('Europe/Brussels');

// Database setup

// Create messages table if it doesn't exist
//$db->exec('CREATE TABLE IF NOT EXISTS messages (
//    id INTEGER PRIMARY KEY AUTOINCREMENT,
//    idasset INTEGER NOT NULL,
//    iduser INTEGER NOT NULL,
//    message TEXT NOT NULL,
//    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
//)');

// Handle different actions
$data = json_decode(file_get_contents('php://input'), true);

$nowdate=new DateTime();
$nowdate=$nowdate->format('Y-m-d H:i:s');

$db = new SQLite3('..\..\db\chat.sqlite',SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000); // Wait up to 5 seconds if database is locked
$db->exec('PRAGMA journal_mode=WAL');
$stmt = $db->prepare('INSERT INTO messages (idasset,iduser,message,timestamp) VALUES (:idasset,:iduser,:message,:ts)');
$stmt->bindValue(':idasset', $data['idasset'], SQLITE3_INTEGER );
$stmt->bindValue(':iduser', $myid, SQLITE3_INTEGER );
$stmt->bindValue(':message', $data['message'], SQLITE3_TEXT);
$stmt->bindValue(':ts',$nowdate, SQLITE3_TEXT);
$stmt->execute();
$id = $db->lastInsertRowID();
//$id=$db->querySingle("SELECT last_insert_rowid()");
$stmt->close();
$db->close();

// already done inside checkSession:
$db = new SQLite3('..\..\db\MarvimUsers.sqlite',SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$resultUserName = $db->querySingle('SELECT name FROM users WHERE id='.$myid);
$db->close();

$db = new SQLite3('..\..\db\chatForPush.sqlite',SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000); // Wait up to 5 seconds if database is locked
$db->exec('PRAGMA journal_mode=WAL');
$stmt = $db->prepare('INSERT INTO messages (id,idasset,iduser,message,timestamp,user) VALUES (:id,:idasset,:iduser,:message,:ts,:user)');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$stmt->bindValue(':idasset', $data['idasset'], SQLITE3_INTEGER );
$stmt->bindValue(':iduser', $myid, SQLITE3_INTEGER );
$stmt->bindValue(':message', $data['message'], SQLITE3_TEXT);
$stmt->bindValue(':ts',$nowdate, SQLITE3_TEXT);
$stmt->bindValue(':user', $resultUserName, SQLITE3_TEXT);
$stmt->execute();
$stmt->close();
$db->close();

echo '{"success":true,"id":'.$id.'}';
?>
