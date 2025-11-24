<?php
session_start();
ob_start();
require_once "../php/config.php";

// HARUS SAMA DENGAN dashboard_event.php
if (!isset($_SESSION["event_id"])) {
    echo "Unauthorized (session event_id tidak ada)";
    exit;
}

$eo_id = $_SESSION["event_id"];

// Pastikan form dikirim via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Invalid Request Method";
    exit;
}

// Ambil input
$title              = $_POST['title'] ?? '';
$sport              = $_POST['sport'] ?? '';
$location           = $_POST['location'] ?? '';
$date_start         = $_POST['date_start'] ?? '';
$date_end           = $_POST['date_end'] ?? '';
$registration_fee   = $_POST['registration_fee'] ?? 0;
$prize_pool         = $_POST['prize_pool'] ?? 0;
$description        = $_POST['description'] ?? '';
$poster             = $_FILES['poster'] ?? null;

// Cek file poster
if (!$poster || $poster['error'] !== 0) {
    // Tidak mengembalikan user, hanya pesan error untuk debug
    echo "Poster upload error (Code: {$poster['error']})";
    exit;
}

// Upload poster
$targetDir = "../uploads/";
if (!is_dir($targetDir)) mkdir($targetDir);

$filename = "poster_" . time() . "_" . basename($poster["name"]);
$targetFile = $targetDir . $filename;

if (!move_uploaded_file($poster["tmp_name"], $targetFile)) {
    echo "Failed to upload poster.";
    exit;
}

// 🔑 KOREKSI QUERY KRITIS: Ganti created_by dengan eo_id
$query = "
    INSERT INTO tournaments 
    (title, sport, location, date_start, date_end, registration_fee, prize_pool, description, poster_url, eo_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($query);

// DEBUG jika prepare gagal
if (!$stmt) {
    echo "Prepare Failed: " . $conn->error;
    // Tambahkan kode untuk menghapus file yang sudah diupload jika prepare gagal
    unlink($targetFile);
    exit;
}

// 🔑 KOREKSI BIND PARAM: Sesuaikan dengan 10 field. Asumsi fee & prize adalah INT.
$bind_string = "sssssiiisi"; // 5s (title-date_end), 2i (fee, prize), 2s (desc, poster_url), 1i (eo_id)

$stmt->bind_param(
    $bind_string,
    $title,
    $sport,
    $location,
    $date_start,
    $date_end,
    $registration_fee,
    $prize_pool,
    $description,
    $filename,
    $eo_id
);

if ($stmt->execute()) {
    // Tambahkan status 'Pending' saat insert jika belum ada kolom status di query
    // Redirect ke dashboard_event.php
    echo "<script>alert('Tournament created successfully!'); window.location='dashboard_event.php';</script>";
} else {
    echo "Execute Failed: " . $stmt->error;
    // Hapus file yang sudah diupload jika execute gagal
    unlink($targetFile);
}
?>