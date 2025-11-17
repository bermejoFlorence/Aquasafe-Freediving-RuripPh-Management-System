<?php
// client/forum.php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'client') {
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
$me = ['full_name'=>'Client', 'profile_pic'=>'uploads/default.png'];
$uid = (int)($_SESSION['user_id'] ?? 0);

/* helper: convert DB path to a web path that works from /client/ */
function web_path_from_client($p) {
  if (!$p) return '../uploads/default.png';
  if (preg_match('#^https?://#', $p) || str_starts_with($p, '/')) return $p;
  if (substr($p, 0, 8) === 'uploads/') return '../' . $p;
  return '../uploads/' . ltrim($p, '/');
}

if ($uid) {
  if ($stmt = $conn->prepare("SELECT full_name,
                                     COALESCE(profile_pic,'uploads/default.png') AS profile_pic
                              FROM user WHERE user_id=? LIMIT 1")) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
      $row['profile_pic'] = web_path_from_client($row['profile_pic']);
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

/* ---- Determine active category from URL ---- */
$activeSlug = 'all';
if (isset($_GET['cat']) && $_GET['cat'] !== '') {
  $activeSlug = preg_replace('/[^a-z0-9_\-]/i', '', $_GET['cat']); // sanitize
  $known = array_column($cats, 'slug');
  if ($activeSlug !== 'all' && !in_array($activeSlug, $known, true)) {
    $activeSlug = 'all';
  }
}

// === Step 1: detect General Announcement(s) and decide if posting is allowed ===
$generalSlug = null;
foreach ($cats as $c) {
  $n = strtolower(trim($c['name'] ?? ''));
  if ($n === 'general announcement' || $n === 'general announcements') {
    $generalSlug = $c['slug'];
    break;
  }
}
// Hide "General Announcements" from the chips (client view)
if ($generalSlug) {
  foreach ($cats as $i => $c) {
    if (($c['slug'] ?? null) === $generalSlug) {
      unset($cats[$i]);
    }
  }
  // reindex array para malinis ang foreach sa rendering
  $cats = array_values($cats);
}


// Block posting when in 'all' or in General Announcement(s)
$postingAllowed = !(
  $activeSlug === 'all' ||
  ($generalSlug && $activeSlug === $generalSlug)
);


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

// client/forum.php
$sql = "SELECT
  p.post_id, p.title, p.body, p.attachments_json,
  p.likes, p.comments, p.bookmarks, p.views,
  p.created_at,
  u.full_name,
  COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic,
  u.role AS user_role,              -- ✅ ito ang tama
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
$sql .= "ORDER BY p.created_at DESC LIMIT 50";

if ($categoryId !== null) {
  $st = $conn->prepare($sql);
  $st->bind_param('ii', $uid, $categoryId); // order: user_id, then categoryId
} else {
  $st = $conn->prepare($sql);
  $st->bind_param('i', $uid);
}
if ($st && $st->execute()) {
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) {
    $row['profile_pic'] = web_path_from_client($row['profile_pic']);
    $posts[] = $row;
  }
  $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>AquaSafe RuripPh Client Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="styles/style.css" />
  <script>
    window.CURRENT_USER = <?= json_encode($me, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script>
  window.POSTING_ALLOWED = <?= $postingAllowed ? 'true' : 'false' ?>;
</script>

<style>
/* =========================================================
   AQUASAFE FORUM — CLEANED & ORGANIZED STYLES
   (same visuals/behavior; deduped + grouped logically)
   ========================================================= */

/* ------------------------------
   CSS VARIABLES
------------------------------ */
:root{
  /* Palette + tokens */
  --teal: #1e8fa2;
  --teal-dark: #0e6d7e;
  --ink: #0e6d7e;
  --chip-border: #cfe5ea;

  /* Layout + components */
  --outer-pad: 14px;
  --radius-xl: 18px;
  --border: 1px solid #d8ecf0;

  /* Comments “breathing room” */
  --reply-right-gap: 26px;   /* tweak 24–32px if needed */
  --reply-left-indent: 46px; /* aligns with avatar width */
}

/* ------------------------------
   LAYOUT
------------------------------ */
.forum-container{
  max-width:100% !important;
  margin:0 !important;
  padding-left:14px !important;
  padding-right:20px !important;
  box-sizing:border-box;
  display:grid;
  gap:16px;
}
.forum-wrap{ display:grid; grid-template-rows:auto auto 1fr; gap:16px; }
.forum-main{ display:grid; gap:22px; }

@media (max-width:700px){
  .forum-container{ padding-left:10px !important; padding-right:10px !important; }
}

/* ------------------------------
   HEADER + ACTIONS
------------------------------ */
.forum-header{
  position:sticky; top:64px; z-index:5;
  background:linear-gradient(180deg, var(--main-bg, #eaf6fb) 30%, rgba(255,255,255,0));
  padding-top:6px;
  display:flex; gap:12px; align-items:center; justify-content:space-between;
}
.forum-title{ font-weight:700; font-size:1.45rem; color:var(--ink); }
.forum-actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

.forum-search{
  display:flex; align-items:center; gap:8px;
  background:#fff; border:var(--border); border-radius:14px;
  padding:9px 12px; flex:1;
}
.forum-search input{ border:0; outline:0; width:100%; background:transparent; font:inherit; color:inherit; }
.forum-sort{ border:var(--border); border-radius:12px; padding:8px 12px; background:#fff; }

/* ------------------------------
   CATEGORY CHIPS (horizontal)
------------------------------ */
.forum-chips-wrap{ display:flex; align-items:center; gap:8px; }

.forum-chips{
  flex:1 1 auto;
  display:flex; gap:10px; flex-wrap:nowrap;
  overflow-x:auto; overflow-y:hidden;
  padding:0 8px 8px 0;
  -webkit-overflow-scrolling:touch;
  scroll-behavior:smooth;
  position:relative;
  scrollbar-gutter:stable;
  scrollbar-width:thin;               /* Firefox */
  scrollbar-color:#8cd0db transparent;/* Firefox */
}
.forum-chips::-webkit-scrollbar{ height:8px; }
.forum-chips::-webkit-scrollbar-track{ background:transparent; }
.forum-chips::-webkit-scrollbar-thumb{
  background:linear-gradient(90deg, #8cd0db, #1e8fa2);
  border-radius:20px; border:2px solid #f0fafd;
}
.forum-chips::-webkit-scrollbar-thumb:hover{
  background:linear-gradient(90deg, #1e8fa2, #0e6d7e);
}
.forum-chips::before,
.forum-chips::after{
  content:""; position:absolute; top:0; bottom:8px; width:28px; pointer-events:none;
}
.forum-chips::before{ left:0;  background:linear-gradient(90deg, #fff, rgba(255,255,255,0)); }
.forum-chips::after { right:0; background:linear-gradient(270deg, #fff, rgba(255,255,255,0)); }

.chip,.chip-add,.chip-manage{
  flex:0 0 auto;
  border:1px solid var(--chip-border);
  border-radius:999px;
  padding:10px 16px;
  background:#fff;
  font-weight:700;
  color:#1b6e7f;
  text-decoration:none;
  display:inline-flex; align-items:center;
}
.chip.active{ background:#e6f7fb; border-color:#bfe6ee; }
.chip-add,.chip-manage{
  width:40px; height:40px; padding:0;
  display:inline-flex; align-items:center; justify-content:center;
  border:1px dashed #b8dbe3; background:#f7fdff; cursor:pointer;
  box-shadow:0 6px 16px rgba(30,143,162,.08);
}
.chip-add:hover,.chip-manage:hover{ background:#e9f8ff; }
.chip-manage{ position:sticky; right:0; margin-left:4px; }

/* ------------------------------
   CARDS (compose & post)
------------------------------ */
.forum-grid{ display:grid; grid-template-columns:1fr !important; gap:18px; }
.compose,.post{
  background:#fff; border:var(--border); border-radius:var(--radius-xl);
  box-shadow:0 6px 20px rgba(16,61,108,.06);
}
.post{ padding:14px 16px; display:grid; gap:10px; }

/* Avatars */
.avatar{
  width:40px; height:40px; border-radius:50%;
  background:#dbeff4; border:1px solid #c7e1e6; object-fit:cover;
}

/* ------------------------------
   COMPOSE
------------------------------ */
.compose{
  padding:14px 16px;
  display:grid; grid-template-columns:auto 1fr; gap:12px;
  position:relative;
}
.compose .avatar{
  width:46px; height:46px; border-radius:50%;
  object-fit:cover; border:2px solid #c7e1e6;
}
.compose-fields{ display:flex; flex-direction:column; gap:12px; width:100%; }

.compose input[type="text"],
.compose textarea{
  border:1.5px solid #cfe5ea; border-radius:14px;
  padding:11px 14px; font-size:15px; outline:none;
  transition:border-color .15s, box-shadow .15s;
}
.compose textarea{ padding:12px 14px; min-height:80px; resize:vertical; }
.compose input::placeholder,
.compose textarea::placeholder{ color:#9eb6bf; }
.compose input:focus,
.compose textarea:focus{ border-color:var(--teal); box-shadow:0 0 0 3px rgba(30,143,162,.15); }

.compose-actions{
  display:flex; align-items:center; gap:10px;
  justify-content:flex-end; flex-wrap:wrap; margin-top:6px;
}

/* Generic buttons */
.btn{
  border:1px solid #bfe0e6; border-radius:12px;
  background:#f7fdff; padding:8px 12px; font-weight:600; cursor:pointer;
  transition:all .2s ease;
}
.btn:hover{ background:#e6f7fb; }
.btn.primary{ background:var(--teal); border-color:#16798e; color:#fff; }
.btn.primary:hover{ background:var(--teal-dark); }

/* Like state */
.btn-like.liked{ color:#0a6758; }
.btn-like.liked .pf-ico{ transform:scale(1.05); }

/* ------------------------------
   POST CONTENT
------------------------------ */
.post-head{ display:flex; justify-content:space-between; align-items:center; gap:8px; }
.post-user{ display:flex; gap:10px; align-items:center; }
.role-badge{
  background:#e9f7fb; border:1px solid #cfe5ea; border-radius:10px;
  padding:2px 8px; font-size:.78rem; color:#2a6f81;
}
.cat-pill{
  background:#ecfff7; border:1px solid #c7e8dd; border-radius:999px;
  padding:4px 10px; color:#27695a; font-weight:600; font-size:.82rem;
}
.post-title{ font-weight:700; font-size:1.1rem; color:#214b55; line-height:1.35; }
.post-title-link{
  font-weight:700; font-size:1.1rem; color:#214b55; line-height:1.35; text-decoration:none;
}
.post-title-link:hover{ text-decoration:underline; }

.post-footer{ display:flex; gap:16px; color:#567882; font-weight:600; }
.muted{ color:#89a7af; }

.view-thread-link{
  display:inline-flex; align-items:center; gap:6px;
  text-decoration:none; font-weight:600; color:var(--teal);
  background:#f7fdff; border:1px solid #cfe5ea; border-radius:10px;
  padding:4px 10px;
}
.view-thread-link:hover{ background:#e6f7fb; }

@media (max-width:700px){
  .post-footer{ flex-wrap:wrap; gap:12px; }
  .view-thread-link{ width:100%; justify-content:center; }
}

/* ------------------------------
   ATTACHMENTS
------------------------------ */
.att-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(160px,1fr));
  gap:10px; margin-top:6px;
}
.att-thumb{ display:block; border:1px solid #e5f0f3; border-radius:10px; overflow:hidden; }
.att-thumb img{ width:100%; height:180px; object-fit:cover; display:block; border-radius:10px; }
.att-file{
  display:inline-flex; align-items:center; gap:8px;
  padding:8px 10px; border:1px solid #e5f0f3; border-radius:10px; background:#fff;
  text-decoration:none; color:#214b55; font-weight:600;
}
.att-file:hover{ background:#f6fdff; }

/* Single-image presentation */
.att-grid:has(.att-thumb:only-child){ display:flex; justify-content:center; }
.att-grid:has(.att-thumb:only-child) .att-thumb{
  width:min(680px, 100%); border:1px solid #e5f0f3; border-radius:12px; overflow:hidden;
}
.att-grid:has(.att-thumb:only-child) .att-thumb img{
  width:100%; height:auto; max-height:520px; object-fit:contain; display:block; background:#fafcfd;
}

/* ------------------------------
   COMMENTS + REPLIES
------------------------------ */
/* Comment card */
.comments-wrap .comment-item{
  background:#fff; border:1px solid #e8f3f6; border-radius:12px; padding:10px;
  display:grid; grid-template-columns:auto 1fr; gap:10px;
}
.comments-wrap .comment-item + .comment-item{ margin-top:10px; }

/* Actions row (Reply pill) */
.comment-actions{ display:flex; gap:12px; margin-top:6px; color:#567882; font-weight:600; }
.comments-wrap .comment-actions .link{
  display:inline-flex; align-items:center; gap:6px;
  padding:6px 10px; background:#f7fdff; border:1px solid #cfe5ea;
  border-radius:999px; color:var(--teal); font-weight:600; cursor:pointer;
}
.comments-wrap .comment-actions .link:hover{ background:#e6f7fb; }

/* Reply thread line + indent */
.comments-wrap .comment-children{
  margin-left:var(--reply-left-indent) !important;
  margin-right:var(--reply-right-gap);
  padding-left:12px;
  border-left:2px solid #e6f2f6;
  display:grid; gap:10px;
}

/* Inline reply composer (under a comment) */
.comments-wrap .reply-form{
  display:none;
  grid-template-columns:36px 1fr !important;
  gap:10px; align-items:flex-start;
  margin:10px 0 0 var(--reply-left-indent) !important;
  margin-right:var(--reply-right-gap) !important;
  width:auto; box-sizing:border-box;
}
.comments-wrap .reply-form .avatar{
  width:36px; height:36px; border-radius:50%;
  object-fit:cover; border:1px solid #cfe5ea;
}
.comments-wrap .reply-form textarea{
  width:100%; min-height:90px;
  border:1.5px solid #cfe5ea; border-radius:12px; padding:10px 12px; outline:none;
  transition:border-color .15s, box-shadow .15s;
}
.comments-wrap .reply-form textarea:focus{
  border-color:var(--teal); box-shadow:0 0 0 3px rgba(30,143,162,.15);
}
.comments-wrap .reply-form .actions{ display:flex; justify-content:flex-end; gap:8px; margin-top:8px; }
.comments-wrap .reply-form .btn.primary{ background:var(--teal); border-color:#16798e; color:#fff; }
.comments-wrap .reply-form .btn.btn-cancel{ background:#6b7785; color:#fff; border:1px solid #5c6672; }

/* Bottom “Write a comment…” composer */
.comments-wrap .comment-form{
  margin-left:var(--reply-left-indent);
  margin-right:var(--reply-right-gap);
  width:auto; box-sizing:border-box;
}

/* Keep right spacing consistent across sections */
.comments-wrap{ padding-bottom:2px; }
.comments-wrap .comments-list{ margin-right:var(--reply-right-gap); }
.comments-wrap .comments-more{
  margin-left:var(--reply-left-indent);
  margin-right:var(--reply-right-gap);
  display:flex; justify-content:flex-end;
}

/* Mobile tighter spacing */
@media (max-width:700px){
  :root{
    --reply-right-gap: 14px;
    --reply-left-indent: 36px;
  }
}

/* ------------------------------
   MODAL (generic)
------------------------------ */
.modal-overlay{
  position:fixed; inset:0;
  background:rgba(9,32,41,.58); backdrop-filter:blur(2px);
  display:flex; align-items:center; justify-content:center;
  z-index:1000; opacity:0; pointer-events:none; transition:opacity .25s ease;
}
.modal-overlay.show{ opacity:1; pointer-events:auto; }
.modal-card{
  width:440px; max-width:95vw;
  background:#fff; border:1px solid #d8ecf0; border-radius:16px;
  box-shadow:0 18px 46px rgba(16,61,108,.18);
  padding:18px; opacity:0; transform:translateY(12px) scale(.96);
}
.modal-overlay.show .modal-card{ animation:modalIn .28s cubic-bezier(.2,.8,.2,1) forwards; }
.modal-card.closing{ animation:modalOut .22s ease forwards; }

@keyframes modalIn{ from{opacity:0; transform:translateY(12px) scale(.96);} to{opacity:1; transform:translateY(0) scale(1);} }
@keyframes modalOut{ from{opacity:1; transform:translateY(0) scale(1);} to{opacity:0; transform:translateY(10px) scale(.96);} }

/* Modal buttons (kept for other dialogs) */
.btn-primary{
  padding:8px 14px; border-radius:10px;
  background:var(--teal); color:#fff; border:1px solid #16798e;
  cursor:pointer; transition:transform .06s ease, filter .12s ease;
}
.btn-cancel{
  padding:8px 14px; border-radius:10px;
  background:#6b7785; color:#fff; border:1px solid #5c6672;
  cursor:pointer; transition:transform .06s ease, filter .12s ease;
}
.btn-primary:hover,.btn-cancel:hover{ filter:brightness(.96); }
.btn-primary:active,.btn-cancel:active{ transform:translateY(1px); }
.btn-primary:focus,.btn-cancel:focus{ outline:2px solid #b1e7fa; outline-offset:2px; }

/* ------------------------------
   MISC: manage list
------------------------------ */
.manage-list .mrow{
  display:flex; align-items:center; justify-content:space-between; gap:10px;
  border:1px solid #d8ecf0; border-radius:10px; padding:8px 10px; background:#fff;
}
.manage-list .handle{ cursor:grab; user-select:none; opacity:.8; }
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
            <select class="forum-sort">
              <option>Newest</option><option>Most Liked</option>
              <option>Most Commented</option><option>Unanswered</option>
              <option>Bookmarked</option>
            </select>
          </div>
        </div>

        <!-- Chips row (no settings gear for client) -->
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
        </div>

        <!-- Feed -->
        <div class="forum-grid">
          <section class="forum-main">
            <?php if ($postingAllowed): ?>
  <form class="compose" id="composeForm" enctype="multipart/form-data" method="post">
    <img class="avatar" id="meAvatar" alt="me" src="" />
    <div class="compose-fields" style="display:flex;flex-direction:column;gap:12px;width:100%;">
      <input type="text" id="composeTitle" name="title" placeholder="Share something… (title or quick tip)">
      <textarea id="composeBody" name="body" rows="2" placeholder="Write details..."></textarea>

      <div class="compose-actions">
        <span class="muted" id="postTarget" style="margin-right:auto">
          Posting to: <?= htmlspecialchars(ucwords(str_replace('-', ' ', $activeSlug))) ?>
        </span>
        <input type="file" id="composeFiles" name="files[]" multiple style="display:none" />
        <button type="button" class="btn" id="btnAttach">Attach</button>
        <span class="muted" id="attachCount" style="display:none"></span>
        <button type="submit" class="btn primary">Post</button>
      </div>
    </div>
  </form>
<?php else: ?>
  <div class="compose" style="padding:14px 16px;">
    <div class="muted">
<?php
        if ($activeSlug === 'all') {
          echo 'Please choose a specific category to post.';
        } elseif ($generalSlug && $activeSlug === $generalSlug) {
          echo 'Only administrators can post in General Announcements.';
        }
      ?>
    </div>
  </div>
<?php endif; ?>


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
             <article class="post" data-post-id="<?= (int)$p['post_id'] ?>">

                <div class="post-head">
                  <div class="post-user">
                    <img class="avatar" src="<?= htmlspecialchars($p['profile_pic']) ?>" alt="avatar">
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

                <div class="post-title"><?= htmlspecialchars($p['title']) ?></div>
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
                <!-- VIEWS count -->
                <div class="pf-action" style="display:inline-flex;align-items:center;gap:6px;">
                  <span>👁</span><span class="pf-view-count"><?= (int)$p['views'] ?></span>
                </div>

              </div>
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
<script>
/* =========================
   Forum UI – One JS to rule them all
   (likes, comments, replies, compose, sidebar)
   ========================= */
document.addEventListener('DOMContentLoaded', () => {
  /* ---------- Helpers ---------- */
  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));
  const esc = (s) => (s ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const webPathFromAdmin = (p) => {
    if (!p) return '../uploads/default.png';
    if (/^https?:\/\//i.test(p) || p.startsWith('/')) return p;
    if (p.startsWith('uploads/')) return '../' + p;
    return '../uploads/' + p.replace(/^\/+/, '');
  };
  const show = (el, display='') => { if (el) el.style.display = display; };
  const hide = (el) => { if (el) el.style.display = 'none'; };

  /* ---------- Global renderers ---------- */
  // One canonical renderer for a comment (with Reply button + inline reply composer)
  function renderCommentItem(c, postId){
  const when  = esc(c.created_at || 'Just now');
  const pic   = webPathFromAdmin(c.profile_pic);
  const name  = esc(c.full_name || 'User');
  const body  = esc(c.body || '');
  const rCount = Number(c.replies_count || 0); // lalabas kapag na-update na natin ang API

  const threadUrl = `view_post.php?post_id=${encodeURIComponent(postId)}&focus_comment=${encodeURIComponent(c.comment_id)}`;

  return `
    <div class="comment-item" data-comment-id="${c.comment_id}" data-post-id="${postId}"
         style="display:grid; grid-template-columns:auto 1fr; gap:10px; border:1px solid #e8f3f6; border-radius:10px; padding:10px;">
      <img src="${pic}" class="avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #cfe5ea;" alt="">
      <div style="display:grid; gap:4px;">
        <div style="display:flex; gap:8px; align-items:center;">
          <strong>${name}</strong>
          <span class="muted" style="font-size:.85em;">${when}</span>
        </div>
        <div style="white-space:pre-wrap; color:#2a515c;">${body}</div>

        <div class="comment-actions">
          <a class="link" href="${threadUrl}">↩️ Reply</a>
          ${rCount > 0 ? `<a class="link" href="${threadUrl}">Replies: ${rCount}</a>` : ''}
        </div>
      </div>
    </div>
  `;
}

  // Render a small reply (child) item (one level deep for now)
  function renderReplyItem(r){
    const when = esc(r.created_at || 'Just now');
    const pic  = webPathFromAdmin(r.profile_pic);
    const name = esc(r.full_name || 'User');
    const body = esc(r.body || '');
    return `
      <div class="comment-item" data-comment-id="${r.comment_id}"
           style="display:grid; grid-template-columns:auto 1fr; gap:10px; border:1px solid #eef6f8; border-radius:10px; padding:10px;">
        <img src="${pic}" class="avatar" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #cfe5ea;" alt="">
        <div style="display:grid; gap:4px;">
          <div style="display:flex; gap:8px; align-items:center;">
            <strong>${name}</strong>
            <span class="muted" style="font-size:.82em;">${when}</span>
          </div>
          <div style="white-space:pre-wrap; color:#2a515c;">${body}</div>
        </div>
      </div>
    `;
  }

  /* ---------- Sidebar + chips horizontal wheel ---------- */
  (function initNav(){
    const row = $('#chipsRow');
    row?.addEventListener('wheel', (e) => {
      if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) { row.scrollLeft += e.deltaY; e.preventDefault(); }
    }, { passive:false });

    const sidebar   = $('#sidebar');
    const hamburger = $('#hamburger-btn');
    const overlay   = $('#sidebar-overlay');
    const openSidebar  = () => { sidebar?.classList.add('open'); overlay?.classList.add('active'); document.body.style.overflow = "hidden"; };
    const closeSidebar = () => { sidebar?.classList.remove('open'); overlay?.classList.remove('active'); document.body.style.overflow = ""; };

    hamburger?.addEventListener('click', (e) => { e.stopPropagation(); (sidebar?.classList.contains('open') ? closeSidebar() : openSidebar()); });
    overlay?.addEventListener('click', closeSidebar);
    $$('.sidebar-nav a').forEach(a => a.addEventListener('click', () => { if (window.innerWidth <= 700) closeSidebar(); }));
    document.addEventListener('keydown', (e) => { if (e.key === "Escape" && sidebar?.classList.contains('open')) closeSidebar(); });
    window.addEventListener('resize', () => { if (window.innerWidth > 700) closeSidebar(); });
  })();

  /* ---------- Highlight post (if ?highlight=ID) ---------- */
  (function initHighlight(){
    const params = new URLSearchParams(location.search);
    const target = params.get('highlight');
    if (!target) return;
    const el = document.querySelector(`[data-post-id="${CSS.escape(target)}"]`);
    if (el) {
      el.scrollIntoView({behavior:'smooth', block:'center'});
      el.style.transition = 'background 0.6s';
      el.style.background = '#fff9d6';
      setTimeout(() => el.style.background = '', 1200);
    }
  })();

  /* ---------- Like / Unlike (optimistic) ---------- */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like');
    if (!btn) return;

    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    const countEl = btn.querySelector('.pf-like-count');
    const likedNow = btn.classList.contains('liked');
    const postId   = btn.getAttribute('data-post-id');

    const oldCount = parseInt(countEl?.textContent || '0', 10);
    const newCount = likedNow ? Math.max(oldCount - 1, 0) : oldCount + 1;

    // Optimistic
    countEl.textContent = String(newCount);
    btn.classList.toggle('liked', !likedNow);
    btn.setAttribute('aria-label', likedNow ? 'Like' : 'Unlike');
    btn.setAttribute('title',      likedNow ? 'Like' : 'Unlike');

    try {
      const res  = await fetch('forum_like_toggle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
        body: 'post_id=' + encodeURIComponent(postId)
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      // Sync
      countEl.textContent = String(data.likes_count ?? newCount);
      btn.classList.toggle('liked', !!data.liked);
      btn.setAttribute('aria-label', data.liked ? 'Unlike' : 'Like');
      btn.setAttribute('title',      data.liked ? 'Unlike' : 'Like');
      btn.dataset.liked = data.liked ? '1' : '0';
    } catch (err) {
      // Revert on error
      countEl.textContent = String(oldCount);
      btn.classList.toggle('liked', likedNow);
      btn.setAttribute('aria-label', likedNow ? 'Unlike' : 'Like');
      btn.setAttribute('title',      likedNow ? 'Unlike' : 'Like');
      Swal.fire({ icon:'error', title:'Like failed', text: String(err), confirmButtonColor:'#1e8fa2' });
    } finally {
      btn.dataset.busy = '0';
    }
  });

  /* ---------- Open comments panel + lazy load ---------- */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-comments');
    if (!btn) return;

    const postId = btn.getAttribute('data-post-id');
    const wrap   = document.querySelector(`.comments-wrap[data-post-id="${CSS.escape(postId)}"]`);
    if (!wrap) return;

    const isOpen = wrap.style.display !== 'none';
    if (isOpen) {
      hide(wrap);
      btn.setAttribute('aria-expanded', 'false');
      return;
    }
    show(wrap);
    btn.setAttribute('aria-expanded', 'true');

    // First open → fetch
    if (wrap.dataset.loaded === '1') return;
    wrap.dataset.loaded = '1';

    const list = wrap.querySelector('.comments-list');
    const more = wrap.querySelector('.comments-more');

    list.innerHTML = '<div class="muted">Loading comments…</div>';
    try {
      const fd = new URLSearchParams({ post_id: postId, page: '1', limit: '10' });
      const res  = await fetch('forum_comment_list.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      list.innerHTML = data.comments.length
        ? data.comments.map(c => renderCommentItem(c, postId)).join('')
        : '<div class="muted">No comments yet. Be the first to comment.</div>';

      if (data.has_more) { show(more); more.dataset.nextPage = data.next_page; } else { hide(more); }
    } catch (err) {
      list.innerHTML = '<div class="muted">Failed to load comments.</div>';
      console.error(err);
    }
  });

  /* ---------- Load more comments ---------- */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.comments-more [data-act="load-more"]');
    if (!btn) return;
    const more   = btn.closest('.comments-more');
    const wrap   = more.closest('.comments-wrap');
    const postId = wrap?.getAttribute('data-post-id');
    const list   = wrap?.querySelector('.comments-list');
    if (!postId || !list) return;

    const next = parseInt(more.dataset.nextPage || '2', 10);
    btn.disabled = true; const old = btn.textContent; btn.textContent = 'Loading…';

    try {
      const fd = new URLSearchParams({ post_id: postId, page: String(next), limit: '10' });
      const res  = await fetch('forum_comment_list.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      if (data.comments.length) list.insertAdjacentHTML('beforeend', data.comments.map(c => renderCommentItem(c, postId)).join(''));
      if (data.has_more) { more.dataset.nextPage = data.next_page; btn.disabled = false; btn.textContent = old; }
      else { hide(more); }
    } catch (err) {
      btn.disabled = false; btn.textContent = old;
      Swal.fire({ icon:'error', title:'Failed to load more', text:String(err), confirmButtonColor:'#1e8fa2' });
    }
  });

  /* ---------- Post a top-level comment ---------- */
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
    const old = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Posting…'; }

    try {
      const fd = new URLSearchParams({ post_id: postId, body: bodyText });
      const res  = await fetch('forum_comment_create.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      // Append new comment at bottom (keep order)
      list?.insertAdjacentHTML('beforeend', renderCommentItem(data.comment, postId));

      // bump 💬 count
      const postEl = form.closest('article.post');
      const cEl = postEl?.querySelector('.pf-comment-count');
      if (cEl) cEl.textContent = String((parseInt(cEl.textContent||'0',10)||0) + 1);

      if (textarea) textarea.value = '';
    } catch (err) {
      Swal.fire({ icon:'error', title:'Comment failed', text:String(err), confirmButtonColor:'#1e8fa2' });
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = old || 'Post Comment'; }
      form.dataset.busy = '0';
    }
  });

  /* ---------- Compose new post ---------- */
  (function initCompose(){
    const me       = window.CURRENT_USER || { profile_pic: '../uploads/default.png' };
    const meAvatar = $('#meAvatar'); if (meAvatar && me.profile_pic) meAvatar.src = me.profile_pic;

    const form        = $('#composeForm');
    const fileInput   = $('#composeFiles');
    const btnAttach   = $('#btnAttach');
    const attachCount = $('#attachCount');

    btnAttach?.addEventListener('click', () => fileInput?.click());
    fileInput?.addEventListener('change', () => {
      if (!attachCount) return;
      if (fileInput.files.length) { show(attachCount, 'inline'); attachCount.textContent = `${fileInput.files.length} file(s)`; }
      else { hide(attachCount); attachCount.textContent = ''; }
    });

    form?.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (window.POSTING_ALLOWED === false) {
        Swal.fire({icon:'warning',title:'Posting not allowed',text:'Only admins can post here or please pick a specific category.',confirmButtonColor:'#1e8fa2'});
        return;
      }

      const currentCat = '<?= $activeSlug ?>';
      if (!currentCat || currentCat === 'all') {
        Swal.fire({icon:'warning',title:'Pick a category',text:'Please choose a category tab before posting.',confirmButtonColor:'#1e8fa2'});
        return;
      }

      const titleEl = $('#composeTitle');
      const title = (titleEl?.value || '').trim();
      if (!title) {
        Swal.fire({icon:'warning',title:'Title is required',text:'Please add a title.',confirmButtonColor:'#1e8fa2'});
        return;
      }

      const fd = new FormData(form);
      fd.set('category', currentCat);

      const submitBtn = form.querySelector('button[type="submit"]');
      const oldText   = submitBtn?.textContent;
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Posting…'; }

      try {
        const r = await fetch('forum_post_create.php', { method:'POST', body: fd });
        const d = await r.json().catch(() => null);
        if (!r.ok || !d || !d.ok) throw new Error(d?.message || ('HTTP ' + r.status));

        form.reset(); hide(attachCount); attachCount.textContent = '';
        window.location = 'view_post.php?post_id=' + encodeURIComponent(d.post_id);
      } catch (err) {
        Swal.fire({ icon:'error', title:'Post failed', text:String(err), confirmButtonColor:'#1e8fa2' });
      } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = oldText || 'Post'; }
      }
    });
  })();
});
</script>

</body>
</html>
