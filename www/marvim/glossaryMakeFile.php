<?php
$results=$db->query('select id,name from Glossary where toDelete=0');
$row=$results->fetchArray(SQLITE3_ASSOC);
if (!$row) 
{
	$content="const myGlossary=[];\nconst myGlossaryID=[]\n";
} else
{
	$content=json_encode($row['name']);
	$contentID=$row['id'];
	while(1)
	{
		$row=$results->fetchArray(SQLITE3_ASSOC);
		if (!$row) break;
		$content.=','.json_encode($row['name']);
		$contentID.=','.$row['id'];
	}
	$content='const myGlossary=['.$content."];\nconst myGlossaryID=[".$contentID."];\n";
}
file_put_contents(__DIR__.'/Avatar/glossary.txt', $content);
?>