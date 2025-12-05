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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Aquasafe RuripPh Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="styles/style.css">
<style>
/* ===========================
   THEME
=========================== */
:root{
  --teal:#1e8fa2;
  --ink:#186070;
  --ink-2:#154f5e;
  --muted:#6b8b97;
  --line:#e3f6fc;
  --shadow: 0 18px 60px rgba(12,73,93,.18);
}

/* ===========================
   LAYOUT
=========================== */
.content-area{
  max-width:none;
  padding-left:12px;
  padding-right:12px;
}

/* Page title */
.page-title{
  width:98%;
  margin:14px auto 6px;
  color:var(--teal);
  font-size:2rem;
  font-weight:800;
  letter-spacing:.02em;
}

/* Sub bar: tabs + search */
.sub-bar{
  width:98%;
  margin:0 auto 8px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}
.tabs-left{ display:flex; gap:8px; flex-wrap:wrap; }

.tab-btn{
  padding:10px 14px;
  border-radius:10px;
  border:1.5px solid #c8eafd;
  background:#fff;
  color:var(--ink);
  font-weight:700;
  cursor:pointer;
}
.tab-btn.active{ background:var(--teal); color:#fff; border-color:var(--teal); }

.search-right{ display:flex; align-items:center; gap:8px; }
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

/* Tab panels */
.tab-panel{ display:none; }
.tab-panel.active{ display:block; }

/* ===========================
   TABLES
=========================== */
.table-scroll{ width:98%; margin:0 auto 30px; overflow-x:auto; }
.table-note{ width:98%; margin:-4px auto 10px; color:var(--muted); font-size:.92em; }

.table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  min-width:980px;
}
.table.inventory{ min-width:980px; }

.table th{
  background:var(--teal);
  color:#fff;
  padding:14px 12px;
  font-weight:800;
  text-align:left;
}
.table td{
  padding:12px 12px;
  background:#ffffffea;
  color:var(--ink);
  border-bottom:2px solid var(--line);
  vertical-align:middle;
}
.table tr:hover td{ background:#e7f7fc !important; }

/* compact table variant */
.table.compact th{ padding:12px 10px; }
.table.compact td{ padding:10px 10px; font-size:.96em; }

/* ===========================
   CHIPS & BUTTONS
=========================== */
.chip{ display:inline-block; padding:6px 14px; border-radius:9px; font-weight:700; }
.status-no { background:#ffd2d2; color:#c0392b; }
.status-ok { background:#d0f5db; color:#27ae60; }

.btn{
  background:var(--teal);
  color:#fff;
  border:none;
  padding:9px 16px;
  border-radius:10px;
  font-weight:700;
  cursor:pointer;
  box-shadow:0 1px 6px #b9eafc66;
}
.btn-outline{
  background:#fff;
  color:var(--teal);
  border:1.5px solid var(--teal);
  padding:8px 14px;
  border-radius:10px;
  font-weight:700;
  cursor:pointer;
}
.btn-ghost{
  background:#c8eafd;
  color:#146b8b;
  border:none;
  padding:10px 22px;
  border-radius:9px;
  cursor:pointer;
}

/* ===========================
   INVENTORY CELLS
=========================== */
.num-col{ width:60px;  text-align:center; }
.pic-col{ width:90px;  }
.qty-col{ width:120px; text-align:center; }
.inuse-col{ width:120px; text-align:center; }
.action-col{ width:110px; text-align:center; }

.item-cell{ display:flex; align-items:center; gap:10px; }
.thumb{
  width:56px; height:56px; object-fit:cover;
  border-radius:10px; border:1px solid #e8f5fb; background:#f6fbfd;
}
.item-name{ font-weight:700; color:var(--ink-2); }
.item-sub { color:var(--muted); font-size:.92em; }

/* ===========================
   RESPONSIVE WIDTH TWEAKS
=========================== */
@media (min-width:1100px){
  .page-title, .sub-bar, .table-scroll, .table-note { width:98%; }
}
@media (min-width:1600px){
  .page-title, .sub-bar, .table-scroll, .table-note { width:99%; }
}

/* ===========================
   MODAL (single, animated)
   NOTE: Walang display:none dito.
=========================== */
.modal{
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;

  /* overlay + subtle highlight */
  background:
    radial-gradient(1200px 420px at 50% -10%, rgba(30,143,162,.10), transparent),
    rgba(0,151,183,.10);

  /* hidden state */
  opacity: 0;
  visibility: hidden;
  pointer-events: none;

  transition: opacity .28s ease, backdrop-filter .28s ease;
  backdrop-filter: blur(0px);
  z-index:10000;
}
.modal.open{
  opacity:1;
  visibility:visible;
  pointer-events:auto;
  backdrop-filter: blur(3px) saturate(120%);
}

/* modal card */
.modal .box{
  position: relative;
  background:#fff;
  border-radius:18px;
  padding:22px;
  max-width:680px;
  width:95vw;

  /* inner ring + drop shadow */
  box-shadow: var(--shadow), 0 0 0 1px rgba(30,143,162,.08) inset;

  /* entrance animation */
  transform: translateY(14px) scale(.985);
  opacity: 0;
  transition:
    transform .34s cubic-bezier(.18,.89,.32,1.28),
    opacity .25s ease;
}
.modal .box::after{
  content:"";
  position:absolute; left:0; right:0; top:0;
  height:4px;
  background: linear-gradient(90deg, #1e8fa2, #21ba6e);
  border-top-left-radius:18px; border-top-right-radius:18px;
}
.modal.open .box{ transform: translateY(0) scale(1); opacity:1; }
.modal.closing .box{ transform: translateY(10px) scale(.985); opacity:0; }

.modal h2{
  font-size:1.65rem;
  margin:6px 0 14px;
  color:#1698b4;
  text-align:center;
}

/* Generic grids inside modals (used by Assign) */
.grid{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:640px){ .grid{ grid-template-columns:1fr; } }
.row{ display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:10px; }
.label{ font-weight:700; color:var(--teal); min-width:140px; }

/* ===========================
   ITEM SIMPLE MODAL POLISH
=========================== */
#itemSimpleModal .box{
  max-width:640px;
  padding:32px 26px 24px;
}

#itemSimpleModal .form-grid{
  display:grid;
  grid-template-columns: 1fr 180px;  /* name grows, qty compact */
  gap:12px;
  align-items:end;
}
#itemSimpleModal .form-grid .full-row{ grid-column:1 / -1; }

/* Inputs – consistent width & focus ring */
#itemSimpleModal .input,
#itemSimpleModal .number,
#itemSimpleModal .textarea,
#itemSimpleModal .select{
  box-sizing:border-box;
  width:100%;
  padding:10px 12px;
  border:1.5px solid #c8eafd;
  border-radius:12px;
  color:var(--ink);
  background:#fff;
  outline:none;
}
#itemSimpleModal .input:focus,
#itemSimpleModal .number:focus,
#itemSimpleModal .textarea:focus{
  border-color: var(--teal);
  box-shadow: 0 0 0 2px #1e8fa21a;
}

#itemSimpleModal .label{
  font-weight:700;
  color:var(--teal);
  margin-bottom:6px;
}

/* Preview row */
#itemSimpleModal .preview-row{
  display:flex;
  align-items:center;
  gap:12px;
  margin-top:8px;
}
#itemSimpleModal .thumb{
  width:64px; height:64px;
  border-radius:12px;
  object-fit:cover;
  border:1px solid #e8f5fb;
  background:#f6fbfd;
}
/* long path wrapping */
#itemSimpleModal .imgpath{
  color:var(--muted);
  word-break:break-all;
  line-height:1.3;
}

/* Actions row in modal */
#itemSimpleModal .actions{
  margin-top:14px;
  display:flex; gap:10px; justify-content:center; flex-wrap:wrap;
}

/* Single-column on small screens */
@media (max-width:560px){
  #itemSimpleModal .form-grid{ grid-template-columns:1fr; }
}

/* ===========================
   (No duplicate .modal blocks below!)
=========================== */

/* === Assign Gear modal — layout & polish === */
#assignModal .box{
  max-width: 820px;
  width: 96vw;
  padding: 28px 28px 22px;
}
#assignModal h2{ margin-bottom: 12px; }

#assignModal .grid{
  display:grid;
  grid-template-columns: 1fr 1fr;   /* 2 cols: Client/Booking, Package/Days, Issue/Due */
  gap: 12px 22px;
}
#assignModal .row{                    /* label + value/input pair */
  display:flex; align-items:center; gap:12px; margin:0;
}
#assignModal .row .label{
  flex:0 0 130px;                    /* fixed label width for clean alignment */
  color:var(--teal); font-weight:700;
}

#assignModal .input,
#assignModal .number{
  box-sizing:border-box;
  width:100%;
  padding:9px 12px;
  border:1.5px solid #c8eafd;
  border-radius:10px;
  color:var(--ink);
  background:#fff;
  outline:none;
}
#assignModal .input:focus,
#assignModal .number:focus{
  border-color:var(--teal);
  box-shadow:0 0 0 2px #1e8fa21a;
}

/* compact widths for specific fields */
#assignModal #ag_days{ max-width:160px; text-align:center; }
#assignModal input[type="datetime-local"]{ max-width:280px; }

/* sections (Included, Add-ons, Notes, Total) */
#assignModal .section{
  margin-top: 12px;
  padding-top: 12px;
  border-top:1px solid #e8f5fb;
}
#assignModal .section .title{
  font-weight:800; color:var(--teal);
  margin: 6px 0 6px;
}

/* included items: name left, qty right */
#assignModal .section .row{ justify-content:space-between; }
#assignModal .section .row b{ color:#186070; }

/* add-on qty wrapper */
#assignModal .qtywrap{
  display:flex; align-items:center; gap:8px;
  white-space:nowrap;
}
#assignModal .addon{ width:90px; }

