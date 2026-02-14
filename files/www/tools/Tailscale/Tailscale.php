<?php
// --- 1. CONFIGURATION ---
error_reporting(0); 
$message = "";
$authUrl = ""; 

require_once '/data/adb/php8/files/www/auth/auth_functions.php'; 

define('TS_CMD', '/system/bin/tailscale');

// --- 2. LOGIKA PROSES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. AJAX STATUS CHECKER
    if (isset($_POST['action']) && $_POST['action'] === 'get_status') {
        ob_clean();
        header('Content-Type: application/json');

        // Ambil Data
        $status_raw = shell_exec("su -c \"" . TS_CMD . " status 2>&1\"");
        $real_ip = trim(shell_exec("su -c \"" . TS_CMD . " ip -4 2>/dev/null\""));
        
        // Analisa Status
        $is_stopped = (strpos($status_raw, 'stopped') !== false) || (strpos($status_raw, 'Tailscale is stopped') !== false);
        $needs_login = strpos($status_raw, 'Log in at') !== false || strpos($status_raw, 'Logged out') !== false || strpos($status_raw, 'NeedsLogin') !== false;

        // Default Response
        $response = [
            'is_connected' => false,
            'is_stopped' => $is_stopped,
            'needs_login' => $needs_login,
            'ip_display' => '...',
            'desc' => '...',
            'css_class' => 'inactive',
            'pill_text' => 'Unknown',
            'pill_class' => 'st-off',
            'icon' => '🚫',
            'log_data' => $status_raw // Data Log dikirim disini
        ];

        // Logika Status UI
        if ($is_stopped) {
            $response['ip_display'] = "Offline";
            $response['desc'] = "Engine is currently down";
            $response['css_class'] = "inactive";
            $response['pill_text'] = "Stopped";
            $response['pill_class'] = "st-off";
            $response['icon'] = "🚫";
        } elseif (!empty($real_ip)) {
            $response['is_connected'] = true;
            $response['ip_display'] = $real_ip;
            $response['desc'] = "Tailscale IPv4 Address";
            $response['css_class'] = "active";
            $response['pill_text'] = "Online";
            $response['pill_class'] = "st-on";
            $response['icon'] = "🌐";
        } elseif ($needs_login) {
            $response['desc'] = "Login needed to connect";
            $response['ip_display'] = "Auth Required";
            $response['css_class'] = "warning";
            $response['pill_text'] = "Auth Needed";
            $response['pill_class'] = "st-warn";
            $response['icon'] = "🔑";
        } else {
            $response['desc'] = "Service Starting...";
            $response['ip_display'] = "...";
            $response['pill_text'] = "Starting";
        }

        echo json_encode($response);
        exit;
    }

    // B. TOGGLE STATE
    if (isset($_POST['action']) && $_POST['action'] === 'set_state') {
        ob_clean(); 
        header('Content-Type: application/json');
        $targetState = $_POST['state']; 
        if ($targetState === 'up') {
            shell_exec("su -c \"nohup " . TS_CMD . " up > /dev/null 2>&1 &\"");
            echo json_encode(['status' => 'success', 'message' => 'Starting Tailscale...']);
        } else {
            shell_exec("su -c \"" . TS_CMD . " down > /dev/null 2>&1\"");
            shell_exec("su -c \"rm -f /data/adb/tailscale/tailscaled.state 2>/dev/null\""); 
            echo json_encode(['status' => 'success', 'message' => 'Service Stopped.']);
        }
        exit;
    }

    // C. LOGOUT
    if (isset($_POST['action']) && $_POST['action'] === 'logout') {
        shell_exec("su -c \"" . TS_CMD . " logout\"");
        $message = "Device logged out.";
    }

    // D. GET AUTH URL
    if (isset($_POST['action']) && $_POST['action'] === 'get_auth') {
        $cmd = "su -c \"timeout 5 " . TS_CMD . " login 2>&1\"";
        $output = shell_exec($cmd);
        if ($output && preg_match('/https:\/\/login\.tailscale\.com\/a\/[a-zA-Z0-9]+/', $output, $matches)) {
            $authUrl = $matches[0];
            $message = "Auth Link Generated!";
        } else {
            $message = "Service Stopped or Already Connected.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tailscale Dashboard</title>
    <style>
        :root {
            --bg: #f0f2f5; --card: #ffffff; --text: #1a202c; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-h: #ef6c00; --pri-soft: #fff3e0;
            --dang: #e53e3e; --dang-soft: #fff5f5; 
            --succ: #38a169; --succ-soft: #f0fff4;
            --warn: #d69e2e; --warn-soft: #fffff0;
            --term-bg: #1a202c; --term-txt: #63b3ed;
            --rad: 16px; --shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e2e8f0; --sub: #a0aec0; --border: #2d3748;
                --pri: #fb8c00; --pri-h: #ffa726; --pri-soft: #2c2115;
                --dang: #fc8181; --dang-soft: #2d1818;
                --succ: #68d391; --succ-soft: #15251d;
                --warn: #f6e05e; --warn-soft: #2c2a1e;
                --term-bg: #000000; --term-txt: #90cdf4;
                --shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 500px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; }
        
        .head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
        .logo { font-size: 1.2rem; font-weight: 800; color: var(--text); letter-spacing: -0.5px; }
        .logo span { color: var(--pri); }
        .status-pill { font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .st-on { background: var(--succ-soft); color: var(--succ); border: 1px solid var(--succ); }
        .st-off { background: var(--dang-soft); color: var(--dang); border: 1px solid var(--dang); }
        .st-warn { background: var(--warn-soft); color: var(--warn); border: 1px solid var(--warn); }

        .hero { background: var(--card); border-radius: var(--rad); padding: 30px 20px; text-align: center; box-shadow: var(--shadow); position: relative; overflow: hidden; border: 1px solid var(--border); }
        .pulse-ring { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; position: relative; }
        .pulse-ring.active { background: var(--succ-soft); }
        .pulse-ring.inactive { background: var(--dang-soft); }
        .pulse-ring.warning { background: var(--warn-soft); }
        .icon { font-size: 2rem; z-index: 2; }
        .pulse-ring.active::after { content: ''; position: absolute; width: 100%; height: 100%; border-radius: 50%; background: var(--succ); opacity: 0.4; animation: pulse 2s infinite; }
        .pulse-ring.warning::after { content: ''; position: absolute; width: 100%; height: 100%; border-radius: 50%; background: var(--warn); opacity: 0.4; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.7; } 70% { transform: scale(1.5); opacity: 0; } 100% { transform: scale(0.95); opacity: 0; } }

        .ip-display { font-family: 'SF Mono', 'Roboto Mono', monospace; font-size: 1.4rem; font-weight: 700; margin: 10px 0 5px; color: var(--text); }
        .lbl { font-size: 0.85rem; color: var(--sub); font-weight: 500; }

        .ctrl-area { margin-top: 25px; }
        .btn-main { width: 100%; border: none; padding: 18px; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; transition: transform 0.1s; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-main:active { transform: scale(0.98); }
        .btn-connect { background: var(--pri); color: #fff; box-shadow: 0 4px 10px rgba(251, 140, 0, 0.3); }
        .btn-stop { background: var(--card); color: var(--dang); border: 2px solid var(--dang); }

        .hidden { display: none !important; }

        .auth-box { background: var(--card); margin-top: 25px; border-radius: var(--rad); padding: 20px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .auth-head { font-size: 0.9rem; font-weight: 700; color: var(--text); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .term { background: var(--term-bg); padding: 15px; border-radius: 8px; color: var(--term-txt); font-family: monospace; font-size: 0.8rem; word-break: break-all; border-left: 3px solid var(--pri); }
        .term-btn { margin-top: 10px; width: 100%; background: transparent; border: 1px dashed var(--pri); color: var(--pri); padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; }

        .btn-logout { width: 100%; margin-top: 20px; background: transparent; color: var(--dang); border: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; opacity: 0.7; }

        /* LOG BOX STYLES */
        .log-container { margin-top: 25px; border-top: 1px solid var(--border); padding-top: 15px; }
        .log-toggle { font-size: 0.8rem; color: var(--sub); cursor: pointer; text-decoration: underline; text-align: center; display: block; margin-bottom: 10px; }
        .log-box { background: var(--term-bg); color: #a0aec0; padding: 15px; border-radius: 8px; font-family: 'SF Mono', monospace; font-size: 0.7rem; height: 150px; overflow-y: auto; white-space: pre-wrap; display: none; border: 1px solid var(--border); }
        .log-box.show { display: block; }
        
        #toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 10px 20px; border-radius: 30px; font-size: 0.85rem; font-weight: 600; opacity: 0; pointer-events: none; transition: 0.3s; z-index: 100; }
        #toast.show { opacity: 1; bottom: 40px; }
    </style>
</head>
<body>

    <div class="head">
        <div class="logo">TS<span>DASHBOARD</span></div>
        <div id="pill-box" class="status-pill st-off">Wait...</div>
    </div>

    <div class="hero">
        <div id="pulse-ring" class="pulse-ring inactive">
            <div id="hero-icon" class="icon">⌛</div>
        </div>
        <div id="ip-text" class="ip-display">Checking...</div>
        <div id="status-desc" class="lbl">Initializing Monitor</div>
    </div>

    <div class="ctrl-area">
        <div id="btn-wrapper">
            <button class="btn-main btn-connect" disabled>Loading...</button>
        </div>
    </div>

    <div id="auth-container" class="auth-box hidden">
        <div class="auth-head"><span>🔑</span> Authentication</div>
        
        <?php if (isset($authUrl) && !empty($authUrl)): ?>
            <div class="term" onclick="copyText(this)"><?= htmlspecialchars($authUrl) ?></div>
            <div style="text-align:center; font-size:0.75rem; color:var(--succ); margin-top:8px;">Link Generated! Click to Copy</div>
        <?php else: ?>
            <div style="font-size:0.85rem; color:var(--sub); margin-bottom:10px;">Device needs authentication.</div>
            <form method="POST">
                <button type="submit" name="action" value="get_auth" class="term-btn">GENERATE LOGIN URL</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="log-container">
        <span class="log-toggle" onclick="toggleLog()">Show/Hide System Logs</span>
        <div id="log-content" class="log-box">Loading logs...</div>
    </div>

    <div id="logout-container" class="hidden">
        <form method="POST">
            <button type="submit" name="action" value="logout" class="btn-logout" onclick="return confirm('Disconnect & Logout?')">Logout from Network</button>
        </form>
    </div>

    <div id="toast">Processing...</div>

    <script>
        const t = document.getElementById("toast");
        function msg(m) { t.innerText = m; t.className = "show"; setTimeout(() => t.className = "", 3000); }

        function copyText(el) {
            const text = el.innerText;
            const ta = document.createElement("textarea");
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand("copy");
            document.body.removeChild(ta);
            msg("Link Copied!");
        }

        function toggleLog() {
            document.getElementById('log-content').classList.toggle('show');
        }

        // --- AJAX POLLING ---
        function updateDashboard() {
            const fd = new FormData();
            fd.append('action', 'get_status');

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                document.getElementById('pill-box').className = 'status-pill ' + d.pill_class;
                document.getElementById('pill-box').innerText = d.pill_text;

                document.getElementById('pulse-ring').className = 'pulse-ring ' + d.css_class;
                document.getElementById('hero-icon').innerText = d.icon;

                document.getElementById('ip-text').innerText = d.ip_display;
                document.getElementById('status-desc').innerText = d.desc;

                // Update Log Box
                document.getElementById('log-content').innerText = d.log_data;

                const btnWrap = document.getElementById('btn-wrapper');
                if (d.is_connected) {
                    btnWrap.innerHTML = `<button onclick="toggleTailscale('down')" class="btn-main btn-stop"><span>⏹</span> Stop Service</button>`;
                } else {
                    btnWrap.innerHTML = `<button onclick="toggleTailscale('up')" class="btn-main btn-connect"><span>🚀</span> Connect Tailscale</button>`;
                }

                const showAuth = (!d.is_connected && !d.is_stopped);
                document.getElementById('auth-container').classList.toggle('hidden', !showAuth);
                document.getElementById('logout-container').classList.toggle('hidden', !d.is_connected);
            })
            .catch(e => console.log("Polling error:", e));
        }

        function toggleTailscale(state) {
            msg(state === 'up' ? "Starting..." : "Stopping...");
            const fd = new FormData(); 
            fd.append('action', 'set_state'); 
            fd.append('state', state);

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                msg(d.message);
                updateDashboard();
            });
        }

        updateDashboard();
        setInterval(updateDashboard, 3000); // Update setiap 3 detik
    </script>

</body>
</html>