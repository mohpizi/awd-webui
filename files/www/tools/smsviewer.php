<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

// --- FUNCTIONS ---

function executeCmd($cmd) {
    // Menggunakan su -c agar command berjalan sebagai root penuh (diperlukan untuk hapus sms)
    return shell_exec("su -c " . escapeshellarg($cmd) . " 2>&1");
}

function getSmsMessages() {
    $command = "/data/data/com.termux/files/usr/bin/termux-sms-list -l 500 -t inbox"; 
    $output = executeCmd($command);
    $data = json_decode($output, true);

    if (empty($data) || !is_array($data)) return [];

    $messages = [];
    foreach ($data as $sms) {
        $messages[] = [
            // Kita butuh ID untuk menghapus pesan
            'id'      => isset($sms['_id']) ? $sms['_id'] : (isset($sms['id']) ? $sms['id'] : 0),
            'address' => isset($sms['number']) ? $sms['number'] : (isset($sms['address']) ? $sms['address'] : 'Unknown'),
            'body'    => isset($sms['body']) ? $sms['body'] : '',
            'date'    => isset($sms['received']) ? strtotime($sms['received']) * 1000 : time() * 1000
        ];
    }
    return $messages;
}

function sendSms($number, $message) {
    // Sanitasi input agar aman di shell
    $safeNumber = escapeshellarg($number);
    $safeMessage = escapeshellarg($message);
    $cmd = "/data/data/com.termux/files/usr/bin/termux-sms-send -n $safeNumber $safeMessage";
    executeCmd($cmd);
    return true; 
}

function deleteSms($id) {
    // Hapus via SQLite Database (Memerlukan Root)
    // Path database SMS standar Android
    $dbPath = "/data/data/com.android.providers.telephony/databases/mmssms.db";
    $id = intval($id);
    if($id > 0) {
        $cmd = "sqlite3 $dbPath \"DELETE FROM sms WHERE _id = $id;\"";
        executeCmd($cmd);
        return true;
    }
    return false;
}

function getAvatarColor($str) {
    $hash = md5($str);
    $r = hexdec(substr($hash, 0, 2));
    $g = hexdec(substr($hash, 2, 2));
    $b = hexdec(substr($hash, 4, 2));
    return "rgba(" . $r . ", " . $g . ", " . $b . ", 0.8)";
}

function getInitial($str) {
    return strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $str), 0, 1));
}

// --- HANDLE POST REQUESTS ---
$notification = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'send') {
            $num = $_POST['number'] ?? '';
            $msg = $_POST['message'] ?? '';
            if (!empty($num) && !empty($msg)) {
                sendSms($num, $msg);
                $notification = "Pesan berhasil dikirim ke $num";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'] ?? 0;
            if ($id > 0) {
                deleteSms($id);
                $notification = "Pesan dihapus.";
            }
        }
    }
}

// --- PREPARE DATA ---
$smsMessages = getSmsMessages();

usort($smsMessages, function($a, $b) { return $b['date'] - $a['date']; });
$uniqueSenders = array_unique(array_column($smsMessages, 'address'));
sort($uniqueSenders);

$selectedSender = isset($_GET['sender']) ? $_GET['sender'] : '';
$searchQuery    = isset($_GET['search']) ? $_GET['search'] : '';
$activeTab      = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';

