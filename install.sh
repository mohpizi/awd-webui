#!/system/bin/sh

# --- KONFIGURASI PATH & ID ---
DIR_MODUL="/data/adb/modules/php8-webserver/"
php_data_dir="/data/adb/php8"
php_bin_dir="${php_data_dir}/files/bin"
system_uid="1000"
system_gid="1000"
version="3.3.5"

cd "$DIR_MODUL" || { echo "❌ Folder $DIR_MODUL tidak ditemukan!"; exit 1; }

# --- UPDATE VERSION ---
echo "📦 Memperbarui versi modul Magisk..."
VERSION_CODE=$(date +'%Y%m%d')
if [ -f "module.prop" ]; then
    sed -i "s/^version=.*/version=${version}/g" module.prop
    sed -i "s/^versionCode=.*/versionCode=${VERSION_CODE}/g" module.prop
    echo "✅ module.prop updated."
fi

# --- FITUR: FIX PERMISSION (ADAPTASI DARI MAGISK SCRIPT) ---
echo "🔧 Setting Files Permission..."

# Fungsi pembantu untuk meniru set_perm_recursive
# format: set_recursive [path] [uid] [gid] [dir_mode] [file_mode]
set_recursive() {
    chown -R $2:$3 "$1"
    find "$1" -type d -exec chmod $4 {} +
    find "$1" -type f -exec chmod $5 {} +
}

# Fungsi pembantu untuk meniru set_perm
# format: set_single [path] [uid] [gid] [mode]
set_single() {
    chown $2:$3 "$1"
    chmod $4 "$1"
}

# 1. Setting Modul Path
set_recursive "$DIR_MODUL" 0 0 0755 0644

# 2. Setting PHP Data Dir secara umum
set_recursive "$php_data_dir" 0 0 0755 0644

# 3. Setting Scripts (Semua 0755 agar bisa dieksekusi)
set_recursive "${php_data_dir}/scripts" 0 0 0755 0755

# 4. Setting Config & WWW (Menggunakan UID/GID System 1000)
set_recursive "${php_data_dir}/files/config" 0 0 0755 0644
set_recursive "${php_data_dir}/files/www" $system_uid $system_gid 0755 0644

# 5. Setting Binaries (Khusus folder bin)
set_recursive "$php_bin_dir" $system_uid $system_gid 0755 0755

# 6. Setting Spesifik File (Single Set)
echo "🚀 Applying specific binary permissions..."

# Scripts
set_single "${php_data_dir}/scripts/php_run" 0 0 0755
set_single "${php_data_dir}/scripts/ttyd_run" 0 0 0755
set_single "${php_data_dir}/scripts/sfa" 0 0 0755
set_single "${php_data_dir}/scripts/php_inotifyd" 0 0 0755

# Binaries
set_single "${php_data_dir}/files/bin/php" 0 0 0755
set_single "${php_data_dir}/files/bin/ttyd" 0 0 0755

# Configs (UID/GID 1000)
set_single "${php_data_dir}/files/config/php.config" $system_uid $system_gid 0755
set_single "${php_data_dir}/files/config/php.ini" $system_uid $system_gid 0755

echo "--------------------"
echo "✅ Fix Permission Berhasil Diterapkan!"
