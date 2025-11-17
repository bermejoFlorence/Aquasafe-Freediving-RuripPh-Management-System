<?php
// admin/reports.php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Access Denied</title>
  <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>
  <script>
    Swal.fire({icon:'error',title:'Access Denied',text:'You do not have permission to access this page.',confirmButtonColor:'#1e8fa2'})
      .then(()=>{ window.location='../login.php'; });
  </script></body></html>";
  exit;
}

require_once '../db_connect.php';
function bind_params(mysqli_stmt $stmt, string $types, array $vals): bool {
    $refs = [];
    foreach ($vals as $i => $v) {
        $refs[$i] = &$vals[$i];   // by ref
    }
    array_unshift($refs, $types); // ilagay si $types sa unahan
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}
// -------- Filters (GET) --------
$statusFilter = trim($_GET['status'] ?? 'all');
$typeFilter   = trim($_GET['type']   ?? 'all');   // all | post | comment
$q            = trim($_GET['q']      ?? '');

$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($pageNum - 1) * $perPage;

// -------- Build WHERE --------
$where = [];
$bindTypes = '';
$bind = [];

// status
if ($statusFilter === 'inbox') {
  $where[] = "r.status IN ('open','under_review')";
} elseif ($statusFilter !== 'all' && $statusFilter !== '') {
  $where[] = "r.status = ?";
  $bindTypes .= 's'; $bind[] = $statusFilter;
}

// type
if ($typeFilter !== 'all' && $typeFilter !== '') {
  $where[] = "r.target_type = ?";
  $bindTypes .= 's'; $bind[] = $typeFilter;
}

// search
if ($q !== '') {
  $where[] =
    "(ur.full_name LIKE CONCAT('%', ?, '%')
      OR ut.full_name LIKE CONCAT('%', ?, '%')
      OR fp.title LIKE CONCAT('%', ?, '%')
      OR r.other_text LIKE CONCAT('%', ?, '%')
      OR r.report_id = ?
      OR r.post_id = ?)";
  $bindTypes .= 'ssssii';
  $qid = ctype_digit($q) ? (int)$q : 0;
  array_push($bind, $q, $q, $q, $q, $qid, $qid);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// -------- Count total --------
$sqlCount = "
  SELECT COUNT(*) AS cnt
  FROM forum_report r                      -- ← was: report r
  LEFT JOIN user ur ON ur.user_id = r.reporter_id
  LEFT JOIN user ut ON ut.user_id = r.target_owner_id
  LEFT JOIN forum_post fp ON fp.post_id = r.post_id
  LEFT JOIN forum_post_comment c ON (c.comment_id = r.target_id AND r.target_type='comment')
  $whereSql
";

$st = $conn->prepare($sqlCount);
if ($bind) {
    bind_params($st, $bindTypes, $bind);
}
$st->execute();
$cnt = 0;
$st->bind_result($cnt);
$st->fetch();
$st->close();

$totalPages = max(1, (int)ceil($cnt / $perPage));

// -------- Fetch rows --------
$sql = "
  SELECT
    r.report_id, r.reporter_id, r.target_type, r.target_id, r.post_id,
    r.target_owner_id, r.target_owner_role, r.reason, r.other_text, r.notes, r.status, r.created_at,

    ur.full_name AS reporter_name,
    ut.full_name AS target_name,
    ut.role       AS target_role,

    fp.title      AS post_title,
    c.body        AS comment_body
  FROM forum_report r                      -- ← was: report r
  LEFT JOIN user ur ON ur.user_id = r.reporter_id
  LEFT JOIN user ut ON ut.user_id = r.target_owner_id
  LEFT JOIN forum_post fp ON fp.post_id = r.post_id
  LEFT JOIN forum_post_comment c ON (c.comment_id = r.target_id AND r.target_type='comment')
  $whereSql
  ORDER BY r.created_at DESC, r.report_id DESC
  LIMIT ? OFFSET ?
";

$st = $conn->prepare($sql);

