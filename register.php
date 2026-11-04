<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteDeploy — Create Account</title>
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
            padding: 24px;
        }
        .register-container {
            display: grid; grid-template-columns: 1fr 1.1fr; max-width: 980px; width: 100%;
            background: var(--surface); border-radius: 20px; overflow: hidden;
            box-shadow: var(--shadow-lg); min-height: 600px;
        }
        .register-left {
            background: var(--dark); color: var(--text-on-dark); padding: 50px 44px;
            display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden;
        }
        .register-left::before {
            content: ''; position: absolute; top: -60px; right: -60px; width: 220px; height: 220px;
            background: var(--lime); border-radius: 50%; opacity: 0.15;
        }
        .register-left::after {
            content: ''; position: absolute; bottom: -40px; left: -40px; width: 160px; height: 160px;
            background: var(--lime); border-radius: 50%; opacity: 0.1;
        }
        .register-left .brand {
            display: flex; align-items: center; gap: 12px; margin-bottom: 32px; position: relative; z-index: 1;
        }
        .register-left .brand svg { width: 36px; height: 36px; }
        .register-left .brand span { font-size: 1.5rem; font-weight: 700; }
        .register-left h1 { font-size: 2.1rem; font-weight: 700; line-height: 1.2; margin-bottom: 16px; position: relative; z-index: 1; }
        .register-left h1 .hl { background: var(--lime); color: var(--dark); padding: 2px 10px; border-radius: 6px; }
        .register-left p { color: rgba(255,255,255,0.65); font-size: 0.95rem; line-height: 1.6; position: relative; z-index: 1; margin-bottom: 24px; }

        .feature-list { list-style: none; position: relative; z-index: 1; display: flex; flex-direction: column; gap: 12px; }
        .feature-item { display: flex; align-items: center; gap: 10px; font-size: 0.88rem; color: rgba(255,255,255,0.85); }
        .feature-item svg { width: 18px; height: 18px; stroke: var(--lime); flex-shrink: 0; }

        .register-right { padding: 48px 44px; display: flex; flex-direction: column; justify-content: center; }
        .register-right h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 6px; }
        .register-right .subtitle { color: var(--text-muted); margin-bottom: 24px; font-size: 0.88rem; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 6px;
        }
        .form-group input {
            width: 100%; padding: 11px 14px; border: 2px solid var(--border); border-radius: var(--radius-sm);
            font-family: var(--font); font-size: 0.92rem; color: var(--text);
            transition: var(--transition); outline: none; background: var(--bg);
        }
        .form-group input:focus { border-color: var(--lime); background: var(--surface); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 13px 24px; border-radius: var(--radius-pill); border: none;
            font-family: var(--font); font-weight: 700; font-size: 0.95rem; cursor: pointer;
            transition: var(--transition); text-decoration: none; margin-top: 8px;
        }
        .btn-lime { background: var(--lime); color: var(--dark); }
        .btn-lime:hover { background: var(--lime-hover); transform: translateY(-1px); }
        .btn-lime:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .error-msg {
            background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: var(--radius-sm);
            font-size: 0.84rem; margin-bottom: 16px; display: none; border-left: 3px solid var(--red);
        }
        .error-msg.show { display: block; }

        .register-footer { text-align: center; margin-top: 20px; font-size: 0.85rem; color: var(--text-muted); }
        .register-footer a { color: var(--dark); font-weight: 600; text-decoration: underline; }
        .register-footer a:hover { color: #000; }

        @media (max-width: 820px) {
            .register-container { grid-template-columns: 1fr; max-width: 480px; }
            .register-left { display: none; }
            .form-row { grid-template-columns: 1fr; }
            .register-right { padding: 36px 28px; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-left">
            <div class="brand">
                <svg viewBox="0 0 36 36" fill="none">
                    <rect width="36" height="36" rx="8" fill="#B9FF66" opacity="0.2"/>
                    <path d="M9 18h18M18 9v18M11 11l14 14M25 11L11 25" stroke="#B9FF66" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <span>SiteDeploy</span>
            </div>
            <h1>Join the field<br><span class="hl">operations team</span></h1>
            <p>Create your technician account to map floorplans, place hardware pins, and manage deployment progress in real time.</p>
            
            <ul class="feature-list">
                <li class="feature-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Interactive zero-misalignment floorplans
                </li>
                <li class="feature-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Live hardware status updates from the field
                </li>
                <li class="feature-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Automatic shared access to site blueprints
                </li>
            </ul>
        </div>

        <div class="register-right">
            <h2>Create Account</h2>
            <p class="subtitle">Enter your details to register as a new technician</p>

            <div class="error-msg" id="errorMsg"></div>

            <form id="registerForm" onsubmit="handleRegister(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" id="username" placeholder="e.g. tech_wan" required autofocus pattern="[a-zA-Z0-9_]{3,30}" title="3-30 letters, numbers, or underscores">
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" id="full_name" placeholder="e.g. Wan Pidin" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" id="email" placeholder="e.g. wan@company.com" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" id="password" placeholder="Min 6 characters" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input type="password" id="confirm_password" placeholder="Repeat password" required minlength="6">
                    </div>
                </div>

                <button type="submit" class="btn btn-lime" id="btnRegister">
                    Create Account &amp; Start
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

            <div class="register-footer">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        async function handleRegister(e) {
            e.preventDefault();
            const btn = document.getElementById('btnRegister');
            const errEl = document.getElementById('errorMsg');
            errEl.classList.remove('show');

            const username = document.getElementById('username').value.trim();
            const full_name = document.getElementById('full_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;

            if (password !== confirm_password) {
                errEl.textContent = 'Passwords do not match!';
                errEl.classList.add('show');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Creating account...';

            try {
                const res = await fetch('api/register_public.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, full_name, email, password }),
                });
                const json = await res.json();

                if (json.success) {
                    btn.textContent = 'Success! Redirecting...';
                    window.location.href = json.redirect || 'dashboard.php';
                } else {
                    errEl.textContent = json.error || 'Registration failed';
                    errEl.classList.add('show');
                    btn.disabled = false;
                    btn.innerHTML = 'Create Account &amp; Start <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
                }
            } catch (err) {
                errEl.textContent = 'Network or server error: ' + err.message;
                errEl.classList.add('show');
                btn.disabled = false;
                btn.innerHTML = 'Create Account &amp; Start';
            }
        }
    </script>
</body>
</html>

