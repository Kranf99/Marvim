<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Add Workflow</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
<?php require "_pe_starter.php"; ?>
<div class="content">
<div class="main-section">
    <h1 style="font-size: 34px; color: #333">Add Workflow</h1>
    <div class="breadcrumb">
        <a href="home.php">Home</a> /
        <a href="Workflow.php">Workflow</a> / Add Workflow
    </div>
<div id="form-report">    
<br>
    <div class="edit-field">
        <label for="newword">New Workflow:</label>
        <input type="text" id="newword" name="newword" placeholder="Enter new Workflow name" class="text-input-style">
    </div>
    <button type="button" class="btn btn-save" onclick="submitCreateReport()">Add Workflow</button>
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
        alert('Please enter a new Workflow name.'); 
        el.focus(); 
        return; 
    }
    window.location.href="WorkflowNew.php?name="+encodeURIComponent(tn);
}
</script>
</body>