/* notes full width */
#assignModal #ag_notes{ width:100%; }

/* total line + actions */
#assignModal .total-line{
  display:flex; justify-content:flex-end; gap:6px; margin-top:6px; font-weight:800;
}
#assignModal #ag_total{ color:#146b8b; }

#assignModal .actions{
  margin-top:16px;
  display:flex; gap:10px; justify-content:center; flex-wrap:wrap;
}

/* responsive: stack to single column on small screens */
@media (max-width: 720px){
  #assignModal .grid{ grid-template-columns:1fr; }
  #assignModal .row .label{ flex-basis:120px; }
  #assignModal input[type="datetime-local"]{ max-width:100%; }
}
/* Make SweetAlert appear above our modal (modal z-index=10000) */
.swal2-container { z-index: 20000 !important; }

#returnsTable td.gear-used{
  white-space: normal;
  line-height: 1.35;
  word-break: break-word;   /* optional but helpful */
}
#returnsTable td.gear-used .addon{
  color: var(--muted);
  font-size: .9em;
}
.btn:hover{ transform: translateY(-1px); box-shadow: 0 4px 14px rgba(12,73,93,.18); filter: saturate(1.06); }
.btn:active{ transform: translateY(0); }
.btn-outline:hover{ background: var(--teal); color:#fff; }
.btn-ghost:hover{ background:#cfeffc; }

/* --- Return modal: same polish as Assign modal --- */
#returnModal .box{
  max-width: 820px;
  width: 96vw;
  padding: 28px 28px 22px;
}
#returnModal h2{ margin-bottom: 12px; }

#returnModal .grid{
  display:grid;
  grid-template-columns: 1fr 1fr;   /* 2 cols like Assign */
  gap: 12px 22px;
}
#returnModal .row{
  display:flex; align-items:center; gap:12px; margin:0;
}
#returnModal .label{
  flex:0 0 130px;
  color:var(--teal); font-weight:700;
}
#returnModal .value{
  color:var(--ink-2); font-weight:700;
}
#returnModal .input{
  box-sizing:border-box; width:100%;
  padding:9px 12px;
  border:1.5px solid #c8eafd; border-radius:10px;
  background:#fff; color:var(--ink); outline:none;
}
#returnModal .input:focus{
  border-color:var(--teal);
  box-shadow:0 0 0 2px #1e8fa21a;
}
#returnModal .actions{
  margin-top:16px;
  display:flex; gap:10px; justify-content:center; flex-wrap:wrap;
}

/* hover states (shared) */
.btn:hover{ transform: translateY(-1px); box-shadow: 0 4px 14px rgba(12,73,93,.18); filter: saturate(1.06); }
.btn:active{ transform: translateY(0); }
.btn-outline:hover{ background: var(--teal); color:#fff; }
.btn-ghost:hover{ background:#cfeffc; }

@media (max-width:720px){
  #returnModal .grid{ grid-template-columns:1fr; }
}
/* Gear-used line tags */
#returnsTable td.gear-used .chip{
  display:inline-block;
  margin-left:6px;
  padding:3px 8px;
  border-radius:8px;
  font-weight:700;
}

/* Color coding (aligned sa theme mo) */
#returnsTable .tag-good{        background:#eef7f1; color:#2e7d32; }
#returnsTable .tag-outstanding{ background:#e7f1fb; color:#1769aa; }
#returnsTable .tag-damaged{     background:#ffe9d6; color:#c77d00; }
#returnsTable .tag-missing{     background:#ffd6d6; color:#c0392b; }

/* Return modal items table */
.ret-table th, .ret-table td { vertical-align: middle; }
.ret-table input.ret-qty { width: 100%; box-sizing: border-box; text-align:center; }
.ret-table select.ret-cond { width: 100%; box-sizing: border-box; }

/* ===== Return modal sizing & scroll ===== */
#returnModal .box{
  max-width: 1080px;      /* mas malapad para magkasya ang columns */
  width: 96vw;
  padding: 28px 28px 22px;
}

/* Scroll container para sa table sa loob ng modal */
#returnModal .items-scroll{
  max-height: 380px;      /* taas ng visible rows; adjust kung gusto mo */
  overflow: auto;         /* both axes — vertical/horizontal kung kailangan */
  margin-top: 8px;
  border: 1px solid #e8f5fb;
  border-radius: 12px;
}

/* Paliitin ng konti ang cells para kumasya */
#returnModal .table.compact th,
#returnModal .table.compact td{ padding: 10px; }

/* Column controls */
#returnModal .ret-qty{ width: 80px; text-align:center; }
#returnModal .ret-cond{ min-width: 140px; }
#returnModal .ret-note{ width: 100%; }

/* Footer bar: summary + buttons nasa isang linya */
#returnModal .footer-bar{
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 12px;
}
#returnModal #ret_summary{ font-weight: 800; color: var(--ink-2); }

/* maliit na screen: hayaan mag-scroll horizontally kung kailangan */
@media (max-width: 740px){
  #returnModal .box{ width: 98vw; }
  #returnModal .items-scroll{ max-height: 55vh; }
}
/* ==== Layout width overrides (maximize space) ==== */
.page-title,
.sub-bar,
.table-scroll,
.table-note { 
  width: 100%;
  margin-left: 0;
  margin-right: 0;
}

/* Slightly larger inner padding but full width */
.content-area{
  padding-left: 16px;
  padding-right: 16px;
}

/* Tables should stretch; let the wrapper handle scroll only when needed */
.table,
.table.inventory{
  min-width: 0;      /* was 980px */
  width: 100%;
}
/* ==== Table column sizing & alignment ==== */
.table .num-col{ width: 56px; text-align: right; color: var(--ink-2); }
.table .pic-col{ width: 72px; }
.table .qty-col,
.table .inuse-col{ width: 90px; text-align: center; font-variant-numeric: tabular-nums; 
  width: 90px;
  text-align: center;
  font-variant-numeric: tabular-nums;}
@media (max-width: 720px){
  .table th{ padding: 10px 10px; }
  .table td{ padding: 10px 10px; }
  .table .qty-col, .table .inuse-col{ width: 72px; }
  .table .pic-col{ width: 64px; }
}
/* === Inventory action buttons (uniform width + theme colors + icons) === */
.inv-actions{
  display:flex;
  flex-direction:column;
  gap:8px;
  align-items:flex-end; /* same look as your screenshot (right-aligned) */
}

.inv-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  width:118px;                 /* <<< PARE-PAREHO ANG LAPAD */
  padding:10px 12px;
  border-radius:14px;
  font-weight:700;
  font-size:0.95rem;
  border:1.5px solid transparent;
  transition:background .2s ease, color .2s ease, border-color .2s ease, transform .02s ease;
  line-height:1;
  white-space:nowrap;
}
.inv-btn i{ font-size:0.95rem; line-height:1; }
.inv-btn:active{ transform: translateY(1px); }

/* — Theme palettes (uses your variables) — */
.inv-btn.edit{
  background:#fff;
  color:var(--teal);
  border-color:var(--teal);
}
.inv-btn.edit:hover{
  background:var(--teal);
  color:#fff;
}

/* “Clean” = soft aqua */
.inv-btn.clean{
  background:#e6f7fa;            /* light aqua fill */
  color:var(--ink);
  border-color:#cdeff7;
}
.inv-btn.clean:hover{
  background:#d9f2f9;
}

/* “Damage” = gentle, on-brand red tint (subtle so it still matches the teal theme) */
.inv-btn.damage{
  background:#ffecec;            /* soft red tint */
  color:#b23b3b;
  border-color:#ffd2d2;
}
.inv-btn.damage:hover{
  background:#ffdcdc;
}

/* Responsive tweak: tighter when narrow screens */
@media (max-width:720px){
  .inv-btn{ width:108px; padding:9px 10px; }
}
.inv-btn[disabled]{ opacity:.55; cursor:not-allowed; }
.btn-outline.btn-assigned[disabled],
.btn-outline.btn-assigned[disabled]:hover {
  background: #dff4e6;
  color: #2e7d32;
  border-color: #bfe9d0;
  opacity: 1;
  cursor: not-allowed;
  pointer-events: none;
  transform: none;
  box-shadow: none;
}
.content-area {
  padding: 5px;
}
.table .missing-col{
  width:90px;
  text-align:center;
  font-variant-numeric: tabular-nums;
}
/* ==== Bigger thumbnails for Inventory table ==== */
.table.inventory .pic-col{
  width:110px; /* mas maluwag na space sa column */
}

.table.inventory .thumb{
  width:80px;
  height:80px;
}

/* medyo liitan ulit sa very small screens */
@media (max-width:720px){
  .table.inventory .thumb{
    width:64px;
    height:64px;
  }
}

</style>

</head>

<body>
  <?php $page = 'inventory'; ?>
  <?php include 'includes/sidebar.php'; ?>
  <main class="content-area">
    <?php include 'includes/header.php'; ?>

    <div class="page-title">Equipment Management</div>

    <div class="sub-bar">
      <div class="tabs-left">
        <button class="tab-btn active" data-tab="tab-approved">Approved Clients</button>
        <button class="tab-btn" data-tab="tab-returns">Gear Returns</button> <!-- NEW -->
        <button class="tab-btn" data-tab="tab-inventory">Inventory</button>
      </div>
      <div class="search-right">
        <input id="globalSearch" type="text" placeholder="Search current tab…">
        <button class="btn" id="addItemBtn" style="display:none"><i class="fa fa-plus"></i> Add Item</button>
      </div>
    </div>

    <!-- ===== TAB: Approved Clients (demo) ===== -->
    <section id="tab-approved" class="tab-panel active">
      <div class="table-note">Assign free gear per client. Add-ons are optional at issuing.</div>
      <div class="table-scroll">
        <table class="table compact" id="approvedTable">
              <thead>
        <tr>
          <th>Client</th>
          <th>Booking</th>
          <th>Package</th>
          <th>Available</th>
          <th>Action</th>
        </tr>
      </thead>


      <tbody>
