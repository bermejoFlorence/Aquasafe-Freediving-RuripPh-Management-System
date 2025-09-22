<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<!DOCTYPE html>
    <html><head>
    <meta charset='UTF-8'>
    <title>Access Denied</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head><body>
    <script>
    Swal.fire({
        icon: 'error',
        title: 'Access Denied',
        text: 'You do not have permission to access this page.',
        confirmButtonColor: '#1e8fa2'
    }).then(() => { window.location = '../login.php'; });
    </script>
    </body></html>";
    exit;
}

include '../db_connect.php';

// Helpers
// Helpers
// Helpers
function peso($n){ return '₱'.number_format((float)$n, 2); }

// Collect both pending and paid
$pendingRows = [];
$paidRows    = [];

$sql = "
SELECT
  b.booking_id,
  b.booking_date,
  u.full_name,
  pk.name AS package_name,
  COALESCE(pk.price, 0)                  AS base_price,
  COALESCE(a.addons_total, 0)            AS addons_total,
  COALESCE(p.paid_sum, 0)                AS payments_sum,
  (COALESCE(pk.price,0) + COALESCE(a.addons_total,0) - COALESCE(p.paid_sum,0)) AS balance
FROM booking b
JOIN user u       ON u.user_id = b.user_id
LEFT JOIN package pk ON pk.package_id = b.package_id
LEFT JOIN (
  SELECT booking_id, SUM(addons_total) AS addons_total
  FROM rental_kit
  GROUP BY booking_id
) a ON a.booking_id = b.booking_id
LEFT JOIN (
  SELECT booking_id, SUM(amount) AS paid_sum
  FROM payment
  WHERE status = 'paid'
  GROUP BY booking_id
) p ON p.booking_id = b.booking_id
WHERE b.status IN ('approved','completed','finished','done')
";

if ($res = $conn->query($sql)) {
  while ($r = $res->fetch_assoc()) {
    $r['balance'] = round((float)$r['balance'], 2);
    if ($r['balance'] > 0.009) { $pendingRows[] = $r; }
    else { $paidRows[] = $r; }
  }
  $res->free();
}

// tag + merge + sort (pending group first; both groups latest first)
foreach ($pendingRows as &$r) { $r['is_pending'] = 1; } unset($r);
foreach ($paidRows as &$r)    { $r['is_pending'] = 0; } unset($r);
$rows = array_merge($pendingRows, $paidRows);

