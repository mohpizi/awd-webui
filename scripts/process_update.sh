#!/system/bin/sh

URL_DOWNLOAD="$1"
DIR_KERJA=$(dirname "$0")
TARGET_DIR="/data/adb/php8"

cd "$DIR_KERJA" || exit 1

# Bersihkan URL
URL_DOWNLOAD=$(echo "$URL_DOWNLOAD" | tr -d '[:space:]')
rm -f update_temp.zip

# Kirim sinyal awal
echo "0%"
echo "Downloading..."

DOWNLOAD_SUCCESS=0

# --- METODE DOWNLOAD (Output dipipe ke tr agar jadi newline) ---

# 1. Coba Busybox Wget (Umum di Android)
if busybox wget --help >/dev/null 2>&1; then
    busybox wget --no-check-certificate -U "Mozilla/5.0" -O update_temp.zip "$URL_DOWNLOAD" 2>&1 | tr '\r' '\n'
    if [ -s "update_temp.zip" ]; then DOWNLOAD_SUCCESS=1; fi
fi

# 2. Jika gagal, coba System Curl
if [ $DOWNLOAD_SUCCESS -eq 0 ] && [ -f "/system/bin/curl" ]; then
    /system/bin/curl -k -L -A "Mozilla/5.0" -o update_temp.zip "$URL_DOWNLOAD" 2>&1 | tr '\r' '\n'
    if [ -s "update_temp.zip" ]; then DOWNLOAD_SUCCESS=1; fi
fi

# 3. Fallback Curl biasa
if [ $DOWNLOAD_SUCCESS -eq 0 ]; then
    if command -v curl >/dev/null 2>&1; then
        curl -k -L -A "Mozilla/5.0" -o update_temp.zip "$URL_DOWNLOAD" 2>&1 | tr '\r' '\n'
        if [ -s "update_temp.zip" ]; then DOWNLOAD_SUCCESS=1; fi
    fi
fi

# --- VERIFIKASI ---
if [ ! -s "update_temp.zip" ]; then
    echo "ERROR: Download failed (0 bytes/not found)."
    exit 1
fi

SIZE=$(wc -c < "update_temp.zip")
if [ $SIZE -lt 1000 ]; then
    echo "ERROR: File too small (<1KB). Check URL."
    rm -f update_temp.zip
    exit 1
fi

# --- INSTALASI ---
echo "90%"
echo "Extracting..."

if [ ! -d "$TARGET_DIR" ]; then mkdir -p "$TARGET_DIR"; fi

busybox unzip -o update_temp.zip -d "$TARGET_DIR" >/dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "95%"
    echo "Finalizing..."
    if [ -f "$TARGET_DIR/install.sh" ]; then
        chmod 755 "$TARGET_DIR/install.sh"
        sh "$TARGET_DIR/install.sh"
        rm -f "$TARGET_DIR/install.sh"
    fi
    rm -f update_temp.zip
    echo "100%"
    echo "SUKSES"
else
    echo "ERROR: Unzip failed"
    exit 1
fi