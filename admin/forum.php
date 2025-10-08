<?php
// admin/forum.php
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

/* --- PHP 8 polyfill (if needed) --- */
if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle) {
    return 0 === strncmp($haystack, $needle, strlen($needle));
  }
}

/* ---- Current user for compose avatar ---- */
$me = ['full_name'=>'Admin', 'profile_pic'=>'uploads/default.png'];
$uid = (int)($_SESSION['user_id'] ?? 0);

/* helper: convert DB path to a web path that works from /admin/ */
function web_path_from_admin($p) {
  if (!$p) return '../uploads/default.png';
  // absolute URL or site-root path
  if (preg_match('#^https?://#', $p) || str_starts_with($p, '/')) return $p;
  // if already starts with uploads/
  if (substr($p, 0, 8) === 'uploads/') return '../' . $p;
  // otherwise assume it's a bare filename in uploads
  return '../uploads/' . ltrim($p, '/');
}

if ($uid) {
  if ($stmt = $conn->prepare("SELECT full_name,
                                     COALESCE(profile_pic,'uploads/default.png') AS profile_pic
                              FROM user WHERE user_id=? LIMIT 1")) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
      $row['profile_pic'] = web_path_from_admin($row['profile_pic']);
      $me = $row;
    }
    $stmt->close();
  }
}

/* ---- Fetch dynamic categories ---- */
$cats = [];
$sql = "SELECT category_id, name, slug, sort_order
        FROM forum_category
        WHERE is_active=1
        ORDER BY sort_order ASC, name ASC";
if ($res = $conn->query($sql)) {
  while ($r = $res->fetch_assoc()) $cats[] = $r;
  $res->close();
}

// ---- Determine active category from URL ----
$activeSlug = 'all';
if (isset($_GET['cat']) && $_GET['cat'] !== '') {
  $activeSlug = preg_replace('/[^a-z0-9_\-]/i', '', $_GET['cat']); // sanitize
  // if unknown slug, fallback to 'all'
  $known = array_column($cats, 'slug');
  if ($activeSlug !== 'all' && !in_array($activeSlug, $known, true)) {
    $activeSlug = 'all';
  }
}

/* ---- Fetch posts filtered by $activeSlug ---- */
$posts = [];

/* resolve category_id if not 'all' */
$categoryId = null;
if ($activeSlug !== 'all') {
  if ($st = $conn->prepare("SELECT category_id FROM forum_category WHERE slug=? AND is_active=1 LIMIT 1")) {
    $st->bind_param('s', $activeSlug);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    if ($r) $categoryId = (int)$r['category_id'];
    $st->close();
  }
}

$sql = "SELECT
          p.post_id, p.title, p.body, p.attachments_json,
          p.likes, p.comments, p.bookmarks, p.views,
          p.created_at,
          u.full_name,
          COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic,
          u.role AS user_role,    
          fc.name  AS category_name,
          fc.slug  AS category_slug,
          EXISTS(
            SELECT 1 FROM forum_post_like fpl
            WHERE fpl.post_id = p.post_id AND fpl.user_id = ?
          ) AS liked_by_me
        FROM forum_post p
        LEFT JOIN user u ON u.user_id = p.user_id
        LEFT JOIN forum_category fc ON fc.category_id = p.category_id ";
if ($categoryId !== null) {
  $sql .= "WHERE p.category_id = ? ";
}
$sql .= "ORDER BY p.created_at DESC
         LIMIT 50";

if ($categoryId !== null) {
  $st = $conn->prepare($sql);
  if (!$st) { die('Prepare failed: ' . $conn->error); } // optional debug
  // order: user_id (for EXISTS), then categoryId
  $st->bind_param('ii', $uid, $categoryId);
} else {
  $st = $conn->prepare($sql);
  if (!$st) { die('Prepare failed: ' . $conn->error); } // optional debug
  // only user_id (for EXISTS)
  $st->bind_param('i', $uid);
}


