<?php require '_pe_checkSession.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marvim - Add User</title>
    <link rel="stylesheet" href="ressources/style.css">
    <link rel="stylesheet" href="ressources/styleUser.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
<?php
require "_pe_starter.php";

$db = new SQLite3('../../db/MarvimDB.sqlite', SQLITE3_OPEN_READONLY);
$db->busyTimeout(5000);
$isAdmin = (bool) $db->querySingle('SELECT 1 FROM userDepartmentRights WHERE idUser='.$myid.' AND rights>=8 LIMIT 1');
$db->close();

if (!$isSuperAdmin && !$isAdmin)
{
    echo '<div class="specialcontent"><p>Not enough rights.</p><p><a href="user.php">Return to users list</a></p></div>';
    die;
}
?>
<div class="specialcontent">
<div class="profile-container">
<div class="breadcrumb"><a href="home.php">Home</a> / <a href="user.php">Users</a> / Add User</div>
<h1 class="page-title">Add New User</h1>

<?php
if (isset($_GET['message']))
{
    if ($_GET['message'][0] == 'O')
        echo '<div class="success-message" id="successMessage">&#10003; ' . htmlspecialchars($_GET['message']) . '</div>';
    else
        echo '<div class="error-message" id="successMessage">&#9888; ' . htmlspecialchars($_GET['message']) . '</div>';
}
$name =isset($_GET['name']) ? trim($_GET['name']) : '';
$email=isset($_GET['email'])? trim($_GET['email']): '';
?>

<form id="addUserForm" action="oneUserNew.php" method="POST">
<div class="form-section">
    <h3>Personal Information</h3>
    <div class="form-grid">
        <div class="form-group full-width">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>
        <div class="form-group full-width">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            <span class="help-text">This email will be used for notifications and login.</span>
        </div>
    </div>
</div>

<div class="form-section">
    <h3>Password</h3>
    <div class="form-grid">
        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-input-wrapper">
                <input type="password" name="password" id="password" autocomplete="new-password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('password')">&#128065;&#65039;</button>
            </div>
        </div>
        <div class="form-group">
            <label for="confirmPassword">Confirm Password</label>
            <div class="password-input-wrapper">
                <input type="password" id="confirmPassword" autocomplete="new-password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">&#128065;&#65039;</button>
            </div>
        </div>
    </div>
</div>

<div class="form-actions">
    <button type="button" class="btn btn-cancel" onclick="window.history.back()">Go Back</button>
    <button type="submit" class="btn btn-save">Create User</button>
</div>
</form>
</div>
</div>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

document.getElementById('addUserForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (name === '') {
        alert('Please enter a name.');
        e.preventDefault();
        return;
    }
    if (email.indexOf('@') === -1) {
        alert('Please enter a valid email address.');
        e.preventDefault();
        return;
    }
//    if (password.length < 6) {
//        alert('Password must be at least 6 characters.');
//        e.preventDefault();
//        return;
//    }
    if (password !== confirmPassword) {
        alert('Passwords do not match.');
        e.preventDefault();
        return;
    }
});
</script>
</body>
</html>