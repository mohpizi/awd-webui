<?php
// --- BACKEND LOGIC ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $a = $_GET['action'];
    
    if ($a === 'get_data') {
        $i = [];
        // 1. MEMORY INFO
        $m = @file_get_contents('/proc/meminfo');
        if ($m) {
            $l = explode("\n", $m);
            $d = [];
            foreach ($l as $r) {
                if (empty($r)) continue;
                $p = explode(':', $r);
                $d[trim($p[0])] = (int)preg_replace('/\D/', '', $p[1]);
            }
            $i['t'] = round($d['MemTotal']/1024, 2);
            $i['f'] = round($d['MemFree']/1024, 2);
            $i['a'] = round($d['MemAvailable']/1024, 2);
            $i['c'] = round($d['Cached']/1024, 2);
            $i['st'] = round($d['SwapTotal']/1024, 2);
            $i['su'] = $i['st'] - round($d['SwapFree']/1024, 2);
            // Kalkulasi Real Used
            $i['u'] = $i['t'] - $i['a'];
            $i['up'] = ($i['t']>0) ? round(($i['u']/$i['t'])*100, 1) : 0;
            $i['sup'] = ($i['st']>0) ? round(($i['su']/$i['st'])*100, 1) : 0;
        }

        // 2. PROCESS LIST (Top 50 by RAM)
        $p = [];
        // Mengambil PID, USER, %MEM, %CPU, COMMAND
        $o = shell_exec('ps -eo pid,user,%mem,%cpu,comm --sort=-%mem | head -n 50');
        if ($o) {
            $l = explode("\n", trim($o)); array_shift($l); // Hapus header
            foreach ($l as $r) {
                $x = preg_split('/\s+/', trim($r));
                // Pastikan array lengkap
                if (count($x) >= 5) {
                    $p[] = [
                        'pid' => $x[0],
                        'user' => $x[1],
                        'mem' => $x[2],
                        'cpu' => $x[3],
                        'name' => basename($x[4]) // Nama proses
                    ];
                }
            }
        }
        $i['proc'] = $p;
        echo json_encode($i);
    } 
    elseif ($a === 'clean') {
        shell_exec('sync; echo 3 > /proc/sys/vm/drop_caches; sysctl vm.drop_caches=3; swapoff -a && swapon -a 2>&1');
        echo json_encode(['ok'=>true]);
    } 
    elseif ($a === 'kill' && isset($_GET['pid'])) {
        shell_exec("kill -9 ".(int)$_GET['pid']." 2>&1");
        echo json_encode(['ok'=>true]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitor</title>
    <style>
        /* --- CSS VARIABLES (TEMA TETAP SAMA) --- */
        :root {
            --bg: #f7fafc; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-dim: rgba(251, 140, 0, 0.1); 
            --suc: #2dce89; --dang: #f5365c; --warn: #fb6340;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-dim: rgba(255, 152, 0, 0.15);
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.3);
            }
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 1000px; margin: 0 auto; }

        /* HEADER */
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        h1 { font-size: 1.5rem; font-weight: 800; color: var(--pri); margin: 0; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px; }
        .badge { font-size: 0.75rem; background: var(--pri-dim); color: var(--pri); padding: 4px 10px; border-radius: 20px; font-weight: 700; }
        .last-up { font-size: 0.8rem; color: var(--sub); font-family: monospace; }

        /* DASHBOARD GRID */
        .dashboard { display: grid; grid-template-columns: 280px 1fr; gap: 20px; margin-bottom: 30px; }
        .card { background: var(--card); border-radius: 16px; padding: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); }
        
        /* CIRCULAR PROGRESS */
        .gauge-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; }
        .gauge-svg { transform: rotate(-90deg); width: 160px; height: 160px; }
        .gauge-bg { fill: none; stroke: var(--border); stroke-width: 10; }
        .gauge-val { fill: none; stroke: var(--pri); stroke-width: 10; stroke-dasharray: 440; stroke-dashoffset: 440; transition: 1s ease-in-out; stroke-linecap: round; }
        .gauge-text { position: absolute; text-align: center; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .gt-val { font-size: 2rem; font-weight: 800; color: var(--text); display: block; }
        .gt-lbl { font-size: 0.75rem; color: var(--sub); font-weight: 700; text-transform: uppercase; }

        /* STATS CARDS */
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .stat-box { background: var(--bg); border-radius: 12px; padding: 15px; border: 1px solid var(--border); }
        .sb-head { font-size: 0.7rem; text-transform: uppercase; color: var(--sub); font-weight: 700; margin-bottom: 5px; display: flex; justify-content: space-between; }
        .sb-val { font-size: 1.1rem; font-weight: 700; color: var(--text); font-family: monospace; }
        .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        
        /* PROCESS SECTION */
        .proc-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .search-box { position: relative; flex: 1; max-width: 300px; }
        .search-inp { width: 100%; padding: 10px 15px; padding-left: 35px; border-radius: 8px; border: 1px solid var(--border); background: var(--card); color: var(--text); font-size: 0.9rem; }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; opacity: 0.5; fill: var(--text); }
        
        .table-wrap { overflow-x: auto; max-height: 500px; overflow-y: auto; border-radius: 12px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; background: var(--card); }
        th { text-align: left; padding: 12px 15px; background: var(--bg); color: var(--sub); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border); }
        td { padding: 10px 15px; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: var(--text); }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: var(--bg); }
        
        .proc-name { font-weight: 600; display: block; }
        .proc-user { font-size: 0.75rem; color: var(--sub); }
        .tag { padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; font-family: monospace; }
        .tag-mem { background: rgba(251, 140, 0, 0.1); color: var(--pri); }
        .tag-cpu { background: rgba(45, 206, 137, 0.1); color: var(--suc); }

        /* BUTTONS */
        .btn-main { background: var(--pri); color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(251,140,0,0.2); }
        .btn-main:active { transform: scale(0.96); }
        .btn-kill { background: transparent; border: 1px solid var(--dang); color: var(--dang); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer; transition: 0.2s; font-weight: 600; }
        .btn-kill:hover { background: var(--dang); color: #fff; }

        @media (max-width: 768px) {
            .dashboard { grid-template-columns: 1fr; }
            .gauge-svg { width: 140px; height: 140px; }
            .proc-head { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            System Monitor <span class="badge">Live</span>
        </h1>
        <span class="last-up" id="time">Connecting...</span>
    </div>

    <div class="dashboard">
        <div class="card">
            <div class="gauge-wrapper">
                <div style="position:relative; width:160px; height:160px; margin-bottom:20px;">
                    <svg class="gauge-svg" viewBox="0 0 160 160">
                        <circle class="gauge-bg" cx="80" cy="80" r="70"></circle>
                        <circle class="gauge-val" cx="80" cy="80" r="70" id="bar"></circle>
                    </svg>
                    <div class="gauge-text">
                        <span class="gt-val" id="pct">0%</span>
                        <span class="gt-lbl">RAM Used</span>
                    </div>
                </div>
                <button class="btn-main" onclick="cleanRam()" style="width:100%">
                    🚀 Boost RAM
                </button>
            </div>
        </div>

        <div class="card">
            <div style="margin-bottom:15px; font-weight:700; color:var(--text); display:flex; gap:10px; align-items:center;">
                <span class="dot" style="background:var(--pri)"></span> Memory Details
            </div>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="sb-head">Total RAM</div>
                    <div class="sb-val" id="rt">-</div>
                </div>
                <div class="stat-box">
                    <div class="sb-head">Available</div>
                    <div class="sb-val" id="ra">-</div>
                </div>
                <div class="stat-box">
                    <div class="sb-head">Cached</div>
                    <div class="sb-val" id="rc">-</div>
                </div>
                <div class="stat-box">
                    <div class="sb-head">Swap Used <span style="font-size:0.6rem" id="spct"></span></div>
                    <div class="sb-val" id="su">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <div style="padding:20px; padding-bottom:10px;">
            <div class="proc-head">
                <h3 style="margin:0; color:var(--text);">Active Processes <span style="font-size:0.8rem; color:var(--sub); font-weight:400;">(Top 50)</span></h3>
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="search" class="search-inp" placeholder="Search process..." onkeyup="filterProc()">
                </div>
            </div>
        </div>
        
        <div class="table-wrap">
            <table id="procTable">
                <thead>
                    <tr>
                        <th width="40%">Application</th>
                        <th width="20%">RAM</th>
                        <th width="20%">CPU</th>
                        <th width="20%" style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody id="pl">
                    <tr><td colspan="4" style="text-align:center; color:var(--sub);">Loading data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

<script>
let procData = []; // Simpan data proses lokal untuk pencarian

function updateStats() {
    fetch('?action=get_data')
    .then(r => r.json())
    .then(d => {
        // Update Stats
        document.getElementById('rt').innerText = d.t + ' MB';
        document.getElementById('ra').innerText = d.a + ' MB';
        document.getElementById('rc').innerText = d.c + ' MB';
        document.getElementById('su').innerText = d.su + ' / ' + d.st + ' MB';
        document.getElementById('spct').innerText = '(' + d.sup + '%)';
        
        // Update Gauge
        const p = d.up;
        const offset = 440 - (440 * p / 100);
        const bar = document.getElementById('bar');
        bar.style.strokeDashoffset = offset;
        document.getElementById('pct').innerText = p + '%';
        
        // Warna Gauge dinamis
        if(p > 85) bar.style.stroke = 'var(--dang)';
        else if(p > 60) bar.style.stroke = 'var(--warn)';
        else bar.style.stroke = 'var(--pri)';

        // Simpan data proses
        procData = d.proc;
        renderTable(procData);

        document.getElementById('time').innerText = "Updated: " + new Date().toLocaleTimeString();
    })
    .catch(e => console.error("Error fetching data", e));
}

function renderTable(data) {
    const tbody = document.getElementById('pl');
    const filter = document.getElementById('search').value.toLowerCase();
    
    // Filter data berdasarkan pencarian
    const filtered = data.filter(item => 
        item.name.toLowerCase().includes(filter) || 
        item.user.toLowerCase().includes(filter) ||
        item.pid.toString().includes(filter)
    );

    if(filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">No process found</td></tr>';
        return;
    }

    let html = '';
    filtered.forEach(x => {
        html += `
        <tr>
            <td>
                <span class="proc-name">${x.name}</span>
                <span class="proc-user">User: ${x.user} | PID: ${x.pid}</span>
            </td>
            <td><span class="tag tag-mem">${x.mem}%</span></td>
            <td><span class="tag tag-cpu">${x.cpu}%</span></td>
            <td style="text-align:right">
                <button class="btn-kill" onclick="killProc(${x.pid}, '${x.name}')">Kill</button>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

// Fungsi Pencarian Cepat (Client Side)
function filterProc() {
    renderTable(procData);
}

function cleanRam() {
    if(confirm("Clear System Cache & Swap?")) {
        fetch('?action=clean').then(() => {
            alert('RAM Boosed Successfully!');
            updateStats();
        });
    }
}

function killProc(pid, name) {
    if(confirm("Force Stop process: " + name + " (PID: " + pid + ")?")) {
        fetch('?action=kill&pid=' + pid).then(() => {
            updateStats();
        });
    }
}

// Auto update setiap 3 detik
setInterval(updateStats, 3000);
updateStats();
</script>
</body>
</html>