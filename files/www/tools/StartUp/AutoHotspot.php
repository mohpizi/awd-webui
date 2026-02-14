<?php
$serviceFile = '/data/adb/service.d/auto_hotspot.sh';
$shellScriptContent = <<<'EOT'
#!/system/bin/sh
# Unified Tethering Manager (Hotspot + RNDIS Watchdog)
# AreweDaks Script
LOGFILE=/sdcard/TetheringManager.log
while [ "$(getprop init.svc.bootanim)" != "stopped" ]; do
    sleep 2
done

log_msg() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOGFILE
}

FLASH_PATH=""
for path in /sys/class/leds/flashlight /sys/class/leds/torch-light0 /sys/class/leds/led:torch_0; do
    if [ -f "$path/brightness" ]; then
        FLASH_PATH="$path/brightness"
        break
    fi
done

if ip link show rndis0 >/dev/null 2>&1; then
    IFACE_USB="rndis0"
elif ip link show usb0 >/dev/null 2>&1; then
    IFACE_USB="usb0"
else
    IFACE_USB="rndis0"
fi

blink_flash() {
    if [ ! -z "$FLASH_PATH" ]; then
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH
    fi
}

is_hotspot_on() {
    if dumpsys wifi | grep -q "mWifiApState=13"; then
        return 0
    elif dumpsys wifi | grep -q "CurState=ApEnabledState"; then
        return 0
    else
        ip addr show wlan0 | grep -q "192.168.43"
        return $?
    fi
}

enable_hotspot() {
    log_msg "[WIFI] Menyalakan Hotspot..."
    cmd connectivity start-tethering wifi
    if [ $? -ne 0 ]; then
        service call tethering 4 null s16 random
    fi
}

is_rndis_connected() {
    ip addr show $IFACE_USB | grep -q "inet "
    return $?
}

enable_rndis() {
    log_msg "[USB] Mengaktifkan RNDIS pada $IFACE_USB..."
    setprop sys.usb.config rndis,adb
    sleep 3
}

if ! ip addr show lo | grep -q "192.168.8.1"; then
    ip addr add 192.168.8.1/24 dev lo
    log_msg "[INIT] IP Loopback 192.168.8.1 ditambahkan."
fi

if ! is_hotspot_on; then
    enable_hotspot
    sleep 5
    if is_hotspot_on; then blink_flash; fi
fi

log_msg "[START] Tethering Manager Monitoring Started..."

while true; do
    if ! is_hotspot_on; then
        log_msg "[WARN] Hotspot mati! Mencoba nyalakan kembali..."
        enable_hotspot
        sleep 10
        if is_hotspot_on; then
            log_msg "[SUCCESS] Hotspot berhasil direstore."
            blink_flash
        fi
    fi

    USB_ONLINE=$(cat /sys/class/power_supply/usb/online 2>/dev/null)
    
    if [ "$USB_ONLINE" = "1" ]; then
        if ! is_rndis_connected; then
            CUR_PROP=$(getprop sys.usb.config)
            
            log_msg "[WARN] Kabel colok tapi IP RNDIS tidak ada. Fix..."
            enable_rndis
            
            sleep 10
            if is_rndis_connected; then
                 log_msg "[SUCCESS] RNDIS Connected & IP Obtained."
                 blink_flash
            fi
        fi
    fi
    sleep 15
done
EOT;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_boot') {
        $enable = $_POST['state'] === 'true';
        
        if ($enable) {
            $result = file_put_contents($serviceFile, $shellScriptContent);
            if ($result !== false) {
                shell_exec("chmod 755 $serviceFile");
                echo json_encode(['status' => 'success', 'message' => 'Auto Start Enabled']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to write file (Check Root Permission)']);
            }
        } else {
            if (file_exists($serviceFile)) {
                unlink($serviceFile);
            }
            echo json_encode(['status' => 'success', 'message' => 'Auto Start Disabled']);
        }
        exit;
    }

    if ($action === 'save_ip') {
        $newIp = $_POST['ip_address'] ?? '';
        
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $newIp)) {
            $newContent = preg_replace('/ip addr add .* dev lo/', "ip addr add $newIp dev lo", $shellScriptContent);
            
            if (file_exists($serviceFile)) {
                file_put_contents($serviceFile, $newContent);
                shell_exec("chmod 755 $serviceFile");
                echo json_encode(['status' => 'success', 'message' => 'IP Updated & Saved']);
            } else {
                echo json_encode(['status' => 'warning', 'message' => 'IP Saved (Enable Auto Start to Apply)']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP Format']);
        }
        exit;
    }
}

