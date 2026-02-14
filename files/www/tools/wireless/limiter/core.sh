#!/system/bin/sh

# ==========================================
# CORE BACKEND: FORWARD MARKING
# ==========================================

BASE_DIR="/data/adb/php8/files/www/tools/wireless/limiter"
LIMIT_FILE="${BASE_DIR}/rules.txt"
LOG_FILE="${BASE_DIR}/debug.log"

log() { echo "[$(date '+%H:%M:%S')] $1" >> "$LOG_FILE"; }

# 1. DETEKSI LAN (SUMBER KONEKSI DARI HP KE LAPTOP/USER)
# Filter: ncm0 (USB), rndis0 (USB), ap0/wlan (Hotspot)
CANDIDATES=$(ip addr show | grep 'inet ' | grep -v '127.0.0.1' | grep -vE 'tun|dummy')
LAN_IFACE=$(echo "$CANDIDATES" | grep -E 'ncm0|rndis0' | awk '{print $NF}' | head -n 1)
if [ -z "$LAN_IFACE" ]; then LAN_IFACE=$(echo "$CANDIDATES" | grep -E 'ap0|swlan|wlan1' | awk '{print $NF}' | head -n 1); fi
if [ -z "$LAN_IFACE" ]; then LAN_IFACE=$(echo "$CANDIDATES" | grep 'wlan0' | awk '{print $NF}' | head -n 1); fi

# 2. DETEKSI WAN (SUMBER INTERNET HP)
# Cari interface yang punya jalur ke internet (Default Gateway)
WAN_IFACE=$(ip route show table 0 | grep default | awk '{print $5}' | head -n 1)

# Fallback 1: Cek table main
if [ -z "$WAN_IFACE" ]; then WAN_IFACE=$(ip route show | grep default | awk '{print $5}' | head -n 1); fi

# Fallback 2: Tebak nama interface data seluler (ccmni/rmnet) atau tun (VPN)
if [ -z "$WAN_IFACE" ]; then WAN_IFACE=$(echo "$CANDIDATES" | grep -E 'ccmni|rmnet|tun' | awk '{print $NF}' | head -n 1); fi

# HINDARI LAN == WAN (Loopback error prevention)
if [ "$LAN_IFACE" == "$WAN_IFACE" ]; then
    # Jika LAN terbaca sama dengan WAN, cari WAN alternatif
    WAN_IFACE=$(echo "$CANDIDATES" | grep -v "$LAN_IFACE" | grep -E 'ccmni|rmnet|tun|wlan' | awk '{print $NF}' | head -n 1)
fi

log "DETECTED LAN: $LAN_IFACE (Download Target)"
log "DETECTED WAN: $WAN_IFACE (Upload Target)"

if [ -z "$LAN_IFACE" ]; then log "CRITICAL: No LAN found"; exit 1; fi
# Jika WAN tidak ketemu, upload limit tidak akan jalan, tapi download tetap jalan
if [ -z "$WAN_IFACE" ]; then log "WARNING: No WAN found! Upload limit will fail."; fi

exec_tc() {
    local cmd="$@"
    err=$($cmd 2>&1)
    if [ -n "$err" ]; then log "TC_ERR: $cmd -> $err"; fi
}

mac2id() { echo "0x$(echo $1 | cut -d: -f6)$(echo $1 | cut -d: -f5)"; }

get_ip() {
    local m=$(echo "$1" | tr '[:upper:]' '[:lower:]')
    local i=$(cat /proc/net/arp | grep -i "$m" | awk '{print $1}')
    if [ -z "$i" ]; then i=$(ip neigh show | grep -i "$m" | awk '{print $1}'); fi
    echo "$i"
}

sanitize_rate() {
    if [ "$1" == "0mbit" ] || [ "$1" == "0kbit" ] || [ -z "$1" ]; then echo "2000mbit"; else echo "$1"; fi
}

init_iptables() {
    # Buat Chain Khusus di Mangle Table
    iptables -t mangle -N LIMIT_FWD >/dev/null 2>&1
    
    # Bersihkan referensi lama
    iptables -t mangle -D FORWARD -j LIMIT_FWD >/dev/null 2>&1
    iptables -t mangle -F LIMIT_FWD
    
    # Pasang di FORWARD Chain (Jalur LAN ke WAN)
    iptables -t mangle -I FORWARD -j LIMIT_FWD
}