if ($st && $st->execute()) {
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) {
    // normalize paths for admin/ relative URL
    $row['profile_pic'] = web_path_from_admin($row['profile_pic']);
    $posts[] = $row;
  }
  $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Aquasafe RuripPh Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="styles/style.css" />
  <script>
    window.CURRENT_USER = <?= json_encode($me, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <style>
    :root{
      --outer-pad:14px; --radius-xl:18px; --border:1px solid #d8ecf0;
      --teal:#1e8fa2; --teal-dark:#0e6d7e; --ink:#0e6d7e; --chip-border:#cfe5ea;
    }
    /* ===== Layout ===== */
    .forum-container{ max-width:100%!important; margin:0!important; padding-left:14px!important; padding-right:20px!important; box-sizing:border-box; display:grid; gap:16px; }
    .forum-wrap{ display:grid; grid-template-rows:auto auto 1fr; gap:16px; }
    .forum-header{ 
      position:sticky; 
      top:64px; z-index:5; 
      background:linear-gradient(180deg, var(--main-bg, #eaf6fb) 30%, rgba(255,255,255,0)); 
      padding-top:6px; 
      display:flex; 
      gap:12px; 
      align-items:center; 
      justify-content:space-between; 
        scrollbar-width: thin;               /* Firefox */
  scrollbar-color: #8cd0db transparent; /* thumb + track color for Firefox */
    }
    .forum-chips::-webkit-scrollbar-track {
  background: transparent;             /* transparent track para di makasagabal */
}
    .forum-chips::-webkit-scrollbar {
  height: 8px;                         /* mas manipis, hindi nakaka-distract */
}
.forum-chips::-webkit-scrollbar-thumb {
  background: linear-gradient(90deg, #8cd0db, #1e8fa2); /* teal gradient */
  border-radius: 20px;                 /* rounded pill look */
  border: 2px solid #f0fafd;           /* may kaunting border para lumutang */
}

.forum-chips::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(90deg, #1e8fa2, #0e6d7e); /* darker teal on hover */
}
    .forum-title{ font-weight:700; font-size:1.45rem; color:#0e6d7e; }
    .forum-actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .forum-search{ display:flex; align-items:center; gap:8px; background:#fff; border:var(--border); border-radius:14px; padding:9px 12px; flex:1; }
    .forum-search input{ border:0; outline:0; width:100%; background:transparent; font:inherit; color:inherit; }
    .forum-sort{ border:var(--border); border-radius:12px; padding:8px 12px; background:#fff; }

    /* ===== Chips wrapper ===== */
    .forum-chips-wrap{
      display:flex;
      align-items:center;
      gap:8px;
    }
    /* ===== One-line scrollable chips ===== */
    .forum-chips{
      flex:1 1 auto;
      display:flex; gap:10px; flex-wrap:nowrap;
      overflow-x:auto; overflow-y:hidden;
      padding:0 8px 8px 0;     /* keep space for the scrollbar */
      -webkit-overflow-scrolling:touch; scroll-behavior:smooth;
      position:relative;
      scrollbar-gutter:stable;              /* modern browsers: avoid layout jump */
      scrollbar-width:thin;                 /* Firefox */
    }
    .forum-chips::-webkit-scrollbar{ height:8px; }
    .forum-chips::-webkit-scrollbar-thumb{ background:#cfe5ea; border-radius:999px; }
    .forum-chips::-webkit-scrollbar-track{ background:transparent; }
    .forum-chips::before, .forum-chips::after{
      content:""; position:absolute; top:0; bottom:8px; width:28px; pointer-events:none; /* stop above scrollbar */
    }
    .forum-chips::before{ left:0;  background:linear-gradient(90deg, rgba(255,255,255,1), rgba(255,255,255,0)); }
    .forum-chips::after { right:0; background:linear-gradient(270deg, rgba(255,255,255,1), rgba(255,255,255,0)); }

    .chip, .chip-add, .chip-manage{
      flex:0 0 auto; border:1px solid var(--chip-border); border-radius:999px; padding:10px 16px; background:#fff; font-weight:700; color:#1b6e7f;
    }
    .chip.active{ background:#e6f7fb; border-color:#bfe6ee; text-decoration:none; }
    .chip-add, .chip-manage{
      width:40px; height:40px; padding:0;
      display:inline-flex; align-items:center; justify-content:center;
      border:1px dashed #b8dbe3; background:#f7fdff; cursor:pointer; box-shadow:0 6px 16px rgba(30,143,162,.08);
    }
    .chip-add:hover, .chip-manage:hover{ background:#e9f8ff; }
    .chip-manage{ position:sticky; right:0; margin-left:4px; }

    /* ensure link chips don't get underlines */
    .forum-chips .chip{ text-decoration:none; color:#1b6e7f; display:inline-flex; align-items:center; }
    .forum-chips .chip:hover,
    .forum-chips .chip:focus,
    .forum-chips .chip:active,
    .forum-chips .chip:visited{ text-decoration:none; }

    /* ===== Cards ===== */
    .forum-grid{ display:grid; grid-template-columns:1fr!important; gap:18px; }
    .compose,.post{ background:#fff; border:var(--border); border-radius:var(--radius-xl); box-shadow:0 6px 20px rgba(16,61,108,.06); }
    .compose{ padding:14px 16px; display:grid; grid-template-columns:auto 1fr; gap:12px; margin-bottom: 6px; }
    .post{ padding:14px 16px; display:grid; gap:10px; }
    .avatar{ width:40px; height:40px; border-radius:50%; background:#dbeff4; border:1px solid #c7e1e6; object-fit:cover; }
    .compose .fake-input{ border:var(--border); border-radius:14px; padding:10px 12px; color:#6f8a93; }
    .compose-actions{ display:flex; gap:10px; align-items:center; justify-content:flex-end; margin-top:8px; }
    .btn{ border:1px solid #bfe0e6; border-radius:12px; background:#f7fdff; padding:8px 12px; font-weight:600; cursor:pointer; }
    .btn.primary{ background:#bff0e6; border-color:#9dd7c9; color:#0a6758; }

    .post-head{ display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .post-user{ display:flex; gap:10px; align-items:center; }
    .role-badge{ background:#e9f7fb; border:1px solid #cfe5ea; border-radius:10px; padding:2px 8px; font-size:.78rem; color:#2a6f81; }
    .cat-pill{ background:#ecfff7; border:1px solid #c7e8dd; border-radius:999px; padding:4px 10px; color:#27695a; font-weight:600; font-size:.82rem; }
    .post-title{ font-weight:700; font-size:1.1rem; color:#214b55; line-height:1.35; }
    .post-footer{ display:flex; gap:16px; color:#567882; font-weight:600; }
    .muted{ color:#89a7af; }

    /* ===== Modal base ===== */
    .modal-overlay{ position:fixed; inset:0; background:rgba(9,32,41,.58); backdrop-filter:blur(2px); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; pointer-events:none; transition:opacity .25s ease; }
    .modal-overlay.show{ opacity:1; pointer-events:auto; }
    .modal-card{ width:440px; max-width:95vw; background:#fff; border:1px solid #d8ecf0; border-radius:16px; box-shadow:0 18px 46px rgba(16,61,108,.18); padding:18px; opacity:0; transform:translateY(12px) scale(.96); }
    .modal-overlay.show .modal-card{ animation:modalIn .28s cubic-bezier(.2,.8,.2,1) forwards; }
    .modal-card.closing{ animation:modalOut .22s ease forwards; }
    @keyframes modalIn{ from{opacity:0; transform:translateY(12px) scale(.96);} to{opacity:1; transform:translateY(0) scale(1);} }
    @keyframes modalOut{ from{opacity:1; transform:translateY(0) scale(1);} to{opacity:0; transform:translateY(10px) scale(.96);} }

    .btn-primary{ padding:8px 14px; border-radius:10px; background:var(--teal); color:#fff; border:1px solid #16798e; cursor:pointer; transition:transform .06s ease, filter .12s ease; }
    .btn-cancel{ padding:8px 14px; border-radius:10px; background:#6b7785; color:#fff; border:1px solid #5c6672; cursor:pointer; transition:transform .06s ease, filter .12s ease; }
    .btn-primary:hover, .btn-cancel:hover{ filter:brightness(.96); }
    .btn-primary:active, .btn-cancel:active{ transform:translateY(1px); }
    .btn-primary:focus, .btn-cancel:focus{ outline:2px solid #b1e7fa; outline-offset:2px; }
    .form-row{ display:grid; gap:8px; margin:10px 0; }
    .form-row label{ font-weight:800; color:#16798e; margin-bottom:6px; }
    .form-row input{ border:1.5px solid #cfe5ea; border-radius:12px; padding:11px 14px; font-size:15px; outline:none; transition:border-color .15s, box-shadow .15s; }
    .form-row input::placeholder{ color:#9eb6bf; }
    .form-row input:focus{ border-color:var(--teal); box-shadow:0 0 0 3px rgba(177,231,250,.55); }

    /* Manage list rows */
    .manage-list .mrow{ display:flex; align-items:center; justify-content:space-between; gap:10px; border:1px solid #d8ecf0; border-radius:10px; padding:8px 10px; background:#fff; }
    .manage-list .handle{ cursor:grab; user-select:none; opacity:.8; }
    @media (max-width:700px){
      .forum-container{ padding-left:10px!important; padding-right:10px!important; }
    }

    .compose { position: relative; }
    .popover .chip { margin:4px; }

    .att-grid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap:10px; margin-top:6px;
    }
    .att-thumb{ display:block; border:1px solid #e5f0f3; border-radius:10px; overflow:hidden; }
    .att-thumb img{ width:100%; height:100px; object-fit:cover; display:block; }
    .att-file{
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 10px; border:1px solid #e5f0f3; border-radius:10px; background:#fff;
      text-decoration:none; color:#214b55; font-weight:600;
    }
    .att-file:hover{ background:#f6fdff; }

    .forum-main{ display:grid; gap:22px; }

    /* ===== Horizontal Scrollbar Styling for Forum Chips ===== */
.forum-chips {
  scrollbar-width: thin;               /* Firefox */
  scrollbar-color: #8cd0db transparent; /* thumb + track color for Firefox */
}

.forum-chips::-webkit-scrollbar {
  height: 8px;                         /* mas manipis, hindi nakaka-distract */
}

.forum-chips::-webkit-scrollbar-track {
  background: transparent;             /* transparent track para di makasagabal */
}

.forum-chips::-webkit-scrollbar-thumb {
  background: linear-gradient(90deg, #8cd0db, #1e8fa2); /* teal gradient */
  border-radius: 20px;                 /* rounded pill look */
  border: 2px solid #f0fafd;           /* may kaunting border para lumutang */
}

.forum-chips::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(90deg, #1e8fa2, #0e6d7e); /* darker teal on hover */
}

/* === Compose Card Styling === */
.compose {
  background: #ffffff;
  border: 1.5px solid #d8ecf0;
  border-radius: 18px;
  box-shadow: 0 6px 20px rgba(16,61,108,0.06);
  padding: 16px;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 14px;
}

/* Avatar stays small and round */
.compose .avatar {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #c7e1e6;
}

/* Fields wrapper */
.compose-fields {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
}

/* Title input */
.compose input[type="text"] {
  border: 1.5px solid #cfe5ea;
  border-radius: 14px;
  padding: 11px 14px;
  font-size: 15px;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.compose input[type="text"]:focus {
  border-color: var(--teal);
  box-shadow: 0 0 0 3px rgba(30,143,162,.15);
}

/* Body textarea */
.compose textarea {
  border: 1.5px solid #cfe5ea;
  border-radius: 14px;
  padding: 12px 14px;
  font-size: 15px;
  min-height: 80px;
  resize: vertical;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.compose textarea:focus {
  border-color: var(--teal);
  box-shadow: 0 0 0 3px rgba(30,143,162,.15);
}

/* Placeholder color */
.compose input::placeholder,
.compose textarea::placeholder {
  color: #9eb6bf;
}

/* Actions row */
.compose-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: flex-end;
  flex-wrap: wrap;
  margin-top: 6px;
}

/* Attach + Post buttons */
.compose-actions .btn {
  border: 1px solid #bfe0e6;
  border-radius: 12px;
  background: #f7fdff;
  padding: 8px 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s ease;
}
.compose-actions .btn:hover {
  background: #e6f7fb;
}

.compose-actions .btn.primary {
  background: var(--teal);
  border-color: #16798e;
  color: #fff;
}
.compose-actions .btn.primary:hover {
  background: var(--teal-dark);
}
.btn-like.liked .pf-ico { transform: scale(1.05); }
.btn-like.liked { color:#0a6758; }
/* Make the title a clickable link but keep the same look */
.post-title-link{
  font-weight:700; font-size:1.1rem; color:#214b55; line-height:1.35;
  text-decoration:none;
}
.post-title-link:hover{ text-decoration:underline; }

/* Compact pill-style link beside the actions */
.view-thread-link{
  display:inline-flex; align-items:center; gap:6px;
  text-decoration:none; font-weight:600; color:#1e8fa2;
  background:#f7fdff; border:1px solid #cfe5ea; border-radius:10px;
  padding:4px 10px;
}
.view-thread-link:hover{ background:#e6f7fb; }

@media (max-width:700px){
  .post-footer{ flex-wrap:wrap; gap:12px; }
  .view-thread-link{ width:100%; justify-content:center; }
}

.forum-container {
  padding-left: 2px;
  padding-right: 2px
}

/* Default (multi-image grid) */
.att-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px;
}

/* Thumbnails in grid */
.att-grid .att-thumb img {
  width: 100%;
  height: 180px;           /* dati 100px — pinalaki ko nang konti */
  object-fit: cover;
  display: block;
  border-radius: 10px;
}

/* FB-like for SINGLE image: center + larger */
.att-grid:has(.att-thumb:only-child) {
  display: flex;               /* center the only image */
  justify-content: center;
}

.att-grid:has(.att-thumb:only-child) .att-thumb {
  width: min(680px, 100%);     /* max width of image block */
  border: 1px solid #e5f0f3;
  border-radius: 12px;
  overflow: hidden;
}

.att-grid:has(.att-thumb:only-child) .att-thumb img {
  width: 100%;
  height: auto;                /* keep aspect ratio */
  max-height: 520px;           /* limit height */
  object-fit: contain;         /* wag i-crop */
  display: block;
  background: #fafcfd;         /* subtle bg habang naglo-load */
}

  </style>
</head>
<body>
  <?php $page='forum'; ?>
  <?php include 'includes/sidebar.php'; ?>
  <main class="content-area">
    <?php include 'includes/header.php'; ?>

    <div class="forum-container">
      <div class="forum-wrap">
        <!-- Header -->
        <div class="forum-header">
          <div class="forum-title">Forum</div>
          <div class="forum-actions">
            <div class="forum-search">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" placeholder="Search posts, tags, people…">
            </div>
            <!-- <select class="forum-sort">
              <option>Newest</option><option>Most Liked</option>
              <option>Most Commented</option><option>Unanswered</option>
              <option>Bookmarked</option>
            </select> -->
          </div>
        </div>

        <!-- Chips row + fixed settings button -->
        <div class="forum-chips-wrap">
          <div class="forum-chips" id="chipsRow">
            <a class="chip <?= $activeSlug==='all' ? 'active' : '' ?>" href="forum.php?cat=all" data-cat="all">All</a>
            <?php foreach ($cats as $c): ?>
              <a class="chip <?= $activeSlug===$c['slug'] ? 'active' : '' ?>"
                 href="forum.php?cat=<?= htmlspecialchars($c['slug']) ?>"
                 data-cat="<?= htmlspecialchars($c['slug']) ?>">
                <?= htmlspecialchars($c['name']) ?>
              </a>
            <?php endforeach; ?>
          </div>
          <!-- Manage (edit/delete/add) — fixed at the far right -->
          <button class="chip-manage" id="btnManageCats" title="Manage Categories" aria-label="Manage categories">
            <i class="fa-solid fa-gear"></i>
          </button>
        </div>

        <!-- Feed -->
        <div class="forum-grid">
          <section class="forum-main">
            <form class="compose" id="composeForm" enctype="multipart/form-data" method="post">
              <img class="avatar" id="meAvatar" alt="me" src="" />
              <div class="compose-fields" style="display:flex;flex-direction:column;gap:10px;width:100%;">
                <input type="text" id="composeTitle" name="title"
                      placeholder="Share something… (title or quick tip)">

                <textarea id="composeBody" name="body" rows="2"
                          placeholder="Write details..."></textarea>

                <div class="compose-actions">
                  <span class="muted" id="postTarget" style="margin-right:auto">
                    Posting to: <?= htmlspecialchars(ucwords(str_replace('-', ' ', $activeSlug))) ?>
                  </span>

                  <!-- Attach -->
                  <input type="file" id="composeFiles" name="files[]" multiple style="display:none" />
                  <button type="button" class="btn" id="btnAttach">Attach</button>
                  <span class="muted" id="attachCount" style="display:none"></span>

                  <!-- Post -->
                  <button type="submit" class="btn primary">Post</button>
                </div>
              </div>
            </form>

            <?php if (empty($posts)): ?>
              <div class="muted" style="padding:12px 4px">No posts yet for this category.</div>
            <?php else: ?>
              <?php foreach ($posts as $p):
                $catName = $p['category_name'] ?: 'General';
                $att = [];
                if (!empty($p['attachments_json'])) {
                  $att = json_decode($p['attachments_json'], true) ?: [];
                }
              ?>
             <article
                class="post"
                data-post-id="<?= (int)$p['post_id'] ?>"
                id="post-<?= (int)$p['post_id'] ?>"
>
                <div class="post-head">
                  <div class="post-user">
                    <img class="avatar" src="<?= htmlspecialchars(web_path_from_admin($p['profile_pic'])) ?>" alt="avatar">
                    <div>
                      <div style="display:flex;gap:8px;align-items:center">
                        <strong><?= htmlspecialchars($p['full_name'] ?: 'User') ?></strong>
                        <span class="muted">• <?= htmlspecialchars(date('M d, Y g:ia', strtotime($p['created_at']))) ?></span>
                      </div>
                      <div class="muted">
                      <?= (strtolower($p['user_role'] ?? '') === 'admin') ? 'Administrator' : 'Member' ?>
                    </div>

                    </div>
                  </div>
                  <div><span class="cat-pill"><?= htmlspecialchars($catName) ?></span></div>
                </div>

                <a class="post-title-link" href="view_post.php?post_id=<?= (int)$p['post_id'] ?>">
                    <?= htmlspecialchars($p['title']) ?>
                  </a>

                <?php if (!empty($p['body'])): ?>
                  <div class="muted"><?= nl2br(htmlspecialchars($p['body'])) ?></div>
                <?php endif; ?>

                <?php if (!empty($att)): ?>
                  <div class="att-grid">
                    <?php foreach ($att as $f):
                      $raw = isset($f['path']) ? $f['path'] : '';
                      if ($raw && preg_match('#^https?://#', $raw)) {
                        $path = $raw;
                      } else {
                        $path = '../' . ltrim($raw, '/');
                      }
                      $name = $f['name'] ?? basename($path);
                      $type = $f['type'] ?? '';
                      $isImg = strpos($type, 'image/') === 0;
                    ?>
                      <?php if ($isImg): ?>
                        <a href="<?= htmlspecialchars($path) ?>" target="_blank" class="att-thumb"><img src="<?= htmlspecialchars($path) ?>" alt="<?= htmlspecialchars($name) ?>"></a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars($path) ?>" target="_blank" class="att-file">
                          <i class="fa-solid fa-paperclip"></i> <?= htmlspecialchars($name) ?>
                        </a>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="post-footer">
                    <!-- LIKE -->
                    <button
                      type="button"
                      class="pf-action btn-like <?= !empty($p['liked_by_me']) ? 'liked' : '' ?>"
                      data-post-id="<?= (int)$p['post_id'] ?>"
                      data-liked="<?= !empty($p['liked_by_me']) ? 1 : 0 ?>"
                      aria-label="<?= !empty($p['liked_by_me']) ? 'Unlike' : 'Like' ?>"
                      title="<?= !empty($p['liked_by_me']) ? 'Unlike' : 'Like' ?>"
                      style="display:inline-flex;align-items:center;gap:6px;border:0;background:transparent;cursor:pointer;font:inherit;color:inherit"
                    >
                      <span class="pf-ico">👍</span>
                      <span class="pf-like-count"><?= (int)$p['likes'] ?></span>
                    </button>
                    <button
                      type="button"
                      class="pf-action btn-comments"
                      data-post-id="<?= (int)$p['post_id'] ?>"
                      aria-expanded="false"
                      style="display:inline-flex;align-items:center;gap:6px;border:0;background:transparent;cursor:pointer;font:inherit;color:inherit"
                    >
                      <span>💬</span><span class="pf-comment-count"><?= (int)$p['comments'] ?></span>
                    </button>
                      <!-- NEW: VIEW THREAD placed right after 💬 -->
  <a class="pf-action view-thread-link" href="view_post.php?post_id=<?= (int)$p['post_id'] ?>">
    View Thread →
  </a>
                    <!-- VIEWS (display only) -->
                    <div class="pf-action" style="display:inline-flex;align-items:center;gap:6px;">
                      <span>👁</span><span class="pf-view-count"><?= (int)$p['views'] ?></span>
                    </div>
                    <!-- READ MORE (link to post page; we’ll wire comments later) -->
                  </div>

                  <!-- Comments panel (hidden until 💬 is clicked) -->
                  <div class="comments-wrap" data-post-id="<?= (int)$p['post_id'] ?>" style="display:none; margin-top:10px;">
                    <div class="comments-list" id="comments-<?= (int)$p['post_id'] ?>"
                        style="display:grid; gap:10px; margin-bottom:8px;"></div>

                    <!-- Load more row (hidden kung wala nang next) -->
                    <div class="comments-more" data-next-page="1" style="display:none; margin-bottom:8px;">
                      <button type="button" class="btn" data-act="load-more">Load more</button>
                    </div>

                    <!-- Composer -->
                    <form class="comment-form" data-post-id="<?= (int)$p['post_id'] ?>" style="display:grid; gap:8px;">
                      <textarea name="body" rows="2" placeholder="Write a comment…" required
                        style="border:1.5px solid #cfe5ea; border-radius:12px; padding:10px 12px; outline:none;"></textarea>
                      <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="submit" class="btn primary">Post Comment</button>
                      </div>
                    </form>
                  </div>

              </article>
              <?php endforeach; ?>
            <?php endif; ?>

          </section>
        </div>
      </div>
    </div>
  </main>

  <!-- Add Category Modal -->
  <div class="modal-overlay" id="addCatModal">
    <div class="modal-card">
      <h3>Add Category</h3>
      <form id="addCatForm">
        <div class="form-row">
          <label for="cat_name">Category name</label>
          <input type="text" id="cat_name" name="name" placeholder="e.g. Safety & Guidelines" required>
        </div>
        <div class="modal-actions">
          <button type="button" id="btnCancelCat" class="btn-cancel">Cancel</button>
          <button type="submit" class="btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Manage Categories Modal -->
  <div class="modal-overlay" id="manageCatModal">
    <div class="modal-card" style="max-width:560px">
      <h3>Manage Categories</h3>
      <div class="muted" style="margin-bottom:10px">Rename, remove, or create new categories.</div>

      <!-- NEW: Add form on top -->
      <form id="mAddForm" class="madd" style="display:flex; gap:8px; margin-bottom:12px;">
        <input type="text" id="mAddName" name="name" placeholder="New category name…" maxlength="100"
               style="flex:1; border:1.5px solid #cfe5ea; border-radius:10px; padding:10px 12px;">
        <button type="submit" class="btn-primary" style="white-space:nowrap;">Add</button>
      </form>

      <div id="manageList" class="manage-list" style="display:grid; gap:8px; max-height:50vh; overflow:auto;">
        <!-- rows injected by JS -->
      </div>

      <div class="modal-actions">
        <button type="button" id="btnCloseManage" class="btn-cancel">Close</button>
      </div>
    </div>
  </div>

<script>
// 1) Chips row: vertical wheel -> horizontal scroll + sidebar toggles
document.addEventListener('DOMContentLoaded', () => {
  const row = document.getElementById('chipsRow');
  row?.addEventListener('wheel', (e) => {
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) { row.scrollLeft += e.deltaY; e.preventDefault(); }
  }, { passive:false });

  // Sidebar (mobile)
  const sidebar   = document.getElementById('sidebar');
  const hamburger = document.getElementById('hamburger-btn');
  const overlay   = document.getElementById('sidebar-overlay');

  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('active');
    document.body.style.overflow = "hidden";
  }
  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('active');
    document.body.style.overflow = "";
  }

  hamburger?.addEventListener('click', (e) => {
    e.stopPropagation();
    if (sidebar?.classList.contains('open')) closeSidebar(); else openSidebar();
  });
  overlay?.addEventListener('click', closeSidebar);
  document.querySelectorAll('.sidebar-nav a').forEach(link => {
    link.addEventListener('click', () => { if (window.innerWidth <= 700) closeSidebar(); });
  });
  document.addEventListener('keydown', (e) => { if (e.key === "Escape" && sidebar?.classList.contains('open')) closeSidebar(); });
  window.addEventListener('resize', () => { if (window.innerWidth > 700) closeSidebar(); });
});

// 2) Expose categories from PHP (keep this exactly)
window.CATS = <?= json_encode($cats, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
</script>

<script>
// 3) Manage (Add inside modal + Edit + Delete) via unified endpoint
document.addEventListener('DOMContentLoaded', () => {
  const chipsRow = document.getElementById('chipsRow');

  const mOverlay = document.getElementById('manageCatModal');
  const mCard    = mOverlay.querySelector('.modal-card');
  const btnMng   = document.getElementById('btnManageCats');
  const btnClose = document.getElementById('btnCloseManage');
  const list     = document.getElementById('manageList');

  // Add form inside Manage modal
  const mAddForm = document.getElementById('mAddForm');
  const mAddName = document.getElementById('mAddName');

  function openManage(){ renderList(); mCard.classList.remove('closing'); mOverlay.classList.add('show'); document.body.classList.add('modal-open'); }
  function hideManageNow(){ mOverlay.classList.remove('show'); document.body.classList.remove('modal-open'); mCard.classList.remove('closing'); }
  function closeManage(){ mCard.classList.add('closing'); const done=()=>{ mCard.removeEventListener('animationend',done); hideManageNow(); }; mCard.addEventListener('animationend',done); setTimeout(hideManageNow,260); }

  btnMng?.addEventListener('click', openManage);
  btnClose?.addEventListener('click', closeManage);
  mOverlay?.addEventListener('click', (e)=>{ if (e.target === mOverlay) closeManage(); });

  function renderList(){
    list.innerHTML = '';
    (window.CATS || []).forEach(cat => {
      const row = document.createElement('div');
      row.className = 'mrow';
      row.innerHTML = `
        <div style="display:flex; align-items:center; gap:10px;">
          <span class="handle" title="(Optional) drag">☰</span>
          <strong>${escapeHtml(cat.name)}</strong>
          <span class="muted" style="font-size:.85em">/ ${escapeHtml(cat.slug)}</span>
        </div>
        <div style="display:flex; gap:8px;">
          <button class="btn" data-act="edit" data-id="${cat.category_id}"><i class="fa-solid fa-pen"></i></button>
          <button class="btn" data-act="delete" data-id="${cat.category_id}" style="border-color:#f2c6c6; background:#fff5f5;"><i class="fa-solid fa-trash"></i></button>
        </div>
      `;
      list.appendChild(row);
    });
  }

  // Edit / Delete handlers
  list.addEventListener('click', async (e)=>{
    const b = e.target.closest('button.btn'); if (!b) return;
    const id  = b.dataset.id;
    const act = b.dataset.act;
    const cat = (window.CATS || []).find(c => String(c.category_id) === String(id));
    if (!cat) return;

    // ----- EDIT -----
    if (act === 'edit') {
      const res = await Swal.fire({
        title:'Rename category',
        input:'text', inputValue: cat.name,
        inputAttributes:{ maxlength:100 },
        showCancelButton:true, confirmButtonText:'Save', confirmButtonColor:'#1e8fa2'
      });
      const v = (res.value || '').trim();
      if (!res.isConfirmed || !v || v === cat.name) return;

      try{
        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('category_id', id);
        fd.append('name', v);
        const r  = await fetch('forum_category_add.php', { method:'POST', body: fd });
        const d  = await r.json().catch(()=>null);
        if (!r.ok || !d || !d.ok) throw new Error((d && d.message) || `HTTP ${r.status}`);

        const oldSlug = cat.slug;
        cat.name = d.category.name;
        cat.slug = d.category.slug;

        const chip = chipsRow.querySelector(`.a.chip[data-cat="${CSS.escape(oldSlug)}"], .chip[data-cat="${CSS.escape(oldSlug)}"]`);
        const chipEl = chipsRow.querySelector(`.chip[data-cat="${CSS.escape(oldSlug)}"]`);
        if (chipEl) {
          chipEl.dataset.cat = cat.slug;
          chipEl.textContent = cat.name;
          if (chipEl.tagName === 'A') {
            chipEl.href = `forum.php?cat=${encodeURIComponent(cat.slug)}`;
          }
        }

        renderList();
        Swal.fire({ icon:'success', title:'Renamed', timer:1000, showConfirmButton:false });

        // Optional: if currently on that category, update "Posting to:" and URL without reload
        try {
          const urlParams = new URLSearchParams(location.search);
          const currentSlug = urlParams.get('cat') || 'all';
          if (currentSlug === oldSlug) {
            const postTarget = document.getElementById('postTarget');
            if (postTarget) postTarget.textContent = `Posting to: ${cat.name}`;
            history.replaceState({}, '', `?cat=${encodeURIComponent(cat.slug)}`);
          }
        } catch {}
      }catch(err){
        Swal.fire({ icon:'error', title:'Update failed', text:String(err), confirmButtonColor:'#1e8fa2' });
      }
    }

    // ----- DELETE -----
    if (act === 'delete') {
      const ok = await Swal.fire({
        icon:'warning', title:`Delete "${cat.name}"?`,
        text:'This cannot be undone.',
        showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#d33'
      }).then(r=>r.isConfirmed);
      if (!ok) return;

      try{
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('category_id', id);
        const r  = await fetch('forum_category_add.php', { method:'POST', body: fd });
        const d  = await r.json().catch(()=>null);
        if (!r.ok || !d || !d.ok) throw new Error((d && d.message) || `HTTP ${r.status}`);

        // remove from cache + chips
        window.CATS = (window.CATS || []).filter(c => String(c.category_id) !== String(id));
        const chip = chipsRow.querySelector(`.chip[data-cat="${CSS.escape(cat.slug)}"]`);
        chip?.remove();

        renderList();
        Swal.fire({ icon:'success', title:'Deleted', timer:1000, showConfirmButton:false });
      }catch(err){
        Swal.fire({ icon:'error', title:'Delete failed', text:String(err), confirmButtonColor:'#1e8fa2' });
      }
    }
  });

  // ----- ADD inside Manage modal -----
  mAddForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const v = (mAddName.value || '').trim();
    if (!v) { await Swal.fire({ icon:'warning', title:'Category name required', confirmButtonColor:'#1e8fa2' }); mAddName.focus(); return; }

    try{
      const fd = new FormData();
      fd.append('action', 'add');
      fd.append('name', v);
      const r  = await fetch('forum_category_add.php', { method:'POST', body: fd });
      const d  = await r.json().catch(()=>null);
      if (!r.ok || !d || !d.ok) throw new Error((d && d.message) || `HTTP ${r.status}`);

      (window.CATS || (window.CATS=[])).push(d.category);

      // Append new chip at the end of the scroll row (as a link)
      const chip = document.createElement('a');
      chip.className = 'chip';
      chip.dataset.cat = d.category.slug;
      chip.href = `forum.php?cat=${encodeURIComponent(d.category.slug)}`;
      chip.textContent = d.category.name;
      chipsRow.appendChild(chip);

      renderList();
      mAddName.value = '';
      Swal.fire({ icon:'success', title:'Category added', timer:1000, showConfirmButton:false });
    }catch(err){
      Swal.fire({ icon:'error', title:'Failed to add', text:String(err), confirmButtonColor:'#1e8fa2' });
    }
  });

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  /* ========== Avatar ========== */
  const me = window.CURRENT_USER || { profile_pic: '../uploads/default.png' };
  const meAvatar = document.getElementById('meAvatar');
  if (meAvatar && me.profile_pic) meAvatar.src = me.profile_pic;

  /* ========== Attach files (inline) ========== */
  const form        = document.getElementById('composeForm');
  const fileInput   = document.getElementById('composeFiles');
  const btnAttach   = document.getElementById('btnAttach');
  const attachCount = document.getElementById('attachCount');

  btnAttach?.addEventListener('click', () => fileInput?.click());
  fileInput?.addEventListener('change', () => {
    if (!attachCount) return;
    if (fileInput.files.length) {
      attachCount.style.display = 'inline';
      attachCount.textContent = `${fileInput.files.length} file(s)`;
    } else {
      attachCount.style.display = 'none';
      attachCount.textContent = '';
    }
  });

  /* ========== Submit Post (admin/forum_post_create.php) ========== */
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);

    // gamitin ang kasalukuyang filter sa URL bilang category
    fd.set('category', '<?= $activeSlug ?>');

    const r = await fetch('forum_post_create.php', { method:'POST', body: fd });
    const d = await r.json().catch(() => null);

    if (!r.ok || !d || !d.ok) {
      Swal.fire({
        icon: 'error',
        title: 'Post failed',
        text: (d && d.message) || ('HTTP ' + r.status),
        confirmButtonColor: '#1e8fa2'
      });
      return;
    }

    // reset UI
    form.reset();
    if (attachCount){ attachCount.style.display = 'none'; attachCount.textContent = ''; }

    Swal.fire({ icon:'success', title:'Posted', timer:700, showConfirmButton:false });
    setTimeout(() => { window.location.reload(); }, 750); // stay on same ?cat
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(location.search);
  const target = params.get('highlight');
  if (!target) return;

  const el = document.querySelector(`[data-post-id="${CSS.escape(target)}"]`);
  if (el) {
    el.scrollIntoView({behavior:'smooth', block:'center'});
    el.style.transition = 'background 0.6s';
    el.style.background = '#fff9d6';
    setTimeout(()=> el.style.background = '', 1200);
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(location.search);
  const target = params.get('highlight');
  if (!target) return;

  const el = document.querySelector(`[data-post-id="${CSS.escape(target)}"]`);
  if (el) {
    el.scrollIntoView({behavior:'smooth', block:'center'});
    el.style.transition = 'background 0.6s';
    el.style.background = '#fff9d6';
    setTimeout(()=> el.style.background = '', 1200);
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const root = document;

  root.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like');
    if (!btn) return;

    // Prevent double-click spam
    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    const countEl = btn.querySelector('.pf-like-count');
    const likedNow = btn.classList.contains('liked');
    const postId   = btn.getAttribute('data-post-id');

    // Optimistic UI
    let oldCount = parseInt(countEl?.textContent || '0', 10);
    let newCount = likedNow ? Math.max(oldCount - 1, 0) : oldCount + 1;
    countEl.textContent = String(newCount);
    btn.classList.toggle('liked', !likedNow);
    btn.setAttribute('aria-label', likedNow ? 'Like' : 'Unlike');
    btn.setAttribute('title',      likedNow ? 'Like' : 'Unlike');

    try {
      const res = await fetch('forum_like_toggle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + encodeURIComponent(postId)
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      // Sync with server truth
      countEl.textContent = String(data.likes_count ?? newCount);
      btn.classList.toggle('liked', !!data.liked);
      btn.setAttribute('aria-label', data.liked ? 'Unlike' : 'Like');
      btn.setAttribute('title',      data.liked ? 'Unlike' : 'Like');
      btn.dataset.liked = data.liked ? '1' : '0';
    } catch (err) {
      // Revert optimistic UI on error
      countEl.textContent = String(oldCount);
      btn.classList.toggle('liked', likedNow);
      btn.setAttribute('aria-label', likedNow ? 'Unlike' : 'Like');
      btn.setAttribute('title',      likedNow ? 'Unlike' : 'Like');
      Swal.fire({ icon:'error', title:'Like failed', text: String(err), confirmButtonColor:'#1e8fa2' });
    } finally {
      btn.dataset.busy = '0';
    }
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // small helpers
  function esc(s){ return (s ?? '').toString().replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function webPathFromAdmin(p){
    if (!p) return '../uploads/default.png';
    if (/^https?:\/\//i.test(p) || p.startsWith('/')) return p;
    if (p.startsWith('uploads/')) return '../' + p;
    return '../uploads/' + p.replace(/^\/+/, '');
  }
  function renderCommentItem(c){
    const when = c.created_at ? esc(c.created_at) : 'Just now';
    const pic  = webPathFromAdmin(c.profile_pic);
    const name = esc(c.full_name || 'User');
    const body = esc(c.body || '');
    return `
      <div class="comment-item" data-comment-id="${c.comment_id}" style="display:grid; grid-template-columns:auto 1fr; gap:10px; border:1px solid #e8f3f6; border-radius:10px; padding:10px;">
        <img src="${pic}" alt="" class="avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #cfe5ea;" />
        <div style="display:grid; gap:4px;">
          <div style="display:flex; gap:8px; align-items:center;">
            <strong>${name}</strong>
            <span class="muted" style="font-size:.85em;">${when}</span>
          </div>
          <div style="white-space:pre-wrap; color:#2a515c;">${body}</div>
        </div>
      </div>
    `;
  }

  // delegated submit para sa lahat ng comment forms
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.comment-form');
    if (!form) return;
    e.preventDefault();

    // prevent double submit
    if (form.dataset.busy === '1') return;
    form.dataset.busy = '1';

    const postId   = form.getAttribute('data-post-id');
    const textarea = form.querySelector('textarea[name="body"]');
    const bodyText = (textarea?.value || '').trim();

    if (!bodyText) {
      form.dataset.busy = '0';
      return;
    }

    // optimistic UX: disable button
    const btn = form.querySelector('button[type="submit"]');
    const oldBtnText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Posting…'; }

    try {
      // send
      const fd = new URLSearchParams();
      fd.set('post_id', postId);
      fd.set('body', bodyText);

      const res = await fetch('forum_comment_create.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      // append new comment
      const list = document.getElementById('comments-' + postId);
      if (list) {
        list.insertAdjacentHTML('afterbegin', renderCommentItem(data.comment));
      }

      // increment 💬 counter in the same post card
      const postEl = form.closest('article.post');
      const cEl = postEl?.querySelector('.pf-comment-count');
      if (cEl) {
        const n = parseInt(cEl.textContent || '0', 10);
        cEl.textContent = String((isNaN(n) ? 0 : n) + 1);
      }

      // reset field
      if (textarea) textarea.value = '';
    } catch (err) {
      Swal.fire({ icon:'error', title:'Comment failed', text:String(err), confirmButtonColor:'#1e8fa2' });
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = oldBtnText || 'Post Comment'; }
      form.dataset.busy = '0';
    }
  });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  function esc(s){ return (s ?? '').toString().replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function webPathFromAdmin(p){
    if (!p) return '../uploads/default.png';
    if (/^https?:\/\//i.test(p) || p.startsWith('/')) return p;
    if (p.startsWith('uploads/')) return '../' + p;
    return '../uploads/' + p.replace(/^\/+/, '');
  }
  function renderCommentItem(c){
    return `
      <div class="comment-item" data-comment-id="${c.comment_id}"
           style="display:grid; grid-template-columns:auto 1fr; gap:10px; border:1px solid #e8f3f6; border-radius:10px; padding:10px;">
        <img src="${webPathFromAdmin(c.profile_pic)}" class="avatar"
             style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #cfe5ea;" alt="">
        <div style="display:grid; gap:4px;">
          <div style="display:flex; gap:8px; align-items:center;">
            <strong>${esc(c.full_name || 'User')}</strong>
            <span class="muted" style="font-size:.85em;">${esc(c.created_at || 'Just now')}</span>
          </div>
          <div style="white-space:pre-wrap; color:#2a515c;">${esc(c.body || '')}</div>
        </div>
      </div>
    `;
  }
  function show(el){ el && (el.style.display = ''); }
  function hide(el){ el && (el.style.display = 'none'); }

  // Toggle comments panel on 💬
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-comments');
    if (!btn) return;

    const postId = btn.getAttribute('data-post-id');
    const wrap   = document.querySelector(`.comments-wrap[data-post-id="${CSS.escape(postId)}"]`);
    if (!wrap) return;

    // toggle
    const isOpen = wrap.style.display !== 'none';
    if (isOpen) {
      hide(wrap);
      btn.setAttribute('aria-expanded', 'false');
      return;
    }
    show(wrap);
    btn.setAttribute('aria-expanded', 'true');

    // first-open lazy load
    if (wrap.dataset.loaded === '1') return;
    wrap.dataset.loaded = '1';

    const list = wrap.querySelector('.comments-list');
    const more = wrap.querySelector('.comments-more');
    const loadBtn = more?.querySelector('[data-act="load-more"]');

    // simple loading placeholder
    list.innerHTML = '<div class="muted">Loading comments…</div>';

    try {
      const fd = new URLSearchParams();
      fd.set('post_id', postId);
      fd.set('page', '1');
      fd.set('limit', '10');

      const res = await fetch('forum_comment_list.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      // render
      list.innerHTML = data.comments.length
        ? data.comments.map(renderCommentItem).join('')
        : '<div class="muted">No comments yet. Be the first to comment.</div>';

      // load-more state
      if (data.has_more) {
        show(more); more.dataset.nextPage = data.next_page;
      } else {
        hide(more);
      }
    } catch (err) {
      list.innerHTML = '<div class="muted">Failed to load comments.</div>';
      console.error(err);
    }
  });

  // Load more
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.comments-more [data-act="load-more"]');
    if (!btn) return;
    const more = btn.closest('.comments-more');
    const wrap = more.closest('.comments-wrap');
    const postId = wrap?.getAttribute('data-post-id');
    const list = wrap?.querySelector('.comments-list');
    if (!postId || !list) return;

    const next = parseInt(more.dataset.nextPage || '2', 10);
    btn.disabled = true; const old = btn.textContent; btn.textContent = 'Loading…';

    try {
      const fd = new URLSearchParams();
      fd.set('post_id', postId);
      fd.set('page', String(next));
      fd.set('limit', '10');

      const res = await fetch('forum_comment_list.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      if (data.comments.length) {
        list.insertAdjacentHTML('beforeend', data.comments.map(renderCommentItem).join(''));
      }
      if (data.has_more) {
        more.dataset.nextPage = data.next_page;
        btn.disabled = false; btn.textContent = old;
      } else {
        hide(more);
      }
    } catch (err) {
      btn.disabled = false; btn.textContent = old;
      Swal.fire({ icon:'error', title:'Failed to load more', text:String(err), confirmButtonColor:'#1e8fa2' });
    }
  });

  // Submit comment (re-use of Step 3 logic but now inside hidden panel)
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.comment-form');
    if (!form) return;
    e.preventDefault();

    if (form.dataset.busy === '1') return;
    form.dataset.busy = '1';

    const postId   = form.getAttribute('data-post-id');
    const textarea = form.querySelector('textarea[name="body"]');
    const bodyText = (textarea?.value || '').trim();
    const list     = form.closest('.comments-wrap')?.querySelector('.comments-list');

    if (!bodyText) { form.dataset.busy = '0'; return; }

    const btn = form.querySelector('button[type="submit"]');
    const oldBtnText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Posting…'; }

    try {
      const fd = new URLSearchParams();
      fd.set('post_id', postId);
      fd.set('body', bodyText);

      const res = await fetch('forum_comment_create.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      // prepend newest comment
      list?.insertAdjacentHTML('beforeend', renderCommentItem(data.comment));

      // increment 💬 counter in footer
      const postEl = form.closest('article.post');
      const cEl = postEl?.querySelector('.pf-comment-count');
      if (cEl) { cEl.textContent = String((parseInt(cEl.textContent||'0',10) || 0) + 1); }

      if (textarea) textarea.value = '';
    } catch (err) {
      Swal.fire({ icon:'error', title:'Comment failed', text:String(err), confirmButtonColor:'#1e8fa2' });
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = oldBtnText || 'Post Comment'; }
      form.dataset.busy = '0';
    }
  });
});
</script>

</body>
</html>
