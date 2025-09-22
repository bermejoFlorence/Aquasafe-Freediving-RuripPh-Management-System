<!-- includes/sidebar.php -->
<nav class="sidebar" id="sidebar">
    <div class="logo-area">
        <img src="/diving/uploads/logo.jpeg" alt="Logo" class="sidebar-logo">
    </div>
    <div class="sidebar-nav">
        <a href="index.php" class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>
        <a href="bookings.php" class="<?php echo ($page == 'bookings') ? 'active' : ''; ?>">
            <i class="fa-solid fa-calendar-check"></i> Bookings
        </a>
        <a href="inventory.php" class="<?php echo ($page == 'inventory') ? 'active' : ''; ?>">
            <i class="fa-solid fa-boxes-stacked"></i> Inventory
        </a>
        <a href="forum.php" class="<?php echo ($page == 'forum') ? 'active' : ''; ?>">
            <i class="fa-solid fa-comments"></i> Forum
        </a>

        <a href="payments.php" class="<?php echo ($page == 'payments') ? 'active' : ''; ?>">
            <i class="fa-solid fa-receipt"></i> Payments
        </a>

        <a href="users.php" class="<?php echo ($page == 'users') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i> Users
        </a>
        <a href="packages.php" class="<?php echo ($page == 'packages') ? 'active' : ''; ?>">
            <i class="fa-solid fa-box"></i> Packages
        </a>

    </div>
</nav>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
