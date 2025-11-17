<?php
// client/forum_post_view.php
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

/* ---- Current user ---- */
$me  = ['full_name'=>'Admin', 'profile_pic'=>'uploads/default.png'];
$uid = (int)($_SESSION['user_id'] ?? 0);

/* helper: convert DB path to a web path that works from /admin/ */
function web_path_from_admin($p) {
  if (!$p) return '../uploads/default.png';
  if (preg_match('#^https?://#', $p) || str_starts_with($p, '/')) return $p;
  if (substr($p, 0, 8) === 'uploads/') return '../' . $p;
  return '../uploads/' . ltrim($p, '/');
  
}

if ($uid) {
  if ($stmt = $conn->prepare("SELECT full_name, COALESCE(profile_pic,'uploads/default.png') AS profile_pic
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

/* ---- Get target post_id ---- */
$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
if ($postId <= 0) {
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Not Found</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>
        <script>
          Swal.fire({icon:'error',title:'Post not found',text:'Invalid post id.',confirmButtonColor:'#1e8fa2'})
          .then(()=>{ window.location='forum.php'; });
        </script></body></html>";
  exit;
}

/* ---- Fetch ONE post ---- */
$p = null;
$sql = "SELECT
          p.post_id, p.title, p.body, p.attachments_json,
          p.likes, p.comments, p.bookmarks, p.views,
          p.created_at,
          u.user_id AS post_author_id,          -- ← ADD
          u.full_name,
          COALESCE(u.profile_pic,'uploads/default.png') AS profile_pic,
          u.role AS user_role,                  -- 'admin' | 'member'
          fc.name  AS category_name,
          fc.slug  AS category_slug,
          EXISTS(SELECT 1 FROM forum_post_like fpl
                 WHERE fpl.post_id = p.post_id AND fpl.user_id = ?) AS liked_by_me
        FROM forum_post p
        LEFT JOIN user u ON u.user_id = p.user_id
        LEFT JOIN forum_category fc ON fc.category_id = p.category_id
        WHERE p.post_id = ?
        LIMIT 1";

$st = $conn->prepare($sql);
if (!$st) {
  die('<pre>Prepare failed: ' . htmlspecialchars($conn->error) . '</pre>');
}
$st->bind_param('ii', $uid, $postId);
$st->execute();
$res = $st->get_result();
if ($res) $p = $res->fetch_assoc();
$st->close();

if (!$p) {
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Not Found</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body>
        <script>
          Swal.fire({icon:'error',title:'Post not found',text:'The post you are looking for no longer exists.',confirmButtonColor:'#1e8fa2'})
          .then(()=>{ window.location='forum.php'; });
        </script></body></html>";
  exit;
}

/* Normalized fields */
$p['profile_pic'] = web_path_from_admin($p['profile_pic']);
$catName = $p['category_name'] ?: 'General';
$att = [];
if (!empty($p['attachments_json'])) {
  $att = json_decode($p['attachments_json'], true) ?: [];
}
// === Record unique view (1x per user per post) ===
if ($uid && $postId) {
    // Optional: huwag bilangin ang author ng post bilang view
    // if ((int)($p['user_id'] ?? 0) === $uid) {
    //     // skip
    // } else {
        if ($stmt = $conn->prepare("INSERT IGNORE INTO forum_post_view (post_id, user_id) VALUES (?, ?)")) {
            $stmt->bind_param('ii', $postId, $uid);
            $stmt->execute();
            $firstTime = ($stmt->affected_rows === 1);
            $stmt->close();

            if ($firstTime) {
                if ($up = $conn->prepare("UPDATE forum_post SET views = views + 1 WHERE post_id = ?")) {
                    $up->bind_param('i', $postId);
                    $up->execute();
                    $up->close();
                    // reflect agad sa current page render
                    $p['views'] = (int)($p['views'] ?? 0) + 1;
                }
            }
        }
    // }
}

$isPostByAdmin  = (strtolower($p['user_role'] ?? '') === 'admin');
$canReportPost  = ($uid > 0)
               && ($uid !== (int)($p['post_author_id'] ?? 0))
               && !$isPostByAdmin;
/* Page title: <Category> - <Post Title> */
$pageTitle = 'Forum — ' . ($catName ?: 'General') . ' — ' . ($p['title'] ?: 'Post');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="styles/style.css" />
  <script>
     window.CURRENT_USER = <?= json_encode($me, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  window.CURRENT_USER_ID = <?= (int)$uid ?>;                               /* ← ADD */
  window.VIEW_POST_ID    = <?= (int)$p['post_id'] ?>;
  window.POST_AUTHOR_ID  = <?= (int)($p['post_author_id'] ?? 0) ?>;        /* ← ADD */
  window.POST_AUTHOR_ROLE= <?= json_encode(strtolower($p['user_role'] ?? '')) ?>; /* ← ADD */
  </script>
  <style>
    :root{
      --outer-pad:14px; --radius-xl:18px; --border:1px solid #d8ecf0;
      --teal:#1e8fa2; --teal-dark:#0e6d7e; --ink:#0e6d7e;
    }
    .post-page-container{ max-width:100%!important; margin:0!important; padding-left:14px!important; padding-right:20px!important; box-sizing:border-box; display:grid; gap:16px; }
    .post{ background:#fff; border:var(--border); border-radius:var(--radius-xl); box-shadow:0 6px 20px rgba(16,61,108,.06); padding:14px 16px; display:grid; gap:10px; }
    .avatar{ width:40px; height:40px; border-radius:50%; background:#dbeff4; border:1px solid #c7e1e6; object-fit:cover; }
    .post-head{ display:flex; justify-content:space-between; align-items:center; gap:8px; }
    .post-user{ display:flex; gap:10px; align-items:center; }
    .cat-pill{ background:#ecfff7; border:1px solid #c7e8dd; border-radius:999px; padding:4px 10px; color:#27695a; font-weight:600; font-size:.82rem; }
    .post-title{ font-weight:700; font-size:1.2rem; color:#214b55; line-height:1.35; }
    .post-footer{ display:flex; gap:16px; color:#567882; font-weight:600; }
    .btn{ border:1px solid #bfe0e6; border-radius:12px; background:#f7fdff; padding:8px 12px; font-weight:600; cursor:pointer; }
    .btn.primary{ background:var(--teal); border-color:#16798e; color:#fff; }
    .muted{ color:#89a7af; }
    .att-grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap:10px; margin-top:6px; }
    .att-thumb{ display:block; border:1px solid #e5f0f3; border-radius:10px; overflow:hidden; }
    .att-thumb img{ width:100%; height:100px; object-fit:cover; display:block; }
    .att-file{ display:inline-flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid #e5f0f3; border-radius:10px; background:#fff; text-decoration:none; color:#214b55; font-weight:600; }
    .att-file:hover{ background:#f6fdff; }
    .btn-like.liked .pf-ico { transform: scale(1.05); }
    .btn-like.liked { color:#0a6758; }

    /* Comments box */
    .comments-wrap{ display:grid; gap:10px; margin-top:10px; }
    .comment-item{ display:grid; grid-template-columns:auto 1fr; gap:10px; border:1px solid #e8f3f6; border-radius:10px; padding:10px; }
    .comment-item .avatar{ width:36px; height:36px; }
    .comment-form textarea{ border:1.5px solid #cfe5ea; border-radius:12px; padding:10px 12px; outline:none; min-height:70px; }
    .forum-header-simple{
  display:flex; align-items:center; justify-content:space-between;
  margin:6px 0 4px;
}
.forum-title{ font-weight:700; font-size:1.45rem; color:#0e6d7e; }
.btn.btn-small{ padding:6px 10px; border-radius:10px; font-size:.92rem; }

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
/* Reply form should stretch full width */
.comment-item .reply-form{
  width:100%;
  display:grid;                  /* textarea row + actions row */
  grid-template-columns: 1fr;
  margin-top:6px;
}
.comment-item .reply-form textarea{
  width:100%;
  box-sizing:border-box;
  min-height:70px;
  border:1.5px solid #cfe5ea;
  border-radius:12px;
  padding:10px 12px;
  outline:none;
  resize:vertical;               /* optional */
}
.comment-item .reply-form .reply-actions{
  display:flex;
  gap:8px;
  justify-content:flex-end;
  margin-top:6px;
}
/* Header row (name + time + small actions on the right) */
.c-head{ display:flex; align-items:center; justify-content:space-between; }

/* Tiny icon button for reply actions */
.btn.icon{
  border:1px solid #e0eef2; background:#fff;
  padding:4px 6px; border-radius:8px; font-size:.85rem; cursor:pointer;
}

/* Optional: show on hover para malinis ang itsura */
.comment-item.reply .btn.icon{ opacity:0; transition:opacity .15s ease; }
.comment-item.reply:hover .btn.icon{ opacity:1; }
/* —— Report modal polish ——————————————————————————— */
.swk-report .swal2-popup{border-radius:16px;padding:22px 22px 16px}
.swk-report .swal2-title{font-size:1.6rem;margin-bottom:6px;color:#0e6d7e}
.swk-report .swal2-html-container{margin:0;text-align:left}

.report-form .label{font-weight:700;margin:6px 0}
.report-form .radio{
  display:flex;align-items:center;gap:10px;
  padding:8px 10px;margin:0 0 8px 0;background:#fff;
  border:1px solid #e1eef2;border-radius:10px;cursor:pointer;
}
.report-form .radio input{width:18px;height:18px}

.report-form textarea,
.report-form input[type="text"]{
  width:100%;box-sizing:border-box;min-height:90px;
  border:1.5px solid #cfe5ea;border-radius:12px;padding:10px 12px;
}
.report-form input[type="text"]{min-height:auto}

.report-form .hint{font-size:.86rem;color:#6b7f86;margin-top:6px}
.report-form .count{font-size:.8rem;color:#7b8d93;text-align:right;margin-top:4px}

.swk-report .swal2-actions{gap:8px}
.swk-btn-primary{background:#1e8fa2 !important;border-radius:10px;padding:10px 16px;font-weight:700}
.swk-btn-cancel{background:#8b97a6 !important;color:#fff !important;border-radius:10px;padding:10px 16px}

  </style>
</head>
<body>
  <?php $page='forum'; ?>
  <?php include 'includes/sidebar.php'; ?>
  <main class="content-area">
    <?php include 'includes/header.php'; ?>

    <div class="post-page-container">
      <div class="forum-header-simple">
        <div class="forum-title">Forum — <?= htmlspecialchars($catName) ?></div>
        <a class="btn btn-small" href="forum.php?cat=<?= urlencode($p['category_slug'] ?: 'all') ?>">
          Back to <?= htmlspecialchars($catName ?: 'All') ?>
        </a>
      </div>

      <!-- Single Post Card -->
      <article class="post" data-post-id="<?= (int)$p['post_id'] ?>" id="post-<?= (int)$p['post_id'] ?>">
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
          <div class="muted" style="color:#2a515c"><?= nl2br(htmlspecialchars($p['body'])) ?></div>
        <?php endif; ?>

        <?php if (!empty($att)): ?>
          <div class="att-grid">
            <?php foreach ($att as $f):
              $raw  = isset($f['path']) ? $f['path'] : '';
              if ($raw && preg_match('#^https?://#', $raw)) { $path = $raw; }
              else { $path = '../' . ltrim($raw, '/'); }
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

          <!-- COMMENTS count (no toggle here; thread is always shown) -->
          <div class="pf-action" style="display:inline-flex;align-items:center;gap:6px;">
            <span>💬</span><span class="pf-comment-count"><?= (int)$p['comments'] ?></span>
          </div>

          <!-- VIEWS (display only; will sync after tracker) -->
          <div class="pf-action" style="display:inline-flex;align-items:center;gap:6px;">
            <span>👁</span><span class="pf-view-count"><?= (int)$p['views'] ?></span>
          </div>

          <!-- REPORT -->
<?php if ($canReportPost): ?>
  <button type="button"
          class="btn ghost danger"
          data-act="report-post"
          data-post-id="<?= (int)$p['post_id'] ?>"
          title="Report this post">🚩 Report</button>
<?php endif; ?>

        </div>

        <!-- Comments thread (always visible on this page) -->
        <div class="comments-wrap" data-post-id="<?= (int)$p['post_id'] ?>">
          <div class="comments-list" id="comments-<?= (int)$p['post_id'] ?>"
               style="display:grid; gap:10px; margin-bottom:8px;">
            <div class="muted">Loading comments…</div>
          </div>

          <div class="comments-more" data-next-page="1" style="display:none; margin-bottom:8px;">
            <button type="button" class="btn" data-act="load-more">Load more</button>
          </div>

          <!-- Composer -->
          <form class="comment-form" data-post-id="<?= (int)$p['post_id'] ?>" style="display:grid; gap:8px;">
            <textarea name="body" rows="3" placeholder="Write a comment…" required></textarea>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
              <button type="submit" class="btn primary">Post Comment</button>
            </div>
          </form>
        </div>
      </article>
    </div>
  </main>

<script>
document.addEventListener('DOMContentLoaded', () => {
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
  /* avatar in composer (header already uses it) */
  const me = window.CURRENT_USER || { profile_pic: '../uploads/default.png' };

  /* ===== Like toggle (same behavior as list page) ===== */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-like');
    if (!btn) return;

    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    const countEl = btn.querySelector('.pf-like-count');
    const likedNow = btn.classList.contains('liked');
    const postId   = btn.getAttribute('data-post-id');

    // optimistic
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

      countEl.textContent = String(data.likes_count ?? newCount);
      btn.classList.toggle('liked', !!data.liked);
      btn.setAttribute('aria-label', data.liked ? 'Unlike' : 'Like');
      btn.setAttribute('title',      data.liked ? 'Unlike' : 'Like');
      btn.dataset.liked = data.liked ? '1' : '0';
    } catch (err) {
      // revert on error
      countEl.textContent = String(oldCount);
      btn.classList.toggle('liked', likedNow);
      btn.setAttribute('aria-label', likedNow ? 'Unlike' : 'Like');
      btn.setAttribute('title',      likedNow ? 'Unlike' : 'Like');
      Swal.fire({ icon:'error', title:'Like failed', text: String(err), confirmButtonColor:'#1e8fa2' });
    } finally {
      btn.dataset.busy = '0';
    }
  });

  /* ===== Comments: render helpers ===== */
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
  const rc   = Number(c.replies_count || 0);

  const isAdminAuthor = String(c.user_role || '').toLowerCase() === 'admin'; // ← needs API (see section C)
  const isSelf        = Number(window.CURRENT_USER_ID || 0) === Number(c.user_id || 0);
  const canReport     = !isSelf && !isAdminAuthor;

  return `
    <div class="comment-item"
         data-comment-id="${c.comment_id}"
         data-author-id="${c.user_id || ''}"
         data-author-role="${c.user_role || ''}">
      <img src="${pic}" alt="" class="avatar" />
      <div style="display:grid; gap:6px;">
        <div style="display:flex; gap:8px; align-items:center;">
          <strong>${name}</strong>
          <span class="muted" style="font-size:.85em;">${when}</span>
        </div>
        <div style="white-space:pre-wrap; color:#2a515c;">${body}</div>

        <div class="comment-actions" style="display:flex; gap:10px;">
          <button type="button" class="btn" data-act="reply-toggle">↩️ Reply</button>
          <button type="button" class="btn" data-act="show-replies" data-loaded="0" data-open="0">
            Replies: <span class="rc">${rc}</span>
          </button>
          ${canReport ? `<button type="button" class="btn ghost danger" data-act="report-comment">🚩 Report</button>` : ``}
        </div>

        <form class="reply-form" data-post-id="${window.VIEW_POST_ID}"
              data-parent-id="${c.comment_id}" style="display:none;">
          <textarea name="body" rows="2" placeholder="Write a reply…" required></textarea>
          <div class="reply-actions">
            <button type="submit" class="btn primary">Reply</button>
            <button type="button" class="btn" data-act="reply-cancel">Cancel</button>
          </div>
        </form>

        <div class="comment-children" data-parent-id="${c.comment_id}" style="display:none; border-left:2px solid #e6f2f6; padding-left:12px;"></div>
      </div>
    </div>
  `;
}

function renderReplyItem(r){
  const when = r.created_at ? esc(r.created_at) : 'Just now';
  const pic  = webPathFromAdmin(r.profile_pic);
  const name = esc(r.full_name || 'User');
  const body = esc(r.body || '');

  // Prefer server-provided r.can_report (naka-implement na sa forum_comment_children.php)
  // Fallback: compute on client kung sakaling wala
  const canReport = (typeof r.can_report !== 'undefined')
    ? !!r.can_report
    : (Number(window.CURRENT_USER_ID || 0) !== Number(r.user_id || 0) &&
       String(r.user_role || '').toLowerCase() !== 'admin');

  return `
    <div class="comment-item reply" data-comment-id="${r.comment_id}" style="border-color:#eef6f8;">
      <img src="${pic}" alt="" class="avatar" style="width:32px;height:32px;" />
      <div style="display:grid; gap:6px;">
        <div class="c-head">
          <div style="display:flex; gap:8px; align-items:center;">
            <strong>${name}</strong>
            <span class="muted" style="font-size:.82em;">${when}</span>
          </div>
          ${canReport
            ? `<button type="button" class="btn icon ghost danger"
                       data-act="report-comment" title="Report this reply">🚩</button>`
            : ``}
        </div>

        <div style="white-space:pre-wrap; color:#2a515c;">${body}</div>
      </div>
    </div>
  `;
}

async function loadRepliesFor(parentId, { open = true } = {}) {
  const card   = document.querySelector(`.comment-item[data-comment-id="${CSS.escape(String(parentId))}"]`);
  if (!card) return;

  const btn    = card.querySelector('[data-act="show-replies"]');
  const box    = card.querySelector('.comment-children');
  const rcSpan = btn?.querySelector('.rc');
  if (!btn || !box) return;

  // fetch once
  if (btn.getAttribute('data-loaded') !== '1') {
    try {
      const fd  = new URLSearchParams({
        post_id: String(window.VIEW_POST_ID || ''),
        parent_id: String(parentId),
        page: '1',
        limit: '50'
      });
      const res = await fetch('forum_comment_children.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      box.innerHTML = data.replies.length
        ? data.replies.map(renderReplyItem).join('')
        : '<div class="muted" style="margin-left:2px;">No replies yet.</div>';

      btn.setAttribute('data-loaded', '1');
    } catch (err) {
      console.error('loadRepliesFor failed', err);
      return;
    }
  }

  // show by default
  if (open) {
    box.style.display = '';
    btn.setAttribute('data-open', '1');
    btn.innerHTML = `Hide replies (<span class="rc">${rcSpan?.textContent || '0'}</span>)`;
  }
}

  /* ===== Load first page of comments on page load ===== */
  (async function loadFirstComments(){
    const postId = String(window.VIEW_POST_ID || '');
    const list   = document.getElementById('comments-' + postId);
    const more   = document.querySelector('.comments-more');

    if (!postId || !list) return;
    list.innerHTML = '<div class="muted">Loading comments…</div>';

    try {
      const fd = new URLSearchParams();
      fd.set('post_id', postId); fd.set('page', '1'); fd.set('limit', '10');

      const res = await fetch('forum_comment_list.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      list.innerHTML = data.comments.length
        ? data.comments.map(renderCommentItem).join('')
        : '<div class="muted">No comments yet. Be the first to comment.</div>';
        // Auto-load & expand replies for parents that have them
const parentsWithReplies = data.comments.filter(c => Number(c.replies_count || 0) > 0);
await Promise.all(parentsWithReplies.map(c => loadRepliesFor(c.comment_id, { open: true })));

      if (data.has_more) {
        more.style.display = '';
        more.dataset.nextPage = data.next_page;
      } else {
        more.style.display = 'none';
      }
    } catch (err) {
      list.innerHTML = '<div class="muted">Failed to load comments.</div>';
      console.error(err);
    }
  })();

  // 2-step report modal: confirm -> reason form
async function promptReportTwoStep({ type, id }) {
  // STEP 1: quick confirm
  const c = await Swal.fire({
    icon: 'warning',
    title: `Report this ${type === 'comment' ? 'comment' : 'post'}?`,
    text: 'Do you want to send a report for review?',
    showCancelButton: true,
    confirmButtonText: 'Yes, report',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#1e8fa2'
  });
  if (!c.isConfirmed) return;

  // STEP 2: reason + notes
 // STEP 2: reason + notes (polished)
const html = `
  <div class="report-form">
    <div class="label">Reason</div>

    <label class="radio">
      <input type="radio" name="r-reason" value="spam" checked>
      <span>Spam / Scam</span>
    </label>
    <label class="radio">
      <input type="radio" name="r-reason" value="offensive">
      <span>Offensive / Profanity</span>
    </label>
    <label class="radio">
      <input type="radio" name="r-reason" value="harassment">
      <span>Harassment / Bullying</span>
    </label>
    <label class="radio">
      <input type="radio" name="r-reason" value="hate">
      <span>Hate Speech</span>
    </label>
    <label class="radio">
      <input type="radio" name="r-reason" value="nsfw">
      <span>NSFW / Sexual Content</span>
    </label>
    <label class="radio">
      <input type="radio" name="r-reason" value="other">
      <span>Other (please specify)</span>
    </label>
    <div id="other-wrap" style="display:none; margin:6px 0 12px 28px">
      <input id="r-other" type="text" placeholder="Type the reason…" />
    </div>

    <div class="label">Notes <span style="font-weight:500;color:#6b7f86">(optional)</span></div>
    <textarea id="r-notes" maxlength="500" placeholder="Add details, quotes, or context…"></textarea>
    <div id="r-notes-count" class="count">0/500</div>
    <div class="hint">Your report is anonymous to other users.</div>
  </div>
`;

const { value: form } = await Swal.fire({
  title: 'Report details',
  html,
  focusConfirm: false,
  showCancelButton: true,
  confirmButtonText: 'Submit report',
  cancelButtonText: 'Back',
  customClass: {
    popup: 'swk-report',
    confirmButton: 'swk-btn-primary',
    cancelButton: 'swk-btn-cancel'
  },
  didOpen: () => {
    const notes = document.getElementById('r-notes');
    const counter = document.getElementById('r-notes-count');
    const otherWrap = document.getElementById('other-wrap');
    const otherInput = document.getElementById('r-other');

    // Show "Other" textbox only when selected
    document.querySelectorAll('input[name="r-reason"]').forEach(r => {
      r.addEventListener('change', () => {
        otherWrap.style.display = (r.value === 'other' && r.checked) ? '' : 'none';
      });
    });

    // Live counter
    const update = () => { counter.textContent = `${(notes.value||'').length}/500`; };
    notes.addEventListener('input', update); update();
  },
  preConfirm: () => {
    const chosen = [...document.querySelectorAll('input[name="r-reason"]')]
      .find(x => x.checked)?.value || '';
    const notes = (document.getElementById('r-notes') || {}).value || '';
    const other = (document.getElementById('r-other') || {}).value || '';

    if (!chosen) { Swal.showValidationMessage('Please select a reason.'); return false; }
    if (chosen === 'other' && other.trim().length < 4) {
      Swal.showValidationMessage('Please specify your reason (at least 4 characters).');
      return false;
    }
    return { reason: chosen, notes, other_text: other.trim() };
  }
});
if (!form) return;

// Build payload (include other_text if present)
const fd = new URLSearchParams();
fd.set('type', type);           // 'post' | 'comment'
fd.set('id', String(id));
fd.set('reason', form.reason);
fd.set('notes', form.notes);
if (form.other_text) fd.set('other_text', form.other_text);

  try {
    const res  = await fetch('forum_report_create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: fd.toString()
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.message || 'Failed to submit report');
    Swal.fire({
      icon: 'success',
      title: 'Thanks for the report',
      text: 'Our moderators will review this shortly.',
      confirmButtonColor: '#1e8fa2'
    });
  } catch (err) {
    Swal.fire({ icon:'error', title:'Could not send report', text:String(err), confirmButtonColor:'#1e8fa2' });
  }
}
// Post-level report (with guard)
document.addEventListener('click', (e) => {
  const b = e.target.closest('[data-act="report-post"]');
  if (!b) return;

  const isSelf   = Number(window.CURRENT_USER_ID || 0) === Number(window.POST_AUTHOR_ID || 0);
  const isAdminP = String(window.POST_AUTHOR_ROLE || '').toLowerCase() === 'admin';
  if (isSelf || isAdminP) {
    Swal.fire({icon:'info', title:'Not allowed', text:"You can't report your own post or an admin post.", confirmButtonColor:'#1e8fa2'});
    return;
  }
  promptReportTwoStep({ type: 'post', id: b.dataset.postId });
});


// Comment-level report
// Comment-level report (with guard)
document.addEventListener('click', (e) => {
  const b = e.target.closest('[data-act="report-comment"]');
  if (!b) return;
  const card = b.closest('.comment-item');
  if (!card) return;

  const authorId   = Number(card.dataset.authorId || 0);
  const authorRole = String(card.dataset.authorRole || '').toLowerCase();
  const isSelf     = Number(window.CURRENT_USER_ID || 0) === authorId;
  const isAdminC   = authorRole === 'admin';

  if (isSelf || isAdminC) {
    Swal.fire({icon:'info', title:'Not allowed', text:"You can't report your own comment or an admin comment.", confirmButtonColor:'#1e8fa2'});
    return;
  }
  promptReportTwoStep({ type: 'comment', id: card.dataset.commentId });
});

  /* ===== Load more comments ===== */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.comments-more [data-act="load-more"]');
    if (!btn) return;
    const more = btn.closest('.comments-more');
    const postId = String(window.VIEW_POST_ID || '');
    const list = document.getElementById('comments-' + postId);
    if (!postId || !list) return;

    const next = parseInt(more.dataset.nextPage || '2', 10);
    btn.disabled = true; const old = btn.textContent; btn.textContent = 'Loading…';

    try {
      const fd = new URLSearchParams();
      fd.set('post_id', postId); fd.set('page', String(next)); fd.set('limit', '10');

      const res = await fetch('forum_comment_list.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      if (data.comments.length) list.insertAdjacentHTML('beforeend', data.comments.map(renderCommentItem).join(''));
      if (data.has_more) { more.dataset.nextPage = data.next_page; btn.disabled = false; btn.textContent = old; }
      else { more.style.display = 'none'; }
    } catch (err) {
      btn.disabled = false; btn.textContent = old;
      Swal.fire({ icon:'error', title:'Failed to load more', text:String(err), confirmButtonColor:'#1e8fa2' });
    }
  });

  /* ===== Submit comment ===== */
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.comment-form');
    if (!form) return;
    e.preventDefault();

    if (form.dataset.busy === '1') return;
    form.dataset.busy = '1';

    const postId   = String(window.VIEW_POST_ID || '');
    const textarea = form.querySelector('textarea[name="body"]');
    const bodyText = (textarea?.value || '').trim();
    const list     = document.getElementById('comments-' + postId);
    const countEl  = document.querySelector('.pf-comment-count');

    if (!bodyText) { form.dataset.busy = '0'; return; }

    const btn = form.querySelector('button[type="submit"]');
    const oldBtnText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Posting…'; }

    try {
      const fd = new URLSearchParams();
      fd.set('post_id', postId); fd.set('body', bodyText);

      const res = await fetch('forum_comment_create.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      list?.insertAdjacentHTML('beforeend', renderCommentItem(data.comment));

      // increment 💬 counter
      if (countEl) countEl.textContent = String((parseInt(countEl.textContent||'0',10) || 0) + 1);

      if (textarea) textarea.value = '';
    } catch (err) {
      Swal.fire({ icon:'error', title:'Comment failed', text:String(err), confirmButtonColor:'#1e8fa2' });
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = oldBtnText || 'Post Comment'; }
      form.dataset.busy = '0';
    }
  });

  /* ===== Views tracker (optional; requires forum_view_track.php) ===== */
  (async function trackView(){
    const postId = String(window.VIEW_POST_ID || '');
    const vc = document.querySelector('.pf-view-count');
    try {
      const res = await fetch('forum_view_track.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + encodeURIComponent(postId)
      });
      const data = await res.json().catch(()=>null);
      if (res.ok && data && data.ok && typeof data.views_count !== 'undefined' && vc) {
        vc.textContent = String(data.views_count);
      }
    } catch (_) {
      /* silent fail; page still works */
    }
  })();

  // Toggle inline reply form
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-act="reply-toggle"]');
  if (!btn) return;
  const card = btn.closest('.comment-item');
  const form = card?.querySelector('.reply-form');
  if (!form) return;
  form.style.display = (form.style.display === 'none' || !form.style.display) ? '' : 'none';
  if (form.style.display !== 'none') form.querySelector('textarea')?.focus();
});

// Cancel reply
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-act="reply-cancel"]');
  if (!btn) return;
  const form = btn.closest('.reply-form');
  if (!form) return;
  const ta = form.querySelector('textarea'); if (ta) ta.value = '';
  form.style.display = 'none';
});

// Show/Hide replies toggle (lazy-load on first click)
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-act="show-replies"]');
  if (!btn) return;
  const card = btn.closest('.comment-item');
  const parentId = card?.dataset.commentId;
  const postId   = String(window.VIEW_POST_ID || '');
  const box      = card?.querySelector('.comment-children');
  const rcSpan   = btn.querySelector('.rc');

  if (!parentId || !box) return;

  const loaded = btn.getAttribute('data-loaded') === '1';
  const open   = btn.getAttribute('data-open') === '1';

  // First time: fetch replies
  if (!loaded) {
    btn.disabled = true; const old = btn.textContent; btn.textContent = 'Loading…';
    try {
      const fd = new URLSearchParams({ post_id: postId, parent_id: parentId, page: '1', limit: '20' });
      const res  = await fetch('forum_comment_children.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: fd.toString()
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

      box.innerHTML = data.replies.length
        ? data.replies.map(renderReplyItem).join('')
        : '<div class="muted" style="margin-left:2px;">No replies yet.</div>';

      btn.setAttribute('data-loaded', '1');
    } catch (err) {
      Swal.fire({ icon:'error', title:'Failed to load replies', text:String(err), confirmButtonColor:'#1e8fa2' });
      return;
    } finally {
      btn.disabled = false; btn.textContent = old;
    }
  }

  // Toggle visibility
  if (!open) {
    box.style.display = '';
    btn.setAttribute('data-open', '1');
    btn.innerHTML = `Hide replies (<span class="rc">${rcSpan?.textContent || '0'}</span>)`;
  } else {
    box.style.display = 'none';
    btn.setAttribute('data-open', '0');
    btn.innerHTML = `Replies: <span class="rc">${rcSpan?.textContent || '0'}</span>`;
  }
});

// Submit a reply
document.addEventListener('submit', async (e) => {
  const form = e.target.closest('.reply-form');
  if (!form) return;
  e.preventDefault();

  if (form.dataset.busy === '1') return;
  form.dataset.busy = '1';

  const postId   = form.getAttribute('data-post-id');
  const parentId = form.getAttribute('data-parent-id');
  const ta       = form.querySelector('textarea[name="body"]');
  const bodyText = (ta?.value || '').trim();
  const card     = form.closest('.comment-item');
  const box      = card?.querySelector('.comment-children');
  const toggle   = card?.querySelector('[data-act="show-replies"]');
  const rcSpan   = toggle?.querySelector('.rc');
  const postCountEl = document.querySelector('.pf-comment-count');

  if (!bodyText) { form.dataset.busy = '0'; return; }

  const btn = form.querySelector('button[type="submit"]');
  const old = btn ? btn.textContent : '';
  if (btn){ btn.disabled = true; btn.textContent = 'Replying…'; }

  try {
    const fd = new URLSearchParams({ post_id: postId, parent_comment_id: parentId, body: bodyText });
    const res  = await fetch('forum_comment_create.php', {
      method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: fd.toString()
    });
    const data = await res.json();
    if (!res.ok || !data || !data.ok) throw new Error((data && data.message) || ('HTTP ' + res.status));

    // ensure replies box visible
    if (box.style.display === 'none' || !box.innerHTML.trim()) {
      box.style.display = '';
    }
    // append the new reply
    box.insertAdjacentHTML('beforeend', renderReplyItem(data.comment));

    // bump replies pill
    if (rcSpan) rcSpan.textContent = String((parseInt(rcSpan.textContent||'0',10)||0) + 1);

    // bump post-level 💬 counter
    if (postCountEl) postCountEl.textContent = String((parseInt(postCountEl.textContent||'0',10)||0) + 1);

    // clear + hide form
    if (ta) ta.value = '';
    form.style.display = 'none';
  } catch (err) {
    Swal.fire({ icon:'error', title:'Reply failed', text:String(err), confirmButtonColor:'#1e8fa2' });
  } finally {
    if (btn){ btn.disabled = false; btn.textContent = old || 'Reply'; }
    form.dataset.busy = '0';
  }
});
});

</script>
</body>
</html>
