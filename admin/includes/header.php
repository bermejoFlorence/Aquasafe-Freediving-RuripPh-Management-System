<?php
// DO NOT put session_start() here! Dapat sa taas ng index.php.
// Dynamic assignments from session:
$profile_pic = $_SESSION['profile_pic'] ?? 'default.png';
$full_name   = $_SESSION['full_name'] ?? 'Admin';
$email       = $_SESSION['email_address'] ?? 'admin@email.com';
$address     = $_SESSION['address'] ?? '';
$user_id     = $_SESSION['user_id'] ?? 0;

// NEW: Structured flash (success/error/info) mula sa change_password.php at iba pa
$flash = $_SESSION['flash'] ?? null;
if ($flash) {
    unset($_SESSION['flash']); // consume once
}

// Back-compat: legacy message para sa profile update
$update_profile_msg = '';
if (isset($_SESSION['update_profile_msg'])) {
    $update_profile_msg = $_SESSION['update_profile_msg'];
    unset($_SESSION['update_profile_msg']);
}
?>

<div class="topbar">
    <div class="topbar-content">
        <button class="hamburger-btn" id="hamburger-btn" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="brand-area">
            <span class="brand-text">Aquasafe RuripPh</span>
        </div>
        <div class="header-right">
        <div class="notif-bell-container" style="position:relative;">
                <button class="notif-btn" id="notif-btn" title="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <span id="notif-badge" class="notif-badge" style="display:none;">!</span>
                </button>
                <div id="notif-dropdown" class="notif-dropdown">
                    <div id="notif-list"></div>
                </div>
            </div>
            <div class="profile-info" id="profile-info" title="Profile">
                <img src="../uploads/<?php echo htmlspecialchars($profile_pic); ?>"
                    alt="Profile"
                    class="profile-img"
                    onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode(substr($full_name,0,2)); ?>&background=1e8fa2&color=fff&rounded=true';">
                <span><?php echo htmlspecialchars($full_name); ?></span>
                <div class="profile-dropdown" id="profile-dropdown">
                    <button type="button" id="edit-profile-btn">Edit Profile</button>
                    <button type="button" id="change-password-btn">Change Password</button>
                        <button type="button" id="logout-btn">Logout</button>
                
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal-overlay" id="edit-profile-modal">
    <div class="modal-content">
        <span class="close-modal" id="close-edit-modal">&times;</span>
        <h2>Edit Profile</h2>
        <form id="edit-profile-form" method="post" action="includes/update_profile.php" enctype="multipart/form-data">
            <div class="img-preview" style="display: flex; justify-content: center;">
                <img id="profile-img-preview"
                     src="../uploads/<?php echo htmlspecialchars($profile_pic); ?>"
                     alt="Profile"
                     style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover;">
            </div>
            <div class="form-group" style="text-align:center">
                <label for="profile-img-input" style="font-size:0.97em;cursor:pointer;color:#1e8fa2">Change Profile Picture</label><br>
                <input type="file" id="profile-img-input" name="profile_pic" accept="image/*" style="margin:0.5em auto;display:block;">
            </div>
            <div class="form-group">
                <label for="profile-name-input">Name</label>
                <input type="text" id="profile-name-input" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>">
            </div>
            <div class="form-group">
                <label for="profile-email-input">Email</label>
                <input type="email" id="profile-email-input" name="email_address" value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <div class="form-group">
                <label for="profile-address-input">Address</label>
                <input type="text" id="profile-address-input" name="address" value="<?php echo htmlspecialchars($address); ?>">
            </div>
            <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>">
            <div class="modal-actions">
                <button type="button" id="cancel-edit">Cancel</button>
                <button type="submit" id="save-profile-btn">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal-overlay" id="change-password-modal">
    <div class="modal-content">
        <span class="close-modal" id="close-pass-modal">&times;</span>
        <h2>Change Password</h2>
        <form id="change-password-form" method="post" action="includes/change_password.php">
            <div class="form-group">
                <label for="current-pass">Current Password</label>
                <div class="input-eye-wrapper">
                    <input type="password" id="current-pass" name="current_password" autocomplete="current-password">
                    <span class="toggle-eye" data-target="current-pass">
                        <i class="fa-regular fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label for="new-pass">New Password</label>
                <div class="input-eye-wrapper">
                    <input type="password" id="new-pass" name="new_password" autocomplete="new-password">
                    <span class="toggle-eye" data-target="new-pass">
                        <i class="fa-regular fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm-pass">Confirm New Password</label>
                <div class="input-eye-wrapper">
                    <input type="password" id="confirm-pass" name="confirm_password" autocomplete="new-password">
                    <span class="toggle-eye" data-target="confirm-pass">
                        <i class="fa-regular fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" id="cancel-pass">Cancel</button>
                <button type="submit" id="save-pass-btn">Submit</button>
            </div>
        </form>
    </div>
