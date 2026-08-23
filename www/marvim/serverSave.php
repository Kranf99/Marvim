<?php
require '_pe_checkSessionApi.php';   // sets Content-Type: application/json, $myid, $isSuperAdmin
require '_pe_serverIcons.php';

/////////////////////////////////////////////////////////////////////////////////
//  ALIAS CHAINS
//
//  servers.shortcutToServerID      : 0/NULL = a real, physical server. Otherwise the
//                                    id of another server, which may itself be an alias.
//  servers.finalShortcutToServerID : derived. The id of the REAL server at the end of
//                                    the chain, or 0 when the server is itself real.
//
//  This endpoint is the only place that validates an alias change and the only place
//  that maintains the derived column, so the two stay together here.
/////////////////////////////////////////////////////////////////////////////////

// Errors are reported as HTTP 200 + {"error":...} on purpose: Apache discards the body of a
// non-2xx response coming from PHP-CGI, so any message sent with a 4xx never reaches the browser.
if (!$isSuperAdmin)
{
    echo '{"error":"Super-admin only."}';
    exit();
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['id'])||!isset($data['columnname']))
{
    echo '{"error":"Bad request."}';
    exit();
}

$id  = intval($data['id']);
$col = $data['columnname'];
$val = isset($data['content']) ? $data['content'] : '';

// Hard whitelist: $col ends up inside the SQL, so it may never come from the client unchecked.
if (!in_array($col,array('name','description','icon','shortcutToServerID')))
{
    echo '{"error":"Unknown column."}';
    exit();
}

date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READWRITE);
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode=WAL;');

// The server being edited. The same statement is re-bound further down to fetch the
// alias target, so keep the two lookups asking for exactly the same columns.
$stmt = $db->prepare('SELECT name,serverType from servers where id=:id');
$stmt->bindValue(':id',$id);
$results=$stmt->execute();
$row=$results->fetchArray(SQLITE3_ASSOC);
if (!$row)
{
    $db->close();
    echo '{"error":"Server not found."}';
    exit();
}

if ($col=='name')
{
    $val=trim($val);
    if ($val=='')
    {
        $db->close();
        echo '{"error":"The name cannot be empty."}';
        exit();
    }
}
else if ($col=='icon')
{
    // The "-- default --" entry of the picker posts an empty value: storing '' is what
    // makes the page fall back to serverDefaultIcon() for this serverType.
    // Otherwise only a filename that really sits in this serverType's directory is
    // accepted; anything else (including path traversal attempts) also clears the column.
    if (!in_array($val,serverIconList($row['serverType']))) $val='';
}
else if ($col=='shortcutToServerID')
{
    $val=intval($val);
    if ($val!=0)
    {
        if ($val==$id)
        {
            $db->close();
            echo json_encode(array('error'=>'Cannot make ['.$row['name'].'] an alias of itself.'));
            exit();
        }

        // Same query as above, for the chosen target instead of the edited server:
        // re-bind the statement rather than preparing an identical one.
        $stmt->reset();
        $stmt->bindValue(':id',$val);
        $res2=$stmt->execute();
        $target=$res2->fetchArray(SQLITE3_ASSOC);
        if (!$target)
        {
            $db->close();
            echo '{"error":"The selected server does not exist."}';
            exit();
        }
        // The dropdown only offers servers of the same type; enforce it here too.
        if ($target['serverType']!==$row['serverType'])
        {
            $db->close();
            echo json_encode(array('error'=>'['.$target['name'].'] is a ['.$target['serverType'].
                                            '] server: a server can only be an alias of a ['.
                                            $row['serverType'].'] server.'));
            exit();
        }

        // The target is allowed to be an alias itself, and this server is allowed to have
        // aliases of its own: chains are the point. The one structural rule left is that
        // the chain must not close on itself, which would make the final server
        // unresolvable. servers.php offers every server of the right type without testing
        // this -- the answer depends on the pair -- so the refusal is explained here.
        // id => shortcutToServerID for every server, with the candidate change applied.
        $chain=array();
        {
            $chRes=$db->query('SELECT id,shortcutToServerID from servers');
            while(1)
            {
                $chRow=$chRes->fetchArray(SQLITE3_ASSOC);
                if (!$chRow) break;
                $chain[(int)$chRow['id']]=(int)$chRow['shortcutToServerID'];
            }
            $chRes->finalize();
        }
        $chain[$id]=$val;

        // Does the chain starting at $id now close on itself?
        $hasCycle=false;
        {
            $cySeen=array($id=>true);
            $cyCur=$id;
            while(1)
            {
                if (!isset($chain[$cyCur])) break;          // dangling: no loop
                $cyNext=$chain[$cyCur];
                if ($cyNext==0) break;                      // reached a real server
                if (isset($cySeen[$cyNext])) { $hasCycle=true; break; }
                $cySeen[$cyNext]=true;
                $cyCur=$cyNext;
            }
        }

        if ($hasCycle)
        {
            // Spell out the path that closes the loop, so the message names the servers
            // involved instead of just saying "not allowed".
            $names=array();
            $resN=$db->query('SELECT id,name from servers');
            while(1)
            {
                $rN=$resN->fetchArray(SQLITE3_ASSOC);
                if (!$rN) break;
                $names[(int)$rN['id']]=$rN['name'];
            }
            $resN->finalize();

            $path=array();
            $guard=array();
            $cur=$val;
            while(1)
            {
                $path[]='['.(isset($names[$cur])?$names[$cur]:('#'.$cur)).']';
                if ($cur==$id) break;                    // closed back on the edited server
                if (!isset($chain[$cur])) break;
                $next=$chain[$cur];
                if ($next==0) break;
                if (isset($guard[$next])) { $path[]='['.(isset($names[$next])?$names[$next]:('#'.$next)).']'; break; }
                $guard[$next]=true;
                $cur=$next;
            }
            $db->close();

            $me='['.$row['name'].']';
            if ($cur==$id)
                $msg='Cannot make '.$me.' an alias of ['.$target['name'].']: ['.$target['name'].
                     '] already resolves back to '.$me.' through '.implode(' -> ',$path).
                     '. An alias chain must end on a real server, never loop.';
            else
                $msg='Cannot make '.$me.' an alias of ['.$target['name'].']: the chain of ['.
                     $target['name'].'] already loops ('.implode(' -> ',$path).
                     '), so it never reaches a real server. Fix that chain first.';
            echo json_encode(array('error'=>$msg));
            exit();
        }
    }
}

