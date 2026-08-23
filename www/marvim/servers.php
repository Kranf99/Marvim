<?php
require '_pe_checkSession.php';
if (!$isSuperAdmin)
{
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head>'.
         '<body style="font-family:sans-serif;padding:40px;color:#c00">'.
         'Access denied &mdash; super-admin only.</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Servers</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="stylesheet" href="ressources/styleUser.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<style>
.rights-table input[type=text] { width:100%; box-sizing:border-box; padding:6px 10px;
                                 border:1px solid #d1d5db; border-radius:6px; font-size:13px; }
/* A <select> sizes itself to its widest option, so the "Alias of" dropdowns came out a
   different width on every row. Fill the column instead. box-sizing keeps the padding
   inside that width rather than pushing the control past the cell. */
.rights-table td select        { width:100%; box-sizing:border-box; }
/* width:1% + nowrap makes the browser shrink this column to its content. */
.rights-table td.icon-cell,
.rights-table th.icon-cell    { width:1%; white-space:nowrap; text-align:center; }
.server-filters               { display:flex; flex-wrap:wrap; gap:16px; align-items:center;
                                margin-bottom:16px; font-size:14px; color:#374151; }
.server-filters label         { display:flex; align-items:center; gap:5px; cursor:pointer; }
.server-filter-check          { margin-left:auto; }
td.saved-flash                { background:#d1f4e0; transition:background .4s; }
.rights-table td.final-cell   { color:#4b5563; font-size:13px; }
.final-none                   { color:#9ca3af; }
.final-bad                    { color:#c00; }

/* A <select> cannot show images in its options, so the icon column uses a small
   custom dropdown: a hidden input carries the value, the list shows the icons.
   Closed, the button is just the icon -- that is what keeps this column narrow.
   The names only appear in the open list, which is free to be wider than the button. */
.icon-picker                  { position:relative; display:inline-block; }
.icon-picker-btn              { display:inline-flex; align-items:center; padding:4px;
                                border:1px solid #d1d5db; border-radius:6px; background:#fff;
                                cursor:pointer; }
.icon-picker-btn:hover        { border-color:#6366f1; background:#f5f6ff; }
.icon-picker-list             { display:none; position:absolute; z-index:60; top:100%; left:0;
                                min-width:170px; background:#fff; border:1px solid #d1d5db;
                                border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.18);
                                max-height:270px; overflow-y:auto; }
.icon-picker.open .icon-picker-list     { display:block; }
.icon-picker.up   .icon-picker-list     { top:auto; bottom:100%; }
.icon-picker-opt              { display:flex; align-items:center; gap:8px; padding:6px 10px;
                                cursor:pointer; font-size:13px; white-space:nowrap; }
.icon-picker-opt:hover        { background:#eef2ff; }
.icon-picker-opt.selected     { background:#e0e7ff; }
.icon-picker img              { height:26px; width:26px; object-fit:contain; flex:0 0 26px; }
.icon-picker img.noicon       { visibility:hidden; }
/* An emoji default has to occupy the same slot as an <img> icon. */
.icon-picker .icon-emoji      { flex:0 0 26px; width:26px; font-size:19px;
                                line-height:26px; text-align:center; }
</style>
</head>
<body>
<?php
require "_pe_starter.php";
require "_pe_serverIcons.php";

// idserver is NOT a filter: it only tells us which row to highlight (the one whose
// pencil was clicked on storage.php / report.php / workflow.php).
$idserver = isset($_REQUEST['idserver']) ? intval($_REQUEST['idserver']) : 0;
// type/onlytrue only seed the initial state of the filter controls, so that the
// location.reload() after an alias change comes back to the same view.
$selType  = isset($_REQUEST['type'])     ? $_REQUEST['type'] : '';
$onlyTrue = isset($_REQUEST['onlytrue']) ? intval($_REQUEST['onlytrue']) : 0;

date_default_timezone_set('Europe/Brussels');
$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);

$sql='SELECT id,name,serverType,description,icon,shortcutToServerID,finalShortcutToServerID'.
     ' from servers order by id ASC';
$results=$db->query($sql);
$servers=array();
while(1)
{
    $row=$results->fetchArray(SQLITE3_ASSOC);
    if (!$row) break;
    $servers[]=$row;
}
$results->finalize();
$db->close();

// The distinct serverTypes: built from the data rather than hardcoded, because the
// codebase is inconsistent about "API's" vs "APIs". Sorted below, so that the order of
// the filter radios does not depend on the order the rows happen to come out in.
$types=array();
// serverType => (id => name) of EVERY server of that type. A shortcut may now point at
// another alias, so the "Alias of" dropdown offers aliases as well as real servers --
// only the type still has to match.
$byType=array();
// id => the whole row, to name the alias target and the resolved real server.
$byId=array();
for($i=0;$i<count($servers);$i++)
{
    $s=$servers[$i];
    $byId[(int)$s['id']]=$s;
    if (($s['serverType']!==null)&&(!in_array($s['serverType'],$types))) $types[]=$s['serverType'];
    $byType[$s['serverType']][(int)$s['id']]=$s['name'];
}
// The table itself is ordered by id, but these lists read better alphabetically.
sort($types);
foreach($byType as $t=>$lst)
{
    natcasesort($lst);       // by name, keeping the id keys
    $byType[$t]=$lst;
}
?>
<div class="specialcontent">
<div class="profile-container">
<div class="breadcrumb"><a href="home.php">Home</a> / Servers</div>
<h1 class="page-title">Servers</h1>

<div class="form-section">
<div class="server-filters">
    <label><input type="radio" name="ftype" value="" onchange="filterRows()"<?php if($selType==='') echo ' checked'; ?>> All</label>
<?php
for($i=0;$i<count($types);$i++)
{
    echo '    <label><input type="radio" name="ftype" value="'.htmlspecialchars($types[$i]).
         '" onchange="filterRows()"'.($selType===$types[$i]?' checked':'').'> '.
         htmlspecialchars($types[$i]).'</label>'."\n";
}
?>
    <label class="server-filter-check"><input type="checkbox" id="onlyTrue" onchange="filterRows()"<?php if($onlyTrue) echo ' checked'; ?>> show only true, physical servers</label>
</div>

<table class="rights-table" id="serverTable">
    <thead>
        <tr>
            <th style="width:50px;">ID</th>
            <th class="icon-cell">Icon</th>
            <th style="width:160px;">Name</th>
            <!-- No fixed width: Description takes whatever the other columns leave. -->
            <th>Description</th>
            <th style="width:160px;">Alias of</th>
            <th style="width:140px;" title="The real server this one ultimately resolves to (finalShortcutToServerID)">Real server</th>
        </tr>
    </thead>
    <tbody>
<?php
for($i=0;$i<count($servers);$i++)
{
    $s=$servers[$i];
    $id=(int)$s['id'];
    $st=$s['serverType'];
    $sc=(int)$s['shortcutToServerID'];

    // The type is no longer shown as a column (the icon conveys it), but data-servertype
    // stays on the row: it is what the radio filter matches against.
    echo '<tr data-servertype="'.htmlspecialchars($st).'" data-istrue="'.($sc==0?'1':'0').'"';
    if ($id==$idserver) echo ' class="highlighted"';
    echo '>';

    echo '<td>'.$id.'</td>';

    // Icon: a custom dropdown showing the actual images of THIS row's serverType directory,
    // plus the serverType's default icon as the first entry. (A plain <select> can only show
    // text in its options.) The hidden input carries the value, so saveServerField() treats
    // it like any other field. The default entry has value "" -- i.e. an empty icon column.
    $src=serverIconSrc($st,$s['icon']);
    $cur=($src=='') ? '' : $s['icon'];   // an icon that no longer exists on disk reads as default
    $icons=serverIconList($st);
    $defIcon=serverIconHtml(serverDefaultIcon($st));

    // Closed, the button carries the icon alone; the tooltip names it, prefixed with the
    // serverType now that it no longer has a column of its own.
    $curIcon =($cur=='') ? $defIcon : '<img src="'.htmlspecialchars($src).'" alt=""/>';
    $curLabel=($cur=='') ? '-- default --' : iconLabel($cur);

    echo '<td class="icon-cell"><div class="icon-picker" id="picker'.$id.'">'.
         '<input type="hidden" data-id="'.$id.'" data-columnname="icon" value="'.
         htmlspecialchars($cur).'" data-prev="'.htmlspecialchars($cur).'">'.
         '<div class="icon-picker-btn" onclick="toggleIconPicker(this)" title="'.
         htmlspecialchars($st.': '.$curLabel).'">'.$curIcon.'</div>'.
         '<div class="icon-picker-list">'.
         '<div class="icon-picker-opt'.($cur==''?' selected':'').'" data-value="" onclick="pickIcon(this)">'.
         $defIcon.'<span>-- default --</span></div>';
    for($j=0;$j<count($icons);$j++)
        echo '<div class="icon-picker-opt'.($icons[$j]===$cur?' selected':'').'" data-value="'.
             htmlspecialchars($icons[$j]).'" onclick="pickIcon(this)">'.
             '<img src="ressources/'.serverIconDir($st).'/'.rawurlencode($icons[$j]).'" alt=""/>'.
             '<span>'.htmlspecialchars(iconLabel($icons[$j])).'</span></div>';
    echo '</div></div></td>';

    // These cells hold plain text, not stored HTML: htmlspecialchars() is correct here.
    echo '<td><input type="text" data-id="'.$id.'" data-columnname="name" value="'.
         htmlspecialchars($s['name']).'" data-prev="'.htmlspecialchars($s['name']).
         '" onchange="saveServerField(this)"></td>';

    echo '<td><input type="text" data-id="'.$id.'" data-columnname="description" value="'.
         htmlspecialchars($s['description']).'" data-prev="'.htmlspecialchars($s['description']).
         '" onchange="saveServerField(this)"></td>';

    // Alias of: "none" first (i.e. this is a real, physical server), then every server OF
    // THIS ROW'S OWN TYPE except this one -- real or alias, since a shortcut may now point
    // at an alias. Whether a choice would close a loop is NOT decided here: that check
    // depends on the pair (row, target), so serverSave.php makes it and explains the
    // refusal, and the page rolls the dropdown back to its previous value.
    echo '<td><select data-id="'.$id.'" data-columnname="shortcutToServerID" data-prev="'.$sc.
         '" onchange="saveServerField(this)">';
    echo '<option value="0"'.($sc==0?' selected':'').'>none</option>';
    $candidates=isset($byType[$st]) ? $byType[$st] : array();
    $scListed=false;
    foreach($candidates as $tid=>$tname)
    {
        if ($tid==$id) continue;
        if ($tid==$sc)                       // the current value always stays selectable
            $scListed=true;
        echo '<option value="'.$tid.'"'.($tid==$sc?' selected':'').'>'.
             htmlspecialchars($tname).' ('.$tid.')</option>';
    }
    // Data may already alias a server of another type. Keep showing it, flagged: without
    // this option the browser would fall back to "none" and the row would claim to be a
    // physical server while the column says otherwise.
    if (($sc!=0)&&(!$scListed)&&isset($byId[$sc]))
        echo '<option value="'.$sc.'" selected>'.htmlspecialchars($byId[$sc]['name']).
             ' ('.$sc.') &mdash; '.htmlspecialchars($byId[$sc]['serverType']).'</option>';
    echo '</select></td>';

    // Read-only: the real server this row ultimately resolves to, straight from the
    // derived column that serverSave.php maintains. Empty for a real server.
    $fin=(int)$s['finalShortcutToServerID'];
    echo '<td class="final-cell">';
    if ($fin==0) echo '<span class="final-none">&mdash;</span>';
    else if (isset($byId[$fin])) echo htmlspecialchars($byId[$fin]['name']).' ('.$fin.')';
    else echo '<span class="final-bad">unknown ('.$fin.')</span>';
    echo '</td>';

    echo "</tr>\n";
}
?>
    </tbody>
</table>
</div>
</div>
</div>
</div>

<script>
//////////////////////
//   ICON PICKER    //
//////////////////////
// A <select> can only show text in its options, so the icon column is a custom dropdown:
// a hidden <input> holds the value and is what gets handed to saveServerField().

function closeIconPickers(except)
{
    var pickers=document.querySelectorAll('.icon-picker.open');
    for(var i=0;i<pickers.length;i++)
        if (pickers[i]!=except) pickers[i].classList.remove('open','up');
}

function toggleIconPicker(btn)
{
    var picker=btn.parentNode;
    var wasOpen=picker.classList.contains('open');
    closeIconPickers(picker);
    if (wasOpen) { picker.classList.remove('open','up'); return; }

    picker.classList.add('open');
    // Not enough room below? Open upwards instead.
    var list=picker.querySelector('.icon-picker-list');
    var below=window.innerHeight-btn.getBoundingClientRect().bottom;
    if (below<list.offsetHeight+10) picker.classList.add('up');
}

// Repaints the button from the hidden input's current value, by copying the icon out of
// the matching option. Copying rather than rebuilding means an emoji default renders
// exactly like an <img> icon, with no duplicated markup here. Only the icon is copied:
// the option's name goes to the tooltip, so the closed button stays icon-wide.
function renderIconPicker(input)
{
    var picker=input.parentNode;
    var btn=picker.querySelector('.icon-picker-btn');
    var opts=picker.querySelectorAll('.icon-picker-opt');
    for(var i=0;i<opts.length;i++)
    {
        var isSel=(opts[i].dataset.value==input.value);
        opts[i].classList.toggle('selected',isSel);
        if (isSel)
        {
            btn.innerHTML=opts[i].firstElementChild.outerHTML;   // the <img> or emoji <span>
            btn.title=input.closest('tr').dataset.servertype+': '+
                      opts[i].lastElementChild.textContent;      // type + icon name
        }
    }
}

function pickIcon(opt)
{
    var picker=opt.parentNode.parentNode;
    var input=picker.querySelector('input');
    picker.classList.remove('open','up');
    if (input.value==opt.dataset.value) return;   // nothing changed, nothing to save
    input.value=opt.dataset.value;
    renderIconPicker(input);
    saveServerField(input);
}

document.addEventListener('click', function(e){
    if (!e.target||!e.target.closest||!e.target.closest('.icon-picker')) closeIconPickers(null);
});

function filterRows()
{
    var type='';
    var radios=document.getElementsByName('ftype');
    for(var i=0;i<radios.length;i++) if (radios[i].checked) type=radios[i].value;
    var onlyTrue=document.getElementById('onlyTrue').checked;

    var rows=document.querySelectorAll('#serverTable tbody tr');
    for(var i=0;i<rows.length;i++)
    {
        var show=true;
        if ((type!='')&&(rows[i].dataset.servertype!=type)) show=false;
        if (onlyTrue&&(rows[i].dataset.istrue!='1')) show=false;
        rows[i].style.display = show ? '' : 'none';
    }

    // Mirror the filter into the URL so a reload comes back to the same view.
    var url='servers.php?idserver=<?php echo $idserver; ?>&type='+encodeURIComponent(type)+
            '&onlytrue='+(onlyTrue?1:0);
    history.replaceState(null,'',url);
}

async function saveServerField(el)
{
    var data=JSON.stringify({id:parseInt(el.dataset.id),
                             columnname:el.dataset.columnname,
                             content:el.value});
    try {
        const response = await fetch('serverSave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: data});
        const result = await response.json();
        if (result.error)
        {
            alert(result.error);
            el.value=el.dataset.prev;
            if (el.dataset.columnname=='icon') renderIconPicker(el);
            return;
        }
        el.dataset.prev=el.value;

        var td=el.closest('td');
        td.classList.add('saved-flash');
        setTimeout(function(){ td.classList.remove('saved-flash'); },1200);

        // An alias change reshuffles every other row's "Alias of" list: let PHP rebuild it.
        if (el.dataset.columnname=='shortcutToServerID') location.reload();
    } catch (err) {
        // A 401 from _pe_checkSessionApi.php also lands here: Apache drops the body of a
        // non-2xx PHP-CGI response, so response.json() throws instead of giving a message.
        console.error('Save failed:', err);
        alert('Save failed. Your session may have expired: please reload the page.');
        el.value=el.dataset.prev;
        if (el.dataset.columnname=='icon') renderIconPicker(el);
    }
}

filterRows();
</script>
</body>
</html>
