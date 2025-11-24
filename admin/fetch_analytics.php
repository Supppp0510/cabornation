<?php
// Simpan sebagai: fetch_analytics.php
include("../php/config.php"); 

if (!isset($conn) || @$conn->connect_error) {
    // Keluarkan error dalam format JSON agar JavaScript dapat menampilkannya
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Database connection failed or $conn is undefined.']));
}

// Tambahkan error reporting untuk sementara (Hanya untuk debugging)
// error_reporting(E_ALL); ini akan menyebabkan error JSON, hapus jika Anda sudah tahu masalahnya.

// 1. Statistik Pengguna
$total_users_q = @$conn->query("SELECT COUNT(id) AS total FROM users");
$total_users = $total_users_q ? $total_users_q->fetch_assoc()['total'] : 0;

$banned_users_q = @$conn->query("SELECT COUNT(id) AS banned FROM users WHERE status = 'banned'");
$banned_users = $banned_users_q ? $banned_users_q->fetch_assoc()['banned'] : 0;
$active_users = $total_users - $banned_users;

// 2. Statistik Turnamen
$total_tournaments_q = @$conn->query("SELECT COUNT(id) AS total FROM tournaments");
$total_tournaments = $total_tournaments_q ? $total_tournaments_q->fetch_assoc()['total'] : 0;

$active_tournaments_q = @$conn->query("SELECT COUNT(id) AS active FROM tournaments WHERE status = 'Active'");
$active_tournaments = $active_tournaments_q ? $active_tournaments_q->fetch_assoc()['active'] : 0;

// 3. Statistik Keuangan (Total Prize Pool)
$total_prize_q = @$conn->query("SELECT SUM(prize_pool) AS total_prize FROM tournaments");
$total_prize = number_format($total_prize_q ? ($total_prize_q->fetch_assoc()['total_prize'] ?? 0) : 0, 0, ',', '.');


// 4. Data untuk Grafik: Hitungan Turnamen berdasarkan Status
$status_counts = [];
$status_q = @$conn->query("SELECT status, COUNT(id) as count FROM tournaments GROUP BY status");
if ($status_q) {
    while ($row = $status_q->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// Kompilasi semua data
$analytics_data = [
    'summary' => [
        'total_users' => $total_users,
        'active_users' => $active_users,
        'banned_users' => $banned_users,
        'total_tournaments' => $total_tournaments,
        'active_tournaments' => $active_tournaments,
        'total_prize_pool' => $total_prize,
    ],
    'charts' => [
        'tournament_status' => $status_counts,
    ]
];

// Pastikan header diatur sebelum output apa pun
header('Content-Type: application/json');
echo json_encode($analytics_data);
// Tidak ada tag penutup ?>