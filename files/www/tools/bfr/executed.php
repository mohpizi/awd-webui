<?php
$commands = [
    'start'   => "/data/adb/box/scripts/box.service start && /data/adb/box/scripts/box.iptables enable",
    'stop'    => "/data/adb/box/scripts/box.iptables disable && /data/adb/box/scripts/box.service stop",
    'restart' => "/data/adb/box/scripts/box.service restart"
];
$clashlogs = "/data/adb/box/run/runs.log";
if (isset($_GET['action']) && isset($_GET['stream'])) {
    $act = $_GET['action'];
    if (array_key_exists($act, $commands)) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        if(function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) ob_end_flush();
        ob_implicit_flush(1);

        $proc = popen($commands[$act] . " 2>&1", 'r');
        echo "data: ⏳ System: Menjalankan perintah " . strtoupper($act) . "...\n\n";
        flush();

        if ($proc) {
            while (!feof($proc)) {
                $line = fgets($proc);
                if ($line) {
                    echo "data: " . trim($line) . "\n\n";
                    flush();
                }
            }
            pclose($proc);
        }
        echo "data: ✅ Proses Selesai.\n\n";
        echo "event: close\ndata: end\n\n";
        flush();
    }
    exit;
}
$host = explode(':', $_SERVER['HTTP_HOST'])[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box For Root</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --log: #f1f3f5; --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            --btn-start: #ffa726; --btn-restart: #fb8c00; --btn-stop: #e65100;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #fb8c00; --log: #1a1a1a;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
                --btn-start: #ffb74d; --btn-restart: #f57c00; --btn-stop: #bf360c;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); height: 100vh; display: flex; flex-direction: column; padding: 15px; gap: 15px; overflow: hidden; }
        
        .dash-btn { display: block; width: 100%; padding: 12px; text-align: center; background: transparent; border: 2px solid var(--pri); color: var(--pri); font-weight: 700; border-radius: 12px; text-decoration: none; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; flex-shrink: 0; }
        .dash-btn:active { background: var(--pri); color: #fff; transform: scale(0.98); }

        .card { background: var(--card); border-radius: 12px; padding: 15px; box-shadow: var(--shadow); border: 1px solid var(--border); flex-shrink: 0; }
        
        .btn-grp { display: flex; width: 100%; border-radius: 10px; overflow: hidden; box-shadow: var(--shadow); }
        .btn { flex: 1; padding: 14px; border: none; color: white; font-weight: 800; cursor: pointer; font-size: 13px; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn:active { opacity: 0.8; transform: scale(0.98); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; filter: grayscale(50%); }
        
        .btn-s { background: var(--btn-start); } 
        .btn-r { background: var(--btn-restart); } 
        .btn-x { background: var(--btn-stop); }

        .log-wrap { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 0; min-height: 0; }
        .log-head { padding: 12px 15px; font-weight: 700; font-size: 14px; text-transform: uppercase; border-bottom: 1px solid var(--border); color: var(--sub); display: flex; align-items: center; background: var(--card); flex-shrink: 0; }
        
        .log-box { flex: 1; overflow-y: auto; background: var(--log); padding: 10px; font-family: monospace; font-size: 11px; line-height: 1.6; color: var(--text); white-space: pre-wrap; word-break: break-all; scroll-behavior: smooth; }
        
        .log-line { border-bottom: 1px solid var(--border); padding: 3px 0; } 
        .log-line:last-child { border: none; }
        .log-success { color: #00c853; font-weight: bold; border-top: 1px dashed var(--border); margin-top: 5px; padding-top: 5px; }

        .indicator { width: 8px; height: 8px; border-radius: 50%; background: #ccc; display: inline-block; margin-right: 8px; transition: background 0.3s; }
        .indicator.active { background: #00e676; box-shadow: 0 0 8px #00e676; animation: pulse 1s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
    </style>
</head>
<body>

    <a href="http://<?php echo $host; ?>:9090/ui/?hostname=<?php echo $host; ?>&port=9090" target="_blank" class="dash-btn">Open Dashboard</a>

    <div class="card" style="padding: 0; border: none; background: transparent; box-shadow: none;">
        <div class="btn-grp">
            <button onclick="exec('start')" class="btn btn-s">Start</button>
            <button onclick="exec('restart')" class="btn btn-r">Restart</button>
            <button onclick="exec('stop')" class="btn btn-x">Stop</button>
        </div>
    </div>

    <div class="card log-wrap">
        <div class="log-head">
            <div><span id="st" class="indicator"></span>SYSTEM LOGS</div>
        </div>
        <div class="log-box" id="logs"><?php
            if (file_exists($clashlogs)) {
                $lines = file($clashlogs, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines !== false) {
                    $lines = array_slice($lines, -50); 
                    foreach ($lines as $l) {
                        $clean = preg_replace('/\x1b\[[0-9;]*m/', '', $l);
                        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $clean);
                        
                        if (trim($clean) !== '') {
                            echo '<div class="log-line">' . htmlspecialchars($clean) . '</div>';
                        }
                    }
                }
            } else {
                echo '<div class="log-line" style="text-align:center; color:var(--sub)">Ready.</div>';
            }
        ?></div>
    </div>

    <script>
        const box = document.getElementById('logs');
        const st = document.getElementById('st');
        let es = null;
        box.scrollTop = box.scrollHeight;
        function stripAnsi(str) {
            return str.replace(/[\u001b\u009b][[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-ORZcf-nqry=><]/g, '');
        }

        function exec(act) {
            if(es) es.close();
            
            st.classList.add('active');
            box.innerHTML = ''; 
            
            const btns = document.querySelectorAll('.btn');
            btns.forEach(b => b.disabled = true);

            es = new EventSource('?action=' + act + '&stream=true');

            es.onmessage = function(e) {
                if(e.data === 'end') {
                    es.close();
                    st.classList.remove('active');
                    btns.forEach(b => b.disabled = false);
                    return;
                }
                
                const cleanText = stripAnsi(e.data);
                const div = document.createElement('div');
                
                if(cleanText.includes('Proses Selesai')) {
                    div.className = 'log-line log-success';
                } else {
                    div.className = 'log-line';
                }
                
                div.innerText = cleanText;
                box.appendChild(div);
                box.scrollTop = box.scrollHeight;
            };

            es.onerror = function() {
                const div = document.createElement('div');
                div.className = 'log-line';
                div.style.color = '#ff5252';
                div.innerText = "Error: Koneksi terputus.";
                box.appendChild(div);
                
                es.close();
                st.classList.remove('active');
                btns.forEach(b => b.disabled = false);
                box.scrollTop = box.scrollHeight;
            };
        }
    </script>

</body>
</html>