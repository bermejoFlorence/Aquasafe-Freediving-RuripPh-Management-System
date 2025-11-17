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

        <!-- NEW: Reports (moderation) -->
        <a href="reports.php" class="<?php echo ($page == 'reports') ? 'active' : ''; ?>">
            <i class="fa-solid fa-flag"></i> Reports
            <!-- optional badge container -->
            <span id="reports-badge" style="display:none"
                  class="badge-dot" title="Open reports"></span>
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

<script>
  const sidebar   = document.getElementById('sidebar');
  const hamburger = document.getElementById('hamburger-btn'); // make sure this exists in your header
  const overlay   = document.getElementById('sidebar-overlay');

  function openSidebar(){
    sidebar.classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar(){
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  hamburger?.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });

  overlay?.addEventListener('click', closeSidebar);

  // close on Esc
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
  });

  // close after clicking a menu item (mobile)
  document.querySelectorAll('.sidebar-nav a').forEach(a => {
    a.addEventListener('click', () => {
      if (window.innerWidth <= 700) closeSidebar();
    });
  });
</script>
