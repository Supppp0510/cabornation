<?php
session_start();
include("../php/config.php"); // Pastikan path ke config.php benar

$signupError = "";
$signupSuccess = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Ambil data POST
    // Kami menggunakan contactName untuk mengisi kolom 'name' di DB
    $contactName = trim($_POST["contact_name"]); 
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]); 
    $password = trim($_POST["password"]);
    $confirmPassword = trim($_POST["confirm_password"]);

    // Validasi input
    if (empty($contactName) || empty($email) || empty($phone) || empty($address) || empty($password) || empty($confirmPassword)) {
        $signupError = "Semua field harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signupError = "Format email tidak valid!";
    } elseif ($password !== $confirmPassword) {
        $signupError = "Konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $signupError = "Password minimal 6 karakter!";
    } else {
        // Cek apakah email sudah terdaftar di tabel event_organizers
        $stmt_check = $conn->prepare("SELECT eo_id FROM event_organizers WHERE contact_email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $signupError = "Email ini sudah terdaftar sebagai Event Organizer!";
        } else {
            // Hash password sebelum menyimpan
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // PERBAIKAN QUERY KRUSIAL: Hapus 'contact_name' dari query INSERT
            // Karena kolom 'contact_name' tidak ada di DB, kita hanya INSERT ke 5 kolom yang tersisa:
            // name, contact_email, password, phone, address
            $stmt_insert = $conn->prepare("INSERT INTO event_organizers (name, contact_email, password, phone, address) VALUES (?, ?, ?, ?, ?)");
            
            // Tipe data: s s s s s (5 parameter)
            // Kami menggunakan $contactName untuk mengisi kolom 'name'
            $stmt_insert->bind_param("sssss", $contactName, $email, $hashedPassword, $phone, $address);

            if ($stmt_insert->execute()) {
                $signupSuccess = "Pendaftaran berhasil! Silakan <a href='login_event.php'>Login</a>.";
                $_POST = array(); 
            } else {
                $signupError = "Terjadi kesalahan saat mendaftar. Silakan coba lagi. ERROR: " . $conn->error; 
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
    @$conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Event Organizer - CaborNation</title>

    <style>
        /* Gaya Total Mirip Login Screenshot (Hitam Kuning) */
        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
            background: #111;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Tinggikan tampilan karena form lebih panjang */
            height: 100%; 
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Kartu Sign Up */
        .signup-container {
            background: #1b1b1b; 
            padding: 40px; 
            width: 420px; /* Sedikit lebih lebar untuk form panjang */
            border-radius: 20px; 
            box-shadow: 0 5px 30px rgba(0,0,0,0.8); 
            text-align: center;
            position: relative; 
            z-index: 1;
            margin: 50px 0; /* Margin atas/bawah agar tidak menempel */
        }

        /* Judul */
        .signup-container h2 {
            margin-bottom: 5px; 
            font-size: 30px; 
            color: #ffcc00; 
            font-weight: 900; 
        }
        /* Subtitle */
        .signup-container p.subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px; 
            margin-bottom: 30px; 
        }

        /* Input Group */
        .input-group {
            margin-bottom: 15px; 
            text-align: left;
        }
        .input-group label {
            font-size: 16px; 
            margin-bottom: 5px; /* Jarak label dan input diperkecil */
            display: block;
            color: white; 
            font-weight: 500;
        }
        .input-group input {
            width: 100%; 
            padding: 14px 15px; 
            border-radius: 10px; 
            border: none; 
            outline: none;
            background: #fff; /* Input background putih solid */
            color: #333;
            font-size: 16px; 
            box-sizing: border-box; 
        }
        
        /* Error/Success Message */
        .error, .success {
            padding: 12px; 
            border-radius: 6px;
            margin-bottom: 20px; 
            font-size: 16px;
            text-align: center;
            color: white;
            font-weight: bold;
        }
        .error { background: #cc0000; }
        .success { background: #008000; }
        .success a { color: #ffcc00; text-decoration: underline; }

        /* TOMBOL UTAMA (DAFTAR) */
        .btn-main {
            width: 100%;
            padding: 18px; 
            border: none;
            background: #ffcc00; 
            color: #111; 
            font-size: 18px; 
            border-radius: 10px; 
            cursor: pointer;
            font-weight: 900; 
            transition: background 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 204, 0, 0.4); 
            margin-top: 25px; 
        }
        .btn-main:hover {
            background: #e6b800;
        }

        /* Tautan Login */
        .login-link {
            margin-top: 25px;
            font-size: 16px;
            color: white;
            display: block;
        }
        .login-link a {
            color: #ffcc00; /* Kuning untuk tautan login */
            text-decoration: none;
            font-weight: bold;
        }
        .login-link a:hover {
            text-decoration: underline;
        }


        /* Optional: Video Background dan Overlay */
        .bg-video {
            position: fixed;
            right: 0;
            bottom: 0;
            min-width: 100%;
            min-height: 100%;
            z-index: -2;
            object-fit: cover;
            filter: brightness(0.2); 
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4); 
            z-index: -1;
        }
    </style>

</head>
<body>

<!-- Optional: Video Background --><video autoplay muted loop playsinline class="bg-video">
    <source src="../../assets/video_bg.mp4" type="video/mp4"> 
Browser Anda tidak mendukung tag video.
</video>
<div class="overlay"></div>

<div class="signup-container">

    <h2>Daftar Event Organizer</h2>
    <p class="subtitle">Buat akun untuk mulai mengelola turnamen Anda.</p>

    <?php if ($signupError != ""): ?>
        <div class="error"><?php echo $signupError; ?></div>
    <?php endif; ?>
    <?php if ($signupSuccess != ""): ?>
        <div class="success"><?php echo $signupSuccess; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        
        <div class="input-group">
            <label>Nama Kontak / Penanggung Jawab</label>
            <input type="text" name="contact_name" required placeholder="Masukkan nama penanggung jawab" value="<?php echo htmlspecialchars($_POST['contact_name'] ?? ''); ?>">
        </div>

        <div class="input-group">
            <label>Email Kontak</label>
            <input type="email" name="email" required placeholder="Masukkan email kontak" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        
        <div class="input-group">
            <label>Nomor Telepon</label>
            <input type="tel" name="phone" required placeholder="Contoh: 08123456789" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>

        <div class="input-group">
            <label>Alamat Lengkap</label>
            <input type="text" name="address" required placeholder="Masukkan alamat lengkap" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
        </div>

        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Buat password">
        </div>
        
        <div class="input-group">
            <label>Konfirmasi Password</label>
            <input type="password" name="confirm_password" required placeholder="Konfirmasi password">
        </div>

        <button type="submit" class="btn-main">Daftar</button>
    </form>

    <p class="login-link">Sudah punya akun? <a href="login_event.php">Login</a></p>
</div>

</body>
</html>