<?php
// --- 1. CONFIGURATION ---
$message = "";
require_once '/data/adb/php8/files/www/auth/auth_functions.php'; 
$serviceFile = '/data/adb/service.d/fix_ttl_64.sh';

define('IPT', '/system/bin/iptables');
define('IP6T', '/system/bin/ip6tables');

// --- 2. LOGIKA PROSES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil nilai TTL dari input
    $ttl_val = isset($_POST['ttl_val']) ? (int)$_POST['ttl_val'] : 64;
    if ($ttl_val < 1 || $ttl_val > 255) $ttl_val = 64;

    // --- TEMPLATE SCRIPT (Disiapkan untuk Toggle MAUPUN Apply) ---
    $shellScriptContent = <<<EOT
#!/system/bin/sh
# TTL Fixer Custom ($ttl_val)
while [ "$(getprop init.svc.bootanim)" != "stopped" ]; do sleep 5; done
IPT=/system/bin/iptables
IP6T=/system/bin/ip6tables

# Clean up old rules
\$IPT -t mangle -D POSTROUTING -j TTL --ttl-set $ttl_val 2>/dev/null
\$IP6T -t mangle -D POSTROUTING -j HL --hl-set $ttl_val 2>/dev/null

# Apply New Rules
\$IPT -t mangle -I POSTROUTING 1 -j TTL --ttl-set $ttl_val
\$IP6T -t mangle -I POSTROUTING 1 -j HL --hl-set $ttl_val
EOT;

    // A. TOGGLE AUTO BOOT
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_boot') {
        header('Content-Type: application/json');
        $enable = $_POST['state'] === 'true';
        
        if ($enable) {
            $tempFile = __DIR__ . '/temp_ttl.sh';
            if (file_put_contents($tempFile, $shellScriptContent) !== false) {
                shell_exec("su -c \"cat '$tempFile' > '$serviceFile'\"");
                shell_exec("su -c \"chmod 755 '$serviceFile'\"");
                unlink($tempFile);
                echo json_encode(['status' => 'success', 'message' => "Auto Start Enabled (TTL $ttl_val)"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Write Error']);
            }
        } else {
            shell_exec("su -c \"rm '$serviceFile'\"");
            echo json_encode(['status' => 'success', 'message' => 'Auto Start Disabled']);
        }
        exit;
    }

    // B. APPLY NOW (+ LOGIKA UPDATE BOOT FILE)
    if (isset($_POST['action']) && $_POST['action'] === 'apply_now') {
        
        // 1. Terapkan ke Sistem (Immediate Apply)
        shell_exec("su -c \"" . IPT . " -t mangle -D POSTROUTING -j TTL --ttl-set 64 2>/dev/null\"");
        shell_exec("su -c \"" . IPT . " -t mangle -D POSTROUTING -j TTL --ttl-set $ttl_val 2>/dev/null\"");
        
        $out = shell_exec("su -c \"" . IPT . " -t mangle -I POSTROUTING 1 -j TTL --ttl-set $ttl_val 2>&1\"");
        shell_exec("su -c \"" . IP6T . " -t mangle -I POSTROUTING 1 -j HL --hl-set $ttl_val 2>&1\"");
        
        $statusMsg = $out ? "Error applying iptables." : "TTL $ttl_val Applied.";

        // 2. LOGIKA BARU: Cek & Update File Boot jika ada
        $checkBoot = shell_exec("su -c \"ls '$serviceFile' 2>/dev/null\"");
        
        if (!empty(trim($checkBoot))) {
            // File boot ada, berarti user menghidupkan toggle sebelumnya.
            // Kita harus update isinya agar sinkron dengan input textbox terbaru.
            $tempFile = __DIR__ . '/temp_ttl_sync.sh';
            file_put_contents($tempFile, $shellScriptContent);
            
            shell_exec("su -c \"cat '$tempFile' > '$serviceFile'\"");
            shell_exec("su -c \"chmod 755 '$serviceFile'\"");
            unlink($tempFile);
            
            $statusMsg .= " & Boot Script Updated.";
        }

        $message = $statusMsg;
    }

    // C. RESET
    if (isset($_POST['action']) && $_POST['action'] === 'reset_now') {
        shell_exec("su -c \"" . IPT . " -t mangle -D POSTROUTING -j TTL --ttl-set $ttl_val 2>/dev/null\"");
        shell_exec("su -c \"" . IP6T . " -t mangle -D POSTROUTING -j HL --hl-set $ttl_val 2>/dev/null\"");
        shell_exec("su -c \"" . IPT . " -t mangle -D POSTROUTING -j TTL --ttl-set 64 2>/dev/null\"");
        $message = "TTL Rules Removed.";
    }
}

// --- 3. CHECK STATUS ---
$is_boot_enabled = !empty(trim(shell_exec("su -c \"ls '$serviceFile' 2>/dev/null\"")));

// Cek TTL Aktif di Iptables
$iptables_dump = shell_exec("su -c \"" . IPT . " -t mangle -S POSTROUTING\"");
$current_active_ttl = 'N/A';
$is_active = false;

if (preg_match('/--ttl-set (\d+)/', $iptables_dump, $matches)) {
    $current_active_ttl = $matches[1];
    $is_active = true;
}

