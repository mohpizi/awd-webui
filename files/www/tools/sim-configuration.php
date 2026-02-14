<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
$isAjax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

function executeCommand($command) {
    return shell_exec($command . " 2>&1");
}

// --- 1. PARSING SIM INFO ---
$simMap = [];
$simInfoRaw = executeCommand('su -c "content query --uri content://telephony/siminfo --projection _id,sim_id,display_name,mcc_string,mnc_string"');

if ($simInfoRaw) {
    $lines = explode("\n", trim($simInfoRaw));
    foreach ($lines as $line) {
        $subId = null; $simId = null; $name = 'Unknown'; $mcc = ''; $mnc = '';
        
        if (preg_match('/_id=(\d+)/', $line, $m)) $subId = $m[1];
        if (preg_match('/sim_id=(\d+)/', $line, $m)) $simId = intval($m[1]);
        if (preg_match('/display_name=([^,]+)/', $line, $m)) $name = trim($m[1]);
        if (preg_match('/mcc_string=(\d+)/', $line, $m)) $mcc = $m[1];
        if (preg_match('/mnc_string=(\d+)/', $line, $m)) $mnc = $m[1];

        $numeric = ($mcc !== '' && $mnc !== '') ? $mcc . $mnc : null;

        if ($subId !== null && $simId !== null) {
            $simMap[$simId] = [
                'subId' => $subId,
                'name' => $name,
                'numeric' => $numeric
            ];
        }
    }
}

// --- 2. LOGIKA PILIHAN SIM ---
$ui_sim_tab = isset($_REQUEST['ui_sim_id']) ? intval($_REQUEST['ui_sim_id']) : 1; 
$target_slot = $ui_sim_tab - 1; 
$target_subId = isset($simMap[$target_slot]['subId']) ? $simMap[$target_slot]['subId'] : null;
$target_numeric = isset($simMap[$target_slot]['numeric']) ? $simMap[$target_slot]['numeric'] : null;

$uri_carriers = "content://telephony/carriers";
$uri_prefer = $target_subId ? "content://telephony/carriers/preferapn/subId/$target_subId" : "content://telephony/carriers/preferapn";

// --- 3. PROSES CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionTaken = false;

    // Set Preferred
    if (isset($_POST['set_apn']) && isset($_POST['apn_id'])) {
        $id = escapeshellarg($_POST['apn_id']);
        executeCommand("su -c \"content update --uri $uri_prefer --bind apn_id:i:$id\"");
        $actionTaken = true;
    }

    // Delete
    if (isset($_POST['delete_apn']) && isset($_POST['apn_id'])) {
        $id = escapeshellarg($_POST['apn_id']);
        executeCommand("su -c \"content delete --uri $uri_carriers --where \\\"_id=$id\\\"\"");
        $actionTaken = true;
    }

    // Add APN
    if (isset($_POST['add_apn'])) {
        $esc = function($v){ return escapeshellarg($v); };
        
        $binds = "name:s:{$esc($_POST['name'])} --bind apn:s:{$esc($_POST['apn'])} " .
                 "--bind proxy:s:{$esc($_POST['proxy']??'')} --bind port:s:{$esc($_POST['port']??'')} " .
                 "--bind user:s:{$esc($_POST['user']??'')} --bind password:s:{$esc($_POST['password']??'')} " .
                 "--bind server:s:{$esc($_POST['server']??'')} --bind mmsc:s:{$esc(str_replace('://','\\://',$_POST['mmsc']??''))} " .
                 "--bind mmsproxy:s:{$esc($_POST['mmsproxy']??'')} --bind mmsport:s:{$esc($_POST['mmsport']??'')} " .
                 "--bind authtype:s:{$esc($_POST['authtype']??'-1')} --bind type:s:{$esc($_POST['type']??'default,supl')} " .
                 "--bind protocol:s:{$esc($_POST['protocol']??'IPv4')} --bind roaming_protocol:s:{$esc($_POST['roamingprotocol']??'IPv4')} " .
                 "--bind current:i:1";

        if ($target_numeric) $binds .= " --bind numeric:s:{$esc($target_numeric)}";
        if ($target_subId) $binds .= " --bind sub_id:i:$target_subId";

        executeCommand("su -c \"content insert --uri $uri_carriers --bind $binds\"");
        $actionTaken = true;
    }

    // Edit APN
    if (isset($_POST['edit_apn'])) {
        $id = escapeshellarg($_POST['id']);
        $esc = function($v){ return escapeshellarg($v); };
        
        $binds = "name:s:{$esc($_POST['name'])} --bind apn:s:{$esc($_POST['apn'])} " .
                 "--bind proxy:s:{$esc($_POST['proxy']??'')} --bind port:s:{$esc($_POST['port']??'')} " .
                 "--bind user:s:{$esc($_POST['user']??'')} --bind password:s:{$esc($_POST['password']??'')} " .
                 "--bind server:s:{$esc($_POST['server']??'')} --bind mmsc:s:{$esc(str_replace('://','\\://',$_POST['mmsc']??''))} " .
                 "--bind mmsproxy:s:{$esc($_POST['mmsproxy']??'')} --bind mmsport:s:{$esc($_POST['mmsport']??'')} " .
                 "--bind authtype:s:{$esc($_POST['authtype']??'-1')} --bind type:s:{$esc($_POST['type']??'')} " .
                 "--bind protocol:s:{$esc($_POST['protocol']??'IPv4')} --bind roaming_protocol:s:{$esc($_POST['roamingprotocol']??'IPv4')}";
        
        executeCommand("su -c \"content update --uri $uri_carriers --where \\\"_id=$id\\\" --bind $binds\"");
        $actionTaken = true;
    }
    
    // Reset APN
    if (isset($_POST['reset_apn'])) {
        $where = "current=1";
        if ($target_numeric) $where .= " AND numeric='$target_numeric'";
        if ($target_subId) $where .= " AND sub_id=$target_subId";

        executeCommand("su -c \"content delete --uri $uri_carriers --where \\\"$where\\\"\"");
        executeCommand('su -c "killall com.android.phone"');
        sleep(2);
        $actionTaken = true;
    }

    if ($actionTaken && !$isAjax) {
        header("Location: ?ui_sim_id=" . $ui_sim_tab); 
        exit();
    }
}

