<?php
// Pastikan path ke config.php benar.
include("../php/config.php"); 

// Pastikan koneksi tersedia
if (!isset($conn) || $conn->connect_error) {
    die("Koneksi database gagal.");
}

// Ambil ID dari URL
// Pastikan ID diset untuk mencegah error "Undefined index: id"
if (!isset($_GET["id"])) {
    die("ID pengguna tidak ditemukan.");
}
$id = $_GET["id"];

// Mengambil data user
$result = $conn->query("SELECT * FROM users WHERE id = $id");

// Pindahkan hasil ke variabel $user
if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    // Jika user tidak ditemukan
    die("User tidak ditemukan");
}


// Cek keberadaan user
if (!$user) { die("User tidak ditemukan"); } 

// --- Proses Update Data ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Ambil input dari form
    $nama_baru = $_POST["username"];
    // MENGAMBIL NILAI STATUS BARU
    $status_baru = $_POST["status"]; 

    // Gunakan Prepared Statement untuk UPDATE (lebih aman)
    // QUERY MENCANGKUP KOLOM STATUS dan NAMA (asumsi nama kolom adalah 'nama')
    $stmt = $conn->prepare("UPDATE users SET nama = ?, status = ? WHERE id = ?"); 
    
    // PERBAIKAN: Menggunakan $status_baru, bukan $status
    // Sesuaikan tipe data: ss (string, string) i (integer)
    $stmt->bind_param("ssi", $nama_baru, $status_baru, $id); 

    if ($stmt->execute()) {
        // Berhasil update, redirect kembali ke dashboard
        header("Location: admin_dashboard.php?updated=1");
        exit();
    } else {
        // Tampilkan error SQL yang sebenarnya
        die("Error saat mengupdate data: " . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User: <?php echo $user['nama'] ?? $user['username'] ?? 'User'; ?></title>
    <style>
        /* Gaya dasar agar form terlihat serasi dengan dashboard admin Anda */
        body { background:#000; color:white; font-family: Arial, sans-serif; padding: 20px; }
        .form-container { background:#1a0000; padding:25px; width:400px; border-radius:12px; border:1px solid #ff1a1a; margin: 50px auto; }
        label { display: block; margin-top: 15px; margin-bottom: 5px; color: #ff4d4d; }
        input[type="text"], select { width:100%; padding:10px; margin-top:8px; background:#000; color:white; border:1px solid #ff1a1a; border-radius:6px; box-sizing: border-box; margin-bottom: 15px; }
        button { background:#ff1a1a; color:white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px; }
        button:hover { background:#ff6666; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Edit Pengguna: <?php echo htmlspecialchars($user['nama'] ?? $user['username'] ?? 'Tidak Diketahui'); ?></h2>

    <form method="POST">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['nama'] ?? $user['username'] ?? ''); ?>" required>

        <label for="status">Status:</label>
        <select id="status" name="status">
            
            <?php $currentStatus = $user['status'] ?? ''; // Ambil status saat ini, default kosong jika tidak ada ?>
            
            <option value="active" <?php if($currentStatus == "active") echo "selected"; ?>>Active</option>
            
            <option value="banned" <?php if($currentStatus == "banned") echo "selected"; ?>>Banned (Blokir)</option>
            
        </select>

        <button type="submit">Update User</button>
    </form>
</div>

</body>
</html>