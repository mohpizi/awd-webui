<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

// --- FUNGSI EKSEKUSI (MENGGUNAKAN LOGIKA DEBUG YANG TERBUKTI SUKSES) ---
function exec_root($cmd) {
    // KITA GUNAKAN FORMAT PERSIS SEPERTI DEBUG:
    // 1. Tidak pakai path panjang (/system/bin/su), cukup 'su' (mengandalkan environment magisk)
    // 2. Menggunakan tanda kutip satu (') untuk membungkus perintah. 
    //    Ini penting agar tanda kutip dua (") di dalam $cmd tidak bentrok.
    // 3. Tambahkan 2>&1 agar output/error tertangkap (mencegah silent fail)
    return trim(shell_exec("su -c '$cmd' 2>&1"));
}

$message = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_airplane') {
        $mode = $_POST['mode'];
        
        if ($mode == '1') { // --- TAKEOFF (ON) ---
            $radios = [];
            // Logic: Radio yang dipilih akan dimasukkan ke pengecualian (tetap hidup)
            if (isset($_POST['cell'])) $radios[] = 'cell';
            if (isset($_POST['bluetooth'])) $radios[] = 'bluetooth';
            
            // Variabel ini mengandung koma, misal: "cell,bluetooth"
            // Karena kita pakai exec_root dengan single quote, double quote di sini AMAN.
            $radios_str = implode(',', $radios);
            
            // 1. Simpan preferensi radio
            exec_root("settings put global airplane_mode_radios \"$radios_str\"");
            
            // 2. Aktifkan Mode Pesawat
            exec_root("settings put global airplane_mode_on 1");
            
            // 3. Broadcast ke sistem (PENTING: tambah --user 0 agar tembus ke sistem)
            exec_root("am broadcast -a android.intent.action.AIRPLANE_MODE --ez state true --user 0");
            
            // 4. Atur Koneksi (Menggunakan perintah CMD Connectivity - Android 10+)
            $net = $_POST['net_pref'] ?? 'hotspot';
            if ($net === 'wifi') {
                // Matikan hotspot dulu (jika nyala) agar tidak konflik
                exec_root("cmd connectivity stop-tethering");
                exec_root("svc wifi enable");
            } else {
                // Hotspot Mode
                exec_root("svc wifi disable");
                // Perintah baru untuk menyalakan hotspot
                exec_root("cmd connectivity start-tethering wifi");
            }

            $message = "Airplane Mode ON";
            $msg_type = "success";

        } else { // --- LAND (OFF) ---
            // Matikan Mode Pesawat
            exec_root("settings put global airplane_mode_on 0");
            exec_root("am broadcast -a android.intent.action.AIRPLANE_MODE --ez state false --user 0");
            
            $message = "Airplane Mode OFF";
            $msg_type = "warning";
        }
    } 
    elseif ($action === 'update_radios') {
        $wifi = isset($_POST['wifi_control']) ? 'enable' : 'disable';
        $bt = isset($_POST['bluetooth_control']) ? 'enable' : 'disable';
        
        exec_root("svc wifi $wifi");
        exec_root("svc bluetooth $bt");
        
        $message = "Radios Updated";
        $msg_type = "success";
    }
}