<?php
$sql = "SELECT 
          b.booking_id,
          b.booking_date,
          u.full_name,
          pk.name AS package_name,
          EXISTS (
            SELECT 1
            FROM rental_kit rk
            WHERE rk.booking_id = b.booking_id
              AND rk.status IN ('issued','partial','overdue')
          ) AS has_active_issue
        FROM booking b
        JOIN user u      ON u.user_id = b.user_id
        LEFT JOIN package pk ON pk.package_id = b.package_id
        WHERE b.status = 'approved'
        ORDER BY has_active_issue ASC, b.booking_date DESC, b.booking_id DESC";

$res = $conn->query($sql);

if ($res && $res->num_rows) {
  while ($row = $res->fetch_assoc()) {
    $code     = sprintf('BK-%04d', $row['booking_id']);
    $dateDisp = date('M j, Y', strtotime($row['booking_date']));
    $active   = (int)$row['has_active_issue'] === 1;

    $issuedChip = $active
      ? '<span class="chip status-ok">Yes</span>'
      : '<span class="chip status-no">No</span>';

    $assignBtn = $active
  ? '<button class="btn-outline btn-assigned" disabled aria-disabled="true">Assigned</button>'
  : '<button class="btn-outline btn-assign"
        data-booking-id="'.(int)$row['booking_id'].'"
        data-booking="'.$code.'"
        data-client="'.htmlspecialchars($row['full_name'], ENT_QUOTES).'"
        data-package="'.htmlspecialchars($row['package_name'] ?? '—', ENT_QUOTES).'"
        data-days="2">Assign Gear</button>';

    ?>
    <tr>
      <td><?= htmlspecialchars($row['full_name']) ?></td>
      <td><b><?= $code ?></b> • <?= $dateDisp ?></td>
      <td><?= htmlspecialchars($row['package_name'] ?? '—') ?></td>
      <td><?= $issuedChip ?></td>
      <td><?= $assignBtn ?></td>
    </tr>
    <?php
  }
} else {
  echo '<tr class="no-approved"><td colspan="5" style="text-align:center; color:#9aaab5;">No approved clients.</td></tr>';
}
?>

</tbody>
        </table>
      </div>
    </section>

    <!-- ===== TAB: Gear Returns ===== -->
<section id="tab-returns" class="tab-panel">
  <div class="table-note">Listahan ng mga kit na na-issue (kasama ang add-ons). I-mark as returned kapag naibalik na.</div>

  <div class="table-scroll">
    <table class="table compact" id="returnsTable">
      <thead>
        <tr>
          <th>Client</th>
          <th>Gear Used</th>
          <th>Issued</th>
          <th>Condition</th>
          <th>Status</th>
          <th style="width:160px;">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php
        // NOTE: hindi na tayo kukuha ng gear_list dito;
        // bubuuin natin per line sa loob ng loop gamit rental_kit_item
        $sql = "
  SELECT
    rk.kit_id,
    rk.booking_id,
    rk.issue_time,
    rk.status,
    rk.overall_condition,   -- ← ito ang tama
    u.full_name
  FROM rental_kit rk
  JOIN booking b ON b.booking_id = rk.booking_id
  JOIN user u    ON u.user_id    = b.user_id
  ORDER BY rk.issue_time DESC
";

        if ($res = $conn->query($sql)) {
          if ($res->num_rows) {
            while ($row = $res->fetch_assoc()) {
              $code   = sprintf('BK-%04d', $row['booking_id']);
              $issued = $row['issue_time'] ? date('M j, Y g:i:s A', strtotime($row['issue_time'])) : '—';
              $cond = $row['overall_condition'] ? ucfirst($row['overall_condition']) : '—';

              $statusChip = ($row['status'] === 'returned')
                ? '<span class="chip status-ok">Returned</span>'
                : '<span class="chip status-no">Issued</span>';

              // --- BUILD PER-LINE GEAR LIST ---
              $gearHtml = '—';
              $itemsSql = "SELECT item_name, qty, is_addon, returned_qty, `condition`, damage_notes
             FROM rental_kit_item
             WHERE kit_id = ".(int)$row['kit_id']."
             ORDER BY is_addon, item_name";

              if ($itemsRes = $conn->query($itemsSql)) {
                $lines = [];
                while ($ir = $itemsRes->fetch_assoc()) {
                  $name = htmlspecialchars($ir['item_name']);
$qty  = (int)$ir['qty'];
$addon = ((int)$ir['is_addon'] === 1) ? ' <span class="addon">(add-on)</span>' : '';

$ret  = (int)($ir['returned_qty'] ?? 0);
$cond = $ir['condition'] ?? null; // 'good' | 'damaged' | 'missing'
$note = trim((string)($ir['damage_notes'] ?? ''));
$out  = max(0, $qty - $ret);

$tags = [];
if ($out > 0)               $tags[] = '<span class="chip tag-outstanding">Outstanding: '.$out.'</span>';
if ($cond === 'damaged')    $tags[] = '<span class="chip tag-damaged">Damaged'.($note ? ' — '.htmlspecialchars($note) : '').'</span>';
elseif ($cond === 'missing')$tags[] = '<span class="chip tag-missing">Missing</span>';
elseif ($out === 0)         $tags[] = '<span class="chip tag-good">Good</span>';

$lines[] = "{$name} × {$qty}{$addon}".( $tags ? ' '.implode(' ', $tags) : '' );

                }
                $itemsRes->free();
                if ($lines) $gearHtml = implode('<br>', $lines); // <-- line breaks dito
              }
              // ---------------------------------

              // Action button (enabled lang kapag 'issued')
              if ($row['status'] === 'issued') {
                $btn = '<button class="btn btn-return"
                            data-kit-id="'.(int)$row['kit_id'].'"
                            data-client="'.htmlspecialchars($row['full_name'], ENT_QUOTES).'"
                            data-booking="'.$code.'">Mark Returned</button>';
              } else {
                $btn = '<button class="btn-outline" disabled>Returned</button>';
              }

              echo '<tr>'.
                     '<td>'.htmlspecialchars($row['full_name']).'<div class="item-sub"><b>'.$code.'</b></div></td>'.
'<td class="gear-used">'. ($gearHtml ?: '—') .'</td>'.

                     '<td>'.$issued.'</td>'.
                     '<td>'.$cond.'</td>'.
                     '<td>'.$statusChip.'</td>'.
                     '<td>'.$btn.'</td>'.
                   '</tr>';
            }
          } else {
            echo '<tr class="no-returns"><td colspan="6" style="text-align:center; color:#9aaab5;">No issued kits.</td></tr>';
          }
          $res->free();
        } else {
          echo '<tr><td colspan="6" style="color:#c0392b">DB error: '.htmlspecialchars($conn->error).'</td></tr>';
        }
      ?>
      </tbody>
    </table>
  </div>
</section>


    <!-- ===== TAB: Inventory ===== -->
    <section id="tab-inventory" class="tab-panel">
      <div class="table-note">Simple stock view — quantities only. “In use” is read-only (based on active dives).</div>
      <div class="table-scroll">
        <table class="table inventory" id="inventoryTable">
         <thead>
  <tr>
    <th>No.</th>
    <th>Item<br>Picture</th>
    <th>Name</th>
    <th>Available</th>
    <th>Cleaning</th>
    <th>Damaged</th>
    <th>In use</th>
    <th>Missing</th>
    <th>Action</th>
  </tr>
