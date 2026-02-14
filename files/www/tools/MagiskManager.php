<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

if (isset($_GET['install_stream']) && isset($_GET['file'])) {
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    while (ob_get_level() > 0) ob_end_flush();
    ob_implicit_flush(1);

    $file = '/data/local/tmp/' . basename($_GET['file']);
    
    if (!file_exists($file) || !str_ends_with($file, '.zip')) {
        echo "Error: Invalid file.";
        exit;
    }

    echo "\n";
    echo "Installing: " . htmlspecialchars(basename($file)) . "...\n";
    flush();

    $cmd = "magisk --install-module " . escapeshellarg($file) . " 2>&1";
    $proc = popen($cmd, 'r');
    
    if ($proc) {
        while (!feof($proc)) {
            $line = fgets($proc);
            if ($line !== false) {
                echo htmlspecialchars($line); 
                echo "<script>try{parent.scrollLog()}catch(e){}</script>"; 
                flush(); 
            }
        }
        pclose($proc);
    }
    
    unlink($file); 
    echo "\nDone. Please Reboot.\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zipfile'])) {
    $uploadDir = '/data/local/tmp/';
    $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['zipfile']['name']));
    $uploadFile = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['zipfile']['tmp_name'], $uploadFile)) {
        echo json_encode(['status' => 'success', 'file' => $fileName]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
    }
    exit;
}

function runCommand($cmd) {
    return shell_exec($cmd . ' 2>&1') ?? 'No output';
}

