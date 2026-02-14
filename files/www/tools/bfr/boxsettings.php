<?php
$ini_file_path = '/data/adb/box/settings.ini';

function parse_settings_ini($file_path) {
    if (!file_exists($file_path)) return [];
    $lines = file($file_path, FILE_IGNORE_NEW_LINES);
    $settings = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] == ';' || $line[0] == '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            $settings[$key] = $value;
        }
    }
    return $settings;
}

$settings = parse_settings_ini($ini_file_path);

$bool_list = ['port_detect', 'ipv6', 'cgroup_cpuset', 'cgroup_blkio', 'cgroup_memcg', 'run_crontab', 'update_geo', 'renew', 'update_subscription'];
$form_list = ['tproxy_port', 'redir_port', 'memcg_limit', 'subscription_url_clash', 'name_clash_config', 'name_sing_config'];
$dropdown_list = [
    'bin_name' => ['clash', 'sing-box', 'xray', 'v2fly'],
    'xclash_option' => ['mihomo', 'premium'],
    'network_mode' => ['redirect', 'tproxy', 'mixed', 'enhance', 'tun'],
    'proxy_mode' => ['blacklist', 'whitelist']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lines = file($ini_file_path, FILE_IGNORE_NEW_LINES);
    $new_content = [];
    
    foreach ($lines as $line) {
        $trimLine = trim($line);
        if ($trimLine === '' || $trimLine[0] == ';' || $trimLine[0] == '#') {
            $new_content[] = $line;
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            
            if (isset($_POST[$key])) {
                $orig_val = trim($val);
                $new_val = $_POST[$key];
                
                if (preg_match('/^".*"$/', $orig_val)) $new_val = '"' . $new_val . '"';
                elseif (preg_match("/^'.*'$/", $orig_val)) $new_val = "'" . $new_val . "'";
                
                $new_content[] = "$key=$new_val";
            } else {
                $new_content[] = $line;
            }
        } else {
            $new_content[] = $line;
        }
    }

    file_put_contents($ini_file_path, implode("\n", $new_content) . "\n");
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Box Config</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-h: #ef6c00; --inp-bg: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05); --radius: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #fb8c00; --pri-h: #ffa726; --inp-bg: #2c2c2c;
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 16px; max-width: 800px; margin: 0 auto; padding-bottom: 80px; }
        
        .card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); padding: 24px; }
        .head { margin-bottom: 24px; padding-bottom: 15px; border-bottom: 1px solid var(--border); }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--pri); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        p { color: var(--sub); font-size: 0.9rem; }

        .grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
        @media (min-width: 600px) { .grid { grid-template-columns: 1fr 1fr; } }
        .full { grid-column: 1 / -1; }

        .grp { margin-bottom: 5px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: var(--text); text-transform: capitalize; }
        input, select { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--inp-bg); color: var(--text); font-size: 0.95rem; transition: 0.2s; }
        input:focus, select:focus { border-color: var(--pri); box-shadow: 0 0 0 3px rgba(251, 140, 0, 0.2); }

        .btn { width: 100%; background: var(--pri); color: #fff; border: none; padding: 14px; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 25px; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn:hover { background: var(--pri-h); transform: translateY(-1px); }
        .btn:active { transform: translateY(1px); }
        .btn:disabled { opacity: 0.7; cursor: wait; }

        #toast { visibility: hidden; min-width: 250px; background: #4caf50; color: #fff; text-align: center; border-radius: 50px; padding: 12px; position: fixed; z-index: 100; bottom: 30px; left: 50%; transform: translateX(-50%); box-shadow: 0 4px 10px rgba(0,0,0,0.2); font-weight: 600; opacity: 0; transition: 0.3s; }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
    </style>
</head>
<body>

    <div class="card">
        <div class="head">
            <h1>Configuration</h1>
            <p>Edit core settings for Box</p>
        </div>

        <form id="cfgForm">
            <div class="grid">
                <?php foreach ($dropdown_list as $key => $options): ?>
                <div class="grp">
                    <label for="<?= $key ?>"><?= str_replace('_', ' ', $key) ?></label>
                    <select name="<?= $key ?>" id="<?= $key ?>">
                        <?php foreach ($options as $opt): 
                            $sel = (trim($settings[$key] ?? '') === $opt); ?>
                            <option value="<?= $opt ?>" <?= $sel ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>

                <?php foreach ($bool_list as $key): ?>
                <div class="grp">
                    <label for="<?= $key ?>"><?= str_replace('_', ' ', $key) ?></label>
                    <select name="<?= $key ?>" id="<?= $key ?>">
                        <option value="true" <?= ($settings[$key] ?? '') === 'true' ? 'selected' : '' ?>>Enabled</option>
                        <option value="false" <?= ($settings[$key] ?? '') === 'false' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <?php endforeach; ?>

                <?php foreach ($form_list as $key): ?>
                <div class="grp <?= (strpos($key, 'url') !== false || strpos($key, 'name') !== false) ? 'full' : '' ?>">
                    <label for="<?= $key ?>"><?= str_replace('_', ' ', $key) ?></label>
                    <input type="text" name="<?= $key ?>" id="<?= $key ?>" value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>

    <div id="toast">Settings Saved Successfully!</div>

    <script>
        document.getElementById('cfgForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.querySelector('.btn');
            const txt = btn.innerText;
            btn.innerText = 'Saving...'; btn.disabled = true;

            fetch('', { method: 'POST', body: new FormData(this) })
            .then(r => r.json())
            .then(d => { if(d.status === 'success') showToast(); })
            .finally(() => { btn.innerText = txt; btn.disabled = false; });
        });

        function showToast() {
            const t = document.getElementById("toast");
            t.className = "show";
            setTimeout(() => t.className = t.className.replace("show", ""), 3000);
        }
    </script>

</body>
</html>