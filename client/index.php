<?php
session_start();
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'client')) {
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Access Denied</title>
  <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>
  <script>
    Swal.fire({icon:'error',title:'Access Denied',text:'You do not have permission to access this page.',confirmButtonColor:'#1e8fa2'})
      .then(()=>{ window.location='../login.php'; });
  </script></body></html>";
  exit;
}
require_once '../db_connect.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$stm = $conn->prepare("SELECT account_status, banned_until, banned_reason, session_version FROM user WHERE user_id=? LIMIT 1");
$stm->bind_param("i", $uid);
$stm->execute();
$row = $stm->get_result()->fetch_assoc();
$stm->close();

$kick = function(string $msg) {
  echo "<script>
    Swal.fire({icon:'error',title:'Session ended',html:".json_encode($msg).",confirmButtonColor:'#1e8fa2'})
      .then(()=>{ window.location='../login.php'; });
  </script></body></html>";
  session_destroy(); exit;
};

if (!$row) { $kick('Your session is invalid. Please log in again.'); }

if (($row['account_status'] ?? 'active') === 'banned') {
  $until = $row['banned_until'] ?? null;
  if (empty($until) || strtotime($until) > time()) {
    $reason = htmlspecialchars($row['banned_reason'] ?? '', ENT_QUOTES, 'UTF-8');
    $msg = 'Your account is banned.';
    if ($reason) $msg .= '<br><b>Reason:</b> '.$reason;
    if ($until)  $msg .= '<br><b>Until:</b> '.date('M j, Y g:ia', strtotime($until));
    $kick($msg);
  }
}

if ((int)($_SESSION['session_version'] ?? 0) !== (int)($row['session_version'] ?? 0)) {
  $kick('Your session was invalidated by an admin action. Please log in again.');
}
?>

<?php
// ==== BEST SELLER BASE SA BOOKING COUNT ====

// 1) Alamin kung aling package ang may pinakamaraming booking
$bestSellerId = null;

// kung gusto mo lahat ng bookings:
$bestSql = "
    SELECT package_id, COUNT(*) AS cnt
    FROM booking
    GROUP BY package_id
    ORDER BY cnt DESC
    LIMIT 1
";

// kung gusto mo approved/confirmed lang, pwede mo itong version na ito:
// $bestSql = "
//     SELECT package_id, COUNT(*) AS cnt
//     FROM booking
//     WHERE status IN ('approved','completed')
//     GROUP BY package_id
//     ORDER BY cnt DESC
//     LIMIT 1
// ";

$bestRes = $conn->query($bestSql);
if ($bestRes && $bestRow = $bestRes->fetch_assoc()) {
    $bestSellerId = (int)$bestRow['package_id'];
}

// 2) Kunin packages + features
$packages = [];
$sql = "SELECT 
            p.package_id, 
            p.name, 
            p.price, 
            p.description, 
            pf.feature 
        FROM package p
        LEFT JOIN package_feature pf ON pf.package_id = p.package_id
        ORDER BY p.package_id ASC, pf.feature_id ASC";

$result = $conn->query($sql);

