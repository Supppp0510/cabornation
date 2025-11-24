<?php
session_start();
if (!isset($_SESSION['user_name'])) {
    header("Location: ../html/login.html");
    exit();
}
$user = $_SESSION['user_name'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | CaborNation</title>
  <link rel="stylesheet" href="../css/landingpagedash.css">
</head>
<body>
  

  <!-- 🧭 SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">CaborNation</div>
    <ul class="menu">
      <li class="active"><a href="#">Beranda</a></li>
      <li><a href="../php/timsaya.php">Tim Saya</a></li>
      <li><a href="#">Turnamen</a></li>
      <li><a href="../php/editprofil.php">Profil</a></li>
      <li><a href="../php/logout.php">Keluar</a></li>
    </ul>
  </aside>

  <!-- 💻 MAIN CONTENT -->
  <main class="content">
    
    <!-- 🔝 TOPBAR -->
    <div class="topbar">
      <h2>Selamat Datang, <span><?php echo htmlspecialchars($user); ?></span> !</h2>
      <img src="../assets/user.png" alt="User Avatar" class="avatar">
    </div>

    <!-- 📊 STATS -->
    <section class="stats">
      <div class="stat-card">
        <h3>12</h3>
        <p>Tim Terdaftar</p>
      </div>
      <div class="stat-card">
        <h3>5</h3>
        <p>Turnamen Aktif</p>
      </div>
      <div class="stat-card">
        <h3>3</h3>
        <p>Prestasi Tim</p>
      </div>
      <div class="stat-card">
        <h3>89%</h3>
        <p>Rata-rata Kemenangan</p>
      </div>
    </section>

    <!-- 📰 NEWS SECTION -->
    <section class="news-section">
      <h2>Berita & Sorotan</h2>
      <div class="news-grid">
        <div class="news-card">
          <img src="../assets/bts vs nanzaby.jpg" alt="Turnamen Basket">
          <div class="news-info">
            <h3>Bintang Timur Surabaya vs Nanzaby FC</h3>
            <p>Bintang Timur Surabaya berhasil mengalahkan Nanzaby FC dalam ajang Pro Futsal League dengan skor 5–4.</p>
          </div>
        </div>

        <div class="news-card">
          <img src="../assets/futsalll.jpeg" alt="Tim Futsal Berprestasi">
          <div class="news-info">
            <h3>Piala Presiden U‑12 & U‑15 di Surabaya</h3>
            <p>Kota Surabaya terpilih menjadi tuan rumah Piala Presiden untuk kategori U-12 dan U-15 yang akan digelar mulai akhir September 2025.</p>
          </div>
        </div>

        <div class="news-card">
          <img src="../assets/unes-scaled.jpeg" alt="Voli">
          <div class="news-info">
            <h3>POMPROV III Jawa Timur 2025</h3>
            <p>Di ajang POMPROV III Jawa Timur 2025, tim voli putra UNESA berhasil menjuarai dengan mengalahkan lawan dalam pertandingan keras yang berakhir 3-2.</p>
          </div>
        </div>
      </div>
    </section>

  </main>

</body>
</html>
