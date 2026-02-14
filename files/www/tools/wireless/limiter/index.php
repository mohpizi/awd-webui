<?php
function cmd($c) { return shell_exec("su -c \"$c\" 2>&1"); }

function getAllInterfaces() {
    $list = [];
    $raw = cmd("ip link show");
    foreach (explode("\n", $raw) as $line) {
        if (preg_match('/^\d+:\s+([a-zA-Z0-9\-_]+)(@\w+)?:\s+.*state\s+(UP|UNKNOWN)/', $line, $matches)) {
            $iface = $matches[1];
            if ($iface !== 'lo' && strpos($iface, 'tun') === false && strpos($iface, 'rmnet') === false) {
                $list[] = $iface;
            }
        }
    }
    if (empty($list)) $list = ['wlan0', 'eth0', 'ap0', 'rndis0', 'swlan0']; 
    return array_unique($list);
}

function macToId($mac) {
    return (crc32($mac) % 9000) + 100;
}

function getIpFromMac($targetMac) {
    $targetMac = strtolower($targetMac);
    $rawArp = cmd("cat /proc/net/arp");
    foreach (explode("\n", $rawArp) as $line) {
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) >= 6 && strtolower($cols[3]) === $targetMac) return $cols[0];
    }
    $rawNeigh = cmd("ip neigh show");
    foreach (explode("\n", $rawNeigh) as $line) {
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s+.*lladdr\s+([a-fA-F0-9:]+)/', $line, $m)) {
            if (strtolower($m[2]) === $targetMac) return $m[1];
        }
    }
    return null;
}

$interfaces = getAllInterfaces();
$dbFile = __DIR__ . '/limits_mac.json';
$bootFile = '/data/adb/service.d/limiter_mac.sh';

$data = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
if (!isset($data['limits'])) $data['limits'] = [];
if (!isset($data['global'])) $data['global'] = ['rate' => '1000mbit', 'display' => 'Unlimited'];

function setupInterface($dev, $globalRate) {
    cmd("tc qdisc del dev $dev root 2>/dev/null");
    cmd("tc qdisc add dev $dev root handle 1: htb default 9999 r2q 1");
    cmd("tc class add dev $dev parent 1: classid 1:1 htb rate 1000mbit ceil 1000mbit");
    cmd("tc class add dev $dev parent 1:1 classid 1:9999 htb rate $globalRate ceil $globalRate prio 5");
}

function applyUserLimit($dev, $mac, $ip, $rate) {
    $id = macToId($mac);
    $classId = "1:$id";
    cmd("tc filter del dev $dev parent 1: protocol ip prio 1 u32 match ip dst $ip/32");
    cmd("tc class del dev $dev parent 1:1 classid $classId");
    cmd("tc class add dev $dev parent 1:1 classid $classId htb rate $rate ceil $rate prio 1");
    cmd("tc filter add dev $dev parent 1: protocol ip prio 1 u32 match ip dst $ip/32 flowid $classId");
}

