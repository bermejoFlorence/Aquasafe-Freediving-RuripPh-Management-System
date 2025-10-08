<?php
session_start();
$_SESSION['fp_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['fp_csrf'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password | AquaSafe RuripPH</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
</head>
<body style="min-height:100vh;background:linear-gradient(180deg,#b1e7fa 0%,#eaf6fa 70%,#bfe9f7 100%);display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="background:#fff;border-radius:28px;box-shadow:0 8px 38px rgba(30,143,162,.14);padding:2.3em 2.5em;max-width:560px;width:95%;">
    <div style="text-align:center;margin-bottom:1em;">
      <div style="font-size:1.55em;font-weight:900;color:#16798e;letter-spacing:1px;">AquaSafe RuripPH</div>
      <div style="font-size:1.05em;color:#1e8fa2;font-weight:500;margin-top:.26em;">Forgot your password?</div>
    </div>

    <form method="POST" action="forgot_password_action.php" autocomplete="off" style="display:flex;flex-direction:column;gap:12px;">
      <label for="email" style="color:#16798e;font-weight:600;">Email</label>
      <input type="email" id="email" name="email" required placeholder="Enter your registered email"
             style="border:2px solid #aad6e3;border-radius:15px;padding:.9em 1.2em;font-size:1.05em;background:#f9fcfd;outline:none;"
             onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">

      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES) ?>">
      <button type="submit" style="background:linear-gradient(90deg,#1e8fa2 60%,#55bde6 100%);color:#fff;border:none;border-radius:23px;padding:.95em 0;font-size:1.1em;font-weight:bold;cursor:pointer;">
        Send reset link
      </button>
    </form>

    <div style="margin-top:1.1em;font-size:.97em;color:#353b40;text-align:center;">
      <a href="login.php" style="color:#1e8fa2;font-weight:600;text-decoration:none;">Back to login</a>
      &nbsp;•&nbsp;
      <a href="index.php" style="color:#1e8fa2;font-weight:600;text-decoration:none;">Return Home</a>
    </div>
  </div>
</body>
</html>
