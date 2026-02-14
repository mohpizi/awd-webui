<?php
if (isset($_GET['action']) && $_GET['action'] === 'get_data') {
    header('Content-Type: application/json');

    function read_sys($f) { 
        return file_exists($f) ? trim(file_get_contents($f)) : 'N/A'; 
    }
    
    function read_int($f) { 
        return file_exists($f) ? (int)trim(file_get_contents($f)) : 0; 
    }

    $base = '/sys/class/power_supply/battery/';
    
    $data = [];
    
    // 1. Basic Info
    $data['capacity'] = read_int($base.'capacity');
    $data['status']   = read_sys($base.'status'); // Charging, Discharging, Full
    $data['health']   = read_sys($base.'health');
    $data['tech']     = read_sys($base.'technology');
    
    // 2. Power Metrics
    $data['voltage']  = round(read_int($base.'voltage_now') / 1000000, 2);
    
    // Ambil nilai mentah arus
    $currentRaw = read_int($base.'current_now');
    if($currentRaw == 0) $currentRaw = read_int($base.'batt_current');
    
    // Konversi ke mA (absolut dulu biar aman)
    $currentAbs = abs(round($currentRaw / 1000, 0));

    // LOGIKA BARU: Tentukan tanda positif/negatif berdasarkan status
    if (stripos($data['status'], 'Charging') !== false && stripos($data['status'], 'Dis') === false) {
        // Sedang Charging -> Positif
        $data['current'] = $currentAbs;
    } else {
        // Sedang Discharging / Full / Not Charging -> Negatif
        $data['current'] = -1 * $currentAbs;
    }
    
    // Wattage (P = V * I) -> Selalu positif untuk besaran daya
    $data['wattage']  = round($data['voltage'] * ($currentAbs / 1000), 2);

    // 3. Temperature
    $data['temp']     = round(read_int($base.'temp') / 10, 1);

    // 4. Health
    $charge_full = read_int($base.'charge_full');
    $design      = read_int($base.'charge_full_design');
    
    if ($charge_full > 100000) $charge_full /= 1000;
    if ($design > 100000) $design /= 1000;

    $data['cap_full']   = round($charge_full);
    $data['cap_design'] = round($design);
    $data['cycle']      = read_sys($base.'cycle_count');
    $data['health_pct'] = ($data['cap_design'] > 0) ? round(($data['cap_full'] / $data['cap_design']) * 100) : 0;

    echo json_encode($data);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Battery Monitor</title>
    <style>
        /* --- TEMA YANG SAMA --- */
        :root {
            --bg: #f7fafc; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-dim: rgba(251, 140, 0, 0.1); 
            --suc: #2dce89; --dang: #f5365c; --warn: #fb6340; --info: #11cdef;
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

        /* HEADER */
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        h1 { font-size: 1.5rem; font-weight: 800; color: var(--pri); margin: 0; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px; }
        .badge { font-size: 0.75rem; background: var(--pri-dim); color: var(--pri); padding: 4px 10px; border-radius: 20px; font-weight: 700; }
        .last-up { font-size: 0.8rem; color: var(--sub); font-family: monospace; }

        /* DASHBOARD */
        .dashboard { display: grid; grid-template-columns: 280px 1fr; gap: 20px; margin-bottom: 20px; }
        .card { background: var(--card); border-radius: 16px; padding: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; flex-direction: column; }

        /* GAUGE */
        .gauge-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 200px; }
        .gauge-svg { transform: rotate(-90deg); width: 160px; height: 160px; }
        .gauge-bg { fill: none; stroke: var(--border); stroke-width: 10; }
        .gauge-val { fill: none; stroke: var(--pri); stroke-width: 10; stroke-dasharray: 440; stroke-dashoffset: 440; transition: 1s cubic-bezier(0.4, 0, 0.2, 1); stroke-linecap: round; }
        .gauge-text { position: absolute; text-align: center; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .gt-val { font-size: 2.5rem; font-weight: 800; color: var(--text); display: block; line-height: 1; }
        .gt-lbl { font-size: 0.8rem; color: var(--sub); font-weight: 700; text-transform: uppercase; margin-top: 5px; }
        
        .status-pill { margin-top: 15px; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; background: var(--bar-bg); color: var(--sub); display: inline-flex; align-items: center; gap: 6px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .charging { color: var(--suc); background: rgba(45, 206, 137, 0.15); }
        .discharging { color: var(--warn); background: rgba(251, 99, 64, 0.15); }

        /* INFO GRID */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .info-box { background: var(--bg); padding: 15px; border-radius: 12px; border: 1px solid var(--border); position: relative; overflow: hidden; }
        .ib-icon { position: absolute; right: 10px; top: 10px; width: 24px; height: 24px; opacity: 0.1; color: var(--text); }
        .ib-val { font-size: 1.3rem; font-weight: 800; color: var(--text); display: block; }
        .ib-lbl { font-size: 0.75rem; color: var(--sub); font-weight: 700; text-transform: uppercase; margin-top: 4px; display: block; }

        /* HEALTH SECTION */
        .sec-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 15px; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .health-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--border); }
        .health-row:last-child { border-bottom: none; }
        .hr-lbl { font-size: 0.9rem; color: var(--sub); }
        .hr-val { font-weight: 700; color: var(--text); font-family: monospace; font-size: 1rem; }

        .progress-bg { height: 8px; background: var(--bar-bg); border-radius: 4px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; background: var(--info); width: 0%; border-radius: 4px; transition: width 1s ease; }

        /* Alert Text */
        .t-hot { color: var(--dang) !important; }
        .t-warm { color: var(--warn) !important; }

        @media (max-width: 768px) {
            .dashboard { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="16" height="10" rx="2" ry="2"></rect><line x1="22" y1="11" x2="22" y2="13"></line></svg>
            Battery <span class="badge">Live</span>
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
                        <span class="gt-val" id="cap">0%</span>
                        <div class="status-pill" id="st-pill">
                            <div class="dot"></div> <span id="status">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="sec-title">Power Metrics</div>
            <div class="info-grid">
                <div class="info-box">
                    <span class="ib-val" id="vol">0 V</span>
                    <span class="ib-lbl">Voltage</span>
                    <svg class="ib-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div class="info-box">
                    <span class="ib-val" id="cur">0 mA</span>
                    <span class="ib-lbl">Current</span>
                    <svg class="ib-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                </div>
                <div class="info-box">
                    <span class="ib-val" id="tmp">0°C</span>
                    <span class="ib-lbl">Temperature</span>
                    <svg class="ib-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"></path></svg>
                </div>
                <div class="info-box">
                    <span class="ib-val" id="watt">0 W</span>
                    <span class="ib-lbl">Wattage</span>
                    <svg class="ib-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
            </div>
        </div>

        <div class="card" style="grid-column: 1 / -1;">
            <div class="sec-title">Health & Capacity</div>
            <div style="display: flex; flex-wrap: wrap; gap: 40px;">
                <div style="flex:1; min-width: 250px;">
                    <div class="health-row">
                        <span class="hr-lbl">Health Status</span>
                        <span class="hr-val" id="hlt" style="color:var(--suc)">Good</span>
                    </div>
                    <div class="health-row">
                        <span class="hr-lbl">Technology</span>
                        <span class="hr-val" id="tech">Li-poly</span>
                    </div>
                    <div class="health-row">
                        <span class="hr-lbl">Cycle Count</span>
                        <span class="hr-val" id="cyc">-</span>
                    </div>
                </div>
                
                <div style="flex:1; min-width: 250px;">
                    <div class="health-row">
                        <span class="hr-lbl">Actual Capacity</span>
                        <span class="hr-val" id="cap-real">0 mAh</span>
                    </div>
                    <div class="health-row">
                        <span class="hr-lbl">Design Capacity</span>
                        <span class="hr-val" id="cap-des">0 mAh</span>
                    </div>
                    
                    <div style="margin-top:15px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--sub); font-weight:700; text-transform:uppercase;">
                            <span>Wear Level</span>
                            <span id="health-pct-txt">100%</span>
                        </div>
                        <div class="progress-bg">
                            <div class="progress-fill" id="health-bar" style="width:100%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
function updateStats() {
    fetch('?action=get_data').then(r => r.json()).then(d => {
        // 1. GAUGE
        const p = d.capacity;
        const offset = 440 - (440 * p / 100);
        const bar = document.getElementById('bar');
        bar.style.strokeDashoffset = offset;
        document.getElementById('cap').innerText = p + '%';
        
        if(p <= 15) bar.style.stroke = 'var(--dang)';
        else if(p <= 30) bar.style.stroke = 'var(--warn)';
        else bar.style.stroke = 'var(--pri)';

        // 2. STATUS
        const pill = document.getElementById('st-pill');
        const stText = document.getElementById('status');
        stText.innerText = d.status;
        
        // Logika Charging Check
        if(d.status.toLowerCase().includes('charging') && !d.status.toLowerCase().includes('dis')) {
            pill.className = 'status-pill charging';
        } else {
            pill.className = 'status-pill discharging';
        }

        // 3. POWER
        document.getElementById('vol').innerText = d.voltage + ' V';
        
        // Format Current (+ / -)
        const curr = d.current;
        const sign = curr > 0 ? '+' : ''; 
        document.getElementById('cur').innerText = sign + curr + ' mA';
        
        // Warna teks current: Hijau jika Charging, Biasa jika Discharging
        document.getElementById('cur').style.color = curr > 0 ? 'var(--suc)' : 'var(--text)';

        // Temp
        const t = d.temp;
        const tEl = document.getElementById('tmp');
        tEl.innerText = t + '°C';
        tEl.className = 'ib-val';
        if(t > 40) tEl.classList.add('t-warm');
        if(t > 45) tEl.classList.add('t-hot');

        document.getElementById('watt').innerText = d.wattage + ' W';

        // 4. HEALTH
        document.getElementById('hlt').innerText = d.health;
        document.getElementById('tech').innerText = d.tech;
        document.getElementById('cyc').innerText = d.cycle != 'N/A' ? d.cycle : 'Unknown';
        document.getElementById('cap-real').innerText = d.cap_full + ' mAh';
        document.getElementById('cap-des').innerText = d.cap_design + ' mAh';
        
        if(d.health_pct > 0) {
            document.getElementById('health-pct-txt').innerText = d.health_pct + '% Health';
            document.getElementById('health-bar').style.width = d.health_pct + '%';
            const hb = document.getElementById('health-bar');
            if(d.health_pct < 60) hb.style.background = 'var(--dang)';
            else if(d.health_pct < 80) hb.style.background = 'var(--warn)';
            else hb.style.background = 'var(--info)';
        }

        document.getElementById('time').innerText = "Updated: " + new Date().toLocaleTimeString();
    }).catch(e => console.log(e));
}

setInterval(updateStats, 3000);
updateStats();
</script>
</body>
</html>