</thead>

          <tbody>
          <?php
            // Render live data from inventory_item (if table exists & has rows)
            $rowsRendered = 0;
            if ($conn) {
$sql = "
SELECT
  ii.item_id,
  ii.name,
  ii.image_path,
  ii.total_qty,
  ii.is_addon,
  ii.price_per_day,
  ii.cleaning_qty,
  ii.damaged_qty,

  -- outstanding sa active kits (issued/partial/overdue)
  COALESCE(SUM(
    CASE 
      WHEN rk.status IN ('issued','partial','overdue') 
      THEN GREATEST(rki.qty - COALESCE(rki.returned_qty,0), 0)
      ELSE 0
    END
  ), 0) AS in_use_out,

  -- 🆕 total missing for this item (kahit returned na yung kit)
  COALESCE(SUM(
    CASE 
      WHEN rki.condition = 'missing' 
      THEN GREATEST(rki.qty - COALESCE(rki.returned_qty,0), 0)
      ELSE 0
    END
  ), 0) AS missing_qty

FROM inventory_item ii
LEFT JOIN rental_kit_item rki
  ON rki.item_id = ii.item_id
LEFT JOIN rental_kit rk
  ON rk.kit_id = rki.kit_id
GROUP BY 
  ii.item_id, ii.name, ii.image_path, ii.total_qty, 
  ii.is_addon, ii.price_per_day, ii.cleaning_qty, ii.damaged_qty
ORDER BY ii.name";

              if ($res = $conn->query($sql)) {
                while ($it = $res->fetch_assoc()) {
                  $rowsRendered++;
$name    = htmlspecialchars($it['name']);
$qty     = (int)$it['total_qty'];
$inUse   = (int)$it['in_use_out'];          // active rentals
$clean   = (int)$it['cleaning_qty'];
$damg    = (int)$it['damaged_qty'];
$missing = (int)$it['missing_qty'];         // 🆕 galing sa SQL

// bawas na rin ang missing sa available
$avail = max(0, $qty - $inUse - $clean - $damg - $missing);


$img   = $it['image_path'] ? '../'.htmlspecialchars($it['image_path']) : '../assets/no-image.png';
$dataImg = $it['image_path'] ? htmlspecialchars($it['image_path']) : '';
$ia    = (int)$it['is_addon'];
$price = (float)$it['price_per_day'];
$sub   = $ia ? ('Add-on • ₱'.number_format($price,2).'/day') : 'Included (free)';


echo '<tr>';
echo '  <td class="num-col"></td>';
echo '  <td class="pic-col"><img class="thumb" src="'.$img.'" onerror="this.src=\'../assets/no-image.png\'" alt="'.$name.'"></td>';
echo '  <td><div class="item-cell"><div><div class="item-name">'.$name.'</div><div class="item-sub">'.$sub.'</div></div></div></td>';

echo '  <td class="qty-col"><b>'.$avail.'</b></td>';   // Available
echo '  <td class="qty-col"><b>'.$clean.'</b></td>';   // Cleaning
echo '  <td class="qty-col"><b>'.$damg.'</b></td>';    // Damaged
echo '  <td class="inuse-col"><b>'.$inUse.'</b></td>'; // In use
echo '  <td class="missing-col"><b>'.$missing.'</b></td>'; // 🆕 Missing
$canFix   = ($damg > 0);
$canClean = ($clean > 0);

$fixBtn =
  '<button class="inv-btn fix btn-fix" '.
    'data-id="'.$it['item_id'].'" '.
    'data-name="'.$name.'" '.
    'data-damaged="'.$damg.'" '.
    ($canFix ? '' : 'disabled').'>' .
    '<i class="fa fa-wrench"></i><span>Fix</span>'.
  '</button>';

$cleanBtn =
  '<button class="inv-btn clean btn-clean" '.
    'data-id="'.$it['item_id'].'" '.
    'data-name="'.$name.'" '.
    'data-cleaning="'.$clean.'" '.
    ($canClean ? '' : 'disabled').'>' .
    '<i class="fa fa-tint"></i><span>Clean</span>'.
  '</button>';

$editBtn =
  '<button class="inv-btn edit edit-item" '.
    'data-id="'.$it['item_id'].'" '.
    'data-name="'.$name.'" '.
    'data-qty="'.$qty.'" '.
    'data-inuse="'.$inUse.'" '.
    'data-img="'.$dataImg.'" '.
    'data-isaddon="'.$ia.'" '.
    'data-price="'.$price.'">' .
    '<i class="fa fa-pencil"></i><span>Edit</span>'.
  '</button>';

echo '  <td class="action-col"><div class="inv-actions">'.
        $fixBtn.$cleanBtn.$editBtn.
     '</div></td>';
                }
                $res->free();
              }
            }
            if ($rowsRendered === 0) {
              echo '<tr class="no-inventory"><td colspan="6" style="text-align:center; color:#9aaab5;">No items found.</td></tr>';
            }
          ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ===== MODAL: Assign Gear ===== -->
    <div class="modal" id="assignModal">
      <div class="box">
        <h2>Assign Gear</h2>
        <div class="grid">
          <div class="row"><span class="label">Client:</span><span id="ag_client">—</span></div>
          <div class="row"><span class="label">Booking:</span><span id="ag_booking">—</span></div>
          <div class="row"><span class="label">Package:</span><span id="ag_package">—</span></div>
          <div class="row"><span class="label">Charge days:</span><input id="ag_days" class="number" type="number" min="1" value="2"></div>
          <div class="row"><span class="label">Issue time:</span><input id="ag_issue" class="input" type="datetime-local"></div>
          <div class="row"><span class="label">Estimated Due back:</span><input id="ag_due" class="input" type="datetime-local"></div>
        </div>

        <div class="section">
        <div class="title">Included (FREE)</div>
        <div id="includedList"></div>
        </div>

        <div class="section">
        <div class="title">Add-ons (optional)</div>
        <div id="addonsList"></div>

        <div class="row"><span class="label">Notes:</span>
            <input id="ag_notes" class="input" type="text" placeholder="Optional notes…">
        </div>
        <div class="row" style="justify-content:flex-end">
            <b>Add-ons total:&nbsp;<span id="ag_total">₱0.00</span></b>
        </div>
        </div>

        <div class="actions">
          <button class="btn" id="ag_issue_btn">Issue Gear</button>
          <button class="btn-ghost" id="ag_close_btn">Close</button>
        </div>
        <input type="hidden" id="ag_booking_id">

      </div>
    </div>

<!-- ===== MODAL: Edit/Add Item (simple) ===== -->
<div class="modal" id="itemSimpleModal">

  <div class="box">
    <h2 id="sim_title">Add Item</h2>

    <form id="itemForm" enctype="multipart/form-data">
      <div class="grid form-grid">
        <div>
          <div class="label">Name</div>
          <input id="sim_name" name="name" class="input" type="text" placeholder="e.g., Mask" required>
        </div>

        <div>
          <div class="label">Quantity (Total)</div>
          <input id="sim_qty" name="total_qty" class="number" type="number" min="0" value="0" required>
        </div>

        <!-- Type (FREE vs Add-on) -->
        <div class="full-row">
        <div class="label">Type</div>
        <label style="display:flex;align-items:center;gap:8px;">
            <input id="sim_is_addon" name="is_addon" type="checkbox">
            Treat as Add-on (paid)
        </label>
        </div>

        <!-- Price per day (enable only if Add-on) -->
        <div class="full-row" id="sim_price_wrap">
        <div class="label">Price per day</div>
        <input id="sim_price" name="price_per_day" class="number" type="number" min="0" step="0.01" value="0">
        </div>


        <div class="full-row">
          <div class="label">Photo</div>
          <input id="sim_photo" name="photo" class="input" type="file" accept="image/png,image/jpeg,image/webp">
          <div class="preview-row">
            <img id="sim_preview" class="thumb" src="../assets/no-image.png" alt="Preview">
            <small id="sim_imgpath" class="imgpath">Current: —</small>
          </div>
        </div>

        <div class="full-row">
          <div class="label">Notes</div>
          <input id="sim_notes" name="notes" class="input" type="text" placeholder="Optional">
        </div>

        <input type="hidden" id="sim_item_id" name="item_id">
      </div>

      <div class="actions">
        <button type="button" class="btn" id="sim_save">Save</button>
        <button type="button" class="btn-ghost" id="sim_close">Close</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== MODAL: Return Gear ===== -->
<div class="modal" id="returnModal">
  <div class="box">
    <h2>Mark Gear as Returned</h2>

    <div class="grid">
      <div class="row">
        <span class="label">Client:</span>
        <span id="ret_client">—</span>
      </div>
      <div class="row">
        <span class="label">Booking:</span>
        <span id="ret_booking">—</span>
      </div>
      <div class="row">
        <span class="label">Overall notes:</span>
        <input id="ret_notes" class="input" type="text" placeholder="Describe damage or missing parts (optional)">
      </div>
    </div>

  <div class="section">
  <div class="title">Items</div>

  <!-- SCROLL WRAPPER PARA SA TABLE -->
  <div class="items-scroll">
    <table class="table compact ret-table">
      <thead>
        <tr>
          <th>Item</th>
          <th style="width:90px; text-align:center;">Issued</th>
          <th style="width:120px; text-align:center;">Return Qty</th>
          <th style="width:160px;">Condition</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody id="ret_items"></tbody>
    </table>
  </div>

  <!-- FOOTER: summary + buttons sa iisang linya -->
  <div class="footer-bar">
    <div id="ret_summary">Outstanding: 0 • Issues: 0</div>
    <div class="actions">
      <button class="btn" id="ret_confirm_btn">Confirm Return</button>
      <button class="btn" id="ret_save_partial_btn" style="display:none">Save (Partial)</button>
      <button class="btn-ghost" id="ret_cancel_btn">Cancel</button>
    </div>
  </div>
</div>


    <input type="hidden" id="ret_kit_id">
  </div>
</div>

<div class="modal" id="cleanModal">
  <div class="box">
    <h2>Mark Item as Cleaned</h2>

    <div class="grid">
      <div class="row">
        <span class="label">Item:</span>
        <span id="cl_name">—</span>
      </div>

      <div class="row">
        <span class="label">Quantity:</span>
        <input id="cl_qty" class="number" type="number" min="1" value="1" style="width:120px">
        <small id="cl_hint" style="margin-left:8px;color:#9aaab5;"></small>
      </div>
    </div>

    <div class="actions">
      <button class="btn" id="cl_confirm_btn">Confirm</button>
      <button class="btn-ghost" id="cl_cancel_btn">Cancel</button>
    </div>

    <input type="hidden" id="cl_item_id">
  </div>
</div>

<div class="modal" id="damageModal">
  <div class="box">
    <h2>Mark Item as Damaged</h2>

    <div class="grid">
      <div class="row">
        <span class="label">Item:</span>
        <span id="dg_name">—</span>
      </div>

      <div class="row">
        <span class="label">Source:</span>
        <div>
          <label style="margin-right:12px;">
            <input type="radio" name="dg_source" value="available" checked> Available
          </label>
          <label>
            <input type="radio" name="dg_source" value="cleaning"> Cleaning
          </label>
        </div>
      </div>

      <div class="row">
        <span class="label">Quantity:</span>
        <input id="dg_qty" class="number" type="number" min="1" value="1" style="width:120px">
        <small id="dg_hint" style="margin-left:8px; color:#9aaab5;"></small>
      </div>
    </div>

    <div class="actions">
      <button class="btn" id="dg_confirm_btn">Confirm</button>
      <button class="btn-ghost" id="dg_cancel_btn">Cancel</button>
    </div>

    <input type="hidden" id="dg_item_id">
  </div>
</div>

  </main>
