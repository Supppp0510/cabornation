<?php
session_start();
require_once "../php/config.php";

header("Content-Type: application/json");

// Fungsi pembantu untuk mengirim respons JSON dan menghentikan script
function sendJsonResponse($data) {
    echo json_encode($data);
    exit;
}

// Pastikan EO login
if (!isset($_SESSION["event_id"])) {
    sendJsonResponse(["success" => false, "message" => "Unauthorized: Event Organizer tidak login."]);
}

if (!isset($_GET["tournament_id"])) {
    sendJsonResponse(["success" => false, "message" => "Missing tournament ID."]);
}

$tournament_id = intval($_GET["tournament_id"]);
$status_filter = $_GET['status'] ?? 'all';

// --- 1. Tentukan Kriteria Filter Status ---
$where_status = "";
$params = [$tournament_id];
$param_types = "i";

if ($status_filter !== 'all') {
    // Pastikan status yang diterima aman
    if (!in_array($status_filter, ['pending', 'accepted', 'rejected'])) {
        sendJsonResponse(["success" => false, "message" => "Filter status tidak valid."]);
    }
    // Perbaikan: Mengubah alias p.status menjadi r.status
    $where_status = " AND r.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// --- 2. Query ke tabel tournament_registrations ---
// ASUMSI: Anda sudah menambahkan kolom 'status' ke tabel 'tournament_registrations'
$sql = "
    SELECT 
        r.id AS registration_id,        /* ID Pendaftaran */
        r.team_name,                    /* Nama Tim */
        r.status,                       /* **WAJIB ADA** di tabel */
        r.created_at AS reg_date,       /* Tanggal Pendaftaran */
        r.registered_by,                /* Email/ID yang mendaftar */
        r.contact_phone
    FROM tournament_registrations r
    WHERE r.tournament_id = ? 
    {$where_status}
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);

// Bind parameter secara dinamis
// PENTING: Gunakan '&' untuk variabel yang akan di-bind_param jika menggunakan PHP versi lama.
$stmt->bind_param($param_types, ...$params);

if (!$stmt->execute()) {
    // Pesan error jika kolom 'status' belum ada atau masalah SQL lainnya.
    $error_msg = "Gagal menjalankan query. Pastikan kolom 'status' (ENUM/VARCHAR) sudah ada di tabel tournament_registrations.";
    if ($conn->error) {
        $error_msg .= " Detail SQL: " . $conn->error;
    }
    sendJsonResponse(["success" => false, "message" => $error_msg]);
}

$result = $stmt->get_result();
$registrations = [];

while ($row = $result->fetch_assoc()) {
    // Format data agar sesuai ekspektasi JS di eo_dashboard.php
    $registrations[] = [
        'registration_id' => $row['registration_id'],
        'team_name' => htmlspecialchars($row['team_name']),
        'status' => strtolower($row['status']),
        'reg_date' => date('d M Y', strtotime($row['reg_date']))
    ];
}

$stmt->close();
$conn->close();

// --- 3. Kirim data yang sudah diformat (ARRAY JSON) ---
sendJsonResponse($registrations);
?>