<?php
ob_start(); // TAMBAHKAN DI BARIS PERTAMA
ini_set('session.cookie_domain', '127.0.0.1');
ini_set('session.cookie_path', '/');
session_start();

// --- Pengecekan Session Kritis ---
if (session_id() !== '' && !isset($_SESSION["event_id"])) {
    session_unset();
    session_destroy();
}
// --- Akhir Pengecekan Session Kritis ---

include("../php/config.php"); 

// Cek sesi EO. Redirect jika tidak login.
if (!isset($_SESSION["event_id"])) {
    header("Location: http://127.0.0.1/cabornation/eventorganizers/login_event.php");
    exit();
}

$eoId = $_SESSION["event_id"];
$eoName = $_SESSION["event_name"];

// Pastikan koneksi ($conn) tersedia
if (!isset($conn) || @$conn->connect_error) { 
    // Handle error koneksi jika diperlukan
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EO Dashboard - <?php echo htmlspecialchars($eoName); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<style>
body {
    margin:0;
    font-family: Arial, sans-serif;
    background:#111;
    color:#ffcc00;
}

header {
    display:flex;
    justify-content:space-between;
    padding:20px;
    background:#0a0a0a;
    border-bottom:3px solid #ffcc00;
    color:white;
}

.tab-btn {
    padding:10px 20px;
    margin-right:10px;
    border-radius:8px;
    border:1px solid #ffcc00;
    background:#222;
    color:#ffcc00;
    cursor:pointer;
    text-decoration: none;
    display: inline-block;
}
.tab-btn.active { 
    background:#ffcc00; 
    color:#111;
    font-weight: bold;
}

.page { display:none; padding:20px; }
.page.active { display:block; }

.center-container {
    width:100%;
    display:flex;
    justify-content:center;
    margin-top:20px;
}

.form-box {
    width:100%;
    max-width:600px;
    background:#181818;
    padding:25px;
    border-radius:12px;
    border:1px solid #ffcc00;
}

input, select, textarea {
    width:100%;
    padding:10px;
    margin-top:8px;
    background:#444;
    color:white;
    border:1px solid #ffcc00;
    border-radius:6px;
    box-sizing: border-box;
    margin-bottom: 15px;
}

button {
    padding:10px 16px;
    border:1px solid #ffcc00;
    background:#ffcc00;
    color:#111;
    border-radius:6px;
    cursor:pointer;
    margin-top:10px;
    font-weight: bold;
}
button:hover { background:#e6b800; }

.tournament-card {
    background: #222;
    border: 1px solid #444;
    padding: 15px;
    margin: 10px auto;
    max-width: 500px;
    border-radius: 8px;
    text-align: left;
    cursor: pointer;
    transition: transform 0.2s, border-color 0.2s;
    color: white;
}
.tournament-card:hover {
    transform: translateY(-3px);
    border-color: #ffcc00;
}

.modal-tab-btn {
    padding: 8px 15px;
    margin-right: 5px;
    border-radius: 6px;
    border: 1px solid #ffcc00;
    background: #222;
    color: #ffcc00;
    cursor: pointer;
}
.modal-tab-btn.active { 
    background:#ffcc00; 
    color:#111;
    font-weight: bold;
}

.registration-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    margin-bottom: 8px;
    border-bottom: 1px solid #333;
    transition: background-color 0.3s;
}
.registration-item:hover { background-color: #222; }
.registration-item:last-child { border-bottom: none; }
.reg-status-pending { color: orange; font-weight: bold; }
.reg-status-accepted { color: #00ff00; font-weight: bold; }
.reg-status-rejected { color: red; font-weight: bold; }

.action-btn {
    padding: 6px 10px;
    margin-left: 5px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}
.btn-accept { background: #00aaff; color: white; border: none; }
.btn-kick { background: red; color: white; border: none; }

/* Dropdown header */
.dropdown-container { position: relative; display:inline-block; }
.dropdown-menu {
    display:none;
    position:absolute;
    right:0;
    background:#222;
    border:1px solid #ffcc00;
    border-radius:6px;
    margin-top:5px;
    min-width:120px;
    z-index:10;
}
.dropdown-menu div { padding:10px; cursor:pointer; color:#ffcc00; }
.dropdown-menu div:hover { background:#333; }
</style>
</head>
<body>

<header>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="font-size:24px;font-weight:bold;color:#ffcc00;">EO Panel</div>
        <div style="font-size:12px;color:#ccc;">Management Event Organizer</div>
    </div>

    <div class="dropdown-container">
        <span id="eoNameDropdown" style="color:#ffcc00; cursor:pointer;"><?php echo htmlspecialchars($eoName); ?></span>
        <i class="fa fa-user" style="color:white; cursor:pointer;"></i>
        <div id="dropdownMenu" class="dropdown-menu">
            <div id="logoutBtn">Logout</div>
        </div>
    </div>
</header>

<div style="padding:20px; text-align:center;">
    <button class="tab-btn active" onclick="switchTab('my-tournaments', event)">My Tournaments</button>
    <button class="tab-btn" onclick="switchTab('create-tournament', event)">Create New Tournament</button>
</div>

<section id="my-tournaments" class="page active">
    <h2 style="color:white; text-align:center;">My Tournaments</h2>
    <div id="tournamentList" style="text-align:center;">
        <p style="color:white;">Loading tournaments...</p>
    </div>
</section>

<section id="create-tournament" class="page">
    <h2 style="color:white; text-align:center;">Create New Tournament</h2>
    <div class="center-container">
        <form method="POST" action="create_tournament_eo.php" enctype="multipart/form-data" id="createTournamentForm" class="form-box">
            <label>Judul Turnamen</label>
            <input type="text" name="title" required placeholder="Contoh: Piala Rektor Futsal 2026">
            <label>Cabang Olahraga</label>
            <input type="text" name="sport" required placeholder="Contoh: Futsal, Basket 3x3">
            <label>Lokasi</label>
            <input type="text" name="location" required placeholder="Contoh: Lapangan A Kampus B">
            <div style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <label>Tanggal Mulai</label>
                    <input type="date" name="date_start" required>
                </div>
                <div style="flex: 1;">
                    <label>Tanggal Selesai</label>
                    <input type="date" name="date_end">
                </div>
            </div>
            <label>Registration Fee (Rp)</label>
            <input type="number" name="registration_fee" required placeholder="0 untuk gratis">
            <label>Prize Pool (Rp)</label>
            <input type="number" name="prize_pool" required placeholder="Contoh: 2500000">
            <label>Poster / Gambar Event</label>
            <input type="file" name="poster" required>
            <label>Deskripsi Lengkap</label>
            <textarea name="description" rows="5" required placeholder="Jelaskan detail turnamen, syarat, dan ketentuan."></textarea>
            <input type="hidden" name="eo_id" value="<?php echo $eoId; ?>">
            <button type="submit">Submit for Approval</button>
        </form>
    </div>
    <p style="color:#aaa; font-size: 14px; text-align:center; margin-top: 15px;">*Turnamen akan berstatus 'Pending' sampai disetujui oleh Administrator.</p>
</section>

<div id="registrationModal" style="
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.8);
    padding-top: 60px;
">
    <div style="
        background-color: #181818;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #ffcc00;
        width: 80%;
        max-width: 800px;
        border-radius: 12px;
        color: white;
    ">
        <span onclick="closeModal()" style="
            color: #ffcc00;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        ">&times;</span>
        <h3 id="modalTitle" style="color:#ffcc00;">Detail Pendaftar: [Judul Turnamen]</h3>
        
        <div style="margin-bottom: 20px;">
            <button class="modal-tab-btn active" data-status="all" onclick="filterRegistrations(this, 'all')">Semua</button>
            <button class="modal-tab-btn" data-status="pending" onclick="filterRegistrations(this, 'pending')">Pending</button>
            <button class="modal-tab-btn" data-status="accepted" onclick="filterRegistrations(this, 'accepted')">Diterima</button>
            <button class="modal-tab-btn" data-status="rejected" onclick="filterRegistrations(this, 'rejected')">Ditolak/Kick</button>
        </div>

        <div id="registrationsContent">
            <p style="text-align:center;">Memuat data...</p>
        </div>
    </div>
</div>

<script>
const eoId = <?php echo json_encode($eoId); ?>;
let currentTournamentId = null;

function loadTournaments() {
    fetch(`fetch_eo_tournaments.php?eo_id=${eoId}`)
        .then(res => res.text())
        .then(data => {
            document.getElementById('tournamentList').innerHTML = data;
            document.querySelectorAll('.tournament-card').forEach(card => {
                card.onclick = function() {
                    const tournamentId = this.dataset.tournamentId; 
                    const tournamentTitle = this.dataset.tournamentTitle || 'Tournament Details'; 
                    openModal(tournamentId, tournamentTitle);
                };
            });
        })
        .catch(error => {
            document.getElementById('tournamentList').innerHTML = `<p style="color:red;">Error loading tournaments: ${error.message}</p>`;
        });
}

function switchTab(tab, event) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(tab).classList.add('active');

    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    if (tab === 'my-tournaments') loadTournaments();
}

function openModal(tournamentId, title) {
    currentTournamentId = tournamentId;
    document.getElementById('modalTitle').textContent = `Detail Pendaftar: ${title}`;
    document.getElementById('registrationModal').style.display = 'block';
    
    document.querySelectorAll('.modal-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.modal-tab-btn[data-status="all"]').classList.add('active');

    loadRegistrations('all');
}

function closeModal() {
    document.getElementById('registrationModal').style.display = 'none';
    currentTournamentId = null;
    document.getElementById('registrationsContent').innerHTML = '<p style="text-align:center;">Memuat data...</p>';
}

function filterRegistrations(btn, status) {
    document.querySelectorAll('.modal-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadRegistrations(status);
}

function loadRegistrations(status) {
    document.getElementById('registrationsContent').innerHTML = '<p style="text-align:center;">Memuat data...</p>';

    fetch(`fetch_tournament_registrations.php?tournament_id=${currentTournamentId}&status=${status}`)
        .then(res => res.json())
        .then(data => {
            let html = '';
            if (data.length === 0) {
                html = `<p style="text-align:center;">Belum ada pendaftar dengan status ${status}.</p>`;
            } else {
                data.forEach(reg => {
                    const statusClass = reg.status === 'pending' ? 'reg-status-pending' : (reg.status === 'accepted' ? 'reg-status-accepted' : 'reg-status-rejected');
                    let actions = '';
                    if (reg.status === 'pending') {
                        actions = `<button class="action-btn btn-accept" onclick="handleAction(${reg.registration_id}, 'accept')">Accept</button>
                                   <button class="action-btn btn-kick" onclick="handleAction(${reg.registration_id}, 'reject')">Reject</button>`;
                    } else if (reg.status === 'accepted') {
                        actions = `<button class="action-btn btn-kick" onclick="handleAction(${reg.registration_id}, 'reject')">Kick</button>`;
                    } else if (reg.status === 'rejected') {
                        actions = `<button class="action-btn btn-accept" onclick="handleAction(${reg.registration_id}, 'accept')">Re-Accept</button>`;
                    }

                    html += `
                        <div class="registration-item" id="reg-item-${reg.registration_id}">
                            <div>
                                <strong>${reg.team_name || reg.user_name}</strong> 
                                <span class="${statusClass}">(${reg.status.toUpperCase()})</span>
                                <div style="font-size:12px; color:#aaa;">ID Pendaftar: ${reg.registration_id} | Tanggal: ${reg.reg_date}</div>
                            </div>
                            <div>
                                ${actions}
                            </div>
                        </div>
                    `;
                });
            }
            document.getElementById('registrationsContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('registrationsContent').innerHTML = `<p style="color:red;text-align:center;">Error memuat pendaftar: ${error.message}</p>`;
        });
}

function handleAction(registrationId, action) {
    if (!confirm(`Yakin ingin ${action === 'accept' ? 'menerima' : 'mengeluarkan'} pendaftar ID ${registrationId}?`)) return;

    fetch('handle_registration_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `registration_id=${registrationId}&action=${action}&eo_id=${eoId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            const activeStatus = document.querySelector('.modal-tab-btn.active').dataset.status;
            loadRegistrations(activeStatus);
        } else alert(`Gagal: ${data.message}`);
    })
    .catch(error => { alert('Terjadi kesalahan koneksi.'); console.error(error); });
}

document.addEventListener('DOMContentLoaded', loadTournaments);

// --- Dropdown logout ---
const eoNameDropdown = document.getElementById('eoNameDropdown');
const dropdownMenu = document.getElementById('dropdownMenu');
const logoutBtn = document.getElementById('logoutBtn');

eoNameDropdown.addEventListener('click', () => {
    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
});

logoutBtn.addEventListener('click', () => {
    if (confirm('Yakin ingin keluar?')) {
        window.location.href = 'logout_event.php';
    }
});

window.addEventListener('click', function(e) {
    if (!eoNameDropdown.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.style.display = 'none';
    }
});
</script>

</body>
</html>
