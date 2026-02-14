<?php
$save_path = '/data/adb/box/clash/proxy_provider/AKUN-VPN.yaml';

// --- DEFINISI VARIABEL YANG BENAR ---
$php_bin = PHP_BINARY;
$backend_script = __DIR__ . "/backend.php"; 
// ------------------------------------

$result = "";
$message = "";
$raw_link = "";
$edit_mode = false;
$edit_content = "";
$edit_target_name = "";
$account_list = [];
$current_file_content = ""; 

// Baca daftar akun
if (file_exists($save_path)) {
    $current_file_content = file_get_contents($save_path);
    preg_match_all('/- name:\s*(.*)/', $current_file_content, $matches);
    if (!empty($matches[1])) {
        $account_list = $matches[1];
    }
} else {
    $current_file_content = "# File belum ada.";
}

// 1. PROSES CONVERT / ADD
if (isset($_POST['process_and_save'])) {
    $raw_link = $_POST['link'];
    if (!empty($raw_link)) {
        $escaped_link = escapeshellarg($raw_link);
        $trimmed_input = trim($raw_link);
        
        // --- PERBAIKAN VARIABEL DI SINI ---
        if (strpos($trimmed_input, '- name:') === 0 || strpos($trimmed_input, 'proxies:') === 0) {
            $command = "$php_bin $backend_script clean $escaped_link 2>&1";
        } else {
            $command = "$php_bin $backend_script convert $escaped_link 2>&1";
        }
        // ----------------------------------

        $yaml_result = shell_exec($command);

        if ($yaml_result && strlen(trim($yaml_result)) > 10) {
            $dir = dirname($save_path);
            if (!is_writable($dir) && !is_dir($dir)) {
                 $message = "Error: Folder tidak writable.";
            } else {
                $final_content = str_replace("\r\n", "\n", $yaml_result);
                if (substr($final_content, -1) != "\n") $final_content .= "\n";

                if (!file_exists($save_path)) {
                    $header = "proxies:\n";
                    if (strpos($final_content, "proxies:") === false) $final_content = $header . $final_content;
                    file_put_contents($save_path, $final_content);
                } else {
                    $content_to_append = str_replace("proxies:\n", "", $final_content);
                    $content_to_append = ltrim($content_to_append);
                    file_put_contents($save_path, $content_to_append, FILE_APPEND);
                }

                // Clean ulang file setelah append
                $full_content_now = file_get_contents($save_path);
                $escaped_full = escapeshellarg($full_content_now);
                
                // --- PERBAIKAN VARIABEL DI SINI ---
                $clean_cmd = "$php_bin $backend_script clean $escaped_full 2>&1";
                // ----------------------------------
                
                $cleaned_res = shell_exec($clean_cmd);

                if ($cleaned_res && strlen($cleaned_res) > 10) {
                    file_put_contents($save_path, $cleaned_res);
                }

                $message = "Sukses! Akun disimpan.";
                $raw_link = "";
                header("Refresh:1"); 
            }
        } else {
            $message = "Gagal. Format tidak valid atau backend error.";
        }
    }
}

// 2. PROSES DELETE
if (isset($_POST['delete_account_direct'])) {
    $target_name = $_POST['delete_account_direct'];
    if (!empty($target_name) && file_exists($save_path)) {
        $current_content = file_get_contents($save_path);
        $escaped_content = escapeshellarg($current_content);
        $escaped_name = escapeshellarg($target_name);
        
        // --- PERBAIKAN VARIABEL DI SINI ---
        $command = "$php_bin $backend_script delete $escaped_content $escaped_name 2>&1";
        // ----------------------------------
        
        $new_content = shell_exec($command);
        
        if ($new_content) {
            file_put_contents($save_path, $new_content);
            $message = "Akun '$target_name' dihapus.";
            header("Refresh:1");
        }
    }
}

// 3. PROSES GET (UNTUK EDIT)
if (isset($_POST['account_selector'])) {
    $target_name = $_POST['account_selector'];
    if (!empty($target_name) && file_exists($save_path)) {
        $current_content = file_get_contents($save_path);
        $escaped_content = escapeshellarg($current_content);
        $escaped_name = escapeshellarg($target_name);

        // --- PERBAIKAN VARIABEL DI SINI ---
        $command = "$php_bin $backend_script get $escaped_content $escaped_name 2>&1";
        // ----------------------------------
        
        $fetched_content = shell_exec($command);
        
        if (trim($fetched_content)) {
            $edit_mode = true;
            $edit_content = $fetched_content;
            $edit_target_name = $target_name;
        }
    }
}

