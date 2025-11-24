<?php
// Simpan sebagai: fetch_tournaments.php
include("../php/config.php"); 

// Cek koneksi
if (!isset($conn) || $conn->connect_error) {
    // Jika koneksi gagal, hentikan eksekusi dan tampilkan pesan error
    die("<h3>Error Koneksi Database: Tidak dapat memuat data turnamen.</h3>");
}

// Query untuk mengambil data turnamen (mengambil semua status)
$sql = "SELECT id, title, sport, location, date_start, date_end, prize_pool, status FROM tournaments ORDER BY id DESC";
$result = $conn->query($sql);

$output = "
<table>
    <tr>
        <th>ID</th>
        <th>Judul</th>
        <th>Tanggal</th>
        <th>Hadiah</th>
        <th>Status</th>
        <th>Aksi</th>
    </tr>";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        // 🔑 REVISI: Tetapkan status default 'Pending' jika NULL atau kosong
        $current_status = $row['status'] ?? 'Pending';
        if (empty($current_status)) {
            $current_status = 'Pending';
        }
        
        // Menentukan style berdasarkan status
        $status_class = '';
        if ($current_status == 'Approved') { // Menggunakan 'Approved'
            $status_class = 'style="color:lightgreen; font-weight:bold;"';
        } 
        elseif ($current_status == 'Rejected' || $current_status == 'Cancelled') {
            $status_class = 'style="color:red; font-weight:bold;"';
        } 
        elseif ($current_status == 'Pending') {
            $status_class = 'style="color:yellow; font-weight:bold; background:#333;"';
        } else {
            $status_class = 'style="color:white;"';
        }

        $output .= "
        <tr>
            <td>{$row['id']}</td>
            <td>" . htmlspecialchars($row['title']) . "</td>
            <td>" . date('d M', strtotime($row['date_start'])) . " - " . date('d M Y', strtotime($row['date_end'])) . "</td>
            <td>Rp" . number_format($row['prize_pool'], 0, ',', '.') . "</td>
            <td {$status_class}>{$current_status}</td> 
            <td>
                                <button onclick=\"openEditPopup({$row['id']}, '" . htmlspecialchars($row['title']) . "', '{$current_status}')\" style='background:#004d00; color:#ccffcc; border-color:#ccffcc;'>ACC / Reject</button>
                <button onclick=\"deleteTournament({$row['id']})\" style='background:#550000; color:#ff6666; border-color:#ff6666;'>Delete</button>
            </td>
        </tr>";
    }
} else {
    $output .= "<tr><td colspan='6' style='text-align:center;'>Belum ada turnamen yang diajukan.</td></tr>";
}

$output .= "</table>";
echo $output;

$conn->close();
?>