$is_enabled = file_exists($serviceFile);
$currentIp = '192.168.8.1/24';
if ($is_enabled) {
    $content = file_get_contents($serviceFile);
    if (preg_match('/ip addr add (.*) dev lo/', $content, $matches)) {
        $currentIp = $matches[1];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hotspot Auto Start</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-h: #ef6c00; --tgl-bg: #cbd5e1; --tgl-act: #fb8c00;
            --shd: 0 4px 6px -1px rgba(0,0,0,0.05); --rad: 12px; --inp: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-h: #ffa726; --tgl-bg: #4b5563; --tgl-act: #ff9800;
                --shd: 0 4px 6px -1px rgba(0,0,0,0.4); --inp: #2c2c2c;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .con { width: 100%; max-width: 500px; }
        
        .head { text-align: center; margin-bottom: 30px; }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--pri); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        p { color: var(--sub); font-size: 0.9rem; }

        .card { background: var(--card); border-radius: var(--rad); padding: 24px; box-shadow: var(--shd); border: 1px solid var(--border); }
        
        .row { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; }
        .lbl { font-size: 1rem; font-weight: 700; color: var(--text); }
        
        .sw { position: relative; display: inline-block; width: 50px; height: 28px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--tgl-bg); transition: .4s; border-radius: 34px; }
        .sl:before { position: absolute; content: ""; height: 22px; width: 22px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .sl { background-color: var(--tgl-act); }
        input:checked + .sl:before { transform: translateX(22px); }

        .grp { margin-top: 20px; }
        .g-lbl { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; color: var(--sub); text-transform: uppercase; }
        .inp { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--inp); color: var(--text); font-size: 1rem; font-family: monospace; transition: 0.2s; }
        .inp:focus { border-color: var(--pri); box-shadow: 0 0 0 3px rgba(251, 140, 0, 0.2); }

        .btn { width: 100%; padding: 14px; margin-top: 20px; border: none; border-radius: 10px; background: var(--pri); color: white; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn:hover { background: var(--pri-h); transform: translateY(-1px); }
        .btn:active { transform: translateY(1px); }
        .btn:disabled { opacity: 0.7; cursor: wait; }

        #toast { visibility: hidden; min-width: 250px; background: #333; color: #fff; text-align: center; border-radius: 50px; padding: 12px; position: fixed; z-index: 100; bottom: 30px; left: 50%; transform: translateX(-50%); box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-weight: 600; font-size: 0.9rem; opacity: 0; transition: 0.3s; }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
    </style>
</head>
<body>

    <div class="con">
        <div class="head">
            <h1>Hotspot Manager</h1>
            <p>Auto Start Service Configuration</p>
        </div>

        <div class="card">
            <div class="row">
                <span class="lbl">Enable on Boot</span>
                <label class="sw">
                    <input type="checkbox" id="bt" <?= $is_enabled ? 'checked' : '' ?>>
                    <span class="sl"></span>
                </label>
            </div>

            <hr style="border: 0; border-top: 1px dashed var(--border); margin: 20px 0;">

            <div class="grp">
                <label class="g-lbl">IP Address Loopback</label>
                <input type="text" id="ip" class="inp" value="<?= htmlspecialchars($currentIp) ?>" placeholder="e.g. 192.168.8.1/24">
            </div>

            <button id="sb" class="btn">Save Configuration</button>
        </div>
    </div>

    <div id="toast">Saved!</div>

    <script>
        const t = document.getElementById("toast");
        function msg(m) { t.innerText = m; t.className = "show"; setTimeout(() => t.className = "", 3000); }

        document.getElementById('bt').addEventListener('change', function() {
            const s = this.checked;
            const fd = new FormData(); fd.append('action', 'toggle_boot'); fd.append('state', s);
            fetch('', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { msg(d.message); if(d.status==='error') this.checked = !s; })
            .catch(() => { msg("Connection Failed"); this.checked = !s; });
        });

        document.getElementById('sb').addEventListener('click', function() {
            const v = document.getElementById('ip').value, b = this, o = b.innerText;
            b.disabled = true; b.innerText = "Saving...";
            const fd = new FormData(); fd.append('action', 'save_ip'); fd.append('ip_address', v);
            fetch('', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { msg(d.message); b.disabled = false; b.innerText = o; })
            .catch(() => { msg("Failed to save IP"); b.disabled = false; b.innerText = o; });
        });
    </script>

</body>
</html>