// 4. PROSES REPLACE (SIMPAN EDITAN)
if (isset($_POST['update_single_account'])) {
    $old_name = $_POST['target_old_name'];
    $new_content_block = $_POST['edited_account_content'];
    
    if (!empty($old_name) && !empty($new_content_block) && file_exists($save_path)) {
        $current_content = file_get_contents($save_path);
        $new_content_block = str_replace("\r\n", "\n", $new_content_block);

        $escaped_content = escapeshellarg($current_content);
        $escaped_name = escapeshellarg($old_name);
        $escaped_new_block = escapeshellarg($new_content_block);

        // --- PERBAIKAN VARIABEL DI SINI ---
        $command = "$php_bin $backend_script replace $escaped_content $escaped_name $escaped_new_block 2>&1";
        // ----------------------------------
        
        $updated_full_file = shell_exec($command);

        if ($updated_full_file && strlen($updated_full_file) > 10) {
            file_put_contents($save_path, $updated_full_file);
            $message = "Akun berhasil diperbarui!";
            $edit_mode = false;
            header("Refresh:1");
        }
    }
}

// 5. PROSES CLEAN MANUAL
if (isset($_POST['clean_file'])) {
    if (file_exists($save_path)) {
        $current_content = file_get_contents($save_path);
        $escaped_content = escapeshellarg($current_content);
        
        // --- PERBAIKAN VARIABEL DI SINI ---
        $command = "$php_bin $backend_script clean $escaped_content 2>&1";
        // ----------------------------------
        
        $cleaned_content = shell_exec($command);
        if ($cleaned_content && strlen($cleaned_content) > 10) {
            file_put_contents($save_path, $cleaned_content);
            $message = "File berhasil dirapikan.";
            header("Refresh:1");
        }
    }
}

