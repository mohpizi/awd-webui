<?php
// --- 1. CONFIG ---
$message = "";
require_once '/data/adb/php8/files/www/auth/auth_functions.php'; // Sesuaikan path
$targetDir = '/data/adb/service.d';

// --- 2. LOGIKA ACTION (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scriptPath = $_POST['script_path'] ?? '';
    $pids = $_POST['pids'] ?? ''; // PID dikirim dari form agar tidak perlu scan ulang saat kill
    $action = $_POST['action'] ?? '';
    
    if (!empty($scriptPath)) {
        if ($action === 'kill') {
            // Kill berdasarkan PID yang terdeteksi sebelumnya
            if (!empty($pids)) {
                $pidList = explode(',', $pids);
                foreach ($pidList as $pid) {
                    shell_exec("su -c \"kill -9 " . trim($pid) . "\"");
                }
                $message = "Killed PIDs: $pids";
            } else {
                // Fallback: pkill by name
                $name = basename($scriptPath);
                shell_exec("su -c \"pkill -f '$name'\"");
                $message = "Force Kill sent via Name.";
            }
            sleep(1);
        }

        if ($action === 'start') {
            // Jalankan di background & buang outputnya
            $cmd = "su -c \"nohup sh " . escapeshellarg($scriptPath) . " > /dev/null 2>&1 &\"";
            shell_exec($cmd);
            sleep(2); // Tunggu sedikit lebih lama agar PID muncul
            $message = "Service started.";
        }
    }
}

// --- 3. ADVANCED PROCESS SCANNER (PHP SIDE) ---
$services = [];

// Langkah A: Ambil SEMUA proses yang berjalan (Raw Output)
// Menggunakan ps -ef untuk detail lengkap, atau ps -A -o PID,ARGS untuk Android modern
$raw_processes = shell_exec("su -c \"ps -ef\"");
if (empty($raw_processes) || strlen($raw_processes) < 50) {
    // Fallback jika ps -ef kosong (misal di Toybox version lama)
    $raw_processes = shell_exec("su -c \"ps -A\"");
}

// Langkah B: Ambil daftar file script
$raw_files = shell_exec("su -c \"ls $targetDir/*.sh 2>/dev/null\"");

if (!empty(trim($raw_files))) {
    $files = explode("\n", trim($raw_files));
    $processLines = explode("\n", $raw_processes);

    foreach ($files as $file) {
        if (empty(trim($file))) continue;
        
        $fileName = basename($file);
        $foundPids = [];

        // Langkah C: Loop setiap baris proses untuk mencari script ini
        foreach ($processLines as $line) {
            // Lewatkan proses grep atau proses PHP itu sendiri
            if (strpos($line, 'grep') !== false || strpos($line, 'monitor.php') !== false) continue;

            // KUNCI DETEKSI: Cek apakah nama file ada di baris proses
            if (strpos($line, $fileName) !== false) {
                // Ekstrak PID (biasanya angka pertama setelah user/kolom ke-2)
                // Kita gunakan Regex untuk mengambil angka pertama di baris tersebut yang bukan di awal (User ID)
                // Format ps biasanya: USER PID PPID ... CMD
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) > 1) {
                    // Index 1 biasanya PID pada output 'ps -ef' standar Android
                    $pidCandidate = $parts[1]; 
                    if (is_numeric($pidCandidate)) {
                        $foundPids[] = $pidCandidate;
                    }
                }
            }
        }

        $isRunning = !empty($foundPids);
        
        $services[] = [
            'path' => $file,
            'name' => $fileName,
            'running' => $isRunning,
            'pid' => $isRunning ? implode(",", array_unique($foundPids)) : '-'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Monitor V3</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-s: #fff3e0; --dang: #f5365c; --succ: #48bb78;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05); --rad: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-s: #3e2723; --dang: #fc8181; --succ: #68d391;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline:none; }
        body { font-family: monospace, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 600px; margin: 0 auto; }
        
        .header { text-align: center; margin-bottom: 25px; }
        h1 { color: var(--pri); margin: 0; font-size: 1.5rem; }
        .alert { background: var(--pri-s); color: var(--pri); padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid var(--pri); font-weight: bold; }
        
        .svc-item { background: var(--card); padding: 15px; border-radius: var(--rad); border: 1px solid var(--border); margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow); }
        .svc-info { display: flex; flex-direction: column; }
        .svc-name { font-weight: bold; font-size: 1rem; color: var(--text); word-break: break-all; }
        .svc-pid { font-size: 0.75rem; color: var(--sub); margin-top: 5px; font-family: monospace; }
        
        .badge { font-size: 0.65rem; padding: 3px 6px; border-radius: 4px; margin-left: 5px; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; vertical-align: middle; }
        .b-run { background: rgba(72, 187, 120, 0.15); color: var(--succ); border: 1px solid rgba(72, 187, 120, 0.3); }
        .b-stop { background: rgba(113, 128, 150, 0.15); color: var(--sub); border: 1px solid rgba(113, 128, 150, 0.3); }
        
        .btn { padding: 8px 0; width: 80px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 0.8rem; transition: 0.2s; }
        .btn-kill { background: transparent; border: 1px solid var(--dang); color: var(--dang); }
        .btn-kill:hover { background: var(--dang); color: white; }
        .btn-start { background: transparent; border: 1px solid var(--succ); color: var(--succ); }
        .btn-start:hover { background: var(--succ); color: white; }
        
        .refresh { width: 100%; padding: 14px; background: var(--pri); color: white; border: none; border-radius: 10px; margin-top: 15px; font-weight: bold; font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="header">
        <h1>SERVICE MONITOR</h1>
        <small style="color:var(--sub)">Advanced Process Detection</small>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (empty($services)): ?>
        <div style="text-align:center; padding:30px; color:var(--sub); font-style:italic">
            No .sh files found in /data/adb/service.d/
        </div>
    <?php else: ?>
        <?php foreach ($services as $svc): ?>
            <div class="svc-item">
                <div class="svc-info">
                    <div>
                        <span class="svc-name"><?= htmlspecialchars($svc['name']) ?></span>
                        <?php if ($svc['running']): ?>
                            <span class="badge b-run">RUNNING</span>
                        <?php else: ?>
                            <span class="badge b-stop">STOPPED</span>
                        <?php endif; ?>
                    </div>
                    <span class="svc-pid">
                        PID: <?= $svc['pid'] ?>
                    </span>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="script_path" value="<?= htmlspecialchars($svc['path']) ?>">
                    <input type="hidden" name="pids" value="<?= htmlspecialchars($svc['pid']) ?>">
                    
                    <?php if ($svc['running']): ?>
                        <button type="submit" name="action" value="kill" class="btn btn-kill" onclick="return confirm('Kill PID <?= $svc['pid'] ?>?')">KILL</button>
                    <?php else: ?>
                        <button type="submit" name="action" value="start" class="btn btn-start">START</button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <button onclick="location.reload()" class="refresh">REFRESH LIST</button>
    
    </body>
</html>