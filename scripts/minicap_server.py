import socket
import http.server
import struct
import subprocess
import time
import threading
from socketserver import ThreadingMixIn

# --- KONFIGURASI ---
PORT = 8181
MINICAP_DIR = "/data/adb/minicap"
SOCKET_NAME = "minicap_socket" 

# Resolusi
try:
    output = subprocess.check_output(["su", "-c", "wm size"]).decode()
    parts = output.split(":")[-1].strip().split("x")
    REAL_W, REAL_H = int(parts[0]), int(parts[1])
except:
    REAL_W, REAL_H = 1080, 2400

VIRT_W = 360 
VIRT_H = int((VIRT_W / REAL_W) * REAL_H)
CMD = f"LD_LIBRARY_PATH={MINICAP_DIR} {MINICAP_DIR}/minicap -P {REAL_W}x{REAL_H}@{VIRT_W}x{VIRT_H}/0"

current_frame = None
frame_condition = threading.Condition()

def free_port(port):
    subprocess.run(f"lsof -t -i:{port} | xargs kill -9", shell=True, stderr=subprocess.DEVNULL)
    subprocess.run(f"fuser -k {port}/tcp", shell=True, stderr=subprocess.DEVNULL)

class MinicapReader(threading.Thread):
    def run(self):
        global current_frame
        while True:
            try:
                s = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
                s.connect(f"\0{SOCKET_NAME}")
                s.recv(24) 
                while True:
                    len_data = s.recv(4)
                    if not len_data: break
                    frame_size = struct.unpack("<I", len_data)[0]
                    frame_data = b""
                    while len(frame_data) < frame_size:
                        packet = s.recv(frame_size - len(frame_data))
                        if not packet: break
                        frame_data += packet
                    with frame_condition:
                        current_frame = frame_data
                        frame_condition.notify_all()
            except:
                time.sleep(1)
            finally:
                try: s.close()
                except: pass

class ThreadedHTTPServer(ThreadingMixIn, http.server.HTTPServer):
    allow_reuse_address = True
    daemon_threads = True

class MJPEGHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        # 1. FIX: Hanya layani path yang benar, tolak favicon.ico
        if self.path == '/favicon.ico':
            self.send_error(404)
            return

        # 2. Mode Stream (MJPEG)
        if self.path.startswith('/stream.mjpeg'):
            self.send_response(200)
            self.send_header('Content-type', 'multipart/x-mixed-replace; boundary=frame')
            self.send_header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            self.send_header('Pragma', 'no-cache')
            self.end_headers()
            try:
                while True:
                    with frame_condition:
                        frame_condition.wait()
                        frame = current_frame
                    if frame:
                        self.wfile.write(b'--frame\r\n')
                        self.send_header('Content-Type', 'image/jpeg')
                        self.send_header('Content-Length', len(frame))
                        self.end_headers()
                        self.wfile.write(frame)
                        self.wfile.write(b'\r\n')
            except: pass
            return

        # 3. Mode Snapshot (Satu Gambar) - Fallback untuk HP
        if self.path.startswith('/snapshot'):
            with frame_condition:
                frame = current_frame
            if frame:
                self.send_response(200)
                self.send_header('Content-type', 'image/jpeg')
                self.send_header('Cache-Control', 'no-store, no-cache, must-revalidate')
                self.end_headers()
                self.wfile.write(frame)
            else:
                self.send_error(503)
            return
            
        self.send_error(404)

if __name__ == '__main__':
    free_port(PORT)
    subprocess.run(["su", "-c", "pkill -x minicap"], stderr=subprocess.DEVNULL)
    subprocess.Popen(f"su -c '{CMD} -n {SOCKET_NAME}'", shell=True)
    time.sleep(2)
    
    reader = MinicapReader()
    reader.daemon = True
    reader.start()

    try:
        server = ThreadedHTTPServer(('0.0.0.0', PORT), MJPEGHandler)
        server.serve_forever()
    except KeyboardInterrupt:
        subprocess.run(["su", "-c", "pkill -x minicap"])