// 6. SAVE MANUAL EDITOR
if (isset($_POST['save_manual_full'])) {
    $manual_content = $_POST['full_editor_content'];
    if (!empty($manual_content)) {
        $dir = dirname($save_path);
        if (!is_writable($dir) && !is_dir($dir)) {
             $message = "Error: Folder tidak writable.";
        } else {
            $fixed_content = str_replace("\r\n", "\n", $manual_content);
            if (file_put_contents($save_path, $fixed_content) !== false) {
                $message = "Sukses! File disimpan ulang.";
                header("Refresh:1");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Magisk Tool</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-soft: rgba(251, 140, 0, 0.1); --code-bg: #f1f5f9;
            --success: #22c55e; --danger: #ef4444; --rad: 12px; --shd: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri-soft: rgba(251, 140, 0, 0.15); --code-bg: #000;
                --shd: 0 4px 6px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; display: flex; justify-content: center; min-height: 100vh; }
        
        .con { width: 100%; max-width: 800px; background: var(--card); padding: 25px; border-radius: var(--rad); border: 1px solid var(--border); box-shadow: var(--shd); }
        
        /* UPDATE: JUDUL TIDAK TEBAL */
        h2 { text-align: left; color: var(--pri); margin: 0 0 5px; font-weight: 400; text-transform: uppercase; letter-spacing: 1px; }
        
        .sub-t { text-align: left; color: var(--sub); font-size: 0.8rem; font-weight: 600; margin-bottom: 20px; letter-spacing: 1px; text-transform: uppercase; border-bottom: 1px solid var(--border); padding-bottom: 10px; width: 100%; }
        
        textarea { 
            width: 100%; 
            background: var(--code-bg); 
            border: 1px solid var(--border); 
            color: var(--text); 
            padding: 12px; 
            border-radius: 8px; 
            font-family: 'SF Mono', 'Segoe UI Mono', 'Roboto Mono', 'Consolas', 'Courier New', monospace; 
            font-size: 0.85rem; 
            line-height: 1.6;
            letter-spacing: 0.3px;
            resize: none; 
            min-height: 100px; 
            max-height: 350px; 
            overflow-y: auto; 
            transition: height 0.1s ease;
        }
        textarea:focus { border-color: var(--pri); }

        .btn-grp { display: flex; gap: 10px; margin-top: 15px; }
        button { flex: 1; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; transition: 0.2s; font-size: 0.9rem; color: #fff; }
        button:active { transform: scale(0.98); }
        
        .btn-pri { background: var(--pri); color: #000; }
        .btn-sec { background: var(--border); color: var(--text); }
        .btn-dan { background: var(--danger); }
        .btn-suc { background: var(--success); color: #000; }

        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert.s { background: rgba(34, 197, 94, 0.15); color: var(--success); border: 1px solid var(--success); }
        .alert.e { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid var(--danger); }

        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; max-height: 400px; overflow-y: auto; padding: 2px; }
        @media (max-width: 600px) { .grid { grid-template-columns: repeat(2, 1fr); } }

        .item { display: flex; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; height: 38px; align-items: center; }
        .item-n { flex: 1; background: transparent; color: var(--text); text-align: left; padding: 0 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-radius: 0; font-family: 'SF Mono', 'Roboto Mono', monospace; font-size: 0.8rem; height: 100%; border: none; }
        .item-n:hover { color: var(--pri); background: var(--pri-soft); }
        .item-d { width: 26px; flex: none; background: transparent; color: var(--pri); border-left: 1px solid var(--border); display: flex; align-items: center; justify-content: center; border-radius: 0; font-size: 0.9rem; height: 100%; padding: 0; }
        .item-d:hover { background: var(--pri-soft); color: #fff; }

        .sep { margin-top: 25px; }
        
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg); z-index: 999; display: flex; flex-direction: column; padding: 20px; animation: slide 0.3s; }
        @keyframes slide { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border); }
        .modal-act { display: flex; gap: 10px; align-items: center; }
        .modal-t { font-size: 1.1rem; font-weight: 700; color: var(--pri); }
        .modal-area { flex-grow: 1; margin-bottom: 15px; font-size: 0.9rem; }
        
        .btn-head { background: var(--border); width: auto; padding: 8px 16px; font-size: 0.8rem; flex: none; display: flex; align-items: center; gap: 5px; color: var(--text); }
        .btn-head-s { background: var(--pri); color: #000; }
    </style>
</head>
<body>

<?php if ($edit_mode): ?>
<div class="modal">
    <form method="post" style="display:flex;flex-direction:column;height:100%">
        <div class="modal-head">
            <div class="modal-act">
                <button type="button" class="btn-head" onclick="location.href=location.pathname">← Back</button>
                <button type="submit" name="update_single_account" class="btn-head btn-head-s">Save</button>
            </div>
            <span class="modal-t">Edit Account</span>
        </div>
        <label>Target: <?php echo htmlspecialchars($edit_target_name); ?></label>
        <textarea name="edited_account_content" class="modal-area"><?php echo htmlspecialchars($edit_content); ?></textarea>
        <input type="hidden" name="target_old_name" value="<?php echo htmlspecialchars($edit_target_name); ?>">
    </form>
</div>
<?php endif; ?>

<div id="advEditor" class="modal" style="display:none">
    <form method="post" style="display:flex;flex-direction:column;height:100%">
        <div class="modal-head">
            <div class="modal-act">
                <button type="button" class="btn-head" onclick="document.getElementById('advEditor').style.display='none'">← Back</button>
                <button type="submit" name="save_manual_full" class="btn-head btn-head-s" onclick="return confirm('Overwrite file?')">Save All</button>
            </div>
            <span class="modal-t">Full Editor</span>
        </div>
        <textarea name="full_editor_content" class="modal-area"><?php echo htmlspecialchars($current_file_content); ?></textarea>
    </form>
</div>

<div class="con">
    <h2>Akun Editor</h2>
    <div class="sub-t">by arewedaks</div>

    <?php if ($message): ?>
        <div class="alert <?php echo strpos($message, 'Sukses')!==false || strpos($message, 'berhasil')!==false ? 's' : 'e'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div>
        <form method="post">
            <textarea id="inLink" name="link" rows="3" class="auto-resize" placeholder="Paste Vmess/Trojan/Vless link here..."><?php echo htmlspecialchars($raw_link); ?></textarea>
            <div class="btn-grp">
                <button id="btnAction" type="submit" name="process_and_save" class="btn-pri">Convert</button>
            </div>
        </form>
    </div>

    <div class="sep"></div>

    <form method="post">
        <?php if (!empty($account_list)): ?>
            <div class="grid">
                <?php foreach ($account_list as $acc): $acc=trim($acc); ?>
                    <div class="item">
                        <button type="submit" name="account_selector" value="<?php echo htmlspecialchars($acc); ?>" class="item-n" title="<?php echo htmlspecialchars($acc); ?>">
                            <?php echo htmlspecialchars($acc); ?>
                        </button>
                        <button type="submit" name="delete_account_direct" value="<?php echo htmlspecialchars($acc); ?>" class="item-d" onclick="return confirm('Delete: <?php echo htmlspecialchars($acc); ?>?');" title="Delete">
                            &times;
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:20px;border:1px dashed var(--border);color:var(--sub);border-radius:8px;font-style:italic">No accounts found in file.</div>
        <?php endif; ?>
    </form>

    <div class="btn-grp" style="margin-top:25px">
        <form method="post" style="flex:1"><button type="submit" name="clean_file" class="btn-sec" style="width:100%">Fix Format</button></form>
        <button type="button" class="btn-sec" onclick="document.getElementById('advEditor').style.display='flex'">Advanced Editor</button>
    </div>
</div>

<script>
    // AUTO RESIZE
    function autoResize() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    }

    // BUTTON TEXT
    const ta = document.getElementById('inLink');
    const btn = document.getElementById('btnAction');

    function checkInput() {
        if (!ta || !btn) return;
        const v = ta.value.trim();
        if (v.startsWith('- name:') || v.startsWith('proxies:')) {
            btn.innerHTML = 'Save';
        } else {
            btn.innerHTML = 'Convert';
        }
    }

    // INIT
    document.querySelectorAll('textarea').forEach(t => {
        t.addEventListener('input', autoResize);
        t.style.height = 'auto';
        t.style.height = (t.scrollHeight) + 'px';
    });

    if (ta) {
        ta.addEventListener('input', checkInput);
        checkInput();
    }
</script>

</body>
</html>