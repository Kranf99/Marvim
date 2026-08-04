<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Add Word to Glossary</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
<?php require "_pe_starter.php"; ?>
<div class="content">
<div class="main-section">
    <h1 style="font-size: 34px; color: #333">Add Word to Glossary</h1>
    <div class="breadcrumb">
        <a href="home.php">Home</a> /
        <a href="report.php">Reports</a> / Word to Glossary
    </div>
<div id="form-report">    
<br>
    <div class="edit-field">
        <label for="newword">New Word:</label>
        <input type="text" id="newword" name="newword" placeholder="Enter new Word" class="text-input-style">
    </div>
    <button type="button" class="btn btn-save" onclick="submitCreateReport()">Add Word to Glossary</button>
</div>
</div>
</div>

<script>
function submitCreateReport() 
{
    var el=document.getElementById('newword');
    var tn = el.value.trim();
    if (!tn) 
    { 
        alert('Please enter a new word to add to the Glossary.'); 
        el.focus(); 
        return; 
    }
    window.location.href="glossaryNew.php?word="+encodeURIComponent(tn);
}
</script>
</body>
