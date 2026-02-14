<?php
session_start();

// Load credentials
$credentials = include 'credentials.php';
$stored_username = $credentials['username'];
$stored_hashed_password = $credentials['hashed_password'];

$config_file = __DIR__ . '/config.json';
if (!file_exists($config_file)) {
    die('Error: Configuration file not found.');
}
$config = json_decode(file_get_contents($config_file), true);

define('LOGIN_ENABLED', $config['LOGIN_ENABLED']);

if (!LOGIN_ENABLED) {
    $_SESSION['login_disabled'] = true;
}

// Remember Me functionality
if (isset($_COOKIE['remember_me']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_COOKIE['remember_me'];
    $_SESSION['username'] = $stored_username;
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    // $remember = isset($_POST['remember']); // Opsi Remember Me (jika ingin diaktifkan di masa depan)

    if ($username === $stored_username && password_verify($password, $stored_hashed_password)) {
        $_SESSION['user_id'] = session_id();
        $_SESSION['username'] = $username;

        header("Location: /");
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#fb8c00">
    <link rel="icon" href="../webui/assets/luci.ico" type="image/x-icon">
    <title>RameeShop Login</title>
    <style>
        /* --- CSS VARIABLES (ORANGE THEME) --- */
        :root {
            /* Light Mode */
            --bg-gradient: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            --card-bg: rgba(255, 255, 255, 0.9);
            --text-main: #2d3748;
            --text-muted: #718096;
            --input-bg: #ffffff;
            --input-border: #e2e8f0;
            
            /* PRIMARY ORANGE */
            --primary: #fb8c00;
            --primary-hover: #ef6c00;
            --primary-shadow: rgba(251, 140, 0, 0.3);
            
            --shadow: 0 8px 30px rgba(0,0,0,0.08);
            --error-bg: #fff5f5;
            --error-text: #c53030;
            --logo-gradient: linear-gradient(90deg, #ef6c00, #ffa726, #ef6c00);
        }

        /* Dark Mode */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-gradient: linear-gradient(135deg, #121212 0%, #1e1e1e 100%);
                --card-bg: rgba(30, 30, 30, 0.85);
                --text-main: #e0e0e0;
                --text-muted: #a0a0a0;
                --input-bg: #2c2c2c;
                --input-border: #424242;
                
                --primary: #fb8c00;
                --primary-hover: #ff9800;
                --primary-shadow: rgba(251, 140, 0, 0.4);
                
                --shadow: 0 10px 30px rgba(0,0,0,0.5);
                --error-bg: rgba(197, 48, 48, 0.2);
                --error-text: #feb2b2;
                --logo-gradient: linear-gradient(90deg, #ff9800, #ffcc80, #ff9800);
            }
        }

        /* --- RESET & BASE --- */
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden; /* Mencegah scroll saat animasi masuk */
        }

        /* --- ANIMATIONS --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        @keyframes gradientText {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* --- LOGIN CARD --- */
        .login-card {
            background: var(--card-bg);
            width: 100%;
            max-width: 360px;
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
            text-align: center;
        }

        /* --- LOGO ANIMASI --- */
        .brand-logo {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: var(--logo-gradient);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
            animation: gradientText 3s linear infinite, float 4s ease-in-out infinite;
            display: inline-block;
            letter-spacing: -0.5px;
        }

        .subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-weight: 500;
        }

        /* --- FORM ELEMENTS --- */
        .input-group { margin-bottom: 1.2rem; position: relative; text-align: left; }
        
        .input-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            width: 20px; height: 20px;
            fill: var(--text-muted); transition: 0.3s; pointer-events: none;
        }

        input {
            width: 100%;
            padding: 14px 14px 14px 48px;
            border: 2px solid var(--input-border);
            background-color: var(--input-bg);
            border-radius: 14px;
            font-size: 0.95rem;
            color: var(--text-main);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 4px 12px var(--primary-shadow);
            transform: translateY(-2px);
        }

        input:focus + .input-icon { fill: var(--primary); }

        /* --- CHECKBOX --- */
        .options { display: flex; align-items: center; margin-bottom: 1.5rem; justify-content: space-between; }
        .checkbox-wrapper { display: flex; align-items: center; cursor: pointer; user-select: none; }
        .checkbox-wrapper input { display: none; }
        
        .checkmark {
            width: 20px; height: 20px;
            border: 2px solid var(--text-muted);
            border-radius: 6px;
            margin-right: 10px;
            position: relative;
            transition: 0.2s;
        }
        
        .checkbox-wrapper input:checked + .checkmark {
            background-color: var(--primary); border-color: var(--primary);
        }
        
        .checkmark::after {
            content: ''; position: absolute; left: 6px; top: 2px; width: 4px; height: 9px;
            border: solid white; border-width: 0 2px 2px 0;
            transform: rotate(45deg) scale(0); transition: 0.2s;
        }
        .checkbox-wrapper input:checked + .checkmark::after { transform: rotate(45deg) scale(1); }
        .label-text { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

        /* --- BUTTON --- */
        .btn-login {
            width: 100%; padding: 15px;
            background-color: var(--primary);
            color: #fff; border: none;
            border-radius: 14px;
            font-size: 1rem; font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 15px var(--primary-shadow);
            position: relative; overflow: hidden;
        }

        .btn-login::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .btn-login:hover { 
            background-color: var(--primary-hover); 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px var(--primary-shadow);
        }
        .btn-login:hover::after { left: 100%; }
        .btn-login:active { transform: scale(0.98); }

        /* --- ERROR --- */
        .error-msg {
            background-color: var(--error-bg);
            color: var(--error-text);
            padding: 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-top: 1.5rem;
            font-weight: 600;
            border: 1px solid var(--error-text);
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-logo">RameeShop</div>
        <div class="subtitle">Access Your Dashboard</div>

        <form method="POST">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required autocomplete="off">
                <svg class="input-icon" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>

            <div class="input-group">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <svg class="input-icon" viewBox="0 0 24 24">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
            </div>

            <div class="options">
                <label class="checkbox-wrapper">
                    <input type="checkbox" id="showPassword">
                    <span class="checkmark"></span>
                    <span class="label-text">Show Password</span>
                </label>
            </div>

            <button type="submit" class="btn-login">
                Sign In
            </button>

            <?php if (isset($error)): ?>
                <div class="error-msg">
                    <svg style="width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:5px; fill:currentColor;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const showPasswordCheckbox = document.getElementById('showPassword');

        showPasswordCheckbox.addEventListener('change', function() {
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    </script>

</body>
</html>