if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'autoboot') {
    sleep(15);
    foreach ($interfaces as $dev) setupInterface($dev, $data['global']['rate']);
    foreach ($data['limits'] as $mac => $info) {
        $ip = getIpFromMac($mac);
        if ($ip) {
            foreach ($interfaces as $dev) applyUserLimit($dev, $mac, $ip, $info['raw_rate']);
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'toggle_boot') {
        $enable = $_POST['enable'] === '1';
        if ($enable) {
            $scriptContent = "#!/system/bin/sh\nsleep 30\n/system/bin/php \"" . __FILE__ . "\" autoboot > /dev/null 2>&1\n";
            file_put_contents($bootFile, $scriptContent);
            cmd("chmod 755 $bootFile");
        } else {
            if (file_exists($bootFile)) unlink($bootFile);
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'set_global' || $action === 'apply_limit' || $action === 'remove_limit') {
        if ($action === 'set_global') {
            $speed = intval($_POST['speed']);
            $unit = $_POST['unit'];
            $rate = ($unit === 'mbps') ? $speed . 'mbit' : $speed . 'kbit';
            $data['global'] = ['rate' => $rate, 'display' => "$speed $unit"];
            file_put_contents($dbFile, json_encode($data));
        }
        foreach ($interfaces as $dev) setupInterface($dev, $data['global']['rate']);
    }

    if ($action === 'apply_limit') {
        $mac = $_POST['mac'];
        $speed = intval($_POST['speed']);
        $unit = $_POST['unit'];
        $rate = ($unit === 'mbps') ? $speed . 'mbit' : $speed . 'kbit';
        $data['limits'][$mac] = ['speed' => "$speed $unit", 'raw_rate' => $rate];
        file_put_contents($dbFile, json_encode($data));
    }

    if ($action === 'remove_limit') {
        unset($data['limits'][$_POST['mac']]);
        file_put_contents($dbFile, json_encode($data));
    }

    foreach ($data['limits'] as $mac => $info) {
        $ip = getIpFromMac($mac);
        if ($ip) {
            foreach ($interfaces as $dev) applyUserLimit($dev, $mac, $ip, $info['raw_rate']);
        }
    }
    
    if ($action === 'reset_all') {
        foreach ($interfaces as $iface) cmd("tc qdisc del dev $iface root");
        unlink($dbFile);
        if (file_exists($bootFile)) unlink($bootFile);
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

$isBootEnabled = file_exists($bootFile);
$clients = [];
$foundMACs = [];

function addClientToResult($ip, $mac, $iface, &$clients, &$foundMACs, $data) {
    if (!filter_var($mac, FILTER_VALIDATE_MAC) || $mac === '00:00:00:00:00:00') return;
    $mac = strtolower($mac);
    if (!isset($foundMACs[$mac])) {
        $isSpecific = isset($data['limits'][$mac]);
        $clients[] = [
            'ip' => $ip, 'mac' => $mac, 'interface' => $iface,
            'status' => $isSpecific ? 'Custom' : 'Global',
            'limit' => $isSpecific ? $data['limits'][$mac]['speed'] : $data['global']['display']
        ];
        $foundMACs[$mac] = true;
    }
}

$rawArp = cmd("cat /proc/net/arp");
foreach (explode("\n", $rawArp) as $line) {
    if (strpos($line, 'IP address') !== false) continue;
    $cols = preg_split('/\s+/', trim($line));
    if (count($cols) >= 6) addClientToResult($cols[0], $cols[3], $cols[5], $clients, $foundMACs, $data);
}

$rawNeigh = cmd("ip neigh show"); 
foreach (explode("\n", $rawNeigh) as $line) {
    if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s+dev\s+([a-zA-Z0-9\-_]+)\s+.*lladdr\s+([a-fA-F0-9:]+)/', $line, $m)) {
        addClientToResult($m[1], $m[3], $m[2], $clients, $foundMACs, $data);
    }
}

foreach ($data['limits'] as $macDb => $info) {
    $macDb = strtolower($macDb);
    if (!isset($foundMACs[$macDb])) {
        $clients[] = ['ip' => 'Offline', 'mac' => $macDb, 'interface' => '-', 'status' => 'Custom', 'limit' => $info['speed']];
        $foundMACs[$macDb] = true;
    }
}
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
        :root {
            --bg: #111111; 
            --surface: #1e1e1e; 
            --surface-hover: #2d2d2d;
            --border: #333333; 
            --text-main: #f3f4f6; 
            --text-sub: #9ca3af;
            --primary: #fb8c00; 
            --primary-glow: rgba(251, 140, 0, 0.2);
            --danger: #ef4444; 
            --success: #10b981; 
            --radius: 16px; 
            --shadow: 0 4px 10px rgba(0,0,0,0.5); 
            --font: 'Plus Jakarta Sans', sans-serif;
            --toggle-bg: #475569;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; outline: none; }
        body { font-family: var(--font); background: var(--bg); color: var(--text-main); padding: 20px; min-height: 100vh; font-size: 14px; }
        .container { max-width: 800px; margin: 0 auto; padding-bottom: 60px; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-icon { width: 40px; height: 40px; background: var(--primary); color: white; border-radius: 12px; display: grid; place-items: center; font-size: 22px; box-shadow: 0 4px 15px -3px var(--primary-glow); }
        .brand-text h1 { font-size: 1.2rem; font-weight: 800; }
        .brand-text p { font-size: 0.75rem; color: var(--text-sub); font-weight: 600; }
        .status-badge { background: var(--surface); border: 1px solid var(--border); padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; color: var(--text-sub); display: flex; align-items: center; gap: 6px; }
        .status-dot { width: 8px; height: 8px; background: var(--success); border-radius: 50%; }

        .card { background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--surface-hover); }
        .card-title { font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
        .card-body { padding: 20px; }

        .input-group { display: flex; gap: 10px; }
        input, select { width: 100%; background: var(--bg); border: 1px solid var(--border); color: var(--text-main); padding: 12px 16px; border-radius: 10px; font-family: var(--font); font-weight: 600; font-size: 0.9rem; appearance: none; transition: 0.2s; }
        input:focus, select:focus { border-color: var(--primary); }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--text-sub); font-size: 0.8rem; }

        .btn { border: none; padding: 12px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 0.9rem; transition: 0.2s; }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 15px -3px var(--primary-glow); }
        .btn-primary:active { transform: scale(0.98); }
        
        .btn-icon { width: 34px; height: 34px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text-sub); font-size: 1.1rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-icon:hover { color: var(--primary); border-color: var(--primary); background: var(--surface); }

        .current-stat { margin-top: 15px; padding: 12px 16px; background: var(--bg); border-radius: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px dashed var(--border); }
        .stat-val { font-size: 1.1rem; font-weight: 800; color: var(--primary); }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 20px; color: var(--text-sub); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); font-weight: 700; }
        td { padding: 14px 20px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        .client-ip { font-weight: 700; color: var(--text-main); font-size: 0.95rem; }
        .client-mac { font-family: monospace; color: var(--text-sub); font-size: 0.8rem; margin-top: 2px; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .badge-custom { background: rgba(251, 140, 0, 0.1); color: var(--primary); }
        .badge-global { background: var(--bg); color: var(--text-sub); border: 1px solid var(--border); }

        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--toggle-bg); transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(20px); }
        .toggle-label { font-size: 0.8rem; margin-right: 10px; color: var(--text-sub); font-weight: 700; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 999; justify-content: center; align-items: center; padding: 20px; }
        .modal-content { background: var(--surface); width: 100%; max-width: 400px; padding: 25px; border-radius: 20px; border: 1px solid var(--border); box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: pop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes pop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        @media (max-width: 600px) {
            thead { display: none; }
            tr { display: block; padding: 15px; border-bottom: 1px solid var(--border); position: relative; }
            td { display: block; padding: 2px 0; border: none; }
            td:nth-child(1) { margin-bottom: 8px; }
            td:nth-child(2) { display: inline-block; margin-right: 10px; }
            td:nth-child(3) { display: inline-block; font-weight: 700; }
            td:nth-child(4) { position: absolute; top: 15px; right: 15px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="brand">
            <div class="brand-icon"><iconify-icon icon="tabler:shield-lock"></iconify-icon></div>
            <div class="brand-text"><h1>Traffic Control</h1><p>Bandwidth Manager</p></div>
        </div>
        <div class="status-badge"><div class="status-dot"></div><?php echo count($interfaces); ?> active</div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><iconify-icon icon="tabler:settings" style="color:var(--primary); font-size:1.2rem"></iconify-icon> Global Policy</span>
            <form method="POST" style="display:flex; align-items:center;">
                <input type="hidden" name="action" value="toggle_boot">
                <span class="toggle-label">Autoboot</span>
                <label class="toggle-switch">
                    <input type="hidden" name="enable" value="0">
                    <input type="checkbox" name="enable" value="1" onchange="this.form.submit()" <?php echo $isBootEnabled ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </form>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="set_global">
                <label>Default Limit (Non-registered)</label>
                <div class="input-group">
                    <input type="number" name="speed" placeholder="Value..." required>
                    <select name="unit" style="width:100px; flex:none"><option value="mbps">Mbps</option><option value="kbps">Kbps</option></select>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
            <div class="current-stat">
                <span style="color:var(--text-sub); font-weight:600">Current Limit</span>
                <span class="stat-val"><?php echo $data['global']['display']; ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><iconify-icon icon="tabler:devices" style="color:var(--primary); font-size:1.2rem"></iconify-icon> Connected Devices</span>
            <form method="POST" onsubmit="return confirm('Reset all config?')">
                <input type="hidden" name="action" value="reset_all">
                <button class="btn-icon" title="Reset All" style="color:var(--danger); border-color:var(--danger)"><iconify-icon icon="tabler:trash"></iconify-icon></button>
            </form>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Device</th><th>Status</th><th>Limit</th><th style="text-align:right">Action</th></tr></thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 40px; color:var(--text-sub)">
                            <iconify-icon icon="tabler:router-off" style="font-size:2.5rem; opacity:0.5; margin-bottom:10px;"></iconify-icon><br>No devices found
                        </td></tr>
                    <?php else: foreach($clients as $c): ?>
                        <tr>
                            <td>
                                <div class="client-ip"><?php echo $c['ip']; ?></div>
                                <div class="client-mac"><?php echo $c['mac']; ?></div>
                            </td>
                            <td><span class="badge <?php echo $c['status'] === 'Custom' ? 'badge-custom' : 'badge-global'; ?>"><?php echo $c['status']; ?></span></td>
                            <td><span style="font-weight:700"><?php echo $c['limit']; ?></span></td>
                            <td style="text-align:right">
                                <?php if($c['status'] === 'Custom'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="remove_limit">
                                        <input type="hidden" name="mac" value="<?php echo $c['mac']; ?>">
                                        <button class="btn-icon" style="color:var(--danger)"><iconify-icon icon="tabler:x"></iconify-icon></button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn-icon" onclick="openModal('<?php echo $c['mac']; ?>')"><iconify-icon icon="tabler:adjustments"></iconify-icon></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="limitModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-bottom:20px; font-weight:800; color:var(--text-main)">Set Device Limit</h3>
        <p style="margin-bottom:20px; font-size:0.85rem; color:var(--text-sub); font-family:monospace" id="targetMac"></p>
        <form method="POST">
            <input type="hidden" name="action" value="apply_limit">
            <input type="hidden" name="mac" id="inputMac">
            <label>Max Download Speed</label>
            <div class="input-group" style="margin-bottom:20px;">
                <input type="number" name="speed" placeholder="e.g. 5" required>
                <select name="unit" style="width:100px; flex:none"><option value="mbps">Mbps</option><option value="kbps">Kbps</option></select>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn" style="flex:1; background:var(--bg); color:var(--text-sub)" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1">Apply Limit</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(mac) {
        document.getElementById('targetMac').innerText = mac;
        document.getElementById('inputMac').value = mac;
        document.getElementById('limitModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('limitModal').style.display = 'none';
    }
    window.onclick = function(e) { if(e.target == document.getElementById('limitModal')) closeModal(); }
</script>
</body>
</html>
