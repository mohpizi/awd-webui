<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

// Bypass SELinux & Root Prep
shell_exec("su -c 'setenforce 0' 2>&1");

// --- FUNCTIONS (Hybrid Logic) ---

function executeCmd($cmd) {
    return shell_exec("su -mm -c " . escapeshellarg($cmd) . " 2>&1");
}

function isTermuxApiAvailable() {
    $check = shell_exec("su -c 'pm path com.termux.api'");
    return (!empty($check) && strpos($check, 'package:') !== false);
}

function getSmsMessages() {
    $messages = [];
    if (isTermuxApiAvailable()) {
        $output = executeCmd("termux-sms-list -l 500");
        $data = json_decode($output, true);
        if (!empty($data) && is_array($data)) {
            foreach ($data as $sms) {
                $messages[] = [
                    'id'      => $sms['_id'] ?? $sms['id'] ?? 0,
                    'address' => $sms['number'] ?? $sms['address'] ?? 'Unknown',
                    'body'    => $sms['body'] ?? '',
                    'date'    => isset($sms['received']) ? strtotime($sms['received']) * 1000 : time() * 1000
                ];
            }
            return $messages;
        }
    }
    // Fallback Root (Android 14 Compatible)
    $cmd = "content query --uri content://sms --projection _id:address:date:body --sort 'date DESC LIMIT 500'";
    $raw = executeCmd($cmd);
    if (!empty($raw) && strpos($raw, 'Row:') !== false) {
        $rows = explode("Row:", $raw);
        foreach ($rows as $row) {
            $row = trim($row); if (empty($row)) continue;
            $id = $address = $date = $body = "";
            if (preg_match('/_id=(\d+)/', $row, $m)) $id = $m[1];
            if (preg_match('/address=(.*?),/', $row, $m)) $address = trim($m[1]);
            if (preg_match('/date=(\d+)/', $row, $m)) $date = $m[1];
            if (preg_match('/body=(.*)/s', $row, $m)) $body = $m[1];
            if ($id) {
                $messages[] = [
                    'id' => $id, 'address' => $address ?: 'Unknown',
                    'body' => $body, 'date' => (strlen($date) > 10) ? (int)$date : (int)$date * 1000
                ];
            }
        }
    }
    return $messages;
}

function sendSms($number, $message) {
    if (isTermuxApiAvailable()) {
        $res = executeCmd("termux-sms-send -n " . escapeshellarg($number) . " " . escapeshellarg($message));
        if (empty($res) || strpos($res, "not found") === false) return true;
    }
    $dest = escapeshellarg($number); $text = escapeshellarg($message); $pkg = "\"com.android.shell\"";
    executeCmd("service call isms 5 s16 $pkg s16 $dest s16 \"\" s16 $text i32 0 i32 0 i64 0 i32 1");
    executeCmd("service call isms 7 i32 0 s16 $pkg s16 $dest s16 \"\" s16 $text i32 0 i32 0 i32 1");
    return true;
}

function deleteSms($id) {
    return executeCmd("content delete --uri content://sms --where \"_id=" . intval($id) . "\"");
}

function getSystemLogs() {
    return executeCmd("logcat -d -t 100 *:W | grep -iE 'sms|isms|radio' | tail -n 25");
}

// --- LOGIC HANDLING ---
$activeTab = $_GET['tab'] ?? 'inbox';
$notification = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'send') {
            sendSms($_POST['number'], $_POST['message']);
            $notification = "Terkirim!";
        } elseif ($_POST['action'] === 'delete') {
            deleteSms($_POST['id']);
            $notification = "Dihapus.";
        }
    }
}