<script>
/* =========================
   Helpers & Modal utilities
========================= */
function showModal(el){
  if (!el) return;
  el.style.removeProperty('display');
  el.classList.remove('closing');
  requestAnimationFrame(()=> el.classList.add('open'));
}
function hideModal(el){
  if (!el) return;
  el.classList.add('closing');
  el.classList.remove('open');
  setTimeout(()=> el.classList.remove('closing'), 300);
}
function pad(n){ return String(n).padStart(2,'0'); }
function nowISO(dt){
  return dt.getFullYear()+'-'+pad(dt.getMonth()+1)+'-'+pad(dt.getDate())+'T'+pad(dt.getHours())+':'+pad(dt.getMinutes());
}
function phpMoney(n){ return '₱ ' + (Number(n)||0).toLocaleString(); }
function esc(s){ return (s??'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

/* =========================
   Global modal behaviors
========================= */
document.addEventListener('DOMContentLoaded', ()=>{
  document.querySelectorAll('.modal').forEach(m=>{
    m.addEventListener('mousedown', (e)=>{ if (e.target === m) hideModal(m); });
  });
});
document.addEventListener('keydown', e=>{
  if (e.key !== 'Escape') return;
  const open = document.querySelector('.modal.open');
  if (open) hideModal(open);
});

/* =========================
   Sidebar
========================= */
document.addEventListener('DOMContentLoaded', function() {
  var sidebar   = document.getElementById('sidebar');
  var hamburger = document.getElementById('hamburger-btn');
  var overlay   = document.getElementById('sidebar-overlay');

  function openSidebar(){
    if (sidebar && overlay){
      sidebar.classList.add('open');
      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  }
  function closeSidebar(){
    if (sidebar && overlay){
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  }
  if (hamburger){
    hamburger.addEventListener('click', function(e){
      e.stopPropagation();
      (sidebar && sidebar.classList.contains('open')) ? closeSidebar() : openSidebar();
    });
  }
  if (overlay){ overlay.addEventListener('click', closeSidebar); }
  window.addEventListener('resize', function(){ if (window.innerWidth > 700) closeSidebar(); });
});

/* =========================
   Tabs + Search
========================= */
document.querySelectorAll('.tab-btn').forEach(function(btn){
  btn.addEventListener('click', function(){
    document.querySelectorAll('.tab-btn').forEach(b=> b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p=> p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');

    var gi = document.getElementById('globalSearch');
    if (gi) gi.value = '';
    runSearch();
    toggleAddBtn();
    if (btn.dataset.tab === 'tab-inventory') renumberInventory();
  });
});

function runSearch(){
  var input = document.getElementById('globalSearch');
  var term = (input ? input.value : '').trim().toLowerCase();
  var activePanel = document.querySelector('.tab-panel.active'); if (!activePanel) return;
  var table = activePanel.querySelector('table'); if (!table) return;

  var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
  var emptyRow = activePanel.querySelector('.no-approved, .no-inventory');
  var shown = 0;

  rows.forEach(function(r){
    if (r.classList.contains('no-approved') || r.classList.contains('no-inventory')) return;
    var txt = r.innerText.toLowerCase();
    var vis = txt.indexOf(term) !== -1;
    r.style.display = vis ? '' : 'none';
    if (vis) shown++;
  });

  if (emptyRow) emptyRow.style.display = shown ? 'none' : '';
  if (activePanel.id === 'tab-inventory') renumberInventory();
}
document.getElementById('globalSearch').addEventListener('input', runSearch);

/* =========================
   Assign Gear (dynamic)
========================= */
var assignModal  = document.getElementById('assignModal');
var agClient     = document.getElementById('ag_client');
var agBooking    = document.getElementById('ag_booking');
var agPackage    = document.getElementById('ag_package');
var agDays       = document.getElementById('ag_days');
var agIssue      = document.getElementById('ag_issue');
var agDue        = document.getElementById('ag_due');
var agTotal      = document.getElementById('ag_total');
var agBookingId  = document.getElementById('ag_booking_id');

/* create/fix containers if missing, and clear old static rows */
function ensureAssignContainers(){
  const sections = assignModal.querySelectorAll('.section');
  const includedSection = sections[0];
  const addonsSection   = sections[1];

  // Included
  if (includedSection){
    includedSection.querySelectorAll('.row').forEach(n=>n.remove());
    if (!document.getElementById('includedList')){
      const div = document.createElement('div');
      div.id = 'includedList';
      includedSection.appendChild(div);
    }
  }
  // Add-ons
  if (addonsSection){
    addonsSection.querySelectorAll('.addon').forEach(inp => inp.closest('.row')?.remove());
    if (!document.getElementById('addonsList')){
      const list = document.createElement('div');
      list.id = 'addonsList';
      const notes = document.getElementById('ag_notes');
      const notesRow = notes ? notes.closest('.row') : null;
      addonsSection.insertBefore(list, notesRow || addonsSection.lastChild);
    }
  }
}

async function loadAssignOptions(bookingId){
  const res = await fetch('assign_options.php?booking_id=' + encodeURIComponent(bookingId||0));
  const data = await res.json();
  if (!res.ok || !data.success) throw new Error(data.message || 'Failed to load options');
  return data;
}

function renderIncluded(list){
  const box = document.getElementById('includedList');
  box.innerHTML = '';
  if (!list || !list.length){
    box.innerHTML = `<div class="row" style="color:#9aaab5">No free items.</div>`;
    return;
  }
  list.forEach(it=>{
    const row = document.createElement('div');
    row.className = 'row';
    row.dataset.itemId = it.item_id;
    row.dataset.qty    = it.qty;
    row.innerHTML = `
      <span>${esc(it.name)} <small class="chip" title="Available now">${it.available} available</small></span>
      <b>×${it.qty}</b>`;
    box.appendChild(row);
  });
}

function bindAddonInputsForTotal(){
  document.querySelectorAll('#addonsList .addon, #ag_days').forEach(inp=>{
    inp.addEventListener('input', ()=>{
      if (inp.classList.contains('addon')){
        const max = parseInt(inp.getAttribute('max')||'0',10);
        let v = parseInt(inp.value||'0',10)||0;
        if (v < 0) v = 0;
        if (v > max) v = max;
        inp.value = v;
      }
      recalcAddons();
    });
  });
}

function renderAddons(list){
  const box = document.getElementById('addonsList');
  box.innerHTML = '';
  (list||[]).forEach(it=>{
    const row = document.createElement('div');
    row.className = 'row';
    const disabled = it.available <= 0 ? 'disabled' : '';
    row.innerHTML = `
      <div>${esc(it.name)} — ${phpMoney(it.price_per_day)}/day
        <small class="chip">${it.available} available</small>
      </div>
      <div class="qtywrap">
        Qty
        <input class="number addon"
               type="number" min="0" max="${it.available}"
               value="0" ${disabled}
               data-item-id="${it.item_id}"
               data-rate="${it.price_per_day}"
               style="width:90px">
      </div>`;
    box.appendChild(row);
  });
  bindAddonInputsForTotal();
}

function recalcAddons(){
  var days  = Math.max(1, parseInt(agDays.value || '1', 10));
  var total = 0;
  document.querySelectorAll('#addonsList .addon').forEach(inp=>{
    var qty  = Math.max(0, parseInt(inp.value || '0', 10));
    var rate = parseFloat(inp.dataset.rate || '0');
    total += qty * rate * days;
  });
  agTotal.textContent = phpMoney(total);
}

async function openAssign(meta){
  agClient.textContent   = meta.client || '—';
  agBooking.textContent  = meta.booking || '—';
  agPackage.textContent  = meta.package || '—';
  agDays.value           = meta.days || 1;
  agBookingId.value      = meta.booking_id || '';

  // default times: now + estimated due (3h after)
  var now = new Date();
  var due = new Date(now.getTime() + 3*60*60*1000);
  agIssue.value = nowISO(now);
  agDue.value   = nowISO(due);

  ensureAssignContainers();

  try{
    const data = await loadAssignOptions(meta.booking_id);
    renderIncluded(data.included);
    renderAddons(data.addons);
    recalcAddons();
    showModal(assignModal);
  }catch(err){
    Swal.fire({icon:'error', title:'Load failed', text:String(err.message||err)});
  }
}
function closeAssign(){ hideModal(assignModal); }

document.querySelectorAll('.btn-assign').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    openAssign({
      client:     btn.dataset.client,
      booking:    btn.dataset.booking,
      booking_id: btn.dataset.bookingId,
      package:    btn.dataset.package,
      days:       btn.dataset.days
    });
  });
});
document.getElementById('ag_close_btn').addEventListener('click', closeAssign);

/* Save (Issue Gear) */
document.getElementById('ag_issue_btn').addEventListener('click', async () => {
  const btn = document.getElementById('ag_issue_btn');
  btn.disabled = true;
  const oldLabel = btn.textContent;
  btn.textContent = 'Saving…';

  // FREE
  const included = Array.from(document.querySelectorAll('#includedList .row')).map(r=>({
    item_id: parseInt(r.dataset.itemId,10),
    qty:     parseInt(r.dataset.qty,10) || 0,
    is_addon: 0,
    price_per_day: 0
  }));

  // ADD-ONS
  const addons = Array.from(document.querySelectorAll('#addonsList .addon')).map(inp=>({
    item_id: parseInt(inp.dataset.itemId,10),
    qty:     parseInt(inp.value||'0',10) || 0,
    is_addon: 1,
    price_per_day: parseFloat(inp.dataset.rate||'0')
  })).filter(x=>x.qty>0);

  const payload = {
    booking_id:   parseInt(agBookingId.value||'0',10),
    days_charged: parseInt(agDays.value||'1',10),
    issue_time:   agIssue.value,
    due_back:     agDue.value,
    notes:        (document.getElementById('ag_notes')?.value || '').trim(),
    items:        included.concat(addons),
    addons
  };

  try {
    const res  = await fetch('issue_gear.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.message || 'Save failed');

    // SUCCESS: isara muna ang modal para hindi takpan ang Swal
    hideModal(assignModal);
    await new Promise(r => setTimeout(r, 320)); // hintay sa close animation

    await Swal.fire({
      icon:'success',
      title:'Saved',
      text:'Gear issued.',
      confirmButtonColor:'#21ba6e'
    });

    location.reload();
  } catch (err) {
    // Kapag may error, huwag isara ang modal para ma-edit pa;
    // lalabas ang Swal sa ibabaw dahil sa CSS z-index fix.
    await Swal.fire({
      icon:'error',
      title:'Save failed',
      text:String(err.message || err)
    });
  } finally {
    btn.disabled = false;
    btn.textContent = oldLabel;
  }
});

/* =========================
   Inventory table utilities
========================= */
function renumberInventory(){
  var rows = document.querySelectorAll('#inventoryTable tbody tr');
  var i = 1;
  rows.forEach(function(r){
    if (r.classList.contains('no-inventory')) return;
    if (r.style.display === 'none') return;
    var cell = r.querySelector('.num-col');
    if (cell) cell.textContent = i++;
  });
}

/* =========================
   Item Add/Edit Modal
========================= */
var itemSimpleModal = document.getElementById('itemSimpleModal');
var simTitle  = document.getElementById('sim_title');
var simItemId = document.getElementById('sim_item_id');
var simName   = document.getElementById('sim_name');
var simQty    = document.getElementById('sim_qty');
var simPhoto  = document.getElementById('sim_photo');
var simPrev   = document.getElementById('sim_preview');
var simImg    = document.getElementById('sim_imgpath');
var simNotes  = document.getElementById('sim_notes');
var simIsAddon   = document.getElementById('sim_is_addon');
var simPrice     = document.getElementById('sim_price');
var simPriceWrap = document.getElementById('sim_price_wrap');

function refreshPriceEnable(){
  const on = !!(simIsAddon && simIsAddon.checked);
  if (simPrice)     simPrice.disabled = !on;
  if (simPriceWrap) simPriceWrap.style.opacity = on ? '1' : '.5';
}
if (simIsAddon) simIsAddon.addEventListener('change', refreshPriceEnable);

function resetItemForm(){
  simItemId.value = '';
  simName.value   = '';
  simQty.value    = 0;
  simNotes.value  = '';
  if (simIsAddon) simIsAddon.checked = false;
  if (simPrice)   simPrice.value = '0';
  refreshPriceEnable();
  simPrev.src     = '../assets/no-image.png';
  simImg.textContent = 'Current: —';
}

function openSimpleItemModal(row){
  simTitle.textContent = 'Edit Item';

  // always RESET first to avoid carry-over
  resetItemForm();

  const btn = row.querySelector('.edit-item');

  // basic fields
  simItemId.value = btn?.dataset.id || '';
  simName.value   = btn?.dataset.name || row.querySelector('.item-name')?.textContent || '';
  simQty.value    = parseInt(btn?.dataset.qty || row.querySelector('.qty-col')?.innerText || '0', 10) || 0;

  // image
  var raw = (btn && btn.dataset.img) ? btn.dataset.img : '../assets/no-image.png';
  var img = raw.indexOf('..') === 0 ? raw : ('../' + raw);
  simPrev.src = img;
  simImg.textContent = 'Current: ' + ((btn && btn.dataset.img) ? btn.dataset.img : '—');

  // type + price (from data-*)
  const isAddon = (btn && ('isaddon' in btn.dataset)) ? (btn.dataset.isaddon === '1') : false;
  const price   = (btn && ('price'   in btn.dataset)) ? (parseFloat(btn.dataset.price) || 0) : 0;
  if (simIsAddon) simIsAddon.checked = isAddon;
  if (simPrice)   simPrice.value     = isAddon ? price : 0;
  refreshPriceEnable();

  showModal(itemSimpleModal);
}
function openSimpleItemModalForAdd(){
  simTitle.textContent = 'Add Item';
  resetItemForm();
  showModal(itemSimpleModal);
}
function closeSimpleItemModal(){ hideModal(itemSimpleModal); }
document.getElementById('sim_close').onclick = closeSimpleItemModal;

/* Live preview for photo */
simPhoto.addEventListener('change', function(e){
  var f = e.target.files && e.target.files[0];
  if (!f) return;
  simPrev.src = URL.createObjectURL(f);
  simImg.textContent = 'Selected file: ' + f.name;
});

/* Delegated Edit click */
document.querySelector('#inventoryTable tbody').addEventListener('click', function(e){
  var btn = e.target.closest('.edit-item'); 
  if (!btn) return;
  openSimpleItemModal(btn.closest('tr'));
});

/* Add button visibility + click */
function toggleAddBtn(){
  var btn = document.getElementById('addItemBtn');
  var active = document.querySelector('.tab-panel.active');
  if (!btn) return;
  btn.style.display = (active && active.id === 'tab-inventory') ? 'inline-flex' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleAddBtn);
document.getElementById('addItemBtn').addEventListener('click', openSimpleItemModalForAdd);

/* Initial state */
document.addEventListener('DOMContentLoaded', function(){
  renumberInventory();
  runSearch();
});

/* =========================
   AJAX save item
========================= */
async function saveItemToServer(){
  var fd = new FormData();
  fd.append('item_id',  simItemId.value || '');
  fd.append('name',     (simName.value || '').trim());
  fd.append('total_qty',simQty.value || 0);
  fd.append('notes',    (simNotes.value || '').trim());
  if (simPhoto.files && simPhoto.files[0]) fd.append('photo', simPhoto.files[0]);

  fd.append('is_addon', (simIsAddon && simIsAddon.checked) ? 1 : 0);
  fd.append('price_per_day', (simIsAddon && simIsAddon.checked) ? (simPrice.value || 0) : 0);

  var resp = await fetch('inventory_item_save.php', { method:'POST', body: fd });
  var data = await resp.json();
  if (!resp.ok || !data.success) throw new Error(data.message || 'Save failed');
  return data;
}

document.getElementById('sim_save').onclick = async function(){
  try{
    if (!simName.value || !simName.value.trim()){ simName.focus(); return; }
    var result = await saveItemToServer();
    var item = result.item;

    var existingBtn = document.querySelector('#inventoryTable .edit-item[data-id="'+ item.item_id +'"]');
    if (existingBtn){
      var row = existingBtn.closest('tr');

      // Update name/qty/image
      row.querySelector('.item-name').textContent = item.name;
      row.querySelector('.qty-col').innerHTML = '<b>'+ item.total_qty +'</b>';
      var imgEl = row.querySelector('.thumb');
      if (imgEl) imgEl.src = item.image_admin_url || ('../' + (item.image_path || 'assets/no-image.png'));

      // Update subtitle + data-*
      var isAddon = Number(item.is_addon || 0);
      var price   = Number(item.price_per_day || 0);
      var sub = isAddon
        ? ('Add-on • ₱' + price.toLocaleString(undefined,{minimumFractionDigits:2}) + '/day')
        : 'Included (free)';
      row.querySelector('.item-sub').textContent = sub;

      existingBtn.dataset.name    = item.name;
      existingBtn.dataset.qty     = item.total_qty;
      existingBtn.dataset.img     = item.image_path || '';
      existingBtn.dataset.isaddon = isAddon ? '1' : '0';
      existingBtn.dataset.price   = price;
    } else {
      appendRow(item);
    }

    closeSimpleItemModal();
    renumberInventory();
    Swal.fire({icon:'success', title:'Saved', text:'Item saved successfully.'});
  }catch(err){
    console.error(err);
    Swal.fire({icon:'error', title:'Save failed', text: err.message});
  }
};

function appendRow(item){
  var tbody = document.querySelector('#inventoryTable tbody');
  var tr = document.createElement('tr');
  var imgSrc = item.image_admin_url || ('../' + (item.image_path || 'assets/no-image.png'));
  var isAddon = Number(item.is_addon || 0);
  var price   = Number(item.price_per_day || 0);
  var sub = isAddon ? ('Add-on • ₱' + price.toLocaleString(undefined,{minimumFractionDigits:2}) + '/day') : 'Included (free)';

  tr.innerHTML =
    '<td class="num-col"></td>'+
    '<td class="pic-col"><img class="thumb" src="'+imgSrc+'" onerror="this.src=\'../assets/no-image.png\'" alt="'+esc(item.name)+'"></td>'+
    '<td><div class="item-cell"><div><div class="item-name">'+esc(item.name)+'</div><div class="item-sub">'+sub+'</div></div></div></td>'+
    '<td class="qty-col"><b>'+item.total_qty+'</b></td>'+
    '<td class="inuse-col"><b>0</b></td>'+
    '<td class="action-col">'+
      '<button class="btn-outline edit-item" '+
              'data-id="'+item.item_id+'" '+
              'data-name="'+esc(item.name)+'" '+
              'data-qty="'+item.total_qty+'" '+
              'data-img="'+(item.image_path || '')+'" '+
              'data-isaddon="'+(isAddon ? '1':'0')+'" '+
              'data-price="'+price+'">Edit</button>'+
    '</td>';
  var marker = tbody.querySelector('.no-inventory');
  tbody.insertBefore(tr, marker || null);
}

/* =============== Gear Returns: load & render =============== */

let returnsLoadedOnce = false;

async function loadReturns(scope='outstanding'){
  const res = await fetch('returns_list.php?scope=' + encodeURIComponent(scope));
  const data = await res.json();
  if (!res.ok || !data.success) throw new Error(data.message || 'Failed to load returns');
  renderReturns(data.kits || []);
}

function formatDT(s){
  if (!s) return '—';
  // "YYYY-MM-DD HH:MM:SS" -> local format
  const dt = new Date(s.replace(' ', 'T'));
  return isNaN(dt) ? s : dt.toLocaleString();
}
// === Gear HTML with item tags (SSR-like) ===
function gearLine(it){
  const name = esc(it.item_name || '');
  const qty  = Number(it.qty || 0);
  const add  = Number(it.is_addon) ? ' <span class="addon">(add-on)</span>' : '';

  // kung wala pang per-item fields sa JSON, huwag maglagay ng tags
  const hasDetail = ('returned_qty' in it) || ('condition' in it) || ('damage_notes' in it);
  if (!hasDetail) return `${name} × ${qty}${add}`;

  const ret  = Number(it.returned_qty || 0);
  const out  = Math.max(0, qty - ret);
  const cond = String(it.condition || '').toLowerCase();
  const note = it.damage_notes ? ' — ' + esc(it.damage_notes) : '';

  const tags = [];
  if (out > 0)            tags.push('<span class="chip tag-outstanding">Outstanding: '+ out +'</span>');
  if (cond === 'damaged') tags.push('<span class="chip tag-damaged">Damaged'+ note +'</span>');
  else if (cond === 'missing')
                          tags.push('<span class="chip tag-missing">Missing</span>');
  else if (out === 0)     tags.push('<span class="chip tag-good">Good</span>');

  return `${name} × ${qty}${add} ${tags.join(' ')}`;
}
function gearHTML(items){
  if (!items || !items.length) return '—';
  return items.map(gearLine).join('<br>');
}
// Condition chip for the column
function condChip(c){
  const v = String(c||'').toLowerCase();
  if (v === 'good') return '<span class="chip status-ok">Good</span>';
  if (v === 'bad')  return '<span class="chip status-no">Bad</span>';
  return '—';
}
const returnsCache = Object.create(null);
function renderReturns(kits){
    
  const tbody = document.querySelector('#returnsTable tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (!kits.length){
    tbody.innerHTML = `
      <tr class="no-returns">
        <td colspan="6" style="text-align:center; color:#9aaab5;">
          No issued kits.
        </td>
      </tr>`;
    return;
  }

  kits.forEach(k => {
    returnsCache[k.kit_id] = k;   // cache the kit with its items for the modal
    const tr = document.createElement('tr');
    const statusChip = (k.status === 'returned')
      ? '<span class="chip status-ok">Returned</span>'
      : '<span class="chip status-no">Issued</span>';

    tr.innerHTML = `
  <td><b>${esc(k.full_name)}</b><br><small>${esc(k.booking_code)}</small></td>
  <td class="gear-used">${gearHTML(k.items)}</td>
  <td>${formatDT(k.issue_time)}</td>
  <td>${condChip(k.overall_condition)}</td>
  <td>${statusChip}</td>
  <td>
    ${k.status === 'issued' || k.status === 'overdue' || k.status === 'partial'
      ? `<button class="btn btn-return"
           data-kit-id="${k.kit_id}"
           data-client="${esc(k.full_name)}"
           data-booking="${esc(k.booking_code)}">Mark Returned</button>`
      : '—'}
  </td>
`;

    tbody.appendChild(tr);
  });

  // (Wala pa tayong handler ng "Mark Returned" – Step 3 natin 'yon)
}

/* Load the list the first time the tab is shown */
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    if (btn.dataset.tab === 'tab-returns' && !returnsLoadedOnce){
      returnsLoadedOnce = true;
      loadReturns('all').catch(err=>{
        Swal.fire({icon:'error', title:'Load failed', text:String(err.message||err)});
      });
    }
  });
});
// ===== Return modal refs
var returnModal = document.getElementById('returnModal');
var retKitId    = document.getElementById('ret_kit_id');
var retClient   = document.getElementById('ret_client');
var retBooking  = document.getElementById('ret_booking');
var retCond     = document.getElementById('ret_condition');
var retNotes    = document.getElementById('ret_notes');
function clamp(n, min, max){ n = Number(n||0); return Math.max(min, Math.min(max, n)); }

