<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AquaSafe RuripPH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles/style.css">
    <!-- You can add a favicon or extra meta tags here -->
</head>
<body>
   <nav class="main-nav">
    <a href="/index.php" class="logo">AquaSafe RuripPH</a>
    <div class="nav-links" id="navLinks">
        <a href="index.php">Home</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="register.php">Register</a>
        <a href="login.php" class="btn-primary">Login</a>
    </div>
    <div class="burger" id="burgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </div>
</nav>
<script>
  // BURGER NAV JS
  const burger = document.getElementById('burgerBtn');
  const navLinks = document.getElementById('navLinks');
  burger.onclick = () => {
      navLinks.classList.toggle('open');
      burger.classList.toggle('active');
  };
  // Auto-close when clicking link (mobile)
  document.querySelectorAll('.nav-links a').forEach(link => {
      link.onclick = () => {
          navLinks.classList.remove('open');
          burger.classList.remove('active');
      };
  });

  // ----- ACTIVE HIGHLIGHT -----
  // 1) Mark active by current page (Home/Register/Login)
  (function markActiveByPath(){
    const cur = (location.pathname.split('/').pop() || 'index.php').toLowerCase();
    document.querySelectorAll('.nav-links a').forEach(a=>{
      const href = (a.getAttribute('href') || '').toLowerCase();
      if (!href || href.startsWith('#')) return;   // skip anchors here
      // match simple pages
      if (href === cur) a.classList.add('active');
      // also treat "/" as index.php
      if (cur === '' && href === 'index.php') a.classList.add('active');
    });
  })();

  // 2) Mark active by #hash for About/Contact on the homepage
  function setActiveByHash(){
    // remove previous
    document.querySelectorAll('.nav-links a').forEach(a=>a.classList.remove('active-hash'));
    const h = window.location.hash;
    if (h === '#about')   document.querySelector('.nav-links a[href="#about"]')?.classList.add('active-hash');
    if (h === '#contact') document.querySelector('.nav-links a[href="#contact"]')?.classList.add('active-hash');
  }
  window.addEventListener('hashchange', setActiveByHash);
  setActiveByHash();
</script>

