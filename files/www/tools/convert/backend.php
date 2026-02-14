<?php
// backend.php - Full Logic Ported from Python
// Usage: php backend.php <mode> <data> [args...]

// Matikan error reporting agar output bersih untuk shell_exec
error_reporting(0);

// --- 1. FUNGSI DECODE ---

function decode_vmess($link) {
    if (strpos($link, "vmess://") !== 0) return null;
    $b64 = substr($link, 8);
    // Fix Padding
    $pad = strlen($b64) % 4;
    if ($pad) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($b64);
    if (!$decoded) return null;
    return json_decode($decoded, true);
}

function decode_url($link) {
    $parsed = parse_url($link);
    if (!$parsed || !isset($parsed['scheme'])) return null;
    if (!in_array($parsed['scheme'], ['trojan', 'vless'])) return null;

    $params = [];
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $params);
    }

    return [
        "type" => $parsed['scheme'],
        "user" => $parsed['user'] ?? '',
        "server" => $parsed['host'] ?? '',
        "port" => $parsed['port'] ?? 443,
        "params" => $params,
        "remark" => isset($parsed['fragment']) ? urldecode($parsed['fragment']) : ''
    ];
}

// --- 2. GENERATOR ---

function generate_yaml($link) {
    // VMESS
    $vmess_data = decode_vmess($link);
    if ($vmess_data) {
        $d = $vmess_data;
        $name = $d['ps'] ?? 'Vmess-Import';
        $server = $d['add'] ?? '';
        $port = $d['port'] ?? 443;
        $uuid = $d['id'] ?? '';
        $aid = $d['aid'] ?? 0;
        $cipher = $d['scy'] ?? 'auto';
        $net = $d['net'] ?? 'tcp';
        $tls = ($d['tls'] ?? '') === 'tls';
        $sni = $d['sni'] ?? '';
        $path = $d['path'] ?? '/';
        $host = $d['host'] ?? '';

        $y  = "  - name: $name\n";
        $y .= "    server: $server\n";
        $y .= "    port: $port\n";
        $y .= "    type: vmess\n";
        $y .= "    uuid: $uuid\n";
        $y .= "    alterId: $aid\n";
        $y .= "    cipher: $cipher\n";
        $y .= "    tls: " . ($tls ? 'true' : 'false') . "\n";
        $y .= "    skip-cert-verify: true\n";
        if ($sni) $y .= "    servername: $sni\n";

        if ($net == "ws") {
            $y .= "    network: ws\n";
            $y .= "    ws-opts:\n";
            $y .= "      path: $path\n";
            $header_host = $host ? $host : $sni;
            if ($header_host) {
                $y .= "      headers:\n";
                $y .= "        Host: $header_host\n";
            }
        }
        $y .= "    udp: true";
        return $y;
    }

    // TROJAN / VLESS
    $url_data = decode_url($link);
    if ($url_data) {
        $t_type = $url_data['type'];
        $name = $url_data['remark'] ?: "$t_type-import";
        $server = $url_data['server'];
        $port = $url_data['port'];
        $password = $url_data['user'];
        $params = $url_data['params'];
        $net = $params['type'] ?? 'tcp';
        $security = $params['security'] ?? 'none';
        $sni = $params['sni'] ?? '';
        $path = $params['path'] ?? '/';
        $host = $params['host'] ?? '';

        $y  = "  - name: $name\n";
        $y .= "    server: $server\n";
        $y .= "    port: $port\n";
        $y .= "    type: $t_type\n";

        if ($t_type == 'trojan') {
            $y .= "    password: $password\n";
            if ($sni) $y .= "    sni: $sni\n";
        } elseif ($t_type == 'vless') {
            $y .= "    uuid: $password\n";
            if ($sni) $y .= "    servername: $sni\n";
            if ($security == 'tls') $y .= "    tls: true\n";
        }

        $y .= "    skip-cert-verify: true\n";

        if ($net == 'ws') {
            $y .= "    network: ws\n";
            $y .= "    ws-opts:\n";
            $y .= "      path: $path\n";
            $header_host = $host ? $host : $sni;
            if ($header_host) {
                $y .= "      headers:\n";
                $y .= "        Host: $header_host\n";
            }
        } elseif ($net == 'grpc') {
            $y .= "    network: grpc\n";
            $y .= "    grpc-opts:\n";
            $serviceName = $params['serviceName'] ?? $path;
            $y .= "      grpc-service-name: $serviceName\n";
        }

        $y .= "    udp: true";
        return $y;
    }

    return null;
}

// --- 3. SMART CLEANER ---

