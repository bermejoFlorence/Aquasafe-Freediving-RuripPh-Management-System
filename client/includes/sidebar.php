<!-- includes/sidebar.php -->
<nav class="sidebar" id="sidebar">
  <div class="logo-area">
    <img src="../uploads/logo.jpeg" alt="Logo" class="sidebar-logo">
    <!-- <div class="sidebar-title">Aquasafe RuripPH</div> (optional) -->
  </div>

  <div class="sidebar-nav">
    <a href="index.php"     class="<?php echo ($page === 'dashboard') ? 'active' : ''; ?>">
      <i class="fa-solid fa-chart-pie"></i> Dashboard
    </a>
    <a href="bookings.php"  class="<?php echo ($page === 'bookings') ? 'active' : ''; ?>">
      <i class="fa-solid fa-calendar-check"></i> Bookings
    </a>
    <a href="forum.php"     class="<?php echo ($page === 'forum') ? 'active' : ''; ?>">
      <i class="fa-solid fa-comments"></i> Forum
    </a>
  </div>
</nav>

<!-- Sidebar Overlay for mobile (click to close) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<script>
    const sidebar  = document.getElementById('sidebar');
const hamburger = document.getElementById('hamburger-btn');
const overlay  = document.getElementById('sidebar-overlay');

</script>