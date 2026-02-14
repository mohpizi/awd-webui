#!/system/bin/sh

while [ "$(getprop sys.boot_completed)" != "1" ]; do
  sleep 1
done
sleep 5

php_base_dir="/data/adb/php8"
module_dir=$(dirname $(realpath "$0"))

rm -f "${php_base_dir}/files/tmp/php.pid"
rm -f "${php_base_dir}/files/tmp/ttyd.pid"
chmod +x "${php_base_dir}/scripts/php_run"
chmod +x "${php_base_dir}/scripts/php_inotifyd"
chmod +x "${php_base_dir}/scripts/ttyd_run"

if [ ! -f "${module_dir}/disable" ]; then
    sh "${php_base_dir}/scripts/php_run" -s > /dev/null 2>&1
    sh "${php_base_dir}/scripts/ttyd_run" -s > /dev/null 2>&1
fi

nohup inotifyd "${php_base_dir}/scripts/php_inotifyd" "${module_dir}" > /dev/null 2>&1 &

iptables -t nat -F PREROUTING 
TS_INTERFACE=$(ip link | grep -o 'tailscale[0-9]*')

if [ -n "$TS_INTERFACE" ]; then
    iptables -t nat -A PREROUTING -i "$TS_INTERFACE" -p tcp --dport 80 -j REDIRECT --to-ports 80
fi

(
  while true; do
    sleep 60
    
    if [ -f "${module_dir}/disable" ]; then
        continue
    fi

    if ! pgrep -f "php" > /dev/null; then
       rm -f "${php_base_dir}/files/tmp/php.pid"
       sh "${php_base_dir}/scripts/php_run" -s > /dev/null 2>&1
    fi

    if ! pgrep -f "ttyd" > /dev/null; then
       rm -f "${php_base_dir}/files/tmp/ttyd.pid"
       sh "${php_base_dir}/scripts/ttyd_run" -s > /dev/null 2>&1
    fi
    
    if ! pgrep -f "php_inotifyd" > /dev/null; then
       nohup inotifyd "${php_base_dir}/scripts/php_inotifyd" "${module_dir}" > /dev/null 2>&1 &
    fi
  done
) &
