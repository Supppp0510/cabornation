<?php
session_start();
include("config.php");

// ✅ Cek login - gunakan email bukan name
if (!isset($_SESSION['user_email'])) {
    header("Location: ../html/login.html");
    exit();
}

$email = $_SESSION['user_email'];

// Ambil data user dari database
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $user_id = $data['id'];
    $user_name = $data['nama'];
    
    // Pastikan field tidak null
    $data['overview'] = $data['overview'] ?? '';
    $data['team_name'] = $data['team_name'] ?? '';
    $data['sport'] = $data['sport'] ?? '';
} else {
    session_destroy();
    header("Location: ../html/login.html");
    exit();
}

// ✅ Ambil foto profil dari database
$foto_profil = '../assets/user.png'; // default
if ($user_id !== null) {
    $stmt_foto = $conn->prepare("SELECT photo_path FROM user_photos WHERE user_id = ? LIMIT 1");
    $stmt_foto->bind_param("i", $user_id);
    $stmt_foto->execute();
    $result_foto = $stmt_foto->get_result();
    
    if ($result_foto && $result_foto->num_rows > 0) {
        $foto_row = $result_foto->fetch_assoc();
        // Pastikan path benar
        if (strpos($foto_row['photo_path'], '../') === 0) {       
            $foto_profil = $foto_row['photo_path'];
        } else {
            $foto_profil = "../" . $foto_row['photo_path'];
        }
    }
}

// Hitung statistik (opsional - bisa disesuaikan dengan data real)
// Query untuk Tim Terdaftar
$stmt_teams = $conn->prepare("SELECT COUNT(*) as total FROM teams WHERE id = ?");
if (!$stmt_teams) {
    die("❌ Query prepare gagal: " . $conn->error);
}
$stmt_teams->bind_param("i", $user_id);
$stmt_teams->execute();
$result_teams = $stmt_teams->get_result();
$tim_terdaftar = $result_teams->fetch_assoc()['total'] ?? 0;

// Turnamen Aktif dari data user
$turnamen_aktif = $data['active_tournaments'] ?? 0;

// Prestasi & Win Rate (placeholder - bisa disesuaikan)
$prestasi = 3; // hardcoded untuk sementara
$win_rate = 89; // hardcoded untuk sementara





?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | CaborNation</title>
  <link rel="stylesheet" href="../css/landingpagedash.css">
  <style>
    /* Style untuk foto profil bulat */
    .avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      cursor: pointer;
      transition: transform 0.3s ease;
    }
    .avatar:hover {
      transform: scale(1.1);
    }
  </style>
</head>
<body>
  
  <!-- 🧭 SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">CaborNation</div>
    <ul class="menu">
      <li class="active"><a href="dashboard.php">Beranda</a></li>
      <li><a href="timsaya.php">Tim Saya</a></li>
      <li><a href="turnamen.php">Turnamen</a></li>
      <li><a href="editprofil.php">Profil</a></li>
      <li><a href="#" onclick="confirmLogout(event)">Keluar</a></li>
    </ul>
  </aside>

  <!-- 💻 MAIN CONTENT -->
  <main class="content">
    
    <!-- 🔝 TOPBAR -->
    <div class="topbar">
      <h2>Selamat Datang, <span><?php echo htmlspecialchars($user_name); ?></span>!</h2>
      <a href="editprofil.php" title="Edit Profil">
        <img src="<?php echo htmlspecialchars($foto_profil); ?>" 
             alt="Foto Profil <?php echo htmlspecialchars($user_name); ?>" 
             class="avatar"
             onerror="this.src='../assets/profil.png'">
      </a>
    </div>

    <!-- 📊 STATS -->
    <section class="stats">
      <div class="stat-card">
        <h3><?php echo $tim_terdaftar; ?></h3>
        <p>Tim Terdaftar</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $turnamen_aktif; ?></h3>
        <p>Turnamen Aktif</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $prestasi; ?></h3>
        <p>Prestasi Tim</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $win_rate; ?>%</h3>
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
<script>
  function confirmLogout(e) {
    e.preventDefault();

    if (confirm("Yakin ingin keluar?")) {
        window.location.href = "logout.php";
    }
}
</script>
</body>
</html>