function fillReturnRows(kit){
  const tbody = document.getElementById('ret_items');
  tbody.innerHTML = '';
  (kit.items || []).forEach(it => {
    const tr = document.createElement('tr');
    tr.dataset.kitItemId = it.kit_item_id || '';
    tr.dataset.qty = it.qty;

    const add = Number(it.is_addon) ? ' <span class="addon">(add-on)</span>' : '';
    const cond = String(it.condition || 'good').toLowerCase();
    const ret  = Number(it.returned_qty || 0);

    tr.innerHTML = `
      <td>${esc(it.item_name)}${add}</td>
      <td style="text-align:center;">${it.qty}</td>
      <td><input class="number ret-qty" type="number" min="0" max="${it.qty}" value="${ret}"></td>
      <td>
        <select class="input ret-cond">
          <option value="good"${cond==='good'?' selected':''}>Good</option>
          <option value="damaged"${cond==='damaged'?' selected':''}>Damaged</option>
          <option value="missing"${cond==='missing'?' selected':''}>Missing</option>
        </select>
      </td>
      <td><input class="input ret-note" type="text" value="${esc(it.damage_notes||'')}"></td>
    `;
    tbody.appendChild(tr);
  });

  // Bind events
  tbody.querySelectorAll('tr').forEach(tr=>{
    const max = Number(tr.dataset.qty||0);
    const qty = tr.querySelector('.ret-qty');
    const sel = tr.querySelector('.ret-cond');
    qty.addEventListener('input', ()=>{
      qty.value = clamp(qty.value, 0, max);
      recalcReturnSummary();
    });
    sel.addEventListener('change', ()=>{
      if (sel.value === 'missing'){
        qty.value = 0;
        qty.disabled = true;
      }else{
        qty.disabled = false;
      }
      recalcReturnSummary();
    });
    // initialize disable if missing
    if (sel.value === 'missing'){ qty.value = 0; qty.disabled = true; }
  });

  recalcReturnSummary();
}

