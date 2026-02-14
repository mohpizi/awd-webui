<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
if (file_exists('/data/adb/php8/version.php')) require_once '/data/adb/php8/version.php';
else define('CURRENT_VERSION', '0.0.0');

function decrypt_link($code) {
    $binary_paths = [
        '/data/adb/php8/files/bin/crypto.so',
        '/data/adb/php8/files/bin/safe_decrypt'
    ];
    $binary_path = '';
    foreach ($binary_paths as $path) {
        if (file_exists($path)) {
            $binary_path = $path;
            break;
        }
    }
    
    if (empty($binary_path)) return false;

    $safe_code = preg_replace('/[^a-zA-Z0-9+\/=:]/', '', $code);
    
    $command = $binary_path . " -d " . escapeshellarg($safe_code) . " 2>&1";
    $result = trim(shell_exec($command));
    
    if (empty($result) || strpos($result, 'ENC::') === 0) {
        return false;
    }
    
    return $result;
}

function getTelegramData() {
    $credsBinary = '/data/adb/php8/files/bin/secure.so';
    if (!file_exists($credsBinary)) return ['err' => 'Binary secure.so tidak ditemukan'];
    
    $output = shell_exec("$credsBinary 2>&1");
    $lines = explode("\n", trim($output));
    
    if (count($lines) < 2) return ['err' => 'Gagal mengambil kredensial'];
    $token = trim($lines[0]);
    $chatId = trim($lines[1]);

    $url = "https://api.telegram.org/bot$token/getChat?chat_id=$chatId";
    $jsonRaw = shell_exec("curl -s -k \"$url\"");
    
    if (!$jsonRaw) return ['err' => 'Gagal koneksi ke Telegram'];

    $data = json_decode($jsonRaw, true);
    
    if (!isset($data['ok']) || $data['ok'] !== true) {
        return ['err' => 'Respon Telegram Error'];
    }

    $pin = $data['result']['pinned_message'] ?? null;
    if (!$pin) return ['err' => 'Tidak ada pesan yang di-PIN'];

    $text = $pin['text'] ?? $pin['caption'] ?? '';
    if (empty($text)) return ['err' => 'Konten teks kosong'];

    $version = '';
    if (preg_match('/v?(\d+\.\d+(\.\d+)?)/i', $text, $matches)) {
        $version = $matches[1];
    } else {
        return ['err' => 'Format versi tidak ditemukan'];
    }

    $downloadUrl = '';
    $rawLink = '';

    if (preg_match('/(https?:\/\/[^\s"]+|ENC::[a-zA-Z0-9+\/=]+)/', $text, $matches)) {
        $rawLink = $matches[0];

        if (strpos($rawLink, 'ENC::') === 0) {
            $decrypted = decrypt_link($rawLink);
            if ($decrypted) {
                $downloadUrl = $decrypted;
            } else {
                return ['err' => 'Gagal mendekripsi link (Cek crypto.so)'];
            }
        } else {
            $downloadUrl = $rawLink;
        }
    } else {
        return ['err' => 'Link download tidak ditemukan'];
    }

    $cleanLog = str_replace($rawLink, '', $text);
    $cleanLog = trim($cleanLog);

    return [
        'status' => 'success',
        'ver' => $version,
        'url' => $downloadUrl,
        'log' => $cleanLog
    ];
}

