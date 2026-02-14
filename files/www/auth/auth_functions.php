<?php
// PERBAIKAN 1: Cek dulu status session
// Ini mencegah error "Notice: Ignoring session_start()" jika session sudah aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if the user is logged in
function checkUserLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login.php'); // Redirect to login page
        exit;
    }
}

// Function to check if login is enabled
function isLoginEnabled() {
    // PERBAIKAN 2: Tambahkan tanda '/' sebelum nama file
    // KODE LAMA: $config_file = __DIR__ . 'config.json'; (Salah, hasilnya folderconfig.json)
    // KODE BARU:
    $config_file = __DIR__ . '/config.json'; 
    
    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
        return isset($config['LOGIN_ENABLED']) && $config['LOGIN_ENABLED'];
    }
    return false;
}

// Set a flag in session or query parameter if login is disabled
if (!isLoginEnabled()) {
    $_SESSION['login_disabled'] = true; 
}
?>