if ($selectedSender) {
    $smsMessages = array_filter($smsMessages, function($m) use ($selectedSender) {
        return $m['address'] === $selectedSender;
    });
}
if ($searchQuery) {
    $smsMessages = array_filter($smsMessages, function($m) use ($searchQuery) {
        return stripos($m['body'], $searchQuery) !== false;
    });
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$total = count($smsMessages);
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;
$displayData = array_slice($smsMessages, $offset, $perPage);

function url($p) {
    global $selectedSender, $searchQuery, $activeTab;
    $url = "?page=$p&tab=$activeTab";
    if ($selectedSender) $url .= "&sender=" . urlencode($selectedSender);
    if ($searchQuery) $url .= "&search=" . urlencode($searchQuery);
    return $url;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Manager</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --primary: #fb8c00; --primary-fg: #ffffff; --accent: #fff3e0;
            --danger: #e53e3e; --radius: 12px; --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --primary: #ff9800; --primary-fg: #1e1e1e; --accent: #3e2723;
                --danger: #f56565; --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: var(--bg); color: var(--text); padding: 16px; max-width: 800px; margin: 0 auto; padding-bottom: 80px; }
        
        .head { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--primary); margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        
        /* TABS */
        .tabs { display: flex; background: var(--card); padding: 4px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 20px; }
        .tab-btn { flex: 1; text-align: center; padding: 10px; cursor: pointer; border-radius: 8px; font-weight: 600; font-size: 0.9rem; color: var(--sub); transition: 0.2s; text-decoration: none; }
        .tab-btn.active { background: var(--accent); color: var(--primary); }
        .tab-btn:hover:not(.active) { background: var(--bg); }

        /* FILTERS & FORM */
        .filters { background: var(--card); padding: 10px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; gap: 8px; margin-bottom: 20px; position: sticky; top: 10px; z-index: 10; }
        .sel, .inp, .txtarea { border: 1px solid var(--border); background: var(--bg); padding: 12px; font-size: 0.95rem; color: var(--text); border-radius: 8px; flex: 1; transition: border 0.2s; font-family: inherit; }
        .sel:focus, .inp:focus, .txtarea:focus { border-color: var(--primary); }
        .txtarea { min-height: 120px; resize: vertical; display: block; width: 100%; margin-bottom: 15px; }
        
        .btn { background: var(--primary); color: var(--primary-fg); border: none; padding: 0 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; height: 42px; }
        .btn:active { transform: scale(0.95); }
        .btn-full { width: 100%; font-size: 1rem; }

        /* LIST */
        .list { display: flex; flex-direction: column; gap: 10px; }
        .msg { background: var(--card); border-radius: var(--radius); padding: 16px; border: 1px solid var(--border); display: flex; gap: 14px; transition: 0.2s; box-shadow: var(--shadow); position: relative; }
        
        .ava { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 1.1rem; flex-shrink: 0; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
        
        .ctx { flex: 1; min-width: 0; }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .snd { font-weight: 700; font-size: 0.95rem; color: var(--text); }
        .tm { font-size: 0.7rem; color: var(--sub); background: var(--bg); padding: 2px 8px; border-radius: 12px; border: 1px solid var(--border); }

        .txt { font-size: 0.9rem; color: var(--sub); line-height: 1.5; word-wrap: break-word; cursor: pointer; }
        .clp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-height: 3.2em; }
        .exp { display: block; -webkit-line-clamp: unset; max-height: none; color: var(--text); }
        
        /* DELETE BUTTON */
        .del-btn { background: transparent; border: none; color: var(--sub); cursor: pointer; padding: 5px; opacity: 0.6; transition: 0.2s; margin-left: 5px; }
        .del-btn:hover { color: var(--danger); opacity: 1; transform: scale(1.1); }

        /* PAGINATION */
        .pgn { display: flex; justify-content: center; gap: 10px; margin-top: 30px; }
        .pg { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; text-decoration: none; color: var(--text); background: var(--card); border: 1px solid var(--border); border-radius: 10px; font-weight: 700; transition: 0.2s; }
        .pg.act { background: var(--primary); color: var(--primary-fg); border-color: var(--primary); }
        .pg.dis { opacity: 0.5; pointer-events: none; }

        /* FORM CONTAINER */
        .form-card { background: var(--card); border-radius: var(--radius); padding: 20px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .form-group { margin-bottom: 15px; }
        .label { display: block; margin-bottom: 8px; font-size: 0.9rem; font-weight: 600; color: var(--text); }

        /* NOTIFICATION */
        .notif { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 12px 24px; border-radius: 30px; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 100; animation: fadeUp 0.3s ease; }
        @keyframes fadeUp { from { transform: translate(-50%, 20px); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }

        @media (max-width: 600px) { .filters { flex-direction: column; } }
    </style>
</head>
<body>

    <div class="head">
        <h1>SMS Manager</h1>
        <?php if(!empty($notification)): ?>
            <div class="notif" id="notif"><?= htmlspecialchars($notification) ?></div>
            <script>setTimeout(() => { document.getElementById('notif').style.display='none'; }, 3000);</script>
        <?php endif; ?>
    </div>

    <div class="tabs">
        <a href="?tab=inbox" class="tab-btn <?= $activeTab == 'inbox' ? 'active' : '' ?>">Inbox (<?= $total ?>)</a>
        <a href="?tab=send" class="tab-btn <?= $activeTab == 'send' ? 'active' : '' ?>">Tulis Pesan</a>
    </div>

    <?php if ($activeTab == 'send'): ?>
        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="action" value="send">
                <div class="form-group">
                    <label class="label">Nomor Tujuan</label>
                    <input type="text" name="number" class="inp" placeholder="Contoh: 08123456789" required style="width: 100%">
                </div>
                <div class="form-group">
                    <label class="label">Isi Pesan</label>
                    <textarea name="message" class="txtarea" placeholder="Tulis pesan anda disini..." required></textarea>
                </div>
                <button type="submit" class="btn btn-full">Kirim SMS</button>
            </form>
        </div>

    <?php else: ?>
        <form method="GET" class="filters">
            <input type="hidden" name="tab" value="inbox">
            <select name="sender" class="sel" onchange="this.form.submit()">
                <option value="">Semua Pengirim</option>
                <?php foreach ($uniqueSenders as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $selectedSender === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" class="inp" placeholder="Cari pesan..." value="<?= htmlspecialchars($searchQuery) ?>" autocomplete="off">
            <button type="submit" class="btn">Cari</button>
        </form>

        <div class="list">
            <?php if (empty($displayData)): ?>
                <div style="text-align:center; padding: 50px 0; color: var(--sub);">Tidak ada pesan.</div>
            <?php else: ?>
                <?php foreach ($displayData as $msg): ?>
                    <div class="msg">
                        <div class="ava" style="background-color: <?= getAvatarColor($msg['address']) ?>;">
                            <?= getInitial($msg['address']) ?>
                        </div>
                        <div class="ctx">
                            <div class="top">
                                <span class="snd"><?= htmlspecialchars($msg['address']) ?></span>
                                <div style="display:flex; align-items:center;">
                                    <span class="tm"><?= date('d M, H:i', $msg['date'] / 1000) ?></span>
                                    <form method="POST" onsubmit="return confirm('Hapus pesan ini?')" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <button type="submit" class="del-btn" title="Hapus">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="txt clp" onclick="tog(this)"><?= nl2br(htmlspecialchars($msg['body'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($pages > 1): ?>
            <div class="pgn">
                <a href="<?= url($page - 1) ?>" class="pg <?= $page <= 1 ? 'dis' : '' ?>">&larr;</a>
                <span class="pg act"><?= $page ?></span>
                <a href="<?= url($page + 1) ?>" class="pg <?= $page >= $pages ? 'dis' : '' ?>">&rarr;</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<script>
    function tog(el) {
        if (el.classList.contains('clp')) {
            el.classList.remove('clp'); el.classList.add('exp');
        } else {
            el.classList.remove('exp'); el.classList.add('clp');
        }
    }
</script>

</body>
</html>