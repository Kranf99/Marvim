<?php
$f1='';
$f2='';
$f3='';
if (isset($_REQUEST['filter1'])) $f1=$_REQUEST['filter1'];
if (isset($_REQUEST['filter2'])) $f2=$_REQUEST['filter2'];
if (isset($_REQUEST['filter3'])) $f3=$_REQUEST['filter3'];
$offset=0;
if (isset($_REQUEST['offset'])) $offset=(int)$_REQUEST['offset'];
if ($offset<0) $offset=0;
$sortdirection='';
if (!isset($sortcol))
{
    $sortcol='dateUpdated';
    if (isset($_REQUEST['sortcolumn'])) $sortcol=$_REQUEST["sortcolumn"];

    $sortdirection='asc';
    if (isset($_REQUEST['sortdirection'])) $sortdirection=$_REQUEST["sortdirection"];
}
?>
<div class="filters">
    <form style="display: contents;" method="GET" action="" id="filterForm">
        <input type="hidden" name="sortdirection"
<?php
echo ' value="'.$sortdirection.'"'
?>
        >
        <input type="hidden" name="sortcolumn"
<?php
echo ' value="'.$sortcol.'">'.$hiddenUrlParameters;
?>
     <input type="hidden" name="offset"
<?php
echo ' value="'.$offset.'"'
?>
        >
        <input type="text" class="filter-input" id="filterInput1" name="filter1" placeholder="Search names"
<?php
echo ' value="'.$f1.'"'
?>
        >
        <input type="text" class="filter-input" id="filterInput2" name="filter2" placeholder="Search descriptions"
 <?php
echo ' value="'.$f2.'"'
?>
        >
        <input type="text" class="filter-input" id="filterInput3" name="filter3" placeholder="Search tags"
        <?php
echo ' value="'.$f3.'"'
?>
        >
    </form>
    <button class="clear-btn" onclick="clearFilter()">CLEAR</button>
</div>

<?php
// todo: create cards on table for small screens
if ($f1!='')
{
//    $sql=$sql.' AND name LIKE \'%'.$f1.'%\''; // todo: use bind with dad
    $sql.=' AND a.name LIKE :f1'; // todo: use bind with dad
}
if ($f2!='') 
{
    if ($filterOnAssetTable)
        $sql.=' AND ((a.shortDescription LIKE :f2) OR (a.longDescription LIKE :f2))';
    else
        $sql.=' AND a.shortDescription LIKE :f2';
}
if ($f3!='')
{   
  $sql.=' AND a.tags LIKE :f3';
}
//$sql=$sql." order by a.$sortcol a.$sortdirection";
$sql=$sql.' ORDER BY a.'.$sortcol.' '.$sortdirection;
$sql=$sql.' LIMIT 101 OFFSET '.$offset;
// for debug:
//echo $sql."<br>\n";
//$results = $db->query($sql);
$stmt = $db->prepare($sql);
// todo: binds
if ($f1!='') $stmt->bindValue(':f1', '%'.$f1.'%');
if ($f2!='') $stmt->bindValue(':f2', '%'.$f2.'%');
if ($f3!='') $stmt->bindValue(':f3', '%'.$f3.'%');
$results=$stmt->execute();

$array_rows=[];
$lastoffset=$offset;
for($i=0;$i<20;$i++)
{
	$row=$results->fetchArray(SQLITE3_ASSOC);
	if (!$row) break;
    $array_rows[] = $row;
    $lastoffset++;
}
if ($row)
{
    while(1)
    {
        $row=$results->fetchArray(SQLITE3_ASSOC);
        if (!$row) break;
        $lastoffset++;
    }
}
// echo $lastoffset;
// Function to generate pagination URL
function getPaginationUrl($increment) {
    global $offset;
    global $lastoffset;
    $params = $_REQUEST;
    $newoffset=$offset+$increment;
    $params['offset'] = $newoffset;
    if ($newoffset<0) return '';
    if ($newoffset>=$lastoffset) return '';
    return '<a href="?' . http_build_query($params).'">page '.($newoffset/20+1).'</a> &nbsp;&nbsp;';
}
if ($offset>80)
{
    $params = $_REQUEST;
    $params['offset']='0';
    echo '<a href=?'.http_build_query($params).'>first page</a> ... ';
}
echo getPaginationUrl(-80);
echo getPaginationUrl(-60);
echo getPaginationUrl(-40);
echo getPaginationUrl(-20);
echo ' <a href="" style="color:#F00;">page '.($offset/20+1).'</a> &nbsp;&nbsp;';
echo getPaginationUrl(20);
echo getPaginationUrl(40);
echo getPaginationUrl(60);
echo getPaginationUrl(80);
if ($lastoffset>$offset+100) echo '...';

function echoSortUrl($col,$nameofcolumn) 
{
    global $sortcol;
    $params = $_REQUEST;
    $arrow='';
    if ($sortcol==$col)
    {
        if (isset($params['sortdirection']))
        {
            if ($params['sortdirection']=='asc')
            {
                $arrow=' ▼';
                $params['sortdirection']='desc';
            }else
            {
                $arrow=' ▲';
                $params['sortdirection']='asc';
                $col='dateUpdated';
            }
        }else
            $params['sortdirection']='asc';
    } else
    {
        $params['sortdirection']='asc';
    } 
    $params['sortcolumn'] = $col;
    //$params['sortdirection']='asc';
    echo '<a style="color:#666; text-decoration:none;" href="?'. http_build_query($params).'">'.$nameofcolumn.$arrow.'</a>';
//    <a href="?sortcolumn=name&sortdirection=asc"> name ▲</a>
}

?>
