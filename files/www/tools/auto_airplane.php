<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';

$script_path  = '/data/adb/php8/scripts/airplane/modpes';
$pid_file     = '/data/adb/php8/scripts/airplane/connection.pid';
$log_file     = '/data/adb/php8/scripts/airplane/connection.log';
$boot_manager = '/data/adb/php8/scripts/airplane/boot_manager';

function exec_root($cmd) {
    return trim(shell_exec("su -c '$cmd'"));
}

function read_config($sp, $bm) {
    $c = ['url'=>'https://www.gstatic.com/generate_204', 'to'=>7, 'onboot'=>false];
    if (file_exists($sp)) {
        $cnt = file_get_contents($sp);
        if (preg_match("/url=['\"](.*?)['\"]/", $cnt, $m)) $c['url'] = $m[1];
        if (preg_match("/to=(\d+)/", $cnt, $m)) $c['to'] = $m[1];
    }
    $bs = exec_root("sh $bm status");
    $c['onboot'] = ($bs === '1');
    return $c;
}

function write_config($sp, $url, $to, $boot, $bm) {
    $u = escapeshellcmd($url);
    $cmd1 = "sed -i \"s|url=.*|url='$u'|g\" $sp";
    $cmd2 = "sed -i \"s|to=.*|to=$to|g\" $sp";
    exec_root($cmd1);
    exec_root($cmd2);
    $act = $boot ? "enable" : "disable";
    exec_root("chmod +x $bm"); 
    exec_root("sh $bm $act");
    return true;
}

function is_running($pf) {
    if (!file_exists($pf)) return false;
    $pid = trim(file_get_contents($pf));
    return !empty($pid) && file_exists("/proc/$pid");
}

function start_script($sp) {
    exec_root("chmod +x $sp");
    exec("su -c 'sh $sp start' > /dev/null 2>&1 &");
    sleep(1);
}

function stop_script($pf) {
    if (file_exists($pf)) {
        $pid = trim(file_get_contents($pf));
        if (!empty($pid)) {
            exec_root("kill -15 $pid"); sleep(1);
            exec_root("kill -9 $pid");
        }
        unlink($pf);
    }
}

function get_log($lf) {
    if (!file_exists($lf)) return "Log file not found.";
    $l = file_get_contents($lf);
    $l = preg_replace_callback('/\[(.*?)\] Active Connection, HTTP=\(204\), latency=\(([\d.]+)(ms)\)/', function($m) {
        $r = min(floatval($m[2])/500, 1.0);
        $c = "rgb(" . min(255, round($r*255)) . "," . max(0, round(255-($r*255))) . ",0)";
        return "[$m[1]] <span style='color:#4ade80'>Active</span>, lat=(<span style='color:$c;font-weight:bold'>$m[2]</span>$m[3])";
    }, $l);
    $l = preg_replace_callback('/\[(.*?)\] Connection Lost(.*?)/', function($m) {
        return "[$m[1]] <span style='color:#f87171'>Lost</span>$m[2]";
    }, $l);
    return nl2br($l);
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => is_running($pid_file)?'running':'stopped', 'log' => get_log($log_file)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'configure') {
        $u = $_POST['url'] ?? '';
        $t = intval($_POST['timeout'] ?? 7);
        $b = isset($_POST['onboot']); 
        write_config($script_path, $u, $t, $b, $boot_manager);
        echo json_encode(['status'=>'success', 'msg'=>'Configuration Saved!']);
        exit;
    } elseif ($a === 'start') { 
        start_script($script_path); 
    } elseif ($a === 'stop') { 
        stop_script($pid_file); 
    } elseif ($a === 'clear_log') {
        file_put_contents($log_file, '');
        echo "ok";
        exit;
    }
}

