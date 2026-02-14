<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

// --- PHP API BACKEND ---
if (isset($_REQUEST['api'])) {
    header('Content-Type: application/json');
    $act = $_REQUEST['api'];

    function get_val($f) { return trim(@file_get_contents($f)) ?: '-'; }
    function fmt($b) {
        if ($b <= 0) return '0 B';
        $u = ['B','KB','MB','GB','TB'];
        $i = floor(log($b, 1024));
        return round($b/pow(1024, $i), 2).' '.$u[$i];
    }

    if ($act === 'stats') {
        $data = [];
        
        // Membaca semua folder di /sys/class/net
        $interfaces = array_diff(scandir('/sys/class/net'), ['.', '..', 'lo']); 

        foreach($interfaces as $real) {
            // 1. Ambil IP terlebih dahulu
            $ipRaw = shell_exec("ip -4 addr show $real | awk '/inet/ {print $2}' | cut -d/ -f1");
            $ip = trim($ipRaw);

            // [FILTER BARU] Jika IP Kosong, lewati (jangan dimasukkan ke data)
            if (empty($ip)) {
                continue; 
            }

            $operstate = get_val("/sys/class/net/$real/operstate");
            $status = ($operstate == 'up' || $operstate == 'unknown') ? 'ONLINE' : 'OFFLINE';
            
            $data[$real] = [
                'exists' => true,
                'real' => $real,
                'status' => $status,
                'mac' => get_val("/sys/class/net/$real/address"),
                'ip' => $ip, // Sudah diambil diatas
                'rx' => fmt(get_val("/sys/class/net/$real/statistics/rx_bytes")),
                'tx' => fmt(get_val("/sys/class/net/$real/statistics/tx_bytes"))
            ];
        }
        echo json_encode($data);
    }

    elseif ($act === 'exec') {
        $cmd = $_POST['cmd'] ?? '';
        if ($cmd) {
            shell_exec("su -c '$cmd' 2>&1");
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Active Interface Manager</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-s: #fff3e0; --suc: #2dce89; --suc-s: #e6fffa;
            --dang: #f5365c; --dang-s: #fff5f5; --rad: 16px; --shd: 0 2px 8px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-s: rgba(255,152,0,0.15); --suc: #69db7c; --suc-s: rgba(43,138,62,0.2);
                --dang: #ff8787; --dang-s: rgba(201,42,42,0.2); --shd: 0 4px 6px rgba(0,0,0,0.3);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .head { margin-bottom: 25px; text-align: center; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 5px; color: var(--pri); }
        p { color: var(--sub); font-size: 0.9rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: var(--card); border-radius: var(--rad); padding: 20px; box-shadow: var(--shd); border: 1px solid var(--border); display: flex; flex-direction: column; position: relative; overflow: hidden; animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .c-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .c-ti { display: flex; align-items: center; gap: 12px; }
        .ico { width: 45px; height: 45px; border-radius: 12px; background: var(--pri-s); color: var(--pri); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ico svg { width: 24px; height: 24px; fill: currentColor; }
        .meta h3 { font-size: 1rem; font-weight: 700; margin: 0; }
        .meta span { font-size: 0.8rem; color: var(--sub); font-family: monospace; }
        .bdg { font-size: 0.75rem; padding: 4px 10px; border-radius: 20px; font-weight: 600; text-transform: uppercase; }
        .act { background: var(--suc-s); color: var(--suc); } .inact { background: var(--dang-s); color: var(--dang); }
        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; background: var(--bg); padding: 15px; border-radius: 12px; }
        .row { display: flex; flex-direction: column; }
        .lbl { font-size: 0.7rem; color: var(--sub); text-transform: uppercase; font-weight: 600; }
        .val { font-size: 0.9rem; font-weight: 600; font-family: monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .acts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: auto; }
        .btn { border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .btn:active { transform: scale(0.98); opacity: 0.8; }
        .bs { background: var(--suc); color: white; } .bx { background: var(--dang); color: white; }
        .bo { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .bo:hover { border-color: var(--pri); color: var(--pri); }
        .x-acts { margin-top: 10px; display: flex; gap: 10px; } .bf { flex: 1; }
        .ovl { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100; display: none; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .mdl { background: var(--card); width: 90%; max-width: 400px; border-radius: var(--rad); padding: 20px; box-shadow: var(--shd); border: 1px solid var(--border); animation: p 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes p { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .mh { font-weight: 700; font-size: 1.1rem; margin-bottom: 15px; text-align: center; }
        .mb label { display: block; font-size: 0.85rem; color: var(--sub); margin: 10px 0 4px; font-weight: 600; }
        .mb textarea { width: 100%; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 8px; padding: 10px; font-family: monospace; font-size: 0.85rem; resize: vertical; min-height: 60px; }
        .mf { display: flex; gap: 10px; margin-top: 20px; }
        .skel { color: transparent; background: linear-gradient(90deg, var(--border) 25%, var(--bg) 50%, var(--border) 75%); background-size: 200% 100%; animation: ld 1.5s infinite; border-radius: 4px; display: inline-block; min-width: 50px; }
        @keyframes ld { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body>

<div class="container">
    <div class="grid" id="mainGrid"></div>
</div>

<div id="em" class="ovl">
    <div class="mdl">
        <div class="mh">Edit <span id="mn" style="color:var(--pri)"></span></div>
        <div class="mb">
            <label>Enable Command</label><textarea id="ce" placeholder="Command to Start"></textarea>
            <label>Disable Command</label><textarea id="cd" placeholder="Command to Stop"></textarea>
            <div style="font-size:0.75rem; color:var(--sub); margin-top:5px">Tip: Use <b>{iface}</b> to auto-insert interface name.</div>
        </div>
        <div class="mf"><button class="btn bo bf" onclick="cl()">Cancel</button><button class="btn bs bf" onclick="sv()">Save</button></div>
    </div>
</div>

<script>
let ci = '';

// --- CONFIGURATION ---
const DEFAULTS = {
    'wlan': { 
        title: 'Wi-Fi Interface', 
        icon: '<path d="M12 11c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 2c0-3.31-2.69-6-6-6s-6 2.69-6 6c0 2.22 1.21 4.15 3 5.19l1-1.74c-1.19-.7-2-1.97-2-3.45 0-2.21 1.79-4 4-4s4 1.79 4 4c0 1.48-.81 2.75-2 3.45l1 1.74c1.79-1.04 3-2.97 3-5.19zM12 3C6.48 3 2 7.48 2 13c0 3.7 2.01 6.92 4.99 8.65l1-1.73C5.61 18.53 4 15.96 4 13c0-4.42 3.58-8 8-8s8 3.58 8 8c0 2.96-1.61 5.53-4 6.92l1 1.73c2.99-1.73 5-4.95 5-8.65 0-5.52-4.48-10-10-10z"/>',
        en: 'svc wifi enable', 
        dis: 'svc wifi disable' 
    },
    'ap': { 
        title: 'Hotspot/AP', 
        icon: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 16c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6z"/><circle cx="12" cy="12" r="2"/>',
        en: 'cmd connectivity start-tethering wifi', 
        dis: 'cmd connectivity stop-tethering wifi' 
    },
    'rndis': { 
        title: 'USB Tethering', 
        icon: '<path d="M15 7v4h1v2h-3V5h2l-3-4-3 4h2v8H8v-2.07c.7-.37 1.2-1.08 1.2-1.93 0-1.21-.99-2.2-2.2-2.2-1.21 0-2.2.99-2.2 2.2 0 .85.5 1.56 1.2 1.93V13c0 1.11.89 2 2 2h3v3.05c-.71.37-1.21 1.1-1.21 1.95 0 1.22.99 2.2 2.2 2.2 1.21 0 2.2-.98 2.2-2.2 0-.85-.5-1.58-1.21-1.95V13h3c1.11 0 2-.89 2-2v-2h1V7h-1z"/>',
        en: 'svc usb setFunctions rndis', 
        dis: 'svc usb setFunctions none' 
    },
    'ncm': { 
        title: 'USB NCM', 
        icon: '<path d="M20 13H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-6c0-.55-.45-1-1-1zM7 19c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM20 3H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1zM7 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>',
        en: 'svc usb setFunctions ncm', 
        dis: 'svc usb setFunctions none' 
    },
    'eth': { 
        title: 'Ethernet LAN', 
        icon: '<path d="M7.77 6.76L6.23 5.48.82 12l5.41 6.52 1.54-1.28L3.42 12l4.35-5.24zM7 13h2v-2H7v2zm10-2h-2v2h2v-2zm-6 2h2v-2h-2v2zm6.77-7.52l-1.54 1.28L20.58 12l-4.35 5.24 1.54 1.28L23.18 12l-5.41-6.52z"/>',
        en: 'ifconfig {iface} up', 
        dis: 'ifconfig {iface} down' 
    },
    'tun': { 
        title: 'VPN / Tunnel', 
        icon: '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>',
        en: 'ip link set {iface} up', 
        dis: 'ip link set {iface} down' 
    },
    'rmnet': { 
        title: 'Mobile Data', 
        icon: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>',
        en: 'svc data enable', 
        dis: 'svc data disable' 
    },
    'default': {
        title: 'Interface',
        icon: '<path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>',
        en: 'ip link set {iface} up',
        dis: 'ip link set {iface} down'
    }
};

function getConfig(name) {
    if (name.includes('wlan')) return DEFAULTS['wlan'];
    if (name.includes('ap')) return DEFAULTS['ap'];
    if (name.includes('rndis')) return DEFAULTS['rndis'];
    if (name.includes('ncm')) return DEFAULTS['ncm'];
    if (name.includes('eth')) return DEFAULTS['eth'];
    if (name.includes('tun')) return DEFAULTS['tun'];
    if (name.includes('rmnet') || name.includes('ccmni')) return DEFAULTS['rmnet'];
    return DEFAULTS['default'];
}

function up() {
    fetch('?api=stats').then(r=>r.json()).then(d => {
        const grid = document.getElementById('mainGrid');
        
        // Hapus kartu yang sudah tidak ada di list (misal karena IP hilang)
        const currentIds = Object.keys(d);
        const existingCards = document.querySelectorAll('.card');
        existingCards.forEach(card => {
            const id = card.id.replace('c-', '');
            if (!currentIds.includes(id)) {
                card.remove(); 
            }
        });

        for (const k in d) {
            const o = d[k];
            const elId = `c-${k}`;
            let el = document.getElementById(elId);

            if (!el) {
                const conf = getConfig(k);
                const displayTitle = (conf === DEFAULTS['default']) ? k.toUpperCase() : conf.title;
                
                const html = `
                <div class="card" id="${elId}">
                    <div class="c-head">
                        <div class="c-ti">
                            <div class="ico"><svg viewBox="0 0 24 24">${conf.icon}</svg></div>
                            <div class="meta"><h3>${displayTitle}</h3><span id="n-${k}">${o.real}</span></div>
                        </div>
                        <span class="bdg inact" id="s-${k}">...</span>
                    </div>
                    <div class="stats">
                        <div class="row"><span class="lbl">IPV4</span><span class="val" id="i-${k}"><span class="skel">...</span></span></div>
                        <div class="row"><span class="lbl">MAC</span><span class="val" id="m-${k}"><span class="skel">...</span></span></div>
                        <div class="row"><span class="lbl">TX</span><span class="val" id="t-${k}"><span class="skel">...</span></span></div>
                        <div class="row"><span class="lbl">RX</span><span class="val" id="r-${k}"><span class="skel">...</span></span></div>
                    </div>
                    <div class="acts">
                        <button class="btn bs" onclick="xc('${k}','en')">START</button>
                        <button class="btn bx" onclick="xc('${k}','dis')">STOP</button>
                    </div>
                    <div class="x-acts">
                        <button class="btn bo bf" onclick="md('${k}')">Edit</button>
                        <button class="btn bo bf" onclick="xc('${k}','rst')">Reset</button>
                    </div>
                </div>`;
                grid.insertAdjacentHTML('beforeend', html);
            }

            if (document.getElementById(`n-${k}`)) {
                const b = document.getElementById(`s-${k}`);
                b.innerText = o.status;
                b.className = o.status === 'ONLINE' ? 'bdg act' : 'bdg inact';
                
                document.getElementById(`i-${k}`).innerText = o.ip;
                document.getElementById(`m-${k}`).innerText = o.mac;
                document.getElementById(`t-${k}`).innerText = o.tx;
                document.getElementById(`r-${k}`).innerText = o.rx;
            }
        }
    });
}

function xc(k, a) {
    let c = '';
    const defConf = getConfig(k);

    if (a === 'rst') {
        if(!confirm('Reset commands to default?')) return;
        localStorage.removeItem(`${k}-en`);
        localStorage.removeItem(`${k}-dis`);
        return alert('Commands reset!');
    }

    if (a === 'en') c = localStorage.getItem(`${k}-en`) || defConf.en;
    if (a === 'dis') c = localStorage.getItem(`${k}-dis`) || defConf.dis;

    c = c.replace(/{iface}/g, k);

    const fd = new FormData();
    fd.append('cmd', c);
    fetch('?api=exec', { method: 'POST', body: fd })
        .then(r=>r.json()).then(()=>{ setTimeout(up, 1000); });
}

function md(k) {
    ci = k;
    const defConf = getConfig(k);
    
    document.getElementById('mn').innerText = k.toUpperCase();
    document.getElementById('ce').value = localStorage.getItem(`${k}-en`) || defConf.en;
    document.getElementById('cd').value = localStorage.getItem(`${k}-dis`) || defConf.dis;
    document.getElementById('em').style.display = 'flex';
}
function cl() { document.getElementById('em').style.display = 'none'; }
function sv() {
    localStorage.setItem(`${ci}-en`, document.getElementById('ce').value);
    localStorage.setItem(`${ci}-dis`, document.getElementById('cd').value);
    cl();
}

setInterval(up, 2000);
up();
</script>

</body>
</html>