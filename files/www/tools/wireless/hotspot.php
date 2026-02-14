<?php
// --- 1. INISIALISASI & KONFIGURASI ---
$message = ""; 
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

define('BACKEND_SCRIPT', '/data/adb/php8/scripts/hotspot');
define('LOG_FILE', '/data/local/tmp/wifi_log.txt');
$serviceFile = '/data/adb/service.d/auto_hotspot.sh';

// --- TEMPLATE SCRIPT (Hanya dipakai saat pertama kali Enable) ---
$shellScriptContent = <<<'EOT'
#!/system/bin/sh
# Unified Tethering Manager (Hotspot + RNDIS Watchdog)
# Gabungan script auto_hotspot & auto_rndis

LOGFILE=/sdcard/TetheringManager.log

# --- 1. KONFIGURASI AWAL ---

# Tunggu Booting
while [ "$(getprop init.svc.bootanim)" != "stopped" ]; do
    sleep 2
done

# Setup Logging
log_msg() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOGFILE
}

# Setup Flashlight Path
FLASH_PATH=""
for path in /sys/class/leds/flashlight /sys/class/leds/torch-light0 /sys/class/leds/led:torch_0; do
    if [ -f "$path/brightness" ]; then
        FLASH_PATH="$path/brightness"
        break
    fi
done

# Setup Interface RNDIS (Auto Detect)
if ip link show rndis0 >/dev/null 2>&1; then
    IFACE_USB="rndis0"
elif ip link show usb0 >/dev/null 2>&1; then
    IFACE_USB="usb0"
else
    IFACE_USB="rndis0" # Default
fi

# --- 2. FUNGSI-FUNGSI UTAMA ---

blink_flash() {
    if [ ! -z "$FLASH_PATH" ]; then
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH; sleep 0.3
        echo 1 > $FLASH_PATH; sleep 0.3
        echo 0 > $FLASH_PATH
    fi
}