// Group features by package + mark best seller
foreach ($result as $row) {
    $id = (int)$row['package_id'];

    if (!isset($packages[$id])) {
        $packages[$id] = [
            'name'        => $row['name'],
            'price'       => $row['price'],
            'description' => $row['description'],
            'features'    => [],
            'best'        => ($id === $bestSellerId)  // ✅ ito ang flag
        ];
    }

    if (!empty($row['feature'])) {
        $packages[$id]['features'][] = $row['feature'];
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aquasafe RuripPh Client Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- FullCalendar CSS & JS CDN (V6 Bundle) -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

    <link rel="stylesheet" href="styles/style.css">

  <style>
/* ===================== */
/* Tokens & base */
/* ===================== */
:root{
  --teal:#1e8fa2;
  --teal-600:#177b8f;
  --teal-700:#126b7d;
  --ink:#164b5a;
  --bg-grad: linear-gradient(180deg,#cfeff8 0%, #e9f9ff 55%, #f6fdff 100%);
  --white:#fff;
  --shadow:0 10px 30px rgba(7,57,75,.12);
  --ring:0 0 0 6px #1e90a214;
}

*{ box-sizing:border-box; }
body{
  background: var(--bg-grad);
  overflow-x:hidden;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

/* ===================== */
/* Page layout wrappers  */
/* ===================== */
.dashboard-main{
  width:100%;
  min-height:80vh;
  padding: 28px 18px 40px;
  display:flex; flex-direction:column; align-items:center;
}

/* (Optional) hero banner feel for the section */
.dashboard-main::before{
  content:"";
  width:min(1120px, 100%);
  height:160px;
  margin:0 auto 18px;
  display:block;
  border-radius:22px;
  background:
    radial-gradient(1200px 280px at 10% -30%, #ffffffc4, transparent 60%),
    radial-gradient(800px 240px at 95% 120%, #bbf1ff66, transparent 60%),
    #ffffffb3;
  box-shadow: var(--shadow);
}

/* ===================== */
/* Packages grid & cards */
/* ===================== */

/* Turn your container into an auto-fit grid */
.package-container{
  width:min(1120px, 100%);
  margin:0 auto;
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap:22px;
  padding: 0 10px;
}

/* Card refresh (keeps your .card markup) */
.card{
  position:relative;
  background: var(--white);
  border-radius: 20px;
  padding: 22px 20px 18px;
  box-shadow: var(--shadow);
  outline:1px solid #d9f2f7;
  transition: transform .22s ease, box-shadow .22s ease, outline-color .2s;
  text-align:center;
  display:flex; flex-direction:column; justify-content:flex-start;
}
.card:hover{
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(10,104,124,.18);
  outline-color:#9ad9e7;
}

.card.best-seller .best-pill{
  content:"Best Seller";
  position:absolute;
  top:14px;
  right:-8px;
  background: linear-gradient(90deg,#ffb703,#ffd166);
  color:#6b4100;
  font-weight:800;
  font-size:12.5px;
  padding:6px 12px;
  border-radius:999px;
  box-shadow: 0 8px 20px #ffbd0f33;
}


/* Title & price pill */
.card h2{
  margin:4px 0 6px;
  color: var(--teal-700);
  font-size: clamp(18px, 2.5vw, 22px);
  font-weight:900; letter-spacing:.3px;
}
.card p.price{
  display:inline-flex; align-items:baseline; gap:6px;
  background:#e9fbff; color:#0f7287; border:1px solid #bdecf6;
  padding:8px 14px; border-radius:999px; font-weight:800;
  font-size: 16px; margin: 4px auto 8px;
}

/* Feature text (re-style your <p> items inside the card) */
.card p{
  margin:.28em 0;
  font-size: 14.8px;
  color:#1f5b6a;
  display:flex; gap:10px; align-items:flex-start; justify-content:center;
}
.card p::before{
  content:"\f058"; /* fa-circle-check */
  font: normal 900 15px/1 "Font Awesome 6 Free";
  color:#1aa6bd; margin-top:2px;
}
/* Image wrapper sa taas ng card */
.card-img{
  width: 100%;
  aspect-ratio: 4 / 3;        /* pwede mong gawing 16/9 kung gusto mo mas wide */
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 12px;
  background: #e3f6fb;        /* light fallback bg kung mabagal mag-load */
}

.card-img img{
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* CTA button */
.book-btn{
  margin-top: 10px;
  width:100%;
  padding: 12px 18px;
  background: var(--teal);
  color:#fff; border:0; border-radius: 12px;
  font-weight:800; font-size: 16px; letter-spacing:.2px;
  cursor:pointer;
  box-shadow: 0 10px 22px rgba(26,151,173,.28);
  transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
}
.book-btn:hover{ background: var(--teal-600); transform: translateY(-1px); box-shadow: 0 14px 26px rgba(26,151,173,.32); }
.book-btn:active{ transform: translateY(0); box-shadow: 0 8px 18px rgba(26,151,173,.26); }
.book-btn:focus{ outline:none; box-shadow: var(--ring); }

/* Little pulse you already have */
@keyframes btnPulse { 0%{transform:scale(1);} 50%{transform:scale(1.07);} 100%{transform:scale(1);} }
.book-btn.animated{ animation: btnPulse .25s; }

/* ===================== */
/* Calendar modal shell  */
/* ===================== */
#calendar-modal{
  display:none; position:fixed; z-index:9999; inset:0;
  width:100vw; height:100vh;
  background: rgba(0,0,0,.65);
  align-items:center; justify-content:center;
  transition: background .25s;
}
.calendar-content{
  background:#fff; padding: 18px 18px;
  border-radius: 16px; width:100%; max-width:640px;
  box-shadow: 0 8px 40px rgba(0,0,0,.16);
  display:flex; flex-direction:column; align-items:center;
  margin: 2.5vw;
  animation: fadeInUpModal .37s cubic-bezier(.4,1.6,.5,1) both;
}
#calendar{ width:100%; }

/* Modal animation keyframes (shared) */
@keyframes fadeInUpModal{
  from{ opacity:0; transform: translateY(60px) scale(.96); }
  to{ opacity:1; transform: translateY(0) scale(1); }
}

/* ===================== */
/* Booking details modal */
/* ===================== */
#booking-details-modal{
  display:none; position:fixed; z-index:10000; inset:0;
  width:100vw; height:100vh;
  background: rgba(0,0,0,.65);
  align-items:center; justify-content:center;
}
.booking-modal-card{
  background:#fff; border-radius: 22px;
  max-width: 520px; width: 96vw;
  box-shadow: 0 8px 44px rgba(0,80,120,.18);
  padding: 26px 24px 16px;
  display:flex; flex-direction:column; align-items:stretch;
  animation: fadeInUpModal .38s cubic-bezier(.4,1.6,.5,1) both;
}
.booking-modal-card h2{
  color:#1593a6; font-weight:800; font-size: 26px;
  text-align:center; letter-spacing:.01em; margin:0 0 10px;
}
.booking-modal-table{ margin-bottom: 14px; }
.booking-modal-table .row{
  display:flex; justify-content:space-between; align-items:center;
  padding: 6px 0; font-size: 16px;
}
.booking-modal-table .label{
  color:#177687; font-weight:800; letter-spacing:.02em;
  min-width: 72px; padding-right:.6em;
}
.booking-modal-table .value{
  color:#173d54; font-weight:600; width:58%; min-width:120px; text-align:left;
  word-break: break-word; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  position:relative;
}
.booking-modal-table .value[title]:hover::after{
  content: attr(title); position:absolute; left:0; top:130%;
  background:#fff; color:#106d92; border:1px solid #1e8fa2;
  padding:6px 12px; border-radius:8px; font-size:.95rem; white-space:normal; z-index:5;
  box-shadow: 0 2px 10px #0002; max-width: 330px;
}
.booking-modal-note{
  width:100%; background:#f3fafb; border-radius:12px;
  padding: 12px; margin-bottom:12px; color:#106d92;
  font-size: 1rem; border:1px solid #eaf7fc; font-style:italic;
}
.booking-modal-note b{ font-style:normal; color:#1593a6; letter-spacing:.02em; }

#bd-note{
  width:100%; min-height: 56px; resize:vertical;
  border-radius: 10px; border: 1.7px solid #b7e3f0;
  padding: 10px 12px; font-size: 1rem; margin-bottom: 12px;
  transition: border .2s;
}
#bd-note:focus{ border:1.7px solid #1e8fa2; outline:none; }

#confirm-booking-btn{
  padding: 12px 18px; background:#1593a6; color:#fff;
  border:none; border-radius: 12px; font-weight:800; font-size: 16px;
  cursor:pointer; margin-bottom:8px;
  box-shadow: 0 10px 22px rgba(17,189,214,.30);
  transition: background .18s, transform .18s;
}
#confirm-booking-btn:hover{ background:#12758a; transform: translateY(-1px); }
.cancel-btn{
  background:none; border:none; color:#1593a6; text-decoration:underline;
  font-size: 14px; cursor:pointer; padding:.25em; margin-top:-.2em;
}

/* ===================== */
/* FullCalendar slot pills */
/* ===================== */
.fc-event.slot-pill{
  border-radius: 1.2em !important;
  background: #1976d2 !important;
  color:#fff !important; border:none !important;
  padding: 4px 13px !important; font-size:.92em !important;
  margin-bottom:3px; box-shadow: 0 2px 8px #78b8fa34;
  font-weight:600; text-align:center; cursor:pointer !important;
  transition: box-shadow .18s, background .18s, transform .18s;
  outline:none; border:2px solid transparent; user-select:none; white-space:nowrap; line-height:1.2;
}
.fc-event.slot-pill:hover,
.fc-event.slot-pill:focus{
  background:#1560ad !important;
  box-shadow:0 4px 14px #8dc7fa42; transform: translateY(-2px) scale(1.04);
  border:2px solid #94d0ff;
}
.fc-event.full-pill{
  border-radius:1.6em !important;
  background:#ffb3b3 !important; color:#a70a2e !important; border:none !important;
  padding:2px 12px !important; font-size:1.06em !important;
  margin-bottom:2px; box-shadow:0 2px 8px #ffa3a349; font-weight:600; line-height:1.2; text-align:center;
}

/* ===================== */
/* Responsive tweaks     */
/* ===================== */
@media (max-width: 900px){
  .dashboard-main::before{ height:130px; }
}
@media (max-width: 700px){
  .calendar-content{ max-width: 98vw; padding: 14px 10px; margin: 2vw; }
  #calendar{ font-size:.92em; }
}
@media (max-width: 600px){
  .booking-modal-card{ max-width: 99vw; padding: 16px 10px 10px; border-radius:16px; }
  .booking-modal-table .label{ width:38%; font-size:.97em; padding-right:.7em; }
  .booking-modal-table .value{ width:60%; font-size:.98em; }
}
@media (max-width: 480px){
  .calendar-content{ max-width: 97vw; padding: 10px 8px; border-radius:12px; }
}
/* ===== Hero Carousel ===== */
.hero-carousel{
  width: min(1120px, 100%);
  margin: 8px auto 22px;
  position: relative;
  border-radius: 22px;
  box-shadow: 0 10px 30px rgba(7,57,75,.12);
  overflow: hidden;
  background:#eafaff;
  isolation: isolate;   /* for overlay blend */
}
.hc-viewport{ width:100%; overflow:hidden; }
.hc-track{
  display:flex; will-change: transform;
  transition: transform .55s cubic-bezier(.22,.85,.32,1);
}
.hc-slide{
  flex: 0 0 100%;
  position: relative;
  height: clamp(180px, 30vw, 320px); /* responsive height */
  background:#cfeff8;
}
.hc-slide img{
  width:100%; height:100%; object-fit:cover; display:block;
  filter: saturate(1.05) contrast(1.02);
}
.hc-overlay{
  position:absolute; inset:0;
  background: linear-gradient(180deg, #001b2633 10%, #001b2600 45%, #001b2640 100%);
  display:flex; flex-direction:column; align-items:flex-start; justify-content:flex-end;
  padding: clamp(12px, 3vw, 24px);
  color:#fff; text-shadow:0 2px 12px rgba(0,0,0,.35);
}
.hc-overlay h2{
  margin:0 0 4px; font-weight:900; letter-spacing:.2px;
  font-size: clamp(18px, 3.2vw, 28px);
}
.hc-overlay p{
  margin:0 0 6px; font-size: clamp(13px, 2.1vw, 15.5px);
  opacity:.95;
}
/* arrows */
.hc-arrow{
  position:absolute; top:50%; transform:translateY(-50%);
  width:40px; height:40px; border-radius:999px; border:none;
  display:grid; place-items:center; cursor:pointer;
  color:#0d5b6a; background:#ffffffcc; backdrop-filter: blur(4px);
  box-shadow: 0 6px 18px rgba(0,0,0,.12);
  transition: background .2s, transform .2s, box-shadow .2s;
  z-index:2;
}
.hc-prev{ left:10px; }
.hc-next{ right:10px; }
.hc-arrow:hover{ background:#fff; transform:translateY(-50%) scale(1.05); box-shadow:0 10px 24px rgba(0,0,0,.16); }
.hc-arrow i{ font-size: 16px; }

/* dots */
.hc-dots{
  position:absolute; left:0; right:0; bottom:10px;
  display:flex; gap:8px; justify-content:center; z-index:2;
}
.hc-dots button{
  width:9px; height:9px; border-radius:50%; border:0; cursor:pointer;
  background:#e8f8fc; opacity:.65; transition: transform .18s, opacity .18s, background .18s;
}
.hc-dots button[aria-selected="true"]{
  background:#1e8fa2; opacity:1; transform: scale(1.15);
}

/* mobile tweaks */
@media (max-width: 700px){
  .hc-arrow{ width:36px; height:36px; }
}
/* 1) Tanggalin ang box sa itaas (hero placeholder) */
.dashboard-main::before{
  display: none !important;
  content: none !important;
  height: 0 !important;
  margin: 0 !important;
}

/* 2) Edge-to-edge layout para sa loob ng content area */
.dashboard-main{
  padding-left: 0 !important;
  padding-right: 0 !important;
}

/* Kung may horizontal padding ang .content-area mula sa header include,
   pwede mo ring i-zero para super wide talaga: */
.content-area{
  padding-left: 0 !important;
  padding-right: 0 !important;
}

/* 3) Carousel & packages: gamitin ang buong lapad */
.hero-carousel,
.package-container{
  width: 100% !important;        /* full width ng content area */
  margin-left: 0 !important;
  margin-right: 0 !important;
  border-radius: 0 !important;   /* straight edges para tunay na wide look */
}

/* 4) Safe side padding para di dumikit sa screen edges on mobile/desktop */
.hero-carousel,
.package-container{
  padding-inline: clamp(10px, 2vw, 24px) !important;
}

/* 5) Wider cards per row (mas konting gutters, mas lapad bawat card) */
.package-container{
  gap: clamp(14px, 2vw, 24px) !important;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important;
}

/* 6) Slightly tighter card radius (optional aesthetic) */
.card{ border-radius: 16px !important; }

/* 7) Tweak ng carousel arrows para hindi sumabit sa gilid kapag full width */
.hc-prev{ left: clamp(6px, 1.2vw, 12px) !important; }
.hc-next{ right: clamp(6px, 1.2vw, 12px) !important; }

/* Huwag maglagay ng padding sa mismong width basis ng slides */
.hero-carousel{ padding: 0 !important; }
.hc-viewport{ padding: 0 !important; overflow: hidden; }

/* Eksaktong lapad bawat slide = 100% ng viewport (walang padding factor) */
.hc-track{ display:flex; margin:0; }
.hc-slide{ flex: 0 0 100%; width: 100%; }

/* Consistent visual height: choose one — 16:6 gives cinematic; adjust to taste */
.hc-slide{
  aspect-ratio: 16 / 6;     /* keeps same height across images */
  height: auto;             /* let aspect-ratio control the height */
}

/* Overlay padding sa loob (dito tayo magbibigay ng “breathing space”, hindi sa viewport) */
.hc-overlay{ padding: clamp(14px, 3vw, 28px) !important; }

/* Optional: center ang subject ng photo; pwede ring 'center top' depende sa larawan */
.hc-slide img{
  object-fit: cover;
  object-position: center;
}

</style>
</head>
<body>
    <?php $page = 'dashboard'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main class="content-area">
        <?php include 'includes/header.php'; ?>
        <div class="dashboard-main">
          <!-- HERO CAROUSEL -->
<div class="hero-carousel" id="heroCarousel" aria-label="Featured photos">
  <button class="hc-arrow hc-prev" aria-label="Previous slide">
    <i class="fa-solid fa-chevron-left"></i>
  </button>

  <div class="hc-viewport">
    <div class="hc-track">
      <!-- Slide 1 -->
      <div class="hc-slide">
        <img src="../uploads/hero/reef-1.jpeg" alt="Open water session over coral reef">
        <div class="hc-overlay">
          <h2>Discover Breathtaking Reefs</h2>
          <p>Beginner-friendly, guided by certified coaches.</p>
        </div>
      </div>

      <!-- Slide 2 -->
      <div class="hc-slide">
        <img src="../uploads/hero/freedive-2.jpg" alt="Freediver practicing equalization">
        <div class="hc-overlay">
          <h2>Learn the Essentials</h2>
          <p>Equalization, rescue, and gear familiarization.</p>
        </div>
      </div>

      <!-- Slide 3 -->
      <div class="hc-slide">
        <img src="../uploads/hero/boat-3.jpg" alt="Boat heading to the dive site at sunrise">
        <div class="hc-overlay">
          <h2>Weekend Getaway</h2>
          <p>Meals, boat fare, and raw photos included.</p>
        </div>
      </div>
    </div>
  </div>

  <button class="hc-arrow hc-next" aria-label="Next slide">
    <i class="fa-solid fa-chevron-right"></i>
  </button>

  <div class="hc-dots" role="tablist" aria-label="Carousel pagination"></div>
</div>

            <div class="package-container">
          <?php foreach ($packages as $id => $pkg): ?>

  <?php
    // Piliin ang tamang image filename base sa package_id
    $imageFile = null;
    if ($id === 1) {
        $imageFile = 'package_1.jfif';
    } elseif ($id === 2) {
        $imageFile = 'package_2.jfif';
    }
  ?>

  <div class="card <?= $pkg['best'] ? 'best-seller' : '' ?>">
      <?php if ($pkg['best']): ?>
        <span class="best-pill">Best Seller</span>
      <?php endif; ?>

      <?php if ($imageFile): ?>
        <div class="card-img">
          <img src="<?= htmlspecialchars($imageFile) ?>"
               alt="<?= htmlspecialchars($pkg['name']) ?>">
        </div>
      <?php endif; ?>

      <h2><?= htmlspecialchars($pkg['name']) ?></h2>
      <p class="price">P <?= number_format($pkg['price'], 2) ?> / pax</p>

      <?php foreach ($pkg['features'] as $feature): ?>
        <p><?= htmlspecialchars($feature) ?></p>
      <?php endforeach; ?>

      <button class="book-btn"
              data-package="<?= htmlspecialchars($pkg['name']) ?>"
              data-price="<?= number_format($pkg['price'], 2, '.', '') ?>"
              data-package-id="<?= $id ?>">
        Book Now
      </button>
  </div>

<?php endforeach; ?>


            </div>
        </div>

        <!-- Calendar Modal -->
        <div id="calendar-modal">
            <div class="calendar-content">
                <div id="calendar"></div>
            </div>
        </div>

        <!-- Booking Details Modal (Step 2) -->
        <div id="booking-details-modal">
            <div class="booking-modal-card">
                <h2>Booking Details</h2>
                <div class="booking-modal-table">
                <div class="row">
                    <span class="label">Package</span>
                    <span class="value" id="bd-package"></span>
                </div>
                <div class="row">
                    <span class="label">Date</span>
                    <span class="value" id="bd-date"></span>
                </div>
                <div class="row">
                    <span class="label">Name</span>            <!-- ADD THIS -->
                    <span class="value" id="bd-name"></span>   <!-- ADD THIS -->
                </div>
                <div class="row">
                    <span class="label">Email</span>
                    <span class="value" id="bd-email"></span>
                </div>
                <div class="row">
                    <span class="label">Contact No.</span>
                    <span class="value" id="bd-contact"></span>
                </div>
                <div class="row">
                  <span class="label">Amount</span>
                  <span class="value" id="bd-amount"></span>
                </div>
                </div>
                <div class="booking-modal-note">
                <b>Note:</b>
                <span>Please pay the downpayment within <u>3 days</u> to secure your slot. Otherwise, your slot will be released.</span>
                </div>
                <textarea id="bd-note" placeholder="Additional note (optional)"></textarea>
                <button id="confirm-booking-btn">Confirm Booking</button>
                <button class="cancel-btn" onclick="closeBookingModal()">Cancel</button>
            </div>
        </div>
    </main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger-btn');
    const overlay = document.getElementById('sidebar-overlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = "hidden";
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = "";
    }

    // Hamburger toggles open/close
    if (hamburger) {
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }
    // Overlay click closes sidebar
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    // Close sidebar on sidebar-nav link click (mobile only)
    document.querySelectorAll('.sidebar-nav a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 700) closeSidebar();
        });
    });
    // ESC key closes sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === "Escape" && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
    // Auto-close sidebar if window resized to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 700) closeSidebar();
    });

      // 1. Initialize Calendar ONCE
      var calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        window.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'en-ph',
            timeZone: 'Asia/Manila',
            height: 510,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            eventDidMount: function(info) {
                let titleDiv = info.el.querySelector('.fc-event-title');
                if (titleDiv) titleDiv.innerHTML = info.event.title;
            },
            events: 'get_calendar_events.php'
        });
        setupCalendarClick(window.calendar); // Attach the event handler ONCE
        window.calendar.render();
    }
});

