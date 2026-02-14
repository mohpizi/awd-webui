<?php
function parseFile($f) {
    $p = __DIR__ . '/codes/' . $f;
    if(!file_exists($p)) return "<div class='desc'>File codes/$f not found.</div>";
    
    $lines = file($p, FILE_IGNORE_NEW_LINES);
    $out = ''; 
    $buf = []; 

    foreach($lines as $l) {
        if(strpos(trim($l), '/') === 0) {
            if(!empty($buf)) { 
                $out .= '<div class="code">' . htmlspecialchars(implode("\n", $buf)) . '</div>'; 
                $buf = []; 
            }
            $txt = trim(substr(trim($l), 1));
            if($txt) $out .= '<div class="desc">' . htmlspecialchars($txt) . '</div>';
        } else {
            $buf[] = $l; 
        }
    }
    if(!empty($buf)) $out .= '<div class="code">' . htmlspecialchars(implode("\n", $buf)) . '</div>';
    
    return $out;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RameShop Guide</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --pri-soft: #fff3e0; --code-bg: #1e293b; --code-tx: #a5b4fc;
            --rad: 12px; --shd: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --pri-soft: rgba(255,152,0,0.15); --code-bg: #000; --code-tx: #4ade80;
                --shd: 0 4px 6px -1px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 800px; margin: 0 auto; line-height: 1.5; }
        
        .head { text-align: center; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        h1 { font-size: 1.6rem; font-weight: 800; color: var(--pri); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
        p { font-size: 0.9rem; color: var(--sub); }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 25px; }
        .box { background: var(--card); border: 1px solid var(--border); border-radius: var(--rad); padding: 20px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 10px; box-shadow: var(--shd); transition: 0.2s; text-align: center; }
        .box:active { transform: scale(0.98); border-color: var(--pri); background: var(--pri-soft); }
        .box h4 { font-size: 0.85rem; font-weight: 700; margin: 0; color: var(--text); }
        .box svg { width: 28px; height: 28px; fill: var(--pri); }

        .item { display: none; background: var(--card); border: 1px solid var(--border); padding: 20px; border-radius: var(--rad); margin-bottom: 20px; box-shadow: var(--shd); animation: fade 0.3s; }
        .item.show { display: block; }
        @keyframes fade { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        .ti { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--sub); font-weight: 700; border-bottom: 2px solid var(--border); padding-bottom: 8px; margin-bottom: 15px; }
        h3 { font-size: 1.1rem; margin: 0 0 15px; color: var(--pri); }
        
        .desc { font-size: 0.9rem; color: var(--sub); margin: 15px 0 5px; font-weight: 600; }
        .code { background: var(--code-bg); color: var(--code-tx); padding: 12px; border-radius: 8px; font-family: monospace; font-size: 0.8rem; overflow-x: auto; margin-bottom: 5px; white-space: pre-wrap; word-break: break-all; border: 1px solid var(--border); }

        .links { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .lnk { display: flex; align-items: center; gap: 8px; padding: 10px 15px; background: var(--bg); border-radius: 8px; border: 1px solid var(--border); text-decoration: none; flex: 1; min-width: 140px; color: var(--text); font-weight: 600; font-size: 0.9rem; transition: 0.2s; position: relative; }
        .lnk:hover { border-color: var(--pri); color: var(--pri); }
        .lnk svg { width: 20px; height: 20px; fill: currentColor; }
        .lnk p { margin: 0; font-size: 0.9rem; font-weight: 700; }
        .lnk h6 { margin: 0; font-size: 0.7rem; color: var(--sub); font-weight: normal; margin-left: auto; }

        .cat-title { font-size: 0.75rem; color: var(--sub); text-transform: uppercase; font-weight: 700; margin: 15px 0 8px; display: block; }
    </style>
</head>
<body>

<div class="head">
    <h1>RameShop Guide</h1>
    <p>WebUI Optimization & Setup</p>
</div>

<div class="grid">
    <div class="box" onclick="tg('c1')">
        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM4 18V6h16v12H4zM6 9l4 3-4 3V9zm6 5h6v2h-6v-2z"/></svg>
        <h4>TTYD Terminal</h4>
    </div>
    <div class="box" onclick="tg('c2')">
        <svg viewBox="0 0 24 24"><path d="M7.77 6.76L6.23 5.48.82 12l5.41 6.52 1.54-1.28L3.42 12l4.35-5.24zM7 13h2v-2H7v2zm10-2h-2v2h2v-2zm-6 2h2v-2h-2v2zm6.77-7.52l-1.54 1.28L20.58 12l-4.35 5.24 1.54 1.28L23.18 12l-5.41-6.52z"/></svg>
        <h4>VNStat</h4>
    </div>
    <div class="box" onclick="tg('c3')">
        <svg viewBox="0 0 24 24"><path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5a2.5 2.5 0 1 0-5 0V5H4c-1.1 0-2 .9-2 2v3.8h1.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5c0-1.49 1.21-2.7 2.7-2.7s2.7 1.21 2.7 2.7V22H17c1.1 0 2-.9 2-2v-4h1.5a2.5 2.5 0 1 0 0-5z"/></svg>
        <h4>Tailscale</h4>
    </div>
    <div class="box" onclick="tg('c4')">
        <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
        <h4>Hotspot & Lock IP</h4>
    </div>
    <div class="box" onclick="tg('c5')">
        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM4 18V6h16v12H4zM6 9l4 3-4 3V9zm6 5h6v2h-6v-2z"/></svg>
        <h4>First Install</h4>
    </div>
</div>

<div id="c1" class="item">
    <div class="ti">Terminal Setup</div>
    <h3>TTYD Installation</h3>
    <?= parseFile('ttyd.txt') ?>
</div>

<div id="c2" class="item">
    <div class="ti">Traffic Monitor</div>
    <h3>VNStat Setup</h3>
    <?= parseFile('vnstat.txt') ?>
</div>

<div id="c3" class="item">
    <div class="ti">VPN</div>
    <h3>Tailscale Magisk</h3>
    <?= parseFile('tailscale.txt') ?>
</div>

<div id="c4" class="item">
    <div class="ti">Gateway Lock</div>
    <h3>Hotspot Automation</h3>
    <?= parseFile('auto_hotspot.txt') ?>
</div>

<div id="c5" class="item">
    <div class="ti">System Setup</div>
    <h3>First Install</h3>
    <?= parseFile('install.txt') ?>
</div>

<div class="item show">
    <div class="ti">Downloads</div>
    
    <div class="cat-title">Termux F-Droid</div>
    <div class="links">
        <a href="https://f-droid.org/id/packages/com.termux/" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM4 18V6h16v12H4zM6 9l4 3-4 3V9zm6 5h6v2h-6v-2z"/></svg>
            <p>Termux</p><h6>Stable</h6>
        </a>
        <a href="https://f-droid.org/id/packages/com.termux.boot/" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM4 18V6h16v12H4zM6 9l4 3-4 3V9zm6 5h6v2h-6v-2z"/></svg>
            <p>Termux:Boot</p><h6>Addon</h6>
        </a>
    </div>

    <div class="cat-title">Magisk Modules</div>
    <div class="links">
        <a href="https://github.com/taamarin/box_for_magisk/releases" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M21 16.5c0 .38-.21.71-.53.88l-7.9 4.44c-.16.12-.36.18-.57.18c-.21 0-.41-.06-.57-.18l-7.9-4.44A.991.991 0 0 1 3 16.5v-9c0-.38.21-.71.53-.88l7.9-4.44A.996.996 0 0 1 12 2c.21 0 .41.06.57.18l7.9 4.44c.32.17.53.5.53.88v9zM12 4.15L6.04 7.5L12 10.85l5.96-3.35L12 4.15zM5 15.91l6 3.38v-6.71L5 9.21v6.7zm14 0v-6.7l-6 3.37v6.71l6-3.38z"/></svg>
            <p>Box For Root</p><h6>Core</h6>
        </a>
        <a href="https://github.com/taamarin/ClashforMagisk/releases" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5z"/></svg>
            <p>Clash For Magisk</p><h6>Net</h6>
        </a>
        <a href="https://github.com/Magisk-Modules-Alt-Repo/Magisk-Tailscaled/releases/" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5a2.5 2.5 0 1 0-5 0V5H4c-1.1 0-2 .9-2 2v3.8h1.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5c0-1.49 1.21-2.7 2.7-2.7s2.7 1.21 2.7 2.7V22H17c1.1 0 2-.9 2-2v-4h1.5a2.5 2.5 0 1 0 0-5z"/></svg>
            <p>Tailscale</p><h6>VPN</h6>
        </a>
    </div>

    <div class="cat-title">Lock IP & Tools</div>
    <div class="links">
        <a href="https://github.com/LSPosed/LSPosed/releases" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5a2.5 2.5 0 1 0-5 0V5H4c-1.1 0-2 .9-2 2v3.8h1.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5c0-1.49 1.21-2.7 2.7-2.7s2.7 1.21 2.7 2.7V22H17c1.1 0 2-.9 2-2v-4h1.5a2.5 2.5 0 1 0 0-5z"/></svg>
            <p>LSPosed</p><h6>Root</h6>
        </a>
        <a href="https://github.com/XhyEax/SoftApHelper/releases" target="_blank" class="lnk">
            <svg class="icon" viewBox="0 0 24 24"><path d="M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85a.637.637 0 0 0-.83.22l-1.88 3.24a11.46 11.46 0 0 0-8.94 0L5.65 5.67a.643.643 0 0 0-.87-.2c-.28.18-.37.54-.22.83L6.4 9.48A10.78 10.78 0 0 0 1 18h22a10.78 10.78 0 0 0-5.4-8.52M7 15.25a1.25 1.25 0 1 1 0-2.5a1.25 1.25 0 0 1 0 2.5m10 0a1.25 1.25 0 1 1 0-2.5a1.25 1.25 0 0 1 0 2.5"/></svg>
            <p>SoftApHelper</p><h6>Apk</h6>
        </a>
    </div>

    <div class="cat-title">Support</div>
    <div class="links">
        <a href="https://t.me/+_IyXS4aBNeE5OGM1" class="lnk" target="_blank"><svg viewBox="0 0 24 24"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.48-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/></svg> Telegram</a>
        <a href="https://shopee.co.id/bstrongshop" class="lnk" target="_blank"><svg viewBox="0 0 24 24"><path d="M12 0C8.86 0 6.67 2.21 6.31 4.84H2.25v18.75h19.5V4.84h-4.06C17.34 2.21 15.14 0 12 0zm0 2.17c2.07 0 3.32 1.65 3.48 2.68H8.53c.16-1.03 1.41-2.68 3.47-2.68z"/></svg> Shopee</a>
    </div>
</div>

<script>
function tg(id) {
    document.querySelectorAll('.item').forEach(e => { if(e.id) e.classList.remove('show') });
    const c = document.getElementById(id);
    if(c) c.classList.add('show');
}
</script>

</body>
</html>