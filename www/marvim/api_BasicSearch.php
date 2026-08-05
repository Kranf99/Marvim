<?php
require '_pe_checkSessionApi.php';

$q = isset($_REQUEST['q']) ? trim($_REQUEST['q']) : '';
if ($q === '') {
    echo '[]';
    exit;
}

$db = new SQLite3(__DIR__.'/../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);

if ($isSuperAdmin)
    $sql='SELECT a.id, a.name, a.shortDescription, a.category from Assets a'.
        ' where (a.name LIKE :q OR a.shortDescription LIKE :q)'.
        ' ORDER BY a.popularity DESC LIMIT 20';
else
    $sql='SELECT a.id, a.name, a.shortDescription, a.category from Assets a'.
        ' INNER JOIN userDepartmentRights ud ON a.idDepartment=ud.idDepartment'.
        ' where ud.idUser=:myid AND (a.name LIKE :q OR a.shortDescription LIKE :q)'.
        ' ORDER BY a.popularity DESC LIMIT 20';

$stmt = $db->prepare($sql);
$stmt->bindValue(':q', '%'.$q.'%');
if (!$isSuperAdmin) $stmt->bindValue(':myid', $myid);
$results = $stmt->execute();

echo '[';
$first = true;
while (1) {
    $row = $results->fetchArray(SQLITE3_ASSOC);
    if (!$row) break;
    $cat = $row['category'];
    if ($cat < 100) $url = 'oneReport.php?idasset='.$row['id'];
    else if ($cat < 200) $url = 'table.php?idasset='.$row['id'];
    else $url = 'oneWorkflow.php?idasset='.$row['id'];
    if (!$first) echo ',';
    $first = false;
    echo '{"id":'.json_encode($row['id']).
        ',"name":'.json_encode($row['name']).
        ',"shortDescription":'.json_encode($row['shortDescription']).
        ',"category":'.json_encode($cat).
        ',"url":'.json_encode($url).'}';
}
echo ']';
$db->close();
