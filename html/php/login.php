<?php
session_start();
include "config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Ambil data user berdasarkan email
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Karena password di database kamu masih plain text (bukan hash)
        if ($password === $row['password']) {
            // Simpan session user
            $_SESSION['user_name'] = $row['nama'];
            $_SESSION['user_email'] = $row['email'];

            // Arahkan ke dashboard
            header("Location: ../php/dashboard.php");
            exit();
        } else {
            echo "<script>alert('Password salah!'); window.location='../html/login.html';</script>";
        }
    } else {
        echo "<script>alert('Akun tidak ditemukan!'); window.location='../html/login.html';</script>";
    }
}
?>
