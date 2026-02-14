<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

// --- KONFIGURASI ---
date_default_timezone_set('Asia/Jakarta');

// Path File dan Binary
$TERMUX_BIN_PATH = "/data/data/com.termux/files/usr/bin/";
$VNSTAT_DB_PATH  = "/data/data/com.termux/files/usr/var/lib/vnstat/vnstat.db";
$BOOT_SCRIPT     = "/data/adb/service.d/vnstat_boot.sh";

// --- FUNGSI BANTUAN ---

/**
 * Mengonversi string ukuran (misal: "5.20 GiB") menjadi float dalam satuan MB.
 */
function parseTrafficToMB($string) {
    $value = floatval($string);
    $unit  = strtoupper(preg_replace('/[^A-Z]/', '', $string)); // Ambil huruf saja (KB, MB, GB, dll)
    
    // Faktor konversi ke MB
    $multipliers = [
        'K' => 1 / 1024,
        'M' => 1,
        'G' => 1024,
        'T' => 1048576
    ];

    foreach ($multipliers as $key => $multiplier) {
        if (strpos($unit, $key) === 0) {
            return $value * $multiplier;
        }
    }
    return 0;
}

/**
 * Mendapatkan daftar interface jaringan yang aktif
 */
function getInterfaces($binPath) {
    $output = shell_exec($binPath . "ifconfig -a");
    preg_match_all('/(wlan|rmnet_data|ccmni|tun|eth|rndis)\d+/', $output, $matches);
    return array_unique($matches[0]);
}

// --- API HANDLING (JSON RESPONSE) ---
if (isset($_GET['api']) && $_GET['api'] === 'get_stats') {
    header('Content-Type: application/json');

    $interfaces = getInterfaces($TERMUX_BIN_PATH);
    $dailyStats = [];

    // 1. Ambil Data Harian (-d)
    foreach ($interfaces as $iface) {
        // Jalankan vnstat untuk setiap interface
        $output = shell_exec($TERMUX_BIN_PATH . "vnstat -d -i " . escapeshellarg($iface) . " 2>&1");
        if (!$output) continue;

        foreach (explode("\n", $output) as $line) {
            // Regex untuk parsing baris output vnstat
            // Format: YYYY-MM-DD   RX   TX
            if (preg_match('/(\d{4}-\d{2}-\d{2})\s+([\d.]+\s+\w+)\s+\|\s+([\d.]+\s+\w+)/', $line, $matches)) {
                $date = $matches[1];
                
                if (!isset($dailyStats[$date])) {
                    $dailyStats[$date] = ['dl' => 0, 'ul' => 0, 't' => 0];
                }

                $dl = parseTrafficToMB($matches[2]);
                $ul = parseTrafficToMB($matches[3]);

                $dailyStats[$date]['dl'] += $dl;
                $dailyStats[$date]['ul'] += $ul;
                $dailyStats[$date]['t']  += ($dl + $ul);
            }
        }
    }

    // Urutkan data harian (terbaru ke terlama)
    krsort($dailyStats);

    // 2. Agregasi Data Bulanan
    $monthlyStats = [];
    foreach ($dailyStats as $date => $val) {
        $month = substr($date, 0, 7); // Ambil YYYY-MM
        if (!isset($monthlyStats[$month])) {
            $monthlyStats[$month] = ['dl' => 0, 'ul' => 0, 't' => 0];
        }
        $monthlyStats[$month]['dl'] += $val['dl'];
        $monthlyStats[$month]['ul'] += $val['ul'];
        $monthlyStats[$month]['t']  += $val['t'];
    }

    // 3. Data untuk Chart (7 Hari Terakhir)
    $chartData = ['l' => [], 'd' => [], 'u' => []];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chartData['l'][] = date('d/m', strtotime($d));
        // Konversi MB ke GB untuk chart agar angka tidak terlalu besar
        $chartData['d'][] = isset($dailyStats[$d]) ? round($dailyStats[$d]['dl'] / 1024, 2) : 0;
        $chartData['u'][] = isset($dailyStats[$d]) ? round($dailyStats[$d]['ul'] / 1024, 2) : 0;
    }

    // 4. Fungsi Internal untuk Data Hourly (-h) dan 5-Menit (-5)
    function getGranularStats($flag, $regex, $binPath, $interfaces) {
        $result = [];
        foreach ($interfaces as $iface) {
            $output = shell_exec($binPath . "vnstat $flag -i " . escapeshellarg($iface) . " 2>&1");
            if (!$output) continue;

            foreach (explode("\n", $output) as $line) {
                if (preg_match($regex, $line, $matches)) {
                    $timeKey = $matches[1];
                    if (!isset($result[$timeKey])) {
                        $result[$timeKey] = ['dl' => 0, 'ul' => 0];
                    }
                    $result[$timeKey]['dl'] += parseTrafficToMB($matches[2]);
                    $result[$timeKey]['ul'] += parseTrafficToMB($matches[3]);
                }
            }
        }
        ksort($result);
        return [
            'l' => array_keys($result), 
            'd' => array_column($result, 'dl'), 
            'u' => array_column($result, 'ul')
        ];
    }

    // Regex untuk jam/menit (HH:MM)
    $regexTime = '/(\d{2}:\d{2})\s+([\d.]+\s+\w+)\s+\|\s+([\d.]+\s+\w+)/';
    $hourlyStats = getGranularStats('-h', $regexTime, $TERMUX_BIN_PATH, $interfaces);
    $realtimeStats = getGranularStats('-5', $regexTime, $TERMUX_BIN_PATH, $interfaces);

    // Kirim Response JSON
    echo json_encode([
        'd' => $dailyStats,
        'm' => $monthlyStats,
        'c' => $chartData,
        'h' => $hourlyStats,
        '5' => $realtimeStats,
        's' => [
            't'  => $dailyStats[date('Y-m-d')]['t'] ?? 0,              // Hari Ini
            'y'  => $dailyStats[date('Y-m-d', strtotime('-1 day'))]['t'] ?? 0, // Kemarin
            'tm' => $monthlyStats[date('Y-m')]['t'] ?? 0               // Bulan Ini
        ]
    ]);
    exit;
}

