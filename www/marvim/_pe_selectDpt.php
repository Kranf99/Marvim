<div class="edit-field">
    <label>Department</label>
    <div class="text-input-style">
<?php
//echo '<div class="editable" data-columnname="subCategory" data-tablename="assets" data-id="'.
//    $idAsset.'">'.$rowAsset['subCategory'].'</div>';

echo '<select class="toxradio" data-columnname="departement" data-tablename="assets" data-id="'.
    $idAsset.'" onchange="saveDepartment(this.value,this);">';

if ($isSuperAdmin) 
    $sql='SELECT id, name, icon FROM departments ORDER BY sortorder ASC';
else 
    $sql='SELECT d.id, d.name, d.icon FROM departments d INNER JOIN userDepartmentRights ud ON ud.idDepartment=d.id WHERE ud.idUser='.$myid.' and ud.rights>=4 ORDER BY sortorder ASC';
$stmt=$db->prepare($sql);
$results=$stmt->execute();
$noRows=true;
for(;;)
{
    $row=$results->fetchArray(SQLITE3_ASSOC);
    if (!$row) break;
    $noRows=false;
    echo '<option value="'.$row['id'].'"';
    if ($row['id']==$rowAsset['idDepartment']) echo ' selected';
    echo '>'.$row['icon'].' '.htmlspecialchars($row['name']).'</option>';
}
if ($noRows)
    echo '<option value="" selected>'.$rowAsset['icon'].' '.htmlspecialchars($rowAsset['dptname']).'</option>';
echo '</select>';
$results->finalize();
$stmt->close();
?>
    </div>
</div>
