<?php
// Simpan sebagai: logout.php
session_start();

// Hapus semua variabel sesi yang terdaftar
$_SESSION = array();

// Jika ingin menghapus cookie sesi juga
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan sesi
session_destroy();

// Redirect ke halaman login admin (sesuaikan nama file login Anda jika berbeda)
header("Location: admin_login.php");
exit();
?>