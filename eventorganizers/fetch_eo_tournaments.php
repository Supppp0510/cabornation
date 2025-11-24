<?php
include("../php/config.php");

$eo_id = intval($_GET['eo_id'] ?? 0);

if ($eo_id <= 0) {
    echo "<p style='color:white;'>Invalid EO ID.</p>";
    exit;
}

// Persiapan query
$stmt = $conn->prepare("
    SELECT id, title, sport, location, date_start, prize_pool, poster_url, status 
    FROM tournaments 
    WHERE eo_id = ? 
    ORDER BY id DESC
");
$stmt->bind_param("i", $eo_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<p style='color:#ccc; text-align:center;'>Belum ada turnamen yang dibuat.</p>";
    exit;
}

// Memastikan style container grid sudah ada (diambil dari kode Anda)
echo '<div style="
        display:grid;
        grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
        gap:18px;
        padding:20px;
    ">';

while ($row = $res->fetch_assoc()) {
    $poster = !empty($row['poster_url']) 
                ? "../uploads/" . $row['poster_url'] 
                : "../assets/default_poster.jpg";

    $status = strtoupper($row['status']);
    $statusColor = $status === "APPROVED" ? "#4CAF50" : ($status === "PENDING" ? "#FFC107" : "#F44336");

    // Tentukan apakah turnamen bisa diklik untuk melihat pendaftar.
    // Biasanya hanya turnamen yang APPROVED yang relevan, tapi kita aktifkan semua untuk testing.
    $isClickable = 'tournament-card';
    
    // --- START MODIFIKASI PENTING ---
    echo '
        <div class="tournament-card"
             data-tournament-id="'.htmlspecialchars($row['id']).'"
             data-tournament-title="'.htmlspecialchars($row['title']).'"
             style="
                background:#1a1a1a;
                border:1px solid #ffcc00;
                border-radius:10px;
                overflow:hidden;
                box-shadow:0 0 8px rgba(0,0,0,0.4);
             ">
             
             <div style="
                 height:500px; /* Dikecilkan agar card tidak terlalu tinggi */
                 width : 300px;
                 background:url('.$poster.') center/cover no-repeat;
             "></div>

            <div style="padding:15px; color:white; text-align:left;">
                <h3 style="margin-top:0; color:#ffcc00;">'.htmlspecialchars($row['title']).'</h3>
                
                <p style="margin:4px 0; color:#ccc;">
                    <b>Sport:</b> '.htmlspecialchars($row['sport']).'
                </p>
                <p style="margin:4px 0; color:#ccc;">
                    <b>Lokasi:</b> '.htmlspecialchars($row['location']).'
                </p>
                <p style="margin:4px 0; color:#ccc;">
                    <b>Tanggal:</b> '.date("d M Y", strtotime($row['date_start'])).'
                </p>
                <p style="margin:4px 0; color:#ccc;">
                    <b>Prize Pool:</b> Rp '.number_format($row['prize_pool'],0,",",".").'
                </p>

                <div style="
                    margin-top:10px;
                    padding:6px 10px;
                    display:inline-block;
                    background:'.$statusColor.';
                    color:black;
                    font-weight:bold;
                    border-radius:6px;
                ">
                    '.$status.'
                </div>
            </div>
        </div>
    ';
    // --- END MODIFIKASI PENTING ---
}

echo "</div>";

$stmt->close();
$conn->close();
?>