</div>


<!-- SweetAlert flash (Change Password / others) + legacy profile -->
<?php if (!empty($flash)): ?>
<script>
Swal.fire({
  icon: <?php echo json_encode($flash['type'] ?? 'info'); ?>,      // 'success' | 'error' | 'info' | 'warning'
  title: <?php echo json_encode($flash['title'] ?? 'Notice'); ?>,
  text: <?php echo json_encode($flash['message'] ?? ''); ?>,
  confirmButtonColor: '#1e8fa2'
}).then(() => {
  // Auto-reopen the Change Password modal kapag nag-error galing sa Change Password
  <?php if (($flash['type'] ?? '') === 'error' && ($flash['title'] ?? '') === 'Change Password'): ?>
    var modal = document.getElementById('change-password-modal');
    if (modal) { modal.classList.add('active'); }
  <?php endif; ?>
});
</script>
<?php elseif (!empty($update_profile_msg)): /* legacy path (profile update) */ ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Profile Updated!',
  text: '<?php echo addslashes($update_profile_msg); ?>',
  confirmButtonColor: '#1e8fa2'
});
</script>
<?php endif; ?>


<script>
    document.addEventListener("DOMContentLoaded", function() {
    // ==== Profile Dropdown ====
    const profileInfo = document.getElementById('profile-info');
    if (profileInfo) {
        profileInfo.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
        document.addEventListener('click', function() {
            profileInfo.classList.remove('active');
        });
    }

    // ==== Edit Profile Modal ====
    const editProfileModal = document.getElementById('edit-profile-modal');
    const editProfileBtn = document.getElementById('edit-profile-btn');
    if (editProfileBtn && editProfileModal) {
        editProfileBtn.onclick = function(e) {
            e.stopPropagation();
            editProfileModal.classList.add('active');
            profileInfo.classList.remove('active');
        };
        document.getElementById('close-edit-modal').onclick = function() {
            editProfileModal.classList.remove('active');
        };
        document.getElementById('cancel-edit').onclick = function() {
            editProfileModal.classList.remove('active');
        };
        document.getElementById('profile-img-input').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    document.getElementById('profile-img-preview').src = evt.target.result;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    }

    // ==== Change Password Modal ====
    const changePassModal = document.getElementById('change-password-modal');
    const changePassBtn = document.getElementById('change-password-btn');
    if (changePassBtn && changePassModal) {
        changePassBtn.onclick = function(e) {
            e.stopPropagation();
            changePassModal.classList.add('active');
            profileInfo.classList.remove('active');
        };
        document.getElementById('close-pass-modal').onclick = function() {
            changePassModal.classList.remove('active');
        };
        document.getElementById('cancel-pass').onclick = function() {
            changePassModal.classList.remove('active');
        };
    }

    // ==== Prevent Modal Self-close ====
    document.querySelectorAll('.modal-content').forEach(function(modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function() {
            this.classList.remove('active');
        });
    });

    // ==== Eye Icon for Password Fields ====
    document.querySelectorAll('.toggle-eye').forEach(function(eye) {
        eye.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input) {
                if (input.type === "password") {
                    input.type = "text";
                    this.innerHTML = '<i class="fa-regular fa-eye-slash"></i>';
                } else {
                    input.type = "password";
                    this.innerHTML = '<i class="fa-regular fa-eye"></i>';
                }
            }
        });
    });

    // ==== Logout Confirmation ====
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to logout?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Logout',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#1e8fa2'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('/diving/logout.php', {
                        method: 'POST',
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Logged out!',
                            text: 'You have been successfully logged out.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(function() {
                            window.location.href = '/diving/login.php';
                        }, 1500);
                    });
                }
            });
        });
    }

    // ==== NOTIFICATION BELL Dropdown ====