# --- BAGIAN A: HOTSPOT WIFI ---
is_hotspot_on() {
    # Cek status AP via dumpsys (Lebih akurat)
    if dumpsys wifi | grep -q "mWifiApState=13"; then
        return 0
    elif dumpsys wifi | grep -q "CurState=ApEnabledState"; then
        return 0
    else
        # Fallback cek IP wlan
        ip addr show wlan0 | grep -q "192.168."
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

# --- BAGIAN B: RNDIS USB ---
is_rndis_connected() {
    # Cek apakah interface USB punya IP
    ip addr show $IFACE_USB | grep -q "inet "
    return $?
}

enable_rndis() {
    log_msg "[USB] Mengaktifkan RNDIS pada $IFACE_USB..."
    setprop sys.usb.config rndis,adb
    sleep 3
}

# --- 3. INISIALISASI ---

# Tambah IP Loopback untuk Web Server
if ! ip addr show lo | grep -q "192.168.8.1"; then
    ip addr add 192.168.8.1/24 dev lo
    log_msg "[INIT] IP Loopback ditambahkan."
fi

# Cek Awal Hotspot saat boot
if ! is_hotspot_on; then
    enable_hotspot
    sleep 5
    if is_hotspot_on; then blink_flash; fi
fi

log_msg "[START] Tethering Manager Monitoring Started..."

# --- 4. LOOPING UTAMA (WATCHDOG) ---
while true; do
    
    # === CEK 1: HOTSPOT WIFI ===
    if ! is_hotspot_on; then
        log_msg "[WARN] Hotspot mati! Mencoba nyalakan kembali..."
        enable_hotspot
        # Tunggu sebentar untuk memastikan sistem memproses
        sleep 10 
        if is_hotspot_on; then
            log_msg "[SUCCESS] Hotspot berhasil direstore."
            blink_flash
        fi
    fi

    # === CEK 2: RNDIS USB ===
    # Cek apakah kabel USB tercolok (1 = Connected)
    USB_ONLINE=$(cat /sys/class/power_supply/usb/online 2>/dev/null)
    
    if [ "$USB_ONLINE" = "1" ]; then
        # Hanya jalankan logika RNDIS jika kabel dicolok
        if ! is_rndis_connected; then
            # Cek properti saat ini agar tidak spam command
            CUR_PROP=$(getprop sys.usb.config)
            
            # Jika IP tidak ada, dan config bukan rndis (atau kita paksa refresh)
            log_msg "[WARN] Kabel colok tapi IP RNDIS tidak ada. Fix..."
            enable_rndis
            
            sleep 10
            if is_rndis_connected; then
                 log_msg "[SUCCESS] RNDIS Connected & IP Obtained."
                 blink_flash
            fi
        fi
    fi

    # Delay 15 detik (Cukup cepat untuk responsif, cukup lama untuk hemat baterai)
    sleep 15
done
EOT;

// --- 2. LOGIKA PROSES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. ENABLE/DISABLE BOOT (Metode Tulis Temp -> Pindah via Root)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_boot') {
        header('Content-Type: application/json');
        $enable = $_POST['state'] === 'true';
        
        if ($enable) {
            // Tulis ke folder www dulu (karena PHP punya izin di sini)
            $tempFile = __DIR__ . '/temp_hotspot.sh';
            if (file_put_contents($tempFile, $shellScriptContent) !== false) {
                // Pindahkan menggunakan Root
                shell_exec("su -c \"cat '$tempFile' > '$serviceFile'\"");
                shell_exec("su -c \"chmod 755 '$serviceFile'\"");
                unlink($tempFile); // Hapus temp
                echo json_encode(['status' => 'success', 'message' => 'Auto Start Enabled']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Write Failed (Check WWW Perms)']);
            }
        } else {
            shell_exec("su -c \"rm '$serviceFile'\"");
            echo json_encode(['status' => 'success', 'message' => 'Auto Start Disabled']);
        }
        exit;
    }

    // B. SAVE IP LOOPBACK (PERBAIKAN UTAMA: MENGGUNAKAN SED)
    if (isset($_POST['action']) && $_POST['action'] === 'save_ip') {
        header('Content-Type: application/json');
        $newIp = $_POST['ip_address'] ?? '';
        
        // Validasi format IP CIDR (contoh: 192.168.8.1/24)
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $newIp)) {
            // Cek apakah file ada via Root
            $checkFile = shell_exec("su -c \"ls '$serviceFile' 2>/dev/null\"");
            
            if (!empty(trim($checkFile))) {
                // GUNAKAN SED: Cari baris 'ip addr add ... dev lo' dan ganti IP-nya saja.
                // Menggunakan delimiter | agar tidak bentrok dengan garis miring / pada CIDR
                $cmd = "su -c \"sed -i 's|ip addr add .* dev lo|ip addr add $newIp dev lo|g' '$serviceFile'\"";
                shell_exec($cmd);
                
                // Pastikan permission tetap benar
                shell_exec("su -c \"chmod 755 '$serviceFile'\"");
                
                echo json_encode(['status' => 'success', 'message' => 'IP Updated via SED']);
            } else {
                echo json_encode(['status' => 'warning', 'message' => 'File not found (Enable Boot First)']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP Format']);
        }
        exit;
    }

    // C. SAVE WIFI SETTINGS
    if (isset($_POST['save'])) {
        $ssid = $_POST['ssid'];
        $pass = $_POST['password'];
        if (strlen($pass) < 8) { $message = "Failed: Password min 8 chars."; } 
        else {
            $command = "su -c \"" . BACKEND_SCRIPT . " " . escapeshellarg($ssid) . " " . escapeshellarg($pass) . "\"";
            shell_exec($command);
            $message = "Config applied. Check Log.";
        }
    }
    
    if (isset($_POST['restart'])) shell_exec("su -c reboot");
    if (isset($_POST['clear_log'])) { shell_exec("su -c \"echo '' > " . LOG_FILE . "\""); $message = "Log cleared."; }
}

// --- 3. DATA FETCHING (BACA STATUS) ---

// Cek status file via Root
$checkFile = shell_exec("su -c \"ls '$serviceFile' 2>/dev/null\"");
$is_enabled = !empty(trim($checkFile));

$currentIp = '192.168.8.1/24'; // Default tampilan

if ($is_enabled) {
    // BACA ISI FILE MENGGUNAKAN CAT VIA ROOT (PENTING AGAR TERBACA)
    $content = shell_exec("su -c \"cat '$serviceFile'\"");
    
    // Cari IP yang tersimpan di file
    if (preg_match('/ip addr add (.*) dev lo/', $content, $matches)) {
        $currentIp = trim($matches[1]);
    }
}

