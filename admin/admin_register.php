<?php
session_start();
ob_start();
include("../php/config.php");

$message = '';
$error = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Semua field wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi Kata Sandi tidak cocok.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM admins WHERE email = ? OR username = ?");
        $stmt_check->bind_param("ss", $email, $username);
        $stmt_check->execute();
        $result = $stmt_check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email atau Username sudah terdaftar.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Super Admin';

            $stmt_insert = $conn->prepare("INSERT INTO admins (username, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt_insert === false) {
                $error = "Gagal mempersiapkan query: " . $conn->error;
            } else {
                $stmt_insert->bind_param("ssss", $username, $email, $hashed_password, $role);

                if ($stmt_insert->execute()) {
                    session_unset();
                    session_destroy();

                    session_start();
                    $_SESSION['registration_success'] = "Registrasi Admin berhasil! Silakan login.";

                    header("Location: admin_login.php");
                    exit();
                } else {
                    $error = "Registrasi gagal: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
        }
        $stmt_check->close();
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Akun Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            overflow: hidden;
        }

        /* 🎬 Video Background */
        .bg-video-login {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.80; /* Lebih gelap */
            z-index: -2;
        }

        /* 🌫 Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85); /* Gelap */
            z-index: -1;
        }

        /* 🧱 Container Card */
        .login-container {
            width: 420px;
            margin: 70px auto;
            padding: 35px;
            background: rgba(0, 0, 0, 0.75);
            border-radius: 18px;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.7);
            text-align: center;
        }

        h2 {
            color: #ff3c00ff;
            margin-bottom: 5px;
            font-weight: 700;
            font-size: 26px;
        }

        .subtitle {
            color: #ccc;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
            font-size: 14px;
        }

        .form-group input {
            width: 94%;
            padding: 12px;
            background: #222;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
        }

        .form-group input:focus {
            outline: 2px solid #ff3300ff;
            background: #2d2d2d;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #ff2600ff;
            border: none;
            border-radius: 10px;
            color: #000;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #e01e00ff;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 8px;
        }

        .error { background: #f44336; }
        .success { background: #4CAF50; }

        .login-link {
            margin-top: 18px;
            font-size: 14px;
        }

        .login-link a {
            color: #ff2a00ff;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            color: #fff;
        }
    </style>
</head>

<body>

    <!-- Video BG -->
    <video autoplay muted loop playsinline class="bg-video-login">
        <source src="../assets/JOIN THE NEW ERA  2023 VCT LOCK__IN  Cinematic Trailer - VALORANT (1080p, h264).mp4" type="video/mp4">
    </video>

    <div class="overlay"></div>

    <div class="login-container">
        <h2>Buat Akun Admin CaborNation</h2>
        <p class="subtitle">Daftarkan admin baru untuk mengelola sistem.</p>

        <?php if ($message): ?><div class="message success"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= $error ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($username) ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">
            </div>

            <div class="form-group">
                <label>Kata Sandi</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Konfirmasi Kata Sandi</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button class="btn-submit" type="submit">Daftar Sekarang</button>
        </form>

        <div class="login-link">
            Sudah punya akun? <a href="admin_login.php">Masuk di sini</a>
        </div>
    </div>

</body>
</html>
