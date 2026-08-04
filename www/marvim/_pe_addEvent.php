<?php
function addEvent(SQLite3 $db,$myid,$msg,$tablename,$rowId)
{
    $stmt = $db->prepare("DELETE FROM Activities WHERE timestamp<:tt");
    $cutoff=date('Ymd H:i:s', strtotime('-90 days'));
    $stmt->bindValue(':tt',$cutoff);
    $stmt->execute();

    $t=$msg;
    $category=0;
    if ($rowId>=0)
    {
        if ($tablename=='Assets')
            $sql='Select name,category from Assets where id=:id';
        else
            $sql='Select name from '.$tablename.' where id=:id';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id',$rowId);
        $results=$stmt->execute();
        $row=$results->fetchArray(SQLITE3_ASSOC);
        if ($row)
        {
            if ($tablename=='Assets')
            {
                $category=$row['category'];
                $t=$row['category'];
                if ($t<100) $t='Report';
                else if ($t<200) $t='Storage';
                else $t='Workflow';
                $t=$msg.' '.$t.'-['.$row['name'].']';
            } else
                $t=$msg.' '.$tablename.'-['.$row['name'].']';
        }
    }

    $sql='select a.* from Activities a, (select max(id) as maxid from Activities where userid=:userid) b where a.id=b.maxid';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userid',$myid);
    $results=$stmt->execute();
    $row=$results->fetchArray(SQLITE3_ASSOC);
    $results->finalize();
    if ($row)
    {
        if (($row['rowId']==$rowId)&&
            ($row['description']==$t)&&
            ($row['tablename']==$tablename))
        {
            $stmt = $db->prepare("update Activities set timestamp=:ua where id=:id");
            $stmt->bindValue(':id',$row['id']);
            $stmt->bindValue(':ua',date('Ymd H:i:s'));
            $stmt->execute();
            return;
        }
    }

    $sql='INSERT INTO Activities(userId,rowId,assetCategory,description,tablename,timestamp)'.
    'values(:userid,:ri,:ac,:des,:tn,:updatedAt)';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userid',$myid);
    $stmt->bindValue(':ri',$rowId);
    $stmt->bindValue(':ac',$category);
    $stmt->bindValue(':des',$t);
    $stmt->bindValue(':tn',$tablename);
    $stmt->bindValue(':updatedAt',date('Ymd H:i:s'));
    $stmt->execute();
}
?>