// Logic Input Value: Prioritas TTL Aktif -> Input Terakhir -> Default 64
$input_value = ($is_active && is_numeric($current_active_ttl)) ? $current_active_ttl : 64;
if (isset($_POST['ttl_val'])) $input_value = $_POST['ttl_val']; // Agar input tidak berubah saat refresh POST
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTL Manager Pro</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-h: #ef6c00; --pri-s: #fff3e0; 
            --dang: #f5365c; --succ: #48bb78;
            --tgl-bg: #cbd5e1; --rad: 12px; --inp: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-h: #ffa726; --pri-s: #3e2723; 
                --dang: #fc8181; --succ: #68d391;
                --tgl-bg: #4b5563; --inp: #2c2c2c;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 500px; margin: 0 auto; }
        
        header { text-align: center; margin-bottom: 25px; }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--pri); margin-bottom: 5px; text-transform: uppercase; }
        .sub { font-size: 0.9rem; color: var(--sub); }

        .card { background: var(--card); border-radius: var(--rad); padding: 24px; box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 20px; }
        .alert { background: var(--pri-s); color: var(--pri); padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 600; border: 1px solid var(--pri); font-size: 0.9rem;}

        .stat-box { display:flex; flex-direction:column; align-items:center; margin-bottom:20px; padding:15px; background:var(--bg); border-radius:10px; border:1px solid var(--border); }
        .big-stat { font-size: 1.3rem; font-weight: 800; margin-bottom: 5px; }
        .c-on { color: var(--succ); }
        .c-off { color: var(--sub); }

        .grp { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-size: 0.9rem; font-weight: 600; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .inp-row { display: flex; gap: 10px; }
        .inp { flex: 1; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--inp); color: var(--text); font-size: 1.1rem; font-weight: 700; text-align: center; font-family: monospace; transition: 0.2s; }
        .inp:focus { border-color: var(--pri); box-shadow: 0 0 0 3px rgba(251, 140, 0, 0.2); }

        .sw-row { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; margin-top: 10px; }
        .sw { position: relative; width: 50px; height: 28px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--tgl-bg); transition: .4s; border-radius: 34px; }
        .sl:before { position: absolute; content: ""; height: 22px; width: 22px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .sl { background-color: var(--pri); }
        input:checked + .sl:before { transform: translateX(22px); }

        .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: 0.2s; font-size: 0.95rem; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .bp { background: var(--pri); color: white; }
        .bp:active { transform: translateY(1px); background: var(--pri-h); }
        .bd { background: transparent; border: 1px solid var(--dang); color: var(--dang); margin-top: 15px; box-shadow: none; }
        .bd:active { background: var(--dang); color: white; }

        #toast { visibility: hidden; min-width: 200px; background: #333; color: #fff; text-align: center; border-radius: 50px; padding: 10px; position: fixed; z-index: 100; bottom: 30px; left: 50%; transform: translateX(-50%); opacity: 0; transition: 0.3s; font-size: 0.8rem; }
        #toast.show { visibility: visible; opacity: 1; bottom: 40px; }
    </style>
</head>
<body>

    <header>
        <h1>Custom TTL Fixer</h1>
        <div class="sub">Advanced Hotspot Bypass</div>
    </header>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="stat-box">
            <span style="font-size:0.75rem; text-transform:uppercase; color:var(--sub); margin-bottom:5px;">System Iptables Status</span>
            <?php if ($is_active): ?>
                <span class="big-stat c-on">ACTIVE: TTL <?= $current_active_ttl ?></span>
                <small style="color:var(--succ)">Running</small>
            <?php else: ?>
                <span class="big-stat c-off">INACTIVE</span>
                <small style="color:var(--sub)">No TTL rules found</small>
            <?php endif; ?>
        </div>

        <hr style="border:0; border-top:1px dashed var(--border); margin:20px 0;">

        <form method="POST" id="mainForm">
            <div class="grp">
                <label>Target TTL Value</label>
                <div class="inp-row">
                    <input type="number" name="ttl_val" id="ttlInput" class="inp" value="<?= $input_value ?>" min="1" max="255" placeholder="64">
                </div>
            </div>

            <div class="sw-row">
                <span style="font-weight:700; font-size:0.95rem;">Enable on Boot</span>
                <label class="sw">
                    <input type="checkbox" id="bt" <?= $is_boot_enabled ? 'checked' : '' ?>>
                    <span class="sl"></span>
                </label>
            </div>

            <button type="submit" name="action" value="apply_now" class="btn bp">APPLY TTL</button>
            <button type="submit" name="action" value="reset_now" class="btn bd" onclick="return confirm('Remove all TTL rules?')">REMOVE RULES</button>
        </form>
    </div>

    <div id="toast">Saved!</div>

    <script>
        const t = document.getElementById("toast");
        function msg(m) { t.innerText = m; t.className = "show"; setTimeout(() => t.className = "", 3000); }

        document.getElementById('bt').addEventListener('change', function() {
            const s = this.checked;
            const ttl = document.getElementById('ttlInput').value;
            
            const fd = new FormData(); 
            fd.append('action', 'toggle_boot'); 
            fd.append('state', s);
            fd.append('ttl_val', ttl);

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { 
                msg(d.message); 
                if(d.status==='error') this.checked = !s; 
            })
            .catch(() => { msg("Connection Failed"); this.checked = !s; });
        });
    </script>

</body>
</html>