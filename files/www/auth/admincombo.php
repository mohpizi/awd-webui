<?php
session_start();
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

if (isset($_SESSION['login_disabled']) && $_SESSION['login_disabled'] === true) {} else { checkUserLogin(); }

$credentials_file = __DIR__ . '/credentials.php';
$config_file = __DIR__ . '/config.json';

$credentials = include $credentials_file;
$stored_username = $credentials['username'];

$config = @json_decode(file_get_contents($config_file), true);
if (!is_array($config)) $config = [];

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_creds') {
        $new_u = $_POST['new_username'];
        $new_p = $_POST['new_password'];
        $cnf_p = $_POST['confirm_new_password'];

        if ($new_p === $cnf_p) {
            $hash = password_hash($new_p, PASSWORD_DEFAULT);
            $content = "<?php\nif (basename(__FILE__) == basename(\$_SERVER['PHP_SELF'])) { header('Location: /'); exit; }\nreturn ['username' => '" . addslashes($new_u) . "', 'hashed_password' => '" . addslashes($hash) . "'];\n";
            
            if (@file_put_contents($credentials_file, $content) === false) {
                shell_exec("su -c 'echo \"".str_replace('"', '\"', $content)."\" > \"$credentials_file\"'");
            }
            $msg = 'Credentials Updated!'; $msg_type = 'success'; $stored_username = $new_u;
        } else {
            $msg = 'Passwords do not match!'; $msg_type = 'error';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_config') {
        $config['LOGIN_ENABLED'] = isset($_POST['login_enabled']);
        $json_data = json_encode($config, JSON_PRETTY_PRINT);
        
        // Coba tulis biasa, jika gagal gunakan Root
        $write = @file_put_contents($config_file, $json_data);
        if ($write === false) {
            $safe_json = str_replace("'", "'\\''", $json_data);
            shell_exec("su -c 'echo \"$safe_json\" > \"$config_file\"'");
        }

        if(isset($_POST['ajax'])) { echo json_encode(['status'=>'success', 'state'=>$config['LOGIN_ENABLED']]); exit; }
        $msg = 'Settings Saved!'; $msg_type = 'success';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Security Admin</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-h: #ef6c00; --suc: #2dce89; --dang: #f5365c;
            --tgl-bg: #cbd5e1; --tgl-act: #fb8c00; --inp: #ffffff;
            --shd: 0 4px 6px -1px rgba(0,0,0,0.05); --rad: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-h: #ffa726; --tgl-bg: #4b5563; --tgl-act: #ff9800;
                --shd: 0 4px 6px -1px rgba(0,0,0,0.4); --inp: #2c2c2c;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .con { width: 100%; max-width: 500px; }
        
        .head { text-align: center; margin-bottom: 30px; }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--pri); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        p { color: var(--sub); font-size: 0.9rem; }

        .card { background: var(--card); border-radius: var(--rad); padding: 24px; box-shadow: var(--shd); border: 1px solid var(--border); margin-bottom: 20px; }
        
        .row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .lbl-main { font-size: 1rem; font-weight: 700; color: var(--text); }
        .lbl-sub { font-size: 0.8rem; color: var(--sub); display: block; margin-top: 2px; }

        .sw { position: relative; display: inline-block; width: 52px; height: 28px; }
        .sw input { opacity: 0; width: 0; height: 0; }
        .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--tgl-bg); transition: .4s; border-radius: 34px; }
        .sl:before { position: absolute; content: ""; height: 22px; width: 22px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        input:checked + .sl { background-color: var(--tgl-act); }
        input:checked + .sl:before { transform: translateX(24px); }

        .div { border-top: 1px dashed var(--border); margin: 20px 0; }

        .grp { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; color: var(--sub); }
        input[type=text], input[type=password] { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--inp); color: var(--text); font-size: 1rem; transition: 0.2s; }
        input:focus { border-color: var(--pri); box-shadow: 0 0 0 3px rgba(251, 140, 0, 0.2); }

        .btn { width: 100%; padding: 14px; margin-top: 10px; border: none; border-radius: 10px; background: var(--pri); color: white; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn:hover { background: var(--pri-h); transform: translateY(-1px); }
        .btn:active { transform: translateY(1px); }

        #toast { visibility: hidden; min-width: 250px; background: #333; color: #fff; text-align: center; border-radius: 50px; padding: 12px; position: fixed; z-index: 100; bottom: 30px; left: 50%; transform: translateX(-50%); box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-weight: 600; font-size: 0.9rem; opacity: 0; transition: 0.3s; }
        #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
        #toast.err { background: var(--dang); } #toast.suc { background: var(--suc); }
    </style>
</head>
<body>

    <div class="con">
        <div class="head">
            <h1>Administration</h1>
            <p>Manage Security & Access</p>
        </div>

        <?php if ($msg): ?>
            <div id="php-msg" data-type="<?= $msg_type ?>" data-text="<?= $msg ?>"></div>
        <?php endif; ?>

        <div class="card">
            <div class="row">
                <div>
                    <span class="lbl-main">Login Page</span>
                    <span class="lbl-sub">Enable/Disable Authentication</span>
                </div>
                <label class="sw">
                    <input type="checkbox" id="loginToggle" <?= (isset($config['LOGIN_ENABLED']) && $config['LOGIN_ENABLED']) ? 'checked' : '' ?>>
                    <span class="sl"></span>
                </label>
            </div>

            <div class="div"></div>

            <form method="POST">
                <input type="hidden" name="action" value="update_creds">
                <div class="grp">
                    <label>Username</label>
                    <input type="text" name="new_username" value="<?= htmlspecialchars($stored_username) ?>" required>
                </div>
                <div class="grp">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="4">
                </div>
                <div class="grp">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_new_password" required minlength="4">
                </div>
                <button type="submit" class="btn">Save Credentials</button>
            </form>
        </div>
    </div>

    <div id="toast">Saved!</div>

    <script>
        const t = document.getElementById("toast");
        const pm = document.getElementById("php-msg");
        
        function show(m, type='suc') { 
            t.innerText = m; t.className = "show " + (type==='error'?'err':'suc'); 
            setTimeout(() => t.className = "", 3000); 
        }

        if (pm) show(pm.dataset.text, pm.dataset.type);

        document.getElementById('loginToggle').addEventListener('change', function() {
            const s = this.checked;
            const fd = new FormData();
            fd.append('action', 'update_config');
            fd.append('ajax', '1');
            if(s) fd.append('login_enabled', 'on');

            fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') show(s ? 'Login Enabled' : 'Login Disabled');
                else { show('Update Failed', 'error'); this.checked = !s; }
            })
            .catch(() => { show('Connection Error', 'error'); this.checked = !s; });
        });
    </script>

</body>
</html>