function getModules() {
    $modulesDir = '/data/adb/modules';
    $modules = [];
    if (is_dir($modulesDir)) {
        foreach (scandir($modulesDir) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            $modulePath = "$modulesDir/$dir";
            if (!is_dir($modulePath)) continue;
            
            $propFile = "$modulePath/module.prop";
            $props = [];
            if (file_exists($propFile)) {
                $content = @file_get_contents($propFile);
                if ($content) {
                    foreach (explode("\n", $content) as $line) {
                        $parts = explode('=', trim($line), 2);
                        if (count($parts) === 2) $props[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }
            $modules[] = [
                'id' => $dir,
                'name' => $props['name'] ?? $dir,
                'version' => $props['version'] ?? '?',
                'author' => $props['author'] ?? '?',
                'desc' => $props['description'] ?? '',
                'enabled' => !file_exists("$modulePath/disable"),
                'remove' => file_exists("$modulePath/remove")
            ];
        }
    }
    return $modules;
}

if (isset($_GET['action']) && isset($_GET['module'])) {
    $mod = escapeshellarg($_GET['module']);
    $path = "/data/adb/modules/" . $_GET['module'];
    switch ($_GET['action']) {
        case 'enable': @unlink("$path/disable"); break;
        case 'disable': @touch("$path/disable"); break;
        case 'remove': runCommand("touch \"$path/remove\""); break;
    }
    header("Location: ?tab=modules");
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'reboot') {
    runCommand("reboot");
    exit;
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'modules';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Magisk Manager</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --primary: #fb8c00; --primary-bg: #fff3e0;
            --success: #2dce89; --success-bg: #e6fffa;
            --warning: #fb6340; --warning-bg: #fff5f5;
            --danger: #f5365c; --danger-bg: #fff5f5;
            --radius: 12px; --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            --log-bg: #1e293b; --log-text: #f1f5f9; --log-head: #0f172a;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --primary: #ff9800; --primary-bg: rgba(255,152,0,0.15);
                --success: #68d391; --success-bg: rgba(104,211,145,0.15);
                --warning: #ffcc80; --warning-bg: rgba(255,167,38,0.15);
                --danger: #fc8181; --danger-bg: rgba(252,129,129,0.15);
                --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
                --log-bg: #000000; --log-text: #ff9800; --log-head: #2d2d2d;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 16px; max-width: 900px; margin: 0 auto; padding-bottom: 80px; }
        
        .card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); overflow: hidden; margin-bottom: 16px; padding: 20px; }
        .title { font-weight: 700; font-size: 1.1rem; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 10px; color: var(--text); }
        .title-left { display: flex; align-items: center; gap: 8px; }

        .btn { border: none; border-radius: 8px; padding: 8px 14px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 0.85rem; display: inline-flex; justify-content: center; align-items: center; gap: 6px; text-decoration: none; color: #fff; }
        .btn-sm { padding: 5px 12px; font-size: 0.75rem; border-radius: 6px; }
        .btn-p { background: var(--primary); color: #fff; }
        .btn-d { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        .btn-w { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .btn-s { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .icon { width: 20px; height: 20px; fill: currentColor; }

        .tabs { display: flex; gap: 10px; margin-bottom: 20px; background: var(--card); padding: 5px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); }
        .tab { flex: 1; text-align: center; padding: 10px; border-radius: 8px; font-weight: 600; text-decoration: none; color: var(--sub); transition: 0.2s; }
        .tab.active { background: var(--primary-bg); color: var(--primary); }

        .mod-item { border-bottom: 1px solid var(--border); padding: 16px 0; }
        .mod-item:last-child { border-bottom: none; padding-bottom: 0; }
        .mod-head { display: flex; justify-content: space-between; margin-bottom: 4px; align-items: center; }
        .mod-name { font-weight: 700; font-size: 1rem; color: var(--text); }
        .mod-ver { font-size: 0.75rem; color: var(--sub); background: var(--bg); padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border); }
        .mod-desc { font-size: 0.85rem; color: var(--sub); margin-bottom: 10px; line-height: 1.4; display: block; }
        .mod-auth { font-size: 0.75rem; color: var(--primary); margin-bottom: 8px; display: block; font-weight: 600; }
        .mod-acts { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }

        .upload-box { border: 2px dashed var(--border); border-radius: 12px; padding: 30px 20px; cursor: pointer; transition: 0.2s; background: var(--bg); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; min-height: 140px; }
        .upload-box:hover, .upload-box.drag-over { border-color: var(--primary); background: var(--primary-bg); transform: scale(1.01); }
        input[type="file"] { display: none; }
        
        .progress-area { display: none; margin-top: 20px; }
        .progress-track { width: 100%; background: var(--border); height: 8px; border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.1s linear; }
        .progress-text { font-size: 0.8rem; color: var(--sub); text-align: center; margin-top: 8px; font-weight: 600; display: flex; justify-content: space-between; }

        .console-wrap { display: none; margin-top: 15px; border-radius: 8px; border: 1px solid var(--border); overflow: hidden; }
        .console-head { background: var(--log-head); padding: 8px 12px; color: #aaa; font-size: 0.75rem; font-weight: bold; border-bottom: 1px solid var(--border); }
        #logFrame { width:100%; height:250px; border:none; background: var(--log-bg); }
        
        .log-box { background-color: var(--log-bg); color: var(--log-text); border: 1px solid var(--border); border-radius: 8px; height: 500px; overflow-y: auto; font-family: monospace; font-size: 0.8rem; padding: 10px; white-space: pre-wrap; }
        .log-err { color: #ff6b6b; } .log-warn { color: #ffd93d; }
    </style>
</head>
<body>

    <div class="tabs">
        <a href="?tab=modules" class="tab <?= $activeTab == 'modules' ? 'active' : '' ?>">Modules</a>
        <a href="?tab=logs" class="tab <?= $activeTab == 'logs' ? 'active' : '' ?>">Logs</a>
    </div>

    <?php if ($activeTab == 'modules'): ?>
        
        <div class="card">
            <div class="title">
                <div class="title-left">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg> Install Module
                </div>
                <a href="?action=reboot" class="btn btn-sm btn-d" onclick="return confirm('Reboot device now?')">
                    <svg class="icon" style="width:16px;height:16px" viewBox="0 0 24 24"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A1,1 0 0,0 11,7V11H7A1,1 0 0,0 6,12A1,1 0 0,0 7,13H13V7A1,1 0 0,0 12,6Z" transform="rotate(45 12 12)"/></svg> Reboot
                </a>
            </div>
            
            <label class="upload-box" id="dropBox">
                <input type="file" id="zipFile" accept=".zip" onchange="startInstall(this.files[0])">
                <div style="color: var(--primary); margin-bottom: 8px;">
                    <svg class="icon" style="width:40px; height:40px;" viewBox="0 0 24 24"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                </div>
                <div style="font-weight: 600; font-size: 1rem; color:var(--text)" id="dropText">Tap or Drag .zip Here</div>
                <div style="color: var(--sub); font-size: 0.85rem; margin-top: 4px;">Install Magisk Module</div>
            </label>

            <div id="progressArea" class="progress-area">
                <div class="progress-track">
                    <div class="progress-fill" id="progressBar"></div>
                </div>
                <div class="progress-text">
                    <span id="progressStatus">Uploading...</span>
                    <span id="progressPercent">0%</span>
                </div>
            </div>

            <div id="consoleArea" class="console-wrap">
                <div class="console-head">INSTALLATION LOG</div>
                <iframe id="logFrame" src=""></iframe>
            </div>
        </div>

        <div class="card">
            <div class="title">
                <div class="title-left">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg> Installed Modules
                </div>
            </div>
            
            <?php 
            $modules = getModules();
            if (empty($modules)): ?>
                <div style="text-align:center; padding:20px; color:var(--sub);">No modules found.</div>
            <?php else: 
                foreach ($modules as $mod): ?>
                <div class="mod-item" style="opacity: <?= ($mod['enabled'] && !($mod['remove'] ?? false)) ? '1' : '0.6' ?>">
                    <div class="mod-head">
                        <span class="mod-name"><?= htmlspecialchars($mod['name']) ?></span>
                        <span class="mod-ver"><?= htmlspecialchars($mod['version']) ?></span>
                    </div>
                    <span class="mod-auth">by <?= htmlspecialchars($mod['author']) ?></span>
                    <span class="mod-desc"><?= htmlspecialchars($mod['desc']) ?></span>
                    
                    <div class="mod-acts">
                        <?php if ($mod['enabled']): ?>
                            <a href="?tab=modules&action=disable&module=<?= urlencode($mod['id']) ?>" class="btn btn-sm btn-w">Disable</a>
                        <?php else: ?>
                            <a href="?tab=modules&action=enable&module=<?= urlencode($mod['id']) ?>" class="btn btn-sm btn-s">Enable</a>
                        <?php endif; ?>
                        
                        <a href="?tab=modules&action=remove&module=<?= urlencode($mod['id']) ?>" class="btn btn-sm btn-d" onclick="return confirm('Remove this module?')">Remove</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <script>
            const pgArea = document.getElementById('progressArea');
            const pgBar = document.getElementById('progressBar');
            const pgPct = document.getElementById('progressPercent');
            const pgStat = document.getElementById('progressStatus');
            const conArea = document.getElementById('consoleArea');
            const logFrame = document.getElementById('logFrame');

            window.scrollLog = function() {
                try {
                    let doc = logFrame.contentWindow.document;
                    if(!doc.body.dataset.styled) {
                        let style = doc.createElement('style');
                        style.textContent = `
                            body { font-family: monospace; font-size: 12px; margin: 10px; white-space: pre-wrap; }
                            @media (prefers-color-scheme: dark) {
                                body { background-color: #000000; color: #ff9800; }
                            }
                            @media (prefers-color-scheme: light) {
                                body { background-color: #1e293b; color: #f1f5f9; }
                            }
                        `;
                        doc.head.appendChild(style);
                        doc.body.dataset.styled = "true";
                    }
                    doc.scrollingElement.scrollTop = doc.scrollingElement.scrollHeight;
                } catch(e) {}
            };

            function startInstall(file) {
                if(!file || !file.name.endsWith('.zip')) { alert('Only .zip files!'); return; }

                pgArea.style.display = 'block';
                conArea.style.display = 'none';
                pgBar.style.width = '0%';
                pgBar.style.background = 'var(--primary)';
                pgPct.innerText = '0%';
                pgStat.innerText = 'Uploading: ' + file.name;

                let fd = new FormData();
                fd.append('zipfile', file);
                
                let xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener("progress", function(e) {
                    if (e.lengthComputable) {
                        let percent = Math.round((e.loaded / e.total) * 100);
                        pgBar.style.width = percent + '%';
                        pgPct.innerText = percent + '%';
                    }
                }, false);

                xhr.onload = function() {
                    if (xhr.status == 200) {
                        try {
                            let resp = JSON.parse(xhr.responseText);
                            if(resp.status === 'success') {
                                pgStat.innerText = 'Installing...';
                                pgBar.style.width = '100%'; 
                                pgBar.style.background = 'var(--success)';
                                conArea.style.display = 'block';
                                
                                logFrame.onload = function() {
                                    pgStat.innerText = 'Done';
                                };

                                logFrame.src = "?install_stream=1&file=" + encodeURIComponent(resp.file);
                            } else {
                                throw new Error(resp.message);
                            }
                        } catch(e) {
                            pgStat.innerText = "Error: " + e.message;
                            pgBar.style.background = 'var(--danger)';
                        }
                    } else {
                        pgStat.innerText = "Upload Failed";
                        pgBar.style.background = 'var(--danger)';
                    }
                };

                xhr.open("POST", window.location.href);
                xhr.send(fd);
            }
            
            const db = document.getElementById('dropBox');
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => db.addEventListener(e, ev => {ev.preventDefault(); ev.stopPropagation()}));
            db.addEventListener('drop', e => { if(e.dataTransfer.files.length) startInstall(e.dataTransfer.files[0]); });
        </script>

    <?php elseif ($activeTab == 'logs'): ?>
        <div class="card">
            <div class="title" style="justify-content:space-between">
                <span>Magisk Logs</span>
                <a href="?tab=logs" class="btn btn-sm btn-p">Refresh</a>
            </div>
            <div class="log-box">
                <?php
                $logFile = '/cache/magisk.log';
                if (file_exists($logFile)) {
                    $handle = fopen($logFile, "r");
                    if ($handle) {
                        while (($line = fgets($handle)) !== false) {
                            $c = 'log-row';
                            if (stripos($line, 'error') !== false || stripos($line, 'fail') !== false) $c .= ' log-err';
                            elseif (stripos($line, 'warn') !== false) $c .= ' log-warn';
                            echo "<div class='$c'>" . htmlspecialchars($line) . "</div>";
                        }
                        fclose($handle);
                    }
                } else {
                    echo '<div class="log-row log-err">Log file not found.</div>';
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