function smart_clean_yaml($content) {
    // Normalisasi newline
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $lines = explode("\n", $content);
    $cleaned = [];

    // Cek Header
    $has_proxies = false;
    foreach ($lines as $l) {
        if (trim($l) == "proxies:") { $has_proxies = true; break; }
    }
    if (!$has_proxies) $cleaned[] = "proxies:";

    foreach ($lines as $line) {
        $raw = trim($line);
        if ($raw === "") continue; // Hapus baris kosong

        // --- FIX SPASI SETELAH TITIK DUA ---
        if (strpos($raw, "#") !== 0 && strpos($raw, ":") !== false) {
            if (substr($raw, -1) === ":") {
                // Header block (ws-opts:)
                $raw = rtrim($raw, ":") . ":";
            } else {
                // Key Value (server:google.com)
                $parts = explode(":", $raw, 2);
                if (count($parts) == 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);
                    $raw = "$key: $val";
                }
            }
        }

        // --- LOGIKA INDENTASI ---
        // Level 0
        if ($raw == "proxies:") {
            if (!in_array("proxies:", $cleaned)) $cleaned[] = "proxies:";
            continue;
        }

        // Level 1 (2 Spasi) - List Item
        if (strpos($raw, "- name:") === 0 || strpos($raw, "#") === 0) {
            $cleaned[] = "  " . $raw;
            continue;
        }

        // Level 3 (6 Spasi) - Sub-properties
        if (strpos($raw, "path:") === 0 || strpos($raw, "headers:") === 0 || strpos($raw, "grpc-service-name:") === 0) {
            $cleaned[] = "      " . $raw;
            continue;
        }

        // Level 4 (8 Spasi) - Deep properties
        if (strpos($raw, "Host:") === 0) {
            $cleaned[] = "        " . $raw;
            continue;
        }

        // Level 2 (4 Spasi) - Default
        $cleaned[] = "    " . $raw;
    }

    return implode("\n", $cleaned);
}

// --- 4. ACTION HANDLERS ---

function process_account_action($content, $target_name, $action, $new_content = null) {
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $lines = explode("\n", $content);
    $final_lines = [];
    $buffer_lines = [];

    $in_target_block = false;

    foreach ($lines as $line) {
        $raw = trim($line);

        if (strpos($raw, "- name:") === 0) {
            // Jika sebelumnya lagi di block target, matikan flagnya karena ketemu nama baru
            if ($in_target_block) $in_target_block = false;

            $parts = explode(":", $raw, 2);
            if (count($parts) > 1) {
                $current_name = trim($parts[1]);
                if ($current_name == $target_name) {
                    $in_target_block = true;

                    if ($action == "get") {
                        $buffer_lines[] = $line;
                        continue;
                    }

                    if ($action == "delete") {
                        continue;
                    }

                    if ($action == "replace") {
                        if ($new_content) {
                            $new_lines = explode("\n", $new_content);
                            foreach ($new_lines as $nl) $final_lines[] = $nl;
                        }
                        continue;
                    }
                }
            }
        }

        if ($in_target_block) {
            if ($action == "get") {
                $buffer_lines[] = $line;
            }
        } else {
            $final_lines[] = $line;
        }
    }

    if ($action == "get") {
        return smart_clean_yaml(implode("\n", $buffer_lines));
    } else {
        return implode("\n", $final_lines);
    }
}

// --- MAIN CLI HANDLER ---

if ($argc > 2) {
    $mode = $argv[1];
    $data = $argv[2];

    if ($mode == "convert") {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $data));
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            $res = generate_yaml($line);
            if ($res) echo $res . "\n";
        }
    }
    elseif ($mode == "clean") {
        echo smart_clean_yaml($data);
    }
    elseif ($mode == "delete") {
        if ($argc > 3) {
            $target = $argv[3];
            echo process_account_action($data, $target, "delete");
        }
    }
    elseif ($mode == "get") {
        if ($argc > 3) {
            $target = $argv[3];
            echo process_account_action($data, $target, "get");
        }
    }
    elseif ($mode == "replace") {
        if ($argc > 4) {
            $target = $argv[3];
            $new_c = $argv[4];
            
            // Clean konten baru dulu
            $new_c_clean = smart_clean_yaml($new_c);
            // Hapus 'proxies:' karena ini block replacement
            $new_c_clean = str_replace("proxies:\n", "", $new_c_clean);
            
            echo process_account_action($data, $target, "replace", trim($new_c_clean));
        }
    }
} else {
    echo "# Error: Arguments not complete";
}
?>