// Ambil data untuk Update UI
$device_model = exec_root("getprop ro.product.model");
$airplane_status = exec_root("settings get global airplane_mode_on");
// Ambil status untuk switch toggle
$wifi_on = exec_root("settings get global wifi_on");
$bt_on = exec_root("settings get global bluetooth_on");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Airplane Control</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --primary: #fb8c00; --danger: #f5365c; --success: #2dce89; --warning: #fb6340;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05); --radius: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --primary: #ff9800; --danger: #fc8181; --success: #68d391; --warning: #ffb74d;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 16px; max-width: 600px; margin: 0 auto; padding-bottom: 80px; }
        
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        h1 { font-size: 1.4rem; font-weight: 700; margin: 0; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }
        .sub { font-size: 0.85rem; color: var(--sub); font-weight: 600; margin-top: 2px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid transparent; }
        .on { background: rgba(245, 54, 92, 0.1); color: var(--danger); border-color: var(--danger); }
        .off { background: rgba(45, 206, 137, 0.1); color: var(--success); border-color: var(--success); }

        .card { background: var(--card); border-radius: var(--radius); padding: 20px; margin-bottom: 16px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .c-title { font-weight: 700; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; font-size: 1rem; color: var(--text); }
        .icon { width: 22px; height: 22px; fill: currentColor; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px; }
        
        .opt-box { display: flex; align-items: center; justify-content: space-between; padding: 12px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; transition: 0.2s; background: var(--bg); }
        .opt-box:active { border-color: var(--primary); background: rgba(251, 140, 0, 0.1); }
        .opt-lbl { font-size: 0.9rem; font-weight: 600; }
        
        /* Switch CSS */
        .sw { position: relative; display: inline-block; width: 44px; height: 24px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--sub); transition: .4s; border-radius: 34px; opacity: 0.3; }
        .sl:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .sl { background-color: var(--primary); opacity: 1; }
        input:checked + .sl:before { transform: translateX(20px); }

        /* Radio CSS */
        .rad-grp { display: flex; gap: 10px; background: var(--bg); padding: 4px; border-radius: 10px; border: 1px solid var(--border); }
        .rad-lbl { flex: 1; text-align: center; padding: 8px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--sub); transition: 0.2s; }
        input[type="radio"] { display: none; }
        input[type="radio"]:checked + .rad-lbl { background: var(--card); color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

        .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn:active { transform: scale(0.98); opacity: 0.9; }
        .btn-p { background: var(--primary); color: #fff; box-shadow: 0 4px 6px rgba(251, 140, 0, 0.2); }
        .btn-d { background: var(--danger); color: #fff; box-shadow: 0 4px 6px rgba(245, 54, 92, 0.2); }
        .btn-o { background: transparent; border: 2px solid var(--border); color: var(--sub); }

        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; text-align: center; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .alert.success { background: rgba(45, 206, 137, 0.1); color: var(--success); border: 1px solid var(--success); }
        .alert.warning { background: rgba(251, 99, 64, 0.1); color: var(--warning); border: 1px solid var(--warning); }
    </style>
</head>
<body>

    <div class="head">
        <div>
            <h1>Airplane</h1>
            <div class="sub"><?= $device_model ?></div>
        </div>
        <div class="badge <?= ($airplane_status == 1) ? 'on' : 'off' ?>">
            STATUS: <?= ($airplane_status == 1) ? 'ACTIVE' : 'INACTIVE' ?>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert <?= $msg_type ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="c-title" style="color: var(--danger);">
            <svg class="icon" viewBox="0 0 24 24"><path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/></svg>
            Flight Control
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="toggle_airplane">
            
            <div class="grid">
                <label class="opt-box">
                    <span class="opt-lbl">Keep Cellular</span>
                    <div class="sw"><input type="checkbox" name="cell" checked><span class="sl"></span></div>
                </label>
                <label class="opt-box">
                    <span class="opt-lbl">Keep Bluetooth</span>
                    <div class="sw"><input type="checkbox" name="bluetooth" checked><span class="sl"></span></div>
                </label>
            </div>
            
            <div class="rad-grp" style="margin-bottom: 20px;">
                <label style="flex:1">
                    <input type="radio" name="net_pref" value="hotspot" checked>
                    <div class="rad-lbl">Hotspot Mode</div>
                </label>
                <label style="flex:1">
                    <input type="radio" name="net_pref" value="wifi">
                    <div class="rad-lbl">Wi-Fi Mode</div>
                </label>
            </div>

            <div class="grid">
                <button type="submit" name="mode" value="1" class="btn btn-d">TAKEOFF (ON)</button>
                <button type="submit" name="mode" value="0" class="btn btn-o">LAND (OFF)</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="c-title" style="color: var(--primary);">
            <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
            Quick Toggle
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_radios">
            <div class="grid">
                <label class="opt-box">
                    <span class="opt-lbl">Wi-Fi</span>
                    <div class="sw"><input type="checkbox" name="wifi_control" <?= ($wifi_on == 1) ? 'checked' : '' ?>><span class="sl"></span></div>
                </label>
                <label class="opt-box">
                    <span class="opt-lbl">Bluetooth</span>
                    <div class="sw"><input type="checkbox" name="bluetooth_control" <?= ($bt_on == 1) ? 'checked' : '' ?>><span class="sl"></span></div>
                </label>
            </div>
            <button type="submit" class="btn btn-p" style="margin-top: 5px;">Update Radios</button>
        </form>
    </div>

</body>
</html>