const notifBtn = document.getElementById('notif-btn');
const notifBadge = document.getElementById('notif-badge');
const notifDropdown = document.getElementById('notif-dropdown');
const notifList = document.getElementById('notif-list');

// Helper: Format "time ago" for notification time
function timeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const seconds = Math.floor((now - date) / 1000);
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds/60) + ' minute(s) ago';
    if (seconds < 86400) return Math.floor(seconds/3600) + ' hour(s) ago';
    return date.toLocaleDateString();
}

function loadNotifications() {
    fetch('admin_get_notifications.php')
        .then(response => response.json())
        .then(data => {
            const unreadCount = data.filter(n => n.is_read == 0).length;
            // Show badge count for unread only
            notifBadge.style.display = unreadCount > 0 ? 'inline-flex' : 'none';
            notifBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;

            notifList.innerHTML = data.length === 0
                ? '<div class="notif-item">No new notifications.</div>'
                : data.map(notif => {
                    let message = '';
                    // Custom display for booking/payment
                    if (notif.type === 'booking') {
                        message = `Booking received from <b>${esc(notif.user_name || 'Client')}</b> for ${esc(notif.package_name || "a package")} on <b>${notif.booking_date ? new Date(notif.booking_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : ''}</b>.`;
                        } else if (notif.type === 'payment') {
                        message = `Downpayment received${notif.booking_date ? ` for <b>${new Date(notif.booking_date).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' })}</b>` : ''}.`;
                        } else if (notif.type === 'forum') {
                        // NEW: gamitin ang forum title kung meron
                        const t = notif.forum_title ? esc(notif.forum_title) : 'View forum post';
                        message = `New forum post: <b>${t}</b>`;
                        } else {
                        message = esc(notif.message || 'Notification');
                        }
                    // helper para safe maglagay ng text/attr
                    function esc(s){ return (s ?? '').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

                    return `
                    <div class="notif-item ${notif.is_read == 0 ? 'notif-unread' : ''}"
                        data-id="${esc(notif.notification_id)}"
                        data-link="${esc(notif.link || 'bookings.php')}"
                        style="cursor:pointer;">
                        <div class="notif-main">${message}</div>
                        <div class="notif-time">${timeAgo(notif.created_at)}</div>
                    </div>
                    `;
                }).join('');
        })
        .catch(() => {
            notifList.innerHTML = '<div class="notif-item">Unable to load notifications.</div>';
        });
}

// Handle notif click
notifList.addEventListener('click', function(e) {
    const target = e.target.closest('.notif-item');
    if (target && target.dataset.id) {
        // Optional: show loading indicator here
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'notification_id=' + encodeURIComponent(target.dataset.id)
        })
        .then(res => res.json())
        .then(() => {
            // 1. Update UI immediately
            loadNotifications();
            // 2. Short delay to see visual feedback (e.g., 200ms), then redirect
            setTimeout(function() {
                window.location = target.dataset.link || 'bookings.php';
            }, 200);
        });
    }
});



// Open/close notification dropdown
notifBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    notifDropdown.classList.toggle('show');
    loadNotifications(); // always refresh on open!
});
document.addEventListener('click', function() {
    notifDropdown.classList.remove('show');
});
notifDropdown.addEventListener('click', function(e) {
    e.stopPropagation();
});
setInterval(loadNotifications, 5000);
loadNotifications();

});

</script>