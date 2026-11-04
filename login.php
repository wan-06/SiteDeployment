<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteDeploy — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #F3F3F3; --surface: #FFFFFF; --dark: #191A23; --dark-card: #292A32;
            --lime: #B9FF66; --lime-hover: #a8f050; --text: #191A23; --text-muted: #6b6d7b;
            --text-on-dark: #FFFFFF; --border: #E0E0E0; --red: #FF6B6B;
            --radius: 14px; --radius-sm: 8px; --radius-pill: 100px;
            --shadow: 0 2px 12px rgba(0,0,0,0.06); --shadow-lg: 0 8px 32px rgba(0,0,0,0.10);
            --font: 'Space Grotesk', system-ui, sans-serif;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            font-family: var(--font); background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .login-container {
            display: grid; grid-template-columns: 1fr 1fr; max-width: 960px; width: 100%;
            background: var(--surface); border-radius: 20px; overflow: hidden;
            box-shadow: var(--shadow-lg); min-height: 540px;
        }
        .login-left {
            background: var(--dark); color: var(--text-on-dark); padding: 60px 48px;
            display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden;
        }
        .login-left::before {
            content: ''; position: absolute; top: -60px; right: -60px; width: 200px; height: 200px;
            background: var(--lime); border-radius: 50%; opacity: 0.15;
        }
        .login-left::after {
            content: ''; position: absolute; bottom: -40px; left: -40px; width: 150px; height: 150px;
            background: var(--lime); border-radius: 50%; opacity: 0.1;
        }
        .login-left .brand {
            display: flex; align-items: center; gap: 12px; margin-bottom: 40px; position: relative; z-index: 1;
        }
        .login-left .brand svg { width: 36px; height: 36px; }
        .login-left .brand span { font-size: 1.5rem; font-weight: 700; }
        .login-left h1 { font-size: 2.2rem; font-weight: 700; line-height: 1.2; margin-bottom: 16px; position: relative; z-index: 1; }
        .login-left h1 .hl { background: var(--lime); color: var(--dark); padding: 2px 10px; border-radius: 6px; }
        .login-left p { color: rgba(255,255,255,0.6); font-size: 0.95rem; line-height: 1.6; position: relative; z-index: 1; }

        .login-right { padding: 60px 48px; display: flex; flex-direction: column; justify-content: center; }
        .login-right h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; }
        .login-right .subtitle { color: var(--text-muted); margin-bottom: 32px; font-size: 0.92rem; }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-size: 0.78rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 6px;
        }
        .form-group input {
            width: 100%; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-sm);
            font-family: var(--font); font-size: 0.95rem; color: var(--text);
            transition: var(--transition); outline: none; background: var(--bg);
        }
        .form-group input:focus { border-color: var(--lime); background: var(--surface); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 14px 24px; border-radius: var(--radius-pill); border: none;
            font-family: var(--font); font-weight: 700; font-size: 1rem; cursor: pointer;
            transition: var(--transition); text-decoration: none;
        }
        .btn-lime { background: var(--lime); color: var(--dark); }
        .btn-lime:hover { background: var(--lime-hover); transform: translateY(-1px); }
        .btn-lime:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .error-msg {
            background: #fee2e2; color: #991b1b; padding: 10px 16px; border-radius: var(--radius-sm);
            font-size: 0.85rem; margin-bottom: 16px; display: none; border-left: 3px solid var(--red);
        }
        .error-msg.show { display: block; }

        .login-footer { text-align: center; margin-top: 24px; font-size: 0.82rem; color: var(--text-muted); }

        @media (max-width: 768px) {
            .login-container { grid-template-columns: 1fr; max-width: 440px; }
            .login-left { padding: 40px 32px; min-height: auto; }
            .login-right { padding: 40px 32px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="brand">
                <svg viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="8" fill="#B9FF66" opacity="0.2"/>
                    <path d="M9 18h18M18 9v18M11 11l14 14M25 11L11 25" stroke="#B9FF66" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <span>SiteDeploy</span>
            </div>
            <h1>Map your<br><span class="hl">deployments</span></h1>
            <p>Drop pins on floorplans, track hardware installation status, and keep your entire field team synced from one dashboard.</p>
        </div>

        <div class="login-right">
            <h2>Welcome back</h2>
            <p class="subtitle">Sign in to your account to continue</p>

            <div class="error-msg" id="errorMsg"></div>

            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" id="username" placeholder="Enter your username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-lime" id="btnLogin">
                    Sign In
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

            <div class="login-footer">
                <div style="margin-bottom: 8px;">
                    Don't have an account? <a href="register.php" style="color:var(--dark); font-weight:600; text-decoration:underline;">Create Account</a>
                </div>
                <div>Default admin: <strong>admin</strong> / <strong>admin123</strong></div>
            </div>
        </div>
    </div>

    <script>
        async function handleLogin(e) {
            e.preventDefault();
            const btn = document.getElementById('btnLogin');
            const errEl = document.getElementById('errorMsg');
            errEl.classList.remove('show');
            btn.disabled = true;
            btn.textContent = 'Signing in...';

            try {
                const res = await fetch('api/login_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: document.getElementById('username').value.trim(),
                        password: document.getElementById('password').value,
                    }),
                });
                const json = await res.json();

                if (json.success) {
                    window.location.href = json.redirect || 'dashboard.php';
                } else {
                    errEl.textContent = json.error || 'Login failed';
                    errEl.classList.add('show');
                    btn.disabled = false;
                    btn.innerHTML = 'Sign In <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
                }
            } catch (err) {
                errEl.textContent = 'Network error: ' + err.message;
                errEl.classList.add('show');
                btn.disabled = false;
                btn.innerHTML = 'Sign In';
            }
        }
    </script>
</body>
</html>

