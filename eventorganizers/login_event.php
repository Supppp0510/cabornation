<?php
// PASTIKAN ob_start() ADA DI BARIS PERTAMA JIKA DIPERLUKAN
// ob_start(); 

// --- KONFIGURASI INI_SET HARUS DI ATAS session_start() ---
ini_set('session.cookie_domain', '127.0.0.1');
ini_set('session.cookie_path', '/');
// ----------------------------------------------------

session_start(); 
include("../php/config.php"); // Pastikan path ke config.php benar

// --- INISIALISASI VARIABEL ---
$error_message = ""; 
$eoId = 0; 
$eoName = "Guest"; 

// LOGIC 1: Jika sudah login, alihkan ke dashboard.
if (isset($_SESSION["event_id"])) {
    $eoId = $_SESSION["event_id"];
    $eoName = $_SESSION["event_name"];

    // Redirect ke dashboard via IP
    header("Location: http://127.0.0.1/cabornation/eventorganizers/dashboard_event.php");
    exit();
}

// LOGIC 2: PROSES LOGIN POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = trim($_POST['password']); 

    // GANTI DENGAN LOGIC QUERY DATABASE ANDA YANG SESUAI (Gunakan contact_email untuk login)
    $query = "SELECT eo_id, name, password FROM event_organizers WHERE contact_email = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows == 1) {
        $user_data = $result->fetch_assoc();
        
        // Asumsi password Anda di-hash menggunakan password_hash() saat daftar
        if (password_verify($password, $user_data['password'])) {
            
            // LOGIN BERHASIL
            $eoId = $user_data['eo_id'];
            $eoName = $user_data['name']; // Asumsi kolom nama EO di DB adalah 'name'
            
            $_SESSION["event_id"] = $eoId;
            $_SESSION["event_name"] = $eoName;

            // REDIRECT BYPASS KUAT KE IP
            header("Location: http://127.0.0.1/cabornation/eventorganizers/dashboard_event.php?id_check={$eoId}&token=bypass");
            exit();

        } else {
            $error_message = "Password salah.";
        }
    } else {
        $error_message = "Email tidak terdaftar.";
    }
    $stmt->close();
}

// Pastikan koneksi ($conn) tersedia
if (!isset($conn) || @$conn->connect_error) { 
    // ...
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Event Organizer - CaborNation</title>

    <style>
        /* CSS DARI KODE SIGNUP ANDA, DIMODIFIKASI UNTUK LOGIN */
        body {
            margin: 0; padding: 0; font-family: sans-serif;
            background: #111; color: white;
            display: flex; justify-content: center; align-items: center;
            height: 100%; min-height: 100vh; overflow-x: hidden;
        }
        .login-container { /* Mengganti signup-container */
            background: #1b1b1b; padding: 40px; width: 380px; 
            border-radius: 20px; box-shadow: 0 5px 30px rgba(0,0,0,0.8); 
            text-align: center; position: relative; z-index: 1;
        }
        .login-container h2 {
            margin-bottom: 5px; font-size: 30px; color: #ffcc00; 
            font-weight: 900; 
        }
        .login-container p.subtitle {
            color: rgba(255, 255, 255, 0.9); font-size: 16px; 
            margin-bottom: 30px; 
        }
        .input-group {
            margin-bottom: 15px; text-align: left;
        }
        .input-group label {
            font-size: 16px; margin-bottom: 5px; display: block;
            color: white; font-weight: 500;
        }
        .input-group input {
            width: 100%; padding: 14px 15px; border-radius: 10px; 
            border: none; outline: none; background: #fff; 
            color: #333; font-size: 16px; box-sizing: border-box; 
        }
        
        /* Error/Success Message */
        .error { background: #cc0000; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 16px; text-align: center; color: white; font-weight: bold; }

        /* TOMBOL UTAMA (MASUK) */
        .btn-main {
            width: 100%; padding: 18px; border: none;
            background: #ffcc00; color: #111; font-size: 18px; 
            border-radius: 10px; cursor: pointer; font-weight: 900; 
            transition: background 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 204, 0, 0.4); 
            margin-top: 25px; 
        }
        .btn-main:hover { background: #e6b800; }

        /* Tautan Daftar */
        .register-link {
            margin-top: 25px; font-size: 16px; color: white; display: block;
        }
        .register-link a {
            color: #ffcc00; text-decoration: none; font-weight: bold;
        }
        /* Optional: Video Background */
        .bg-video {
            position: fixed; right: 0; bottom: 0; min-width: 100%;
            min-height: 100%; z-index: -2; object-fit: cover;
            filter: brightness(0.2); 
        }
        .overlay {
            position: fixed; top: 0; left: 0; width: 100%;
            height: 100%; background: rgba(0, 0, 0, 0.4); z-index: -1;
        }
    </style>
</head>
<body>

<!-- Optional: Video Background (Anda perlu menaruh file video di path yang benar) -->
<video autoplay muted loop playsinline class="bg-video">
    <source src="../../assets/video_bg.mp4" type="video/mp4"> 
    Browser Anda tidak mendukung tag video.
</video>
<div class="overlay"></div>

<div class="login-container">

    <h2>SELAMAT DATANG KEMBALI!</h2>
    <p class="subtitle">Masuk untuk mengelola turnamen dan timmu</p>

    <?php if ($error_message != ""): ?>
        <div class="error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        
        <div class="input-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="Masukkan email Anda" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        
        <div class="input-group">
            <label>Kata Sandi</label>
            <input type="password" name="password" required placeholder="Masukkan kata sandi Anda">
        </div>

        <button type="submit" class="btn-main">Masuk</button>
    </form>

    <p class="register-link">Belum punya akun? <a href="signup_event.php">Daftar Sekarang</a></p>
</div>

</body>
</html>