if (isset($_REQUEST['api'])) {
    $act = $_REQUEST['api'];

    if ($act === 'check') {
        header('Content-Type: application/json');
        $res = getTelegramData();
        
        if (isset($res['err'])) {
            echo json_encode(['status' => 'error', 'msg' => $res['err']]);
        } else {
            $newVer = $res['ver'];
            $isAvail = version_compare($newVer, CURRENT_VERSION, '>');
            
            echo json_encode([
                'status' => 'ok',
                'avail' => $isAvail,
                'ver' => $newVer,
                'url' => $res['url'],
                'log' => $res['log']
            ]);
        }
        exit;
    }

    if ($act === 'update_stream') {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        if(function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) ob_end_flush();
        ob_implicit_flush(1);

        $url = $_GET['url'] ?? '';
        $script = '/data/adb/php8/scripts/process_update.sh';
        
        $proc = popen("su -c sh \"$script\" \"$url\" 2>&1", 'r');

        if ($proc) {
            while (!feof($proc)) {
                $line = fgets($proc);
                if ($line) {
                    $cleanLine = trim($line);
                    $pct = null;
                    if (preg_match('/(\d{1,3})%/', $cleanLine, $matches)) {
                        $pct = intval($matches[1]);
                    }
                    echo "data: " . json_encode(['msg' => $cleanLine, 'pct' => $pct]) . "\n\n";
                    flush();
                }
            }
            pclose($proc);
        }
        
        echo "data: end\n\n"; 
        flush();
        sleep(1); 
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Updater</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --suc: #2dce89; --dang: #f5365c; --cons: #1a202c; --cons-tx: #e2e8f0;
            --rad: 12px; --shd: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --cons: #000; --shd: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .con { width: 100%; max-width: 500px; background: var(--card); border-radius: var(--rad); padding: 25px; box-shadow: var(--shd); border: 1px solid var(--border); }
        
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        .ti { font-size: 1.2rem; font-weight: 800; color: var(--text); text-transform: uppercase; letter-spacing: 1px; }
        .sub { font-size: 0.7rem; color: var(--sub); font-weight: 600; margin-top: 2px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--sub); transition: 0.3s; }
        .dot.on { background: var(--suc); box-shadow: 0 0 8px var(--suc); } .dot.off { background: var(--dang); } .dot.wait { background: var(--pri); animation: p 1s infinite; }

        .info { display: flex; justify-content: space-between; margin-bottom: 20px; background: var(--bg); padding: 15px; border-radius: 8px; border: 1px solid var(--border); }
        .ibl { font-size: 0.7rem; color: var(--sub); text-transform: uppercase; font-weight: 700; margin-bottom: 4px; display: block; }
        .ivl { font-size: 1.1rem; font-weight: 700; font-family: monospace; }
        .new { color: var(--pri); }

        .term { background: var(--cons); color: var(--cons-tx); padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.8rem; border: 1px solid var(--border); margin-bottom: 20px; max-height: 200px; overflow-y: auto; min-height: 100px; display: flex; flex-direction: column-reverse; }
        .log-line { margin-bottom: 2px; white-space: pre-wrap; word-break: break-all; }
        .tc-g { color: var(--suc); } .tc-r { color: var(--dang); } .tc-b { color: var(--pri); }

        .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; background: var(--pri); color: white; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; }
        .btn:active { transform: scale(0.98); }
        
        .ldr { display: flex; justify-content: center; align-items: center; gap: 10px; padding: 10px; }
        .sp { width: 24px; height: 24px; border: 3px solid var(--border); border-top: 3px solid var(--pri); border-radius: 50%; animation: s 1s linear infinite; }
        .lt { font-size: 0.9rem; font-weight: 600; color: var(--sub); }

        .pg-wrap { display: none; width: 100%; margin-top: 10px; }
        .pg-head { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--sub); margin-bottom: 5px; font-weight: 600; }
        .pg-track { width: 100%; height: 16px; background: var(--border); border-radius: 20px; overflow: hidden; position: relative; }
        .pg-bar { height: 100%; background: var(--pri); width: 0%; transition: width 0.2s linear; position: relative; border-radius: 20px; display: flex; align-items: center; justify-content: flex-end; }
        .pg-bar::after { content: ''; position: absolute; top: 0; left: 0; bottom: 0; right: 0; background-image: linear-gradient(45deg, rgba(255,255,255,.2) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.2) 50%, rgba(255,255,255,.2) 75%, transparent 75%, transparent); background-size: 1rem 1rem; animation: s-str 1s linear infinite; }
        
        @keyframes s { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes p { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }
        @keyframes s-str { from { background-position: 1rem 0; } to { background-position: 0 0; } }
    </style>
</head>
<body>

<div class="con">
    <div class="head">
        <div><div class="ti">Updater</div><div class="sub">System Firmware</div></div>
        <div class="dot wait" id="st-dot"></div>
    </div>

    <div class="info">
        <div><span class="ibl">Current</span><span class="ivl">v<?= defined('CURRENT_VERSION') ? CURRENT_VERSION : '?.?.?' ?></span></div>
        <div style="text-align:right">
            <span class="ibl">Latest</span>
            <span class="ivl" id="ver-new">...</span>
        </div>
    </div>

    <div class="term" id="log-box">
        <div class="log-line">Waiting for action...</div>
    </div>

    <div id="area-act">
        <div class="ldr" id="ldr"><div class="sp"></div><span class="lt" id="lt">Checking...</span></div>
        <button class="btn" id="btn-up" onclick="startUpdate()">Install Update</button>

        <div class="pg-wrap" id="pg-box">
            <div class="pg-head"><span id="pg-txt">Processing...</span><span id="pg-pct">0%</span></div>
            <div class="pg-track"><div class="pg-bar" id="pg-in"></div></div>
        </div>
    </div>
</div>

<script>
let upUrl = '';
let es = null;
let isFinished = false; 

function log(t, type='') {
    const box = document.getElementById('log-box');
    const d = document.createElement('div');
    d.className = 'log-line ' + (type==='suc'?'tc-g':(type==='err'?'tc-r':'tc-b'));
    d.innerText = t;
    box.prepend(d);
}

function check() {
    const fd = new FormData(); fd.append('api', 'check');
    fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        document.getElementById('ldr').style.display = 'none';
        if(d.status === 'ok') {
            document.getElementById('ver-new').innerText = 'v' + d.ver;
            if(d.avail) {
                document.getElementById('ver-new').classList.add('new');
                document.getElementById('st-dot').className = 'dot on';
                document.getElementById('btn-up').style.display = 'block';
                upUrl = d.url;
                log("UPDATE AVAILABLE:\n" + d.log, 'suc');
            } else {
                document.getElementById('st-dot').className = 'dot';
                log("System is up to date.", 'suc');
            }
        } else {
            document.getElementById('st-dot').className = 'dot off';
            document.getElementById('ver-new').innerText = 'Err';
            log("Check Failed: " + d.msg, 'err');
        }
    })
    .catch(() => {
        document.getElementById('ldr').style.display = 'none';
        document.getElementById('st-dot').className = 'dot off';
        log("Connection Failed.", 'err');
    });
}

