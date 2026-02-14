#!/bin/bash

echo "🔄 Memulai sinkronisasi ke GitHub..."

# 1. Menambahkan semua file baru dan perubahan
git add .

# 2. Menyimpan perubahan dengan pesan otomatis (Tanggal & Waktu)
WAKTU=$(date +'%Y-%m-%d %H:%M:%S')
git commit -m "Update otomatis: $WAKTU"

# 3. Mengunggah ke GitHub
git push origin main

echo "✅ Selesai! Semua file berhasil diperbarui ke GitHub."
