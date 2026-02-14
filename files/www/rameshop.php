<?php
require_once __DIR__ . '/auth/auth_functions.php';

// --- API UNTUK CEK KONEKSI SERVER ---
if (isset($_GET['api']) && $_GET['api'] === 'check_connection') {
    header('Content-Type: application/json');
    // Ping ke Google DNS
    $connected = @fsockopen("8.8.8.8", 53, $errno, $errstr, 2);
    if ($connected) {
        fclose($connected);
        echo json_encode(['status' => 'online']);
    } else {
        echo json_encode(['status' => 'offline']);
    }
    exit;
}
// ------------------------------------

ob_start();

// --- KONFIGURASI ---
$p = $_SERVER['HTTP_HOST'];
$x = explode(':', $p);
$host = $x[0];

if (session_status() == PHP_SESSION_NONE) {
    session_start(['cookie_lifetime' => 31536000, 'read_and_close' => false]);
}

if (!isset($_SESSION['cached_device_name'])) {
    $model = trim(shell_exec('getprop ro.product.model'));
    $deviceName = ucfirst($model) ?: "Android Device";
    $_SESSION['cached_device_name'] = $deviceName;
} else {
    $deviceName = $_SESSION['cached_device_name'];
}

include __DIR__ . '/auth/config.php';

