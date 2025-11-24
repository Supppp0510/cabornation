<?php
session_start();
ob_start();
include("config.php"); // sesuaikan path jika perlu

// Utility: response JSON
function json_res($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// AJAX: get tournament details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_tournament') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) json_res(['success' => false, 'message' => 'id invalid']);

    $stmt = $conn->prepare("SELECT id,title,location,description,prize_pool FROM tournaments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $d = $res->fetch_assoc() ?: null;
    $stmt->close();

    if (!$d) json_res(['success' => false, 'message' => 'Turnamen tidak ditemukan']);
    $d['prize_pool'] = formatRupiah($d['prize_pool']);
    json_res(['success' => true, 'tournament' => $d]);
}

// AJAX: register team
// AJAX: register team (debuggable)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'register_team' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tournament_id = intval($_POST['tournament_id'] ?? 0);
    $team_name     = trim($_POST['team_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $user_email    = $_SESSION['user_email'] ?? ($_POST['user_email'] ?? null);

    if (!$user_email) json_res(['success' => false, 'message' => 'User tidak terautentikasi']);
    if ($tournament_id <= 0 || $team_name === '' || $contact_phone === '') {
        json_res(['success' => false, 'message' => 'Data tidak lengkap']);
    }

    // Ambil team_id dari teams
    $q = $conn->prepare("SELECT id FROM teams WHERE team_name = ? LIMIT 1");
    if (!$q) json_res(['success' => false, 'message' => 'Prepare (select team) failed: ' . $conn->error]);
    $q->bind_param("s", $team_name);
    if (!$q->execute()) {
        $err = $q->error;
        $q->close();
        json_res(['success' => false, 'message' => 'Execute (select team) failed: ' . $err]);
    }
    $qr = $q->get_result();
    $tid = 0;
    if ($r = $qr->fetch_assoc()) $tid = (int)$r['id'];
    $q->close();

    if ($tid <= 0) json_res(['success' => false, 'message' => 'Tim tidak ditemukan. Pastikan team_name di tabel `teams` sesuai.']);

    // Cek sudah terdaftar
    $c = $conn->prepare("SELECT id FROM tournament_registrations WHERE tournament_id = ? AND team_id = ?");
    if (!$c) json_res(['success' => false, 'message' => 'Prepare (check exists) failed: ' . $conn->error]);
    $c->bind_param("ii", $tournament_id, $tid);
    if (!$c->execute()) {
        $err = $c->error;
        $c->close();
        json_res(['success' => false, 'message' => 'Execute (check exists) failed: ' . $err]);
    }
    $cr = $c->get_result();
    if ($cr->fetch_assoc()) { $c->close(); json_res(['success' => false, 'message' => 'Tim sudah terdaftar pada turnamen ini']); }
    $c->close();

    // Insert registrasi (cek prepare & execute)
    $ins = $conn->prepare("
        INSERT INTO tournament_registrations
        (tournament_id, team_id, registered_by, contact_phone, team_name, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    if (!$ins) json_res(['success' => false, 'message' => 'Prepare (insert) failed: ' . $conn->error]);

    $bindOk = $ins->bind_param("iisss", $tournament_id, $tid, $user_email, $contact_phone, $team_name,);
    if (!$bindOk) {
        $err = $ins->error;
        $ins->close();
        json_res(['success' => false, 'message' => 'bind_param failed: ' . $err]);
    }

    if ($ins->execute()) {
        $ins->close();
        json_res(['success' => true, 'message' => 'Pendaftaran berhasil']);
    } else {
        $err = $ins->error;
        $ins->close();
        // kembalikan error DB supaya kita tahu apa yang salah
        json_res(['success' => false, 'message' => 'Execute (insert) failed: ' . $err]);
    }
}



// ---------------- RENDER PAGE ----------------
$user_email = $_SESSION['user_email'] ?? null;
if (!isset($_SESSION['user_email'])) { header("Location: login.php"); exit; }
if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_error)) die('Database connection error.');

$tournaments = [];
$sql_error = '';
try {
    $sql = "SELECT id,title,sport,location,date_start,registration_fee,prize_pool,poster_url,description FROM tournaments WHERE status='Approved' ORDER BY date_start ASC";
    $res = $conn->query($sql);
    if ($res === false) throw new Exception($conn->error);
    while ($r = $res->fetch_assoc()) $tournaments[] = $r;
} catch (Exception $e) {
    $sql_error = "Gagal memuat data turnamen: " . $e->getMessage();
}

function formatRupiah($angka) {
    if (!is_numeric($angka)) return "Rp 0";
    return "Rp " . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Daftar Turnamen | CaborNation</title>
<link rel="stylesheet" href="../css/timsaya.css">
<link rel="stylesheet" href="../css/turnamen.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* layout dasar */
:not(pre){box-sizing:border-box}
body{font-family:Poppins,system-ui,Arial;background:#0f0f12;color:#eaeaea;margin:0}
.sidebar{width:220px;position:fixed;left:0;top:0;bottom:0;background:#111;padding:18px}
.logo{font-weight:700;color:#ffd700;margin-bottom:12px}
.menu{list-style:none;padding:0;margin:0}
.menu li{margin-bottom:8px}
.menu a{color:#ddd;text-decoration:none}
.content{margin-left:240px;padding:20px}
.notif{background:#f44336;color:white;padding:10px;border-radius:6px;margin-bottom:12px}

/* daftar turnamen */
.tournament-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-top:16px}
.tournament-card{
    background:#151515;
    width : 700px;
    border-radius:8px;
    overflow:hidden;
    box-shadow:0 6px 18px rgba(0,0,0,.5);
    transition:.18s
}

.tournament-card:hover{transform:translateY(-6px)}
.poster {
    width: 100%;
    max-height: 400px; /* batasi agar tidak terlalu tinggi */
     height: 500px; /* samakan semua tinggi */
    overflow: hidden;
    position: relative;
}

.poster img {
    width: 100%;
    height: auto;
    display: block;
    object-fit: cover;
}

.sport-badge{position:absolute;top:10px;right:10px;background:#FFD700;color:#000;padding:6px 10px;border-radius:6px;font-weight:700;font-size:12px}
.details{padding:12px}
.details h3{margin:0 0 6px 0;font-size:1.02rem;color:#fff}
.small-muted{color:#bbb;font-size:.88em}
.info-row{display:flex;gap:8px;margin-top:10px}
.info-item{flex:1;text-align:center;background:#0e0e0e;padding:8px;border-radius:6px}
.label{display:block;font-size:.78em;color:#aaa;margin-bottom:6px}
.value{font-weight:700}
.fee{color:#4CAF50}.prize{color:#FFD700}
.btn-detail{display:inline-block;width:100%;padding:9px;background:#1976D2;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;margin-top:10px;text-align:center}
.btn-detail:hover{background:#1565C0}

/* popup overlay */
.popup-modal{
    display:none;position:fixed;z-index:1200;left:0;top:0;width:100%;height:100%;
    background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:18px;
}

/* popup box (no scrollbar on whole modal) */
.popup-content{
    background:#161616;padding:20px;border:1px solid #FFD700;
    width:92%;max-width:640px;border-radius:12px;color:#eee;
    max-height:90vh;overflow:hidden;position:relative;
    box-shadow:0 6px 28px rgba(0,0,0,.6);
}

/* close button in container */
.close-btn{
    position:absolute;right:16px;top:10px;font-size:22px;cursor:pointer;color:#ddd;background:transparent;border:0;
}

/* only description is scrollable */
.modal-description{max-height:220px;overflow-y:auto;padding:10px;border:1px solid #333;border-radius:8px;background:#0f0f0f;margin-top:6px;margin-bottom:10px}

/* compact form spacing */
.form-row{display:flex;gap:10px;flex-wrap:wrap}
.form-col{flex:1;min-width:180px}
.field-group{margin-bottom:10px}
label.input-label{display:block;margin-bottom:6px;color:#FFD700;font-weight:600;font-size:0.95em}
select,input[type="tel"]{width:100%;padding:9px;border-radius:6px;border:1px solid #333;background:#0f0f0f;color:#eee;font-size:0.98em}
.btn-register{
    display : block;
    margin: 0 auto;
    background:#FFD700;
    color:#000;
    weight: 200px;
    font-weight:700;
    padding:28px 84px;
    justify-content : center;
    border-radius:8px;
    border:0;
    cursor:pointer}
.error-msg{color:#ff6d6d;margin-top:8px;font-weight:700;min-height:20px}

/* responsive tweaks */
@media (max-width:480px){
    .popup-content{padding:16px}
    .details h3{font-size:1rem}
}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="logo">CaborNation</div>
    <ul class="menu">
        <li><a href="../php/dashboard.php">Beranda</a></li>
        <li><a href="../php/timsaya.php">Tim Saya</a></li>
        <li class="active"><a href="#">Turnamen</a></li>
        <li><a href="../php/editprofil.php">Profil</a></li>
        <li><a href="#" onclick="confirmLogout(event)">Keluar</a></li>
    </ul>
</aside>

<div class="content">
    <h2>Daftar Turnamen</h2>

    <?php if ($sql_error): ?>
        <div class="notif"><?= htmlspecialchars($sql_error) ?></div>
    <?php endif; ?>

    <?php if (empty($tournaments)): ?>
        <div class="no-events"><h3>Belum ada event turnamen. 😔</h3><p>Nantikan pengumuman berikutnya!</p></div>
    <?php else: ?>
        <div class="tournament-list">
            <?php foreach ($tournaments as $event):
                $poster_db_name = htmlspecialchars($event['poster_url']);
                $default_poster = '../assets/default_poster.jpg';
                $poster = !empty($poster_db_name) ? "../uploads/{$poster_db_name}" : $default_poster;
                $fee = $event['registration_fee'] > 0 ? formatRupiah($event['registration_fee']) : 'Gratis';
                $prize = $event['prize_pool'] > 0 ? formatRupiah($event['prize_pool']) : 'Disesuaikan';
            ?>
            <div class="tournament-card">
                <div class="poster" style="background-image:url('<?= $poster ?>')">
                    <span class="sport-badge"><?= htmlspecialchars($event['sport']) ?></span>
                </div>
                <div class="details">
                    <h3><?= htmlspecialchars($event['title']) ?></h3>
                    <div class="small-muted">Lokasi: <strong><?= htmlspecialchars($event['location']) ?></strong></div>
                    <div class="small-muted" style="margin-top:6px">Tanggal: <strong><?= date('d M Y', strtotime($event['date_start'])) ?></strong></div>
                    <div class="info-row">
                        <div class="info-item"><span class="label">Biaya</span><div class="value fee"><?= $fee ?></div></div>
                        <div class="info-item"><span class="label">Hadiah</span><div class="value prize"><?= $prize ?></div></div>
                    </div>
                    <button class="btn-detail" onclick="showDetail(<?= $event['id'] ?>)">Daftar & Detail</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- POPUP -->
<div id="detailPopup" class="popup-modal" aria-hidden="true">
    <div class="popup-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button class="close-btn" aria-label="Tutup" onclick="closePopup()">&times;</button>

        <h3 id="modalTitle" style="color:#FFD700;margin:4px 0 6px 0"></h3>
        <div id="modalLocation" class="small-muted"></div>
        <div id="modalPrize" class="small-muted" style="margin-bottom:8px"></div>

        <h4 style="color:#fff;margin:8px 0 6px 0">Deskripsi & Aturan:</h4>
        <div id="modalDescription" class="modal-description"></div>

        <h4 style="color:#fff;margin:8px 0 6px 0">Daftarkan Tim Anda:</h4>
        <div class="field-group">
            <label class="input-label">Pilih Tim</label>
            <select id="teamSelect" name="team_name">
                <option value="">-- Pilih Tim --</option>
                <?php
                $q = $conn->query("SELECT DISTINCT team_name FROM teams ORDER BY team_name ASC");
                if ($q) {
                    while ($r = $q->fetch_assoc()) {
                        $tn = htmlspecialchars($r['team_name']);
                        echo "<option value=\"$tn\">$tn</option>";
                    }
                }
                ?>
            </select>
        </div>

        <div class="field-group">
            <label class="input-label">Nomor Telepon Kontak</label>
            <input type="tel" id="contactPhone" name="contact_phone" placeholder="Cth: 0812xxxxxxx">
        </div>

        <div style="display:flex;gap:10px;align-items:center">
            <form id="registrationForm" style="flex:1">
                <input type="hidden" name="tournament_id" id="regTournamentId">
                <input type="hidden" name="user_email" value="<?= htmlspecialchars($user_email) ?>">
                <button type="submit" class="btn-register">Daftar Sekarang</button>
            </form>
            <div id="regMessage" class="error-msg" style="min-width:200px;color:transparent"></div>
        </div>
    </div>
</div>

<script>
    document.getElementById('detailPopup').style.display = 'none';
/* Helpers */
function confirmLogout(e){ e.preventDefault(); if (confirm("Yakin ingin keluar?")) location.href = "logout.php"; }
function closePopup(){
    const modal = document.getElementById('detailPopup');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
    // reset small states
    document.getElementById('regMessage').textContent = '';
    document.getElementById('regMessage').style.color = 'transparent';
    document.getElementById('teamSelect').value = '';
    document.getElementById('contactPhone').value = '';
}
async function fetchJson(url, opts){ const r = await fetch(url, opts); return r.json(); }

/* Show detail & open popup */
async function showDetail(tournamentId){
    document.getElementById('regTournamentId').value = tournamentId;
    document.getElementById('modalTitle').textContent = 'Memuat...';
    document.getElementById('modalDescription').innerHTML = '';
    document.getElementById('modalLocation').textContent = '';
    document.getElementById('modalPrize').textContent = '';
    document.getElementById('detailPopup').style.display = 'flex';
    document.getElementById('detailPopup').setAttribute('aria-hidden','false');

    try {
        const d = await fetchJson('?ajax=get_tournament&id=' + tournamentId);
        if (d.success) {
            const t = d.tournament;
            document.getElementById('modalTitle').textContent = t.title;
            document.getElementById('modalDescription').innerHTML = t.description || '(Tidak ada deskripsi)';
            document.getElementById('modalLocation').textContent = 'Lokasi: ' + (t.location || '-');
            document.getElementById('modalPrize').textContent = 'Total Hadiah: ' + (t.prize_pool || '-');
        } else {
            document.getElementById('modalTitle').textContent = 'Detail tidak ditemukan';
        }
    } catch (err) {
        document.getElementById('modalTitle').textContent = 'Gagal memuat detail';
    }
}

/* Register form submit
   B: close popup automatically after success (1.2s)
*/
document.getElementById('registrationForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = this.querySelector('.btn-register');
    btn.disabled = true;

    const selectedTeamName = document.getElementById('teamSelect').value.trim();
    const contactPhone = document.getElementById('contactPhone').value.trim();
    const msg = document.getElementById('regMessage');

    if (!selectedTeamName) {
        msg.style.color = 'salmon';
        msg.textContent = 'Harap pilih tim terlebih dahulu.';
        btn.disabled = false;
        return;
    }
    if (!contactPhone) {
        msg.style.color = 'salmon';
        msg.textContent = 'Harap isi nomor telepon kontak.';
        btn.disabled = false;
        return;
    }

    const formData = new FormData(this);
    formData.append('team_name', selectedTeamName);
    formData.append('contact_phone', contactPhone);

    try {
        const res = await fetch('?ajax=register_team', { method: 'POST', body: formData });
        const j = await res.json();
        if (j.success) {
            msg.style.color = 'lightgreen';
            msg.textContent = j.message;
            // tutup popup otomatis (pilihan B)
            setTimeout(() => { closePopup(); }, 1200);
        } else {
            msg.style.color = 'salmon';
            msg.textContent = j.message;
        }
    } catch (err) {
        msg.style.color = 'salmon';
        msg.textContent = 'Terjadi kesalahan saat pendaftaran (Server Error).';
    }
    btn.disabled = false;
});

/* Close modal on outside click */
document.addEventListener('click', function(e){
    const modal = document.getElementById('detailPopup');
    if (modal.style.display === 'flex' && e.target === modal) closePopup();
});

/* allow Esc to close */
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        const modal = document.getElementById('detailPopup');
        if (modal.style.display === 'flex') closePopup();
    }
});
</script>
</body>
</html>