// Ambil Config Wifi & Devices (Kode lama)
function getCurrentConfig() {
    $paths = ['/data/misc/apexdata/com.android.wifi/WifiConfigStoreSoftAp.xml', '/data/misc/wifi/WifiConfigStore.xml'];
    $content = "";
    foreach ($paths as $p) {
        $check = shell_exec("su -c \"ls $p 2>/dev/null\"");
        if (!empty(trim($check))) { $content = shell_exec("su -c \"cat $p\""); break; }
    }
    preg_match('/<string name="SSID">(.*?)<\/string>/', $content, $s);
    $ssid = $s[1] ?? '';
    if (empty($ssid)) {
        preg_match('/<string name="WifiSsid">&quot;(.*?)&quot;<\/string>/', $content, $s_alt);
        $ssid = $s_alt[1] ?? '';
    }
    preg_match('/<string name="Passphrase">(.*?)<\/string>/', $content, $p);
    $pass = $p[1] ?? '';
    if (empty($pass)) {
        preg_match('/<string name="PreSharedKey">&quot;(.*?)&quot;<\/string>/', $content, $p_alt);
        $pass = $p_alt[1] ?? '';
    }
    return ['ssid' => str_replace('&quot;', '', $ssid), 'pass' => str_replace('&quot;', '', $pass)];
}

function getConnectedDevicesDetail() {
    $output = shell_exec('ip neigh');
    $devices = [];
    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 4) {
                $ip = $parts[0]; $mac = 'N/A'; $status = end($parts);
                foreach ($parts as $part) { if (preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $part)) { $mac = $part; break; } }
                if ($mac !== 'N/A') { $devices[] = ['ip' => $ip, 'mac' => strtoupper($mac), 'status' => strtoupper($status)]; }
            }
        }
    }
    return $devices;
}

