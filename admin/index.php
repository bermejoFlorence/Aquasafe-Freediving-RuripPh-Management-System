<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  echo "<!DOCTYPE html>
  <html><head>
  <meta charset='UTF-8'><title>Access Denied</title>
  <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
  </head><body>
  <script>
  Swal.fire({
    icon:'error',
    title:'Access Denied',
    text:'You do not have permission to access this page.',
    confirmButtonColor:'#1e8fa2'
  }).then(()=>{ window.location='../login.php'; });
  </script></body></html>";
  exit;
}

include '../db_connect.php';
@$conn->query("SET time_zone = '+08:00'");

/* --- KPIs --- */
$kpi = [
  'dives_today'       => 0,
  'kits_to_issue'     => 0,
  'in_cleaning'       => 0,
  'pending_approvals' => 0,
  'total_clients'     => 0,
];

if ($stmt = $conn->prepare("SELECT COUNT(*) FROM `user` WHERE role='client'")) {
  $stmt->execute(); $stmt->bind_result($kpi['total_clients']); $stmt->fetch(); $stmt->close();
}
if ($stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE status='approved' AND DATE(booking_date)=CURDATE()")) {
  $stmt->execute(); $stmt->bind_result($kpi['dives_today']); $stmt->fetch(); $stmt->close();
}
if ($stmt = $conn->prepare("
  SELECT COUNT(*)
  FROM booking b
  WHERE b.status='approved'
    AND DATE(b.booking_date)=CURDATE()
    AND NOT EXISTS (
      SELECT 1 FROM rental_kit rk
      WHERE rk.booking_id=b.booking_id
        AND rk.status IN ('issued','partial','overdue')
    )
")) {
  $stmt->execute(); $stmt->bind_result($kpi['kits_to_issue']); $stmt->fetch(); $stmt->close();
}
if ($stmt = $conn->prepare("SELECT COALESCE(SUM(cleaning_qty),0) FROM inventory_item")) {
  $stmt->execute(); $stmt->bind_result($kpi['in_cleaning']); $stmt->fetch(); $stmt->close();
}
if ($stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE status='pending'")) {
  $stmt->execute(); $stmt->bind_result($kpi['pending_approvals']); $stmt->fetch(); $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Aquasafe RuripPh Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Icons & Alerts -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- FullCalendar v6 -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <!-- <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js"></script> -->

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>

  <!-- Optional external stylesheet -->
  <link rel="stylesheet" href="styles/style.css">

<style>
:root{
  --teal:#1e8fa2; --teal-600:#187e90; --teal-700:#136f7f;
  --blue:#35b5ff; --ink:#2e6b78; --bg:#e6f7fb; --surface:#fff;
  --border:#ecf6fb; --muted:#7aa0af;
  --shadow:0 6px 24px rgba(24,182,213,.12);
  --radius:14px;
}

/* ===== Base layout ===== */
.content-area{ padding:0; background:var(--bg); }
.dash-wrap{
  display:grid;
  grid-template-columns: minmax(0,1fr) clamp(280px, 28vw, 360px);
  gap:12px; padding:12px;
  align-items:start;
}
.dash-left{ display:flex; flex-direction:column; gap:12px; }
.card{ background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow); }

/* ===== Sales chart (left) ===== */
.chart-card.single{
  background:var(--surface);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  padding:8px 10px 10px;
  height: clamp(480px, 78vh, 920px);
  display:flex; flex-direction:column;
}
.chart-card.single canvas{
  flex:1 1 auto;
  width:100% !important; height:100% !important;
}
.chart-head{
  display:flex; align-items:center; justify-content:space-between;
  gap:12px; margin-bottom:6px; flex-wrap:wrap;
}
.chart-title{
  margin:0; font-size:1rem; color:var(--teal);
  letter-spacing:.3px; display:flex; align-items:center; gap:8px;
}

/* Filter buttons (Daily/Weekly/Monthly/Yearly) */
.chart-filters{ display:flex; gap:6px; flex-wrap:wrap; }
.btn-seg{
  background:#eef7f9; color:var(--teal); border:1px solid #cfe8ee;
  border-radius:10px; padding:6px 10px; font-weight:600;
  display:inline-flex; align-items:center; gap:6px; cursor:pointer;
  transition:background .15s, transform .04s, box-shadow .15s, color .15s, border-color .15s;
}
.btn-seg:hover{ background:#e6f2f5; }
.btn-seg:active{ transform:translateY(1px); }
.btn-seg.active{ background:var(--teal); color:#fff; border-color:var(--teal); }

/* ===== Right panel (Overview) ===== */
.dash-right{
  background:var(--surface);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  padding:12px; display:flex; flex-direction:column; gap:10px;
  position:sticky; top:72px; max-height:calc(100vh - 96px); overflow:auto;
}
.panel-head{
  display:flex; align-items:center; justify-content:space-between;
  gap:8px; margin:4px 4px 8px; flex-wrap:wrap;
}
.panel-title{
  margin:0; font-size:1rem; color:#4d768b;
  letter-spacing:.3px; display:flex; align-items:center; gap:8px;
}
.stat-row{
  border:1px solid var(--border); border-radius:12px; padding:10px;
  background:linear-gradient(180deg, #f9feff 0%, #ffffff 100%);
  box-shadow:0 2px 8px rgba(24,182,213,.06);
}
.sr-label{ display:flex; align-items:center; gap:8px; font-weight:600; color:#4d768b; letter-spacing:.2px; margin-bottom:4px; }
.sr-icon{
  background:#b6eafe; color:#188bd9; width:28px; height:28px; border-radius:50%;
  display:flex; align-items:center; justify-content:center; font-size:.95rem;
}
.sr-value{ font-size:1.9rem; font-weight:800; color:#188ba2; line-height:1; }
.sr-sub{ font-size:.9rem; color:#67b37d; margin-top:2px; }

/* Soft buttons (e.g., View Calendar) */
.btn-soft{
  background:#eef7f9; color:var(--teal); border:1px solid #cfe8ee;
  border-radius:12px; padding:8px 12px; font-weight:600;
  display:inline-flex; align-items:center; gap:6px; cursor:pointer;
  transition:background .15s, transform .04s, box-shadow .15s;
}
.btn-soft:hover{ background:#e6f2f5; }
.btn-soft:active{ transform:translateY(1px); }
.btn-soft:focus{ outline:none; box-shadow:0 0 0 3px rgba(30,143,162,.25); }

/* ===== Calendar Modal ===== */
.modal-backdrop{
  position:fixed; inset:0; background:rgba(16,36,42,.35);
  display:grid; place-items:center; z-index:1000;
  opacity:0; pointer-events:none; transition:opacity .18s ease;
}
.modal-backdrop.show{ opacity:1; pointer-events:auto; }
.modal-backdrop[hidden]{ display:none !important; opacity:0 !important; pointer-events:none !important; }

.modal-panel{
  width:92vw; max-width:1200px; height:85vh;
  background:#fff; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,.18);
  display:flex; flex-direction:column; overflow:hidden;
}
.modal-header{
  padding:10px 14px; border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
}
.modal-header h3{ margin:0; color:var(--teal); letter-spacing:.3px; }
.modal-close{
  background:var(--teal); color:#fff; border:none; width:36px; height:36px;
  border-radius:10px; font-size:22px; line-height:1; cursor:pointer;
}
.modal-body{ padding:10px; height:100%; }
.cal-wrap{ position:relative; height:100%; }
.cal-wrap #calendar{ height:100%; }
.fc{ height:100%; }

/* Custom calendar arrows */
.cal-nav-btn{
  position:absolute; top:10px; z-index:5;
  background:var(--teal); color:#fff; border:none;
  width:36px; height:36px; border-radius:10px; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 2px 8px rgba(30,143,162,.25);
}
.cal-nav-btn:hover{ background:var(--teal-600); }
.cal-nav-btn:active{ transform:translateY(1px); }
.cal-prev{ left:10px; } .cal-next{ right:10px; }

/* ===== FullCalendar theme bits ===== */
.fc .fc-toolbar-title{ color:var(--teal); font-weight:800; letter-spacing:.3px; }
.fc .fc-col-header-cell-cushion{ color:var(--teal); font-weight:700; }
.fc .fc-daygrid-day-number{ color:var(--ink); }
.fc .fc-day-today{ background:#f2fbfd; }
.fc .fc-event{ border:none; }
.fc-event.slot-pill{
  border-radius:1.6em !important; background:#86e6a6 !important; color:#196833 !important;
  padding:2px 8px !important; font-size:.89em !important; margin-bottom:2px;
  box-shadow:0 2px 8px #a6e8ba49; font-weight:600; text-align:center;
}
.fc-event.full-pill{
  border-radius:1.6em !important; background:#ffb3b3 !important; color:#a70a2e !important;
  padding:2px 8px !important; font-size:.89em !important; margin-bottom:2px;
  box-shadow:0 2px 8px #ffa3a349; font-weight:600; text-align:center;
}
.fc-event.name-pill{
  border-radius:1.6em !important; background:#35b5ff !important; color:#fff !important;
  padding:2px 8px !important; font-size:.85em !important; margin-bottom:2px;
  box-shadow:0 2px 8px #b1eaff3c; text-align:center;
}

/* ===== Responsive tweaks ===== */
@media (max-width:1200px){
  .dash-wrap{ gap:10px; }
}
@media (max-width:1000px){
  .dash-wrap{ grid-template-columns:1fr; }
  .dash-right{ position:static; top:auto; max-height:none; }
  .chart-card.single{ height:420px; }
}

/* Cursor rules inside calendar modal */
#calModal .fc-daygrid-day { cursor: not-allowed; }               /* default */
#calModal .fc-event { cursor: default; }
#calModal .fc-daygrid-day:has(.fc-event.approved-pill) { cursor: pointer; } /* day has approved */
#calModal .fc-event.approved-pill { cursor: pointer !important; }          /* approved pill */
</style>

</head>
<body>
  <?php $page = 'dashboard'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main class="content-area">
    <?php include 'includes/header.php'; ?>

    <div class="dash-wrap">
      <!-- LEFT: Sales Graph -->
      <section class="dash-left">
        <div class="chart-card single">
          <div class="chart-head">
            <h3 class="chart-title">Sales — Last 30 Days</h3>
            <div class="chart-filters" role="tablist" aria-label="Sales granularity">
              <button class="btn-seg active" data-g="daily">Daily</button>
              <button class="btn-seg" data-g="weekly">Weekly</button>
              <button class="btn-seg" data-g="monthly">Monthly</button>
              <button class="btn-seg" data-g="yearly">Yearly</button>
            </div>
          </div>
          <canvas id="chartSales"></canvas>
        </div>
      </section>

      <!-- RIGHT: Overview -->
      <aside class="dash-right">
        <div class="panel-head">
          <h3 class="panel-title"><i class="fa-regular fa-chart-bar"></i> Overview</h3>
          <button id="openCalendarBtn" class="btn-soft">
            <i class="fa-regular fa-calendar"></i> View Calendar
          </button>
        </div>

        <div class="stat-row">
          <div class="sr-label"><span class="sr-icon"><i class="fa-solid fa-person-swimming"></i></span><span>DIVES TODAY</span></div>
          <div class="sr-value"><?= (int)$kpi['dives_today']; ?></div>
          <div class="sr-sub"><?= $kpi['dives_today'] ? 'On schedule' : 'No dives today'; ?></div>
        </div>

        <div class="stat-row">
          <div class="sr-label"><span class="sr-icon"><i class="fa-solid fa-toolbox"></i></span><span>KITS TO ISSUE</span></div>
          <div class="sr-value"><?= (int)$kpi['kits_to_issue']; ?></div>
          <div class="sr-sub"><?= $kpi['kits_to_issue'] ? 'Prep now' : 'All set'; ?></div>
        </div>

        <div class="stat-row">
          <div class="sr-label"><span class="sr-icon"><i class="fa-solid fa-soap"></i></span><span>IN CLEANING</span></div>
          <div class="sr-value"><?= (int)$kpi['in_cleaning']; ?></div>
          <div class="sr-sub"><?= $kpi['in_cleaning'] ? 'Queue pending' : 'No backlog'; ?></div>
        </div>

        <div class="stat-row">
          <div class="sr-label"><span class="sr-icon"><i class="fa-solid fa-clock"></i></span><span>PENDING APPROVALS</span></div>
          <div class="sr-value"><?= (int)$kpi['pending_approvals']; ?></div>
          <div class="sr-sub"><?= $kpi['pending_approvals'] ? 'Review needed' : 'All cleared'; ?></div>
        </div>

        <div class="stat-row">
          <div class="sr-label"><span class="sr-icon"><i class="fa-solid fa-users"></i></span><span>TOTAL CLIENTS</span></div>
          <div class="sr-value"><?= (int)$kpi['total_clients']; ?></div>
          <div class="sr-sub">Clients Only</div>
        </div>
      </aside>
    </div>
  </main>

  <!-- ===== Calendar Modal ===== -->
  <div id="calModal" class="modal-backdrop" hidden>
    <div class="modal-panel">
      <div class="modal-header">
        <h3><i class="fa-regular fa-calendar"></i> Booking Calendar</h3>
        <button class="modal-close" id="closeCalBtn" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="cal-wrap">
          <button class="cal-nav-btn cal-prev" id="calPrev" aria-label="Previous"><i class="fa-solid fa-angle-left"></i></button>
          <button class="cal-nav-btn cal-next" id="calNext" aria-label="Next"><i class="fa-solid fa-angle-right"></i></button>
          <div id="calendar"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== Manage Booking Modal (Bulk Reschedule) ===== -->
  <div id="manageModal" class="modal-backdrop" hidden>
    <div class="modal-panel" style="max-width:720px;height:auto;">
      <div class="modal-header">
        <h3><i class="fa-regular fa-calendar-check"></i> Reschedule Booking</h3>
        <button class="modal-close" id="mmCloseBtn" aria-label="Close">&times;</button>
      </div>

      <div class="modal-body" style="padding:14px">
        <!-- Summary (clicked item only; display) -->
        <div class="stat-row" style="margin-bottom:10px">
          <div class="sr-label">
            <span class="sr-icon"><i class="fa-solid fa-user"></i></span>
            <span id="mmClientName">Client Name</span>
          </div>
          <div class="sr-sub" id="mmClientEmail">email@example.com</div>
          <div class="sr-sub" id="mmBookingInfo" style="margin-top:6px">
            Booking #<span id="mmBookingId">—</span> · Current: <span id="mmCurrentStart">—</span>
          </div>
        </div>

        <!-- Bulk summary -->
        <div class="stat-row" id="mmBulkInfo" style="margin-top:8px; display:none">
          <div class="sr-label">
            <span class="sr-icon"><i class="fa-regular fa-bell"></i></span>
            <span>
              This will reschedule <strong id="mmBulkCount">0</strong> booking(s)
              on <strong id="mmGroupDateLabel">—</strong>
            </span>
          </div>
        </div>

        <!-- Names (all approved on this date) -->
        <div class="stat-row" id="mmApprovedToday" style="margin-top:8px; display:none">
          <div class="sr-label">
            <span class="sr-icon"><i class="fa-solid fa-users"></i></span>
            <span>Approved on this date</span>
          </div>
          <div id="mmApprovedList" style="max-height:160px; overflow:auto; line-height:1.45"></div>
        </div>

        <!-- New date only -->
        <div style="margin-top:8px">
          <label style="display:block;font-weight:600;color:#4d768b;margin-bottom:4px">New Date</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input id="mmNewDate" type="date" class="btn-soft" style="padding:10px 12px">
          </div>
        </div>

        <!-- Reason -->
        <div style="margin-top:10px">
          <label for="mmReason" style="display:block;font-weight:600;color:#4d768b;margin-bottom:4px">
            Reason <span style="color:#a70a2e">*</span>
          </label>
          <textarea id="mmReason" rows="4" class="btn-soft" style="width:100%;padding:10px 12px" placeholder="e.g., bad weather, staff unavailable, safety advisory"></textarea>
        </div>

        <!-- Actions -->
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
          <button class="btn-soft" id="mmCancelBtn"><i class="fa-regular fa-rectangle-xmark"></i> Close</button>
          <button class="btn-seg" id="mmSaveBtn" disabled>
            <i class="fa-regular fa-paper-plane"></i> Save Reschedule
          </button>
        </div>

        <!-- hidden state -->
        <input type="hidden" id="mmBookingIdHidden" value="">
        <input type="hidden" id="mmGroupDateHidden" value="">
        <input type="hidden" id="mmApprovedIdsHidden" value="">
      </div>
    </div>
  </div>

<script>
(function(){
  'use strict';

  /* =======================
     Helpers
  ======================= */
  async function fetchJSON(url, fallback){
    try{
      const r = await fetch(url, { cache:'no-store' });
      if(!r.ok) return fallback;
      const ct = r.headers.get('content-type')||'';
      if(!ct.includes('application/json')) return fallback;
      return await r.json();
    }catch(e){
      console.warn('fetchJSON failed:', url, e);
      return fallback;
    }
  }

  function fmtPH(dt){
    if (!dt) return '—';
    const d = (dt instanceof Date) ? dt : new Date(dt);
    return d.toLocaleString('en-PH', {
      timeZone:'Asia/Manila', year:'numeric', month:'short', day:'2-digit',
      hour:'2-digit', minute:'2-digit', hour12:true
    });
  }

  function dayKeyPH(date){
    const d = (date instanceof Date) ? date : new Date(date);
    const ph = new Date(d.toLocaleString('en-US', { timeZone:'Asia/Manila' }));
    const y = ph.getFullYear(), m = String(ph.getMonth()+1).padStart(2,'0'), dd = String(ph.getDate()).padStart(2,'0');
    return `${y}-${m}-${dd}`; // yyyy-mm-dd
  }

  function startOfDayPH(date){
    const d = (date instanceof Date) ? date : new Date(date);
    const ph = new Date(d.toLocaleString('en-US', { timeZone:'Asia/Manila' }));
    ph.setHours(0,0,0,0);
    return ph;
  }
  function isDateTodayOrFuturePH(dateStr){
    if (!dateStr) return false;
    const candidate = startOfDayPH(`${dateStr}T00:00:00`);
    const todayPH   = startOfDayPH(new Date());
    return candidate.getTime() >= todayPH.getTime(); // allow today and future
  }

  
  /* =======================
     Sales Chart
  ======================= */
  let salesChart = null;

  function pickChartType(g){
     return 'line'; // lahat line graph na
  }
  function titleFor(g){
    if (g === 'daily') return 'Sales — Last 30 Days';
    if (g === 'weekly') return 'Sales — Last 12 Weeks';
    if (g === 'monthly') return 'Sales — Last 12 Months';
    return 'Sales — Last 5 Years';
  }

  async function renderSales(g='daily'){
    const ctx = document.getElementById('chartSales');
    if (!ctx) return;

    const { labels, sales } = await fetchJSON(`get_sales.php?g=${g}`, { labels: [], sales: [] });
    const allZero = sales.every(v => Number(v||0) === 0);

    if (salesChart) { salesChart.destroy(); salesChart = null; }

    const t = document.querySelector('.chart-title');
    if (t) t.textContent = titleFor(g);

    // eslint-disable-next-line no-undef
    salesChart = new Chart(ctx, {
      type: pickChartType(g),
      data: {
        labels,
        datasets: [{
          label: 'Sales (₱)',
          data: sales,
          tension: .3,
          borderWidth: 2,
          borderColor: '#35b5ff',
          backgroundColor: 'rgba(53,181,255,.25)',
          pointRadius: 0,
          fill: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { left: 6, right: 6, top: 2, bottom: 2 } },
        plugins: { legend: { display: false } },
        scales: {
          x: {
            ticks: {
              autoSkip: true,
              maxTicksLimit: (g==='yearly') ? 6 : (g==='monthly' ? 12 : 10),
              maxRotation: 0
            }
          },
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0,
              callback: v => '₱' + v
            },
            suggestedMax: allZero ? 5 : undefined
          }
        }
      },
      plugins: [{
        id: 'noDataText',
        afterDraw(chart){
          if (!allZero) return;
          const {ctx, chartArea:{left,right,top,bottom}} = chart;
          ctx.save();
          ctx.font = '14px Arial'; ctx.fillStyle = '#7aa0af'; ctx.textAlign='center';
          ctx.fillText('No sales data for the selected range', (left+right)/2, (top+bottom)/2);
          ctx.restore();
        }
      }]
    });
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    renderSales('daily');
    document.querySelectorAll('.btn-seg').forEach(btn=>{
      if (!btn.dataset?.g) return;
      btn.addEventListener('click', ()=>{
        document.querySelectorAll('.btn-seg').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        renderSales(btn.dataset.g);
      });
    });
  });

  /* =======================
     Calendar + Manage (Bulk Reschedule)
  ======================= */
  let calendarInstance = null;
  // helper: kuhanin lahat ng approved sa isang araw
function getApprovedForDate(groupDate){
  if (!calendarInstance) return [];
  return calendarInstance.getEvents().filter(ev=>{
    if (!ev.start) return false;
    if (dayKeyPH(ev.start) !== groupDate) return false;
    const ex = ev.extendedProps || {};
    const t  = (ev.title || '').trim();
    const stat = (ex.status || '').toLowerCase();
    const classes = (ev._def?.ui?.classNames || []);
    const looksLikeSlots = /^slots?:\s*\d+/i.test(t) || classes.includes('slot-pill');
    const looksLikeFull  = /full/i.test(t)           || classes.includes('full-pill');
    const namePill       = classes.includes('name-pill');
    return stat === 'approved' || namePill || (!looksLikeSlots && !looksLikeFull);
  }).map(ev=>{
    const ex = ev.extendedProps || {};
    return {
      id:   ex.booking_id || ev.id || '',
      name: ex.client_name || ex.client || (ev.title||'').trim()
    };
  }).filter(x => String(x.id||'').trim() !== '' || String(x.name||'').trim() !== '');
}
function openCalendar(){
  const modal = document.getElementById('calModal');
  modal.hidden = false; requestAnimationFrame(()=> modal.classList.add('show'));

  const calEl = document.getElementById('calendar');
  calendarInstance = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    locale: 'en-ph',
    timeZone: 'Asia/Manila',
    height: '100%',
    headerToolbar: { left: '', center: 'title', right: '' },
    navLinks: false,
    selectable: false,

    eventDidMount(info){
      const titleEl = info.el.querySelector('.fc-event-title, .fc-event-title-container');
      if (titleEl) titleEl.innerHTML = info.event.title;

      const ex = info.event.extendedProps || {};
      const status = (ex.status || '').toLowerCase();
      const t  = (info.event.title || '').trim();
      const looksLikeSlots = /^slots?:\s*\d+/i.test(t);
      const looksLikeFull  = /full/i.test(t);
      const isApprovedUI = status === 'approved' || (!looksLikeSlots && !looksLikeFull);
      if (isApprovedUI) info.el.classList.add('approved-pill');
    },

    // ✅ Lahat ng click sa event → check muna kung may approved sa araw na iyon
    eventClick(info){
      const groupDate = dayKeyPH(info.event.start || info.event.startStr);
      const approvedList = getApprovedForDate(groupDate);

      if (approvedList.length === 0){
        Swal.fire({ icon:'warning', title:'No approved bookings', text:'Walang approved client sa date na ito.', confirmButtonColor:'#1e8fa2' });
        info.jsEvent?.preventDefault?.();
        info.jsEvent?.stopPropagation?.();
        return;
      }

      const first = approvedList[0] || {};
      const payload = {
        booking_id:  first.id || '',
        client_name: first.name || '',
        client_email: '',
        start: groupDate,
        groupDate,
        approvedList
      };

      info.jsEvent?.preventDefault?.();
      info.jsEvent?.stopPropagation?.();
      closeCalendar(()=> openManageModal(payload));
    },

    // (Optional) ✅ Pati click sa bakanteng part ng cell
    dayCellDidMount(arg){
      arg.el.addEventListener('click', ()=>{
        const groupDate = dayKeyPH(arg.date);
        const approvedList = getApprovedForDate(groupDate);

        if (approvedList.length === 0){
          Swal.fire({ icon:'warning', title:'No approved bookings', text:'Walang approved client sa date na ito.', confirmButtonColor:'#1e8fa2' });
          return;
        }

        const first = approvedList[0] || {};
        const payload = {
          booking_id:  first.id || '',
          client_name: first.name || '',
          client_email: '',
          start: groupDate,
          groupDate,
          approvedList
        };
        closeCalendar(()=> openManageModal(payload));
      });
    },

    events: 'get_calendar_events.php'
  });

  calendarInstance.render();
  document.getElementById('calPrev')?.addEventListener('click', ()=> calendarInstance.prev());
  document.getElementById('calNext')?.addEventListener('click', ()=> calendarInstance.next());
}

  function closeCalendar(afterClose){
    if (calendarInstance){ calendarInstance.destroy(); calendarInstance = null; }
    const modal = document.getElementById('calModal');
    if (!modal || modal.hidden) { if (typeof afterClose==='function') afterClose(); return; }
    modal.classList.remove('show');
    const done = () => {
      modal.hidden = true;
      modal.removeEventListener('transitionend', done);
      if (typeof afterClose === 'function') afterClose();
    };
    modal.addEventListener('transitionend', done, { once:true });
  }

  function openManageModal(payload){
    const mm = document.getElementById('manageModal');
    const f = {
      idHidden:  document.getElementById('mmBookingIdHidden'),
      name:      document.getElementById('mmClientName'),
      email:     document.getElementById('mmClientEmail'),
      bkId:      document.getElementById('mmBookingId'),
      curStart:  document.getElementById('mmCurrentStart'),
      reason:    document.getElementById('mmReason'),
      newDate:   document.getElementById('mmNewDate'),
      save:      document.getElementById('mmSaveBtn'),

      bulkInfo:  document.getElementById('mmBulkInfo'),
      bulkCount: document.getElementById('mmBulkCount'),
      groupLbl:  document.getElementById('mmGroupDateLabel'),
      idsHidden: document.getElementById('mmApprovedIdsHidden'),

      listBox:   document.getElementById('mmApprovedToday'),
      listEl:    document.getElementById('mmApprovedList')
    };

    // Prefill
    f.idHidden.value   = payload.booking_id || '';
    f.name.textContent = payload.client_name || '—';
    f.email.textContent= payload.client_email || '—';
    f.bkId.textContent = payload.booking_id || '—';
    f.curStart.textContent = fmtPH(payload.start || payload.startStr);

    // Reset + min date = today (PH)
    f.reason.value = '';
    f.newDate.value = '';
    f.save.disabled = true;
    const todayPH = new Date(new Date().toLocaleString('en-US', { timeZone:'Asia/Manila' }));
    f.newDate.min = `${todayPH.getFullYear()}-${String(todayPH.getMonth()+1).padStart(2,'0')}-${String(todayPH.getDate()).padStart(2,'0')}`;

    // --- BULK summary + IDs ---
    const ids = (payload.approvedList || [])
      .map(x => String(x.id||'').trim())
      .filter(Boolean);
    f.idsHidden.value   = ids.join(',');
    f.bulkCount.textContent = ids.length;
    f.groupLbl.textContent  = payload.groupDate || '—';

    // VERY IMPORTANT: set dataset para mabasa ng save handler
    mm.dataset.groupDate = payload.groupDate || dayKeyPH(payload.start || payload.startStr);

    if ((payload.approvedList||[]).length){
      f.listEl.innerHTML = payload.approvedList.map(it =>
        `<div style="padding:4px 0">${it.name}${it.id?` <span style="color:#7aa0af">#${it.id}</span>`:''}</div>`
      ).join('');
      f.listBox.style.display = '';
    } else {
      f.listEl.innerHTML = '';
      f.listBox.style.display = 'none';
    }

    // Show modal
    mm.hidden = false; requestAnimationFrame(()=> mm.classList.add('show'));
  }

  function closeManageModal(reopenCalendar = true){
    const mm = document.getElementById('manageModal');
    mm.classList.remove('show');
    mm.addEventListener('transitionend', ()=>{
      mm.hidden = true;
      if (reopenCalendar) {
        openCalendar();
        setTimeout(()=>{ try { calendarInstance?.refetchEvents?.(); } catch(e){} }, 250);
      }
    }, { once:true });
  }

  // Manage UI setup
  (function setupManageUI(){
    const reason = document.getElementById('mmReason');
    const newDate= document.getElementById('mmNewDate');
    const save   = document.getElementById('mmSaveBtn');

    function validate(){
      const okFields = reason.value.trim().length >= 3 && !!newDate.value;
      const okFuture = isDateTodayOrFuturePH(newDate.value);
      save.disabled = !(okFields && okFuture);
    }

    [reason, newDate].forEach(el=>{
      el?.addEventListener('input', validate);
      el?.addEventListener('change', validate);
    });

    document.getElementById('mmCloseBtn')?.addEventListener('click', ()=> closeManageModal(true));
    document.getElementById('mmCancelBtn')?.addEventListener('click', ()=> closeManageModal(true));

    // Save (Bulk)
    document.getElementById('mmSaveBtn')?.addEventListener('click', async ()=>{
      const mm        = document.getElementById('manageModal');
      const groupDate = mm.dataset.groupDate;                    // YYYY-MM-DD (original date)
      const newDate   = document.getElementById('mmNewDate').value;
      const reason    = document.getElementById('mmReason').value.trim();

      if (!groupDate) {
        Swal.fire({ icon:'error', title:'No date selected', text:'Walang group_date mula sa napiling araw.', confirmButtonColor:'#1e8fa2' });
        return;
      }
      if (!newDate || reason.length < 3) return;

      // IDs na na-compute nung open (may fallback kung wala)
      const idsVal = (document.getElementById('mmApprovedIdsHidden')?.value || '').trim();
      const booking_ids = idsVal
        ? idsVal.split(',').map(s=>parseInt(s,10)).filter(n=>!isNaN(n))
        : [];

      try{
        const r = await fetch('reschedule_booking.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            bulk: true,
            group_date: groupDate,
            target_date: newDate,
            reason,
            // Optional lang; backend will fallback to DB lookup kapag empty
            booking_ids
          })
        });
        const txt = await r.text();
        let data = {};
        try { data = JSON.parse(txt); } catch {}
        if (!r.ok || !data.ok) {
          const msg = (data && data.error) ? data.error : (txt || 'Reschedule failed');
          Swal.fire({ icon:'error', title:'Reschedule failed', text: msg, confirmButtonColor:'#1e8fa2' });
          return;
        }

        Swal.fire({
          icon:'success',
          title:'Rescheduled',
          text:`Moved ${data.count} booking(s) from ${data.group_date} to ${data.target_date}.`,
          confirmButtonColor:'#1e8fa2'
        }).then(()=> closeManageModal(true));
      }catch(e){
        Swal.fire({ icon:'error', title:'Network error', text:String(e), confirmButtonColor:'#1e8fa2' });
      }
    });

  })();

  // Global open/close wires + ESC behavior
  document.addEventListener('DOMContentLoaded', ()=>{
    document.getElementById('openCalendarBtn')?.addEventListener('click', openCalendar);
    document.getElementById('closeCalBtn')?.addEventListener('click', closeCalendar);

    const backdrop = document.getElementById('calModal');
    backdrop?.addEventListener('click', (e)=>{
      if (e.target === backdrop) closeCalendar();
    });

    document.addEventListener('keydown', (e)=>{
      if (e.key !== 'Escape') return;
      const mm = document.getElementById('manageModal');
      const cm = document.getElementById('calModal');
      if (mm && !mm.hidden) return closeManageModal(true);
      if (cm && !cm.hidden) return closeCalendar();
    });
  });

  
})();
</script>


</body>
</html>
