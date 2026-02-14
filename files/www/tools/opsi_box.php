<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

if (isset($_POST['action']) && $_POST['action'] === 'run_fix') {
    header('Content-Type: application/json');

    $source = '/data/adb/php8/files/backup/config.yaml';
    $dest = '/data/adb/box/clash/config.yaml';

    // 1. Cek dulu apakah file backup ada (Pencegahan awal)
    if (!file_exists($source)) {
        echo json_encode(['status' => 'error', 'message' => 'File Backup tidak ditemukan di: ' . $source]);
        exit;
    }

    // 2. Siapkan Perintah Shell yang Robust
    // "|| true" artinya: Jika error, abaikan dan lanjut.
    $commands = [
        "box_stop" => "/data/adb/box/scripts/box.iptables disable || true",
        "srv_stop" => "/data/adb/box/scripts/box.service stop || true",
        "wait"     => "sleep 3", // Tunggu log disconnect
        "copy"     => "cp -f '$source' '$dest'", // Pakai tanda kutip agar aman spasi
        "perm"     => "chmod 644 '$dest'"
    ];

    // Gabungkan perintah jadi satu baris panjang
    $full_cmd = implode('; ', $commands);
    
    // Jalankan perintah via Root
    // 2>&1 digabung di akhir untuk menangkap error message
    $final_exec = "su -c \"$full_cmd\" 2>&1";
    
    exec($final_exec, $output, $return_var);
    $log_output = implode("\n", $output);

    // 3. Verifikasi Akhir (Deep Check)
    // Kita cek apakah MD5 file sumber sama dengan tujuan
    $md5_source = md5_file($source);
    $md5_dest = file_exists($dest) ? md5_file($dest) : 'not_found';

    if ($md5_source === $md5_dest) {
        // Jika sidik jari file sama, berarti copy 100% berhasil
        echo json_encode([
            'status' => 'success', 
            'message' => 'BERHASIL! Service stop & Config terganti.',
            'debug' => "Source MD5: $md5_source\nDest MD5: $md5_dest"
        ]);
    } else {
        // Jika beda, berarti copy gagal
        echo json_encode([
            'status' => 'error', 
            'message' => 'GAGAL COPY! Hash file tidak sama.', 
            'debug' => "Output Shell:\n$log_output\n\nMD5 Source: $md5_source\nMD5 Dest: $md5_dest"
        ]);
    }
    exit;
}

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
    <title>Box For Root</title>
    <style>
        :root {
            --bg: #f8f9fa; --nav-bg: #e2e8f0; --text: #2d3748; --text-muted: #718096;
            --pri: #fb8c00; --active-bg: #ffffff; --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --nav-bg: #1e1e1e; --text: #e0e0e0; --text-muted: #757575;
                --pri: #fb8c00; --active-bg: #2d2d2d; --border: #2d2d2d;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        .head { padding: 15px; display: flex; justify-content: center; background: var(--bg); flex-shrink: 0; }
        
        .nav-pill {
            background: var(--nav-bg); padding: 5px; border-radius: 12px;
            display: flex; gap: 5px; overflow-x: auto; max-width: 100%;
            scrollbar-width: none; border: 1px solid var(--border);
        }
        .nav-pill::-webkit-scrollbar { display: none; }
        
        .tab {
            padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600;
            color: var(--text-muted); white-space: nowrap; cursor: pointer;
            transition: all 0.2s ease; user-select: none;
        }
        .tab.active {
            background: var(--active-bg); color: var(--pri);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .tab-fix { color: #f44336 !important; font-weight: 800; }
        .tab-fix:active { transform: scale(0.95); opacity: 0.7; }

        .main { flex-grow: 1; position: relative; width: 100%; height: 100%; }
        .view { display: none; width: 100%; height: 100%; }
        .view.active { display: block; }
        iframe { width: 100%; height: 100%; border: none; background: var(--bg); }
    </style>
</head>
<body>

<div class="head">
    <div class="nav-pill">
        <div class="tab tab-fix" onclick="fx()" id="b-FIX">⚡ FIX</div>
        <div class="tab active" onclick="sw('BFR')" id="b-BFR">Box For Root</div>
        <div class="tab" onclick="sw('SRV')" id="b-SRV">Services</div>
        <div class="tab" onclick="sw('EDT')" id="b-EDT">Akun</div>
        <div class="tab" onclick="sw('YML')" id="b-YML">Config.yaml</div>
    </div>
</div>

<div class="main">
    <div id="v-BFR" class="view active"><iframe id="f-BFR"></iframe></div>
    <div id="v-SRV" class="view"><iframe id="f-SRV"></iframe></div>
    <div id="v-EDT" class="view"><iframe id="f-EDT"></iframe></div>
    <div id="v-YML" class="view"><iframe id="f-YML"></iframe></div>
</div>

<script>
    const u = {
        'BFR': 'bfr/executed.php',
        'SRV': 'bfr/boxsettings.php',
        'EDT': 'convert/link2yaml.php',
        'YML': 'http://<?php echo $host; ?>/tiny/index.php?p=data%2Fadb%2Fbox%2Fclash&view=config.yaml',
    };

    function sw(k) {
        document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab:not(.tab-fix)').forEach(e => e.classList.remove('active'));
        document.getElementById('v-'+k).classList.add('active');
        document.getElementById('b-'+k).classList.add('active');
        const f = document.getElementById('f-'+k);
        if (!f.getAttribute('src')) f.src = u[k];
    }

    function fx() {
        if (!confirm("Stop Box & Restore Config?")) return;
        const b = document.getElementById('b-FIX');
        const t = b.innerText;
        b.innerText = "⏳";
        const fd = new FormData(); fd.append('action', 'run_fix');
        fetch('', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => alert(d.message))
            .catch(() => alert("Gagal koneksi."))
            .finally(() => b.innerText = t);
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('f-BFR').src = u['BFR'];
    });
</script>

</body>
</html>