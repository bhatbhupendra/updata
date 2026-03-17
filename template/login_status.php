<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.navbar-custom {
    background: #1f2937;
    padding: 8px 0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.navbar-container {
    max-width: 1400px;
    margin: auto;
}

.brand-title {
    font-size: 16px;
    font-weight: 600;
    color: #ffffff;
    text-decoration: none;
}

.brand-title:hover {
    color: #e5e7eb;
}

.welcome-text {
    font-size: 12.5px;
    color: #d1d5db;
    margin-right: 14px;
}

.user-badge {
    background: #374151;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    margin-right: 12px;
    color: #f9fafb;
}

.logout-btn {
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 6px;
}
</style>

<nav class="navbar-custom">
    <div class="container-fluid navbar-container d-flex justify-content-between align-items-center">

        <!-- Left -->
        <a href="#" class="brand-title">
            Grow Bridges
        </a>

        <!-- Right -->
        <div class="d-flex align-items-center">

            <div class="user-badge">
                <?php echo htmlspecialchars($_SESSION['name']); ?>
            </div>

            <div class="welcome-text">
                <?php echo htmlspecialchars($_SESSION['email']); ?>
            </div>

            <a href="../logout.php" class="btn btn-outline-light logout-btn">
                Logout
            </a>

        </div>

    </div>
</nav>