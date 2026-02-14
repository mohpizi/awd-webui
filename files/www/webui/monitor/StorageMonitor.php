<?php
// --- BACKEND PHP ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $act = $_GET['action'];

    // Helper: Format Bytes
    function fmt($bytes) {
        $bytes = (float)$bytes;
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes/pow(1024, $i), 2).' '.$units[$i];
    }

    // Helper: Parse DF command
    function get_df($path) {
        $raw = shell_exec("df $path 2>/dev/null | tail -n1");
        // Output DF: Filesystem 1K-blocks Used Available Use% Mounted on
        $s = preg_split('/\s+/', trim($raw));
        if (count($s) >= 6) {
            $total = (float)$s[1] * 1024;
            $used  = (float)$s[2] * 1024;
            $free  = (float)$s[3] * 1024;
            $pct   = (int)str_replace('%', '', $s[4]);
            return [
                'path' => $path,
                'total_raw' => $total, // Untuk kalkulasi grafik utama
                'used_raw' => $used,
                'total' => fmt($total),
                'used' => fmt($used),
                'free' => fmt($free),
                'pct' => $pct
            ];
        }
        return null;
    }

    if ($act === 'get_data') {
        $data = [];
        
        // 1. PARTITIONS
        // Prioritas: /data adalah penyimpanan internal user
        $data['main'] = get_df('/data'); 
        
        $parts = [];
        $list = [
            'System' => '/system',
            'Internal' => '/sdcard',
            'Root' => '/'
        ];

        foreach($list as $name => $path) {
            $info = get_df($path);
            if($info) {
                $info['label'] = $name;
                $parts[] = $info;
            }
        }
        $data['partitions'] = $parts;

        // 2. CACHE CALCULATION
        // Note: Menghitung /data/data/*/cache bisa berat, kita pakai du -s summary
        $cachePaths = [
            'Dalvik' => '/data/dalvik-cache', 
            'App Cache' => '/data/data/*/cache', 
            'Temp' => '/data/local/tmp'
        ];
        
        $totalCache = 0;
        $cacheDetails = [];
        
        foreach ($cachePaths as $name => $path) {
            // Menggunakan timeout agar tidak hang jika terlalu lama
            $sizeKB = (int)shell_exec("timeout 1s du -sk $path 2>/dev/null | awk '{print $1}'");
            $bytes = $sizeKB * 1024;
            $cacheDetails[] = ['name' => $name, 'size' => fmt($bytes)];
            $totalCache += $bytes;
        }
        
        $data['cache'] = [
            'total_fmt' => fmt($totalCache),
            'total_raw' => $totalCache,
            'details' => $cacheDetails
        ];

        echo json_encode($data);
    } 
    elseif ($act === 'clean_cache') {
        // Command pembersih (Memerlukan Root)
        shell_exec('rm -rf /data/dalvik-cache/* /data/data/*/cache/* /data/local/tmp/* 2>&1');
        echo json_encode(['status' => 'success']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Monitor</title>
    <style>
        /* --- CSS VARIABLES (SAMA DENGAN SEBELUMNYA) --- */
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

        /* HEADER */
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--border); }
        h1 { font-size: 1.5rem; font-weight: 800; color: var(--pri); margin: 0; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px; }
        .badge { font-size: 0.75rem; background: var(--pri-dim); color: var(--pri); padding: 4px 10px; border-radius: 20px; font-weight: 700; }
        .last-up { font-size: 0.8rem; color: var(--sub); font-family: monospace; }

        /* DASHBOARD LAYOUT */
        .dashboard { display: grid; grid-template-columns: 280px 1fr; gap: 20px; margin-bottom: 20px; }
        .card { background: var(--card); border-radius: 16px; padding: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; flex-direction: column; }

        /* GAUGE (LINGKARAN) */
        .gauge-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 200px; }
        .gauge-svg { transform: rotate(-90deg); width: 160px; height: 160px; }
        .gauge-bg { fill: none; stroke: var(--border); stroke-width: 10; }
        .gauge-val { fill: none; stroke: var(--pri); stroke-width: 10; stroke-dasharray: 440; stroke-dashoffset: 440; transition: 1s cubic-bezier(0.4, 0, 0.2, 1); stroke-linecap: round; }
        .gauge-text { position: absolute; text-align: center; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .gt-val { font-size: 2rem; font-weight: 800; color: var(--text); display: block; }
        .gt-lbl { font-size: 0.75rem; color: var(--sub); font-weight: 700; text-transform: uppercase; }
        
        .main-stats { display: flex; justify-content: space-between; width: 100%; margin-top: 20px; text-align: center; }
        .ms-item { flex: 1; border-right: 1px solid var(--border); }
        .ms-item:last-child { border: none; }
        .ms-val { font-weight: 700; color: var(--text); display: block; }
        .ms-lbl { font-size: 0.7rem; color: var(--sub); text-transform: uppercase; }

        /* PARTITION LIST */
        .sec-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 15px; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .part-list { display: flex; flex-direction: column; gap: 15px; }
        .part-item { padding-bottom: 15px; border-bottom: 1px solid var(--border); }
        .part-item:last-child { border-bottom: none; padding-bottom: 0; }
        
        .part-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .part-name { font-weight: 700; font-size: 0.95rem; color: var(--text); display: flex; align-items: center; gap: 6px; }
        .part-path { font-size: 0.75rem; color: var(--sub); background: var(--bar-bg); padding: 2px 6px; border-radius: 4px; font-weight: normal; }
        .part-pct { font-weight: 800; font-size: 0.9rem; color: var(--pri); }
        
        .progress-bg { height: 8px; background: var(--bar-bg); border-radius: 4px; overflow: hidden; margin-bottom: 5px; }
        .progress-fill { height: 100%; background: var(--pri); width: 0%; transition: width 0.5s ease; border-radius: 4px; }
        
        .part-meta { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--sub); }
        .part-meta strong { color: var(--text); }

        /* CACHE / JUNK SECTION */
        .junk-card { background: var(--bg); border: 1px dashed var(--border); border-radius: 12px; padding: 15px; text-align: center; margin-bottom: 15px; }
        .junk-total { font-size: 2.5rem; font-weight: 800; color: var(--dang); line-height: 1; margin: 10px 0; }
        .junk-lbl { font-size: 0.8rem; text-transform: uppercase; color: var(--sub); font-weight: 700; letter-spacing: 1px; }
        
        .cache-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
        .cache-box { background: var(--bar-bg); padding: 10px; border-radius: 8px; text-align: center; }
        .cb-val { font-weight: 700; color: var(--text); font-size: 0.9rem; display: block; }
        .cb-lbl { font-size: 0.7rem; color: var(--sub); }

        .btn-clean { background: var(--dang); color: white; border: none; width: 100%; padding: 12px; border-radius: 50px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(245, 54, 92, 0.3); display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-clean:active { transform: scale(0.98); opacity: 0.9; }
        .btn-clean:hover { background: #e02e49; }

        @media (max-width: 768px) {
            .dashboard { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="head">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            Storage <span class="badge">Live</span>
        </h1>
        <span class="last-up" id="time">Connecting...</span>
    </div>

    <div class="dashboard">
        <div class="card">
            <div class="sec-title">Internal Storage (/data)</div>
            <div class="gauge-wrapper">
                <div style="position:relative; width:160px; height:160px; margin-bottom:20px;">
                    <svg class="gauge-svg" viewBox="0 0 160 160">
                        <circle class="gauge-bg" cx="80" cy="80" r="70"></circle>
                        <circle class="gauge-val" cx="80" cy="80" r="70" id="bar"></circle>
                    </svg>
                    <div class="gauge-text">
                        <span class="gt-val" id="main-pct">0%</span>
                        <span class="gt-lbl">Used</span>
                    </div>
                </div>
            </div>
            
            <div class="main-stats">
                <div class="ms-item">
                    <span class="ms-val" id="main-used">-</span>
                    <span class="ms-lbl">Used</span>
                </div>
                <div class="ms-item">
                    <span class="ms-val" id="main-free">-</span>
                    <span class="ms-lbl">Free</span>
                </div>
                <div class="ms-item">
                    <span class="ms-val" id="main-total">-</span>
                    <span class="ms-lbl">Total</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="sec-title">Partitions Details</div>
            <div class="part-list" id="part-list">
                <div style="text-align:center; padding:20px; color:var(--sub)">Loading partitions...</div>
            </div>
        </div>
        
        <div class="card" style="grid-column: 1 / -1;">
            <div class="sec-title" style="color:var(--dang)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                System Junk & Cache
            </div>
            
            <div style="display:flex; flex-wrap:wrap; gap:20px; align-items:center;">
                <div style="flex:1; min-width:200px;">
                    <div class="junk-card">
                        <span class="junk-lbl">Total Junk Found</span>
                        <div class="junk-total" id="junk-total">0 B</div>
                        <div class="cache-grid" id="cache-grid">
                            </div>
                    </div>
                </div>
                
                <div style="flex:1; min-width:200px; display:flex; flex-direction:column; justify-content:center;">
                    <p style="color:var(--sub); font-size:0.9rem; margin-bottom:15px; line-height:1.5;">
                        Membersihkan cache sistem dan aplikasi dapat membebaskan ruang penyimpanan, namun beberapa aplikasi mungkin akan memuat data sedikit lebih lama saat pertama kali dibuka kembali.
                    </p>
                    <button class="btn-clean" onclick="cleanCache()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                        Clean All Junk Files
                    </button>
                </div>
            </div>
        </div>
    </div>

<script>
function updateStats() {
    fetch('?action=get_data').then(r => r.json()).then(d => {
        // 1. UPDATE MAIN GAUGE (/data)
        if(d.main) {
            const p = d.main.pct;
            const offset = 440 - (440 * p / 100);
            const bar = document.getElementById('bar');
            bar.style.strokeDashoffset = offset;
            document.getElementById('main-pct').innerText = p + '%';
            
            // Warna Gauge
            if(p > 90) bar.style.stroke = 'var(--dang)';
            else if(p > 75) bar.style.stroke = 'var(--warn)';
            else bar.style.stroke = 'var(--pri)';

            document.getElementById('main-used').innerText = d.main.used;
            document.getElementById('main-free').innerText = d.main.free;
            document.getElementById('main-total').innerText = d.main.total;
        }

        // 2. UPDATE PARTITIONS LIST
        let ph = '';
        d.partitions.forEach(pt => {
            let col = 'var(--pri)';
            if(pt.pct > 75) col = 'var(--warn)';
            if(pt.pct > 90) col = 'var(--dang)';
            
            ph += `
            <div class="part-item">
                <div class="part-head">
                    <span class="part-name">${pt.label} <span class="part-path">${pt.path}</span></span>
                    <span class="part-pct" style="color:${col}">${pt.pct}%</span>
                </div>
                <div class="progress-bg">
                    <div class="progress-fill" style="width:${pt.pct}%; background:${col}"></div>
                </div>
                <div class="part-meta">
                    <span>Used: <strong>${pt.used}</strong></span>
                    <span>Free: <strong>${pt.free}</strong></span>
                </div>
            </div>`;
        });
        document.getElementById('part-list').innerHTML = ph;

        // 3. UPDATE CACHE
        document.getElementById('junk-total').innerText = d.cache.total_fmt;
        let ch = '';
        d.cache.details.forEach(c => {
            ch += `<div class="cache-box"><span class="cb-val">${c.size}</span><span class="cb-lbl">${c.name}</span></div>`;
        });
        document.getElementById('cache-grid').innerHTML = ch;

        document.getElementById('time').innerText = "Updated: " + new Date().toLocaleTimeString();
    }).catch(e => console.log(e));
}

function cleanCache() {
    if(confirm("Apakah anda yakin ingin menghapus Cache Sistem & Aplikasi?")) {
        fetch('?action=clean_cache').then(r => r.json()).then(d => {
            if(d.status === 'success') {
                alert('Cache berhasil dibersihkan!');
                updateStats();
            }
        });
    }
}

// Update setiap 5 detik (Storage jarang berubah cepat)
setInterval(updateStats, 5000);
updateStats();
</script>
</body>
</html>