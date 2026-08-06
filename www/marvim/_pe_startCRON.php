<?php
function startMarvimCRON()
{
    $isRunning = false;
    $pidFile = __DIR__ . '/avatar/cron_daemon.pid';
    if (file_exists($pidFile)) 
    {
        $pid = (int) file_get_contents($pidFile);
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $out = shell_exec('tasklist /FI "PID eq '.$pid.'" /FI "IMAGENAME eq php.exe" /NH 2>NUL');
            $isRunning = strpos($out, 'php.exe') !== false;
        } else {
            $isRunning = file_exists('/proc/'.$pid);
        }
    }
    if (!$isRunning) {
//        echo "Starting CRON\n";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
        {
        	if (PHP_VERSION_ID < 80000) 
        	{
				// this is for UwAMp:
	            $bindir = dirname(dirname(dirname(PHP_BINARY)));
	            $s='start "" /B "'.$bindir.'\\php\\php-'.PHP_VERSION.'\\php.exe" -c "'.
	                $bindir.'\\apache\\php.ini" "'.__DIR__.'\\cron.php" >> "'.
	                __DIR__.'\\avatar\\cron.log" 2>&1';
			} else
			{
				// this is for Uniform Server:
	            $bindir=dirname(php_ini_loaded_file());
	            $s='start "" /B "'.$bindir.'\\php.exe" -c "'.
	                $bindir.'\\php-cli.ini" "'.__DIR__.'\\cron.php" >> "'.
	                __DIR__.'\\avatar\\cron.log" 2>&1';
			}
//            echo $s;
            pclose(popen($s,'r'));
        } else 
        {
            // PHP_BINARY is httpd under mod_php; locate the matching php CLI instead
            $phpBin = trim(shell_exec('which php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION
                          .' 2>/dev/null || which php 2>/dev/null'));
            shell_exec('nohup "'.$phpBin.'" "'.__DIR__.'/cron.php" >> "'.__DIR__.'/avatar/cron.log" 2>&1 &');
        }
    }
}
?>