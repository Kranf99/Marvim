<div class="specialcontent">
<div class="profile-container">
<?php
if (isset($_GET['message']))
{
    if ($_GET['message'][0] == 'O')
        echo '<div class="success-message" id="successMessage">&#10003; ' . htmlspecialchars($_GET['message']) . '</div>';
    else
        echo '<div class="error-message" id="successMessage">&#9888; ' . htmlspecialchars($_GET['message']) . '</div>';
}
?>
<div class="breadcrumb"><a href="home.php">Home</a> / <a href="user.php">Users</a> / <?php echo htmlspecialchars($targetUser['name']); ?></div>
<h1 class="page-title">Edit User</h1>

<form action="oneUserSavePhoto.php" method="POST" enctype="multipart/form-data" id="pictureupload">
  <input type="hidden" name="iduser" value="<?php echo $idUser; ?>">
  <input type="hidden" name="return" value="<?php echo $returnPage; ?>">
  <div class="profile-photo-section">
    <div class="profile-photo-preview">
      <?php echo '<img src="' . $targetUser['imageFile'] . '" class="profile-photo-large" alt="Profile Photo" id="photoPreview">'; ?>
    </div>
    <div class="photo-upload-info">
        <h3>Profile Photo</h3>
<?php 
if ($canEditPersonal)
{
?>
    <p><label for="profile_picture">Upload a new profile photo. JPG, JPEG. Max size 1MB.</label></p>
    <div class="file-input-wrapper">
        <input type="file" name="profile_picture" id="profile_picture" accept=".jpg,.jpeg,image/jpeg" required style="display:none;">
        <label for="profile_picture" class="upload-btn">
            &#128228; Upload Photo
        </label>
    </div>
<?php 
        echo '<a href="oneUserNoPhoto.php?iduser='.$idUser.'&return='.$returnPage.'" class="remove-btn">Remove Photo</a>'; 
}
?>
    </div>
  </div>
</form>

<form id="profileForm" action="oneUserSave.php" method="POST">
<input type="hidden" name="iduser" value="<?php echo $idUser; ?>">
<input type="hidden" name="return" value="<?php echo $returnPage; ?>">
<div class="form-section">
    <h3>Personal Information</h3>
    <div class="form-grid">
        <div class="form-group full-width">
            <label for="Name">Name</label>
<?php 
$dis = $canEditPersonal ? '' : ' disabled'; 
echo '<input type="text" id="Name" name="name" value="' . htmlspecialchars($targetUser['name']) . '"' . $dis . ' required>'; 
?>
        </div>
        <div class="form-group full-width">
            <label for="email">Email Address</label>
<?php 
    echo '<input type="email" id="email" name="email" value="' . htmlspecialchars($targetUser['email']) . '"' . $dis . ' required>'; 
?>
            <span class="help-text">This email will be used for notifications and login.</span>
        </div>
    </div>
</div>

<?php if (($idUser== $myid)||($isSuperAdmin)) { ?>
<div class="form-section">
    <h3>API Token</h3>
    <div class="form-grid">
        <div class="form-group full-width">
            <label for="apiToken">API Token</label>
            <div class="password-input-wrapper" style="gap:8px;">
                <input type="text" id="apiToken" value="***" readonly onclick="copyApiToken()" title="Click to copy" style="cursor:pointer; border-radius:6px; border-right:1px solid #d1d5db;">
                <button type="button" class="password-toggle" id="apiTokenToggle" onclick="toggleApiToken()" style="border-radius:6px; border-left:1px solid #d1d5db;">Display API Key</button>
                <button type="button" class="password-toggle" id="apiTokenReset" onclick="resetApiToken()" style="border-radius:6px; border-left:1px solid #d1d5db;">Generate New Key</button>
            </div>
            <span class="help-text" id="apiTokenHelp">Use this token to authenticate API requests as this user.</span>
        </div>
    </div>
</div>
<?php } ?>

