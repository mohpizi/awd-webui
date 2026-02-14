<?php
if (isset($_GET['action']) && $_GET['action'] === 'get_data') {
    header('Content-Type: application/json');
    $i = [];

    // 1. CPU INFO
    $i['model'] = trim(shell_exec("getprop ro.soc.model")); 
    if (empty($i['model'])) $i['model'] = trim(shell_exec("cat /proc/cpuinfo | grep 'Hardware' | head -1 | cut -d ':' -f2"));
    if (empty($i['model'])) $i['model'] = 'Android Device';
    
    $i['cores'] = (int)shell_exec('nproc');
    $i['arch'] = trim(shell_exec("uname -m"));
    
    // 2. GOVERNOR
    $i['gov'] = trim(@file_get_contents("/sys/devices/system/cpu/cpu0/cpufreq/scaling_governor"));

    // 3. LOAD AVERAGE
    $l = @file_get_contents('/proc/loadavg');
    $i['load'] = $l ? explode(' ', $l) : ['0.00', '0.00', '0.00'];

    // 4. THERMAL
    $tz = glob('/sys/class/thermal/thermal_zone*/temp');
    $tm = [];
    foreach ($tz as $z) {
        $v = (int)@file_get_contents($z);
        if ($v > 10000 && $v < 100000) {
            $typePath = str_replace('temp', 'type', $z);
            $t = @file_get_contents($typePath);
            $name = trim($t);
            if(stripos($name, 'cpu') !== false || stripos($name, 'soc') !== false || stripos($name, 'bms') !== false) {
                 $tm[$name] = round($v / 1000, 1);
            }
        }
    }
    $i['temp'] = $tm;

    // 5. FREQUENCY PER CORE (Cur & Max)
    $fr = [];
    for ($n = 0; $n < $i['cores']; $n++) {
        $cur = (int)@file_get_contents("/sys/devices/system/cpu/cpu$n/cpufreq/scaling_cur_freq");
        $max = (int)@file_get_contents("/sys/devices/system/cpu/cpu$n/cpufreq/scaling_max_freq");
        if($max == 0) $max = 2000000; // Fallback
        
        $fr[] = [
            'id' => $n,
            'cur' => $cur,
            'max' => $max,
            'on'  => file_exists("/sys/devices/system/cpu/cpu$n/online") ? (int)@file_get_contents("/sys/devices/system/cpu/cpu$n/online") : 1
        ];
    }
    $i['freq'] = $fr;

    // 6. CPU STAT
    $stat = explode(' ', preg_replace('!\s+!', ' ', trim(shell_exec('head -n1 /proc/stat'))));
    $i['stat'] = array_slice($stat, 1, 7);

    echo json_encode($i);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPU Monitor</title>
    <style>
        :root {
            --bg: #f7fafc; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-dim: rgba(251, 140, 0, 0.1); 
            --suc: #2dce89; --dang: #f5365c; --warn: #fb6340;
            --bar-bg: #edf2f7;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-dim: rgba(255, 152, 0, 0.15);
                --bar-bg: #2d3748;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.3);
            }
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 1000px; margin: 0 auto; }

        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        h1 { font-size: 1.5rem; font-weight: 800; color: var(--pri); margin: 0; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px; }
        .badge { font-size: 0.75rem; background: var(--pri-dim); color: var(--pri); padding: 4px 10px; border-radius: 20px; font-weight: 700; }
        .last-up { font-size: 0.8rem; color: var(--sub); font-family: monospace; }

        .dashboard { display: grid; grid-template-columns: 280px 1fr; gap: 20px; margin-bottom: 20px; }
        .card { background: var(--card); border-radius: 16px; padding: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; flex-direction: column; }
        
        .gauge-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 200px; }
        .gauge-svg { transform: rotate(-90deg); width: 160px; height: 160px; }
        .gauge-bg { fill: none; stroke: var(--border); stroke-width: 10; }
        .gauge-val { fill: none; stroke: var(--pri); stroke-width: 10; stroke-dasharray: 440; stroke-dashoffset: 440; transition: 0.5s linear; stroke-linecap: round; }
        .gauge-text { position: absolute; text-align: center; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .gt-val { font-size: 2rem; font-weight: 800; color: var(--text); display: block; }
        .gt-lbl { font-size: 0.75rem; color: var(--sub); font-weight: 700; text-transform: uppercase; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; width: 100%; margin-bottom: 20px; }
        .info-item { padding-bottom: 8px; border-bottom: 1px solid var(--border); }
        .info-lbl { font-size: 0.7rem; color: var(--sub); text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 2px; }
        .info-val { font-size: 1rem; font-weight: 600; color: var(--text); }

        .load-title { font-size: 0.8rem; font-weight: 700; color: var(--sub); margin-bottom: 10px; text-transform: uppercase; }
        .load-flex { display: flex; gap: 10px; }
        .load-box { flex: 1; background: var(--bg); padding: 10px; border-radius: 10px; text-align: center; border: 1px solid var(--border); }
        .lb-val { font-size: 1.2rem; font-weight: 800; color: var(--text); display: block; }
        .lb-lbl { font-size: 0.7rem; color: var(--sub); font-weight: 700; }

        .sec-title { font-size: 1.1rem; font-weight: 700; margin: 30px 0 15px; color: var(--text); display: flex; align-items: center; justify-content: space-between; }
        .cores-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; } /* Min width increased slightly */
        .core-card { background: var(--card); padding: 15px; border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow); position: relative; overflow: hidden; }
        .core-head { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--sub); font-weight: 800; margin-bottom: 5px; }
        
        /* FREQ STYLES BARU */
        .freq-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
        .core-freq { font-size: 1rem; font-weight: 700; font-family: monospace; color: var(--pri); }
        .max-freq { font-size: 0.7rem; font-weight: 600; color: var(--sub); }
        
        .progress-bg { height: 6px; background: var(--bar-bg); border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--pri); width: 0%; transition: width 0.3s ease; border-radius: 3px; }
        
        .core-card.offline { opacity: 0.6; grayscale: 1; }
        .core-card.offline .core-freq { color: var(--sub); }

        .therm-flex { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .chip { background: var(--card); border: 1px solid var(--border); padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 6px; box-shadow: var(--shadow); }
        .temp-ok { color: var(--suc); }
        .temp-warm { color: var(--warn); }
        .temp-hot { color: var(--dang); }

        @media (max-width: 768px) {
            .dashboard { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
            CPU Monitor <span class="badge">Live</span>
        </h1>
        <span class="last-up" id="time">Connecting...</span>
    </div>

    <div class="dashboard">
        <div class="card">
            <div class="gauge-wrapper">
                <div style="position:relative; width:160px; height:160px;">
                    <svg class="gauge-svg" viewBox="0 0 160 160">
                        <circle class="gauge-bg" cx="80" cy="80" r="70"></circle>
                        <circle class="gauge-val" cx="80" cy="80" r="70" id="bar"></circle>
                    </svg>
                    <div class="gauge-text">
                        <span class="gt-val" id="cpu-pct">0%</span>
                        <span class="gt-lbl">Total Load</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-lbl">Hardware Model</span>
                    <span class="info-val" id="md">...</span>
                </div>
                <div class="info-item">
                    <span class="info-lbl">Architecture</span>
                    <span class="info-val" id="ar">...</span>
                </div>
                <div class="info-item">
                    <span class="info-lbl">Total Cores</span>
                    <span class="info-val" id="cr">...</span>
                </div>
                <div class="info-item">
                    <span class="info-lbl">Governor</span>
                    <span class="info-val" id="gv">...</span>
                </div>
            </div>

            <div class="load-title">System Load Average</div>
            <div class="load-flex">
                <div class="load-box">
                    <span class="lb-val" id="l1">-</span>
                    <span class="lb-lbl">1 MIN</span>
                </div>
                <div class="load-box">
                    <span class="lb-val" id="l5">-</span>
                    <span class="lb-lbl">5 MIN</span>
                </div>
                <div class="load-box">
                    <span class="lb-val" id="l15">-</span>
                    <span class="lb-lbl">15 MIN</span>
                </div>
            </div>
        </div>
    </div>

    <div class="sec-title">Thermal Zones</div>
    <div class="therm-flex" id="therm-list">
        <div class="chip">Loading sensors...</div>
    </div>

    <div class="sec-title">Core Frequencies</div>
    <div class="cores-grid" id="core-list"></div>

<script>
let prevTotal = 0;
let prevIdle = 0;

function updateStats() {
    fetch('?action=get_data').then(r => r.json()).then(d => {
        document.getElementById('md').innerText = d.model;
        document.getElementById('ar').innerText = d.arch;
        document.getElementById('cr').innerText = d.cores + ' Cores';
        document.getElementById('gv').innerText = d.gov;

        document.getElementById('l1').innerText = d.load[0];
        document.getElementById('l5').innerText = d.load[1];
        document.getElementById('l15').innerText = d.load[2];

        // Calc Total Load
        const s = d.stat.map(Number);
        const currentTotal = s.reduce((a, b) => a + b, 0);
        const currentIdle = s[3] + s[4]; 

        let percent = 0;
        if (prevTotal > 0) {
            const diffTotal = currentTotal - prevTotal;
            const diffIdle = currentIdle - prevIdle;
            percent = ((diffTotal - diffIdle) / diffTotal) * 100;
        }
        prevTotal = currentTotal;
        prevIdle = currentIdle;

        const p = Math.max(0, Math.min(100, percent.toFixed(1)));
        const offset = 440 - (440 * p / 100);
        const bar = document.getElementById('bar');
        bar.style.strokeDashoffset = offset;
        document.getElementById('cpu-pct').innerText = p + '%';
        
        if(p > 90) bar.style.stroke = 'var(--dang)';
        else if(p > 70) bar.style.stroke = 'var(--warn)';
        else bar.style.stroke = 'var(--pri)';

        // Thermal
        let thHtml = '';
        for (const [key, val] of Object.entries(d.temp)) {
            let colClass = 'temp-ok';
            if(val > 60) colClass = 'temp-warm';
            if(val > 75) colClass = 'temp-hot';
            thHtml += `<div class="chip">${key.toUpperCase()} <span class="chip-val ${colClass}">${val}°C</span></div>`;
        }
        if(thHtml === '') thHtml = '<div class="chip">No sensors found</div>';
        document.getElementById('therm-list').innerHTML = thHtml;

        // Cores
        let coreHtml = '';
        d.freq.forEach(c => {
            const mhz = (c.cur / 1000).toFixed(0);
            const maxMhz = (c.max / 1000).toFixed(0); // Hitung Max MHz
            const pct = (c.cur / c.max) * 100;
            const isOff = c.on === 0;
            const statusClass = isOff ? 'offline' : '';
            const statusText = isOff ? 'OFFLINE' : mhz + ' MHz';
            const maxText = isOff ? '' : maxMhz + ' MHz'; // Teks Max
            
            let barCol = 'var(--pri)';
            if(pct > 90) barCol = 'var(--dang)';

            coreHtml += `
            <div class="core-card ${statusClass}">
                <div class="core-head">
                    <span>CPU ${c.id}</span>
                    <span>${isOff ? 'ZZZ' : pct.toFixed(0)+'%'}</span>
                </div>
                
                <div class="freq-row">
                    <span class="core-freq">${statusText}</span>
                    <span class="max-freq">${maxText}</span>
                </div>
                
                <div class="progress-bg">
                    <div class="progress-fill" style="width:${isOff ? 0 : pct}%; background:${barCol}"></div>
                </div>
            </div>`;
        });
        document.getElementById('core-list').innerHTML = coreHtml;

        document.getElementById('time').innerText = "Updated: " + new Date().toLocaleTimeString();
    }).catch(e => console.log(e));
}

setInterval(updateStats, 2000);
updateStats();
</script>
</body>
</html>