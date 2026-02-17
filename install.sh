#!/system/bin/sh

DIR_MODUL="/data/adb/modules/php8-webserver/"
php_data_dir="/data/adb/php8"
php_bin_dir="${php_data_dir}/files/bin"
system_uid="1000"
system_gid="1000"
version="3.3.5"

cd "$DIR_MODUL" || { echo "❌ Folder $DIR_MODUL tidak ditemukan!"; exit 1; }

echo "📦 Memperbarui versi modul Magisk..."
VERSION_CODE=$(date +'%Y%m%d')
if [ -f "module.prop" ]; then
    sed -i "s/^version=.*/version=${version}/g" module.prop
    sed -i "s/^versionCode=.*/versionCode=${VERSION_CODE}/g" module.prop
    echo "✅ module.prop updated."
fi

echo "🔧 Setting Files Permission..."
set_recursive() {
    chown -R $2:$3 "$1"
    find "$1" -type d -exec chmod $4 {} +
    find "$1" -type f -exec chmod $5 {} +
}

set_single() {
    chown $2:$3 "$1"
    chmod $4 "$1"
}

set_recursive "$DIR_MODUL" 0 0 0755 0644
set_recursive "$php_data_dir" 0 0 0755 0644
set_recursive "${php_data_dir}/scripts" 0 0 0755 0755
set_recursive "${php_data_dir}/files/config" 0 0 0755 0644
set_recursive "${php_data_dir}/files/www" $system_uid $system_gid 0755 0644
set_recursive "$php_bin_dir" $system_uid $system_gid 0755 0755
echo "🚀 Applying specific binary permissions..."
set_single "${php_data_dir}/scripts/php_run" 0 0 0755
set_single "${php_data_dir}/scripts/ttyd_run" 0 0 0755
set_single "${php_data_dir}/scripts/sfa" 0 0 0755
set_single "${php_data_dir}/scripts/php_inotifyd" 0 0 0755
set_single "${php_data_dir}/files/bin/php" 0 0 0755
set_single "${php_data_dir}/files/bin/ttyd" 0 0 0755
set_single "${php_data_dir}/files/config/php.config" $system_uid $system_gid 0755
set_single "${php_data_dir}/files/config/php.ini" $system_uid $system_gid 0755

echo "--------------------"
echo "✅ Fix Permission Berhasil Diterapkan!"
