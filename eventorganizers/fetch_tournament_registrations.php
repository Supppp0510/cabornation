<?php
session_start();
require_once "../php/config.php";

// Pastikan EO login
if (!isset($_SESSION["event_id"])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

// Pastikan ada tournament_id
if (!isset($_GET['tournament_id'])) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$tournament_id = intval($_GET['tournament_id']);
$status_filter = $_GET['status'] ?? 'all';

// Filter status valid
$valid_status = ['pending','accepted','rejected'];
$where_status = "";
$params = [$tournament_id];
$param_types = "i";

if ($status_filter !== 'all') {
    if (!in_array($status_filter, $valid_status)) {
        $status_filter = 'all';
    } else {
        $where_status = " AND r.status = ?";
        $params[] = $status_filter;
        $param_types .= "s";
    }
}

// Query data pendaftar
$sql = "SELECT r.id AS registration_id, r.team_name, r.status, r.created_at AS reg_date
        FROM tournament_registrations r
        WHERE r.tournament_id = ? $where_status
        ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);

if ($param_types == "i") {
    $stmt->bind_param($param_types, $params[0]);
} else {
    $stmt->bind_param($param_types, $params[0], $params[1]);
}

$stmt->execute();
$result = $stmt->get_result();

$registrations = [];
while($row = $result->fetch_assoc()){
    $registrations[] = [
        'registration_id' => $row['registration_id'],
        'team_name' => htmlspecialchars($row['team_name']),
        'status' => strtolower($row['status']),
        'reg_date' => date('d M Y', strtotime($row['reg_date']))
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($registrations);