$current = getCurrentConfig();
$deviceList = getConnectedDevicesDetail();
$log_content = shell_exec("su -c \"cat " . LOG_FILE . "\"") ?: "Log empty.";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hotspot Manager Pro</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-s: #fff3e0; --dang: #f5365c; --tgl-bg: #cbd5e1;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05); --rad: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-s: #3e2723; --dang: #fc8181; --tgl-bg: #4b5563;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 600px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 25px; }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--pri); text-transform: uppercase; }
        .sub { font-size: 0.9rem; color: var(--sub); }
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; background: var(--card); padding: 5px; border-radius: 50px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .tab { flex: 1; background: transparent; border: none; color: var(--sub); padding: 10px; border-radius: 25px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: 0.2s; }
        .tab.active { background: var(--pri); color: white; }
        .view { display: none; } .view.active { display: block; }
        .card { background: var(--card); border-radius: var(--rad); padding: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 20px; }
        .grp { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; font-size: 0.8rem; font-weight: 600; color: var(--sub); text-transform: uppercase; }
        input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 1rem; }
        .sw-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; }
        .sw { position: relative; width: 46px; height: 24px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: var(--tgl-bg); transition: .3s; border-radius: 30px; }
        .sl:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; transition: .3s; border-radius: 50%; }
        input:checked + .sl { background: var(--pri); }
        input:checked + .sl:before { transform: translateX(22px); }
        .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: 0.2s; font-size: 0.9rem; text-transform: uppercase; }
        .bp { background: var(--pri); color: white; }
        .bo { background: transparent; border: 1px solid var(--border); color: var(--sub); }
        .bd { background: transparent; border: 1px solid var(--dang); color: var(--dang); }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        td, th { padding: 12px 8px; border-bottom: 1px solid var(--border); text-align: left; }
        .log { background: #1a1a1a; color: #e0e0e0; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.75rem; white-space: pre-wrap; height: 250px; overflow-y: auto; border: 1px solid var(--border); margin-bottom: 15px; }
        .alert { background: var(--pri-s); color: var(--pri); padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 600; border: 1px solid var(--pri); }
        #toast { visibility: hidden; min-width: 200px; background: #333; color: #fff; text-align: center; border-radius: 50px; padding: 10px; position: fixed; z-index: 100; bottom: 30px; left: 50%; transform: translateX(-50%); opacity: 0; transition: 0.3s; font-size: 0.8rem; }
        #toast.show { visibility: visible; opacity: 1; bottom: 40px; }
    </style>
</head>
<body>

    <header>
        <h1>Hotspot Manager</h1>
        <div class="sub">Control Service & Monitor</div>
    </header>

    <?php if ($message): ?>
        <div class="alert"><?= $message ?></div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab active" onclick="sw('set')" id="b-set">Settings</button>
        <button class="tab" onclick="sw('dev')" id="b-dev">Clients</button>
        <button class="tab" onclick="sw('log')" id="b-log">Logs</button>
    </div>

    <div id="v-set" class="view active">
        <div class="card">
            <div class="sw-row">
                <span style="font-weight:700">Auto Start on Boot</span>
                <label class="sw">
                    <input type="checkbox" id="bt" <?= $is_enabled ? 'checked' : '' ?>>
                    <span class="sl"></span>
                </label>
            </div>
            <div class="grp" style="margin-top:15px">
                <label>IP Loopback</label>
                <div style="display:flex; gap:8px">
                    <input type="text" id="ip_val" value="<?= htmlspecialchars($currentIp) ?>" style="font-family:monospace">
                    <button id="save_ip_btn" class="bp" style="width:80px; border-radius:8px; border:none; color:white; font-weight:700; cursor:pointer">SET</button>
                </div>
            </div>
        </div>

        <div class="card">
            <form method="POST">
                <div class="grp"><label>SSID Name</label><input type="text" name="ssid" value="<?= htmlspecialchars($current['ssid']) ?>" required></div>
                <div class="grp"><label>Password</label><input type="text" name="password" value="<?= htmlspecialchars($current['pass']) ?>" required></div>
                <button type="submit" name="save" class="btn bp">Apply Wifi Settings</button>
            </form>
            <form method="POST" onsubmit="return confirm('Reboot device?')"><button type="submit" name="restart" class="btn bo">Reboot System</button></form>
        </div>
    </div>

    <div id="v-dev" class="view">
        <div class="card">
            <h3 style="margin-bottom:15px; font-size:0.9rem">Connected Devices</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>IP</th><th>MAC</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (empty($deviceList)): ?>
                            <tr><td colspan="3" style="text-align:center; padding:20px; color:var(--sub)">No devices.</td></tr>
                        <?php else: foreach ($deviceList as $d): ?>
                            <tr>
                                <td style="font-family:monospace"><?= htmlspecialchars($d['ip']) ?></td>
                                <td style="font-family:monospace"><?= htmlspecialchars($d['mac']) ?></td>
                                <td style="color:var(--pri); font-weight:700"><?= htmlspecialchars($d['status']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <button onclick="location.reload()" class="btn bo">Refresh List</button>
        </div>
    </div>

    <div id="v-log" class="view">
        <div class="card">
            <label>Service Log</label>
            <div class="log"><?= htmlspecialchars($log_content) ?></div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <button onclick="location.reload()" class="btn bo">Refresh</button>
                <form method="POST" style="margin:0"><button type="submit" name="clear_log" class="btn bd">Clear</button></form>
            </div>
        </div>
    </div>

    <div id="toast">Saved!</div>

    <script>
        const t = document.getElementById("toast");
        function msg(m) { t.innerText = m; t.className = "show"; setTimeout(() => t.className = "", 3000); }
        
        function sw(t) {
            document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(e => e.classList.remove('active'));
            document.getElementById('v-'+t).classList.add('active');
            document.getElementById('b-'+t).classList.add('active');
        }

        // Auto Start Toggle
        document.getElementById('bt').addEventListener('change', function() {
            const s = this.checked;
            const fd = new FormData(); fd.append('action', 'toggle_boot'); fd.append('state', s);
            fetch('', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { msg(d.message); if(d.status==='error') this.checked = !s; })
            .catch(() => { msg("Failed"); this.checked = !s; });
        });

        // Save IP Loopback
        document.getElementById('save_ip_btn').addEventListener('click', function() {
            const v = document.getElementById('ip_val').value;
            const fd = new FormData(); fd.append('action', 'save_ip'); fd.append('ip_address', v);
            fetch('', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => msg(d.message))
            .catch(() => msg("Error saving IP"));
        });
    </script>
</body>
</html>