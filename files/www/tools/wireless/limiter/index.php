<?php
$coreScript ='/data/adb/php8/scrips/core_bw';
$ruleFile   = __DIR__ . '/rules.txt';
$dbFile     = __DIR__ . '/limits_mac.json';
$bootFile   = '/data/adb/service.d/limiter_mac.sh';
$data = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
if (!isset($data['limits'])) $data['limits'] = [];
if (!isset($data['global'])) $data['global'] = [
    'down_rate' => '0mbit', 'down_display' => 'Unlimited',
    'up_rate'   => '0mbit', 'up_display'   => 'Unlimited'
];

// FUNGSI APPLY KE SHELL
function applyChanges() {
    global $data, $ruleFile, $coreScript;
    
    // Tulis Config ke rules.txt
    // Baris 1: Global
    $content = "GLOBAL|" . $data['global']['down_rate'] . "|" . $data['global']['up_rate'] . "\n";
    
    // Baris 2+: Users
    foreach ($data['limits'] as $mac => $info) {
        $content .= "$mac|" . $info['down_rate'] . "|" . $info['up_rate'] . "\n";
    }
    
    file_put_contents($ruleFile, $content);
    
    // Panggil Core Shell Script
    shell_exec("sh \"$coreScript\" refresh > /dev/null 2>&1 &");
}

function cmd($c) { return shell_exec("su -c \"$c\" 2>&1"); }

