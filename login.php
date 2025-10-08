<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | AquaSafe RuripPH</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="
    min-height: 100vh;
    background: linear-gradient(180deg, #b1e7fa 0%, #eaf6fa 70%, #bfe9f7 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', Arial, sans-serif;
">
    <div style="
        background: #fff;
        border-radius: 28px;
        box-shadow: 0 8px 38px rgba(30,143,162,0.14);
        padding: 2.5em 2em 2em 2em;
        max-width: 370px;
        width: 98%;
        margin: 2.4em 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: box-shadow 0.18s;
    ">
        <div style="text-align:center;margin-bottom:1.5em;">
            <div style="font-size:1.6em;font-weight:900;color:#16798e;letter-spacing:1px;">AquaSafe RuripPH</div>
            <div style="font-size:1.07em;color:#1e8fa2;font-weight:500;margin-top:0.34em;">Member Login</div>
        </div>
        <form method="POST" action="login_action.php" style="width:100%;display:flex;flex-direction:column;">
            <label for="email" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.18em;">Email</label>
            <input type="email" name="email" id="email" required style="
                border: 2px solid #aad6e3;
                border-radius: 16px;
                padding: 0.92em 1.2em;
                margin-bottom: 1.1em;
                font-size: 1.08em;
                outline: none;
                transition: border 0.17s;
                background: #f9fcfd;
                color: #1c2227;
                width: 100%;
                box-sizing: border-box;
            " onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">

            <label for="password" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.18em;">Password</label>
            <input type="password" name="password" id="password" required style="
                border: 2px solid #aad6e3;
                border-radius: 16px;
                padding: 0.92em 1.2em;
                margin-bottom: 1.5em;
                font-size: 1.08em;
                outline: none;
                transition: border 0.17s;
                background: #f9fcfd;
                color: #1c2227;
                width: 100%;
                box-sizing: border-box;
            " onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">

            <button type="submit" style="
                background: linear-gradient(90deg, #1e8fa2 60%, #55bde6 100%);
                color: #fff;
                border: none;
                border-radius: 23px;
                padding: 0.85em 0;
                font-size: 1.13em;
                font-weight: bold;
                box-shadow: 0 4px 16px rgba(30,143,162,0.13);
                margin-top: 0.5em;
                cursor:pointer;
                transition: background 0.17s, transform 0.11s;
                width:100%;
                letter-spacing:0.4px;
            " onmouseover="this.style.background='#16798e';this.style.transform='scale(1.04)';" 
              onmouseout="this.style.background='linear-gradient(90deg, #1e8fa2 60%, #55bde6 100%)';this.style.transform='scale(1)';">
                Login
            </button>
        </form>
        <div style="margin-top:1.2em;font-size:0.97em;color:#353b40;">
            Don’t have an account?
            <a href="register.php" style="color:#1e8fa2;font-weight:600;text-decoration:none;">Register</a>
        </div>
        <!-- sa ilalim ng existing "Don't have an account? Register" line -->
<div style="margin-top:10px; font-size:.95em; color:#353b40;">
  <a href="index.php" style="color:#1e8fa2; font-weight:600; text-decoration:none;">Return Home</a>
  &nbsp;•&nbsp;
  <a href="forgot_password.php" style="color:#1e8fa2; font-weight:600; text-decoration:none;">Forgot password?</a>
</div>

    </div>
    <script>
    // Mobile responsiveness (optional, for perfect mobile)
    window.addEventListener('resize', function(){
        const box = document.querySelector('body > div');
        if(window.innerWidth < 420){
            box.style.padding = "1.2em 0.2em";
            box.style.maxWidth = "98vw";
        } else {
            box.style.padding = "2.5em 2em 2em 2em";
            box.style.maxWidth = "370px";
        }
    });
    </script>
</body>
</html>
