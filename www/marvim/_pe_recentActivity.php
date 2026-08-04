<div id="ActivityTab" class="tabcontent" style="padding: 6px 6px;">
<h3>Recent Activity from others</h3>
<?php 
//$db->exec("attach database '".__DIR__ . "/../../db/MarvimUsers.sqlite' as dbu;"); // already done above
$results = $db->query('SELECT a.*, u.name as username, u.imagefile as ifile'.
    ' from Activities a LEFT JOIN dbu.Users u ON u.id=a.userid where userid<>'.$myid.
    ' ORDER BY timestamp DESC LIMIT 100');

for($i=0;$i<100;$i++)
{
	$row=$results->fetchArray(SQLITE3_ASSOC);
	if (!$row) break;
    echo '<div class="activity-item"><img src="'.defaultAvatarImage($row["ifile"]).
        '" class="activity-avatar"/><div class="activity-content"><div class="activity-title">'.
        $row['description'].'</div><div class="activity-meta"><div class="activity-subtitle">'.
        $row['username'].
        '</div><div class="activity-time">'.getHumanElapsedTime($row['timestamp']).
        '</div></div></div></div>'."\n";
}
$results->finalize();
?>
</div>
