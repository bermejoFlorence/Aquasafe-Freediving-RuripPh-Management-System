<?php
include 'includes/header.php';
?>

<section class="hero" style="
    min-height: calc(100vh - 90px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(180deg, #b1e7fa 0%, #eaf6fa 70%, #bfe9f7 100%);
    padding: 0;
    position: relative;
    overflow: hidden;
">
  <div class="hero-inner" style="
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      max-width: 1100px;
      gap: 2.2em;
      padding: 0 2em;
      margin: 0 auto;
      z-index: 2;
      min-height: 62vh;
  ">
    <div class="hero-text" style="
        flex: 1 1 470px;
        max-width: 470px;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
        font-size: 1em;
    ">
      <span class="badge-popular" style="
          display: inline-block;
          background: linear-gradient(90deg, #1e8fa2 60%, #55bde6 100%);
          color: #fff;
          font-weight: bold;
          font-size: 0.95em;
          letter-spacing: 0.05em;
          padding: 0.28em 1em;
          border-radius: 30px;
          margin-bottom: 0.5em;
          align-self: center;
          box-shadow: 0 2px 10px rgba(30,143,162,0.14);
          text-shadow: 0 1px 6px rgba(0,0,0,0.08);
      ">#1 Free Diving Experience in Pasacao</span>
      <h1 style="
          font-size: 2em;
          color: #16798e;
          font-weight: 900;
          margin-bottom: 0.28em;
          letter-spacing: 0.5px;
          line-height: 1.1;
      ">Let’s Dive In!</h1>
      <div class="tagline" style="
          font-size: 0.98em;
          color: #232c33;
          margin-bottom: 0.7em;
          line-height: 1.4;
      ">
        Turn your weekend into an adventure—explore the underwater beauty of Daruanak Island with AquaSafe RuripPH.
      </div>
      <ul class="hero-benefits" style="
          list-style: none;
          margin: 0.6em 0 1em 0;
          padding: 0;
          color: #1e8fa2;
          font-weight: 500;
          font-size: 1em;
      ">
        <li style="margin: 0.2em 0; padding-left: 0.5em;">✓ Hassle-free online booking</li>
        <li style="margin: 0.2em 0; padding-left: 0.5em;">✓ Clean, safe, & guided dives</li>
        <li style="margin: 0.2em 0; padding-left: 0.5em;">✓ Gear included & expert support</li>
      </ul>
      <a href="customer/register.php" class="btn-book" style="
          background: linear-gradient(90deg, #1e8fa2 60%, #55bde6 100%);
          color: #fff;
          border-radius: 45px;
          font-size: 1em;
          padding: 0.8em 2em;
          font-weight: bold;
          border: none;
          display: inline-block;
          margin-top: 0.4em;
          text-shadow: 0 2px 8px rgba(30,143,162,0.13);
          box-shadow: 0 6px 24px rgba(30,143,162,0.12);
          transition: background 0.2s, transform 0.13s;
          cursor: pointer;
      ">Book Your Dive Now</a>
    </div>
    <div class="hero-art" style="
        flex: 1 1 470px;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
    ">
      <img src="uploads/diving.jpg" alt="Free Diving in Pasacao" style="
          width: auto;
          height: 350px;
          max-height: 57vh;
          border-radius: 33px;
          display: block;
          box-shadow: 0 8px 38px rgba(30,143,162,0.11);
          background: #eaf6fa;
          object-fit: cover;
      ">
    </div>
  </div>
</section>

<section class="about">
    <h2>About AquaSafe RuripPH</h2>
    <p>
        At AquaSafe RuripPH, we blend adventure, safety, and convenience for divers of all skill levels.
        As Daruanak Island’s leading free diving service, we make booking easy, gear management seamless, and create a community where you belong.
        Founded by Mr. Melecio B. Baricante III, our mission is to empower your diving experience through digital innovation and genuine hospitality.
    </p>
</section>

<section class="features">
    <h2>Why Dive with Us?</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <span class="icon">📅</span>
            <h3>Online Booking</h3>
            <p>Book your dive slot from anywhere, anytime, with instant confirmation and reminders.</p>
        </div>
        <div class="feature-card">
            <span class="icon">📦</span>
            <h3>Inventory Tracking</h3>
            <p>We ensure all essential gear is ready and available – no more double-booked equipment or surprises.</p>
        </div>
        <div class="feature-card">
            <span class="icon">💳</span>
            <h3>Easy Payment</h3>
            <p>Secure your spot with a simple GCash payment – just upload proof and you’re good to go.</p>
        </div>
        <div class="feature-card">
            <span class="icon">💬</span>
            <h3>Community Forum</h3>
            <p>Join fellow divers, ask questions, share experiences, and promote safety & environmental awareness.</p>
        </div>
    </div>
</section>

<section class="steps">
    <h2>How It Works</h2>
    <div class="steps-grid">
        <div class="step-card">
            <span class="step-number">1</span>
            <h4>Register</h4>
            <p>Create your free account and join our growing diving family.</p>
        </div>
         <div class="step-arrow">→</div>
        <div class="step-card">
            <span class="step-number">2</span>
            <h4>Book & Reserve</h4>
            <p>Pick your preferred date and gear, and book your next dive adventure.</p>
        </div>
         <div class="step-arrow">→</div>
        <div class="step-card">
            <span class="step-number">3</span>
            <h4>Pay via GCash</h4>
            <p>Confirm your slot by uploading your GCash payment proof. Fast and secure.</p>
        </div>
         <div class="step-arrow">→</div>
        <div class="step-card">
            <span class="step-number">4</span>
            <h4>Enjoy & Connect</h4>
            <p>Experience Daruanak Island’s underwater beauty – and connect in our online forum!</p>
        </div>
    </div>
</section>

<!-- =========================
     FAQs (Q&A) - UI ONLY
     Inserted BEFORE CTA
========================== -->
<section class="faqs" style="
  padding: 36px 0 24px;
  background: linear-gradient(180deg, #eaf6fa 0%, #d9f1fb 100%);
">
  <div class="faqs-wrap" style="
    width: 96%;
    max-width: 1100px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 8px 28px rgba(30,143,162,0.10);
    padding: 26px 22px;
    border: 1px solid #e3f6fc;
  ">
    <h2 style="
      color:#1e8fa2; font-size:1.8rem; font-weight:900; text-align:center; letter-spacing:.02em; margin:0 0 8px;
    ">Frequently Asked Questions</h2>
    <p style="text-align:center; color:#186070; margin:0 0 18px;">
      Quick answers about booking, payment, gear, and safety.
    </p>

    <!-- FAQ Grid -->
    <div class="faq-grid" style="
      display:grid; gap:12px;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    ">
      <!-- Item -->
      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="
            flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;
          ">+</span>
          <span>How do I book a dive?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66; line-height:1.5;">
          Choose a date & package, submit the form, then upload your GCash proof to confirm your slot.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>What payment method do you accept?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          We currently support <b>GCash</b>. Upload your payment proof so we can verify your booking.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>When will I receive my confirmation?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          After verification you’ll see the status in your dashboard and receive a confirmation message.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>Can I reschedule or cancel?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          Yes. Eligibility and fees depend on lead time and weather policy. Manage it in your dashboard.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>How do refunds work?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          If eligible, we’ll process your refund request. Usual processing time is <b>3–7 business days</b>.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>Do I need to be a strong swimmer?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          Basic water comfort helps. Beginners are welcome—just follow the coach briefing closely.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>Do I need medical clearance?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          Recommended if you have respiratory or heart conditions. Please declare honestly for your safety.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>Can I rent gear on-site?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          Yes—limited sizes. Add gear during booking to reserve your size ahead of time.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>What if there’s bad weather?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          Safety first. We may reschedule or provide credits/refunds based on our weather policy.
        </div>
      </div>

      <div class="faq-item" style="background:#fff; border:1px solid #e8f7fb; border-radius:14px; box-shadow:0 4px 14px rgba(30,143,162,.06);">
        <button class="faq-q" aria-expanded="false" style="
          width:100%; text-align:left; background:transparent; border:none; padding:14px 16px; cursor:pointer;
          display:flex; align-items:center; gap:10px; color:#186070; font-weight:700; font-size:1rem;
        ">
          <span class="faq-icon" style="flex:0 0 28px; height:28px; border-radius:50%; border:2px solid #1e8fa2; display:inline-flex; align-items:center; justify-content:center; font-weight:900; color:#1e8fa2;">+</span>
          <span>Is there an age limit?</span>
        </button>
        <div class="faq-a" style="display:none; padding:0 16px 14px 54px; color:#3a5b66;">
          Minors need guardian consent; final approval depends on coach assessment.
        </div>
      </div>
    </div>

    <!-- Footer links of FAQs -->
    <div class="faq-join" style="margin-top:16px; display:flex; justify-content:center;">
  <a href="register.php"
     class="faq-cta"
     style="
        display:inline-flex; align-items:center; gap:10px;
        background: linear-gradient(90deg, #1e8fa2 60%, #55bde6 100%);
        color:#fff; font-weight:800; letter-spacing:.3px;
        padding:12px 24px; border-radius:999px; text-decoration:none;
        box-shadow:0 6px 20px rgba(30,143,162,.15);
        transition: transform .12s ease;
     "
     onmouseover="this.style.transform='scale(1.04)';"
     onmouseout="this.style.transform='scale(1)';">
    <span>For more info & member perks, join our community</span>
    <span aria-hidden="true">→</span>
  </a>
</div>

  </div>

  <!-- Tiny script for accordion -->
  <script>
    (function(){
      var qs = document.querySelectorAll('.faq-q');
      qs.forEach(function(btn){
        btn.addEventListener('click', function(){
          var expanded = this.getAttribute('aria-expanded') === 'true';
          this.setAttribute('aria-expanded', String(!expanded));
          var ans = this.parentElement.querySelector('.faq-a');
          var icon = this.querySelector('.faq-icon');
          if(ans){
            ans.style.display = expanded ? 'none' : 'block';
          }
          if(icon){
            icon.textContent = expanded ? '+' : '–';
          }
        });
      });

      // Responsive: stack to 1 column on small screens
      function adjustCols(){
        var grid = document.querySelector('.faq-grid');
        if(!grid) return;
        if(window.innerWidth <= 860){
          grid.style.gridTemplateColumns = '1fr';
        } else {
          grid.style.gridTemplateColumns = 'repeat(2, minmax(0, 1fr))';
        }
      }
      window.addEventListener('resize', adjustCols);
      adjustCols();
    })();
  </script>
</section>
<!-- =========================
     END FAQs
========================== -->

<section class="cta">
    <h2>Ready to Dive In?</h2>
    <div class="cta-actions">
        <a href="customer/register.php"
   style="
      display: inline-block;
      background: #198fa3;
      color: #fff;
      font-size: 1.24em;
      font-weight: bold;
      padding: 0.7em 2.7em;
      border-radius: 50px;
      border: none;
      outline: none;
      text-align: center;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(30,143,162,0.07);
      transition: background 0.17s, transform 0.11s;
      margin-top: 1.2em;
      letter-spacing: 1px;
   "
   onmouseover="this.style.background='#176e80'; this.style.transform='scale(1.06)';"
   onmouseout="this.style.background='#198fa3'; this.style.transform='scale(1)';"
>
   Register
</a>

    </div>
</section>

<?php
include 'includes/footer.php';
?>