if ($bind) {
    // may filters tayo → idagdag natin sa dulo ang limit/offset
    $types  = $bindTypes . 'ii';
    $params = array_merge($bind, [$perPage, $offset]);
    bind_params($st, $types, $params);
} else {
    // walang filters → simple lang
    $st->bind_param('ii', $perPage, $offset);
}

$st->execute();

$res = $st->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
$st->close();

function pill($status) {
  $map = [
    'open'          => ['#ffe8e6', '#e11d48'],
    'under_review'  => ['#fff7e6', '#b45309'],
    'resolved'      => ['#e6fffa', '#0f766e'],
    'closed'        => ['#eef2ff', '#3730a3'],
  ];
  [$bg,$fg] = $map[$status] ?? ['#eef2f3','#475569'];
  return "<span class='status-pill' style='background:$bg;color:$fg'>$status</span>";
}
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function trimtext($s,$len=60){ $s=trim((string)$s); return (mb_strlen($s)>$len)?(mb_substr($s,0,$len-1).'…'):$s; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports · Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="styles/style.css">
<style>
/* ======= Match Bookings table look & spacing ======= */
.table-title-bar{
  display:flex;align-items:center;justify-content:space-between;
  margin-top:10px;margin-bottom:1px;width:100%;
  margin-left:0;margin-right:0;flex-wrap:wrap;
}
.table-title{color:#1e8fa2;font-size:2rem;font-weight:bold;letter-spacing:.02em;}

.table-controls{display:flex;align-items:center;gap:10px;}
.table-filter{
  padding:8px 12px;border:1.5px solid #c8eafd;border-radius:10px;
  background:#fff;color:#186070;font-weight:600;box-shadow:0 2px 8px #b9eafc22;
}
.table-filter:focus{outline:none;border-color:#1e8fa2;}

.table-search input{
  padding:8px 16px;border:1.5px solid #c8eafd;border-radius:10px;
  font-size:1em;min-width:180px;background:#fff;color:#186070;
  box-shadow:0 2px 8px #b9eafc22;transition:border .18s;
}
.table-search input:focus{outline:none;border-color:#1e8fa2;}

@media (max-width:700px){
  .table-title-bar{flex-direction:column;align-items:center;margin-top:20px;margin-bottom:10px;}
  .table-title{font-size:1.3rem;text-align:center;width:100%;margin-top:10px;}
  .table-controls,.table-filter,.table-search{width:100%;}
  .table-search input{width:100%}
}

/* Full-bleed content like Bookings */
.content-area{padding-left:0;padding-right:0}

/* Table container & table (copy of Bookings style) */
.report-table-container{width:100%;margin:0 auto 30px;overflow-x:auto;background:transparent;border-radius:0}
.report-table{
  width:100%;border-collapse:separate;border-spacing:0;background:transparent;border-radius:0
}
.report-table th,.report-table td{text-align:left}
.report-table th{
  background:#1e8fa2;color:#fff;padding:16px 12px;font-weight:700;font-size:1.07em;border:none
}
.report-table td{
  padding:14px 12px;background:#ffffffea;color:#186070;font-size:1em;
  border-bottom:2px solid #e3f6fc;vertical-align:middle
}
.report-table tbody tr:last-child td{border-bottom:none}
.report-table tr{transition:background .18s}
.report-table tr:hover td{background:#e7f7fc !important}

/* Buttons to match theme */
.btn{border:0;border-radius:10px;padding:8px 16px;font-weight:700;cursor:pointer}
.btn-open{
  background:#f7fdff;border:1px solid #c8eafd;color:#1e8fa2;display:inline-flex;
  align-items:center;gap:8px;text-decoration:none
}
.btn-review{background:#1e8fa2;color:#fff}

/* Status badges (reuse palette of Bookings page) */
.status-badge{
  display:inline-flex;align-items:center;justify-content:center;
  padding:8px 16px;border-radius:12px;font-weight:800;font-size:.98rem;letter-spacing:.2px;line-height:1;
  box-shadow:inset 0 0 0 1px rgba(0,0,0,.04),0 1px 0 rgba(0,0,0,.03)
}
.status-badge.open{background:#ffe3d7;color:#b65e1b}
.status-badge.under_review{background:#e8f6fb;color:#1e8fa2}
.status-badge.resolved{background:#dff5e8;color:#1d8b58}
.status-badge.closed{background:#e9eef3;color:#5a6b78}

/* Pagination (simple) */
.pagination{display:flex;gap:8px;justify-content:center;margin-top:18px}
.pagination a,
.pagination .active{
  padding:6px 10px;border-radius:10px;border:1px solid #cfe5ea;background:#fff;color:#186070
}
.pagination .active{background:#1e8fa2;color:#fff;border-color:#1e8fa2}
/* Full-bleed layout for Reports (match Bookings spacing) */
.content-area{ padding-left:0 !important; padding-right:0 !important; }
.content-area .dashboard-main{
  max-width:none !important;
  width:100% !important;
  margin-left:0 !important;
  margin-right:0 !important;
  padding-left:0 !important;
  padding-right:0 !important;
}

/* Filters & table wrappers stretch full width */
.table-title-bar,
.report-table-container{ width:100% !important; margin:0 !important; }

/* Optional: flatten header card so it spans full width */
.page-head{ margin:0 0 12px 0 !important; border-radius:0 !important; }

/* Make the table breathe without adding outer gutters */
.report-table{ width:100%; }
.report-table th, .report-table td{ padding-left:18px; padding-right:18px; }

/* If your theme sets a max-width on .content-area > * via container rules, kill it */
.content-area > .container, 
.content-area > .dashboard-main > .container{
  max-width:none !important;
  width:100% !important;
  margin:0 !important;
  padding:0 !important;
}
/* === Payment / Generic modal sizing === */
.modal {
  position: fixed; inset: 0; z-index: 10000;
  display: flex; align-items: center; justify-content: center;
  background: rgba(0, 151, 183, 0.11);
}

.modal-content{
  width: min(96vw, 560px);          /* hindi sobrang lapad */
  max-width: 560px;
  background:#fff;
  border-radius: 22px;
  box-shadow: 0 2px 24px #1e8fa233;
  padding: 28px 24px 22px;          /* bawas ang side padding */
}

.modal-title{
  margin: 4px 0 18px;
  text-align:center;
  font-size: 2rem;
  color:#1698b4;
  font-weight: 800;
}

/* === Rows inside the modal === */
.modal-details{ margin-top: 6px; }
.modal-details div{
  display:flex;                     /* HINDI na space-between */
  align-items:center;
  gap: 14px;                        /* maliit na pagitan lang */
  padding: 6px 0;
}

.modal-label{
  flex: 0 0 170px;                  /* fixed label width */
  color:#1e8fa2;
  font-weight: 700;
}

.modal-details span:not(.modal-label),
.modal-details .value{
  flex: 1 1 auto;                   /* value consumes the rest */
  color:#186070;
  text-align:left;                  /* walang dikit sa kanan */
  word-break: break-word;
}

.modal-details select{
  width: 100%;
  max-width: 260px;                 /* para hindi kumain ng buong row */
}

.modal-buttons{
  display:flex; justify-content:center;
  gap: 12px; margin-top: 22px;
}

@media (max-width: 540px){
  .modal-content{ width: 92vw; padding: 22px 16px 18px; }
  .modal-label{ flex-basis: 130px; } /* mas makitid sa mobile */
}

/* ---- Compact layout for the Report modal (SweetAlert2) ---- */
.swal2-popup.report-modal{
  width: min(96vw, 560px) !important;    /* tighter like your payment modal */
  padding: 22px 18px 18px !important;    /* reduce side padding */
  border-radius: 16px;
}
.swal2-popup.report-modal .swal2-title{
  margin: 0 0 12px !important;
  font-size: 28px; font-weight: 800; color: #1698b4;
}
.swal2-popup.report-modal .swal2-html-container{
  margin: 0 !important;                  /* remove default SA2 margins */
  padding: 0 !important;
  text-align: left;
}
/* Make the admin note textarea fill the popup width */
.swal2-popup.report-modal #rep-note.swal2-textarea{
  width: 100% !important;
  min-height: 160px;     /* make it taller so the right side doesn’t look empty */
  display: block;
  box-sizing: border-box;
  padding: 12px 14px;
  font-size: 1rem;
  resize: vertical;      /* allow vertical drag only */
}

/* ---- Report modal polish (SweetAlert2) ---- */
.swal2-popup.report-modal{
  width: min(96vw, 540px) !important;     /* a bit narrower for balance */
  padding: 22px 18px 20px !important;
  border-radius: 16px;
}

/* Title */
.swal2-popup.report-modal .swal2-title{
  margin: 0 0 12px !important;
  font-size: 28px; font-weight: 800; color:#1698b4;
}

/* Content container: remove extra scroll + right padding look */
.swal2-popup.report-modal .swal2-html-container{
  margin: 0 !important;
  padding: 0 !important;
  overflow-x: hidden !important;   /* hide the horizontal bar */
  overflow-y: visible !important;  /* let content breathe */
  text-align: left;
}

/* Admin note textarea: full width, not too tall */
.swal2-popup.report-modal #rep-note.swal2-textarea{
  width: 100% !important;
  min-height: 120px;               /* was 160; make it tighter */
  max-height: 40vh;                /* never too tall */
  display: block;
  box-sizing: border-box;
  padding: 12px 14px;
  font-size: 1rem;
  resize: vertical;                /* allow only vertical resize */
}

/* Center and style the action buttons */
.swal2-popup.report-modal .rep-actions{
  display:flex;
  justify-content:center;          /* center the trio */
  gap: 10px 12px;
  flex-wrap: wrap;
  margin-top: 12px;
}

.swal2-popup.report-modal .rep-actions .btn-primary,
.swal2-popup.report-modal .rep-actions .btn-soft,
.swal2-popup.report-modal .rep-actions .btn-danger{
  padding: 9px 16px;
  border-radius: 10px;
  font-weight: 700;
  line-height: 1;
  min-width: 156px;                /* uniform width for neat alignment */
}

/* optional: soften the divider under "Add admin note" if any */
.swal2-popup.report-modal hr{ margin: 10px 0; border-color:#e9eef3; }

/* Consistent inner gutter for the report modal */
.swal2-popup.report-modal{ --rep-gutter: 14px; }

/* Give the content rows, note block, and actions the same side padding
   so the textarea aligns with the text and has space on the right */
.swal2-popup.report-modal .rep-row,
.swal2-popup.report-modal .rep-note,
.swal2-popup.report-modal .rep-actions{
  padding-inline: var(--rep-gutter);
}

/* Textarea: fill the padded area exactly, so both left and right borders show */
.swal2-popup.report-modal #rep-note.swal2-textarea{
  width: 100% !important;
  margin: 0;                   /* align left edge with text */
  box-sizing: border-box;      /* borders inside width */
  min-height: 136px;
  border: 2px solid #c8eafd;
  border-radius: 12px;
  padding: 12px 14px;
}

/* Optional: if you still see the right line too tight,
   bump the gutter slightly here (e.g., 16px) */

</style>

</head>
<body>
<?php $page='reports'; ?>
<?php include 'includes/sidebar.php'; ?>
<main class="content-area">
  <?php include 'includes/header.php'; ?>

  <div class="dashboard-main">
<!-- <form class="table-title-bar" method="get" action="reports.php"> -->
  <div class="table-title">Reports</div>
  <!-- <div class="table-controls">
    <select name="status" class="table-filter" aria-label="Status">
      <?php
        $opts = ['inbox'=>'Inbox (Open + Under review)','open'=>'Open','under_review'=>'Under review','resolved'=>'Resolved','closed'=>'Closed','all'=>'All'];
        foreach($opts as $val=>$label){
          $sel = ($statusFilter===$val)?'selected':'';
          echo "<option value='".esc($val)."' $sel>".esc($label)."</option>";
        }
      ?>
    </select>

    <select name="type" class="table-filter" aria-label="Type">
      <?php
        $tops = ['all'=>'All types','post'=>'Post','comment'=>'Comment'];
        foreach($tops as $val=>$label){
          $sel = ($typeFilter===$val)?'selected':'';
          echo "<option value='".esc($val)."' $sel>".esc($label)."</option>";
        }
      ?>
    </select>

    <div class="table-search">
      <input type="text" name="q" placeholder="Search reporter, user, title, ID…" value="<?=esc($q)?>">
    </div>

    <button class="btn btn-review" type="submit"><i class="fa fa-filter"></i>&nbsp;Filter</button>
  </div> -->
<!-- </form> -->

<div class="report-table-container">
  <table class="report-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Date Reported</th>
        <th>Reporter</th>
        <th>Target</th>
        <th>Reason</th>
        <th>Notes</th>
        <th>Status</th>
        <th style="text-align:right">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" style="text-align:center;color:#aaa;">No reports found.</td></tr>
      <?php else: $rownum = ($pageNum-1)*$perPage + 1; foreach($rows as $r): ?>
        <?php
          $targetLabel = ($r['target_type']==='comment') ? ('Comment • #'.$r['target_id']) : 'Post';
          $targetText  = ($r['target_type']==='comment')
                          ? trimtext($r['comment_body'] ?? '', 60)
                          : trimtext($r['post_title'] ?? '(untitled)', 60);
          $goto = 'view_post.php?post_id='.(int)$r['post_id'];
          if ($r['target_type']==='comment') { $goto .= '#c-'.$r['target_id']; }

          $statusClass = str_replace(' ','_', strtolower($r['status']));
          $statusLabel = ucwords(str_replace('_',' ', $r['status']));
        ?>
        <tr>
          <td><?= $rownum++; ?></td>
          <td><?= esc(date('F j, Y g:ia', strtotime($r['created_at']))) ?></td>
          <td><?= esc($r['reporter_name'] ?? 'Client') ?></td>
          <td>
            <div><strong><?=esc($targetLabel)?></strong></div>
            <div style="color:#567882"><?=esc($targetText)?></div>
            <div style="margin-top:6px">
              <a class="btn btn-open" href="<?=esc($goto)?>" target="_blank">
                <i class="fa fa-up-right-from-square"></i> Open
              </a>
            </div>
          </td>
          <td>
            <div><strong><?= esc(ucwords(str_replace('_',' ', $r['reason'] ?? ''))) ?: '—' ?></strong></div>
            <div style="color:#567882"><?= esc(trimtext($r['other_text'] ?? '', 48)) ?: '' ?></div>
          </td>
          <td>
            <div title="<?=esc($r['notes'] ?? '')?>"><?= esc(trimtext($r['notes'] ?? '')) ?: '—' ?></div>
            <div style="margin-top:6px;font-size:.92em;color:#567882">
              Reported user: <strong><?=esc($r['target_name'] ?? '—')?></strong>
              <span>(<?=esc($r['target_role'] ?? $r['target_owner_role'] ?? '')?>)</span>
            </div>
          </td>
          <td><span class="status-badge <?=esc($statusClass)?>"><?=esc($statusLabel)?></span></td>
          <td style="text-align:right">
            <button
  class="btn btn-review"
  data-act="review"
  data-id="<?=$r['report_id']?>"
  data-status="<?=esc($r['status'])?>"
  data-target-type="<?=esc($r['target_type'])?>"
  data-post-id="<?=$r['post_id']?>"
  data-target-id="<?=$r['target_id']?>"
  data-reason="<?=esc($r['reason'])?>"
  data-other="<?=esc($r['other_text'])?>"
  data-reporter="<?=esc($r['reporter_name'])?>"
  data-user="<?=esc($r['target_name'])?>"
  data-role="<?=esc($r['target_role'] ?? $r['target_owner_role'] ?? '')?>"
  data-target-user-id="<?=$r['target_owner_id'] ?? 0?>"   <!-- 👈 ADD THIS -->
>Review</button>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

    <!-- Pagination -->
    <div class="pagination">
      <?php for($p=1;$p<=$totalPages;$p++): ?>
        <?php $u = 'reports.php?'.http_build_query(['status'=>$statusFilter,'type'=>$typeFilter,'q'=>$q,'page'=>$p]); ?>
<?= $p === $pageNum ? '<span class="active">'.$p.'</span>' : '<a href="'.esc($u).'">'.$p.'</a>' ?>
      <?php endfor; ?>
    </div>
  </div>
</main>

<script>

document.addEventListener('click', async (e) => {
  const b = e.target.closest('[data-act="review"]');
  if (!b) return;

  // ---- pulled from data-* on the Review button ----
  const id        = b.dataset.id;
  const status    = (b.dataset.status || '').toLowerCase();
  const meta = {
    reporter:  b.dataset.reporter || 'Client',
    user:      b.dataset.user || '—',
    role:      b.dataset.role || '',
    reason:    (b.dataset.reason || '').replaceAll('_',' '),
    other:     b.dataset.other || '',
    target:    (b.dataset.targetType || ''),
    postId:    b.dataset.postId || '',
    targetId:  b.dataset.targetId || '',
    deadline:  b.dataset.deadline || ''   // may be blank if you haven’t added it yet
  };

  function fmtDeadline(s){
    if(!s) return '';
    const d = new Date(s.replace(' ', 'T')); // tolerate "YYYY-MM-DD HH:mm:ss"
    if (isNaN(d.getTime())) return s;
    const opts = { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' };
    return d.toLocaleString(undefined, opts);
  }

  const showNotify = status !== 'under_review'; // don’t show notify if already under review
  const deadlineLine = meta.deadline ? `<div style="margin-top:6px"><strong>Deadline:</strong> ${fmtDeadline(meta.deadline)}</div>` : '';

  const html = `
    <style>
      .rep-row{margin:8px 0}
      .rep-note{margin-top:10px}
      .rep-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
      .btn-soft{padding:9px 14px;border-radius:10px;border:1px solid #c8eafd;background:#f7fdff;color:#1e8fa2;font-weight:700;cursor:pointer}
      .btn-primary{padding:9px 14px;border-radius:10px;border:0;background:#1e8fa2;color:#fff;font-weight:700;cursor:pointer}
      .btn-danger{padding:9px 14px;border-radius:10px;border:0;background:#e74c3c;color:#fff;font-weight:700;cursor:pointer}
      .rep-small{color:#567882;font-size:.92em}
      .rep-check{display:flex;align-items:center;gap:8px;margin-top:8px}
    </style>
    <div style="text-align:left">
      <div class="rep-row"><strong>Reporter:</strong> ${escapeHtml(meta.reporter)}</div>
      <div class="rep-row"><strong>Reported user:</strong> ${escapeHtml(meta.user)} <span class="rep-small">(${escapeHtml(meta.role)})</span></div>
      <div class="rep-row"><strong>Target:</strong> ${escapeHtml(meta.target)} · Post #${escapeHtml(meta.postId)} ${meta.target==='comment' ? '· Comment #'+escapeHtml(meta.targetId): ''}</div>
      <div class="rep-row"><strong>Reason:</strong> ${escapeHtml(meta.reason || '—')}</div>
      <div class="rep-row"><strong>Other text:</strong> ${escapeHtml(meta.other || '—')}</div>
      ${deadlineLine}

      <div class="rep-note">
        <div style="font-weight:600;margin:10px 0 6px">Add admin note (optional)</div>
        <textarea id="rep-note" class="swal2-textarea" rows="3" placeholder="E.g., Notified user; 48h to comply."></textarea>
        <label class="rep-check"><input type="checkbox" id="rep-hide"> Hide target while reviewing</label>
      </div>

      <div class="rep-actions">
        ${showNotify ? `<button class="btn-primary" data-op="notify">Notify & start 48h countdown</button>` : ``}
        <button class="btn-soft" data-op="resolve">Resolve</button>
        <button class="btn-danger" data-op="ban">Ban now</button>
      </div>
    </div>
  `;

  await Swal.fire({
    title: 'Report details',
    html,
    showConfirmButton:false,
    showCancelButton:true,
    cancelButtonText:'Done',
      customClass: { popup: 'report-modal' },  // ✅ add this line
    didOpen: () => {
      const box = Swal.getPopup();

      box.addEventListener('click', async (ev)=>{
        const actBtn = ev.target.closest('[data-op]');
        if (!actBtn) return;

        const op   = actBtn.getAttribute('data-op');                  // notify | resolve | ban
        const note = (box.querySelector('#rep-note')?.value || '').trim();
        const hide = box.querySelector('#rep-hide')?.checked ? 1 : 0;

        try {
          if (op === 'notify') {
            // start countdown to 48h
            const fd = new URLSearchParams();
            fd.set('action', 'notify_start');
            fd.set('report_id', id);
            fd.set('deadline_hours', '48');
            fd.set('hide', String(hide));
            fd.set('note', note);

            await doPost(fd);
            await Swal.fire({icon:'success', title:'Notified', text:'Reported user has been notified. Countdown started (48h).', confirmButtonColor:'#1e8fa2'});
            location.reload();
            return;
          }

          if (op === 'resolve') {
            const ok = await confirmBox('Resolve this report?', 'This will notify the parties and close the case as resolved.');
            if (!ok) return;

            const fd = new URLSearchParams();
            fd.set('action', 'resolve');
            fd.set('report_id', id);
            fd.set('note', note);

            await doPost(fd);
            await Swal.fire({icon:'success', title:'Resolved', text:'Report marked as resolved.', confirmButtonColor:'#1e8fa2'});
            location.reload();
            return;
          }

          if (op === 'ban') {
  // Open a detailed ban form
  const banHtml = `
    <style>
      .ban-row{margin:8px 0}
      .ban-options{
        display:flex; flex-wrap:wrap; gap:10px 14px; margin:6px 0 8px;
      }
      .ban-options label{
        background:#f7fdff; border:1px solid #c8eafd; color:#1e8fa2;
        padding:8px 10px; border-radius:10px; font-weight:700; cursor:pointer;
        display:inline-flex; align-items:center; gap:8px;
      }
      .ban-options input[type="radio"]{ accent-color:#1e8fa2; }
      #ban_custom_days{
        width: 90px; padding:6px 8px; border:1px solid #c8eafd; border-radius:8px;
      }
      .rep-small{color:#567882;font-size:.92em}
    </style>

    <div class="ban-row"><strong>Choose ban duration</strong></div>
    <div class="ban-options" id="banOptions">
      <label><input type="radio" name="ban_choice" value="1h24"> 24 hours</label>
      <label><input type="radio" name="ban_choice" value="3"> 3 days</label>
      <label><input type="radio" name="ban_choice" value="7" checked> 7 days</label>
      <label><input type="radio" name="ban_choice" value="30"> 30 days</label>
      <label><input type="radio" name="ban_choice" value="custom"> Custom</label>
      <input type="number" id="ban_custom_days" min="1" placeholder="days" style="display:none">
      <label><input type="radio" name="ban_choice" value="permanent"> Permanent</label>
    </div>

    <div class="ban-row"><small class="rep-small">Admin note (optional):</small></div>
    <textarea id="ban-note" class="swal2-textarea" rows="3"
      placeholder="Reason / details to include in the notice.">${escapeHtml(note)}</textarea>
  `;

  const res = await Swal.fire({
    title: 'Ban this account',
    html: banHtml,
    showCancelButton: true,
    confirmButtonText: 'Ban user',
    confirmButtonColor: '#e74c3c',
    cancelButtonText: 'Cancel',
    focusConfirm: false,
    didOpen: () => {
      const popup = Swal.getPopup();
      const radios = popup.querySelectorAll('input[name="ban_choice"]');
      const custom = popup.querySelector('#ban_custom_days');

      function toggleCustom() {
        const chosen = popup.querySelector('input[name="ban_choice"]:checked')?.value;
        custom.style.display = (chosen === 'custom') ? 'inline-block' : 'none';
      }
      radios.forEach(r => r.addEventListener('change', toggleCustom));
      toggleCustom();
    },
    preConfirm: () => {
      const popup = Swal.getPopup();
      const choice = popup.querySelector('input[name="ban_choice"]:checked')?.value;
      const customDays = parseInt(popup.querySelector('#ban_custom_days')?.value || '0', 10);
      const note2 = (popup.querySelector('#ban-note')?.value || '').trim();

      if (!choice) {
        Swal.showValidationMessage('Please choose a duration.');
        return false;
      }
      if (choice === 'custom' && (!customDays || customDays <= 0)) {
        Swal.showValidationMessage('Enter a valid number of days.');
        return false;
      }
      return { choice, customDays, note2 };
    }
  });

  if (!res.isConfirmed) return;

  const { choice, customDays, note2 } = res.value;

  const fd = new URLSearchParams();
  fd.set('action', 'ban_now');
  fd.set('report_id', id);
  fd.set('note', note2);

  // Encode duration
  if (choice === 'permanent') {
    fd.set('permanent', '1');               // server: treat as no ban_until
  } else if (choice === '1h24') {
    fd.set('ban_hours', '24');              // server: NOW() + INTERVAL 24 HOUR
  } else if (choice === 'custom') {
    fd.set('ban_days', String(customDays)); // server: NOW() + INTERVAL X DAY
  } else {
    fd.set('ban_days', String(choice));     // 3, 7, 30
  }

  await doPost(fd);
  await Swal.fire({
    icon:'success',
    title:'Banned',
    text:'User has been banned with your selected duration.',
    confirmButtonColor:'#1e8fa2'
  });
  location.reload();
  return;
}

        } catch (err) {
          await Swal.fire({icon:'error', title:'Action failed', text:String(err), confirmButtonColor:'#1e8fa2'});
        }
      }, {once:false});
    }
  });

  async function doPost(fd){
    const res  = await fetch('report_update.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: fd.toString()
    });
    const data = await res.json().catch(()=>({}));
    if (!res.ok || !data.ok) {
      throw new Error(data.message || ('HTTP '+res.status));
    }
  }

  async function confirmBox(title, text){
    const r = await Swal.fire({
      icon: 'question',
      title, text,
      showCancelButton: true,
      confirmButtonText: 'Yes, proceed',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#1e8fa2'
    });
    return r.isConfirmed;
  }
});

function escapeHtml(s){return (s||'').replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}

// Sidebar behaviour (same as other pages)
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const hamburger = document.getElementById('hamburger-btn');
  const overlay = document.getElementById('sidebar-overlay');
  function openSidebar(){sidebar.classList.add('open');overlay.classList.add('active');document.body.style.overflow='hidden';}
  function closeSidebar(){sidebar.classList.remove('open');overlay.classList.remove('active');document.body.style.overflow='';}
  if (hamburger) hamburger.addEventListener('click', e => { e.stopPropagation(); sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
  if (overlay) overlay.addEventListener('click', closeSidebar);
  document.querySelectorAll('.sidebar-nav a').forEach(link => link.addEventListener('click', () => { if (window.innerWidth <= 700) closeSidebar(); }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar(); });
  window.addEventListener('resize', () => { if (window.innerWidth > 700) closeSidebar(); });
});
</script>
</body>
</html>
