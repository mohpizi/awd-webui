#!/usr/bin/env python3
import json
import ssl
import sys
import re
import base64
import urllib.request
import urllib.parse
import subprocess
import os

# --- KONFIGURASI PATH ---
# Lokasi binary yang berisi kredensial (Sesuai PHP Anda)
CREDS_BINARY = "/data/adb/php8/files/bin/secure.so"

# --- FUNGSI AMBIL KREDENSIAL DARI SECURE.SO ---
def get_credentials():
    # 1. Cek apakah file binary ada
    if not os.path.exists(CREDS_BINARY):
        print(f"ERROR|Binary security tidak ditemukan di: {CREDS_BINARY}")
        sys.exit(1)

    # 2. Cek izin eksekusi (chmod +x)
    if not os.access(CREDS_BINARY, os.X_OK):
        print(f"ERROR|Binary {CREDS_BINARY} tidak memiliki izin eksekusi.")
        sys.exit(1)

    try:
        # 3. Jalankan binary (Setara shell_exec di PHP)
        # stderr=subprocess.STDOUT agar error juga tertangkap
        output_bytes = subprocess.check_output([CREDS_BINARY], stderr=subprocess.STDOUT)
        output_str = output_bytes.decode('utf-8').strip()
        
        # 4. Pisahkan baris (Explode)
        lines = output_str.split('\n')
        
        if len(lines) < 2:
            print("ERROR|Output secure.so tidak lengkap (Harus ada Token & Chat ID).")
            sys.exit(1)
            
        # Baris 1: Token, Baris 2: Chat ID (Sesuai PHP)
        token = lines[0].strip()
        chat_id = lines[1].strip()
        
        return token, chat_id

    except subprocess.CalledProcessError as e:
        print(f"ERROR|Gagal menjalankan secure.so. Code: {e.returncode}")
        sys.exit(1)
    except Exception as e:
        print(f"ERROR|Terjadi kesalahan sistem: {str(e)}")
        sys.exit(1)

# --- FUNGSI REQUEST (Bypass SSL) ---
def get_json(url):
    try:
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
        
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, context=ctx, timeout=15) as response:
            return json.load(response)
    except Exception as e:
        return None

# --- LOGIKA UTAMA ---
def main():
    # 1. AMBIL KREDENSIAL DARI BINARY
    TOKEN, CHAT_ID = get_credentials()

    # Validasi sederhana
    if not TOKEN or not CHAT_ID:
        print("ERROR|Kredensial kosong dari secure.so")
        sys.exit(1)

    # 2. AMBIL INFO CHANNEL
    api_chat = f"https://api.telegram.org/bot{TOKEN}/getChat?chat_id={CHAT_ID}"
    data = get_json(api_chat)

    if not data or not data.get('ok'):
        print("ERROR|Gagal Koneksi ke Telegram (Token/ID dari secure.so mungkin salah)")
        sys.exit(1)

    # 3. AMBIL PINNED MESSAGE
    result = data.get('result', {})
    pinned = result.get('pinned_message')

    if not pinned:
        print("ERROR|Tidak ada pesan yang di-PIN di channel ini")
        sys.exit(1)

    # 4. AMBIL CAPTION / TEXT
    caption = pinned.get('caption', '') 
    if not caption:
        caption = pinned.get('text', '') # Fallback jika hanya teks biasa

    if not caption:
        print("ERROR|Pesan PIN tidak memiliki Caption/Teks")
        sys.exit(1)

    # 5. CARI VERSI (Regex: Angka.Angka.Angka)
    match = re.search(r'([0-9]+\.[0-9]+(\.[0-9]+)?)', caption)
    if match:
        version = match.group(1)
    else:
        print("ERROR|Format Versi (misal: 1.0.5) tidak ditemukan di caption")
        sys.exit(1)

    # 6. ENCODE CHANGELOG
    changelog_bytes = caption.encode('utf-8')
    changelog_b64 = base64.b64encode(changelog_bytes).decode('utf-8')

    # 7. CEK LINK EKSTERNAL
    link_match = re.search(r'(https?://[^\s]+)', caption)
    
    if link_match:
        final_url = link_match.group(1)
        print(f"SUKSES|{version}|{final_url}|{changelog_b64}")
        sys.exit(0)

    # 8. JIKA TIDAK ADA LINK, CARI FILE DOKUMEN
    document = pinned.get('document')
    if not document:
        print("ERROR|Tidak ada Link Download & Tidak ada File terlampir")
        sys.exit(1)

    file_id = document.get('file_id')
    
    # Minta Path File ke Telegram
    api_file = f"https://api.telegram.org/bot{TOKEN}/getFile?file_id={file_id}"
    file_data = get_json(api_file)

    if not file_data or not file_data.get('ok'):
        print("ERROR|Gagal mengambil File Path dari Telegram")
        sys.exit(1)

    file_path = file_data['result'].get('file_path')
    final_url = f"https://api.telegram.org/file/bot{TOKEN}/{file_path}"

    # OUTPUT FINAL
    print(f"SUKSES|{version}|{final_url}|{changelog_b64}")

if __name__ == "__main__":
    main()