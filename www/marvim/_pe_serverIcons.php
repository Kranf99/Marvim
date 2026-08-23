<?php
// Maps a servers.serverType to its icon directory under ressources/
function serverIconDir($serverType)
{
    if ($serverType=='Workflow')  return 'WorkflowServers';
    if ($serverType=='Reporting') return 'ReportingServers';
    return 'StorageServers';   // Files, Data Bases, Applications, API's
}

// Sorted list of icon FILENAMES (e.g. 'Oracle.svg') available for a serverType.
// Also used as the whitelist to validate whatever the browser sends back.
function serverIconList($serverType)
{
    static $cache=array();
    $dir = serverIconDir($serverType);
    if (isset($cache[$dir])) return $cache[$dir];

    $out=array();
    $files=glob(__DIR__.'/ressources/'.$dir.'/*.*');
    if ($files)
    {
        for($i=0;$i<count($files);$i++)
        {
            $ext=strtolower(pathinfo($files[$i], PATHINFO_EXTENSION));
            if (in_array($ext,array('svg','png','jpg','jpeg','gif'))) $out[]=basename($files[$i]);
        }
    }
    sort($out);
    $cache[$dir]=$out;
    return $out;
}

// Builds the src for an icon, or '' when the server has no (valid) icon.
// Only the basename is ever stored in servers.icon, never a path.
function serverIconSrc($serverType,$icon)
{
    if (!$icon) return '';
    if (!in_array($icon,serverIconList($serverType))) return '';
    return 'ressources/'.serverIconDir($serverType).'/'.rawurlencode($icon);
}

// 'Microsoft_SQL_Server.svg' -> 'Microsoft SQL Server'
function iconLabel($icon)
{
    return str_replace('_',' ',pathinfo($icon,PATHINFO_FILENAME));
}

// What is shown when servers.icon is empty. Some server types default to an emoji
// rather than to a file: same choices as the cards on storage.php.
// Emojis are returned as HTML entities so this file stays plain ASCII.
function serverDefaultIcon($serverType)
{
    if ($serverType=='Reporting')    return 'ressources/reports.svg';
    if ($serverType=='Files')        return 'ressources/file.svg';
    if ($serverType=='Data Bases')   return 'ressources/database.svg';
    if ($serverType=='Workflow')     return 'ressources/anatella.svg';
    if ($serverType=='Applications') return '&#128295;';            // wrench
    return '&#9729;&#65039;';                                       // cloud: API's, and anything else
}

// Renders an icon -- a file path OR an emoji -- inside the fixed-size icon box,
// so that both kinds line up in the picker.
function serverIconHtml($iconOrPath)
{
    if ($iconOrPath=='') return '<img src="" alt="" class="noicon"/>';
    $ext=strtolower(pathinfo($iconOrPath,PATHINFO_EXTENSION));
    if (in_array($ext,array('svg','png','jpg','jpeg','gif','ico')))
        return '<img src="'.htmlspecialchars($iconOrPath).'" alt=""/>';
    return '<span class="icon-emoji">'.$iconOrPath.'</span>';   // already an HTML entity
}
?>