if (defined('LOGIN_ENABLED') && LOGIN_ENABLED && !isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php'); 
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_REQUEST['actionButton'] ?? '';
    if (isset($moduledir)) { 
        if ($action == "disable") {
            $myfile = fopen("$moduledir/disable", "w");
            if($myfile) fclose($myfile);
        } elseif ($action == "enable") {
            if (file_exists("$moduledir/disable")) unlink("$moduledir/disable");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#fb8c00">
    <title>AWD Ui - <?php echo $deviceName; ?></title>
    <link rel="icon" href="webui/assets/luci.ico" type="image/x-icon">
    
    <style>
        /* --- CSS VARIABLES --- */
        :root {
            --bg-body: #f8f9fa; --bg-sidebar: #ffffff; --bg-header: #ffffff;
            --text-main: #2d3748; --text-muted: #718096;
            --primary: #fb8c00; --primary-dark: #ef6c00; --primary-soft: rgba(251, 140, 0, 0.12);
            --border-color: #e2e8f0; --shadow: 0 4px 15px rgba(0,0,0,0.05);
            --hover-bg: #fff3e0; --scrollbar: #cbd5e0;
            
            /* Warna Status Baru (Lebih Modern) */
            --success-bg: rgba(76, 175, 80, 0.15); 
            --success-text: #2e7d32;
            --danger-bg: rgba(244, 67, 54, 0.15);
            --danger-text: #c62828;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-body: #121212; --bg-sidebar: #1e1e1e; --bg-header: #1e1e1e;
                --text-main: #e0e0e0; --text-muted: #a0a0a0;
                --primary: #fb8c00; --primary-dark: #ff9800; --primary-soft: rgba(251, 140, 0, 0.15);
                --border-color: #333333; --shadow: 0 4px 6px rgba(0,0,0,0.4);
                --hover-bg: #2c2c2c; --scrollbar: #424242;
                
                --success-bg: rgba(76, 175, 80, 0.2); 
                --success-text: #81c784;
                --danger-bg: rgba(244, 67, 54, 0.2);
                --danger-text: #e57373;
            }
        }

        /* --- BASE STYLES --- */
        * { box-sizing: border-box; outline: none; -webkit-tap-highlight-color: transparent; }
        body { 
            margin: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            background-color: var(--bg-body); color: var(--text-main); 
            overflow-x: hidden; -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; 
        }

        /* --- SIDEBAR --- */
        .sidebar { 
            height: 100vh; width: 260px; position: fixed; top: 0; left: -260px; 
            background-color: var(--bg-sidebar); border-right: 1px solid var(--border-color); 
            z-index: 1000; overflow-y: auto; display: flex; flex-direction: column; 
            box-shadow: var(--shadow); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            will-change: transform; 
        }
        .sidebar.open { transform: translateX(260px); }
        
        .logo { 
            padding: 25px 20px 15px 20px;
            font-size: 1.4rem; font-weight: 800; color: var(--text-main); 
            display: flex; align-items: center; 
            gap: 12px; letter-spacing: 0.5px; 
        }
        .logo-animated { 
            background: linear-gradient(90deg, var(--primary), var(--primary-dark), #ffd54f, var(--primary)); 
            background-size: 300% auto; -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; background-clip: text; color: transparent; 
            animation: gradientFlow 3s linear infinite; display: inline-block; will-change: background-position; 
        }
        @keyframes gradientFlow { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }

        /* --- REDESIGN STATUS INTERNET --- */
        .status-wrapper {
            padding: 0 20px 15px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 10px;
        }

        .connection-card {
            display: flex;
            align-items: center;
            justify-content: space-between; /* Icon kiri, text kanan */
            padding: 10px 15px;
            border-radius: 12px; /* Sama dengan menu items */
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        /* Style saat Online */
        .connection-card.online {
            background-color: var(--success-bg);
            color: var(--success-text);
            border-color: rgba(76, 175, 80, 0.3);
        }

        /* Style saat Offline */
        .connection-card.offline {
            background-color: var(--danger-bg);
            color: var(--danger-text);
            border-color: rgba(244, 67, 54, 0.3);
        }

        .status-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        /* Animasi Denyut (Pulse) */
        .connection-card.online .status-dot {
            background-color: var(--success-text);
            box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
            animation: pulse-green 2s infinite;
        }
        .connection-card.offline .status-dot {
            background-color: var(--danger-text);
            box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7);
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(76, 175, 80, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
        }
        @keyframes pulse-red {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(244, 67, 54, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(244, 67, 54, 0); }
        }

        .status-icon {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* --- MENU ITEMS --- */
        ul { list-style: none; padding: 10px 15px; margin: 0; flex-grow: 1; } 
        li { margin-bottom: 5px; }
        
        a, .dropdown-btn { 
            display: flex; align-items: center; padding: 12px 16px; 
            color: var(--text-muted); text-decoration: none; border-radius: 12px; 
            font-size: 0.95rem; font-weight: 600; transition: background-color 0.2s, color 0.2s; 
            cursor: pointer; background: transparent; border: none; width: 100%; text-align: left; 
        }
        a:hover, .dropdown-btn:hover, a.active-link { 
            background-color: var(--hover-bg); color: var(--primary); transform: translateX(3px); 
        }
        a.active-link { background-color: var(--primary-soft); }
        
        .icon { 
            width: 22px; height: 22px; margin-right: 12px; stroke-width: 2; fill: none; 
            stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; transition: 0.2s; 
        }
        a:hover .icon, .active-link .icon { stroke: var(--primary); }
        
        .arrow { margin-left: auto; width: 16px; height: 16px; transition: transform 0.3s; }
        .dropdown-btn.active .arrow { transform: rotate(180deg); }
        
        .dropdown-container { display: none; padding-left: 12px; margin-top: 2px; }
        .dropdown-container.show { display: block; animation: slideDown 0.2s ease-out; }
        .dropdown-container a { 
            font-size: 0.9rem; padding: 10px 15px; font-weight: 500; 
            border-left: 2px solid transparent; border-radius: 0 12px 12px 0; 
        }
        .dropdown-container a:hover { border-left-color: var(--primary); }

        /* --- HEADER & LAYOUT --- */
        .header { 
            position: fixed; top: 0; left: 0; right: 0; height: 65px; 
            background-color: var(--bg-header); border-bottom: 1px solid var(--border-color); 
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px; 
            z-index: 900; transition: padding-left 0.3s; box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
        }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .toggle-btn { 
            background: none; border: none; cursor: pointer; color: var(--text-main); 
            padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; 
        }
        .toggle-btn:hover { background: var(--hover-bg); color: var(--primary); }
        .device-name { font-weight: 700; font-size: 1rem; color: var(--primary); letter-spacing: 0.5px; }

        .quick-menu { 
            display: flex; gap: 12px; position: absolute; left: 50%; transform: translateX(-50%); 
            background: var(--bg-body); padding: 5px 15px; border-radius: 30px; 
            border: 1px solid var(--border-color); 
        }
        .quick-item { 
            color: var(--text-muted); cursor: pointer; padding: 8px; border-radius: 50%; 
            transition: transform 0.2s, background-color 0.2s; display: flex; align-items: center; 
        }
        .quick-item:hover { background: var(--primary-soft); color: var(--primary); transform: scale(1.1); }
        @media (max-width: 768px) { .quick-menu { display: none; } }

        .header-right { display: flex; align-items: center; gap: 10px; }
        .action-btn { 
            background: transparent; border: 1px solid var(--border-color); color: var(--text-main); 
            width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; 
            justify-content: center; cursor: pointer; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        .action-btn svg { width: 20px; height: 20px; }
        .btn-refresh:hover { color: var(--primary); border-color: var(--primary); background: var(--hover-bg); transform: rotate(15deg); }
        .btn-logout:hover { color: #f5365c; border-color: #f5365c; background: rgba(245, 54, 92, 0.08); transform: scale(1.05); }

        .main-content { 
            margin-top: 65px; height: calc(100vh - 65px); width: 100%; transition: margin-left 0.3s; 
            background-color: var(--bg-body); background-image: radial-gradient(var(--border-color) 1px, transparent 1px); 
            background-size: 20px 20px; 
        }
        iframe { width: 100%; height: 100%; border: none; display: block; }

        .overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.4); z-index: 950; backdrop-filter: blur(2px); 
            opacity: 0; pointer-events: none; transition: opacity 0.3s; will-change: opacity; 
        }
        .overlay.active { opacity: 1; pointer-events: auto; }
        
        .loader { 
            position: fixed; top: 80px; left: 50%; transform: translateX(-50%); 
            background: var(--bg-sidebar); padding: 10px 20px; border-radius: 30px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.1); font-size: 0.9rem; font-weight: bold; 
            color: var(--primary); display: none; align-items: center; gap: 10px; 
            z-index: 2000; border: 1px solid var(--border-color); animation: slideDown 0.3s; 
        }
        .loader-spin { 
            width: 18px; height: 18px; border: 2px solid var(--primary-soft); 
            border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s infinite linear; 
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }
        @keyframes slideDown { from { opacity: 0; transform: translate(-50%, -10px); } to { opacity: 1; transform: translate(-50%, 0); } }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
        ::-webkit-scrollbar-track { background: transparent; }

        @media (min-width: 992px) { 
            .sidebar { transform: translateX(260px); } 
            .header { padding-left: 280px; } 
            .main-content { margin-left: 260px; width: calc(100% - 260px); } 
            .toggle-btn { display: none; } 
            .overlay { display: none !important; } 
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="header-left">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <svg class="icon" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
            <div class="device-name"><?php echo $deviceName; ?></div>
        </div>

        <div class="quick-menu">
            <div class="quick-item" onclick="loadContent('/webui/monitor/Overview.php')" title="Overview">
                <svg class="icon" style="margin:0" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            </div>
            <div class="quick-item" onclick="loadContent('http://<?php echo $host; ?>:3001')" title="Terminal">
                <svg class="icon" style="margin:0" viewBox="0 0 24 24"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
            </div>
            <div class="quick-item" onclick="loadContent('/tiny/opsi.php')" title="File Manager">
                <svg class="icon" style="margin:0" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            </div>
        </div>

        <div class="header-right">
            <button class="action-btn btn-refresh" onclick="refreshFrame()" title="Refresh Page">
                <svg class="icon" style="margin:0" viewBox="0 0 24 24"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
            </button>
            <button class="action-btn btn-logout" onclick="logoutApp()" title="Logout">
                <svg class="icon" style="margin:0" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </button>
        </div>
    </header>
    <nav id="sidebar" class="sidebar">
        <div class="logo">
            <svg class="icon" style="color:var(--primary)" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            <span class="logo-animated">AWD UI</span>
        </div>
        
        <div class="status-wrapper">
            <div id="conn-card" class="connection-card offline">
                <div class="status-content">
                    <span class="status-dot"></span>
                    <span id="conn-text">Checking...</span>
                </div>
                <svg class="status-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
        </div>
        
        <ul>
            <li>
                <a onclick="loadContent('/webui/monitor/Overview.php')" class="active-link">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> 
                    Overview
                </a>
            </li>
            
            <li>
                <a href="http://<?php echo $host; ?>:9090/ui/?hostname=<?php echo $host; ?>&port=9090" target="_blank">
                    <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg> 
                    Dashboard
                </a>
            </li>

            <li>
                <button class="dropdown-btn">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> 
                    Android <svg class="icon arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="dropdown-container">
                    <a onclick="loadContent('/tools/ScreenCap/Screen.php')">Screen Cap</a>
                    <a onclick="loadContent('/tools/apkmgr.php')">APK Manager</a>
                    <a onclick="loadContent('/tiny/opsi.php')">File Manager</a>
                    <a onclick="loadContent('/tools/MagiskManager.php')">Magisk Manager</a>
                    <a onclick="loadContent('/tools/smsviewer.php')">SMS Viewer</a>
                </div>
            </li>

            <li>
                <button class="dropdown-btn">
                    <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> 
                    Tools <svg class="icon arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="dropdown-container">
                    <a onclick="loadContent('/tools/opsi_box.php')">Box for Root</a>
                    <a onclick="loadContent('/tools/Tailscale/Tailscale.php')">Tailscale</a>
                </div>
            </li>

            <li>
                <button class="dropdown-btn">
                    <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> 
                    Network <svg class="icon arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="dropdown-container">
                    <a onclick="loadContent('/tools/vnstat.php')">Bandwidth</a>
                    <a onclick="loadContent('/tools/modpes.php')">Airplane Pilot</a>
                    <a onclick="loadContent('/tools/Fix_ttl.php')">TTL Config</a>
                    <a onclick="loadContent('/tools/auto_airplane.php')">Auto Airplane</a>
                    <a onclick="loadContent('/tools/sim-configuration.php')">SIM Config</a>
                    <a onclick="loadContent('/tools/wireless_mgr.php')">Wireless Mgr</a>
                    <a onclick="loadContent('/tools/interface/interface.php')">Interface</a>
                </div>
            </li>

            <li>
                <button class="dropdown-btn">
                    <svg class="icon" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg> 
                    Services <svg class="icon arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="dropdown-container">
                    <a onclick="loadContent('/tools/AdBlockTest.php')">AdBlock Test</a>
                    <a onclick="loadContent('/tools/DnsCheckTools.php')">DNS Check</a>
                    <a onclick="loadContent('/tools/DnsLeakTest.php')">DNS Leak</a>
                    <a onclick="loadContent('/tools/Speedtest.php')">Speed Test</a>
                </div>
            </li>

            <li>
                <button class="dropdown-btn">
                    <svg class="icon" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg> 
                    Status <svg class="icon arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="dropdown-container">
                    <a onclick="loadContent('/webui/monitor/BatteryMonitor.php')">Battery</a>
                    <a onclick="loadContent('/webui/monitor/CpuMonitor.php')">CPU</a>
                    <a onclick="loadContent('/webui/monitor/RamMonitor.php')">RAM</a>
                    <a onclick="loadContent('/webui/monitor/SignalMonitor.php')">Signal</a>
                    <a onclick="loadContent('/webui/monitor/StorageMonitor.php')">Storage</a>
                </div>
            </li>

            <li>
                <button class="dropdown-btn">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M4 17l6-6-6-6"></path><path d="M12 19h8"></path></svg> 
                    System <svg class="icon arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="dropdown-container">
                    <a onclick="loadContent('/tools/StartUp.php')">StartUp</a>
                    <a onclick="loadContent('/auth/admincombo.php')">Security</a>
                    <a onclick="loadContent('tools/PowerManager.php')">Power</a>
                    <a onclick="loadContent('http://<?php echo $host; ?>:3001')">Terminal</a>
                </div>
            </li>

            <li>
                <a onclick="loadContent('/auth/updater.php')">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg> 
                    Updater
                </a>
            </li>

            <li>
                <a onclick="loadContent('about.php')">
                    <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> 
                    Readme
                </a>
            </li>
        </ul>
    </nav>
    <main class="main-content">
        <div id="loader" class="loader">
            <div class="loader-spin"></div> Loading...
        </div>
        <div id="iframeContainer" style="width:100%; height:100%;"></div>
    </main>
    <div id="overlay" class="overlay" onclick="toggleSidebar()"></div>
    
    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        // --- LOGIC KONEKSI SERVER (UPDATED UI) ---
        function checkServerConnection() {
            fetch('?api=check_connection')
                .then(response => response.json())
                .then(data => {
                    const card = document.getElementById('conn-card');
                    const text = document.getElementById('conn-text');
                    
                    if (data.status === 'online') {
                        card.classList.remove('offline');
                        card.classList.add('online');
                        text.innerText = 'Connected';
                    } else {
                        card.classList.remove('online');
                        card.classList.add('offline');
                        text.innerText = 'Disconnected';
                    }
                })
                .catch(err => {
                    const card = document.getElementById('conn-card');
                    const text = document.getElementById('conn-text');
                    card.classList.remove('online');
                    card.classList.add('offline');
                    text.innerText = 'Error';
                });
        }

        setInterval(checkServerConnection, 5000);
        checkServerConnection();
        // ----------------------------------------------

        // --- Sidebar Logic ---
        function toggleSidebar(){
            if(window.innerWidth >= 992){
                sidebar.style.left = (sidebar.style.left === '0px' || sidebar.style.left === '') ? '-260px' : '0px';
                document.querySelector('.main-content').style.marginLeft = (document.querySelector('.main-content').style.marginLeft === '260px' || document.querySelector('.main-content').style.marginLeft === '') ? '0px' : '260px';
                document.querySelector('.header').style.paddingLeft = (document.querySelector('.header').style.paddingLeft === '280px' || document.querySelector('.header').style.paddingLeft === '') ? '20px' : '280px';
            } else {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            }
        }

        window.addEventListener('resize', () => {
            if(window.innerWidth >= 992){
                sidebar.style.left = '';
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.querySelector('.main-content').style.marginLeft = '';
                document.querySelector('.header').style.paddingLeft = '';
            }
        });

        document.querySelectorAll('.dropdown-btn').forEach(btn => {
            btn.addEventListener('click', function(){
                document.querySelectorAll('.dropdown-btn').forEach(b => {
                    if(b !== this){
                        b.classList.remove('active');
                        b.nextElementSibling.classList.remove('show')
                    }
                });
                this.classList.toggle('active');
                this.nextElementSibling.classList.toggle('show');
            });
        });

        function loadContent(url){
            const container = document.getElementById('iframeContainer');
            const loader = document.getElementById('loader');
            loader.style.display = 'flex';
            
            document.querySelectorAll('a').forEach(el => el.classList.remove('active-link'));
            if(event && event.target){
                const link = event.target.closest('a');
                if(link) link.classList.add('active-link');
            }
            
            container.innerHTML = `<iframe src="${url}" allowfullscreen onload="document.getElementById('loader').style.display='none'"></iframe>`;
            
            if(window.innerWidth < 992){
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        }

        function refreshFrame(){
            const iframe = document.querySelector('iframe');
            iframe ? iframe.contentWindow.location.reload() : location.reload();
        }
        
        function logoutApp(){
            if(confirm("Logout?")) window.location.href = 'auth/logout.php';
        }

        loadContent('/webui/monitor/Overview.php');
    </script>
</body>
</html>