reset_tc() {
    log "Resetting Rules..."
    tc qdisc del dev $LAN_IFACE root >/dev/null 2>&1
    if [ -n "$WAN_IFACE" ]; then tc qdisc del dev $WAN_IFACE root >/dev/null 2>&1; fi
    
    # Bersihkan IPTables
    iptables -t mangle -D FORWARD -j LIMIT_FWD >/dev/null 2>&1
    iptables -t mangle -F LIMIT_FWD >/dev/null 2>&1
    iptables -t mangle -X LIMIT_FWD >/dev/null 2>&1
}

init_tc() {
    local down=$(sanitize_rate $1)
    local up=$(sanitize_rate $2)
    log "Init Global: D=$down U=$up"
    
    init_iptables

    # --- A. DOWNLOAD (LAN - EGRESS) ---
    exec_tc tc qdisc add dev $LAN_IFACE root handle 1: htb default 9999 r2q 1
    exec_tc tc class add dev $LAN_IFACE parent 1: classid 1:1 htb rate 2000mbit ceil 2000mbit
    exec_tc tc class add dev $LAN_IFACE parent 1:1 classid 1:9999 htb rate ${down} ceil ${down} prio 50

    # --- B. UPLOAD (WAN - EGRESS via MARK) ---
    if [ -n "$WAN_IFACE" ]; then
        exec_tc tc qdisc add dev $WAN_IFACE root handle 1: htb default 9999 r2q 1
        exec_tc tc class add dev $WAN_IFACE parent 1: classid 1:1 htb rate 2000mbit ceil 2000mbit
        exec_tc tc class add dev $WAN_IFACE parent 1:1 classid 1:9999 htb rate ${up} ceil ${up} prio 50
    fi
}

apply_user() {
    local mac=$1
    local raw_down=$2
    local raw_up=$3
    local ip=$(get_ip $mac)
    
    if [ -n "$ip" ]; then
        local hex_id=$(mac2id $mac)
        local dec_id=$(printf "%d" $hex_id)
        local class_id="1:$hex_id"
        
        log "User $ip -> ID:$hex_id"
        
        # --- 1. DOWNLOAD (LAN) ---
        if [ "$raw_down" != "0mbit" ] && [ "$raw_down" != "0kbit" ]; then
            exec_tc tc class add dev $LAN_IFACE parent 1:1 classid $class_id htb rate $raw_down ceil $raw_down prio 10
            exec_tc tc filter add dev $LAN_IFACE parent 1: protocol ip prio 10 u32 match ip dst $ip/32 flowid $class_id
        fi
        
        # --- 2. UPLOAD (WAN + FORWARD MARK) ---
        if [ "$raw_up" != "0mbit" ] && [ "$raw_up" != "0kbit" ] && [ -n "$WAN_IFACE" ]; then
            # Mark Paket di Chain FORWARD (Sebelum NAT, tapi sudah pasti akan di-routing)
            iptables -t mangle -A LIMIT_FWD -s $ip -j MARK --set-mark $dec_id
            iptables -t mangle -A LIMIT_FWD -s $ip -j RETURN
            
            # Buat Class di WAN
            exec_tc tc class add dev $WAN_IFACE parent 1:1 classid $class_id htb rate $raw_up ceil $raw_up prio 10
            
            # Filter di WAN berdasarkan Mark
            exec_tc tc filter add dev $WAN_IFACE parent 1: protocol ip prio 10 handle $dec_id fw flowid $class_id
        fi
    else
        log "Skipped $mac (Offline)"
    fi
}

action=$1

if [ "$action" == "refresh" ]; then
    echo "--- NEW SESSION (FWD) ---" >> "$LOG_FILE"
    if [ ! -f "$LIMIT_FILE" ]; then exit 0; fi

    gl=$(head -n 1 $LIMIT_FILE)
    g_down=$(echo $gl | cut -d'|' -f2)
    g_up=$(echo $gl | cut -d'|' -f3)
    
    reset_tc
    init_tc "$g_down" "$g_up"
    
    tail -n +2 $LIMIT_FILE | while read line; do
        mac=$(echo $line | cut -d'|' -f1)
        ud=$(echo $line | cut -d'|' -f2)
        uu=$(echo $line | cut -d'|' -f3)
        apply_user "$mac" "$ud" "$uu"
    done
fi

if [ "$action" == "reset" ]; then reset_tc; log "Reset DONE."; fi