// showCalendar will only display the modal (NO init)
function showCalendar() {
    const modal = document.getElementById('calendar-modal');
    modal.style.display = 'flex';
     // Trigger FullCalendar to fix its size/layout
     setTimeout(() => {
        if (window.calendar) {
            window.calendar.updateSize();
            // or window.calendar.render();
        }
    }, 20); // Small delay to allow modal to be visible first
}

// Hide modal when clicking background
document.getElementById('calendar-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});

// ---- BOOKING FLOW (unchanged) ----

// Utility: get user info from PHP session
const USER_INFO = {
  name: '<?php echo addslashes($_SESSION["full_name"] ?? ""); ?>',
  email: '<?php echo addslashes($_SESSION["email_address"] ?? ""); ?>',
  contact: '<?php echo addslashes($_SESSION["contact_number"] ?? ""); ?>',
  user_id: '<?php echo $_SESSION["user_id"] ?? ""; ?>'
};
let SELECTED_PACKAGE = '';
let SELECTED_DATE = '';
let SELECTED_PRICE = '';
let SELECTED_PACKAGE_ID = '';


// Book Now button
document.querySelectorAll('.book-btn').forEach(btn => {
  btn.onclick = function() {
    SELECTED_PACKAGE = this.getAttribute('data-package');
    SELECTED_PRICE = this.getAttribute('data-price');
    SELECTED_PACKAGE_ID = this.getAttribute('data-package-id'); // 🟢 Fix here!
    showCalendar();
  }
});


// Setup calendar click event
// Listen for slot-pill click in FullCalendar (eventClick)
// Helper function to format date
function formatDateHuman(d) {
  const dt = new Date(d);
  return dt.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function setupCalendarClick(calendar) {
  calendar.setOption('eventClick', function(info) {
    const eventDate = new Date(info.event.startStr);
    const now = new Date();
    now.setHours(0,0,0,0);
    if (eventDate < now) return; // do nothing

    // Helper function to format date
        function formatDateHuman(d) {
        const dt = new Date(d);
        return dt.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }

    // Only allow if slot-pill, not fully booked
    if(info.event.classNames.includes('slot-pill')) {
      SELECTED_DATE = info.event.startStr;
      // Animate and show booking modal
      const bookModal = document.getElementById('booking-details-modal');
      bookModal.style.display = 'flex';
      const animBox = bookModal.children[0];
      animBox.classList.add('booking-anim-modal');
      setTimeout(() => animBox.classList.remove('booking-anim-modal'), 450);

      // Fill modal details
      document.getElementById('bd-package').textContent = SELECTED_PACKAGE || '';
      document.getElementById('bd-date').textContent = formatDateHuman(SELECTED_DATE);
      document.getElementById('bd-name').textContent = USER_INFO.name;
      document.getElementById('bd-amount').textContent = '₱ ' + parseFloat(SELECTED_PRICE).toLocaleString('en-PH', { minimumFractionDigits: 2 });
      document.getElementById('bd-email').setAttribute('title', USER_INFO.name);
      document.getElementById('bd-email').textContent = USER_INFO.email;
      document.getElementById('bd-email').setAttribute('title', USER_INFO.email);

      document.getElementById('bd-contact').textContent = USER_INFO.contact;
        document.getElementById('bd-contact').setAttribute('title', USER_INFO.contact);      
      document.getElementById('bd-note').value = '';
      // Hide calendar modal (if still open)
      document.getElementById('calendar-modal').style.display = 'none';
      info.jsEvent.preventDefault();
    }
  });
}

// Close Booking Details modal
function closeBookingModal() {
  document.getElementById('booking-details-modal').style.display = 'none';
}

document.getElementById('confirm-booking-btn').onclick = function() {
  const note = document.getElementById('bd-note').value;
  this.disabled = true;
  this.textContent = 'Processing...';
  fetch('book_slot.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
  user_id: USER_INFO.user_id,
  package_id: SELECTED_PACKAGE_ID,
  booking_date: SELECTED_DATE,
  note: note
})

  })
  .then(r => r.json()).then(res => {
    this.disabled = false;
    this.textContent = 'Confirm Booking';
    if(res.success) {
      // Use SweetAlert instead of alert
      Swal.fire({
        icon: 'success',
        title: 'Booking successful!',
        text: 'Please proceed to payment within 3 days.',
        confirmButtonColor: '#1593a6',
        allowOutsideClick: false,
        timer: 1900,
        timerProgressBar: true,
        showConfirmButton: false
      }).then(() => {
        window.location = 'bookings.php'; // Redirect after closing popup
      });
      setTimeout(() => { window.location = 'bookings.php'; }, 2100); // Safety redirect
      closeBookingModal();
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Booking failed',
        text: res.message || 'Try another date.',
        confirmButtonColor: '#1593a6'
      });
    }
  }).catch(() => {
    this.disabled = false;
    this.textContent = 'Confirm Booking';
    Swal.fire({
      icon: 'error',
      title: 'Network error',
      text: 'Please try again.',
      confirmButtonColor: '#1593a6'
    });
  });
};

