<?php
session_start();
include "config.php"; // Pastikan file ini mendefinisikan $conn

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Ambil data user berdasarkan email
    // PENTING: Gunakan prepared statement di production untuk mencegah SQL Injection.
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Warning: Hati-hati, menggunakan plain text password sangat tidak aman! 
        // Sebaiknya gunakan password_verify() dan password_hash() untuk keamanan.
        if ($password === $row['password']) {
            
            // --- LOGIKA UTAMA: CEK STATUS AKUN ---
            if (isset($row['status']) && $row['status'] === 'banned') {
                // Jika statusnya 'banned', tolak login
                echo "<script>alert('Akun Anda telah diblokir. Silakan hubungi administrator.'); window.location='../html/login.html';</script>";
                exit();
            }
            // --- AKHIR LOGIKA CEK STATUS ---

            // Jika status bukan 'banned' dan password benar, izinkan login
            $_SESSION['user_name'] = $row['nama'];
            $_SESSION['user_email'] = $row['email'];

            // Arahkan ke dashboard
            header("Location: ../php/dashboard.php");
            exit();
        } else {
            // Password salah
            echo "<script>alert('Password salah!'); window.location='../html/login.html';</script>";
        }
    } else {
        // Akun tidak ditemukan
        echo "<script>alert('Akun tidak ditemukan!'); window.location='../html/login.html';</script>";
    }
}
?>