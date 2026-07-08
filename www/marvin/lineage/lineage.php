<?php
// _pe_checkSession.php redirects to "./index.html" on no session, relative to
// marvin/*.php; this page lives one level deeper (marvin/lineage/), so that
// resolves to a non-existent marvin/lineage/index.html. Redirect ourselves
// to the real login page before handing off to the shared session check.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.html');
    exit;
}
require '../_pe_checkSession.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Marvin - Lineage</title>
  <link rel="stylesheet" href="../ressources/style.css">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <style>
    .lineage-wrap {
      flex: 1;
      overflow: hidden;
      display: flex;
      min-height: 0;
    }
    .lineage-wrap iframe {
      flex: 1;
      border: none;
      width: 100%;
      height: 100%;
      display: block;
    }
  </style>
</head>
<body>
<?php //require "../_pe_starter.php"; ?>
<!-- adapted from "_pe_starter.php": -->
<button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button><div class="sidebar-overlay" onclick="toggleMobileMenu()"></div><div class="container"><div class="sidebar">
<div class="logo">
    <a href="../home.php">
    <img src="../ressources/marvin.svg" height="50px"/>
    <span class="logotext">Marvin</span>
    </a>
</div>
<a href="../report.php" class="nav-item" style="color:#fff; text-decoration:none;">
    <img src="../ressources/reports.svg" height="25px"/> Reports</a>
<a href="../storage.php" class="nav-item" style="color:#fff; text-decoration:none;">
    <img src="../ressources/database.svg" height="25px"/> Storage</a> 
<a href="../workflow.php" class="nav-item" style="color:#fff; text-decoration:none;">
    <img src="../ressources/anatella.svg" height="25px"/> Workflows</a>
<a href="../glossary.php" class="nav-item" style="color:#fff; text-decoration:none;">
    <img src="../ressources/glossary.svg" width="28px"/> Glossary</a>
<a href="lineage.php" class="nav-item" style="color:#fff; text-decoration:none;">
    <img src="../ressources/lineage.svg" height="28px"/> Lineage</a>
<a href="../Task.php" class="nav-item" style="color:#fff; text-decoration:none;">
    <img src="../ressources/tasks.svg" height="23px"/> Tasks</a>
<a href="../user.php" class="nav-item" style="color:#fff; text-decoration:none;">
    <img src="../ressources/users.svg" height="23px"/> People</a>
</div>

<!-- Main content -->
<div class="main-content">
<!-- Header -->
<div class="header">
    <div class="search-bar">
        <input type="text" placeholder="Search">
    </div>
    <div class="header-actions">
        <button class="btn">↗️ SHARE</button>
        <span>❓</span>
        <span>🔔</span>
        <div class="user-profile-wrapper">
            <input type="checkbox" id="menu-toggle">
            
            <label for="menu-toggle" class="user-profile">
<?php
echo '<img src="../'.$resultUser['imageFile'].'" class="avatar" alt="Avatar"/>'.
     '<span>'.$resultUser['name'].'</span>';
 ?>
                <span>▼</span>
            </label>

            <div class="dropdown-menu">
                <div class="menu-header">
<?php
echo '<img src="../'.$resultUser['imageFile'].'" class="avatar-large" alt="Avatar"/>'.
     '<div class="menu-header-info"><h3>'.$resultUser['name'].'</h3><p>'.$resultUser['email'].'</p></div>';
?>
                </div>
                <div class="menu-section">
                    <a href="../profile.php" class="menu-item">
                        <span class="icon">👤</span>
                        <span>My Profile</span>
                    </a>
                    <a href="#theme" class="menu-item">
                        <span class="icon">🎨</span>
                        <span>Personnalized Theme</span>
                    </a>
                </div>
                <div class="menu-divider"></div>
                <div class="menu-section">
                    <a href="#help" class="menu-item">
                        <span class="icon">💡</span>
                        <span>Help &amp; Support</span>
                    </a>
                </div>
                <div class="menu-footer">
                    <a href="../logout.php">🚪 Disconnect</a>
                </div>
            </div>
            <label for="menu-toggle" class="menu-overlay"></label>
        </div>
    </div>
</div>
<script>
//////////////////////////////////
//       Toggle Mobile Menu     //
//////////////////////////////////

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    menuToggle.classList.toggle('hidden');
}
</script>	  

<div class="lineage-wrap">
  <iframe src="marvin_lineage.html"></iframe>
</div>
</div></div>
</body>
</html>
