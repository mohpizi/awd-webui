<?php
$targetScript = '/data/adb/post-fs-data.d/module_fix.sh'; 
$modulesDir   = '/data/adb/modules';

function runCmd($cmd) {
    return shell_exec("su -c '$cmd'");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $blacklisted = isset($_POST['modules']) ? $_POST['modules'] : [];
    $newBlacklistStr = "\n" . implode("\n", $blacklisted) . "\n";
    
    if (!file_exists($targetScript)) {
        $defaultContent = <<<'EOD'
#!/system/bin/sh
BLACKLIST_MODULES="
zn_magisk_compat
"
MODULES_DIR="/data/adb/modules"
if [ -d "$MODULES_DIR" ]; then
    for MODULE_PATH in "$MODULES_DIR"/*; do
        if [ -d "$MODULE_PATH" ]; then
            MODULE_NAME=$(basename "$MODULE_PATH")
            DISABLE_FILE="$MODULE_PATH/disable"
            case "$BLACKLIST_MODULES" in
                *"$MODULE_NAME"*) continue ;;
            esac
            if [ -f "$DISABLE_FILE" ]; then
                rm -f "$DISABLE_FILE"
            fi
        fi
    done
fi
EOD;
        $tmpInit = tempnam(sys_get_temp_dir(), 'init_mod');
        file_put_contents($tmpInit, $defaultContent);
        runCmd("cat $tmpInit > $targetScript");
        runCmd("chmod 755 $targetScript");
        unlink($tmpInit);
    }

    if (file_exists($targetScript)) {
        $content = file_get_contents($targetScript);
        $newContent = preg_replace(
            '/BLACKLIST_MODULES="([^"]*)"/s', 
            'BLACKLIST_MODULES="' . $newBlacklistStr . '"', 
            $content
        );
        $tmpFile = tempnam(sys_get_temp_dir(), 'bl_edit');
        file_put_contents($tmpFile, $newContent);
        runCmd("cat $tmpFile > $targetScript");
        runCmd("chmod 755 $targetScript");
        unlink($tmpFile);
        $message = "Blacklist Updated!";
        $msgType = "success";
    } else {
        $message = "Failed to write script!";
        $msgType = "error";
    }
}

$installedModules = [];
if (is_dir($modulesDir)) {
    $dirs = scandir($modulesDir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        if (is_dir("$modulesDir/$dir")) {
            $propFile = "$modulesDir/$dir/module.prop";
            $name = $dir;
            if (file_exists($propFile)) {
                $lines = file($propFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, 'name=') === 0) {
                        $name = trim(substr($line, 5));
                        break;
                    }
                }
            }
            $installedModules[$dir] = $name;
        }
    }
}

$currentBlacklist = [];
if (file_exists($targetScript)) {
    $content = file_get_contents($targetScript);
    if (preg_match('/BLACKLIST_MODULES="([^"]*)"/s', $content, $matches)) {
        $currentBlacklist = preg_split('/\s+/', trim($matches[1]), -1, PREG_SPLIT_NO_EMPTY);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Module Fix</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-s: #fff3e0; --suc: #2dce89; --suc-s: #e6fffa;
            --dang: #f5365c; --dang-s: #fff5f5; --rad: 12px; --shd: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-s: rgba(255,152,0,0.15); --suc: #69db7c; --suc-s: rgba(43,138,62,0.2);
                --dang: #fc8181; --dang-s: rgba(252,129,129,0.15); --shd: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 800px; margin: 0 auto; padding-bottom: 80px; }
        
        header { text-align: center; margin-bottom: 25px; }
        h1 { font-size: 1.4rem; font-weight: 700; color: var(--pri); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        p { font-size: 0.9rem; color: var(--sub); }

        .card { background: var(--card); border-radius: var(--rad); padding: 20px; box-shadow: var(--shd); border: 1px solid var(--border); }
        
        .list { display: flex; flex-direction: column; gap: 10px; max-height: 60vh; overflow-y: auto; padding-right: 5px; }
        .item { display: flex; align-items: center; justify-content: space-between; padding: 15px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; transition: 0.2s; background: var(--bg); }
        .item:hover { border-color: var(--pri); background: var(--pri-s); }
        
        .info { flex: 1; padding-right: 15px; }
        .name { font-weight: 700; font-size: 0.95rem; display: block; margin-bottom: 2px; }
        .id { font-size: 0.75rem; color: var(--sub); font-family: monospace; }

        input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--dang); cursor: pointer; }

        .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; background: var(--pri); color: white; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: 20px; transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn:active { transform: scale(0.98); opacity: 0.9; }

        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .suc { background: var(--suc-s); color: var(--suc); border: 1px solid var(--suc); }
        .err { background: var(--dang-s); color: var(--dang); border: 1px solid var(--dang); }
        
        .note { text-align: center; font-size: 0.8rem; color: var(--sub); margin-top: 15px; font-style: italic; }
    </style>
</head>
<body>

    <header>
        <h1>Module Fix</h1>
        <p>Prevent specific modules from auto-enabling</p>
    </header>

    <?php if (isset($message)): ?>
        <div class="alert <?= ($msgType=='success')?'suc':'err' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card">
        <div class="list">
            <?php if(empty($installedModules)): ?>
                <div style="text-align:center; padding:30px; color:var(--sub)">No modules found.</div>
            <?php else: foreach ($installedModules as $id => $name): 
                $chk = in_array($id, $currentBlacklist) ? 'checked' : ''; ?>
                <label class="item">
                    <div class="info">
                        <span class="name"><?= htmlspecialchars($name) ?></span>
                        <span class="id"><?= htmlspecialchars($id) ?></span>
                    </div>
                    <input type="checkbox" name="modules[]" value="<?= htmlspecialchars($id) ?>" <?= $chk ?>>
                </label>
            <?php endforeach; endif; ?>
        </div>
        <button type="submit" name="save" class="btn">Save Blacklist</button>
    </form>
    
    <div class="note">Checked modules will remain disabled after reboot fix.</div>

</body>
</html>