// The write and the rebuild of the derived column have to land together, or a failure
// halfway would leave finalShortcutToServerID describing a chain that no longer exists.
//$db->exec('BEGIN');
$stmt = $db->prepare('UPDATE servers SET '.$col.'=:v, dateUpdated=:u, changedByUserId=:cu where id=:id');
$stmt->bindValue(':v',$val);
$stmt->bindValue(':u',date('Ymd H:i:s'));
$stmt->bindValue(':cu',$myid);
$stmt->bindValue(':id',$id);
$stmt->execute();

// finalShortcutToServerID is derived from shortcutToServerID, so changing one shortcut
// re-derives it for this server AND for every server chained behind it. The whole table
// is redone rather than just the edited row, and only the rows that actually moved are
// written back.
if ($col=='shortcutToServerID')
{
    // What every server's shortcut is, and what its finalShortcutToServerID says today.
    $fsShortcut=array();
    $fsStored=array();
    $fsIsNull=array();
    {
        $fsRes=$db->query('SELECT id,shortcutToServerID,finalShortcutToServerID from servers');
        while(1)
        {
            $fsRow=$fsRes->fetchArray(SQLITE3_ASSOC);
            if (!$fsRow) break;
            $sid=(int)$fsRow['id'];
            $fsShortcut[$sid]=(int)$fsRow['shortcutToServerID'];
            $fsStored[$sid]  =(int)$fsRow['finalShortcutToServerID'];
            // A row never computed yet holds NULL, which casts to the same 0 a real server
            // resolves to. Without this flag those rows would look "unchanged" and the
            // column would stay a mix of NULL and 0.
            $fsIsNull[$sid]  =($fsRow['finalShortcutToServerID']===null);
        }
        $fsRes->finalize();
    }

    $stmt=$db->prepare('UPDATE servers SET finalShortcutToServerID=:f where id=:sid');
    foreach($fsShortcut as $sid=>$fsSc)
    {
        // Walk this server's chain to the real server at its end. A broken chain -- a
        // dangling id, or a loop left by a direct DB edit -- resolves to 0 rather than
        // spinning, by falling back to $sid so the test below yields 0.
        $fsSeen=array($sid=>true);
        $fsPrev1=$fsCur=$sid; $fsPrev2=-1;
        // loop unrolled 2 times to do path-halving for better speed
        while(1)
        {
            if (!isset($fsShortcut[$fsCur])) { $fsCur=$sid; break; }   // dangling
            $fsNext=$fsShortcut[$fsCur];
            if ($fsNext==0) break;                                     // real server
            if (isset($fsSeen[$fsNext])) { $fsCur=$sid; break; }       // loop
            $fsSeen[$fsNext]=true;
            // path-halving: $fsPrev2 lags two hops behind $fsNext, so this skips a node.
            if ($fsPrev2>0) $fsShortcut[$fsPrev2]=$fsNext;
            $fsPrev2=$fsCur=$fsNext;

            if (!isset($fsShortcut[$fsCur])) { $fsCur=$sid; break; }   // dangling
            $fsNext=$fsShortcut[$fsCur];
            if ($fsNext==0) break;                                     // real server
            if (isset($fsSeen[$fsNext])) { $fsCur=$sid; break; }       // loop
            $fsSeen[$fsNext]=true;
            //  path-halving:
            $fsShortcut[$fsPrev1]=$fsNext;
            $fsPrev1=$fsCur=$fsNext;
        }
        $fsFinal=($fsCur==$sid) ? 0 : $fsCur;

        // only run SQL update if the row has changed
        if (($fsFinal!=$fsStored[$sid])||($fsIsNull[$sid])) 
        {
            $stmt->bindValue(':f',$fsFinal);
            $stmt->bindValue(':sid',$sid);
            $stmt->execute();
            $stmt->reset();
        }
    }
}
//$db->exec('COMMIT');

require_once '_pe_addEvent.php';
addEvent($db,$myid,'Update','servers',$id);
$db->close();

echo '{"status":"ok"}';
?>
