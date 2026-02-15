#!/system/bin/sh
DIR_MODUL="/data/adb/modules/php8-webserver/"
cd "$DIR_MODUL" || { echo "❌ Folder $DIR_MODUL tidak ditemukan!"; exit 1; }
if [ ! -f "module.prop" ]; then
    echo "❌ File module.prop tidak ditemukan di folder ini!"
    exit 1
fi
VERSION_CODE=$(date +'%Y%m%d')
echo "📦 Memperbarui versi modul Magisk..."
echo "--- Info Saat Ini ---"
grep "^version=" module.prop
grep "^versionCode=" module.prop
echo "---------------------"
version="3.3.5"

if [ -z "$version" ]; then
    echo "❌ Versi tidak boleh kosong! Dibatalkan."
    exit 1
fi
sed -i "s/^version=.*/version=${version}/g" module.prop
sed -i "s/^versionCode=.*/versionCode=${VERSION_CODE}/g" module.prop

echo ""
echo "✅ module.prop berhasil diperbarui!"
echo "--- Info Terbaru ---"
grep "^version=" module.prop
grep "^versionCode=" module.prop
echo "--------------------"
