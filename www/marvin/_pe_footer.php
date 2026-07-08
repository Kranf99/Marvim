<script>

//////////////////////////////////
//    Card/Table view toggle    //
//////////////////////////////////

function toggleCardView()
{
    const table = document.getElementById('dataTable');
    if (table.classList.contains('card-view')) {
        // Switch to table view
        table.classList.remove('card-view');
        table.classList.add('table-view');
    } else {
        // Switch to card view
        table.classList.remove('table-view');
        table.classList.add('card-view');
    }
}

// Create a media query
const mediaQuery = window.matchMedia('(max-width: 1250px)');
// Function to run when the media query condition matches
function handleMobileView(e) {
    const table = document.getElementById('dataTable');
    if (e.matches) {
        table.classList.remove('table-view');
        table.classList.add('card-view');
    } else {
        table.classList.remove('card-view');
        table.classList.add('table-view');
    }
}

//////////////////////////////////
//       increase LIKES         //
//////////////////////////////////

async function addlike(t,id,table)
{
   var res=await fetch('addLike.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: '{"id":'+id+',"table":"'+table+'"}'});
  const data = await res.json();
  if (data.counter==0) 
    t.innerHTML="<img src=\"ressources/nolike.svg\" height=\"15px\"/>";
  else{
    var s=data.counter+" <img src=\"ressources/";
    if(!data.blue) s+="no";
    t.innerHTML=s+"like.svg\" height=\"15px\"/>";
  }
}

//////////////////////////
//      Toggle TABS     //
//////////////////////////

function openTab(evt, tabName) 
{
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  // Get all buttons and remove the class "active"
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.className += " active";
} 

////////////////////////////////////////////////////
//              Edit Cells with HugeRCE           //
////////////////////////////////////////////////////
let myEditor = null,myEditorTarget=null, myEditorContent="", myMainEditorContent="";

<?php @readfile('Avatar/glossary.txt') ?>

function jsonEscape(s)
{
  s=JSON.stringify(String(s));
  return s.substr(1,s.length-2);
}

function upgradeGlossary(content)
{
  const n=myGlossary.length;
  var i,needle,escapedNeedle,myReplace,regex;
  for(i=0;i<n;i++)
  {
    needle=myGlossary[i];
    escapedNeedle = needle.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    myReplace="$1<a href=\"glossaryOneDef.php?idasset="+myGlossaryID[i]+"\">"+needle+"</a>";
    var regex=new RegExp("([\\s,\\-\\>])" + escapedNeedle + "(?=[\\s,\\-\\<])", "gi");
    content=content.replace(regex,myReplace);
  }
  return content;
}

function cleanGlossary(content)
{
  if (typeof(content)!="string") return content;
  var needle="<a href=\"glossaryOneDef\\.php\\?idasset=\\d+\"[^>]*>([^<]+)<\\/a>";
  var regex=new RegExp(needle, "g");
  content=content.replace(regex,"$1");
  return content;
}

async function saveContent(content,plainContent,el,editor)
{
  const id = el.dataset.id;
  var data="{\"id\":"+id+",\"tablename\":\""+el.dataset.tablename;
  content=cleanGlossary(content);
  if (el.dataset.columnname!=null) 
    data+="\",\"content\":\""+jsonEscape(content)+"\",\"columnname\":\""+el.dataset.columnname+"\"}";
  if (el.dataset.columnname2!=null) 
    data+="\",\"content2\":\""+jsonEscape(plainContent)+"\",\"columnname2\":\""+el.dataset.columnname2+"\"}";
  try {
    const response = await fetch('save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: data});
    const result = await response.text();
    console.log('Saved:', id, result);

    if (editor && !editor.inline) {
      editor.getBody().style.backgroundColor = '#aeecff';
    } else
      el.parentNode.classList.add('highlighted');
    
  } catch (err) {
    console.error('Save failed:', err);
  }
}

function hugeRTEbeforeUnload(content,plainContent,editor)
{
  const el=editor.getElement();
  const id=el.dataset.id;
  content=cleanGlossary(content);
  // Use sendBeacon for reliable delivery during page unload
  var data="{\"id\":"+id+",\"tablename\":\""+el.dataset.tablename;
  if (el.dataset.columnname!=null) 
    data+="\",\"content\":\""+jsonEscape(content)+"\",\"columnname\":\""+el.dataset.columnname+"\"}";
  if (el.dataset.columnname2!=null) 
    data+="\",\"content2\":\""+jsonEscape(plainContent)+"\",\"columnname2\":\""+el.dataset.columnname2+"\"}";
  const blob = new Blob([data], { type: 'application/json' });
    
  if (navigator.sendBeacon) {
    navigator.sendBeacon('save.php', blob);
  } else {
    // Fallback: synchronous XHR (less reliable, but better than nothing)
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'save.php', false); // false = synchronous
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.send(data);
  }
}
  
// Named function instead of arrow
function handleEditableClick(event) 
{
  // If already initialized, just focus
  if (myEditor&&(myEditorTarget==event.target)) {
    myEditor.focus();
    return;
  }
  const element = event.currentTarget;
  myEditorContent=element.innerHTML;
  hugerte.init({
    target: element,   // IMPORTANT: use the clicked element
    plugins: ['autolink', 'link', 'charmap', 'searchreplace', 'emoticons' ],
    inline: true,
    menubar: false,
//    toolbar_location: 'bottom',
    toolbar: 'bold italic underline | undo redo | link forecolor backcolor emoticons',
    setup: function (editor) {
      myEditor=editor;
      myEditorTarget=event.target;
      editor.on('blur', function hugeRTEblurOnEdit() {
  		  if (!myEditor) return;
    	  var editor=myEditor;
        if (myEditorTarget!=event.target) 
        {
            myEditorContent=editor.getContent();
        }
        myEditorTarget=null;
//	  	  myEditor=null;
//	  	  setTimeout(() => {
//	  	    var elp=el.firstElementChild;
//	  	    elp.style.marginBottom='0';
//	  	    elp.style.marginTop='0';
//	  	  }, 50);
		    const content = editor.getContent();
		    if (myEditorContent==content)
		    {
//	  	     editor.destroy();
		      return;
		    }
        var linked = upgradeGlossary(content);
        if (linked !== content) editor.setContent(linked);
		    hugerte.triggerSave();
		    myEditorContent = content; // Update tracked content
	  	  var el=editor.getElement();
		    saveContent(content,editor.getContent({ format: 'text' }),el,editor);
//		  editor.destroy();
  		});
    },

    init_instance_callback: function (editor) {
      editor.focus();
      // solve firefox bug:
 //     if (navigator.userAgent.includes('Firefox'))
//        requestAnimationFrame(() => {
//          const tb = document.querySelector('.tox.tox-hugerte-inline');
//          if (tb) tb.style.transform = 'translateY(-50px)';
//        });
    }
  });

  // Save on page unload (new - handles browser close/back button)
  window.addEventListener('beforeunload', function (e) {
  	if (myEditor)
  	{
  		const content = myEditor.getContent();
	    if (myEditorContent!=content)
	  	{
		  	hugeRTEbeforeUnload(content,myEditor.getContent({ format: 'text' }),myEditor);
  			myEditor=null;
  		}
  	}
  });
}

function initHugeRTEEditMain(bgcolor)
{
 if (!bgcolor) bgcolor = '#fff';
 hugerte.init({
  selector: 'textarea#editorMain',
  plugins: [
    'advlist', 'autolink', 'link', 'image', 'lists', 'charmap', 'preview', 'anchor', 'pagebreak',
    'searchreplace', 'wordcount', 'visualblocks', 'visualchars', 'code', 'fullscreen', 'insertdatetime',
    'media', 'table', 'emoticons', 'template', 'help', 'autoresize'
  ],
  autoresize_bottom_margin :1,
  min_height: 40,
  content_style: 'body { background-color: ' + bgcolor + '; }',
  toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | ' +
    'bullist numlist outdent indent | link image media | ' +
    'forecolor backcolor emoticons | help',
  menu: {
    favs: { title: 'My Favorites', items: 'code visualaid | searchreplace | emoticons' }
  },
  menubar: 'favs edit view insert format tools table help',
  highlight_on_focus: true,
  readonly: true,
  setup: function (editor) {
    myMainEditorContent=editor.getContent();
    editor.on('init', function () {
      var content = editor.getContent();
      var linked = upgradeGlossary(content);
      if (linked !== content) editor.setContent(linked);
    });
//    editor.on('BeforeSetContent', function (e) {
//      e.content = upgradeGlossary(e.content);
//    });
    editor.on('blur', async function () {
      const content = editor.getContent();
  	  if (myMainEditorContent==content)
	      return;
      var linked = upgradeGlossary(content);
      if (linked !== content) editor.setContent(linked);
	    hugerte.triggerSave();
	    myMainEditorContent = content; // Update tracked content
	    saveContent(content,editor.getContent({ format: 'text' }),editor.getElement(),editor);
	  });
    window.addEventListener('beforeunload', function (e) {
      const content = editor.getContent();
  	  if (myMainEditorContent==content)
	      return;
      var linked = upgradeGlossary(content);
      if (linked !== content) editor.setContent(linked);
  	  hugeRTEbeforeUnload(content,0,editor);
    });
  }
 });
}
function initEditAllCells()
{
  setTimeout(() => {
    enableDisableEdit(null);
    var a=document.querySelector('.tox-statusbar__help-text');
    if (a) a.textContent="";
    a=document.querySelector('.tox-statusbar__branding');
    if (a) a.firstChild.textContent="";

    var i,editables=document.querySelectorAll('.editable');
    for (i=0; i<editables.length; i++)
    {
      c=editables[i].innerHTML;
      editables[i].innerHTML=upgradeGlossary(c);
      if (editables[i].dataset.highlight)
      {
        editables[i].parentNode.classList.add('highlighted');
        var row = editables[i].closest('.tr-asset');
        if (row) {
          row.classList.add('highlighted2');
        }        
      }
    }
  }, 100);
}

/////////////////
//     CHAT    //
/////////////////

<?php
if (!$idAsset)
{
  echo ' initEditAllCells(); ';
} else
{
?>
let lastMessageId = 0;
let eventSource = null;
var allMessagesDiv=[];
var chatRedrawTimer=null;

function formatTime(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  const seconds = String(date.getSeconds()).padStart(2, '0');
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

function updateConnectionStatus(connected) {
//    const status = document.getElementById('connectionStatus');
  const status = document.getElementById('sendButton');
  status.style.color = connected ? '#00ff00' : '#ff0000';
}

function redrawChatWindow()
{
  for(;;)
  {
    var m=allMessagesDiv.pop();
    if (!m) break;
    m.style="min-height: "+(m.children[0].offsetHeight+m.children[1].offsetHeight)+"px";
  }
  const messagesContainer = document.getElementById('messages');
  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function displayMessage(msg) 
{
  if (msg.id <= lastMessageId) 
    return;
  lastMessageId = msg.id;

  const messagesContainer = document.getElementById('messages');
  const messageDiv = document.createElement('div');
  
//    var currentUser='frank'; 
<?php
echo ' const currentUser='.$myid.';';
?>
  messageDiv.className = (msg.iduser === currentUser ? 'message sent' : 'message received');
  
  const userDiv = document.createElement('div');
  userDiv.className = 'message-user';
  userDiv.textContent = msg.user;
  
  const contentDiv = document.createElement('div');
  contentDiv.className = 'message-content';
  
  const textDiv = document.createElement('div');
  textDiv.textContent = msg.message;
  
  const timeDiv = document.createElement('div');
  timeDiv.className = 'message-time';
  timeDiv.textContent = formatTime(new Date(msg.timestamp));
  
  contentDiv.appendChild(textDiv);
  contentDiv.appendChild(timeDiv);
  
  messageDiv.appendChild(userDiv);
  messageDiv.appendChild(contentDiv);

  allMessagesDiv.push(messageDiv);
  if (chatRedrawTimer) clearTimeout(chatRedrawTimer);
  chatRedrawTimer= setTimeout(redrawChatWindow,50);

  messagesContainer.appendChild(messageDiv);
}

async function sendMessage() {
  const messageInput = document.getElementById('messageInput');
  const text = messageInput.value.trim();
//    const currentUser=document.getElementById('userInput').value;
<?php
echo ' const currentUserID='.$myid.';'.
  ' const currentAsset='.$idAsset.';';
?>
  if (text === "") return;
  messageInput.value = '';
  try {
    const response = await fetch('messageSave.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({message: text, idasset: currentAsset})
    });
    const data = await response.json();
    if (data.success)
    {
      displayMessage({"iduser":currentUserID,"message":text,
        "timestamp":formatTime(new Date()),"id":data.id});
    }
  } catch (error) {
    console.error('Error sending message:', error);
  }
}

function connectSSE() {
  if (eventSource) {
    eventSource.close();
  }
<?php    
echo 'eventSource = new EventSource(`messageStream.php?after=${lastMessageId}&idasset='.$idAsset.'`);';
?>    
  eventSource.onopen = function() {
    console.log('SSE connection established');
    updateConnectionStatus(true);
  };
  
  eventSource.onmessage = function(event) {
    try {
      const msg = JSON.parse(event.data);
      displayMessage(msg);
    } catch (error) {
      console.error('Error parsing message:', error);
    }
  };
  
  eventSource.onerror = function(error) {
    console.error('SSE error:', error);
    updateConnectionStatus(false);
    eventSource.close(); eventSource=null;
    // Reconnect after 3 seconds
    setTimeout(connectSSE, 3000);
  };
}

async function initScriptChat() {
  const sendButton = document.getElementById('sendButton');
  if (!sendButton) return;
  const messageInput = document.getElementById('messageInput');

  sendButton.addEventListener('click', sendMessage);
  messageInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendMessage();
  });

  // loadInitialMessages
  try {
<?php      
echo 'const response = await fetch(\'messageGet.php?after=0&idasset='.$idAsset.'\');';
?>        
    const data = await response.json();
    for (let i = 0; i < data.length - 1; i++) {
        displayMessage(data[i]);
    }
  } catch (error) {
    console.error('Error loading initial messages:', error);
  }
  connectSSE();
}
initScriptChat();
initEditAllCells();
<?php } ?>
</script>