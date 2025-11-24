<?php
session_start();
include("../php/config.php"); 

if (!isset($_SESSION["admin_username"])) {
    // Pastikan path login admin benar
    header("Location: ../php/admin_login.php");
    exit();
}

$adminUsername = $_SESSION["admin_username"];

if (!isset($conn) || @$conn->connect_error) { 
    $db_error = true;
} else {
    $db_error = false;
}

$active_tab = $_GET['tab'] ?? 'tournaments'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #000;
    color: #ff4d4d;
}
header {
    display: flex;
    justify-content: space-between;
    padding: 20px;
    background: #0a0a0a;
    border-bottom: 1px solid #ff1a1a;
    color: white;
}
.tab-btn {
    padding: 10px 20px;
    margin-right: 10px;
    border-radius: 8px;
    border: 1px solid #ff1a1a;
    background: #1a1a1a;
    color: #ff4d4d;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.tab-btn.active {
    background: #ff1a1a;
    color: white;
}
.page {
    display: none;
    padding: 20px;
}
.page.active {
    display: block;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
table th {
    background: #220000;
    color: #ff4d4d;
}
table td, th {
    padding: 12px;
    border: 1px solid #550000;
    word-break: break-word;
}
.popup-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}
.popup-box {
    background: #1a0000;
    padding: 25px;
    height : 300px;
    width: 400px;
    border-radius: 12px;
    border: 1px solid #ff1a1a;
    color: white;
}
input, select {
    width: 100%;
    padding: 10px;
    margin-top: 10px;
    margin-bottom: 15px;
    background: #000;
    color: white;
    border: 1px solid #ff1a1a;
    border-radius: 6px;
    box-sizing: border-box;
}
button {
    padding: 10px 16px;
    border: 1px solid #ff1a1a;
    background: #1a1a1a;
    color: #ff6666;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 10px;
}
button:hover {
    background: #ff1a1a;
    color: white;
}
.user-menu {
    position: relative;
    display: inline-block;
}
.user-info {
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    background-color: #1a1a1a;
    min-width: 120px;
    box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.5);
    z-index: 100;
    border: 1px solid #ff1a1a;
    border-top: none;
    border-radius: 0 0 8px 8px;
}
.dropdown-content a {
    color: #ff4d4d;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
}
.dropdown-content a:hover {
    background-color: #ff1a1a;
    color: white;
}
.analytics-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}
.card {
    background: #1a0000;
    padding: 15px 20px;
    border-radius: 8px;
    border-left: 5px solid #ff1a1a;
    min-width: 200px;
    flex-grow: 1;
}
.card-value {
    font-size: 24px;
    font-weight: bold;
    color: white;
    margin-top: 5px;
}
.card-title {
    font-size: 14px;
    color: #ff8080;
}
.chart-container {
    max-width: 880px;
    width: 800px;
    flex-basis: 48%;
    height: 700px;
    background: #1a0000;
    padding: 30px;
    border-radius: 8px;
    border: 1px solid #550000;
    flex-grow: 0;
}
.charts-wrapper {
max-width: 1000px;
    display: flex;
    flex-wrap: nowrap;
    /* 'space-between' akan menempelkan satu ke kiri dan satu ke kanan */
    justify-content: space-between; 
    margin: 20px auto 0;
}
</style>
</head>

<body>

<header>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="background:#ff1a1a;padding:10px 14px;font-weight:bold;border-radius:10px;color:black;">CN</div>
        <div>
            <div style="font-size:20px;font-weight:bold;">Admin</div>
            <div style="font-size:12px;color:#ffb3b3;">Panel Pengelolaan</div>
        </div>
    </div>

    <div class="user-menu">
        <div class="user-info" onclick="toggleDropdown()">
            <span><?php echo htmlspecialchars($adminUsername); ?></span>
            <i class="fa fa-user"></i>
        </div>
        <div class="dropdown-content" id="myDropdown">
            <a href="logout.php" onclick="return confirm('Yakin ingin keluar dari dashboard Admin?');">
                <i class="fa fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</header>

<div style="padding:20px;">
    <button class="tab-btn <?php echo $active_tab == 'tournaments' ? 'active' : ''; ?>" onclick="switchTab('tournaments')">Tournaments</button>
    <button class="tab-btn <?php echo $active_tab == 'users' ? 'active' : ''; ?>" onclick="switchTab('users')">Users</button>
    <button class="tab-btn <?php echo $active_tab == 'analytics' ? 'active' : ''; ?>" onclick="switchTab('analytics')">Analytics</button>
</div>