// --- FORM HANDLING (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Reset Database
    if (isset($_POST['rst'])) {
        if (file_exists($VNSTAT_DB_PATH)) unlink($VNSTAT_DB_PATH);
    }

    // 2. Start Service Manual
    if (isset($_POST['str'])) {
        shell_exec($TERMUX_BIN_PATH . "vnstatd -d");
        sleep(1);
    }

    // 3. Toggle Auto Start Service (Membuat Script Boot)
    if (isset($_POST['tgl'])) {
        if (file_exists($BOOT_SCRIPT)) {
            unlink($BOOT_SCRIPT);
        } else {
            $scriptContent = <<<'EOT'
#!/system/bin/sh
LOGFILE=/sdcard/vnstat.log

(
    echo "$(date): Menunggu sistem boot..." >> $LOGFILE
    until [ "$(getprop sys.boot_completed)" = "1" ]; do
        sleep 2
    done

    until [ -d "/data/data/com.termux/files" ]; do
        echo "$(date): Menunggu Termux..." >> $LOGFILE
        sleep 2
    done
    
    export PATH=/data/data/com.termux/files/usr/bin:/system/bin:$PATH
    export LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib
    export LD_PRELOAD=/data/data/com.termux/files/usr/lib/libtermux-exec.so
    export PREFIX=/data/data/com.termux/files/usr
    export HOME=/data/data/com.termux/files/home

    sleep 15
    
    echo "$(date): Mencoba menjalankan vnstatd..." >> $LOGFILE
    
    if pgrep vnstatd > /dev/null; then
        echo "$(date): vnstatd sudah berjalan" >> $LOGFILE
    else
        su -c "/data/data/com.termux/files/usr/bin/vnstatd -d"
        echo "$(date): vnstatd dijalankan" >> $LOGFILE
    fi
)&
EOT;
            file_put_contents($BOOT_SCRIPT, $scriptContent);
            chmod($BOOT_SCRIPT, 0755);
        }
    }
    
    // Refresh Halaman
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$isAutoStartEnabled = file_exists($BOOT_SCRIPT);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Monitor</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* CSS Variables untuk Tema */
        :root {
            --bg: #f8f9fa; --card: #fff; --txt: #2d3748; --sub: #718096; --bd: #e2e8f0;
            --pri: #fb8c00; --pri-soft: rgba(251, 140, 0, 0.1); --suc: #2dce89; --dang: #f5365c;
            --shd: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --txt: #e0e0e0; --sub: #a0a0a0; --bd: #2d2d2d;
                --pri: #ff9800; --shd: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            }
        }

        /* Reset & Layout */
        * { box-sizing: border-box; margin: 0; padding: 0; outline: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg); color: var(--txt);
            padding: 15px; max-width: 900px; margin: 0 auto; padding-bottom: 80px;
        }

        /* Header */
        header { text-align: center; margin-bottom: 20px; }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--pri); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .sub { font-size: 0.9rem; color: var(--sub); }

        /* Stats Cards */
        .g-stat { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .c-stat { background: var(--card); padding: 15px; border-radius: 12px; box-shadow: var(--shd); text-align: center; border: 1px solid var(--bd); }
        .v-stat { font-size: 1.2rem; font-weight: 800; display: block; margin-bottom: 5px; }
        .l-stat { font-size: 0.7rem; color: var(--sub); text-transform: uppercase; font-weight: 700; }
        .hl .v-stat { color: var(--pri); }

        /* Chart Area */
        .c-box { background: var(--card); border-radius: 12px; padding: 20px; box-shadow: var(--shd); margin-bottom: 20px; border: 1px solid var(--bd); }
        .tabs { display: flex; gap: 8px; margin-bottom: 15px; overflow-x: auto; }
        .tab { background: 0 0; border: 1px solid var(--bd); color: var(--sub); padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: 0.2s; white-space: nowrap; }
        .tab.act { background: var(--pri); color: #fff; border-color: var(--pri); }
        .cvs { position: relative; height: 250px; width: 100%; }

        /* Tables */
        .tbl { background: var(--card); border-radius: 12px; box-shadow: var(--shd); overflow: hidden; margin-bottom: 20px; border: 1px solid var(--bd); }
        .thd { padding: 12px 15px; font-weight: 700; border-bottom: 1px solid var(--bd); font-size: 0.95rem; }
        .trsp { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 10px 15px; text-align: left; border-bottom: 1px solid var(--bd); }
        th { color: var(--sub); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; }
        tr:last-child td { border: none; }
        .dl { color: var(--pri); font-weight: 600; }
        .ul { color: var(--suc); font-weight: 600; }

        /* Actions & Buttons */
        .acts { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn { padding: 14px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; color: #fff; width: 100%; transition: 0.2s; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn:active { transform: scale(0.98); opacity: 0.9; }
        .btn-s { background: var(--pri); }
        .btn-r { background: var(--dang); }
        .btn svg { width: 18px; height: 18px; fill: currentColor; }

        /* Toggle Switch */
        .tgl { grid-column: 1/-1; display: flex; justify-content: space-between; align-items: center; background: var(--card); padding: 15px; border-radius: 12px; border: 1px solid var(--bd); box-shadow: var(--shd); }
        .tl { font-weight: 700; font-size: 0.95rem; }
        .sw { position: relative; display: inline-block; width: 46px; height: 26px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--bd); transition: .4s; border-radius: 34px; }
        .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: #fff; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); }
        input:checked + .sl { background-color: var(--pri); }
        input:checked + .sl:before { transform: translateX(20px); }

        /* Skeleton Loading Animation */
        .sk { background: linear-gradient(90deg, var(--bd) 25%, var(--bg) 50%, var(--bd) 75%); background-size: 200% 100%; animation: ld 1.5s infinite; color: transparent !important; border-radius: 4px; display: inline-block; }
        @keyframes ld { 0% { background-position: 200% 0 } 100% { background-position: -200% 0 } }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>Network Monitor</h1>
            <div class="sub">Total Traffic Usage</div>
        </header>

        <div class="g-stat">
            <div class="c-stat hl">
                <span class="v-stat" id="stat_month"><span class="sk">...</span></span>
                <span class="l-stat">This Month</span>
            </div>
            <div class="c-stat">
                <span class="v-stat" id="stat_today"><span class="sk">...</span></span>
                <span class="l-stat">Today</span>
            </div>
            <div class="c-stat">
                <span class="v-stat" id="stat_yesterday"><span class="sk">...</span></span>
                <span class="l-stat">Yesterday</span>
            </div>
        </div>

        <div class="c-box">
            <div class="tabs">
                <button class="tab act" onclick="changeChart('c')">Daily</button>
                <button class="tab" onclick="changeChart('h')">Hourly</button>
                <button class="tab" onclick="changeChart('5')">Realtime</button>
            </div>
            <div class="cvs"><canvas id="trafficChart"></canvas></div>
        </div>

        <div class="tbl">
            <div class="thd">Daily Log</div>
            <div class="trsp">
                <table id="table_daily">
                    <thead><tr><th>Date</th><th>Down</th><th>Up</th><th>Total</th></tr></thead>
                    <tbody><tr><td colspan="4" align="center">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="tbl">
            <div class="thd">Monthly Log</div>
            <div class="trsp">
                <table id="table_monthly">
                    <thead><tr><th>Month</th><th>Down</th><th>Up</th><th>Total</th></tr></thead>
                    <tbody><tr><td colspan="4" align="center">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="acts">
            <div class="tgl">
                <span class="tl">Auto Start Service</span>
                <form method="post" id="form_autostart">
                    <input type="hidden" name="tgl" value="1">
                    <label class="sw">
                        <input type="checkbox" onchange="document.getElementById('form_autostart').submit()" <?php echo $isAutoStartEnabled ? 'checked' : ''; ?>>
                        <span class="sl"></span>
                    </label>
                </form>
            </div>

            <form method="post" onsubmit="return confirm('Reset Data?')" style="width:100%">
                <input type="hidden" name="rst" value="1">
                <button class="btn btn-r">
                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg> 
                    Reset Data
                </button>
            </form>

            <form method="post" style="width:100%">
                <input type="hidden" name="str" value="1">
                <button class="btn btn-s">
                    <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg> 
                    Start Service
                </button>
            </form>
        </div>
    </div>

    <script>
        let chartData = {}, myChart;
        const ctx = document.getElementById('trafficChart').getContext('2d');

        // Helper: Format Bytes
        function formatBytes(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' TB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' GB';
            return bytes.toFixed(2) + ' MB';
        }

        // Initialize Data
        function initData() {
            fetch('?api=get_stats')
                .then(r => r.json())
                .then(data => {
                    // Update Summary
                    document.getElementById('stat_month').innerText = formatBytes(data.s.tm);
                    document.getElementById('stat_today').innerText = formatBytes(data.s.t);
                    document.getElementById('stat_yesterday').innerText = formatBytes(data.s.y);

                    // Update Tables
                    const renderTable = (tableId, dataset) => {
                        let html = '', count = 0;
                        for (let key in dataset) {
                            if (count++ >= 5) break; // Limit 5 rows
                            html += `<tr>
                                <td>${key}</td>
                                <td class="dl">↓ ${formatBytes(dataset[key].dl)}</td>
                                <td class="ul">↑ ${formatBytes(dataset[key].ul)}</td>
                                <td><b>${formatBytes(dataset[key].t)}</b></td>
                            </tr>`;
                        }
                        document.getElementById(tableId).querySelector('tbody').innerHTML = html || '<tr><td colspan="4" align="center">No Data</td></tr>';
                    };

                    renderTable('table_daily', data.d);
                    renderTable('table_monthly', data.m);

                    // Store Chart Data
                    chartData = {
                        c: data.c, // Daily
                        h: data.h, // Hourly
                        5: data['5'] // Realtime (5 min)
                    };
                    
                    // Render default chart
                    drawChart('c');
                });
        }

        // Draw Chart
        function drawChart(type) {
            if (myChart) myChart.destroy();
            const data = chartData[type];
            if (!data) return;

            // Simple scaling function if needed, currently raw data
            const scale = v => type === 'c' ? v : v; 

            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.l,
                    datasets: [
                        {
                            label: 'Download',
                            data: data.d.map(scale),
                            borderColor: '#fb8c00',
                            backgroundColor: 'rgba(251,140,0,0.1)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 0
                        },
                        {
                            label: 'Upload',
                            data: data.u.map(scale),
                            borderColor: '#2dce89',
                            backgroundColor: 'rgba(45,206,137,0.1)',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 10 } }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }
                    }
                }
            });
        }

        // Handle Tab Change
        function changeChart(type) {
            document.querySelectorAll('.tab').forEach(b => b.classList.remove('act'));
            event.target.classList.add('act');
            drawChart(type);
        }

        // Start
        initData();
    </script>
</body>
</html>