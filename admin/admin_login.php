<?php
session_start();
ob_start();
include("../php/config.php");

$error = "";

// Jika admin sudah login → ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email_or_username = trim($_POST['email_or_username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email_or_username) || empty($password)) {
        $error = "Email/Username dan Password wajib diisi.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email_or_username, $email_or_username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if ($admin) {
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];

                header("Location: admin_dashboard.php");
                exit;
            } else {
                $error = "Password salah.";
            }
        } else {
            $error = "Admin tidak ditemukan.";
        }
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Admin Login | Cabornation</title>
<style>
    body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        color: #fff;
        height: 100vh; /* Tambahkan: Set tinggi penuh viewport */
        display: flex; /* Tambahkan: Aktifkan Flexbox */
        justify-content: center; /* Tambahkan: Tengah horizontal */
        align-items: center; /* Tambahkan: Tengah vertikal */
        overflow: hidden;
    }

    /* Video Background */
    .bg-video-login {
        position: fixed;
        background-color : black;
        top: 0;
        left: 0;
        width: 100%;
        opacity : 0.80;
        height: 100%;
        object-fit: cover;
        z-index: -2;
    }

    /* Overlay */
    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.90);
        z-index: -1;
    }

    /* Container */
    .login-container {
        width: 400px;
        /* Hapus margin: 80px auto; dan ganti dengan centering Flexbox */
        background: rgba(0, 0, 0, 0.75);
        padding: 35px;
        border-radius: 20px;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.7);
        text-align: center;
        z-index: 10; /* Tambahkan: Pastikan di atas overlay */
    }

    h1 {
        color: #ff0000ff;
        margin-bottom: 5px;
        font-size: 26px;
        font-weight: 700;
    }

    .subtitle {
        margin-bottom: 30px;
        font-size: 14px;
        color: #ccc;
    }

    .form-group {
        margin-bottom: 18px;
        text-align: left;
    }

    .form-group label {
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 5px;
        display: block;
    }

    .form-group input {
        width: 94%;
        padding: 12px;
        border-radius: 8px;
        border: none;
        background: #222;
        color: #fff;
    }

    .btn-main {
        width: 100%;
        padding: 14px;
        background: #ff0000ff;
        border: none;
        border-radius: 10px;
        color: #000;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        margin-top: 10px;
    }

    .btn-main:hover {
        background: #e60000ff;
    }

    .error {
        background: #f44336;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .register-text {
        margin-top: 15px;
        font-size: 14px;
    }

    .register-text a {
        color: #ff0000ff;
        font-weight: 600;
        text-decoration: none;
    }
</style>
</head>

<body>

    <!-- Video Background -->
    <video autoplay muted loop playsinline class="bg-video-login">
        <source src="../assets/JOIN THE NEW ERA  2023 VCT LOCK__IN  Cinematic Trailer - VALORANT (1080p, h264).mp4" type="video/mp4">
    </video>

    <div class="overlay"></div>

    <div class="login-container">

        <h1>Selamat Datang Kembali!</h1>
        <p class="subtitle">Masuk sebagai Admin untuk mengelola sistem</p>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email atau Username Admin</label>
                <input type="text" name="email_or_username" required placeholder="Masukkan email/username admin">
            </div>

            <div class="form-group">
                <label>Kata Sandi</label>
                <input type="password" name="password" required placeholder="Masukkan password admin">
            </div>

            <button type="submit" class="btn-main">Masuk Admin</button>
        </form>

        <div class="register-text">
            <p>Belum punya akun admin? <a href="admin_register.php">Daftar Sekarang</a></p>
        </div>

    </div>

</body>

</html>
