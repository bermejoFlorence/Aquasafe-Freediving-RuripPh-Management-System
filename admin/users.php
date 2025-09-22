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

// --- Fetch Total Clients (users with role 'client') ---
include '../db_connect.php';
if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle) {
    return 0 === strncmp($haystack, $needle, strlen($needle));
  }
}

/* Convert DB path to a web path from /admin/ */
function web_path_from_admin($p) {
  if (!$p) return '../assets/no-avatar.png';      // fallback
  if (preg_match('#^https?://#', $p) || str_starts_with($p, '/')) return $p; // absolute
  if (substr($p, 0, 8) === 'uploads/') return '../' . $p;                    // already in uploads/
  return '../uploads/' . ltrim($p, '/');                                     // bare filename
}
$total_clients = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS total_clients FROM user WHERE role = 'client'")) {
    $stmt->execute();
    $stmt->bind_result($total_clients);
    $stmt->fetch();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Aquasafe RuripPh • Users</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="styles/style.css">

  <style>
    :root{
      --teal:#1e8fa2; --ink:#186070; --muted:#6b8b97; --line:#e3f6fc;
    }

    /* Page chrome */
    .page-title{ width:98%; margin:14px auto 8px; color:var(--teal); font-size:2rem; font-weight:800; }
    .sub-bar{
      width:98%; margin:0 auto 14px;
      display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
    }
    .filters{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .filters .pill{
      display:inline-flex; align-items:center; gap:8px; background:#fff; border:1.5px solid #c8eafd;
      border-radius:10px; padding:8px 10px; color:var(--ink); font-weight:700;
    }
    .filters select, .filters input[type="number"]{
      border:1.5px solid #c8eafd; border-radius:8px; padding:6px 8px; outline:none; color:var(--ink);
    }
    .filters select:focus, .filters input:focus{ border-color:var(--teal); box-shadow:0 0 0 2px #1e8fa220; }

    .searchbox{ display:flex; gap:8px; align-items:center; }
    .searchbox input{
      min-width:260px; padding:9px 14px; border:1.5px solid #c8eafd; border-radius:10px;
      color:var(--ink); box-shadow:0 2px 8px #b9eafc22;
    }
    .searchbox input:focus{ outline:0; border-color:var(--teal); }

    /* Table */
    .table-scroll{ width:98%; margin:0 auto 30px; overflow:auto; }
    .table{ width:100%; border-collapse:separate; border-spacing:0; min-width:1080px; }
    .table th{
      background:var(--teal); color:#fff; padding:12px; text-align:left; font-weight:800; white-space:nowrap;
      position:sticky; top:0; z-index:1;
    }
    .table td{ background:#fffffffb; border-bottom:2px solid var(--line); padding:12px; vertical-align:middle; }
    .table tr:hover td{ background:#e7f7fc; }

    /* Columns */
    .col-client { min-width:260px; }
    .client-cell{ display:flex; align-items:center; gap:10px; }
    .avatar{
      width:40px; height:40px; border-radius:50%; object-fit:cover; background:#f6fbfd; border:1px solid #e8f5fb;
    }
    .client-name{ font-weight:800; color:#154f5e; }
    .client-sub{ color:var(--muted); font-size:.9em; }

    .chips{ display:flex; gap:6px; flex-wrap:wrap; }
    .chip{ padding:4px 10px; border-radius:999px; font-weight:800; font-size:.85em; }
    .chip.gray{ background:#eef6fa; color:#557a8a; }
    .chip.green{ background:#d8f6e3; color:#227a48; }
    .chip.blue{ background:#e1f3ff; color:#1b6fae; }
    .chip.red{ background:#ffd8d8; color:#b43d3d; }
    .chip.amber{ background:#ffe8c7; color:#8a5a00; }

    .actions{ display:flex; gap:8px; flex-wrap:wrap; }
    .btn{ background:var(--teal); color:#fff; border:none; padding:8px 12px; border-radius:10px; font-weight:800; cursor:pointer; }
    .btn-outline{ background:#fff; color:var(--teal); border:1.5px solid var(--teal); padding:8px 12px; border-radius:10px; font-weight:800; cursor:pointer; }
    .btn-outline:hover{ background:var(--teal); color:#fff; }

    /* Empty row */
    .empty{ text-align:center; color:#9aaab5; padding:18px !important; font-weight:700; }

    /* ==== Users page: edge-to-edge ==== */
.content-area.users-fullbleed{
  padding-left: 0 !important;
  padding-right: 0 !important;
  gap: 0 !important;
}
.content-area.users-fullbleed .page-title,
.content-area.users-fullbleed .sub-bar,
.content-area.users-fullbleed .table-scroll{
  width: 100% !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  padding-left: 5px !important;
  padding-right: 5px !important;
}
.content-area.users-fullbleed .table-scroll{ overflow: auto; }
.content-area.users-fullbleed .table{
  min-width: 0 !important;
  width: 100% !important;
  border-spacing: 0;
}

/* compact table for fewer columns */
.table th, .table td { padding-left: 12px; padding-right: 12px; }

/* avatar + name */
.client-cell{ display:flex; align-items:center; gap:10px; }
.client-cell .avatar{
  width:40px; height:40px; border-radius:50%; object-fit:cover;
  background:#f6fbfd; border:1px solid #e8f5fb;
}
.client-name{ font-weight:800; color:#154f5e; }
.client-sub{ color:#6b8b97; font-size:.9em; }

/* verified chip */
.chip{ padding:4px 10px; border-radius:999px; font-weight:800; font-size:.85em; }
.chip.green{ background:#d8f6e3; color:#227a48; }
.chip.gray { background:#eef6fa; color:#557a8a; }

/* Head bar: title left, search right */
.users-head{
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: 14px 0 8px;
}
.users-head .page-title{ margin: 0; width:auto; } /* remove auto-centering width */

  </style>
</head>
<body>
  <?php $page = 'users'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <main class="content-area users-fullbleed">
    <?php include 'includes/header.php'; ?>

    <div class="users-head">
   <h1 class="page-title">Users</h1>
   <div class="searchbox">
     <input id="userSearch" type="text" placeholder="Search name, email, phone…">
     <button class="btn-outline" id="clearSearch"><i class="fa fa-xmark"></i></button>
   </div>
 </div>

    <div class="table-scroll">
      <table class="table" id="usersTable">
       <thead>
  <tr>
    <th style="width:56px;">#</th>
    <th>Client</th>
    <th>Contact No.</th>
    <th>Email Address</th>
    <th>Status</th>
  </tr>
</thead>
<tbody>
<?php
// NOTE: if your table name is literally `user`, better wrap with backticks.
$sql = "
SELECT
  u.user_id,
  u.full_name,
  u.email_address,
  u.contact_number,
  u.profile_pic,
  u.is_verified
FROM `user` u
WHERE u.role='client'
ORDER BY u.full_name
";

if ($res = $conn->query($sql)) {
  if ($res->num_rows > 0) {
    $rownum = 0;
    while ($row = $res->fetch_assoc()) {
      $rownum++;
      $uid    = (int)$row['user_id'];
      $name   = $row['full_name'] ?? '';
      $email  = $row['email_address'] ?? '';
      $phone  = $row['contact_number'] ?? '';
      $pic = web_path_from_admin($row['profile_pic'] ?? '');
      $v      = (int)($row['is_verified'] ?? 0);
      $joined = '—';
      if (!empty($row['created_at'])) {
        $joined = date('M j, Y', strtotime($row['created_at']));
      }

      echo '<tr>';
      // #
      echo '<td>'. $rownum .'</td>';

      // Client (with avatar + name)
      echo '<td>
              <div class="client-cell">
                <img class="avatar" src="'.htmlspecialchars($pic).'" alt="">
                <div>
                  <div class="client-name">'.htmlspecialchars($name).'</div>
                  <div class="client-sub">ID: U-'.str_pad((string)$uid, 4, "0", STR_PAD_LEFT).'</div>
                </div>
              </div>
            </td>';

      // Contact No.
      echo '<td>'. htmlspecialchars($phone ?: '—') .'</td>';

      // Email Address
      echo '<td>'. htmlspecialchars($email ?: '—') .'</td>';

      // Status
      echo '<td>'. ($v ? '<span class="chip green">Verified</span>'
                       : '<span class="chip gray">Unverified</span>') .'</td>';

      echo '</tr>';
    }
  } else {
    echo '<tr class="empty"><td colspan="6">No users found.</td></tr>';
  }
  $res->free();
} else {
  echo '<tr class="empty"><td colspan="6" style="color:#c0392b">DB error: '.htmlspecialchars($conn->error).'</td></tr>';
}
?>
</tbody>

      </table>
    </div>
  </main>

  <script>
    // ===== Sidebar interactions (kept from your pattern) =====
    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.getElementById('sidebar');
      const hamburger = document.getElementById('hamburger-btn');
      const overlay = document.getElementById('sidebar-overlay');

      function openSidebar(){ if(!sidebar) return; sidebar.classList.add('open'); if(overlay) overlay.classList.add('active'); document.body.style.overflow='hidden'; }
      function closeSidebar(){ if(!sidebar) return; sidebar.classList.remove('open'); if(overlay) overlay.classList.remove('active'); document.body.style.overflow=''; }

      if (hamburger) hamburger.addEventListener('click', (e)=>{ e.stopPropagation(); sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
      if (overlay) overlay.addEventListener('click', closeSidebar);
      document.querySelectorAll('.sidebar-nav a').forEach(a=> a.addEventListener('click', ()=>{ if (window.innerWidth<=700) closeSidebar(); }));
      document.addEventListener('keydown', e=>{ if (e.key==='Escape' && sidebar && sidebar.classList.contains('open')) closeSidebar(); });
      window.addEventListener('resize', ()=>{ if (window.innerWidth>700) closeSidebar(); });
    });

    // ===== Table search + filters (front-end only) =====
    (function(){
      const $ = s => document.querySelector(s);
      const rows = ()=> Array.from(document.querySelectorAll('#usersTable tbody tr'))
                              .filter(r=>!r.classList.contains('empty'));

      function textMatch(r, term){
        if (!term) return true;
        const hay = ((r.dataset.name||'')+' '+(r.dataset.email||'')+' '+(r.dataset.phone||'')).toLowerCase();
        return hay.includes(term);
      }
      function filterVerified(r, val){
        if (val==='all') return true;
        const v = r.dataset.verified === '1';
        return (val==='yes' ? v : !v);
      }
      function filterStatus(r, val){
        if (val==='all') return true;
        const active = parseInt(r.dataset.activeKits||'0',10) > 0;
        const overdue= parseInt(r.dataset.overdue||'0',10) > 0;
        const total  = parseInt(r.dataset.bookingsTotal||'0',10);
        if (val==='active')  return active;
        if (val==='overdue') return overdue;
        if (val==='nobook')  return total===0;
        return true;
      }

      function runFilters(){
        const term = ($('#userSearch')?.value||'').trim().toLowerCase();
        const ver  = $('#fVerified')?.value||'all';
        const stat = $('#fStatus')?.value||'all';

        let visible = 0;
        rows().forEach(r=>{
          const show = textMatch(r, term) && filterVerified(r, ver) && filterStatus(r, stat);
          r.style.display = show ? '' : 'none';
          if (show) visible++;
        });

        const emptyRow = document.querySelector('#usersTable tbody .empty');
        if (emptyRow) emptyRow.style.display = visible ? 'none' : '';
        renumber();
      }

      function renumber(){
        let i = 1;
        rows().forEach(r=>{
          if (r.style.display==='none') return;
          const cell = r.children[0];
          if (cell) cell.textContent = i++;
        });
      }

      document.addEventListener('DOMContentLoaded', ()=>{
        $('#userSearch')?.addEventListener('input', runFilters);
        $('#fVerified')?.addEventListener('change', runFilters);
        $('#fStatus')?.addEventListener('change', runFilters);
        $('#clearSearch')?.addEventListener('click', ()=>{ const s=$('#userSearch'); if(s){ s.value=''; s.focus(); } runFilters(); });

        // first run
        runFilters();
      });
    })();
  </script>
</body>
</html>
