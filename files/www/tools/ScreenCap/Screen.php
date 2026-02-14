<?php
$defaultIp   = '192.168.8.1'; 
$defaultPort = '8181';

function exec_bg($cmd) { return shell_exec("su -c '$cmd > /dev/null 2>&1 &'"); }
function exec_sync($cmd) { return shell_exec("su -c '$cmd'"); }

if (isset($_GET['action']) && $_GET['action'] == 'get_size') {
    header('Content-Type: application/json');
    $output = shell_exec("su -c 'wm size'");
    if (preg_match('/(\d+)x(\d+)/', $output, $matches)) {
        echo json_encode(['width' => (int)$matches[1], 'height' => (int)$matches[2]]);
    } else { echo json_encode(['width' => 1080, 'height' => 2400]); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'start_stream') {
        exec_sync("pkill -x minicap"); 
        exec_sync("pkill -f minicap_server.py");
        exec_sync("pkill -f stream.py");
        $cmd = "/data/data/com.termux/files/usr/bin/python /data/adb/php8/scripts/minicap_server.py";
        exec_bg($cmd);
        echo "Started";
    }
    elseif ($action === 'stop_stream') {
        exec_sync("pkill -x minicap"); 
        exec_sync("pkill -f minicap_server.py");
        echo "Stopped";
    }
    elseif ($action === 'tap') { exec_bg("input tap {$_POST['x']} {$_POST['y']}"); }
    elseif ($action === 'swipe') { exec_bg("input swipe {$_POST['x1']} {$_POST['y1']} {$_POST['x2']} {$_POST['y2']} {$_POST['duration']}"); }
    elseif ($action === 'key') { exec_bg("input keyevent {$_POST['code']}"); }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Remote Control</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-h: #ef6c00; --dang: #f5365c; --tgl-bg: #cbd5e1; --tgl-act: #fb8c00;
            --shd: 0 4px 6px -1px rgba(0,0,0,0.05); --rad: 16px; --inp: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-h: #ffa726; --dang: #fc8181; --tgl-bg: #4b5563; --tgl-act: #ff9800;
                --shd: 0 4px 6px -1px rgba(0,0,0,0.4); --inp: #2c2c2c;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding: 20px; overscroll-behavior: none; }
        .con { width: 100%; max-width: 600px; display: flex; flex-direction: column; gap: 20px; align-items: center; }
        .header { text-align: center; width: 100%; }
        h2 { font-size: 1.4rem; font-weight: 700; color: var(--pri); margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: 0.5px; }
        p.sub { font-size: 0.85rem; color: var(--sub); }
        .card { background: var(--card); border-radius: var(--rad); padding: 20px; width: 100%; box-shadow: var(--shd); border: 1px solid var(--border); }

        .dev-wrap { position: relative; display: inline-block; border: 8px solid var(--card); border-radius: 20px; overflow: hidden; box-shadow: var(--shd); background: #000; line-height: 0; touch-action: none; min-height: 200px; min-width: 100px; display: none; user-select: none; }
        .dev-wrap.active { display: inline-block; }
        #img { width: 100%; max-height: 75vh; display: block; pointer-events: none; }
        #layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; cursor: pointer; }

        .controls { display: flex; gap: 15px; background: var(--card); padding: 10px 20px; border-radius: 50px; border: 1px solid var(--border); box-shadow: var(--shd); opacity: 0.5; pointer-events: none; transition: opacity 0.3s; }
        .controls.active { opacity: 1; pointer-events: auto; }
        .btn-nav { background: none; border: none; color: var(--text); cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: 0.2s; }
        .btn-nav:hover { background: var(--tgl-bg); }
        .btn-nav:active { color: var(--pri); transform: scale(0.9); }
        .btn-nav svg { width: 22px; height: 22px; fill: currentColor; }

        .inp-grp { display: flex; gap: 15px; align-items: center; margin-bottom: 15px; }
        .form-i { flex: 1; }
        label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--sub); margin-bottom: 6px; }
        input { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--inp); color: var(--text); font-size: 0.95rem; }
        input:focus { border-color: var(--pri); box-shadow: 0 0 0 3px rgba(251, 140, 0, 0.2); }

        .opt-row { display: flex; align-items: center; justify-content: space-between; background: var(--inp); padding: 10px 15px; border-radius: 12px; border: 1px solid var(--border); margin-top: 15px; }
        .opt-lbl { font-size: 0.85rem; color: var(--text); font-weight: 600; }
        
        .sw { position: relative; display: inline-block; width: 48px; height: 26px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: var(--tgl-bg); transition: .4s; border-radius: 34px; }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .sl { background: var(--tgl-act); }
        input:checked + .sl:before { transform: translateX(22px); }

        .btn-main { width: 100%; padding: 14px; border: none; border-radius: 10px; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: 0.2s; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-c { background: var(--pri); } .btn-c:hover { background: var(--pri-h); }
        .btn-d { background: var(--dang); } .btn-d:hover { background: var(--dang-h); }
        .btn-main:active { transform: scale(0.98); }
        .btn-main:disabled { opacity: 0.7; cursor: wait; }
    </style>
</head>
<body>

<div class="con">
    
    <div class="header">
        <h2>Mini Mirror</h2>
        <p class="sub">script by arewedaks</p>
    </div>

    <div class="dev-wrap" id="sb">
        <img id="img" src="" alt="Live Stream">
        <div id="layer"></div>
    </div>

    <div class="controls" id="nb">
        <button class="btn-nav" onclick="sendKey(4)" title="Back">
            <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        </button>
        <button class="btn-nav" onclick="sendKey(3)" title="Home">
            <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
        </button>
        <button class="btn-nav" onclick="sendKey(187)" title="Recent Apps">
            <svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
        </button>
        <button class="btn-nav" onclick="sendKey(26)" style="color:var(--dang)" title="Power Button">
            <svg viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg>
        </button>
    </div>

    <div class="card">
        <div class="inp-grp">
            <div class="form-i">
                <label>IP Address</label>
                <input type="text" id="ip" value="<?php echo $defaultIp; ?>">
            </div>
            <div class="form-i">
                <label>Port (Fixed)</label>
                <input type="text" value="<?php echo $defaultPort; ?>" disabled>
            </div>
        </div>

        <div class="opt-row">
            <span class="opt-lbl">Compatibility Mode</span>
            <label class="sw">
                <input type="checkbox" id="fjm">
                <span class="sl"></span>
            </label>
        </div>
        
        <button id="mb" class="btn-main btn-c" data-connected="false" onclick="tgc()">
            <svg viewBox="0 0 24 24" style="width:20px;height:20px"><path d="M8 5v14l11-7z"/></svg>
            Connect
        </button>
    </div>

</div>

<script>
    const layer = document.getElementById('layer'), img = document.getElementById('img');
    const sb = document.getElementById('sb'), nb = document.getElementById('nb'), mb = document.getElementById('mb');
    const fjm = document.getElementById('fjm');
    const PORT = '8181';
    let rw = 0, rh = 0, rI = null, isR = false;
    let sX, sY, sT, isD = false, lTT = 0;
    
    // --- FITUR BARU: AUTO DETECT IP ---
    // Mengambil hostname (IP) dari address bar browser dan memasukkannya ke kolom IP
    if (window.location.hostname) {
        document.getElementById('ip').value = window.location.hostname;
    } else if(localStorage.getItem('rem_ip')) {
        document.getElementById('ip').value = localStorage.getItem('rem_ip');
    }
    // ----------------------------------
    
    fetch('?action=get_size').then(r => r.json()).then(d => { rw = d.width; rh = d.height; });

    function tgc() {
        if (!isR) {
            mb.disabled = true; mb.innerHTML = 'Starting...';
            const ip = document.getElementById('ip').value;
            localStorage.setItem('rem_ip', ip);
            const fd = new FormData(); fd.append('action', 'start_stream');
            fetch('', { method: 'POST', body: fd }).then(() => { setTimeout(() => start(ip, PORT), 1500); });
        } else {
            stop();
            const fd = new FormData(); fd.append('action', 'stop_stream');
            fetch('', { method: 'POST', body: fd });
        }
    }

    function start(ip, port) {
        isR = true; sb.classList.add('active'); nb.classList.add('active');
        mb.innerHTML = '<svg viewBox="0 0 24 24" style="width:20px;height:20px"><path d="M6 6h12v12H6z"/></svg> Disconnect';
        mb.className = "btn-main btn-d"; mb.disabled = false;

        const ujsm = fjm.checked;
        const bu = `http://${ip}:${port}`;

        if (ujsm) {
            if(rI) clearInterval(rI);
            rI = setInterval(() => { img.src = `${bu}/snapshot?t=${Date.now()}`; }, 100);
        } else {
            img.src = `${bu}/stream.mjpeg?t=${Date.now()}`;
            img.onerror = () => {
                img.onerror = null;
                fjm.checked = true;
                start(ip, port);
            };
        }
    }

    function stop() {
        isR = false; if(rI) clearInterval(rI); img.src = "";
        sb.classList.remove('active'); nb.classList.remove('active');
        mb.innerHTML = '<svg viewBox="0 0 24 24" style="width:20px;height:20px"><path d="M8 5v14l11-7z"/></svg> Connect';
        mb.className = "btn-main btn-c"; mb.disabled = false;
    }

    function sd(d) {
        const fd = new FormData();
        for(let k in d) fd.append(k, d[k]);
        fetch('', {method:'POST', body:fd});
    }
    function sendKey(code) { sd({action:'key', code:code}); }

    function getC(e) {
        const r = layer.getBoundingClientRect();
        let cx, cy;
        if (e.changedTouches) { cx = e.changedTouches[0].clientX; cy = e.changedTouches[0].clientY; }
        else { cx = e.clientX; cy = e.clientY; }
        return { x: Math.round((cx - r.left) * (rw / r.width)), y: Math.round((cy - r.top) * (rh / r.height)) };
    }

    function hS(e) {
        if (e.type === 'mousedown' && (Date.now() - lTT < 500)) return;
        if (e.type === 'touchstart') lTT = Date.now();
        if (e.type === 'mousedown') e.preventDefault(); 
        isD = true; sT = Date.now();
        const p = getC(e);
        sX = p.x; sY = p.y;
    }

    function hE(e) {
        if (!isD) return;
        if (e.cancelable && (e.type === 'mouseup' || e.type === 'touchend')) e.preventDefault();
        isD = false;
        const p = getC(e);
        const dX = Math.abs(p.x - sX);
        const dY = Math.abs(p.y - sY);
        const dur = Date.now() - sT;

        if (dX > 15 || dY > 15) {
            sd({action: 'swipe', x1:sX, y1:sY, x2:p.x, y2:p.y, duration:Math.max(100, dur)});
        } else {
            sd({action: 'tap', x:p.x, y:p.y});
        }
    }

    layer.addEventListener('mousedown', hS);
    layer.addEventListener('touchstart', hS, {passive: false});
    layer.addEventListener('mouseup', hE);
    layer.addEventListener('touchend', hE, {passive: false});
    layer.addEventListener('mouseleave', () => isD = false);
    layer.addEventListener('touchcancel', () => isD = false);
</script>
</body>
</html>