$cfg = read_config($script_path, $boot_manager);
$run = is_running($pid_file);
$log = get_log($log_file);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Auto Airplane</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    :root { --bg: #f8f9fa; --card: #ffffff; --text: #2d3748; --sub: #718096; --border: #e2e8f0; --pri: #fb8c00; --suc: #2dce89; --dang: #f5365c; --term: #1a202c; --term-tx: #e2e8f0; --rad: 12px; --shd: 0 4px 6px -1px rgba(0,0,0,0.05); }
    @media (prefers-color-scheme: dark) { :root { --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --sub: #a0a0a0; --border: #2d2d2d; --pri: #ff9800; --term: #000; --shd: 0 4px 6px -1px rgba(0,0,0,0.4); } }
    * { box-sizing: border-box; margin: 0; padding: 0; outline: none; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); padding: 20px; max-width: 800px; margin: 0 auto; }
    
    header { text-align: center; margin-bottom: 25px; }
    h1 { font-size: 1.4rem; font-weight: 700; color: var(--pri); margin-bottom: 4px; text-transform: uppercase; }
    .sub { font-size: 0.9rem; color: var(--sub); }

    .card { background: var(--card); border-radius: var(--rad); padding: 20px; box-shadow: var(--shd); border: 1px solid var(--border); margin-bottom: 20px; }
    .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .ti { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    
    .bdg { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
    .run { background: rgba(45,206,137,0.1); color: var(--suc); border: 1px solid var(--suc); }
    .stp { background: rgba(245,54,92,0.1); color: var(--dang); border: 1px solid var(--dang); }

    .grp { margin-bottom: 15px; }
    label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 6px; color: var(--sub); }
    input[type=text], input[type=number] { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); transition:0.2s; }
    input:focus { border-color: var(--pri); box-shadow: 0 0 0 2px rgba(251,140,0,0.2); }

    .tgl { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; cursor: pointer; border-top: 1px dashed var(--border); margin-top: 10px; }
    .sw { position: relative; display: inline-block; width: 46px; height: 26px; }
    .sw input { opacity: 0; width: 0; height: 0; }
    .sl { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: var(--border); transition: .4s; border-radius: 34px; }
    .sl:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    input:checked + .sl { background: var(--pri); }
    input:checked + .sl:before { transform: translateX(20px); }

    .btns { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
    .btn { padding: 12px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; color: white; font-size: 0.9rem; width: 100%; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .bp { background: var(--pri); } .bd { background: var(--dang); } 
    .bs { background: var(--pri); margin-top: 15px; box-shadow: 0 4px 6px rgba(251,140,0,0.2); }
    .bs:hover { opacity: 0.9; transform: translateY(-1px); }
    
    .act-link { font-size: 0.75rem; font-weight: 700; color: var(--dang); cursor: pointer; text-decoration: none; padding: 4px 8px; border: 1px solid var(--dang); border-radius: 6px; transition: 0.2s; }
    .act-link:hover { background: var(--dang); color: white; }

    .term { background: var(--term); color: var(--term-tx); border-radius: 12px; padding: 15px; height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.8rem; border: 1px solid var(--border); white-space: pre-wrap; }
    .icon { width: 20px; height: 20px; fill: currentColor; }
    
    #toast { visibility: hidden; min-width: 250px; background: var(--suc); color: #fff; text-align: center; border-radius: 50px; padding: 12px; position: fixed; z-index: 100; bottom: 30px; left: 50%; transform: translateX(-50%); box-shadow: 0 4px 10px rgba(0,0,0,0.2); font-weight: 600; opacity: 0; transition: 0.3s; }
    #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
</style>
</head>
<body>

<div class="container">
    <header>
        <h1>Auto Airplane</h1>
        <p class="sub">Automated Network Refresh System</p>
    </header>

    <div class="card">
        <div class="head">
            <div class="ti"><svg class="icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg> Status</div>
            <div class="bdg <?php echo $run?'run':'stp'; ?>" id="sb"><span id="st"><?php echo $run?'RUNNING':'STOPPED'; ?></span></div>
        </div>
        <div class="btns">
            <form method="post" style="display:contents" class="act-form"><input type="hidden" name="action" value="start">
            <button type="submit" id="b-on" class="btn bp" <?php echo $run?'disabled':'';?>><svg class="icon" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg> Start</button></form>
            <form method="post" style="display:contents" class="act-form"><input type="hidden" name="action" value="stop">
            <button type="submit" id="b-off" class="btn bd" <?php echo !$run?'disabled':'';?>><svg class="icon" viewBox="0 0 24 24"><path d="M6 6h12v12H6z"/></svg> Stop</button></form>
        </div>
    </div>

    <div class="card">
        <div class="ti" style="margin-bottom:20px"><svg class="icon" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg> Config</div>
        <form method="post" id="cfg-form">
            <input type="hidden" name="action" value="configure">
            <div class="grp"><label>Target URL</label><input type="text" name="url" value="<?php echo htmlspecialchars($cfg['url']); ?>" required></div>
            <div class="grp"><label>Timeout (s)</label><input type="number" name="timeout" value="<?php echo htmlspecialchars($cfg['to']); ?>" min="1" required></div>
            <div class="tgl">
                <span style="font-weight:600; font-size:0.9rem; color:var(--text)">Enable on Boot</span>
                <label class="sw"><input type="checkbox" name="onboot" value="1" <?php echo $cfg['onboot']?'checked':'';?>><span class="sl"></span></label>
            </div>
            <button type="submit" class="btn bs">Save Changes</button>
        </form>
    </div>

    <div class="card">
        <div class="head" style="margin-bottom:15px">
            <div class="ti"><svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg> Live Logs</div>
            <a href="#" onclick="clr(event)" class="act-link">CLEAR</a>
        </div>
        <div class="term" id="logs"><?= $log ?></div>
    </div>
    <div id="toast">Settings Saved!</div>
</div>

<script>
function up() {
    $.get('?ajax=1', function(d) {
        if(d.status === 'running') {
            $('#sb').removeClass('stp').addClass('run'); $('#st').text('RUNNING');
            $('#b-on').prop('disabled',true); $('#b-off').prop('disabled',false);
        } else {
            $('#sb').removeClass('run').addClass('stp'); $('#st').text('STOPPED');
            $('#b-on').prop('disabled',false); $('#b-off').prop('disabled',true);
        }
        var l = $('#logs');
        var b = l[0].scrollHeight - l.scrollTop() <= l.outerHeight() + 50;
        l.html(d.log);
        if(b) l.scrollTop(l[0].scrollHeight);
    });
}
function toast(m) {
    $('#toast').text(m).addClass('show');
    setTimeout(()=>$('#toast').removeClass('show'), 3000);
}
function clr(e) {
    e.preventDefault();
    if(confirm('Clear logs?')) {
        $.post('', {action: 'clear_log'}, function() { up(); });
    }
}
$(document).ready(function() {
    $('#logs').scrollTop($('#logs')[0].scrollHeight);
    setInterval(up, 2000);

    $('#cfg-form').submit(function(e) {
        e.preventDefault();
        var b = $(this).find('button'), t = b.html();
        b.prop('disabled',true).html('Saving...');
        $.ajax({
            type: 'POST', url: '', data: $(this).serialize(),
            success: function(r) { toast('Configuration Saved!'); },
            error: function(r) { alert('Error saving config'); },
            complete: function() { b.prop('disabled',false).html(t); }
        });
    });

    $('.act-form').submit(function(e) {
        e.preventDefault();
        var b = $(this).find('button');
        b.prop('disabled',true);
        $.ajax({
            type: 'POST', url: '', data: $(this).serialize(),
            success: function() { up(); },
            error: function() { b.prop('disabled',false); }
        });
    });
});
</script>
</body>
</html>