// --- 4. FETCH DATA ---
$currentApnId = null;
$prefOut = executeCommand("su -c \"content query --uri $uri_prefer\"");
if (preg_match('/_id=(\d+)/', $prefOut, $m)) $currentApnId = $m[1];
elseif (preg_match('/_id\s*:\s*(\d+)/', $prefOut, $m)) $currentApnId = $m[1];

$apnList = [];
$whereClause = "type!='ims'";
if ($target_numeric) {
    if ($target_subId) {
        $whereClause .= " AND (numeric='$target_numeric' OR sub_id=$target_subId)";
    } else {
        $whereClause .= " AND numeric='$target_numeric'";
    }
}

$output = executeCommand("su -c \"content query --uri $uri_carriers --where \\\"$whereClause\\\" --projection _id,name,apn,numeric,type\"");

if ($output) {
    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (preg_match('/_id=(\d+)/', $line, $idM) && preg_match('/name=([^,]*)/', $line, $nM)) {
            $apnM = preg_match('/apn=([^,]*)/', $line, $m) ? $m[1] : '';
            $apnList[] = [
                'id' => $idM[1],
                'name' => trim($nM[1]),
                'apn' => trim($apnM)
            ];
        }
    }
}

// Edit Data Fetch
$editApnData = [];
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $out = executeCommand("su -c \"content query --uri $uri_carriers --where \\\"_id=$id\\\"\"");
    if ($out) {
        $parts = preg_split('/,\s+/', trim($out));
        foreach($parts as $p) {
            $kv = explode('=', $p, 2);
            if(count($kv)==2) $editApnData[trim($kv[0])] = trim($kv[1]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Network Manager</title>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <style>
        :root {
            --bg: #f2f2f7; 
            --card: #ffffff; 
            --text: #1c1c1e; 
            --sub: #8e8e93; 
            --border: #e5e5ea;
            --primary: #fb8c00; 
            --primary-light: #fff3e0; 
            --input-bg: #f9f9f9;
            --radius: 16px;
            --shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        
        /* DARK MODE CONFIGURATION - EDITED */
        @media (prefers-color-scheme: dark) {
            :root {
                /* UBAH DISINI: Warna background jadi Abu-abu Gelap (#181818), bukan Hitam (#000000) */
                --bg: #181818; 
                --card: #242424; /* Warna kartu sedikit lebih terang dari background */
                --text: #ffffff; 
                --sub: #98989d; 
                --border: #333333; /* Border disesuaikan agar tidak terlalu kontras */
                --primary: #fb8c00; 
                --primary-light: rgba(251, 140, 0, 0.15); 
                --input-bg: #2f2f2f; /* Input field disesuaikan */
            }
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 600px; margin: 0 auto; padding-bottom: 100px; }
        
        h2, h3 { font-weight: 700; margin-bottom: 15px; font-size: 1.1rem; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }

        .card { background: var(--card); border-radius: var(--radius); padding: 20px; margin-bottom: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); overflow: hidden; }
        .card.p-0 { padding: 0; }
        .card-header { padding: 15px 20px; border-bottom: 1px solid var(--border); background: var(--card); font-weight: 600; display: flex; justify-content: space-between; align-items: center; color: var(--primary); }

        /* SIM Selector Style */
        .sim-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .sim-box { 
            background: var(--input-bg); border: 2px solid transparent; border-radius: 12px; 
            padding: 15px; cursor: pointer; display: flex; align-items: center; gap: 12px; 
            transition: all 0.2s ease; position: relative; overflow: hidden;
        }
        .sim-box.active { border-color: var(--primary); background: var(--primary-light); }
        .sim-box.disabled { opacity: 0.5; pointer-events: none; }
        .sim-icon { font-size: 24px; color: var(--sub); }
        .sim-box.active .sim-icon { color: var(--primary); }
        .sim-info { display: flex; flex-direction: column; }
        .sim-name { font-weight: 600; font-size: 0.95rem; color: var(--text); }
        .sim-num { font-size: 0.75rem; color: var(--sub); margin-top: 2px; }
        
        /* APN List Style */
        .apn-item { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 16px 20px; border-bottom: 1px solid var(--border); 
            background: var(--card); transition: background 0.2s; 
        }
        .apn-item:last-child { border-bottom: none; }
        .apn-item:active { background: var(--input-bg); }
        
        .radio-wrapper { display: flex; align-items: center; gap: 15px; flex: 1; cursor: pointer; }
        .custom-radio { 
            width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--sub); 
            display: flex; align-items: center; justify-content: center; transition: 0.2s; 
        }
        .apn-item.active .custom-radio { border-color: var(--primary); }
        .apn-item.active .custom-radio::after { 
            content: ''; width: 10px; height: 10px; background: var(--primary); border-radius: 50%; 
        }
        
        .apn-text div:first-child { font-weight: 600; font-size: 0.95rem; color: var(--text); }
        .apn-text div:last-child { font-size: 0.8rem; color: var(--sub); margin-top: 2px; }
        
        .apn-actions { display: flex; gap: 8px; }
        .icon-btn { 
            width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; 
            border-radius: 8px; border: none; background: transparent; color: var(--sub); 
            cursor: pointer; font-size: 1.2rem; transition: 0.2s; 
        }
        .icon-btn:hover { background: var(--input-bg); color: var(--text); }
        .icon-btn.danger:hover { background: #ffebeb; color: #ff3b30; }

        /* Form Style */
        .form-group { margin-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--sub); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        input, select { 
            width: 100%; padding: 14px; border-radius: 10px; border: 1px solid var(--border); 
            background: var(--input-bg); color: var(--text); font-size: 1rem; transition: 0.2s; 
            -webkit-appearance: none;
        }
        input:focus, select:focus { border-color: var(--primary); background: var(--card); }
        
        .btn-row { display: flex; gap: 10px; margin-top: 20px; }
        .btn { 
            flex: 1; padding: 14px; border-radius: 12px; border: none; font-weight: 600; 
            font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-sec { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn:active { transform: scale(0.98); opacity: 0.9; }

        /* FAB */
        .fab { 
            position: fixed; bottom: 25px; right: 25px; width: 56px; height: 56px; 
            background: var(--primary); color: white; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 24px; box-shadow: 0 4px 15px rgba(251, 140, 0, 0.4); 
            cursor: pointer; transition: transform 0.2s; z-index: 50; 
        }
        .fab:hover { transform: scale(1.05); }
        
        .fab-reset {
            position: fixed; bottom: 90px; right: 33px; width: 40px; height: 40px;
            background: var(--card); color: var(--text); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: 1px solid var(--border);
            cursor: pointer; z-index: 49;
        }

        /* Loader */
        .loader { position: fixed; inset: 0; background: rgba(0,0,0,0.3); backdrop-filter: blur(2px); display: none; align-items: center; justify-content: center; z-index: 100; }
        .spinner { width: 40px; height: 40px; border: 4px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div id="app">
    <?php if (!isset($_GET['action'])): ?>
    <div class="card">
        <h3>Select SIM</h3>
        <form method="POST" id="simForm">
            <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
            <div class="sim-grid">
                <?php foreach([1,2] as $n): 
                    $idx = $n-1; $isActive = ($ui_sim_tab == $n); $hasSim = isset($simMap[$idx]); 
                ?>
                <div class="sim-box <?= $isActive ? 'active' : '' ?> <?= !$hasSim ? 'disabled' : '' ?>" onclick="<?= $hasSim ? "selectSim($n)" : '' ?>">
                    <iconify-icon icon="ic:round-sim-card" class="sim-icon"></iconify-icon>
                    <div class="sim-info">
                        <div class="sim-name"><?= $hasSim ? htmlspecialchars($simMap[$idx]['name']) : 'Empty' ?></div>
                        <div class="sim-num"><?= $hasSim ? ($simMap[$idx]['numeric']??'Unknown') : 'No SIM' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <div class="card p-0">
        <div class="card-header">
            <span>APN List</span>
            <span style="font-size:0.75rem; background:var(--primary-light); color:var(--primary); padding:2px 8px; border-radius:6px">
                <?= htmlspecialchars($simMap[$target_slot]['name'] ?? 'SIM '.$ui_sim_tab) ?>
            </span>
        </div>
        
        <?php if(empty($apnList)): ?>
            <div style="padding:40px 20px; text-align:center; color:var(--sub);">
                <iconify-icon icon="tabler:database-off" style="font-size:32px; margin-bottom:10px; opacity:0.5"></iconify-icon>
                <div>No APN Configuration found.</div>
                <div style="font-size:0.8rem; margin-top:5px">Numeric: <?= $target_numeric ?? 'N/A' ?></div>
            </div>
        <?php else: foreach($apnList as $apn): $act = ($apn['id'] == $currentApnId); ?>
            <div class="apn-item <?= $act ? 'active' : '' ?>">
                <form method="POST" class="radio-wrapper" onclick="this.submit()">
                    <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
                    <input type="hidden" name="set_apn" value="1">
                    <input type="hidden" name="apn_id" value="<?= $apn['id'] ?>">
                    <div class="custom-radio"></div>
                    <div class="apn-text">
                        <div><?= htmlspecialchars($apn['name']) ?></div>
                        <div><?= htmlspecialchars($apn['apn']) ?></div>
                    </div>
                </form>
                <div class="apn-actions">
                    <div onclick="editApn(<?= $apn['id'] ?>)" class="icon-btn"><iconify-icon icon="tabler:pencil"></iconify-icon></div>
                    <form method="POST" onsubmit="return confirm('Delete this APN?');" style="display:inline">
                        <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
                        <input type="hidden" name="delete_apn" value="1">
                        <input type="hidden" name="apn_id" value="<?= $apn['id'] ?>">
                        <button class="icon-btn danger"><iconify-icon icon="tabler:trash"></iconify-icon></button>
                    </form>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
    
    <div onclick="addApn()" class="fab"><iconify-icon icon="tabler:plus"></iconify-icon></div>
    <form method="POST" onsubmit="return confirm('Reset APN to default?')" style="display:inline">
        <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
        <button name="reset_apn" class="fab-reset"><iconify-icon icon="tabler:refresh"></iconify-icon></button>
    </form>


    <?php else: 
        $action = $_GET['action'];
        $title = ($action == 'edit') ? 'Edit Configuration' : 'New Configuration';
        $d = ($action == 'edit') ? $editApnData : [];
    ?>
    
    <div class="card">
        <h3><?= $title ?></h3>
        <form method="POST" onsubmit="showL()">
            <input type="hidden" name="ui_sim_id" value="<?= $ui_sim_tab ?>">
            <?php if($action=='edit'): ?><input type="hidden" name="id" value="<?= $_GET['id'] ?>"><?php endif; ?>
            
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?= $d['name']??'' ?>" required placeholder="e.g. Telkomsel Internet">
            </div>
            
            <div class="form-group">
                <label>APN</label>
                <input type="text" name="apn" value="<?= $d['apn']??'' ?>" required placeholder="e.g. internet">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Proxy</label>
                    <input type="text" name="proxy" value="<?= $d['proxy']??'' ?>" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="text" name="port" value="<?= $d['port']??'' ?>" placeholder="Optional">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="user" value="<?= $d['user']??'' ?>" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" value="<?= $d['password']??'' ?>" placeholder="Optional">
                </div>
            </div>

            <div class="form-group">
                <label>Authentication Type</label>
                <select name="authtype">
                    <option value="-1">None</option>
                    <option value="1" <?= ($d['authtype']??'')=='1'?'selected':'' ?>>PAP</option>
                    <option value="2" <?= ($d['authtype']??'')=='2'?'selected':'' ?>>CHAP</option>
                    <option value="3" <?= ($d['authtype']??'')=='3'?'selected':'' ?>>PAP or CHAP</option>
                </select>
            </div>
            
            <div class="btn-row">
                <button type="button" class="btn btn-sec" onclick="window.location.href='?ui_sim_id=<?= $ui_sim_tab ?>'">
                    <iconify-icon icon="tabler:x"></iconify-icon> Cancel
                </button>
                <button type="submit" name="<?= $action ?>_apn" class="btn btn-primary">
                    <iconify-icon icon="tabler:check"></iconify-icon> Save
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="loader" id="ldr"><div class="spinner"></div></div>

<script>
function showL() { document.getElementById('ldr').style.display='flex'; }
function selectSim(n) {
    showL();
    const f = document.getElementById('simForm');
    f.querySelector('input[name="ui_sim_id"]').value = n;
    f.submit();
}
function addApn() { window.location.href = "?action=add&ui_sim_id=" + <?= $ui_sim_tab ?>; }
function editApn(id) { window.location.href = "?action=edit&id=" + id + "&ui_sim_id=" + <?= $ui_sim_tab ?>; }
</script>
</body>
</html>