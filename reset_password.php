<?php
require __DIR__.'/db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (method_exists($conn,'set_charset')) $conn->set_charset('utf8mb4');

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

$valid = false;
if ($token && $email) {
  $stmt = $conn->prepare("
    SELECT id
    FROM password_reset
    WHERE email = ?
      AND token = ?
      AND used = 0
      AND expires_at > NOW()
    LIMIT 1
  ");
  $stmt->bind_param('ss', $email, $token);
  $stmt->execute();
  $stmt->bind_result($rid);
  $valid = (bool)$stmt->fetch();
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reset Password | AquaSafe RuripPH</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
</head>
<body style="min-height:100vh;background:linear-gradient(180deg,#b1e7fa 0%,#eaf6fa 70%,#bfe9f7 100%);display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="background:#fff;border-radius:28px;box-shadow:0 8px 38px rgba(30,143,162,.14);padding:2.3em 2.5em;max-width:560px;width:95%;">
    <div style="text-align:center;margin-bottom:1em;">
      <div style="font-size:1.55em;font-weight:900;color:#16798e;">AquaSafe RuripPH</div>
      <div style="font-size:1.05em;color:#1e8fa2;font-weight:500;margin-top:.26em;">Set a new password</div>
    </div>

    <?php if ($valid): ?>
      <form method="POST" action="reset_password_action.php" style="display:flex;flex-direction:column;gap:12px;" autocomplete="off">
        <label style="color:#16798e;font-weight:600;">New password</label>
        <input type="password" name="password" required minlength="8"
               style="border:2px solid #aad6e3;border-radius:15px;padding:.9em 1.2em;background:#f9fcfd;outline:none;"
               onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">

        <label style="color:#16798e;font-weight:600;">Confirm password</label>
        <input type="password" name="cpassword" required minlength="8"
               style="border:2px solid #aad6e3;border-radius:15px;padding:.9em 1.2em;background:#f9fcfd;outline:none;"
               onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">

        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>">

        <button type="submit"
                style="background:linear-gradient(90deg,#1e8fa2 60%,#55bde6 100%);color:#fff;border:none;border-radius:23px;padding:.95em 0;font-size:1.1em;font-weight:bold;cursor:pointer;">
          Update password
        </button>
      </form>
      <div style="margin-top:12px;text-align:center;">
        <a href="login.php" style="color:#1e8fa2;font-weight:600;text-decoration:none;">Back to login</a>
        &nbsp;•&nbsp;
        <a href="index.php" style="color:#1e8fa2;font-weight:600;text-decoration:none;">Return Home</a>
      </div>
    <?php else: ?>
      <p style="color:#c0392b;text-align:center;">Link is invalid or expired.</p>
      <div style="text-align:center;margin-top:8px;">
        <a href="forgot_password.php" style="color:#1e8fa2;font-weight:600;">Request a new link</a>
        &nbsp;•&nbsp;
        <a href="index.php" style="color:#1e8fa2;font-weight:600;">Return Home</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