function startUpdate() {
    if(!confirm('Start Realtime Update?')) return;
    if(es) es.close();

    isFinished = false; 
    document.getElementById('btn-up').style.display = 'none';
    document.getElementById('pg-box').style.display = 'block';
    document.getElementById('st-dot').className = 'dot wait';
    document.getElementById('log-box').innerHTML = ''; 
    
    const bar = document.getElementById('pg-in');
    const pctTxt = document.getElementById('pg-pct');
    const statusTxt = document.getElementById('pg-txt');

    es = new EventSource('?api=update_stream&url=' + encodeURIComponent(upUrl));

    es.onmessage = function(e) {
        if(e.data === 'end') {
            isFinished = true; 
            es.close();
            bar.style.width = '100%';
            pctTxt.innerText = '100%';
            statusTxt.innerText = 'Completed';
            document.getElementById('st-dot').className = 'dot on';
            log("Update Completed! Rebooting...", 'suc');
            setTimeout(() => location.reload(), 3000);
            return;
        }

        try {
            const data = JSON.parse(e.data);
            if(data.msg && data.pct === null) log(data.msg);

            if(data.pct !== null) {
                bar.style.width = data.pct + '%';
                pctTxt.innerText = data.pct + '%';
                statusTxt.innerText = 'Downloading... ' + data.pct + '%';
            }
        } catch(err) {}
    };

    es.onerror = function() {
        if (isFinished) return;

        es.close();
        document.getElementById('st-dot').className = 'dot off';
        statusTxt.innerText = 'Error';
        bar.style.backgroundColor = 'var(--dang)';
        log("Connection Lost / Script Error.", 'err');
        setTimeout(() => {
             document.getElementById('pg-box').style.display = 'none';
             document.getElementById('btn-up').style.display = 'block';
        }, 3000);
    };
}

window.onload = check;
</script>
</body>
</html>