<div class="form-section">
<?php if ($editPersonalPass){ ?>
    <h3>Set New Password</h3>
    <div class="form-grid">
        <div class="form-group">
            <label for="currentPassword">Current Password</label>
            <div class="password-input-wrapper">
                <input type="password" name="currentPassword" id="currentPassword" autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">&#128065;&#65039;</button>
            </div>
        </div>
        <div class="form-group"></div>
    </div>
<?php 
}
if ($isSuperAdmin||$editPersonalPass)
{
     if ($isSuperAdmin&&(!$editPersonalPass)) echo '<h3>Change Password</h3>';
?>
    <div class="form-grid">
        <div class="form-group">
            <label for="newPassword">New Password</label>
            <div class="password-input-wrapper">
                <input type="password" name="newPassword" id="newPassword" autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">&#128065;&#65039;</button>
            </div>
        </div>
        <div class="form-group">
            <label for="confirmPassword">Confirm New Password</label>
            <div class="password-input-wrapper">
                <input type="password" id="confirmPassword" autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">&#128065;&#65039;</button>
            </div>
        </div>
    </div>
<?php } ?>
</div>

<div class="form-actions">
<!--    <a href="user.php" class="btn btn-cancel">Go Back</a>-->
    <button type="button" class="btn btn-cancel" onclick="window.history.back()">Go Back</button>
    <?php if ($isSuperAdmin||$editPersonalPass) echo '<button type="submit" class="btn btn-save">Save Changes</button>'; ?>
</div>
</form>

<script>
let apiTokenRevealed = false;
<?php
if (($idUser==$myid)||($isSuperAdmin)) 
    echo 'let apit= '.json_encode($targetUser['apitoken']).';';
else
    echo 'let apit= "";';
?>
function toggleApiToken() {
    const field = document.getElementById('apiToken');
    const btn = document.getElementById('apiTokenToggle');
    if (apiTokenRevealed) {
        field.value = '***';
        btn.textContent = 'Display API Key';
        apiTokenRevealed = false;
        return;
    }
    field.value=apit;
    btn.textContent = 'Hide API Key';
    apiTokenRevealed = true;
}

function copyApiToken() {
    if (!apit) return;
    navigator.clipboard.writeText(apit).then(function() {
        const help = document.getElementById('apiTokenHelp');
        const original = help.textContent;
        const originalColor = help.style.color;
        help.textContent = 'Copied to clipboard!';
        help.style.color = 'red';
        setTimeout(function() { help.textContent = original; help.style.color = originalColor; }, 1500);
    }).catch(function(err) {
        console.error('Failed to copy API token:', err);
    });
}

async function resetApiToken() {
    if (!confirm('Generate a new API key? The current key will stop working immediately.')) return;
    try {
        const response = await fetch('oneUserResetApiToken.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({iduser: <?php echo $idUser; ?>})
        });
        const result = await response.json();
        if (result.error) {
            alert(result.error);
            return;
        }
        const field = document.getElementById('apiToken');
        const btn = document.getElementById('apiTokenToggle');
        apit = result.apitoken;
        field.value = result.apitoken;
        btn.textContent = 'Hide API Key';
        apiTokenRevealed = true;
    } catch (err) {
        console.error('Failed to reset API token:', err);
        alert('Failed to reset API token.');
    }
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

document.getElementById('profile_picture').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || '';
    if (fileName) {
        document.getElementById('pictureupload').submit();
    }
});

document.getElementById('profileForm').addEventListener('submit', function(e) {
    const Name = document.getElementById('Name').value;
    const email = document.getElementById('email').value;
    if (Name == '') {
        alert('Please enter a name.');
        e.preventDefault();
        return;
    }
    const newPasswordEl = document.getElementById('newPassword');
    if (newPasswordEl) {
        const newPassword = newPasswordEl.value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        if ((newPassword != '') && (newPassword !== confirmPassword)) {
            alert('New passwords do not match!');
            e.preventDefault();
            return;
        }
    }
    if (email.indexOf('@') == -1) {
        alert('Invalid email address.');
        e.preventDefault();
        return;
    }
});
</script>