<section id="tournaments" class="page <?php echo $active_tab == 'tournaments' ? 'active' : ''; ?>">
    <h2 style="color:white;">Tournament Management (ACC/Reject)</h2>
    <div id="tournamentList">
        <p style="color:gray;">Loading tournaments...</p>
    </div>
</section>

<section id="users" class="page <?php echo $active_tab == 'users' ? 'active' : ''; ?>">
    <h2 style="color:#ff3b3b;">User Management</h2>
    
    <?php $current_filter = $_GET['role_filter'] ?? 'all'; ?>
    <div style="margin-bottom: 15px; color: white;">
        Filter Role:
        <a href="admin_dashboard.php?tab=users&role_filter=all" class="tab-btn <?php echo ($current_filter == 'all') ? 'active' : ''; ?>" style="background:#555;">Official (All Non-Admin)</a>
        <a href="admin_dashboard.php?tab=users&role_filter=eo" class="tab-btn <?php echo (isset($_GET['role_filter']) && $_GET['role_filter'] == 'eo') ? 'active' : ''; ?>" style="background:#006600;">Event Organizer</a>
    </div>
    
    <table>
        <tr>
            <th>ID</th>
            <th>Nama</th>
            <th>Email Utama</th>
            <th>Kontak/Phone</th>
            <th>Alamat EO</th>
            <th>Tanggal Daftar</th>
            <th>Status</th> 
            <th>Role</th>
            <th>Aksi</th>
        </tr>
        <?php
        if ($db_error) {
            echo "<tr><td colspan='9' style='text-align:center; color:yellow;'>Error: Database connection failed. Cannot load Users data.</td></tr>";
        } else {
            
            $users = null;

            if ($current_filter == 'eo') {
                // 🔑 QUERY EO: AMBIL LANGSUNG DARI event_organizers
                $sql = "
                    SELECT 
                        eo_id AS id, 
                        name AS nama, 
                        contact_email AS email, 
                        phone, 
                        address, 
                        is_verified AS status, 
                        created_at AS joined_at,
                        'Event Organizer' AS role_display
                    FROM event_organizers 
                    ORDER BY eo_id DESC
                ";
            } else {
                // FILTER DEFAULT/OFFICIAL: Ambil dari users
                $sql = "
                    SELECT 
                        id, 
                        nama, 
                        email, 
                        joined_at, 
                        status, 
                        role,
                        'N/A' AS phone, 
                        'N/A' AS address,
                        role AS role_display
                    FROM users 
                    WHERE role <> 'admin' 
                    ORDER BY id DESC
                ";
            }

            $users = @$conn->query($sql);

            if ($users && $users->num_rows > 0) {
                while ($row = $users->fetch_assoc()) {
                    
                    $display_status = strtoupper(htmlspecialchars($row['status'] ?? 'N/A'));
                    $display_role = htmlspecialchars($row['role_display'] ?? 'N/A');

                    // Style status
                    if ($display_status == 'BANNED') {
                        $status_color = 'style="color:red; font-weight:bold;"';
                    } elseif ($display_status == 'ACTIVE' || $display_status == 'APPROVED' || $display_status == 'VERIFIED') {
                        $status_color = 'style="color:lightgreen; font-weight:bold;"';
                    } else {
                        $status_color = 'style="color:yellow;"';
                    }
                    
                    // KONDISIONAL: Menentukan isi kolom Phone dan Alamat
                    $phone_col = htmlspecialchars($row['phone'] ?? 'N/A');
                    $address_col = htmlspecialchars($row['address'] ?? 'N/A');

                    if ($current_filter == 'eo') {
                        // Jika Event Organizer, gunakan data EO
                        $display_role = 'Event Organizer'; 
                        
                    } else {
                        // Jika Official/User, kolom Phone dan Alamat harus N/A
                        $phone_col = "N/A";
                        $address_col = "N/A";
                        // Role ditampilkan sesuai data users (official, user, dll.)
                        $display_role = htmlspecialchars($row['role']);
                    }
                    
                    echo "
                    <tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['nama']) . "</td>
                        <td>" . htmlspecialchars($row['email']) . "</td>
                        <td>{$phone_col}</td> 
                        <td>{$address_col}</td> 
                        <td>{$row['joined_at']}</td>
                        <td {$status_color}>{$display_status}</td>
                        <td>{$display_role}</td>";

                        // 🔑 PERBAIKAN: Menghilangkan kolom Aksi (Edit/Delete) untuk EO
                        if ($current_filter == 'eo') {
                            echo "<td>N/A</td>";
                        } else {
                            // Tampilkan Aksi hanya untuk Official/User (yang berasal dari tabel users)
                            echo "
                            <td>
                                <a href='update_user.php?id={$row['id']}' style='color:yellow;'>Edit</a> | 
                                <a href='delete_user.php?id={$row['id']}' style='color:red;' onclick='return confirm(\"Yakin hapus user?\")'>Delete</a>
                            </td>";
                        }
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='9' style='text-align:center;'>Tidak ada pengguna ditemukan dengan kriteria ini.</td></tr>";
            }
        }
        ?>
    </table>
