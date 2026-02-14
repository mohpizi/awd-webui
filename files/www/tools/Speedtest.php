<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Speed Test</title>
    <style>
        /* --- CSS VARIABLES & THEME --- */
        :root {
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --primary: #3b82f6;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 16px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-body: #111827;
                --bg-card: #1f2937;
                --text-main: #f9fafb;
                --text-sub: #9ca3af;
                --primary: #60a5fa;
                --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            }
        }

        /* --- RESET & BASE --- */
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            transition: background-color 0.3s, color 0.3s;
        }

        .container {
            width: 100%;
            max-width: 800px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* --- CARD --- */
        .card {
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 0; /* Padding 0 agar iframe memenuhi card */
            overflow: hidden;
            transition: background-color 0.3s;
        }

        /* --- HEADER --- */
        .header {
            text-align: center;
            padding: 10px 0;
        }
        
        .header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-main);
        }
        
        .header p {
            font-size: 0.95rem;
            color: var(--text-sub);
        }

        /* --- SPEEDTEST WRAPPER --- */
        .speedtest-wrapper {
            width: 100%;
            position: relative;
            /* Aspect ratio menjaga proporsi agar pas di HP & PC */
            aspect-ratio: 16/9; 
            min-height: 450px; /* Tinggi minimal agar gauge terlihat jelas */
            background: var(--bg-card);
        }
        
        .speedtest-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        /* --- FOOTER --- */
        footer {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-sub);
            margin-top: 10px;
        }
        
        footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.2s;
        }
        
        footer a:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h1>Speed Test</h1>
            <p>Cek kecepatan koneksi internet Anda</p>
        </div>

        <div class="card">
            <div class="speedtest-wrapper">
                <iframe src="//openspeedtest.com/speedtest" 
                        allowfullscreen 
                        scrolling="no"></iframe>
            </div>
        </div>

        <footer>
            <div style="margin-bottom: 8px;">Powered by OpenSpeedTest</div>
            <a href="https://t.me/On_Progressss" target="_blank">
                Telegram @Arewedaks
            </a>
        </footer>
    </div>

</body>
</html>