function recalcReturnSummary(){
  let outstanding = 0, issues = 0;
  document.querySelectorAll('#ret_items tr').forEach(tr=>{
    const max = Number(tr.dataset.qty||0);
    const qty = Number(tr.querySelector('.ret-qty').value||0);
    const cond= tr.querySelector('.ret-cond').value;
    outstanding += Math.max(0, max - qty);
    if (cond === 'damaged' || cond === 'missing') issues++;
  });
  const sumEl = document.getElementById('ret_summary');
  if (sumEl) sumEl.textContent = `Outstanding: ${outstanding} • Issues: ${issues}`;

  // Toggle buttons
  const savePartial = document.getElementById('ret_save_partial_btn');
  const confirmBtn  = document.getElementById('ret_confirm_btn');
  if (outstanding > 0){
    if (savePartial) savePartial.style.display = '';
    if (confirmBtn)  confirmBtn.disabled = true;
  }else{
    if (savePartial) savePartial.style.display = 'none';
    if (confirmBtn)  confirmBtn.disabled = false;
  }
}

function collectReturnPayload(){
  const kit_id = parseInt(document.getElementById('ret_kit_id').value||'0',10);
  const notes  = (document.getElementById('ret_notes').value||'').trim();

  const items = Array.from(document.querySelectorAll('#ret_items tr')).map(tr=>{
    return {
      kit_item_id: parseInt(tr.dataset.kitItemId||'0',10),
      returned_qty: parseInt(tr.querySelector('.ret-qty').value||'0',10),
      condition: tr.querySelector('.ret-cond').value,
      damage_notes: (tr.querySelector('.ret-note').value||'').trim()
    };
  });

  // derive overall_condition
  const issues = items.some(it => it.condition==='damaged' || it.condition==='missing');
  const overall_condition = issues ? 'bad' : 'good';

  return { kit_id, notes, overall_condition, items };
}

function openReturnModal(meta){
  retKitId.value        = meta.kit_id || '';
  retClient.textContent = meta.client || '—';
  retBooking.textContent= meta.booking || '—';
  retNotes.value        = '';

  const kit = returnsCache[String(meta.kit_id)];
  fillReturnRows(kit || {items:[]});

  showModal(returnModal);
}

