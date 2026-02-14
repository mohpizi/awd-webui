<?php
$p = $_SERVER['HTTP_HOST'];
$x = explode(':', $p);
$host = $x[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#fb8c00">
    <title>Wireless Manager</title>
    <style>
        :root {
            --bg-body: #f8f9fa;
            --bg-nav: #ffffff;
            --text-main: #2d3748;
            --text-muted: #718096;
            --primary: #fb8c00;
            --primary-bg: #fff3e0;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-body: #121212;
                --bg-nav: #1e1e1e;
                --text-main: #e0e0e0;
                --text-muted: #a0a0a0;
                --primary: #ff9800;
                --primary-bg: #3e2723;
                --border: #2d2d2d;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .nav-bar {
            background-color: var(--bg-nav);
            padding: 12px 16px;
            box-shadow: var(--shadow);
            z-index: 10;
            display: flex;
            justify-content: center;
            border-bottom: 1px solid var(--border);
        }
        .tabs {
            background-color: var(--bg-body);
            padding: 4px;
            border-radius: 12px;
            display: flex;
            gap: 5px;
            width: 100%;
            max-width: 360px;
            border: 1px solid var(--border);
        }
        .tab {
            flex: 1;
            padding: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            border-radius: 8px;
            transition: 0.2s;
            user-select: none;
        }
        .tab.active {
            background-color: var(--bg-nav);
            color: var(--primary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .container {
            flex-grow: 1;
            position: relative;
            width: 100%;
            height: 100%;
        }
        .view {
            display: none;
            width: 100%;
            height: 100%;
        }
        .view.active { display: block; }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background-color: var(--bg-body);
        }
    </style>
</head>
<body>

<div class="nav-bar">
    <div class="tabs">
        <div class="tab active" onclick="sw('hotspot')" id="b-hotspot">Hotspot Monitor</div>
        <div class="tab" onclick="sw('limiter')" id="b-limiter">BW Limiter</div>
    </div>
</div>

<div class="container">
    <div id="v-hotspot" class="view active">
        <iframe id="f-hotspot"></iframe>
    </div>
    <div id="v-limiter" class="view">
        <iframe id="f-limiter"></iframe>
    </div>
</div>

<script>
    const uHost = '/tools/wireless/hotspot.php';
    const uLimit = '/tools/wireless/limiter';

    function sw(t) {
        document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(e => e.classList.remove('active'));
        
        document.getElementById('v-' + t).classList.add('active');
        document.getElementById('b-' + t).classList.add('active');

        const f = document.getElementById('f-' + t);
        if (!f.getAttribute('src')) {
            f.src = (t === 'hotspot') ? uHost : uLimit;
        }
    }
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('f-hotspot').src = uHost;
    });
</script>

</body>
</html>