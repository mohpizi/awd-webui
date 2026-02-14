<?php
// Tentukan path file yang akan dimuat di dalam tab
$fileTab1 = "/tools/StartUp/Services.php"; 
$fileTab2 = "/tools/StartUp/ModuleEnabler.php";   
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#fb8c00">
    <title>StartUp Manager</title>
    <style>
        /* TEMA IDENTIK DENGAN SCRIPT SEBELUMNYA */
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-h: #ef6c00; --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            --rad: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-h: #ffa726; --shadow: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            height: 100vh; /* Full Height layar */
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Mencegah scroll ganda di body utama */
        }

        /* HEADER NAVIGASI */
        .head {
            padding: 15px 20px;
            display: flex;
            justify-content: center;
            background: var(--bg);
            flex-shrink: 0; /* Header tidak boleh mengecil */
            z-index: 10;
        }

        /* CONTAINER TAB (Gaya Card) */
        .nav-box {
            background: var(--card);
            padding: 5px;
            border-radius: 50px; /* Pill Shape */
            display: flex;
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        /* TOMBOL TAB */
        .tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--sub);
            border-radius: 40px;
            transition: all 0.3s ease;
            user-select: none;
        }

        /* TAB AKTIF (Orange) */
        .tab.active {
            color: white;
            background: var(--pri);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* MAIN CONTENT (IFRAME CONTAINER) */
        .main {
            flex-grow: 1;
            position: relative;
            width: 100%;
            height: 100%;
        }

        .view {
            display: none;
            width: 100%;
            height: 100%;
        }
        
        .view.active { display: block; }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: var(--bg); /* Agar transisi halus */
        }
    </style>
</head>
<body>

<div class="head">
    <div class="nav-box">
        <div class="tab active" onclick="sw('t1')" id="b-t1">Services</div>
        <div class="tab" onclick="sw('t2')" id="b-t2">Fix Module</div>
    </div>
</div>

<div class="main">
    <div id="v-t1" class="view active">
        <iframe src="<?php echo $fileTab1; ?>"></iframe>
    </div>
    <div id="v-t2" class="view">
        <iframe src="<?php echo $fileTab2; ?>"></iframe>
    </div>
</div>

<script>
    function sw(t) {
        // Hilangkan class active dari semua view & tab
        document.querySelectorAll('.view').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(e => e.classList.remove('active'));
        
        // Tambahkan class active ke target
        document.getElementById('v-' + t).classList.add('active');
        document.getElementById('b-' + t).classList.add('active');
    }
</script>

</body>
</html>