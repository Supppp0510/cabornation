<?php
session_start();
include("../php/config.php");

// pastikan login
if (!isset($_SESSION['user_email'])) {
    header("Location: ../html/login.html");
    exit();
}
$user_email = $_SESSION['user_email'];

// validasi param team_id & sport
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : (isset($_POST['team_id']) ? intval($_POST['team_id']) : 0);
$sport = isset($_GET['sport']) ? $_GET['sport'] : (isset($_POST['sport']) ? $_POST['sport'] : '');

// mapping batas pemain (sama seperti tim_saya.php)
$olahraga_batas = [
    'Futsal'    => 14,
    'Basket'    => 12,
    'Voli'      => 12,
    'Badminton' => 5,
    'Atletik'   => 1,
    'Padel'     => 2,
    'Catur'     => 4,
    'Esport'    => 7,
];

// cek team ada dan milik user
if (!$team_id) {
    die("Team tidak diset.");
}
$stmt = $conn->prepare("SELECT id, team_name, sport FROM teams WHERE id = ? AND user_email = ?");
$stmt->bind_param("is", $team_id, $user_email);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();
if (!$team) {
    die("Team tidak ditemukan atau bukan milik Anda.");
}
$sport = $team['sport'];

// pastikan team_players table exists (safety)
$create_sql = "CREATE TABLE IF NOT EXISTS team_players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  nama_pemain VARCHAR(150) NOT NULL,
  posisi VARCHAR(100),
  nomor VARCHAR(50),
  foto VARCHAR(255) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($create_sql);

// cek jumlah pemain saat ini
$q = $conn->prepare("SELECT COUNT(*) AS cnt FROM team_players WHERE team_id = ?");
$q->bind_param("i", $team_id);
$q->execute();
$count_row = $q->get_result()->fetch_assoc();
$current_count = intval($count_row['cnt']);
$max_allowed = $olahraga_batas[$sport] ?? 0;

if ($current_count >= $max_allowed) {
    // sudah penuh, redirect balik
    $_SESSION['team_msg'] = "Batas pemain untuk {$sport} sudah penuh ({$max_allowed} pemain).";
    header("Location: tim_saya.php");
    exit();
}

// proses tambah pemain
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pemain = trim($_POST['nama_pemain'] ?? '');
    $posisi = trim($_POST['posisi'] ?? '');
    $nomor = trim($_POST['nomor'] ?? '');

    if ($nama_pemain === '') {
        $error = "Nama pemain wajib diisi.";
    } else {
        // upload foto jika ada
        $foto_path = '';
        if (!empty($_FILES['foto']['name'])) {
            $upload_dir = "../uploads/players/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $orig = basename($_FILES['foto']['name']);
            $safe = preg_replace('/[^A-Za-z0-9\._-]/', '', $orig);
            $new = uniqid("player_") . "_" . $safe;
            $target = $upload_dir . $new;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
                // simpan relative path agar <img> bisa load dari html folder
                $foto_path = $target;
            } else {
                $error = "Gagal mengupload foto pemain.";
            }
        }

        if (!isset($error)) {
            $ins = $conn->prepare("INSERT INTO team_players (team_id, nama_pemain, posisi, nomor, foto) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("issss", $team_id, $nama_pemain, $posisi, $nomor, $foto_path);
            if ($ins->execute()) {
                header("Location: tim_saya.php");
                exit();
            } else {
                $error = "Gagal menyimpan pemain: " . $conn->error;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Tambah Pemain - <?php echo htmlspecialchars($team['team_name']); ?></title>
<link rel="stylesheet" href="../css/style_tim.css">
</head>
<body>
  <!-- 🧭 SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">CaborNation</div>
    <ul class="menu">
      <li><a href="../php/dashboard.php">Beranda</a></li>
      <li class="active"><a href="tim_saya.php">Tim Saya</a></li>
      <li><a href="../php/turnamen.php">Turnamen</a></li>
      <li><a href="../php/editprofil.php">Profil</a></li>
      <li><a href="../php/logout.php">Keluar</a></li>
    </ul>
  </aside>


  <main class="main-content">
    <div class="container small">
      <h1>Tambah Pemain untuk: <?php echo htmlspecialchars($team['team_name']) ?> (<?php echo htmlspecialchars($sport); ?>)</h1>

      <?php if (!empty($error)): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <p>Jumlah pemain saat ini: <?php echo $current_count; ?> / <?php echo $max_allowed; ?></p>

      <form method="POST" enctype="multipart/form-data" class="form-create">
        <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
        <label>Nama Pemain</label>
        <input type="text" name="nama_pemain" required>

        <label>Posisi</label>
        <input type="text" name="posisi">

        <label>Nomor Punggung</label>
        <input type="text" name="nomor">

        <label>Foto (opsional)</label>
        <input type="file" name="foto" accept="image/*">

        <div style="margin-top:12px;">
          <button type="submit">Simpan Pemain</button>
          <a class="btn secondary" href="tim_saya.php">Batal</a>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
