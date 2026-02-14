<?php
function getSystemData($command, $default = 'N/A') {
    $output = @shell_exec($command);
    return trim($output) !== '' ? trim($output) : $default;
}

// 1. Ambil Data Raw
$signalInfo = getSystemData('dumpsys telephony.registry');
// 2. Ambil Nama Operator (Biasanya dipisah koma, cth: Telkomsel,Indosat)
$operatorRaw = getSystemData('getprop gsm.sim.operator.alpha');
$simNames = explode(',', $operatorRaw);

$signalData = [];

if (!empty($signalInfo)) {
    // 3. Gunakan preg_match_all untuk menangkap SEMUA blok sinyal LTE yang ada
    if (preg_match_all('/CellSignalStrengthLte:(.+?)(?=CellSignalStrength|$)/s', $signalInfo, $lteMatches)) {
        
        // Loop hasil temuan (Maksimal 2 untuk Dual SIM)
        foreach ($lteMatches[1] as $index => $lteData) {
            if ($index > 1) break; // Batasi hanya 2 SIM

            preg_match('/rssi\s*=\s*([-\d]+)/i', $lteData, $rssi);
            preg_match('/rsrp\s*=\s*([-\d]+)/i', $lteData, $rsrp);
            preg_match('/rsrq\s*=\s*([-\d]+)/i', $lteData, $rsrq);
            preg_match('/rssnr\s*=\s*([-\d]+)/i', $lteData, $rssnr);
            preg_match('/level\s*=\s*(\d)/i', $lteData, $level);

            // Validasi data sampah (Kadang Android menyimpan history sinyal kosong)
            $lvl = (int)($level[1] ?? 0);
            $val_rsrp = $rsrp[1] ?? 0;
            
            // Jika Level 0 dan RSRP aneh, kemungkinan data hantu/tidak aktif, skip
            if ($lvl == 0 && abs($val_rsrp) > 140) continue;

            $v_rsrq = (isset($rsrq[1]) && abs($rsrq[1]) < 1000) ? $rsrq[1] : 'N/A';
            $v_sinr = (isset($rssnr[1]) && abs($rssnr[1]) < 1000) ? $rssnr[1] : 'N/A';

            // Tentukan Nama SIM
            $providerName = isset($simNames[$index]) && !empty(trim($simNames[$index])) 
                            ? trim($simNames[$index]) 
                            : "SIM " . ($index + 1);

            $signalData[] = [
                'provider' => $providerName,
                'type' => 'LTE',
                'rssi' => $rssi[1] ?? 'N/A',
                'rsrp' => $rsrp[1] ?? 'N/A',
                'rsrq' => $v_rsrq,
                'sinr' => $v_sinr,
                'level' => $lvl,
            ];
        }
    }
}

// Fallback jika array kosong tapi ada nama operator (No Signal state)
if (empty($signalData) && !empty($operatorRaw)) {
    foreach($simNames as $nm) {
        if(empty(trim($nm))) continue;
        $signalData[] = [
            'provider' => $nm, 'type' => 'NO SIGNAL', 
            'rssi' => 'N/A', 'rsrp' => 'N/A', 'rsrq' => 'N/A', 'sinr' => 'N/A', 'level' => 0
        ];
    }
}

function getSignalColor($val, $type) {
    if ($val === 'N/A') return 'var(--sub)';
    $v = (int)$val;
    if ($type == 'rsrp') {
        if ($v > -80) return 'var(--suc)';
        if ($v > -95) return 'var(--suc)'; 
        if ($v > -105) return 'var(--pri)';
        return 'var(--dang)';
    }
    if ($type == 'sinr') {
        if ($v >= 20) return 'var(--suc)';
        if ($v >= 13) return 'var(--suc)';
        if ($v >= 0) return 'var(--pri)';
        return 'var(--dang)';
    }
    if ($type == 'rsrq') {
        if ($v >= -10) return 'var(--suc)';
        if ($v >= -15) return 'var(--pri)';
        return 'var(--dang)';
    }
    return 'var(--pri)';
}