// Fetch & Grouping
$allMessages = ($activeTab == 'inbox') ? getSmsMessages() : [];
$selectedSender = $_GET['sender'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// 1. Filter data
$filtered = array_filter($allMessages, function($m) use ($selectedSender, $searchQuery) {
    $matchSender = empty($selectedSender) || $m['address'] === $selectedSender;
    $matchSearch = empty($searchQuery) || stripos($m['body'], $searchQuery) !== false;
    return $matchSender && $matchSearch;
});

// 2. Group by Sender
$grouped = [];
foreach ($filtered as $m) {
    $grouped[$m['address']][] = $m;
}

$uniqueSenders = array_unique(array_column($allMessages, 'address'));
sort($uniqueSenders);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Manager</title>
    <style>
        :root {
            --bg: #f4f7f6; --card: #ffffff; --text: #333; --border: #e0e0e0;
            --primary: #fb8c00; --accent: #fff3e0; --danger: #ff5252;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #121212; --card: #1e1e1e; --text: #eee; --border: #333; --accent: #2c1d10; }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 12px; line-height: 1.5; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .tabs { display: flex; gap: 8px; margin-bottom: 12px; }
        .tab-link { flex: 1; text-align: center; padding: 10px; background: var(--card); border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: var(--text); font-size: 0.8rem; font-weight: 600; }
        .tab-link.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        .filter-bar { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 10px; margin-bottom: 12px; display: flex; gap: 8px; }
        .filter-input { flex: 1; padding: 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: inherit; font-size: 0.8rem; }
        .filter-btn { background: var(--text); color: var(--card); border: none; padding: 8px 12px; border-radius: 6px; font-size: 0.8rem; }

        /* Conversation Card */
        .conv-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 10px; overflow: hidden; }
        .conv-header { padding: 14px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .conv-header:active { background: var(--bg); }
        .sender-info { font-weight: 800; color: var(--primary); font-size: 0.9rem; }
        .msg-count { font-size: 0.7rem; background: var(--accent); color: var(--primary); padding: 2px 8px; border-radius: 10px; }
        
        .conv-body { display: none; padding: 0 14px 14px 14px; border-top: 1px dashed var(--border); }
        .conv-body.open { display: block; }
        
        .sub-msg { padding: 10px 0; border-bottom: 1px solid var(--bg); position: relative; }
        .sub-msg:last-child { border-bottom: none; }
        .sub-date { font-size: 0.65rem; color: #888; display: block; margin-bottom: 4px; }
        .sub-text { font-size: 0.85rem; word-break: break-word; }
        .del-btn { color: var(--danger); font-size: 0.65rem; background: none; border: none; font-weight: bold; cursor: pointer; margin-top: 5px; }

        .form-box { background: var(--card); padding: 15px; border-radius: 12px; border: 1px solid var(--border); }
        .log-box { background: #000; color: #0f0; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 10px; overflow-x: auto; white-space: pre-wrap; }
        .notif { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 8px 20px; border-radius: 20px; font-size: 0.75rem; z-index: 100; }
    </style>
</head>
<body>

    <div class="header">
        <h2 style="font-size: 1rem;">SMS Viewer</h2>
        <span style="font-size: 0.6rem; opacity: 0.6;"><?= isTermuxApiAvailable() ? 'API' : 'ROOT' ?> MODE</span>
    </div>

    <?php if($notification): ?> <div class="notif" id="notif"><?= $notification ?></div> <?php endif; ?>

    <nav class="tabs">
        <a href="?tab=inbox" class="tab-link <?= $activeTab=='inbox'?'active':'' ?>">Inbox</a>
        <a href="?tab=send" class="tab-link <?= $activeTab=='send'?'active':'' ?>">Kirim</a>
        <a href="?tab=logs" class="tab-link <?= $activeTab=='logs'?'active':'' ?>">Log</a>
    </nav>

    <?php if ($activeTab == 'inbox'): ?>
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="inbox">
            <select name="sender" class="filter-input" onchange="this.form.submit()">
                <option value="">Semua</option>
                <?php foreach($uniqueSenders as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $selectedSender==$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="filter-input" placeholder="Cari..." value="<?= htmlspecialchars($searchQuery) ?>">
            <button type="submit" class="filter-btn">Cari</button>
        </form>

        <?php if(empty($grouped)): ?>
            <p style="text-align:center; padding: 40px; color: #888;">Kosong.</p>
        <?php else: ?>
            <?php foreach($grouped as $sender => $msgs): ?>
                <div class="conv-card">
                    <div class="conv-header" onclick="this.nextElementSibling.classList.toggle('open')">
                        <div>
                            <span class="sender-info"><?= htmlspecialchars($sender) ?></span>
                            <div style="font-size: 0.7rem; color: #888;"><?= htmlspecialchars(substr($msgs[0]['body'], 0, 40)) ?>...</div>
                        </div>
                        <span class="msg-count"><?= count($msgs) ?></span>
                    </div>
                    <div class="conv-body">
                        <?php foreach($msgs as $m): ?>
                            <div class="sub-msg">
                                <span class="sub-date"><?= date('d M Y, H:i', $m['date']/1000) ?></span>
                                <div class="sub-text"><?= htmlspecialchars($m['body']) ?></div>
                                <form method="POST" onsubmit="return confirm('Hapus?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="del-btn">HAPUS</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php elseif ($activeTab == 'send'): ?>
        <div class="form-box">
            <form method="POST">
                <input type="hidden" name="action" value="send">
                <input type="text" name="number" class="filter-input" style="width:100%; margin-bottom:10px;" placeholder="Tujuan" required>
                <textarea name="message" class="filter-input" style="width:100%; height:100px; margin-bottom:10px;" placeholder="Pesan..." required></textarea>
                <button type="submit" class="tab-link active" style="width:100%; border:none;">KIRIM SMS</button>
            </form>
        </div>

    <?php elseif ($activeTab == 'logs'): ?>
        <div class="form-box">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size: 0.8rem;">
                <strong>Logs</strong>
                <a href="?tab=logs" style="color: var(--primary); text-decoration:none;">Refresh</a>
            </div>
            <div class="log-box"><?= htmlspecialchars(getSystemLogs()) ?: 'Log kosong.' ?></div>
        </div>
    <?php endif; ?>

    <script>
        setTimeout(() => { if(document.getElementById('notif')) document.getElementById('notif').style.display = 'none'; }, 2000);
    </script>
</body>
</html>