document.getElementById('ret_cancel_btn').addEventListener('click', ()=> hideModal(returnModal));
// Global delegated click para sa mga "Mark Returned" button
document.addEventListener('click', function(e){
  const btn = e.target.closest('.btn-return');
  if (!btn) return;
  openReturnModal({
    kit_id:  btn.dataset.kitId,
    client:  btn.dataset.client,
    booking: btn.dataset.booking
  });
});
document.getElementById('ret_confirm_btn').addEventListener('click', async ()=>{
  const btn = document.getElementById('ret_confirm_btn');
  btn.disabled = true; const old = btn.textContent; btn.textContent = 'Saving…';

  const payload = collectReturnPayload();

  try{
    const res  = await fetch('return_gear.php', {
      method:'POST',
      headers:{ 'Content-Type':'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.message || 'Return failed');

    hideModal(returnModal);
    await new Promise(r=>setTimeout(r,320));
    await Swal.fire({ icon:'success', title:'Returned', text:'Gear marked as returned.', confirmButtonColor:'#21ba6e' });
    location.reload();
  }catch(err){
    await Swal.fire({ icon:'error', title:'Return failed', text:String(err.message||err) });
  }finally{
    btn.disabled = false; btn.textContent = old;
  }
});

document.getElementById('ret_save_partial_btn').addEventListener('click', async ()=>{
  const btn = document.getElementById('ret_save_partial_btn');
  btn.disabled = true; const old = btn.textContent; btn.textContent = 'Saving…';

  const payload = collectReturnPayload(); // same payload; backend sets status=partial if may outstanding

  try{
    const res  = await fetch('return_gear.php', {
      method:'POST',
      headers:{ 'Content-Type':'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.message || 'Save failed');

    hideModal(returnModal);
    await new Promise(r=>setTimeout(r,320));
    await Swal.fire({ icon:'success', title:'Saved', text:'Partial return recorded.', confirmButtonColor:'#21ba6e' });
    location.reload();
  }catch(err){
    await Swal.fire({ icon:'error', title:'Save failed', text:String(err.message||err) });
  }finally{
    btn.disabled = false; btn.textContent = old;
  }
});

document.addEventListener('DOMContentLoaded', ()=>{
  const btn = document.querySelector('.tab-btn[data-tab="tab-returns"]');
  const pane = document.getElementById('tab-returns');
  if (pane && btn && pane.classList.contains('active') && !returnsLoadedOnce){
    returnsLoadedOnce = true;
    loadReturns('all').catch(err=>{
      Swal.fire({icon:'error', title:'Load failed', text:String(err.message||err)});
    });
  }
});

/* =========================
   Mark Cleaned (modal + ajax)
========================= */
const cleanModal  = document.getElementById('cleanModal');
const clItem      = document.getElementById('cl_item');
const clInclean   = document.getElementById('cl_inclean');
const clQty       = document.getElementById('cl_qty');
const clItemId    = document.getElementById('cl_item_id');

function openCleanModal(meta){
  clItem.textContent   = meta.name || '—';
  clInclean.textContent= String(meta.cleaning || 0);
  clItemId.value       = meta.item_id || '';
  const max = Math.max(1, Number(meta.cleaning || 0));
  clQty.value = 1;
  clQty.min   = 1;
  clQty.max   = max;
  showModal(cleanModal);
}

document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.btn-clean');
  if (!btn) return;
  openCleanModal({
    item_id:  btn.dataset.id,
    name:     btn.dataset.name,
    cleaning: Number(btn.dataset.cleaning || 0)
  });
});

document.getElementById('cl_cancel_btn').addEventListener('click', ()=> hideModal(cleanModal));

document.getElementById('cl_confirm_btn').addEventListener('click', async ()=>{
  const btn = document.getElementById('cl_confirm_btn');
  const item_id = parseInt(clItemId.value||'0',10);
  let qty = parseInt(clQty.value||'0',10);
  const max = parseInt(clQty.max||'1',10);

  if (!item_id){ Swal.fire({icon:'error', title:'Oops', text:'Missing item id.'}); return; }
  if (qty < 1) qty = 1;
  if (qty > max) qty = max;

  btn.disabled = true; const old = btn.textContent; btn.textContent = 'Saving…';

  try{
    const res = await fetch('clean_item.php', {
      method:'POST',
      headers:{ 'Content-Type':'application/json' },
      body: JSON.stringify({ item_id, qty })
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.message || 'Update failed');

    hideModal(cleanModal);
    await new Promise(r=>setTimeout(r,320));
    await Swal.fire({ icon:'success', title:'Done', text:'Items marked as cleaned.', confirmButtonColor:'#21ba6e' });

    // Simplest: reload to recompute Available/Cleaning counts
    location.reload();
  }catch(err){
    await Swal.fire({ icon:'error', title:'Failed', text:String(err.message||err) });
  }finally{
    btn.disabled = false; btn.textContent = old;
  }
});
// Delegated click for "Clean" button
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-clean');
  if (!btn) return;

  const itemId = parseInt(btn.dataset.id, 10);
  const name   = btn.dataset.name || 'item';
  const qty    = parseInt(btn.dataset.cleaning || '0', 10);

  if (!itemId || qty <= 0) return;

  const ok = await Swal.fire({
    icon: 'question',
    title: 'Mark as cleaned?',
    text: `Move ${qty} “${name}” from Cleaning back to Available?`,
    showCancelButton: true,
    confirmButtonText: 'Yes, cleaned',
    cancelButtonText: 'Cancel'
  });

  if (!ok.isConfirmed) return;

  try {
    const resp = await fetch('clean_item.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_id: itemId, quantity: qty })
    });
    const data = await resp.json();
    if (!resp.ok || !data.success) throw new Error(data.message || 'Request failed');

    await Swal.fire({ icon: 'success', title: 'Updated', text: 'Item(s) moved to Available.' });
    location.reload(); // simplest refresh; later pwede natin gawing in-place update
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Update failed', text: String(err.message || err) });
  }
});

// Damage modal refs
const dmgModal = document.getElementById('damageModal');
const dgName   = document.getElementById('dg_name');
const dgQty    = document.getElementById('dg_qty');
const dgHint   = document.getElementById('dg_hint');
const dgItemId = document.getElementById('dg_item_id');

function clamp(n, min, max){ n = Number(n||0); return Math.max(min, Math.min(max, n)); }
function currentSource(){ return (document.querySelector('input[name="dg_source"]:checked')?.value) || 'available'; }

let dgAvailable = 0;
let dgCleaning  = 0;

function updateDgLimits(){
  const src = currentSource();
  const max = src === 'cleaning' ? dgCleaning : dgAvailable;
  dgQty.min = 1;
  dgQty.max = Math.max(1, max);
  dgQty.value = clamp(dgQty.value, 1, max);
  dgHint.textContent = `(max ${max} from ${src})`;
}

document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.btn-damage');
  if (!btn) return;

  dgItemId.value = btn.dataset.id || '';
  dgName.textContent = btn.dataset.name || 'item';

  dgAvailable = parseInt(btn.dataset.available || '0', 10);
  dgCleaning  = parseInt(btn.dataset.cleaning || '0', 10);

  // Default source: prefer 'available' if >0, else 'cleaning'
  const prefer = dgAvailable > 0 ? 'available' : 'cleaning';
  document.querySelectorAll('input[name="dg_source"]').forEach(r=>{
    r.checked = (r.value === prefer);
  });

  updateDgLimits();
  showModal(dmgModal);
});

document.getElementById('dg_cancel_btn').addEventListener('click', ()=> hideModal(dmgModal));
document.querySelectorAll('input[name="dg_source"]').forEach(r=>{
  r.addEventListener('change', updateDgLimits);
});
dgQty.addEventListener('input', updateDgLimits);

document.getElementById('dg_confirm_btn').addEventListener('click', async ()=>{
  const item_id = parseInt(dgItemId.value||'0',10);
  const qty     = parseInt(dgQty.value||'0',10);
  const source  = currentSource();

  if (!item_id || qty <= 0){
    Swal.fire({icon:'error', title:'Invalid', text:'Check item and quantity.'});
    return;
  }

  const btn = document.getElementById('dg_confirm_btn');
  btn.disabled = true; const old = btn.textContent; btn.textContent = 'Saving…';

  try{
    const resp = await fetch('damage_item.php', {
      method:'POST',
      headers:{ 'Content-Type':'application/json' },
      body: JSON.stringify({ item_id, quantity: qty, source })
    });
    const data = await resp.json();
    if (!resp.ok || !data.success) throw new Error(data.message || 'Update failed');

    hideModal(dmgModal);
    await new Promise(r=>setTimeout(r,320));
    await Swal.fire({icon:'success', title:'Updated', text:'Item(s) moved to Damaged.'});
    location.reload();
  }catch(err){
    Swal.fire({icon:'error', title:'Update failed', text:String(err.message||err)});
  }finally{
    btn.disabled = false; btn.textContent = old;
  }
});
// FIX: damaged -> cleaning
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-fix');
  if (!btn) return;

  const itemId  = parseInt(btn.dataset.id, 10);
  const name    = btn.dataset.name || 'item';
  const damaged = parseInt(btn.dataset.damaged || '0', 10);
  if (!itemId || damaged <= 0) return;

  // optional: piliin ilan ang ifi-fix (default = lahat ng damaged)
  const ask = await Swal.fire({
    title: 'Fix damaged items?',
    html: `Move damaged <b>${name}</b> to Cleaning.`,
    input: 'number',
    inputValue: damaged,
    inputLabel: 'Quantity to fix',
    inputAttributes: { min: 1, max: damaged, step: 1 },
    showCancelButton: true,
    confirmButtonText: 'Fix',
    preConfirm: (v) => {
      const n = parseInt(v, 10);
      if (isNaN(n) || n < 1 || n > damaged) {
        Swal.showValidationMessage(`Enter 1–${damaged}`);
        return false;
      }
      return n;
    }
  });
  if (!ask.isConfirmed) return;

  const qty = parseInt(ask.value, 10);

  try {
    const resp = await fetch('fix_damage.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_id: itemId, qty })
    });
    const data = await resp.json();
    if (!resp.ok || !data.success) throw new Error(data.message || 'Request failed');

    // ✅ in-place UI update gamit ang counts galing sa backend
    const row = btn.closest('tr');
    if (row && data.counts) {
      // columns order mo: Available • Cleaning • Damaged • In use
      const tds = row.querySelectorAll('td');
      // hanapin via class para safe:
      row.querySelectorAll('.qty-col')[0].innerHTML = `<b>${data.counts.available}</b>`; // Available
      row.querySelectorAll('.qty-col')[1].innerHTML = `<b>${data.counts.cleaning}</b>`;  // Cleaning
      row.querySelectorAll('.qty-col')[2].innerHTML = `<b>${data.counts.damaged}</b>`;   // Damaged
      row.querySelector('.inuse-col').innerHTML     = `<b>${data.counts.in_use}</b>`;     // In use

      // update data-damaged at disable kung zero na
      btn.dataset.damaged = String(data.counts.damaged || 0);
      if ((data.counts.damaged || 0) <= 0) btn.setAttribute('disabled','');
      else btn.removeAttribute('disabled');
    }

    await Swal.fire({ icon:'success', title:'Updated', text:`Fixed ${qty} item(s).` });
  } catch (err) {
    Swal.fire({ icon:'error', title:'Update failed', text:String(err.message || err) });
  }
});

</script>

</body>
</html>