function getWidth($val, $type) {
    if ($val === 'N/A') return '0%';
    $v = (int)$val;
    $p = 0;
    if ($type == 'rsrp') $p = ($v + 140) * (100/100);
    elseif ($type == 'rssi') $p = ($v + 113) * (100/62);
    elseif ($type == 'sinr') $p = ($v + 10) * (100/40);
    elseif ($type == 'rsrq') $p = ($v + 20) * (100/17);
    return max(5, min(100, $p)) . '%';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signal Monitor</title>
    <style>
        :root {
            --bg: #ffffff; --text: #2d3748; --sub: #718096; --border: #edf2f7;
            --pri: #fb8c00; --suc: #2dce89; --warn: #fb6340; --dang: #f5365c;
            --bar: #e2e8f0; --hov: #f7fafc; --head-bg: #f7fafc;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --suc: #68d391; --warn: #ffb74d; --dang: #fc8181;
                --bar: #374151; --hov: #1e1e1e; --head-bg: #1a1a1a;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: var(--bg); color: var(--text); padding: 20px; overflow-x: hidden; }
        
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        h1 { font-size: 1.5rem; font-weight: 800; color: var(--pri); margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .upd { font-size: 0.85rem; color: var(--sub); font-family: monospace; font-weight: 600; }

        .sec { margin-bottom: 30px; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .sec-hd { display: flex; align-items: center; justify-content: space-between; padding: 15px; background: var(--head-bg); border-bottom: 1px solid var(--border); }
        .prov { font-size: 1.1rem; font-weight: 800; color: var(--text); }
        .type-badge { font-size: 0.75rem; background: var(--pri); color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: bold; margin-left: 8px; }
        
        .bars { display: flex; align-items: flex-end; gap: 4px; height: 20px; }
        .b { width: 5px; border-radius: 2px; background-color: var(--bar); transition: 0.3s; }
        .b:nth-child(1) { height: 25%; } .b:nth-child(2) { height: 50%; } 
        .b:nth-child(3) { height: 75%; } .b:nth-child(4) { height: 100%; }
        .b.on { background-color: var(--pri); }

        .list { display: flex; flex-direction: column; padding: 15px; }
        .item { display: flex; flex-direction: column; padding: 10px 0; border-bottom: 1px dashed var(--border); }
        .item:last-child { border: none; padding-bottom: 0; }
        .item:first-child { padding-top: 0; }
        
        .info { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.9rem; font-weight: 600; }
        .lbl { color: var(--sub); text-transform: uppercase; font-size: 0.75rem; font-weight: 700; } 
        .val { font-family: monospace; font-size: 1rem; }
        
        .pb { height: 6px; background-color: var(--bar); border-radius: 10px; overflow: hidden; width: 100%; }
        .pf { height: 100%; border-radius: 10px; transition: width 0.5s ease; }
        
        .no-data { text-align: center; padding: 40px; color: var(--sub); font-style: italic; border: 1px dashed var(--border); border-radius: 12px; }

        @media (min-width: 768px) {
            .list { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .item { border-bottom: none; padding: 0; }
        }
    </style>
    <script>
        setInterval(() => location.reload(), 3000);
    </script>
</head>
<body>

    <div class="head">
        <h1>Signal Monitor</h1>
        <div class="upd"><?= date('H:i:s') ?></div>
    </div>

    <?php if (empty($signalData)): ?>
        <div class="no-data">Scanning for signals...<br><small>Pastikan koneksi Mobile Data aktif</small></div>
    <?php else: ?>
        <?php foreach ($signalData as $sig): ?>
            <div class="sec">
                <div class="sec-hd">
                    <div style="display:flex; align-items:center;">
                        <span class="prov"><?= htmlspecialchars($sig['provider']) ?></span>
                        <span class="type-badge"><?= $sig['type'] ?></span>
                    </div>
                    
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:0.8rem; font-weight:700; color:var(--sub)">Lvl <?= $sig['level'] ?></span>
                        <div class="bars">
                            <?php for($i=1; $i<=4; $i++): ?>
                                <div class="b <?= $i <= $sig['level'] ? 'on' : '' ?>"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="list">
                    <?php if($sig['rsrp'] !== 'N/A'): ?>
                    <div class="item">
                        <div class="info"><span class="lbl">RSRP (Strength)</span><span class="val" style="color:<?= getSignalColor($sig['rsrp'], 'rsrp') ?>"><?= $sig['rsrp'] ?> dBm</span></div>
                        <div class="pb"><div class="pf" style="width:<?= getWidth($sig['rsrp'], 'rsrp') ?>; background:<?= getSignalColor($sig['rsrp'], 'rsrp') ?>"></div></div>
                    </div>

                    <div class="item">
                        <div class="info"><span class="lbl">SINR (Quality)</span><span class="val" style="color:<?= getSignalColor($sig['sinr'], 'sinr') ?>"><?= $sig['sinr'] ?> dB</span></div>
                        <div class="pb"><div class="pf" style="width:<?= getWidth($sig['sinr'], 'sinr') ?>; background:<?= getSignalColor($sig['sinr'], 'sinr') ?>"></div></div>
                    </div>

                    <div class="item">
                        <div class="info"><span class="lbl">RSRQ</span><span class="val" style="color:<?= getSignalColor($sig['rsrq'], 'rsrq') ?>"><?= $sig['rsrq'] ?> dB</span></div>
                        <div class="pb"><div class="pf" style="width:<?= getWidth($sig['rsrq'], 'rsrq') ?>; background:<?= getSignalColor($sig['rsrq'], 'rsrq') ?>"></div></div>
                    </div>
                    
                    <div class="item">
                        <div class="info"><span class="lbl">RSSI</span><span class="val" style="color:var(--text)"><?= $sig['rssi'] ?> dBm</span></div>
                        <div class="pb"><div class="pf" style="width:<?= getWidth($sig['rssi'], 'rssi') ?>; background:var(--sub)"></div></div>
                    </div>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align:center; color:var(--sub); padding:10px;">No Signal / Inactive</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>