// HANDLE FORM REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    function makeRate($val, $unit) {
        return ($val == 0) ? '0mbit' : (($unit === 'mbps') ? $val.'mbit' : $val.'kbit');
    }
    function makeDisplay($val, $unit) {
        return ($val == 0) ? 'Unlimited' : "$val $unit";
    }

    if ($action === 'set_global') {
        $ds = intval($_POST['down_speed']); 
        $us = intval($_POST['up_speed']);
        
        $data['global'] = [
            'down_rate'    => makeRate($ds, $_POST['down_unit']),
            'down_display' => makeDisplay($ds, $_POST['down_unit']),
            'up_rate'      => makeRate($us, $_POST['up_unit']),
            'up_display'   => makeDisplay($us, $_POST['up_unit'])
        ];
        file_put_contents($dbFile, json_encode($data));
        applyChanges();
    }

    if ($action === 'apply_limit') {
        $mac = $_POST['mac'];
        $ds = intval($_POST['down_speed_user']);
        $us = intval($_POST['up_speed_user']);

        $data['limits'][$mac] = [
            'down_rate'    => makeRate($ds, $_POST['down_unit_user']),
            'down_display' => makeDisplay($ds, $_POST['down_unit_user']),
            'up_rate'      => makeRate($us, $_POST['up_unit_user']),
            'up_display'   => makeDisplay($us, $_POST['up_unit_user'])
        ];
        file_put_contents($dbFile, json_encode($data));
        applyChanges();
    }

    if ($action === 'remove_limit') {
        unset($data['limits'][$_POST['mac']]);
        file_put_contents($dbFile, json_encode($data));
        applyChanges();
    }

    if ($action === 'reset_all') {
        unlink($dbFile);
        if (file_exists($bootFile)) unlink($bootFile);
        shell_exec("sh \"$coreScript\" reset");
    }
    
    if ($action === 'toggle_boot') {
        $enable = $_POST['enable'] === '1';
        if ($enable) {
            // Update boot script dengan path absolut yang benar
            $script = "#!/system/bin/sh\nsleep 30\nsh \"$coreScript\" refresh >/dev/null 2>&1\n";
            file_put_contents($bootFile, $script);
            cmd("chmod 755 $bootFile");
        } else {
            if (file_exists($bootFile)) unlink($bootFile);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

// VIEW DATA & SCANNING
$clients = [];
$foundMACs = [];
$g_disp = "<span style='color:#10b981'>↓ {$data['global']['down_display']}</span> <span style='color:#3b82f6'>↑ {$data['global']['up_display']}</span>";

function addClient($ip, $mac, &$clients, &$foundMACs, $data, $gd) {
    if (!filter_var($mac, FILTER_VALIDATE_MAC) || $mac === '00:00:00:00:00:00') return;
    $mac = strtolower($mac);
    if (!isset($foundMACs[$mac])) {
        $isSpecific = isset($data['limits'][$mac]);
        $disp = $gd;
        if ($isSpecific) {
            $i = $data['limits'][$mac];
            $disp = "<span style='color:#10b981'>↓ {$i['down_display']}</span> <span style='color:#3b82f6'>↑ {$i['up_display']}</span>";
        }
        $clients[] = ['ip' => $ip, 'mac' => $mac, 'status' => $isSpecific?'Custom':'Global', 'limit' => $disp];
        $foundMACs[$mac] = true;
    }
}

$arp = cmd("cat /proc/net/arp");
foreach (explode("\n", $arp) as $line) {
    $c = preg_split('/\s+/', trim($line));
    if (count($c) >= 6) addClient($c[0], $c[3], $clients, $foundMACs, $data, $g_disp);
}
$neigh = cmd("ip neigh show");
foreach (explode("\n", $neigh) as $line) {
    if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s+.*lladdr\s+([a-fA-F0-9:]+)/', $line, $m)) addClient($m[1], $m[2], $clients, $foundMACs, $data, $g_disp);
}
foreach ($data['limits'] as $mac => $info) {
    if (!isset($foundMACs[$mac])) {
        $disp = "<span style='color:#10b981'>↓ {$info['down_display']}</span> <span style='color:#3b82f6'>↑ {$info['up_display']}</span>";
        $clients[] = ['ip' => 'Offline', 'mac' => $mac, 'status' => 'Custom', 'limit' => $disp];
        $foundMACs[$mac] = true;
    }
}
$isBootEnabled = file_exists($bootFile);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bandwidth Manager</title>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #111111; --surface: #1e1e1e; --border: #333333; --text: #f3f4f6; --sub: #9ca3af; --pri: #fb8c00; --dang: #ef4444; --font: 'Plus Jakarta Sans', sans-serif; }
        * { margin:0; padding:0; box-sizing:border-box; outline:none; }
        body { font-family:var(--font); background:var(--bg); color:var(--text); padding:20px; font-size:14px; }
        .container { max-width:800px; margin:0 auto; padding-bottom:60px; }
        .head { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:16px; margin-bottom:20px; overflow:hidden; }
        .card-h { padding:15px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:#252525; font-weight:700; }
        .card-b { padding:20px; }
        .inp-g { display:flex; gap:10px; margin-bottom:10px; }
        input, select { width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:12px; border-radius:10px; }
        .btn { width:100%; padding:12px; background:var(--pri); color:#fff; border:none; border-radius:10px; font-weight:700; cursor:pointer; }
        .btn-icon { background:none; border:1px solid var(--border); color:var(--sub); width:32px; height:32px; border-radius:8px; cursor:pointer; display:grid; place-items:center; }
        table { width:100%; border-collapse:collapse; }
        td, th { padding:12px 15px; border-bottom:1px solid var(--border); text-align:left; }
        .badge { padding:3px 8px; border-radius:5px; font-size:0.7rem; background:rgba(251,140,0,0.15); color:var(--pri); border:1px solid var(--pri); }
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:99; align-items:center; justify-content:center; padding:20px; }
        .modal-c { background:var(--surface); width:100%; max-width:400px; padding:25px; border-radius:20px; border:1px solid var(--border); }
        .sw { position:relative; width:40px; height:22px; display:inline-block; }
        .sw input { opacity:0; width:0; height:0; }
        .sl { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#475569; border-radius:20px; transition:.3s; }
        .sl:before { content:""; height:16px; width:16px; left:3px; bottom:3px; background:white; border-radius:50%; position:absolute; transition:.3s; }
        input:checked + .sl { background:var(--pri); }
        input:checked + .sl:before { transform:translateX(18px); }
    </style>
</head>
<body>

<div class="container">
    <div class="head">
        <h2 style="display:flex; align-items:center; gap:10px"><iconify-icon icon="tabler:shield-lock" style="color:var(--pri)"></iconify-icon> Traffic Control</h2>
        <div style="font-size:0.8rem; color:var(--sub)">Tools</div>
    </div>

    <div class="card">
        <div class="card-h">
            <span>Global Policy</span>
            <form method="POST" style="display:flex; align-items:center; gap:10px">
                <input type="hidden" name="action" value="toggle_boot">
                <span style="font-size:0.75rem; font-weight:400">Autoboot</span>
                <label class="sw">
                    <input type="hidden" name="enable" value="0">
                    <input type="checkbox" name="enable" value="1" onchange="this.form.submit()" <?php echo $isBootEnabled?'checked':''; ?>>
                    <span class="sl"></span>
                </label>
            </form>
        </div>
        <div class="card-b">
            <form method="POST">
                <input type="hidden" name="action" value="set_global">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px">
                    <div>
                        <label style="font-size:0.8rem; color:#10b981; font-weight:700">Download</label>
                        <div class="inp-g">
                            <input type="number" name="down_speed" placeholder="0" required>
                            <select name="down_unit" style="width:70px; flex:none"><option value="mbps">M</option><option value="kbps">K</option></select>
                        </div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:#3b82f6; font-weight:700">Upload</label>
                        <div class="inp-g">
                            <input type="number" name="up_speed" placeholder="0" required>
                            <select name="up_unit" style="width:70px; flex:none"><option value="mbps">M</option><option value="kbps">K</option></select>
                        </div>
                    </div>
                </div>
                <button class="btn">Save Global</button>
            </form>
            <div style="margin-top:15px; font-size:0.9rem; text-align:center; color:var(--sub)">
                Current: <b><?php echo $g_disp; ?></b>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">
            <span>Devices (<?php echo count($clients); ?>)</span>
            <form method="POST" onsubmit="return confirm('Reset All?')">
                <input type="hidden" name="action" value="reset_all">
                <button class="btn-icon" style="color:var(--dang); border-color:var(--dang)"><iconify-icon icon="tabler:trash"></iconify-icon></button>
            </form>
        </div>
        <div style="overflow-x:auto">
            <table>
                <?php foreach($clients as $c): ?>
                <tr>
                    <td>
                        <div style="font-weight:700"><?php echo $c['ip']; ?></div>
                        <div style="font-family:monospace; color:var(--sub); font-size:0.8rem"><?php echo $c['mac']; ?></div>
                    </td>
                    <td><?php echo $c['limit']; ?></td>
                    <td style="text-align:right">
                        <?php if($c['status']=='Custom'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="remove_limit">
                                <input type="hidden" name="mac" value="<?php echo $c['mac']; ?>">
                                <button class="btn-icon" style="color:var(--dang)"><iconify-icon icon="tabler:x"></iconify-icon></button>
                            </form>
                        <?php else: ?>
                            <button class="btn-icon" onclick="modal('<?php echo $c['mac']; ?>')"><iconify-icon icon="tabler:settings"></iconify-icon></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<div id="mod" class="modal">
    <div class="modal-c">
        <h3 style="margin-bottom:20px">Set Limit</h3>
        <p id="tMac" style="color:var(--sub); font-family:monospace; margin-bottom:15px"></p>
        <form method="POST">
            <input type="hidden" name="action" value="apply_limit">
            <input type="hidden" name="mac" id="iMac">
            
            <label style="color:#10b981; font-weight:700; font-size:0.8rem">Download</label>
            <div class="inp-g">
                <input type="number" name="down_speed_user" id="d_val" placeholder="0" required>
                <select name="down_unit_user" style="width:80px; flex:none"><option value="mbps">M</option><option value="kbps">K</option></select>
            </div>

            <label style="color:#3b82f6; font-weight:700; font-size:0.8rem">Upload</label>
            <div class="inp-g">
                <input type="number" name="up_speed_user" id="u_val" placeholder="0" required>
                <select name="up_unit_user" style="width:80px; flex:none"><option value="mbps">M</option><option value="kbps">K</option></select>
            </div>

            <div style="display:flex; gap:10px">
                <button type="button" class="btn" style="background:var(--surface); border:1px solid var(--border)" onclick="document.getElementById('mod').style.display='none'">Cancel</button>
                <button type="submit" class="btn">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
function modal(mac) {
    document.getElementById('tMac').innerText = mac;
    document.getElementById('iMac').value = mac;
    document.getElementById('d_val').value = '';
    document.getElementById('u_val').value = '';
    document.getElementById('mod').style.display = 'flex';
}
</script>
</body>
</html>