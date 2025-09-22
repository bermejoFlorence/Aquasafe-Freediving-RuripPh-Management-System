<?php include 'includes/header.php'; ?>

<style>
  :root{
    --teal:#1e8fa2; --ink:#186070; --muted:#6b8b97; --line:#e3f6fc;
  }
  .about-hero{
    background: linear-gradient(180deg, #b1e7fa 0%, #eaf6fa 70%, #bfe9f7 100%);
    padding: 54px 0 36px;
  }
  .wrap{ width:96%; max-width:1100px; margin:0 auto; }
  .about-hero h1{
    color:#16798e; font-size:2rem; font-weight:900; letter-spacing:.4px; text-align:center; margin:0 0 8px;
  }
  .about-hero p.lead{
    color:#232c33; text-align:center; max-width:820px; margin:0 auto 10px; line-height:1.55;
  }

  .about-section{ padding:28px 0; }
  .two-col{
    display:grid; grid-template-columns: 1.1fr .9fr; gap:22px; align-items:center;
  }

  /* Illustration card (kapalit ng real images) */
  .illus-card{
    background: linear-gradient(180deg, #eaf6fa 0%, #d9f1fb 100%);
    border:1px solid #e3f6fc; border-radius:20px; padding:0;
    box-shadow:0 10px 36px rgba(30,143,162,.12);
    overflow:hidden; position:relative; min-height:260px;
    display:flex; align-items:center; justify-content:center;
  }
  .illus-label{
    position:absolute; bottom:10px; right:12px; color:#18607099;
    font-size:.9rem; font-weight:700;
  }

  .about-h2{
    color:#1e8fa2; font-size:1.6rem; font-weight:900; letter-spacing:.02em; margin:0 0 8px;
  }
  .about-p{ color:#3a5b66; line-height:1.65; margin:0 0 8px; }

  .values{
    display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap:14px; margin-top:12px;
  }
  .card{
    background:#fff; border:1px solid #e3f6fc; border-radius:16px; padding:16px;
    box-shadow:0 8px 22px rgba(30,143,162,.08);
  }
  .card h3{ color:#186070; font-size:1.05rem; margin:0 0 6px; }
  .card p{ color:#52707c; margin:0; line-height:1.5; }

  .list{ margin:8px 0 0 0; padding-left:18px; color:#3a5b66; line-height:1.6; }
  .badge{
    display:inline-block; padding:4px 10px; border-radius:999px; font-weight:700; font-size:.78rem;
    color:#186070; background:#e8f7fb; border:1px solid #d4f1f9; margin-right:6px;
  }

  .cta-bar{
    background: linear-gradient(180deg, #eaf6fa 0%, #d9f1fb 100%);
    border-top:1px solid #e3f6fc; padding:24px 0 36px; margin-top:8px;
  }
  .cta-inner{
    background:#fff; border:1px solid #e3f6fc; border-radius:20px; padding:20px; text-align:center;
    box-shadow:0 8px 24px rgba(30,143,162,.10);
  }
  .cta-inner h3{ color:#1e8fa2; font-size:1.4rem; margin:0 0 6px; font-weight:900; }
  .btn-pill{
    display:inline-block; padding:12px 24px; border-radius:999px; color:#fff; text-decoration:none; font-weight:800;
    background: linear-gradient(90deg, #1e8fa2 60%, #55bde6 100%);
    box-shadow:0 8px 24px rgba(30,143,162,.15);
    transition: transform .12s ease;
  }
  .btn-pill:hover{ transform:scale(1.04); }

  @media (max-width: 920px){
    .two-col{ grid-template-columns: 1fr; }
    .values{ grid-template-columns: repeat(2,minmax(0,1fr)); }
  }
  @media (max-width: 560px){
    .values{ grid-template-columns: 1fr; }
  }
</style>

<!-- HERO -->
<section class="about-hero">
  <div class="wrap">
    <h1>About AquaSafe RuripPH</h1>
    <p class="lead">
      We blend adventure, safety, and community for all skill levels. Based in Daruanak Island, Pasacao,
      we make booking simple, gear handling seamless, and learning fun — so you can focus on the joy of free diving.
    </p>
  </div>
</section>

<!-- WHO WE ARE / STORY -->
<section class="about-section">
  <div class="wrap two-col">
    <div>
      <h2 class="about-h2">Who We Are</h2>
      <p class="about-p">
        AquaSafe RuripPH started with a simple goal: make freediving safe, accessible, and community-driven in Bicol.
        Founded by <b>Mr. Melecio B. Baricante III</b>, we combine local expertise with digital convenience — from online booking
        to organized gear handling and safety-first coaching.
      </p>
      <p class="about-p">
        Whether you're a beginner or leveling up your breath-hold, our coaches and crew will guide you through
        <span class="badge">briefings</span><span class="badge">proper techniques</span><span class="badge">respect for marine life</span>.
      </p>
    </div>

    <!-- Illustration: Diver & waves -->
    <div class="illus-card" aria-hidden="true">
      <svg viewBox="0 0 640 360" width="100%" height="100%" preserveAspectRatio="xMidYMid slice">
        <defs>
          <linearGradient id="g1" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%"  stop-color="#bfe9f7"/>
            <stop offset="100%" stop-color="#eaf6fa"/>
          </linearGradient>
        </defs>
        <rect width="640" height="360" fill="url(#g1)"/>
        <!-- Waves -->
        <path d="M0,260 C80,240 160,280 240,260 C320,240 400,280 480,260 C560,240 640,280 640,280 L640,360 L0,360 Z"
              fill="#9edbf2" opacity=".7"/>
        <path d="M0,290 C100,270 180,300 260,285 C340,270 420,300 520,285 C600,275 640,300 640,300 L640,360 L0,360 Z"
              fill="#7fcde8" opacity=".55"/>
        <!-- Simple diver icon -->
        <g transform="translate(380,120) scale(1.1)" fill="#16798e" opacity=".9">
          <circle cx="0" cy="0" r="16"/>
          <rect x="-8" y="12" width="90" height="14" rx="7"/>
          <rect x="50" y="26" width="46" height="10" rx="5" opacity=".7"/>
          <rect x="-20" y="26" width="40" height="10" rx="5" opacity=".7"/>
        </g>
        <!-- Bubbles -->
        <g fill="#ffffff" opacity=".8">
          <circle cx="360" cy="90" r="4"/><circle cx="352" cy="110" r="3"/><circle cx="368" cy="105" r="2.5"/>
        </g>
      </svg>
      <span class="illus-label">AquaSafe • Freediving</span>
    </div>
  </div>
</section>

<!-- MISSION & VALUES -->
<section class="about-section" style="background:#f4fbfe; border-top:1px solid var(--line); border-bottom:1px solid var(--line);">
  <div class="wrap">
    <h2 class="about-h2" style="text-align:center;">Our Mission & Values</h2>
    <div class="values">
      <div class="card">
        <h3>Safety First</h3>
        <p>Structured briefings, buddy checks, coach-to-diver ratios, and weather-aware decisions.</p>
      </div>
      <div class="card">
        <h3>Sustainability</h3>
        <p>Respect for marine life and coral-safe practices; leave no trace behind.</p>
      </div>
      <div class="card">
        <h3>Community</h3>
        <p>We learn together — join our forum for tips, experiences, and support.</p>
      </div>
      <div class="card">
        <h3>Convenience</h3>
        <p>Easy online booking, clear schedules, and organized gear handling.</p>
      </div>
    </div>
  </div>
</section>

<!-- SAFETY & STANDARDS -->
<section class="about-section">
  <div class="wrap">
    <h2 class="about-h2">Safety & Standards</h2>
    <ul class="list">
      <li>Mandatory <b>pre-dive briefing</b> and buddy system.</li>
      <li><b>Gear sanitation</b> and sizing assistance before each session.</li>
      <li>Reasonable coach-to-diver ratio; beginners welcome with close supervision.</li>
      <li>Weather policy: safety first — we may <b>reschedule</b> or issue credits/refunds when necessary.</li>
      <li>Honest medical declaration encouraged; consult your physician if unsure.</li>
    </ul>
  </div>
</section>

<!-- LOCATION (SVG map/pin illustration) -->
<section class="about-section" style="background:#f9fdff; border-top:1px solid var(--line); border-bottom:1px solid var(--line);">
  <div class="wrap two-col" style="align-items:start;">
    <div>
      <h2 class="about-h2">Where We Dive</h2>
      <p class="about-p">
        We operate in and around <b>Daruanak Island, Pasacao, Camarines Sur</b> — a beautiful spot with calm waters
        ideal for training and enjoying the reef. We coordinate timing around tides and weather for safety.
      </p>
      <p class="about-p">
        Need directions? Search “Daruanak Island, Pasacao” on your map app. You can also message us for detailed meet-up instructions.
      </p>
      <div style="margin-top:10px;">
        <a class="btn-pill" href="customer/register.php">Join the community</a>
      </div>
    </div>

    <div class="illus-card" aria-hidden="true">
      <svg viewBox="0 0 640 360" width="100%" height="100%" preserveAspectRatio="xMidYMid slice">
        <defs>
          <linearGradient id="g2" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%"  stop-color="#bfe9f7"/>
            <stop offset="100%" stop-color="#eaf6fa"/>
          </linearGradient>
        </defs>
        <rect width="640" height="360" fill="url(#g2)"/>
        <!-- Simplified island -->
        <ellipse cx="470" cy="230" rx="90" ry="26" fill="#9dd08d" opacity=".9"/>
        <ellipse cx="470" cy="240" rx="110" ry="16" fill="#6fbf7a" opacity=".6"/>
        <!-- Pin -->
        <g transform="translate(300,130)">
          <path d="M40 0c-22,0-40,17.9-40,40 0,29 40,70 40,70s40-41 40-70C80,17.9,62,0,40,0Z" fill="#1e8fa2"/>
          <circle cx="40" cy="40" r="14" fill="#fff"/>
        </g>
        <!-- Waves -->
        <path d="M0,300 C80,290 160,310 240,300 C320,290 400,310 480,300 C560,290 640,310 640,310 L640,360 L0,360 Z"
              fill="#7fcde8" opacity=".55"/>
      </svg>
      <span class="illus-label">Daruanak • Pasacao</span>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-bar">
  <div class="wrap cta-inner">
    <h3>Ready to Dive In?</h3>
    <p style="color:#52707c; margin:0 0 10px;">Create your free account to book a schedule, reserve gear, and get safety tips.</p>
    <a class="btn-pill" href="customer/register.php">Register</a>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
