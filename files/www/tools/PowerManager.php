<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cmd = '';
    switch ($action) {
        case 'reboot': $cmd = 'su -c reboot'; break;
        case 'shutdown': $cmd = 'su -c reboot -p'; break;
        case 'recovery': $cmd = 'su -c reboot recovery'; break;
        case 'fastboot': $cmd = 'su -c reboot bootloader'; break;
    }
    if ($cmd) {
        shell_exec($cmd);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Power Menu</title>
    <style>
        :root {
            --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0;
            --pri: #fb8c00; --grad: linear-gradient(135deg, #ffa726 0%, #ef6c00 100%);
            --rad: 16px; --shd: 0 4px 20px rgba(0,0,0,0.05);
            --btn-shd: 0 8px 15px rgba(239, 108, 0, 0.25);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d;
                --pri: #ff9800; --grad: linear-gradient(135deg, #ff9800 0%, #e65100 100%);
                --shd: 0 4px 20px rgba(0,0,0,0.4);
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: var(--bg); color: var(--text); padding: 20px; display: flex; flex-direction: column; min-height: 100vh; align-items: center; justify-content: center; }
        .con { width: 100%; max-width: 420px; }
        
        .card { background: var(--card); padding: 30px; border-radius: var(--rad); box-shadow: var(--shd); border: 1px solid var(--border); text-align: center; transition: transform 0.2s; }
        
        h1 { font-size: 1.6rem; font-weight: 800; margin-bottom: 10px; color: var(--text); text-transform: uppercase; letter-spacing: 1px; }
        p { font-size: 0.9rem; color: var(--sub); margin-bottom: 30px; line-height: 1.5; }
        
        .badge { display: inline-block; padding: 6px 16px; background: rgba(251, 140, 0, 0.1); color: var(--pri); border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-bottom: 30px; border: 1px solid rgba(251, 140, 0, 0.2); }

        .grid { display: grid; gap: 15px; }
        
        .btn { width: 100%; padding: 16px; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.3s; color: white; display: flex; align-items: center; justify-content: center; gap: 12px; text-transform: uppercase; letter-spacing: 0.5px; background: var(--grad); box-shadow: var(--btn-shd); position: relative; overflow: hidden; }
        .btn:active { transform: scale(0.98); opacity: 0.9; }
        .btn::after { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: 0.5s; }
        .btn:hover::after { left: 100%; }
        
        .ovl { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 50; display: none; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .mdl { background: var(--card); padding: 25px; border-radius: var(--rad); width: 90%; max-width: 320px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: 1px solid var(--border); animation: pop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes pop { from{transform:scale(0.9);opacity:0} to{transform:scale(1);opacity:1} }
        
        .mdl h3 { margin-bottom: 10px; font-size: 1.2rem; color: var(--text); }
        .mdl p { color: var(--sub); font-size: 0.9rem; margin-bottom: 25px; }
        .act { display: flex; gap: 10px; }
        .bo { background: transparent; border: 1px solid var(--border); color: var(--text); box-shadow: none; }
        .bo:hover { background: var(--border); }
        
        .icon { width: 24px; height: 24px; fill: currentColor; }
    </style>
</head>
<body>

<div class="con">
    <div class="card">
        <h1>Power Menu</h1>
        <p>System Power Management Interface</p>
        
        <div class="badge">● SYSTEM ONLINE</div>

        <div class="grid">
            <button class="btn" onclick="cf('reboot', 'System Reboot', 'Restart device now?')">
                <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg> Reboot
            </button>
            <button class="btn" onclick="cf('shutdown', 'Power Off', 'Shutdown device?')">
                <svg class="icon" viewBox="0 0 24 24"><path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/></svg> Power Off
            </button>
            <button class="btn" onclick="cf('recovery', 'Recovery Mode', 'Reboot to Recovery?')">
                <svg class="icon" viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg> Recovery
            </button>
            <button class="btn" onclick="cf('fastboot', 'Fastboot Mode', 'Reboot to Bootloader?')">
                <svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg> Fastboot
            </button>
        </div>
    </div>
</div>

<div id="mdl" class="ovl">
    <div class="mdl">
        <h3 id="mt"></h3>
        <p id="md"></p>
        <div class="act">
            <button class="btn bo" onclick="cl()">Cancel</button>
            <form method="POST" style="width:100%">
                <input type="hidden" name="action" id="ma">
                <button type="submit" class="btn">Confirm</button>
            </form>
        </div>
    </div>
</div>

<script>
    const m = document.getElementById('mdl');
    const mt = document.getElementById('mt');
    const md = document.getElementById('md');
    const ma = document.getElementById('ma');

    function cf(a, t, d) {
        mt.innerText = t; md.innerText = d; ma.value = a;
        m.style.display = 'flex';
    }
    function cl() { m.style.display = 'none'; }
    m.addEventListener('click', e => { if(e.target===m) cl(); });
</script>

</body>
</html>