</section>

<section id="analytics" class="page <?php echo $active_tab == 'analytics' ? 'active' : ''; ?>">
    <h2 style="color:white;">Platform Analytics & Summary</h2>
    <div id="analytics-content">
        <div class="analytics-cards">
            <div class="card">
                <div class="card-title">Total Pengguna</div>
                <div class="card-value" id="total-users">0</div>
            </div>
            <div class="card">
                <div class="card-title">Pengguna Aktif</div>
                <div class="card-value" id="active-users">0</div>
            </div>
            <div class="card">
                <div class="card-title">Turnamen Aktif</div>
                <div class="card-value" id="active-tournaments">0</div>
            </div>
            <div class="card" style="border-left-color: #33ccff;">
                <div class="card-title">Total Turnamen</div>
                <div class="card-value" id="total-tournaments">0</div>
            </div>
            <div class="card" style="border-left-color: #00ff66;">
                <div class="card-title">Total Hadiah (Prize Pool)</div>
                <div class="card-value" id="total-prize-pool">Rp0</div>
            </div>
        </div>
        
        <div class="charts-wrapper">
            <div class="chart-container">
                <h3 style="color:#ff4d4d;">Proporsi Status Akun</h3>
                <canvas id="userStatusChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 style="color:#ff4d4d;">Turnamen Berdasarkan Status</h3>
                <canvas id="tournamentStatusChart"></canvas>
            </div>
        </div>
    </div>
</section>


<div class="popup-bg" id="editPopup">
    <div class="popup-box">
        <h2>ACC/REJECT Tournament</h2>
        <form id="editStatusForm">
            <input type="hidden" name="id" id="editTournamentId">
            
            Judul Turnamen:
            <input type="text" id="editTournamentTitle" readonly style="background:#0a0a0a; color:#ccc;">

            Pilih Status Baru:
            <select name="status" id="editTournamentStatus" required>
                <option value="Pending">Pending Review</option>
                <option value="Approved">ACC (Approve)</option>
                <option value="Rejected">Reject</option>
                <option value="Completed">Completed</option>
            </select>

            <div style="display:flex;gap:10px;margin-top:12px;">
                <button type="submit" style="background:green;color:white;border-radius:6px;padding:10px 14px;border:none;">Update Status</button>
                <button type="button" onclick="closeEditPopup()" style="background:#550000;color:white;border-radius:6px;padding:10px 14px;border:none;">Close</button>
            </div>
        </form>
    </div>
</div>


<script>
let analyticsData = {};

function renderCharts() {
    if (!analyticsData.summary || !analyticsData.charts) return;
    const totalUsers = parseInt(analyticsData.summary.total_users || 0);
    const bannedUsers = parseInt(analyticsData.summary.banned_users || 0);
    const activeUsers = Math.max(0, totalUsers - bannedUsers);

    const userStatusCtx = document.getElementById('userStatusChart');
    if (userStatusCtx && userStatusCtx.chart) userStatusCtx.chart.destroy();
    
    if (userStatusCtx) {
        userStatusCtx.chart = new Chart(userStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Aktif', 'Diblokir (Banned)'],
                datasets: [{
                    data: [activeUsers, bannedUsers],
                    backgroundColor: ['#00aaff', '#ff1a1a'],
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: 'white' } }
                }
            }
        });
    }

    const statusData = analyticsData.charts.tournament_status || {};
    const tournamentStatusCtx = document.getElementById('tournamentStatusChart');

    if (tournamentStatusCtx && tournamentStatusCtx.chart) tournamentStatusCtx.chart.destroy();
    
    if (tournamentStatusCtx) {
        tournamentStatusCtx.chart = new Chart(tournamentStatusCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(statusData),
                datasets: [{
                    label: 'Jumlah Turnamen',
                    data: Object.values(statusData),
                    backgroundColor: [
                        'rgba(255, 159, 64, 0.7)', // Pending
                        'rgba(75, 192, 192, 0.7)', // Active
                        'rgba(255, 99, 132, 0.7)', // Rejected
                        'rgba(54, 162, 235, 0.7)', // Completed
                        'rgba(153, 102, 255, 0.7)' // Cancelled
                    ],
                    borderColor: [
                        'rgb(255, 159, 64)',
                        'rgb(75, 192, 192)',
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(153, 102, 255)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { color: '#ccc' }, grid: { color: '#333' } }
                },
                plugins: { legend: { labels: { color: 'white' } } }
            }
        });
    }
}


