<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | AquaSafe RuripPH</title>
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
    <div id="register-box" style="
        background: #fff;
        border-radius: 28px;
        box-shadow: 0 8px 38px rgba(30,143,162,0.14);
        padding: 2.3em 2.5em 2em 2.5em;
        max-width: 660px;
        width: 100%;
        margin: 2.4em 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: box-shadow 0.18s;
    ">
        <div style="text-align:center;margin-bottom:1.3em;">
            <div style="font-size:1.55em;font-weight:900;color:#16798e;letter-spacing:1px;">AquaSafe RuripPH</div>
            <div style="font-size:1.05em;color:#1e8fa2;font-weight:500;margin-top:0.26em;">Create your account</div>
        </div>
        <form method="POST" action="register_action.php" style="width:100%;display:flex;flex-direction:column;" autocomplete="off">
            <!-- First Name and Last Name -->
            <div style="display: flex; gap: 1em; margin-bottom: 1em;">
                <div style="flex:1;">
                    <label for="firstname" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.13em;display:block;">First Name</label>
                    <input type="text" name="firstname" id="firstname" required placeholder="Enter your first name" maxlength="30"
                        style="border:2px solid #aad6e3; border-radius:15px; padding:0.9em 1.2em; font-size:1.08em; outline:none; transition:border 0.17s; background:#f9fcfd; color:#1c2227; width:100%; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';"
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '')">
                </div>
                <div style="flex:1;">
                    <label for="lastname" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.13em;display:block;">Last Name</label>
                    <input type="text" name="lastname" id="lastname" required placeholder="Enter your last name" maxlength="30"
                        style="border:2px solid #aad6e3; border-radius:15px; padding:0.9em 1.2em; font-size:1.08em; outline:none; transition:border 0.17s; background:#f9fcfd; color:#1c2227; width:100%; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';"
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '')">
                </div>
            </div>

            <!-- Address -->
            <label for="address" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.13em;display:block;">Address</label>
            <input type="text" name="address" id="address" required placeholder="Enter your address" style="
                border: 2px solid #aad6e3;
                border-radius: 15px;
                padding: 0.9em 1.2em;
                margin-bottom: 1em;
                font-size: 1.08em;
                outline: none;
                transition: border 0.17s;
                background: #f9fcfd;
                color: #1c2227;
                width: 100%;
                box-sizing: border-box;
            " onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">

            <!-- Contact Number and Email -->
            <div style="display: flex; gap: 1em; margin-bottom: 1em;">
                <div style="flex:1;">
                    <label for="contact" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.13em;display:block;">Contact Number</label>
                    <input type="text" name="contact" id="contact" required placeholder="09XXXXXXXXXX" maxlength="12"
                        style="border:2px solid #aad6e3; border-radius:15px; padding:0.9em 1.2em; font-size:1.08em; outline:none; transition:border 0.17s; background:#f9fcfd; color:#1c2227; width:100%; box-sizing:border-box;"
                        onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,12)">
                </div>
                <div style="flex:1;">
                    <label for="email" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.13em;display:block;">Email Address</label>
                    <input type="email" name="email" id="email" required placeholder="Enter your email" style="
                        border: 2px solid #aad6e3;
                        border-radius: 15px;
                        padding: 0.9em 1.2em;
                        font-size: 1.08em;
                        outline: none;
                        transition: border 0.17s;
                        background: #f9fcfd;
                        color: #1c2227;
                        width: 100%;
                        box-sizing: border-box;
                    " onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">
                </div>
            </div>

            <!-- Password and Confirm Password -->
            <div style="display: flex; gap: 1em; margin-bottom: 1.1em;">
                <div style="flex:1;">
                    <label for="password" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.13em;display:block;">Password</label>
                    <input type="password" name="password" id="password" required placeholder="Password" style="
                        border: 2px solid #aad6e3;
                        border-radius: 15px;
                        padding: 0.9em 1.2em;
                        font-size: 1.08em;
                        outline: none;
                        transition: border 0.17s;
                        background: #f9fcfd;
                        color: #1c2227;
                        width: 100%;
                        box-sizing: border-box;
                    " onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">
                </div>
                <div style="flex:1;">
                    <label for="cpassword" style="color:#16798e;font-weight:600;font-size:1em;margin-bottom:0.13em;display:block;">Confirm Password</label>
                    <input type="password" name="cpassword" id="cpassword" required placeholder="Confirm password" style="
                        border: 2px solid #aad6e3;
                        border-radius: 15px;
                        padding: 0.9em 1.2em;
                        font-size: 1.08em;
                        outline: none;
                        transition: border 0.17s;
                        background: #f9fcfd;
                        color: #1c2227;
                        width: 100%;
                        box-sizing: border-box;
                    " onfocus="this.style.borderColor='#16798e';" onblur="this.style.borderColor='#aad6e3';">
                </div>
            </div>

            <button type="submit" style="
                background: linear-gradient(90deg, #1e8fa2 60%, #55bde6 100%);
                color: #fff;
                border: none;
                border-radius: 23px;
                padding: 0.95em 0;
                font-size: 1.13em;
                font-weight: bold;
                box-shadow: 0 4px 16px rgba(30,143,162,0.13);
                margin-top: 0.2em;
                cursor:pointer;
                transition: background 0.17s, transform 0.11s;
                width:100%;
                letter-spacing:0.4px;
            " onmouseover="this.style.background='#16798e';this.style.transform='scale(1.04)';" 
              onmouseout="this.style.background='linear-gradient(90deg, #1e8fa2 60%, #55bde6 100%)';this.style.transform='scale(1)';">
                Register
            </button>
        </form>
        <div style="margin-top:1.2em;font-size:0.97em;color:#353b40;">
            Already have an account?
            <a href="login.php" style="color:#1e8fa2;font-weight:600;text-decoration:none;">Login</a>
        </div>
    </div>
    <script>
    // Responsive for mobile/tablet: stack fields
    function adjustRegisterBox() {
        const box = document.getElementById('register-box');
        let pairs = document.querySelectorAll('#register-box form > div[style*="display: flex"]');
        if(window.innerWidth < 600){
            pairs.forEach(d => d.style.flexDirection = 'column');
            box.style.maxWidth = "99vw";
            box.style.padding = "1.1em 0.4em";
        } else {
            pairs.forEach(d => d.style.flexDirection = 'row');
            box.style.maxWidth = "660px";
            box.style.padding = "2.3em 2.5em 2em 2.5em";
        }
    }
    window.addEventListener('resize', adjustRegisterBox);
    window.addEventListener('DOMContentLoaded', adjustRegisterBox);
    </script>
</body>
</html>
