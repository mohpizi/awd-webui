<?php
// --- KONFIGURASI BINARY ---
$binary_paths = [
    '/data/adb/php8/files/bin/crypto.so',
    '/data/adb/php8/files/bin/safe_decrypt'
];

$binary = '';
foreach ($binary_paths as $path) {
    if (file_exists($path)) {
        $binary = $path;
        break;
    }
}

// Fungsi Helper
function encrypt_data($text, $binary) {
    if (empty($text) || empty($binary)) return $text;
    $cmd = $binary . " -e " . escapeshellarg($text);
    return trim(shell_exec($cmd));
}

$result_text = "";
$json_output = "";
$active_tab = "simple"; 

// =======================================================
// LOGIKA 1: SIMPLE ENCRYPT
// =======================================================
if (isset($_POST['do_encrypt'])) {
    $text = trim($_POST['text']);
    if ($binary) {
        $result_text = encrypt_data($text, $binary);
    } else {
        $result_text = "Error: Binary crypto tidak ditemukan.";
    }
    $active_tab = "simple";
}

// =======================================================
// LOGIKA 2: JSON GENERATOR (Telegram Pin)
// =======================================================
if (isset($_POST['generate_json'])) {
    $active_tab = "json";
    $name = $_POST['mod_name'];
    $desc = $_POST['mod_desc'];
    $url  = $_POST['mod_url'];
    $icon = $_POST['mod_icon'] ?: 'fa-brands fa-readme';
    $color= $_POST['mod_color'] ?: '#ff9800';
    $vip_ids_raw = $_POST['mod_vip'];

    $enc_url = encrypt_data($url, $binary);

    $data = [
        "name"  => $name,
        "desc"  => $desc,
        "url"   => $enc_url,
        "icon"  => $icon,
        "color" => $color
    ];

    if (!empty(trim($vip_ids_raw))) {
        $ids_array = explode("\n", $vip_ids_raw);
        $encrypted_ids = [];
        foreach ($ids_array as $id) {
            $clean_id = trim($id);
            if (!empty($clean_id)) $encrypted_ids[] = encrypt_data($clean_id, $binary);
        }
        if (!empty($encrypted_ids)) $data['allowed_ids'] = $encrypted_ids;
    }

    $final_structure = [$data];
    $json_output = json_encode($final_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// =======================================================
// LOGIKA 3: MODULE PACKER (ZIP GENERATOR)
// =======================================================
if (isset($_POST['pack_module'])) {
    $active_tab = "packer";
    
    // Input Data
    $p_name = $_POST['p_name'];
    $p_desc = $_POST['p_desc'];
    $p_icon = $_POST['p_icon'] ?: 'fa-brands fa-readme';
    $p_color= $_POST['p_color'] ?: '#ff9800';
    $p_main = $_POST['p_main'] ?: 'index.php'; // Nama file utama
    
    // ID Folder (Hanya untuk nama folder dalam ZIP)
    $p_id   = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['p_id']); 
    if (empty($p_id)) $p_id = 'mod_' . time();

    // Isi File
    $p_php  = $_POST['p_php']; // Konten Main File
    $p_sh   = $_POST['p_sh'];  // Konten Install.sh

    // 1. Buat info.json (Sesuai Format Request)
    $info_data = [
        "name"        => $p_name,
        "icon"        => $p_icon,
        "color"       => $p_color,
        "main_file"   => $p_main,
        "description" => $p_desc
    ];
    $json_content = json_encode($info_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // 2. Siapkan ZIP
    $zip = new ZipArchive();
    $filename = $p_id . ".zip";
    $temp_zip = sys_get_temp_dir() . "/" . $filename;

    if ($zip->open($temp_zip, ZipArchive::CREATE) === TRUE) {
        // Struktur: Folder_ID / file-file
        $root = $p_id . "/";
        
        $zip->addEmptyDir($p_id);
        $zip->addFromString($root . "info.json", $json_content);
        $zip->addFromString($root . $p_main, $p_php); // File utama (index.php)
        
        // Tambah install.sh jika diisi
        if (!empty(trim($p_sh))) {
            $zip->addFromString($root . "install.sh", $p_sh);
        }

        $zip->close();

        // 3. Download File
        if (file_exists($temp_zip)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($temp_zip));
            readfile($temp_zip);
            unlink($temp_zip);
            exit();
        }
    } else {
        echo "<script>alert('Gagal membuat ZIP');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Tools</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; margin: 0; }
        .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); overflow: hidden; }
        
        /* TABS */
        .tabs { display: flex; background: #e4e6eb; }
        .tab-btn { flex: 1; padding: 15px; border: none; background: transparent; cursor: pointer; font-weight: bold; color: #65676b; transition: 0.3s; font-size: 0.9rem; }
        .tab-btn:hover { background: #d0d2d6; }
        .tab-btn.active { background: white; color: #1877f2; border-bottom: 3px solid #1877f2; }
        
        .tab-content { display: none; padding: 25px; }
        .tab-content.active { display: block; }

        /* FORM */
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; font-size: 0.9rem; }
        input[type="text"], textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; }
        
        /* Code Editor Style */
        textarea.code-editor { font-family: 'Courier New', monospace; background: #282c34; color: #abb2bf; font-size: 0.85rem; border: 1px solid #1e2127; }
        
        .color-picker { display: flex; align-items: center; gap: 10px; }
        input[type="color"] { border: none; width: 40px; height: 40px; cursor: pointer; background: none; }

        button.primary { width: 100%; background: #1877f2; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; transition: 0.3s; }
        button.primary:hover { background: #166fe5; }
        button.success { background: #4caf50; }
        button.success:hover { background: #43a047; }

        /* RESULT */
        .result-box { margin-top: 20px; background: #282c34; color: #abb2bf; padding: 15px; border-radius: 8px; position: relative; }
        .result-box pre { margin: 0; white-space: pre-wrap; word-break: break-all; font-family: monospace; }
        .copy-btn { position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.2); color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        
        .status-dot { height: 10px; width: 10px; background-color: #bbb; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .status-dot.ok { background-color: #4caf50; }
        
        .note { font-size: 0.8rem; color: #666; margin-top: 2px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div style="padding: 20px; border-bottom: 1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
        <h3 style="margin:0;">🛠️ Admin Tools</h3>
        <div style="font-size: 0.8rem; color: #666;">
            Binary: 
            <?php if($binary): ?>
                <span class="status-dot ok"></span> Siap
            <?php else: ?>
                <span class="status-dot" style="background:red;"></span> Hilang
            <?php endif; ?>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn <?= $active_tab == 'simple' ? 'active' : '' ?>" onclick="openTab('simple')">
            <i class="fa-solid fa-lock"></i> Encrypt
        </button>
        <button class="tab-btn <?= $active_tab == 'json' ? 'active' : '' ?>" onclick="openTab('json')">
            <i class="fa-brands fa-telegram"></i> TG JSON
        </button>
        <button class="tab-btn <?= $active_tab == 'packer' ? 'active' : '' ?>" onclick="openTab('packer')">
            <i class="fa-solid fa-file-zipper"></i> Packer
        </button>
    </div>

    <div id="simple" class="tab-content <?= $active_tab == 'simple' ? 'active' : '' ?>">
        <form method="POST">
            <div class="form-group">
                <label>Masukkan Teks / URL / ID:</label>
                <input type="text" name="text" placeholder="Contoh: https://github.com/..." required>
            </div>
            <button type="submit" name="do_encrypt" class="primary">Enkripsi Sekarang</button>
        </form>

        <?php if($result_text): ?>
            <div class="result-box">
                <button class="copy-btn" onclick="copyText('res1')">Copy</button>
                <pre id="res1"><?= htmlspecialchars($result_text) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <div id="json" class="tab-content <?= $active_tab == 'json' ? 'active' : '' ?>">
        <form method="POST">
            <div class="form-group">
                <label>Nama Modul (Store)</label>
                <input type="text" name="mod_name" placeholder="File Manager" required>
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <input type="text" name="mod_desc" placeholder="Modul keren..." required>
            </div>
            <div class="form-group">
                <label>Link Download</label>
                <input type="text" name="mod_url" placeholder="https://..." required>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Icon (FA)</label>
                    <input type="text" name="mod_icon" value="fa-brands fa-readme">
                </div>
                <div class="form-group">
                    <label>Warna</label>
                    <div class="color-picker">
                        <input type="color" name="mod_color" value="#ff9800">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>VIP Access (Opsional)</label>
                <textarea name="mod_vip" rows="2" placeholder="ID Telegram per baris"></textarea>
            </div>
            <button type="submit" name="generate_json" class="primary">Generate JSON</button>
        </form>
        <?php if($json_output): ?>
            <div class="result-box">
                <button class="copy-btn" onclick="copyText('res2')">Copy JSON</button>
                <pre id="res2"><?= htmlspecialchars($json_output) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <div id="packer" class="tab-content <?= $active_tab == 'packer' ? 'active' : '' ?>">
        <form method="POST">
            <div class="row">
                <div class="form-group">
                    <label>ID Folder (Tanpa spasi)</label>
                    <input type="text" name="p_id" placeholder="my_tools" required>
                </div>
                <div class="form-group">
                    <label>Nama Modul (Display)</label>
                    <input type="text" name="p_name" placeholder="ReadMe" required>
                </div>
            </div>

            <div class="form-group">
                <label>Deskripsi (info.json)</label>
                <input type="text" name="p_desc" placeholder="Kelola file sistem" required>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Icon (FontAwesome)</label>
                    <input type="text" name="p_icon" value="fa-brands fa-readme">
                </div>
                <div class="form-group">
                    <label>Warna Tema</label>
                    <div class="color-picker">
                        <input type="color" name="p_color" value="#ff9800">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Nama File Utama</label>
                <input type="text" name="p_main" value="index.php" required>
            </div>

            <div class="form-group">
                <label>Isi File Utama (Code Editor)</label>
                <textarea name="p_php" class="code-editor" rows="8" spellcheck="false"><?php echo "<?php\n// Kode PHP Anda\necho 'Hello World';\n?>"; ?></textarea>
            </div>

            <div class="form-group">
                <label>Isi File: <b>install.sh</b> (Opsional)</label>
                <textarea name="p_sh" class="code-editor" rows="4" spellcheck="false" placeholder="#!/bin/sh"></textarea>
            </div>

            <button type="submit" name="pack_module" class="primary success">
                <i class="fa-solid fa-download"></i> Buat & Download .ZIP
            </button>
        </form>
    </div>

</div>

<script>
    function openTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        
        document.getElementById(tabName).classList.add('active');
        const btns = document.getElementsByClassName('tab-btn');
        for(let btn of btns) {
            if(btn.getAttribute('onclick').includes(tabName)) {
                btn.classList.add('active');
            }
        }
    }

    function copyText(elementId) {
        var text = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(text).then(function() {
            alert('Teks berhasil disalin!');
        }, function(err) {
            alert('Gagal menyalin');
        });
    }
</script>

</body>
</html>
