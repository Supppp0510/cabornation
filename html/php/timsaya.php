<?php
session_start();
include 'config.php';

// Simulasi user yang login (ganti sesuai sistem loginmu nanti)
$user_email = $_SESSION['user_email'] ?? 'test@example.com';

// Jika form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $team_name = $_POST['team_name'];
    $sport = $_POST['sport'];
    $team_desc = $_POST['team_desc'] ?? '';

    // Query insert pakai kolom yang benar
    $stmt = $conn->prepare("INSERT INTO teams (user_email, team_name, sport, team_desc) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        die("Query error: " . $conn->error);
    }

    $stmt->bind_param("ssss", $user_email, $team_name, $sport, $team_desc);

    if ($stmt->execute()) {
        echo "<script>alert('Tim berhasil dibuat!'); window.location.href='timsaya.php';</script>";
    } else {
        echo "Gagal menambahkan tim: " . $stmt->error;
    }

    $stmt->close();
}

// Ambil semua tim milik user
$sql = "SELECT * FROM teams WHERE user_email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tim Saya</title>
    <link rel="stylesheet" href="../css/timsaya.css">
</head>
<body>

<aside class="sidebar">
  <div class="logo">CaborNation</div>
  <ul class="menu">
    <li class="active"><a href="../php/dashboard.php">Beranda</a></li>
    <li><a href="../php/timsaya.php">Tim Saya</a></li>
    <li><a href="#">Turnamen</a></li>
    <li><a href="../php/editprofil.php">Profil</a></li>
    <li><a href="../php/logout.php">Keluar</a></li>
  </ul>
</aside>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tim Saya | CaborNation</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">CaborNation</div>
    <ul class="menu">
      <li><a href="#">Beranda</a></li>
      <li class="active"><a href="#">Tim Saya</a></li>
      <li><a href="#">Turnamen</a></li>
      <li><a href="#">Profil</a></li>
      <li><a href="#">Keluar</a></li>
    </ul>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main">
    <section class="card">
      <h2 class="section-title">Buat Tim & Tambah Pemain</h2>
      <p class="section-subtitle">Tambahkan pemain sesuai batas per cabang olahraga.</p>

      <form class="styled-form">
        <div class="form-group">
          <label>Nama Tim</label>
          <input type="text" placeholder="Masukkan nama tim">
        </div>

        <div class="form-group">
          <label>Cabang Olahraga</label>
          <select>
            <option>Futsal</option>
            <option>Basket</option>
            <option>Badminton</option>
            <option>Volly</option>
          </select>
        </div>

        <div class="form-group">
          <label>Asal Instansi</label>
          <input type="text" placeholder="Masukkan asal sekolah/universitas">
        </div>

        <div class="form-group">
          <label>Jumlah Pemain</label>
          <input type="number" placeholder="Masukkan jumlah pemain">
        </div>

        <button type="submit" class="btn-submit">Simpan Tim</button>
      </form>
    </section>
  </main>

</body>
</html>
