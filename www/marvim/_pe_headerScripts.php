<script src="hugeRTE/hugerte.min.js"></script>
<script>
async function handleStatusClick(event) 
{
  const el=event.currentTarget;
  const id=el.dataset.id;
  const tn=el.dataset.tablename;
  var c=parseInt(el.dataset.status);
  c++;
  if (c==3) c=0;
  el.dataset.status=c;
  switch(c)
  {
    case 0: el.innerHTML="<div style='width:20px; height:20px'></div>"; break;
    case 1: el.innerHTML="<img src=\"ressources/certified.svg\" height=\"20px\">"; break;
    case 2: el.innerHTML="<img src=\"ressources/forbidden.svg\" height=\"20px\">"; break;
  }
  try {
    const response = await fetch('save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: "{\"id\":"+el.dataset.id+",\"content\":"+c+
       ",\"columnname\":\"status\",\"tablename\":\""+tn+"\"}"});
    const result = await response.text();
    console.log('Saved:', id, result);
  } catch (err) {
    console.error('Save failed:', err);
  }
}

var editing=true;
var _llmHasData = false;
function enableDisableEdit(b) 
{
  const menubar = document.querySelector('.tox-editor-header');
  const statusbar = document.querySelector('.tox-statusbar');
  const radios = document.querySelectorAll('.toxradio');
  var llmbtn = document.getElementById('llm-autocomplete-btn');
  var eMain=hugerte.get('editorMain');
  if (editing) 
  {
    if (eMain) eMain.mode.set("readonly");
    if (menubar) menubar.style.display = 'none';
    if (statusbar) statusbar.style.display = 'none';
    if (b) b.innerText = "Enable editor";
    var i,editables=document.querySelectorAll('.editable');
    for (i=0; i<editables.length; i++)
    {
      editables[i].removeEventListener('click', handleEditableClick);
      editables[i].style.border='0px';
      editables[i].style.height = '1lh';
    }
    editables=document.querySelectorAll('.statusEdit');
    for (i=0; i<editables.length; i++)
    {
      editables[i].removeEventListener('click', handleStatusClick);
      editables[i].style.border='0px';
      editables[i].style.height = '1lh';
    }
    editables=document.querySelectorAll('.deleteicon');
    for (i=0; i<editables.length; i++)
      editables[i].style.display="none";
    for (i=0; i<radios.length; i++) 
      radios[i].disabled = true;
    if (llmbtn&&_llmHasData)      llmbtn.style.display = 'none';
  } else 
  {
    if (menubar) menubar.style.display = '';
    if (statusbar) statusbar.style.display = '';
    if (eMain) eMain.mode.set("design");
    if (b) b.innerText = "Disable editor";
    var i,editables=document.querySelectorAll('.editable');
    for (i=0; i<editables.length; i++)
    {
      editables[i].addEventListener('click', handleEditableClick);
      editables[i].style.border = '2px solid #88F';
    }
    var i,editables=document.querySelectorAll('.statusEdit');
    for (i=0; i<editables.length; i++)
    {
      editables[i].addEventListener('click', handleStatusClick);
      editables[i].style.border = '2px solid #88F';
    }
    editables=document.querySelectorAll('.deleteicon');
    for (i=0; i<editables.length; i++)
      editables[i].style.display="inline";

    for (i=0; i<radios.length; i++) 
      radios[i].disabled = false;
    if (llmbtn) llmbtn.style.display = 'inline-block';
  }
  editing=!editing;
}
</script>
