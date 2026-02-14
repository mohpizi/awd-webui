<?php
// Set header JSON agar dikenali oleh JS
header('Content-Type: application/json');

// --- FUNGSI FORMAT BYTES ---
function formatSize($kb) {
    if ($kb >= 1048576) { // > 1 GB
        return round($kb / 1048576, 1) . ' GB';
    } elseif ($kb >= 1024) { // > 1 MB
        return round($kb / 1024, 1) . ' MB';
    } else {
        return $kb . ' KB';
    }
}

// --- 1. AMBIL DATA MEMORI (Optimized: Baca file sekali saja) ---
$memInfo = @file_get_contents('/proc/meminfo');

function getVal($key, $content) {
    if (preg_match('/^' . $key . ':\s+(\d+)/m', $content, $matches)) {
        return (int)$matches[1]; // Hasil dalam KB
    }
    return 0;
}

$memTotal = getVal('MemTotal', $memInfo);
$memFree = getVal('MemFree', $memInfo);
$memBuffers = getVal('Buffers', $memInfo);
$memCached = getVal('Cached', $memInfo);
$swapTotal = getVal('SwapTotal', $memInfo);
$swapFree = getVal('SwapFree', $memInfo);
$swapCached = getVal('SwapCache', $memInfo);
$dirty = getVal('Dirty', $memInfo);

// Perhitungan RAM
// MemAvailable biasanya lebih akurat jika ada, tapi kita pakai rumus manual agar konsisten dengan script lama:
// Available = Free + Buffers + Cached
$memAvailable = $memFree + $memBuffers + $memCached;
$memUsed = $memTotal - $memAvailable;

$memUsedPercent = ($memTotal > 0) ? round(($memUsed / $memTotal) * 100) : 0;

// Perhitungan Swap
$swapUsed = $swapTotal - $swapFree;
$swapUsedPercent = ($swapTotal > 0) ? round(($swapUsed / $swapTotal) * 100) : 0;


// --- 2. AMBIL CPU LOAD (Tanpa mpstat) ---
// Kita baca /proc/stat dua kali dengan jeda sebentar untuk melihat aktivitas CPU
function getCpuStats() {
    $stat = @file_get_contents('/proc/stat');
    if (preg_match('/^cpu\s+(.*)/m', $stat, $matches)) {
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim($matches[1])));
        // user + nice + system + idle + iowait + irq + softirq
        $total = 0;
        foreach ($parts as $part) $total += (int)$part;
        $idle = (int)$parts[3] + (int)$parts[4]; // idle + iowait
        return ['total' => $total, 'idle' => $idle];
    }
    return ['total' => 0, 'idle' => 0];
}

$cpu1 = getCpuStats();
usleep(150000); // Sleep 150ms (0.15 detik) untuk sampling
$cpu2 = getCpuStats();

$diffTotal = $cpu2['total'] - $cpu1['total'];
$diffIdle = $cpu2['idle'] - $cpu1['idle'];
$activeCpu = 0;

if ($diffTotal > 0) {
    $usage = ($diffTotal - $diffIdle);
    $activeCpu = round(($usage / $diffTotal) * 100, 1);
}

// --- 3. AMBIL FREKUENSI CPU & GPU LOAD ---
$cpuFreqRaw = @file_get_contents('/sys/devices/system/cpu/cpu0/cpufreq/cpuinfo_max_freq');
$cpuFreq = $cpuFreqRaw ? (intval(trim($cpuFreqRaw)) / 1000) . ' MHz' : 'N/A';

// GPU Busy (Mungkin berbeda tiap HP, pakai path yang kamu berikan)
$gpuLoadRaw = shell_exec('cat /sys/kernel/gpu/gpu_busy 2>/dev/null'); 
$gpuLoad = trim($gpuLoadRaw);
if ($gpuLoad === '') $gpuLoad = '0'; // Default jika file tidak ditemukan


// --- 4. OUTPUT JSON ---
echo json_encode([
    'total_memory' => formatSize($memTotal),
    'free_memory' => formatSize($memAvailable), // Ini yang ditampilkan sebagai "Free"
    'used_memory_percent' => $memUsedPercent,
    
    'swap_free' => formatSize($swapFree),
    'total_swap' => formatSize($swapTotal),
    'used_swap_percent' => $swapUsedPercent,
    
    'total_swapcache' => formatSize($swapCached),
    'total_dirty' => formatSize($dirty),
    
    'gpuFreq' => $cpuFreq, // Sesuai script aslimu, meski isinya CPU freq
    'gpuLoad' => $gpuLoad,
    'active' => $activeCpu
]);
?>
