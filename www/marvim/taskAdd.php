<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Add Task</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
<?php
require "_pe_starter.php";
$priority='';
if (isset($_REQUEST['priority'])) $priority=$_REQUEST['priority'];
?>
<div class="content">
<div class="main-section">
    <h1 style="font-size: 34px; color: #333">Add Task</h1>
    <div class="breadcrumb">
        <a href="home.php">Home</a> /
        <a href="task.php">Tasks</a> / Add Task
    </div>
<div id="form-task">
<br>
    <div class="edit-field">
        <label for="newname">Task Name:</label>
        <input type="text" id="newname" name="newname" placeholder="Enter task name" class="text-input-style">
    </div>
    <button type="button" class="btn btn-save" onclick="submitCreateTask()">Add Task</button>
</div>
</div>
</div>

<script>
function submitCreateTask()
{
    var el=document.getElementById('newname');
    var tn = el.value.trim();
    if (!tn)
    {
        alert('Please enter a task name.');
        el.focus();
        return;
    }
    window.location.href="taskNew.php?name="+encodeURIComponent(tn)+"&priority=<?php echo urlencode($priority); ?>";
}
</script>
</body>