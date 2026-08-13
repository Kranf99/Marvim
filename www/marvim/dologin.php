<?php
// Always start this first
session_start();
if ( ! empty( $_POST ) ) 
{
    if ( isset( $_POST['login'] ) && isset( $_POST['password'] ) ) 
    {
        // Getting submitted user data from database
		
		$db = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READONLY);
		$db->busyTimeout(5000);
        $statement = $db->prepare('SELECT id,email,password FROM users WHERE email = :uu');
		$statement->bindValue(':uu', $_POST['login'] );
		$result =$statement->execute();
		$r=$result->fetchArray(SQLITE3_ASSOC);
		$db->close();

        if (isset($r['password']))
    	{
    		// Verify user password and set $_SESSION
    		if ( password_verify( $_POST['password'], $r['password']) )
    		{
    			$_SESSION['id'] = $r['id'];
				$_SESSION['LAST_ACTIVITY'] = $_SERVER['REQUEST_TIME'];

		        $db = new SQLite3('../../db/MarvimUsers.sqlite', SQLITE3_OPEN_READWRITE);
				$db->busyTimeout(5000);
			    $db->exec('PRAGMA journal_mode=WAL');
		        $db->exec("UPDATE users SET LastLoginDate=CurrentLoginDate WHERE id = ".$r['id']);
		        $db->exec("UPDATE users SET CurrentLoginDate=datetime('now','localtime')  WHERE id = ".$r['id']);
				$db->close();
    			header("Location: /marvim/home.php");
    			exit;
    		}
	    }
    }
}
?>
<html>
<head>
<title>Login Error</title>
</head>
<body>
Error in login/password<br/>
<a href="index.html">RETRY</a>
</body>
</html>