</script>
<script>

(function () {
  const root = document.getElementById('heroCarousel');
  if (!root) return;

  const track    = root.querySelector('.hc-track');
  const slides   = Array.from(root.querySelectorAll('.hc-slide'));
  const prevBtn  = root.querySelector('.hc-prev');
  const nextBtn  = root.querySelector('.hc-next');
  const dotsWrap = root.querySelector('.hc-dots');
  const viewport = root.querySelector('.hc-viewport');

  let index = 0;
  let timer = null;
  let touchStartX = 0;
  let touchDx = 0;

  const SLIDE_MS = 5200;         // autoplay interval
  const SWIPE_THRESH = 40;       // px

  function viewportWidth() {
    // exact content width; unaffected by external padding
    return viewport.getBoundingClientRect().width;
  }

  function go(i, animate = true) {
    index = (i + slides.length) % slides.length;
    if (!animate) track.style.transition = 'none';
    track.style.transform = `translate3d(${-index * viewportWidth()}px,0,0)`;
    // restore transition after the frame to avoid disabling future anims
    requestAnimationFrame(() => { track.style.transition = ''; });
    updateDots();
  }

  function next() { go(index + 1); }
  function prev() { go(index - 1); }

  function makeDots() {
    slides.forEach((_, i) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('role', 'tab');
      b.setAttribute('aria-label', `Slide ${i + 1}`);
      b.addEventListener('click', () => go(i));
      dotsWrap.appendChild(b);
    });
    updateDots();
  }

  function updateDots() {
    dotsWrap.querySelectorAll('button').forEach((b, i) => {
      b.setAttribute('aria-selected', String(i === index));
    });
  }

  function start() {
    stop();
    timer = setInterval(next, SLIDE_MS);
  }
  function stop() {
    if (timer) { clearInterval(timer); timer = null; }
  }

  // Arrow controls
  prevBtn.addEventListener('click', () => { stop(); prev(); start(); });
  nextBtn.addEventListener('click', () => { stop(); next(); start(); });

  // Hover pause (desktop)
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);

  // Touch swipe (mobile)
  root.addEventListener('touchstart', (e) => {
    stop();
    touchStartX = e.touches[0].clientX;
    touchDx = 0;
  }, { passive: true });

  root.addEventListener('touchmove', (e) => {
    touchDx = e.touches[0].clientX - touchStartX;
  }, { passive: true });

  root.addEventListener('touchend', () => {
    if (Math.abs(touchDx) > SWIPE_THRESH) (touchDx < 0 ? next() : prev());
    start();
  });

  // Resize handling (more accurate than window resize alone)
  const ro = new ResizeObserver(() => go(index, false));
  ro.observe(viewport);

  // Fallback for environments without ResizeObserver
  window.addEventListener('resize', () => go(index, false));

  // Init
  makeDots();
  go(0, false);
  start();
})();
</script>

</body>
</html>