usort($rows, function($a,$b){
  if ($a['is_pending'] !== $b['is_pending']) return $a['is_pending'] ? -1 : 1;
  $ad = strtotime($a['booking_date'] ?? '1970-01-01');
  $bd = strtotime($b['booking_date'] ?? '1970-01-01');
  if ($ad === $bd) return $b['booking_id'] <=> $a['booking_id'];
  return $bd <=> $ad;
});


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Aquasafe RuripPh Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="styles/style.css">

  <style>
  :root{
    --teal:#1e8fa2;
    --ink:#186070;
    --ink-2:#154f5e;
    --muted:#6b8b97;
    --line:#e3f6fc;
    --shadow: 0 18px 60px rgba(12,73,93,.18);
  }

  /* Page blocks */
  .page-title,
  .sub-bar,
  .table-scroll,
  .table-note{
    width:98%;
    margin-left:auto; margin-right:auto;
  }
  .page-title{
    margin:14px auto 6px;
    color:var(--teal);
    font-size:2rem;
    font-weight:800;
    letter-spacing:.02em;
  }

  .sub-bar{
    margin:0 auto 8px;
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  }
  .search-right{ margin-left:auto; display:flex; align-items:center; gap:8px; }
  .search-right input{
    padding:8px 16px;
    border:1.5px solid #c8eafd;
    border-radius:10px;
    font-size:1em;
    background:#fff;
    color:var(--ink);
    box-shadow:0 2px 8px #b9eafc22;
    min-width:260px;
    transition:border-color .18s;
  }
  .search-right input:focus{ outline:0; border-color:var(--teal); }

  .table-scroll{ margin:0 auto 30px; overflow-x:auto; }
  .table-note{ margin:-4px auto 10px; color:var(--muted); font-size:.95em; }

  .table{
    width:100%;
    border-collapse:separate; border-spacing:0;
    min-width:960px;
  }
  .table th{
    background:var(--teal);
    color:#fff;
    padding:14px 12px;
    font-weight:800; text-align:left;
  }
  .table td{
    padding:12px 12px;
    background:#ffffffea;
    color:#186070;
    border-bottom:2px solid var(--line);
    vertical-align:middle;
  }
  .table tr:hover td{ background:#e7f7fc !important; }

  /* Right-align money columns */
  #pendingTable th:nth-child(4), #pendingTable td:nth-child(4),
  #pendingTable th:nth-child(5), #pendingTable td:nth-child(5),
  #pendingTable th:nth-child(6), #pendingTable td:nth-child(6){
    text-align:right; font-variant-numeric: tabular-nums;
  }

  .item-sub{ color:var(--muted); font-size:.92em; }

  .chip{ display:inline-block; padding:6px 14px; border-radius:9px; font-weight:700; }
  .status-no { background:#ffd2d2; color:#c0392b; }

  .amount { font-weight:800; color:var(--ink-2); }
  .amount.due  { background:#ffe9e9; color:#b23b3b; padding:3px 8px; border-radius:8px; }

  button.btn{
    background:var(--teal); color:#fff; border:none;
    padding:9px 16px; border-radius:10px; font-weight:700; cursor:pointer;
    box-shadow:0 1px 6px #b9eafc66;
  }
  button.btn:hover{ transform: translateY(-1px); box-shadow: 0 4px 14px rgba(12,73,93,.18); filter:saturate(1.06); }
  #pendingTable td:last-child{ white-space:nowrap; }

  @media (max-width:720px){
    .table th{ padding:10px 10px; }
    .table td{ padding:10px 10px; }
  }

  /* Edge-to-edge look (small safe gutters) */
  .content-area{ padding-left:8px !important; padding-right:8px !important; }
  .table{ min-width:0 !important; width:100% !important; }

  /* ===== Settle Payment modal ===== */
  .modal{
    position: fixed; inset: 0; display:flex; align-items:center; justify-content:center;
    background: radial-gradient(1200px 420px at 50% -10%, rgba(30,143,162,.10), transparent), rgba(0,151,183,.10);
    opacity:0; visibility:hidden; pointer-events:none;
    transition: opacity .28s ease, backdrop-filter .28s ease; backdrop-filter: blur(0px);
    z-index:10000;
  }
  .modal.open{ opacity:1; visibility:visible; pointer-events:auto; backdrop-filter: blur(3px) saturate(120%); }
  .modal .box{
    position:relative; background:#fff; border-radius:18px; padding:26px; max-width:640px; width:95vw;
    box-shadow: var(--shadow), 0 0 0 1px rgba(30,143,162,.08) inset;
    transform: translateY(14px) scale(.985); opacity:0;
    transition: transform .34s cubic-bezier(.18,.89,.32,1.28), opacity .25s ease;
  }
  .modal.open .box{ transform: translateY(0) scale(1); opacity:1; }
  .modal .box::after{ content:""; position:absolute; left:0; right:0; top:0; height:4px;
    background: linear-gradient(90deg, #1e8fa2, #21ba6e); border-top-left-radius:18px; border-top-right-radius:18px; }
  .modal h2{ font-size:1.65rem; margin:6px 0 14px; color:#1698b4; text-align:center; }
  .modal .grid{ display:grid; grid-template-columns: 1fr 1fr; gap:12px 16px; }
  @media (max-width:640px){ .modal .grid{ grid-template-columns:1fr; } }
  .modal .row{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .modal .label{ font-weight:700; color:var(--teal); min-width:150px; }
  .modal .value{ font-weight:800; color:var(--ink-2); }
  .modal .input,.modal .number{
    width:100%; box-sizing:border-box; padding:10px 12px; border:1.5px solid #c8eafd; border-radius:12px; outline:none; background:#fff; color:var(--ink);
  }
  .modal .input:focus,.modal .number:focus{ border-color:var(--teal); box-shadow:0 0 0 2px #1e8fa21a; }
  .modal .note{ color:var(--muted); font-size:.92em; }
  .modal .actions{ margin-top:14px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
  .swal2-container { z-index: 20000 !important; }
  /* === Ultra-tight gutters for Payments (almost full width) === */

/* Remove any content caps + side paddings */
.content-area .dashboard-main{
  width: 100% !important;
  max-width: none !important;
  margin: 0 !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}

/* Payments wrapper: full bleed */
.payments-wrap{
  width: 100% !important;
  max-width: none !important;
  margin: 0 !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}

/* Inner blocks stretch edge-to-edge */
.payments-wrap .page-title,
.payments-wrap .sub-bar,
.payments-wrap .table-scroll,
.payments-wrap .table-note{
  width: 100% !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
}

/* Keep a tiny safe gutter so text doesn't kiss the edge */
.content-area{
  padding-left: 8px !important;   /* change to 4px if gusto mo pang mas sagad */
  padding-right: 8px !important;
}

/* Tables use entire width */
.table{
  min-width: 0 !important;
  width: 100% !important;
}
/* Right-align money columns (4–6) for the single table */
#paymentsTable th:nth-child(4), #paymentsTable td:nth-child(4),
#paymentsTable th:nth-child(5), #paymentsTable td:nth-child(5),
#paymentsTable th:nth-child(6), #paymentsTable td:nth-child(6){
  text-align:right; font-variant-numeric: tabular-nums;
}

/* Already have .status-no (Pending). Add for Paid + zero style */
.status-ok { background:#d0f5db; color:#27ae60; }
.amount.zero { background:#e6f5ea; color:#2e7d32; padding:3px 8px; border-radius:8px; }
/* right-align money columns on the single table */
#paymentsTable th:nth-child(4), #paymentsTable td:nth-child(4),
#paymentsTable th:nth-child(5), #paymentsTable td:nth-child(5),
#paymentsTable th:nth-child(6), #paymentsTable td:nth-child(6){
  text-align:right; font-variant-numeric: tabular-nums;
}

/* chips + amounts you already have: */
.status-ok { background:#d0f5db; color:#27ae60; }
.amount.zero { background:#e6f5ea; color:#2e7d32; padding:3px 8px; border-radius:8px; }

/* Green Print button (malapit sa screenshot mo) */
.btn-print{
  background:#2ecc71; color:#fff; border:none;
  padding:9px 18px; border-radius:12px; font-weight:800;
  box-shadow:0 1px 6px rgba(60,179,113,.25);
  cursor:pointer; text-decoration:none; display:inline-block;
}
.btn-print:hover{ transform:translateY(-1px); box-shadow:0 6px 16px rgba(60,179,113,.35); }

/* Pagination */
/* Simple, centered pagination */
.pager{
  display:flex;
  gap:8px;
  justify-content:center;
  align-items:center;
  margin:14px auto 6px;
}
.pager button{
  border:1.5px solid #c8eafd;
  background:#fff;
  color:var(--ink);
  padding:6px 12px;
  border-radius:8px;
  font-weight:700;
  cursor:pointer;
}
.pager button.active{
  background:var(--teal);
  color:#fff;
  border-color:var(--teal);
}
.pager button[disabled]{ opacity:.45; cursor:default; }

.content-area.payments-fullbleed{ padding-left:0!important; padding-right:0!important; gap:0!important; }
.content-area.payments-fullbleed .dashboard-main{ width:100%!important; max-width:none!important; margin:0!important; padding:5px!important; }
.content-area.payments-fullbleed .page-title,
.content-area.payments-fullbleed .sub-bar,
.content-area.payments-fullbleed .table-note,
.content-area.payments-fullbleed .table-scroll{ width:100%!important; margin:0!important; padding-left:0!important; padding-right:0!important; }
.content-area.payments-fullbleed .table{ min-width:0!important; width:100%!important; border-spacing:0; }

  </style>
</head>

<body>
  <?php $page = 'payments'; ?>
  <?php include 'includes/sidebar.php'; ?>
  <main class="content-area payments-fullbleed">

    <?php include 'includes/header.php'; ?>

    <div class="dashboard-main">

      <div class="page-title">Payments</div>

      <div class="sub-bar">
        <div class="search-right">
          <input id="paySearch" type="text" placeholder="Search client, booking…">
        </div>
      </div>

     <div class="table-note">Pending bookings first, then fully paid (latest first).</div>
<div class="table-scroll">
  <table class="table compact" id="paymentsTable">
    <thead>
      <tr>
        <th>Client</th>
        <th>Booking</th>
        <th>Package</th>
        <th style="width:120px;">Add-ons</th>
        <th style="width:120px;">Payments</th>
        <th style="width:120px;">Balance</th>
        <th style="width:110px;">Status</th>
        <th style="width:150px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($rows) === 0): ?>
        <tr class="no-data">
          <td colspan="8" style="text-align:center; color:#9aaab5;">No records.</td>
        </tr>
      <?php else: foreach ($rows as $row):
        $code = sprintf('BK-%04d', $row['booking_id']);
        $dateDisp = $row['booking_date'] ? date('M j, Y', strtotime($row['booking_date'])) : '—';
      ?>
      <tr data-booking-id="<?= (int)$row['booking_id'] ?>">
        <td><?= htmlspecialchars($row['full_name']) ?></td>
        <td><b><?= $code ?></b> • <?= $dateDisp ?></td>
        <td>
          <?= htmlspecialchars($row['package_name'] ?? '—') ?>
          <div class="item-sub"><?= peso($row['base_price']) ?></div>
        </td>
        <td><span class="amount"><?= peso($row['addons_total']) ?></span></td>
        <td><span class="amount"><?= peso($row['payments_sum']) ?></span></td>

        <?php if ($row['is_pending']): ?>
          <td><span class="amount due"><?= peso($row['balance']) ?></span></td>
          <td><span class="chip status-no">Pending</span></td>
          <td>
            <button class="btn btn-settle"
                    data-booking-id="<?= (int)$row['booking_id'] ?>"
                    data-booking="<?= $code ?>"
                    data-client="<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>"
                    data-package="<?= htmlspecialchars($row['package_name'] ?? '—', ENT_QUOTES) ?>"
                    data-base="<?= number_format((float)$row['base_price'],2,'.','') ?>"
                    data-addons="<?= number_format((float)$row['addons_total'],2,'.','') ?>"
                    data-payments="<?= number_format((float)$row['payments_sum'],2,'.','') ?>"
                    data-balance="<?= number_format((float)$row['balance'],2,'.','') ?>">
              Settle
            </button>
          </td>
        <?php else: ?>
          <td><span class="amount zero"><?= peso(0) ?></span></td>
          <td><span class="chip status-ok">Fully Paid</span></td>
          <td>
            <a class="btn-print" href="receipt.php?booking_id=<?= (int)$row['booking_id'] ?>"
               target="_blank" rel="noopener">Print</a>
          </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- pagination container -->
<div id="pager" class="pager"></div>



      <!-- MODAL: Settle Payment -->
      <div class="modal" id="settleModal">
        <div class="box">
          <h2>Settle Payment</h2>

          <div class="grid">
            <div class="row"><span class="label">Client:</span><span id="sp_client" class="value">—</span></div>
            <div class="row"><span class="label">Booking:</span><span id="sp_booking" class="value">—</span></div>
            <div class="row"><span class="label">Package:</span><span id="sp_package" class="value">—</span></div>

            <div class="row"><span class="label">Base price:</span><span id="sp_base" class="value">₱0.00</span></div>
            <div class="row"><span class="label">Add-ons:</span><span id="sp_addons" class="value">₱0.00</span></div>
            <div class="row"><span class="label">Paid so far:</span><span id="sp_paid" class="value">₱0.00</span></div>
            <div class="row"><span class="label">Balance:</span><span id="sp_balance" class="value">₱0.00</span></div>

            <div class="row" style="grid-column:1/-1;">
              <span class="label">Receive amount:</span>
              <input id="sp_amount" class="number" type="number" min="0" step="0.01" value="0">
            </div>

            <div class="row" style="grid-column:1/-1;">
              <span class="label">Notes (optional):</span>
              <input id="sp_notes" class="input" type="text" placeholder="Reference #, method (Cash/GCash), remarks…">
            </div>
            <div class="note" style="grid-column:1/-1;">
              Tip: Default amount equals remaining balance. You can take partial payments; balance will update.
            </div>
          </div>

          <div class="actions">
            <button class="btn" id="sp_confirm_btn">Confirm Payment</button>
            <button class="btn-ghost" id="sp_cancel_btn">Cancel</button>
          </div>

          <input type="hidden" id="sp_booking_id">
        </div>
      </div>

    </div><!-- /.dashboard-main -->

  </main>

  <script>
  // Sidebar behavior (same as other pages)
  document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.getElementById('hamburger-btn');
    const overlay = document.getElementById('sidebar-overlay');

    function openSidebar(){ sidebar.classList.add('open'); overlay.classList.add('active'); document.body.style.overflow = "hidden"; }
    function closeSidebar(){ sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ""; }

    if (hamburger) {
      hamburger.addEventListener('click', function(e){
        e.stopPropagation();
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
      });
    }
    if (overlay) overlay.addEventListener('click', closeSidebar);
    document.querySelectorAll('.sidebar-nav a').forEach(link=>{
      link.addEventListener('click', ()=>{ if (window.innerWidth <= 700) closeSidebar(); });
    });
    document.addEventListener('keydown', e=>{ if (e.key === "Escape" && sidebar.classList.contains('open')) closeSidebar(); });
    window.addEventListener('resize', ()=>{ if (window.innerWidth > 700) closeSidebar(); });
  });
  </script>

<script>
function paySearch(){
  const term = (document.getElementById('paySearch')?.value || '').trim().toLowerCase();
  const rows = document.querySelectorAll('#paymentsTable tbody tr');
  let shown = 0;
  rows.forEach(tr => {
    if (tr.classList.contains('no-data')) return;
    // keep the divider visible
    if (tr.hasAttribute('data-divider')) { tr.style.display = ''; return; }
    const vis = tr.innerText.toLowerCase().includes(term);
    tr.style.display = vis ? '' : 'none';
    if (vis) shown++;
  });
  const empty = document.querySelector('#paymentsTable .no-data');
  if (empty) empty.style.display = shown ? 'none' : '';
}
document.getElementById('paySearch')?.addEventListener('input', paySearch);
</script>


  <script>
// ===== Modal helpers =====
function showModal(el){ if(!el) return; el.classList.add('open'); }
function hideModal(el){ if(!el) return; el.classList.remove('open'); }
function phpMoney(n){ return '₱' + (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }
function clamp(n, lo, hi){ n=Number(n||0); return Math.max(lo, Math.min(hi, n)); }

const settleModal = document.getElementById('settleModal');
const spClient  = document.getElementById('sp_client');
const spBooking = document.getElementById('sp_booking');
const spPackage = document.getElementById('sp_package');
const spBase    = document.getElementById('sp_base');
const spAddons  = document.getElementById('sp_addons');
const spPaid    = document.getElementById('sp_paid');
const spBal     = document.getElementById('sp_balance');
const spAmt     = document.getElementById('sp_amount');
const spNotes   = document.getElementById('sp_notes');
const spBid     = document.getElementById('sp_booking_id');

document.getElementById('sp_cancel_btn').addEventListener('click', ()=> hideModal(settleModal));
document.addEventListener('keydown', e=>{ if(e.key==='Escape'){ const m=document.querySelector('.modal.open'); if(m) hideModal(m); } });

// Open modal on “Settle”
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.btn-settle');
  if (!btn) return;

  const meta = {
    booking_id: Number(btn.dataset.bookingId),
    booking:    btn.dataset.booking,
    client:     btn.dataset.client,
    package:    btn.dataset.package,
    base:       Number(btn.dataset.base || 0),
    addons:     Number(btn.dataset.addons || 0),
    paid:       Number(btn.dataset.payments || 0),
    balance:    Number(btn.dataset.balance || 0)
  };

  spClient.textContent  = meta.client || '—';
  spBooking.textContent = meta.booking || '—';
  spPackage.textContent = meta.package || '—';
  spBase.textContent    = phpMoney(meta.base);
  spAddons.textContent  = phpMoney(meta.addons);
  spPaid.textContent    = phpMoney(meta.paid);
  spBal.textContent     = phpMoney(meta.balance);

  spAmt.min = 0; spAmt.step = 0.01;
  spAmt.value = (meta.balance > 0 ? meta.balance : 0).toFixed(2);
  spBid.value = meta.booking_id;
  spNotes.value = '';

  showModal(settleModal);
});

// Confirm payment
document.getElementById('sp_confirm_btn').addEventListener('click', async ()=>{
  const booking_id = Number(spBid.value || 0);
  let amount = Number(spAmt.value || 0);
  if (!booking_id){ Swal.fire({icon:'error', title:'Missing data', text:'No booking id.'}); return; }
  if (!isFinite(amount) || amount <= 0){ Swal.fire({icon:'error', title:'Invalid amount', text:'Enter a positive amount.'}); return; }

  const visibleBal = Number((spBal.textContent || '₱0').replace(/[^\d.]/g,'')) || 0;
  amount = clamp(amount, 0.01, visibleBal || amount);

  const btn = document.getElementById('sp_confirm_btn');
  btn.disabled = true; const old = btn.textContent; btn.textContent = 'Saving…';

  try{
    const res = await fetch('settle_payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        booking_id,
        amount: Number(amount.toFixed(2)),
        notes: (spNotes.value || '').trim()
      })
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.message || 'Payment failed');

    hideModal(settleModal);
await new Promise(r=>setTimeout(r,250));
await Swal.fire({ icon:'success', title:'Payment recorded', text:`New balance: ${phpMoney(data.new_balance)}`, confirmButtonColor:'#21ba6e' });

// Live-update the row (no reload)
updatePaymentRowUI(booking_id, data);

  }catch(err){
    Swal.fire({ icon:'error', title:'Payment failed', text: String(err.message||err) });
  }finally{
    btn.disabled = false; btn.textContent = old;
  }
});

function findRowByBookingId(id){
  return document.querySelector(`tr[data-booking-id="${id}"]`);
}

function moneySpan(val, cls){
  const klass = cls ? ` ${cls}` : '';
  return `<span class="amount${klass}">${phpMoney(val)}</span>`;
}

function updatePaymentRowUI(bookingId, payload){
  const tr = findRowByBookingId(bookingId);
  if (!tr) return;

  const tds = tr.querySelectorAll('td');
  // Column indexes based on header order:
  // 0=Client, 1=Booking, 2=Package, 3=Add-ons, 4=Payments, 5=Balance, 6=Status, 7=Action

  // 1) Update Payments & Balance cells
  if (tds[4]) tds[4].innerHTML = moneySpan(payload.paid_total);
  if (tds[5]) {
    const zero = (payload.new_balance <= 0.009);
    tds[5].innerHTML = moneySpan(payload.new_balance, zero ? 'zero' : 'due');
  }

  // 2) Update Status chip
  if (tds[6]) {
    if (payload.is_fully_paid) {
      tds[6].innerHTML = '<span class="chip status-ok">Paid</span>';
    } else {
      tds[6].innerHTML = '<span class="chip status-no">Pending</span>';
    }
  }

  // 3) Update Action cell
// 3) Update Action cell + move row to Paid section if fully paid
if (tds[7]) {
  if (payload.is_fully_paid) {
    // Change action to Print
    tds[7].innerHTML =
      `<a class="btn-outline btn-receipt" href="receipt.php?booking_id=${bookingId}" target="_blank" rel="noopener">Print</a>`;

    // Move the row under the "Fully paid" divider (top of the Paid group)
    const tbody = document.querySelector('#paymentsTable tbody');
    let divider = tbody.querySelector('tr[data-divider="paid"]');
    if (!divider) {
      divider = document.createElement('tr');
      divider.setAttribute('data-divider', 'paid');
      divider.innerHTML = `<td colspan="8" style="background:#f2fbff;border-bottom:2px solid #e3f6fc;font-weight:800;color:#186070;">Fully paid (latest first)</td>`;
      tbody.appendChild(divider);
    }
    // insert right after the divider so newly-paid appears at the top of the paid group
    tbody.insertBefore(tr, divider.nextSibling);
  } else {
    // Still pending: keep Settle button but refresh data-* values
    const btn = tds[7].querySelector('.btn-settle');
    if (btn) {
      btn.dataset.payments = Number(payload.paid_total || 0).toFixed(2);
      btn.dataset.balance  = Number(payload.new_balance || 0).toFixed(2);
    }
  }
}

}

  </script>
<script>
/* ---------- SEARCH (mark only) ---------- */
function getAllDataRows(){
  return Array.from(document.querySelectorAll('#paymentsTable tbody tr'))
    .filter(tr => !tr.classList.contains('no-data'));
}

function paySearch(){
  const term = (document.getElementById('paySearch')?.value || '').trim().toLowerCase();
  const rows = getAllDataRows();
  let hit = 0;
  rows.forEach(tr => {
    const ok = tr.innerText.toLowerCase().includes(term);
    tr.dataset.match = ok ? '1' : '0';
    if (ok) hit++;
  });
  const empty = document.querySelector('#paymentsTable .no-data');
  if (empty) empty.style.display = hit ? 'none' : '';
  pager.page = 1;        // reset to first page on search
  applyPager();
}
document.getElementById('paySearch')?.addEventListener('input', paySearch);

/* ---------- PAGINATION ---------- */
const pager = { page: 1, perPage: 10 };

function applyPager(){
  const rows = getAllDataRows().filter(tr => tr.dataset.match !== '0'); // only matched rows
  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / pager.perPage));
  if (pager.page > pages) pager.page = pages;

  rows.forEach((tr, idx) => {
    const start = (pager.page - 1) * pager.perPage;
    const end   = start + pager.perPage;
    tr.style.display = (idx >= start && idx < end) ? '' : 'none';
  });

  renderPagerControls(pages);
}

function renderPagerControls(pages){
  const el = document.getElementById('pager');
  if (!el) return;
  el.innerHTML = '';

  for (let i = 1; i <= pages; i++){
    const b = document.createElement('button');
    b.textContent = i;
    if (i === pager.page) b.classList.add('active');
    b.onclick = ()=>{ pager.page = i; applyPager(); };
    el.appendChild(b);
  }
}

// init defaults (mark all as matched, then paginate)
document.addEventListener('DOMContentLoaded', ()=>{
  getAllDataRows().forEach(tr => tr.dataset.match = '1');
  applyPager();
});
</script>
<script>
function findRowByBookingId(id){
  return document.querySelector(`tr[data-booking-id="${id}"]`);
}
function moneySpan(val, cls){
  const klass = cls ? ` ${cls}` : '';
  return `<span class="amount${klass}">${phpMoney(val)}</span>`;
}
function moveRowToPaidGroup(tr){
  const tbody = document.querySelector('#paymentsTable tbody');
  const rows = getAllDataRows().filter(r => r.dataset.match !== '0'); // current logical list
  let lastPending = null;
  rows.forEach(r=>{
    const chip = r.querySelector('td:nth-child(7) .chip');
    const txt  = (chip?.textContent || '').trim().toLowerCase();
    if (txt === 'pending') lastPending = r;
  });
  if (lastPending) {
    if (lastPending.nextSibling) tbody.insertBefore(tr, lastPending.nextSibling);
    else tbody.appendChild(tr);
  } else {
    tbody.insertBefore(tr, tbody.firstChild); // no pending left → top
  }
}

function updatePaymentRowUI(bookingId, payload){
  const tr = findRowByBookingId(bookingId);
  if (!tr) return;
  const tds = tr.querySelectorAll('td');
  // 0=Client, 1=Booking, 2=Package, 3=Add-ons, 4=Payments, 5=Balance, 6=Status, 7=Action

  // Payments + Balance
  if (tds[4]) tds[4].innerHTML = moneySpan(payload.paid_total);
  if (tds[5]) {
    const zero = (payload.new_balance <= 0.009);
    tds[5].innerHTML = moneySpan(payload.new_balance, zero ? 'zero' : 'due');
  }

  // Status + Action
  if (payload.is_fully_paid) {
    if (tds[6]) tds[6].innerHTML = '<span class="chip status-ok">Fully Paid</span>';
    if (tds[7]) {
      tds[7].innerHTML =
        `<a class="btn-print" href="receipt.php?booking_id=${bookingId}" target="_blank" rel="noopener">Print</a>`;
    }
    moveRowToPaidGroup(tr);
  } else {
    if (tds[6]) tds[6].innerHTML = '<span class="chip status-no">Pending</span>';
    if (tds[7]) {
      const btn = tds[7].querySelector('.btn-settle');
      if (btn) {
        btn.dataset.payments = Number(payload.paid_total || 0).toFixed(2);
        btn.dataset.balance  = Number(payload.new_balance || 0).toFixed(2);
      }
    }
  }

  // re-apply pagination after DOM changes
  applyPager();
}
</script>

</body>
</html>
