<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $nama = $_POST['nama'];
  $email = $_POST['email'];
  $password = $_POST['password'];

  // cek kalau nama/email/password kosong
  if (empty($nama) || empty($email) || empty($password)) {
    echo "<script>alert('Data tidak boleh kosong!'); window.history.back();</script>";
    exit;
  }

  $sql = "INSERT INTO users (nama, email, password) VALUES ('$nama', '$email', '$password')";

  if (mysqli_query($conn, $sql)) {
    echo "<script>alert('Pendaftaran berhasil! Silakan login.'); window.location='../html/login.html';</script>";
  } else {
    echo "<script>alert('Registrasi gagal: " . mysqli_error($conn) . "'); window.history.back();</script>";
  }
}
?>
