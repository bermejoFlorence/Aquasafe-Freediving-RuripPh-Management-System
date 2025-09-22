<?php
// Start session for CSRF token
session_start();
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include 'includes/header.php';
?>

<style>
  :root{ --teal:#1e8fa2; --ink:#186070; --muted:#6b8b97; --line:#e3f6fc; }

  /* Slim hero */
  .contact-hero{
    background: linear-gradient(180deg,#b1e7fa 0%,#eaf6fa 70%,#bfe9f7 100%);
    padding: 20px 0 12px; border-bottom:1px solid var(--line);
  }
  .wrap{ width:94%; max-width:1100px; margin:0 auto; }
  .contact-hero h1{ color:#16798e; font-weight:900; font-size:1.75rem; margin:0 0 4px; letter-spacing:.3px; }
  .contact-hero p{ color:#232c33; margin:0; font-size:.98rem; }

  /* Layout compact */
  .contact-section{ padding:14px 0 20px; }
  .contact-grid{
    display:grid; grid-template-columns: 1fr 1fr; gap:16px; align-items:start;
  }
  .card{
    background:#fff; border:1px solid #e3f6fc; border-radius:16px; padding:14px;
    box-shadow:0 8px 24px rgba(30,143,162,.10);
  }

  /* Form (compact + polished) */
  .contact-form .field{ margin-bottom:10px; }
  .contact-form label{ display:block; color:#186070; font-weight:700; margin:0 2px 6px; font-size:.93rem; }
  .contact-form input,.contact-form textarea{
    width:100%; box-sizing:border-box;
    font:inherit; color:#1b2b32; background:#fff;
    border:1.5px solid #d8edf5; border-radius:12px; padding:10px 12px; outline:none; line-height:1.35;
    box-shadow:0 1px 4px rgba(30,143,162,.05);
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  .contact-form input{ height:44px; }
  .contact-form textarea{ min-height:120px; resize:vertical; padding-top:10px; }
  .contact-form input::placeholder,.contact-form textarea::placeholder{ color:#7ba0ac; }
  .contact-form input:focus,.contact-form textarea:focus{ border-color:#1e8fa2; box-shadow:0 0 0 3px rgba(30,143,162,.18); }
  .contact-form .field:focus-within{ filter:saturate(1.03); }
  .contact-form .is-invalid{ border-color:#e85b5b !important; box-shadow:0 0 0 3px rgba(232,91,91,.15) !important; }

  .btn-send{
    display:inline-block; background:#198fa3; color:#fff; border:none;
    padding:11px 22px; border-radius:999px; font-weight:800; letter-spacing:.3px; cursor:pointer;
    box-shadow:0 8px 20px rgba(30,143,162,.18);
    transition:transform .12s ease, background .12s ease;
  }
  .btn-send[disabled]{ opacity:.7; cursor:not-allowed; }
  .btn-send:hover:not([disabled]){ background:#176e80; transform:translateY(-1px) scale(1.02); }

  /* Toast */
  .toast{
    opacity:0; transform:translateY(6px);
    background:#e6faf0; color:#176e80; border:1px solid #bdebd6;
    padding:8px 12px; border-radius:10px; margin-top:8px; font-weight:700; font-size:.92rem;
    transition:opacity .18s ease, transform .18s ease;
  }
  .toast.show{ opacity:1; transform:translateY(0); }
  .toast.error{ background:#fdeaea; color:#7a1111; border-color:#f3c2c2; }

  /* Details + mini-map */
  .details h3{ color:#186070; margin:0 0 6px; font-size:1.05rem; }
  .dline{ display:flex; align-items:flex-start; gap:10px; margin:8px 0; color:#52707c; font-size:.98rem; }
  .dicon{
    flex:0 0 30px; height:30px; border-radius:50%; border:2px solid var(--teal);
    display:inline-flex; align-items:center; justify-content:center; color:var(--teal); font-weight:900; font-size:.95rem;
  }
  .map-wrap{ margin-top:10px; border:1px solid #e3f6fc; border-radius:12px; overflow:hidden; background:#eef8fb; box-shadow:0 8px 22px rgba(30,143,162,.12); }
  .map-wrap iframe{ width:100%; height:220px; border:0; display:block; }

  @media (max-width: 920px){
    .contact-grid{ grid-template-columns:1fr; }
    .map-wrap iframe{ height:240px; }
    .btn-send{ width:100%; }
  }
</style>

<section class="contact-hero">
  <div class="wrap">
    <h1 id="contact">Contact Us</h1>
    <p>Questions about booking, gear, or schedules? Send us a message — happy to help!</p>
  </div>
</section>

<section class="contact-section">
  <div class="wrap contact-grid">

    <!-- LEFT: FORM -->
    <div class="card">
      <form class="contact-form" id="contactForm" action="contact_submit.php" method="post">
        <!-- CSRF + honeypot -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
        <input type="text" name="website" value="" style="display:none !important;" tabindex="-1" autocomplete="off" aria-hidden="true">

        <div class="field">
          <label for="cname">Your Name</label>
          <input id="cname" type="text" name="name" placeholder="Juan Dela Cruz" required autocomplete="name">
        </div>

        <div class="field">
          <label for="cphone">Phone Number</label>
          <input id="cphone" type="tel" name="phone" placeholder="+63 9XX XXX XXXX" required autocomplete="tel">
        </div>

        <div class="field">
          <label for="cemail">Email</label>
          <input id="cemail" type="email" name="email" placeholder="you@email.com" required autocomplete="email">
        </div>

        <div class="field">
          <label for="cmsg">Message</label>
          <textarea id="cmsg" name="message" placeholder="How can we help?" required></textarea>
        </div>

        <button type="submit" class="btn-send" id="sendBtn">Send Message</button>
        <div id="contactToast" class="toast" role="status" aria-live="polite"></div>
      </form>
    </div>

    <!-- RIGHT: DETAILS + MINI MAP -->
    <div class="card">
      <div class="details">
        <h3>Reach Us</h3>

        <div class="dline">
          <span class="dicon" aria-hidden="true">📍</span>
          <div>
            Daruanak Island, Pasacao, Camarines Sur<br>
            <a href="https://www.google.com/maps/search/?api=1&query=Daruanak+Island%2C+Pasacao%2C+Camarines+Sur"
               target="_blank" style="color:#1e8fa2; font-weight:700; text-decoration:none;">
              Open in Google Maps →
            </a>
          </div>
        </div>

        <div class="dline">
          <span class="dicon" aria-hidden="true">✉️</span>
          <div>aquasafe@example.com</div>
        </div>

        <div class="dline">
          <span class="dicon" aria-hidden="true">📞</span>
          <div>+63 9XX XXX XXXX</div>
        </div>

        <div class="dline">
          <span class="dicon" aria-hidden="true">⏰</span>
          <div>Mon–Sun · 8:00 AM – 6:00 PM</div>
        </div>
      </div>

      <div class="map-wrap" aria-label="Map to RURIP PH Freediving/Spearfishing">
        <iframe
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3879.576759168771!2d123.06035857513194!3d13.500157686865931!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a227da5ce31753%3A0x953ce6068ce83500!2sRURIP%20PH%20Freediving%2FSpearfishing!5e0!3m2!1sen!2sph!4v1757159660353!5m2!1sen!2sph">
        </iframe>
      </div>
    </div>

  </div>
</section>

<script>
  // AJAX submit for better UX
  const form = document.getElementById('contactForm');
  const toast = document.getElementById('contactToast');
  const btn = document.getElementById('sendBtn');

  form.addEventListener('submit', async function(e){
    e.preventDefault();

    // clear previous errors
    ['cname','cphone','cemail','cmsg'].forEach(id => {
      document.getElementById(id).classList.remove('is-invalid');
    });
    toast.className = 'toast'; // reset
    toast.textContent = '';

    btn.disabled = true;

    try {
      const fd = new FormData(form);
      const res = await fetch(form.action, {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const data = await res.json();

      if (data.ok) {
        toast.textContent = data.msg || 'Sent!';
        toast.classList.add('show');
        form.reset();
      } else {
        toast.textContent = data.msg || 'Please check your inputs.';
        toast.classList.add('show', 'error');
        if (data.errors) {
          if (data.errors.name)    document.getElementById('cname').classList.add('is-invalid');
          if (data.errors.phone)   document.getElementById('cphone').classList.add('is-invalid');
          if (data.errors.email)   document.getElementById('cemail').classList.add('is-invalid');
          if (data.errors.message) document.getElementById('cmsg').classList.add('is-invalid');
        }
      }
    } catch (err) {
      toast.textContent = 'Network error. Please try again.';
      toast.classList.add('show', 'error');
    } finally {
      btn.disabled = false;
      setTimeout(()=>toast.classList.remove('show','error'), 3200);
    }
  });
</script>

<?php include 'includes/footer.php'; ?>
