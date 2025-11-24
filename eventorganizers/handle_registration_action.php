<?php
session_start();
require_once "../php/config.php";

header('Content-Type: application/json');

// Pastikan EO login
if (!isset($_SESSION["event_id"])) {
    echo json_encode(['success'=>false, 'message'=>'Unauthorized: EO tidak login']);
    exit;
}

// Ambil data POST
$eo_id = $_SESSION['event_id'];
$registration_id = intval($_POST['registration_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$registration_id || !in_array($action, ['accept','reject'])) {
    echo json_encode(['success'=>false, 'message'=>'Data tidak valid']);
    exit;
}

// Pastikan EO punya hak atas turnamen ini
$stmt = $conn->prepare("
    SELECT t.id 
    FROM tournaments t
    JOIN tournament_registrations r ON r.tournament_id = t.id
    WHERE r.id = ? AND t.eo_id = ?
");
$stmt->bind_param("ii", $registration_id, $eo_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['success'=>false, 'message'=>'Anda tidak memiliki izin mengubah pendaftar ini']);
    exit;
}
$stmt->close();

// Tentukan status baru
$new_status = $action === 'accept' ? 'accepted' : 'rejected';

// Update status pendaftar
$update = $conn->prepare("UPDATE tournament_registrations SET status=? WHERE id=?");
$update->bind_param("si", $new_status, $registration_id);

if ($update->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => "Pendaftar berhasil diupdate menjadi '$new_status'"
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => "Gagal mengupdate status: " . $conn->error
    ]);
}

$update->close();
$conn->close();