function loadAnalytics() {
    fetch('fetch_analytics.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('analytics-content').innerHTML = `<p style="color:red;">Error: ${data.error}</p>`;
                return;
            }
            analyticsData = data;
            document.getElementById('total-users').textContent = data.summary.total_users;
            document.getElementById('active-users').textContent = data.summary.active_users;
            document.getElementById('active-tournaments').textContent = data.summary.active_tournaments;
            document.getElementById('total-tournaments').textContent = data.summary.total_tournaments;
            const prizePoolElement = document.getElementById('total-prize-pool');
            if (prizePoolElement) {
                prizePoolElement.textContent = 'Rp' + (data.summary.total_prize_pool || '0');
            }
            renderCharts();
        })
        .catch(error => {
            console.error('Error fetching analytics data:', error);
            document.getElementById('analytics-content').innerHTML = '<p style="color:red;">Gagal memuat data analytics. Cek file fetch_analytics.php.</p>';
        });
}


function toggleDropdown() {
    document.getElementById("myDropdown").style.display = 
        document.getElementById("myDropdown").style.display === "block" ? "none" : "block";
}

window.onclick = function(event) {
    if (!event.target.closest('.user-info')) {
        const dropdowns = document.getElementsByClassName("dropdown-content");
        for (let i = 0; i < dropdowns.length; i++) {
            const openDropdown = dropdowns[i];
            if (openDropdown.style.display === 'block') {
                openDropdown.style.display = 'none';
            }
        }
    }
}


function loadTournaments() {
    fetch('fetch_tournaments.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('tournamentList').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('tournamentList').innerHTML = '<p style="color:red;">Gagal memuat daftar turnamen. Cek file fetch_tournaments.php atau koneksi DB.</p>';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || 'tournaments'; 
    
    if (initialTab === 'analytics') {
        loadAnalytics();
    } else if (initialTab === 'tournaments') {
        loadTournaments();
    } 
});


function switchTab(tab) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(tab).classList.add('active');

    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    const clickedButton = event && event.currentTarget;
    if (clickedButton) {
        clickedButton.classList.add('active');
    } else {
        document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');
    }

    history.replaceState(null, '', `admin_dashboard.php?tab=${tab}`);

    if (tab === 'tournaments') {
        loadTournaments();
    } else if (tab === 'analytics') {
        loadAnalytics(); 
    }
}

function openEditPopup(id, title, currentStatus) {
    document.getElementById('editTournamentId').value = id;
    document.getElementById('editTournamentTitle').value = title;
    const sel = document.getElementById('editTournamentStatus');
    if (sel) {
        const opts = Array.from(sel.options).map(o => o.value);
        sel.value = opts.includes(currentStatus) ? currentStatus : 'Pending';
    }
    document.getElementById("editPopup").style.display = "flex";
}
function closeEditPopup(){ 
    document.getElementById("editPopup").style.display = "none"; 
}

function deleteTournament(id) {
    if (confirm("Yakin ingin menghapus turnamen ini? Tindakan tidak dapat dibatalkan.")) {
        fetch(`delete_tournament.php?id=${id}`)
            .then(response => response.text())
            .then(data => {
                if (data.trim() === 'success') {
                    alert('Turnamen berhasil dihapus!');
                    loadTournaments();
                } else {
                    alert('Gagal menghapus turnamen. Response: ' + data); 
                }
            })
            .catch(error => console.error('Error deleting tournament:', error));
    }
}

const editForm = document.getElementById('editStatusForm');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('update_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            const response = data.trim();

            if (response === 'success' || response === 'success_no_change') {
                alert('Status berhasil diperbarui!');
                closeEditPopup();
                loadTournaments();
            } else {
                console.error('Response dari server:', response);
                alert('Gagal memperbarui status. Cek konsol browser untuk detail error.');
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
            alert('Terjadi error saat menghubungi server.');
        });
    });
}


document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const active_tab = urlParams.get('tab') || 'tournaments'; 

    if (urlParams.get('success') === '1' || urlParams.get('updated') === '1') {
        alert('Operasi berhasil!');
        const url = window.location.href.split('?')[0];
        history.replaceState(null, '', url + `?tab=${active_tab}`);
    }
});
</script>

</body>
</html>