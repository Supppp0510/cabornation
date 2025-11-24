<?php
session_start();
ob_start();
// Pastikan file config.php ada dan berisi koneksi $conn
include("config.php");

// Error handling koneksi
if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_error)) {
    die('Database connection error: ' . ($conn instanceof mysqli ? $conn->connect_error : 'Koneksi tidak terdefinisi.'));
}

// Gunakan email sesi atau default
$user_email = $_SESSION['user_email'] ?? 'test@example.com';

// =================================================================
// 1. LOGIC MENYIMPAN PLAYER DENGAN WARNA (AJAX)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && isset($_POST['is_player_ajax'])) {
    // Kosongkan dan mulai output buffering untuk JSON
    ob_end_clean();
    ob_start();
    header('Content-Type: application/json');

    $team_id = (int)($_POST['team_id'] ?? 0);
    $action_type = $_POST['action_type'] ?? 'add';
    $player_id = $_POST['player_id'] ?? null;
    $color_class = $_POST['color_class'] ?? null; 

    if (empty($team_id)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Team ID is required. Please save the team first.']);
        exit;
    }

    $nama = trim($_POST['player_name'] ?? '');
    $nomor_punggung = (int)($_POST['player_number'] ?? 0);
    $posisi = trim($_POST['player_position'] ?? '');
    $tinggi_badan = (int)($_POST['player_height'] ?? 0);
    $berat_badan = (int)($_POST['player_weight'] ?? 0);
    $umur = (int)($_POST['player_age'] ?? 0);
    $current_foto_path = $_POST['current_foto_path'] ?? null; 

    $foto_path_for_db = $current_foto_path ?: null; 

    if (!empty($_FILES['player_foto']['name'])) {
        $target_dir = __DIR__ . "/../uploads/players/"; 
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $original_name = basename($_FILES['player_foto']['name']);
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $allowed = ['jpg','jpeg','png','gif'];
        if (!in_array($file_ext, $allowed)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File type not supported.']);
            exit;
        }

        $file_name = uniqid('player_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['player_foto']['tmp_name'], $target_file)) {
            // Path relatif dari folder root web (asumsi timsaya.php ada di /php/)
            $foto_path_for_db = 'uploads/players/' . $file_name; 
        } else {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to upload photo. Check folder permissions (0777).']);
            exit;
        }
    }

    $success = false;
    $new_player_id = null;
    $sql_error = '';

    if ($action_type === 'edit' && $player_id && $player_id !== 'new') {
        // Update data pemain, termasuk color_class
        $sql = "UPDATE players SET team_id = ?, nama = ?, nomor_punggung = ?, posisi = ?, tinggi_badan = ?, berat_badan = ?, umur = ?, foto_path = ?, color_class = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             $sql_error = $conn->error;
        } else {
            // Tipe data: i (team_id), s (nama), i (nomor), s (posisi), i (tinggi), i (berat), i (umur), s (foto_path), s (color_class), i (player_id)
            $stmt->bind_param("isisiiissi", $team_id, $nama, $nomor_punggung, $posisi, $tinggi_badan, $berat_badan, $umur, $foto_path_for_db, $color_class, $player_id);
            $success = $stmt->execute();
            $new_player_id = $player_id;
            if (!$success) $sql_error = $stmt->error;
            $stmt->close();
        }

    } else {
        // Insert data pemain, termasuk color_class
        $sql = "INSERT INTO players (team_id, nama, nomor_punggung, posisi, tinggi_badan, berat_badan, umur, foto_path, color_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             $sql_error = $conn->error;
        } else {
             // Tipe data: i (team_id), s (nama), i (nomor), s (posisi), i (tinggi), i (berat), i (umur), s (foto_path), s (color_class)
            $stmt->bind_param("isisiiiss", $team_id, $nama, $nomor_punggung, $posisi, $tinggi_badan, $berat_badan, $umur, $foto_path_for_db, $color_class);
            $success = $stmt->execute();
            $new_player_id = $stmt->insert_id ?: $conn->insert_id;
            if (!$success) $sql_error = $stmt->error;
            $stmt->close();
        }
    }

    if ($success) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Player saved successfully!',
            'player_id' => $new_player_id,
            // Kembalikan path yang dapat diakses di klien (misal: '../uploads/players/...')
            'foto_path' => $foto_path_for_db ? ('../' . $foto_path_for_db) : '../assets/profil.png',
            'color_class' => $color_class 
        ]);
    } else {
        $output = ob_get_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $sql_error . ' | Buffer output: ' . $output]);
    }
    exit;
}

// =================================================================
// 2. LOGIC MENYIMPAN FORMASI (AJAX)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_formation']) && isset($_POST['team_id'])) {
    ob_end_clean();
    ob_start();
    header('Content-Type: application/json');

    $team_id = (int)($_POST['team_id'] ?? 0);
    $formation_data = json_decode($_POST['formation_data'] ?? '[]', true);

    if (empty($team_id) || empty($formation_data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data formasi atau Team ID tidak valid.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Hapus semua formasi lama untuk tim ini
        $stmt = $conn->prepare("DELETE FROM formations WHERE team_id = ?");
        
        if ($stmt === false) throw new Exception("Prepare DELETE failed: " . $conn->error);
        
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $stmt->close();

        // 2. Insert formasi baru
        // kolom pos_x dan pos_y harus bertipe DECIMAL/FLOAT di DB
        $sql = "INSERT INTO formations (team_id, player_id, pos_x, pos_y) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) throw new Exception("Prepare INSERT failed: " . $conn->error);

        foreach ($formation_data as $data) {
            $player_id = (int)$data['id'];
            // Pastikan data x dan y adalah float/decimal
            $pos_x = (float)$data['x'];
            $pos_y = (float)$data['y'];

            // Bind dan execute (i: int, i: int, d: double/float, d: double/float)
            $stmt->bind_param("iidd", $team_id, $player_id, $pos_x, $pos_y);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Formasi berhasil disimpan!']);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan formasi: ' . $e->getMessage()]);
    } finally {
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
             $stmt->close();
        }
        exit;
    }
}


// =================================================================
// 3. LOGIC UTAMA (LOAD DATA TIM & PLAYER)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_team'])) {
    $team_name = $_POST['team_name'] ?? '';
    $sport = $_POST['sport'] ?? '';
    $instansi = $_POST['instansi'] ?? '';
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $logo_name = null;
    $existing_logo = $_POST['existing_logo'] ?? null;

    if (!empty($_FILES['logo']['name'])) {
        $target_dir = __DIR__ . "/../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $original = basename($_FILES['logo']['name']);
        $file_name = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowed)) {
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                $logo_name = $file_name;
            }
        }
    }
    
    if ($logo_name === null && $existing_logo) {
        $logo_name = $existing_logo;
    }

    $check = $conn->prepare("SELECT id FROM teams WHERE user_email = ?");
    
    if ($check === false) die('SQL Prepare Error (Teams Check): ' . $conn->error);

    $check->bind_param("s", $user_email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        if ($logo_name !== null) {
            $stmt = $conn->prepare("UPDATE teams SET team_name=?, sport=?, instansi=?, jumlah_pemain=?, logo=? WHERE user_email=?");
            if ($stmt === false) die('SQL Prepare Error (Teams Update 1): ' . $conn->error);
            $stmt->bind_param("sssiss", $team_name, $sport, $instansi, $jumlah, $logo_name, $user_email);
        } else {
            $stmt = $conn->prepare("UPDATE teams SET team_name=?, sport=?, instansi=?, jumlah_pemain=? WHERE user_email=?");
            if ($stmt === false) die('SQL Prepare Error (Teams Update 2): ' . $conn->error);
            $stmt->bind_param("sssis", $team_name, $sport, $instansi, $jumlah, $user_email);
        }

        if ($stmt->execute()) {
            $_SESSION['notif'] = "Tim berhasil diperbarui!";
        } else {
            $_SESSION['notif'] = "Gagal memperbarui tim: " . $stmt->error;
        }
        if (isset($stmt)) $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO teams (user_email, team_name, sport, instansi, jumlah_pemain, logo) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt === false) die('SQL Prepare Error (Teams Insert): ' . $conn->error);
        $stmt->bind_param("ssssis", $user_email, $team_name, $sport, $instansi, $jumlah, $logo_name);
        if ($stmt->execute()) {
            $_SESSION['notif'] = "Tim berhasil disimpan!";
        } else {
            $_SESSION['notif'] = "Gagal menyimpan tim: " . $stmt->error;
        }
        $stmt->close();
    }

    header("Location: timsaya.php");
    exit;
}

// Ambil data tim (jika ada)
$stmt = $conn->prepare("SELECT id, team_name, sport, instansi, jumlah_pemain, logo FROM teams WHERE user_email = ?");

if ($stmt === false) {
    die('SQL Prepare Error (Teams Check 2): ' . $conn->error);
}

$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$team = $result->fetch_assoc();
$isEdit = $team ? true : false;
$stmt->close();

$team_id = $team['id'] ?? null;
$current_logo_path = !empty($team['logo']) ? '../uploads/' . htmlspecialchars($team['logo']) : '../assets/profil.png';

$notif = $_SESSION['notif'] ?? '';
unset($_SESSION['notif']);

$players = [];
$loaded_formation = []; // Data formasi yang dimuat

if ($team_id) {
    // Ambil data pemain
    $stmt_players = $conn->prepare("SELECT id, nama, nomor_punggung, posisi, tinggi_badan, berat_badan, umur, foto_path, color_class FROM players WHERE team_id = ? ORDER BY id ASC");
    if ($stmt_players === false) die('SQL Prepare Error (Players Select): ' . $conn->error);
    
    $stmt_players->bind_param("i", $team_id);
    $stmt_players->execute();
    $result_players = $stmt_players->get_result();

    while ($row = $result_players->fetch_assoc()) {
        $players[] = [
            'id' => $row['id'],
            'name' => $row['nama'],
            'number' => $row['nomor_punggung'],
            'position' => $row['posisi'],
            'height' => $row['tinggi_badan'],
            'weight' => $row['berat_badan'],
            'age' => $row['umur'],
            'foto' => $row['foto_path'] ? ('../' . $row['foto_path']) : '../assets/profil.png',
            'color_class' => $row['color_class'] ?? 'color-red' 
        ];
    }
    $stmt_players->close();
    
    // Load formasi yang sudah tersimpan
    $stmt_formation = $conn->prepare("SELECT player_id, pos_x, pos_y FROM formations WHERE team_id = ?");
    if ($stmt_formation === false) die('SQL Prepare Error (Formation Select): ' . $conn->error);
    
    $stmt_formation->bind_param("i", $team_id);
    $stmt_formation->execute();
    $result_formation = $stmt_formation->get_result();

    while ($row = $result_formation->fetch_assoc()) {
        $loaded_formation[] = [
            'id' => $row['player_id'],
            'x' => $row['pos_x'], // Nilai persentase float dari DB
            'y' => $row['pos_y']  // Nilai persentase float dari DB
        ];
    }
    $stmt_formation->close();
}

// Kosongkan output buffer sebelum mengirim HTML
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tim Saya | CaborNation</title>
    <link rel="stylesheet" href="../css/timsaya.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Oswald:wght@700&display=swap" rel="stylesheet">
    <style>
        /* CSS INLINE DARI KODE ASLI */
        .readonly input, .readonly select {
            background: #f1f1f1;
            color : #000;
            cursor: default;
            border: 1px solid #ccc;
        }
        .notif {
            background: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        /* --- PLAYER CARD STYLES (OVERRIDE DENGAN WARNA DINAMIS DAN 3 KOLOM) --- */
        .player-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 5px; /* Jarak antar kartu */
            justify-content: flex-start;
            margin-top: 30px;
        }

        /* Mengatur Grid Layout 3 Kartu Lebar (DEFAULT) */
        .player-card {
            position: relative;
            /* Kalkulasi lebar: (100% - 2 * gap) / 3 */
            width: calc((100% - 2 * 25px) / 3); 
            min-width: 210px;
            height: 300px; 
            background-color: #0d0d0d;
            color: #ffffff;
            border: 2px solid #333;
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.7);
            transition: transform 0.2s, box-shadow 0.2s;
            font-family: 'Poppins', sans-serif;
            text-transform: uppercase;
        }

        /* Responsive Override untuk 5/4/2/1 kolom */
        @media (min-width: 1600px) {
            .player-card {
                width: calc((100% - 4 * 25px) / 5);
                height: 350px; 
            }
        }
        @media (min-width: 1200px) and (max-width: 1599px) {
            .player-card {
                width: calc((100% - 3 * 25px) / 4);
                height: 370px;
            }
        }
        @media (max-width: 768px) {
            .player-card {
                width: calc((100% - 1 * 25px) / 2);
            }
        }
        @media (max-width: 480px) {
            .player-card {
                width: 100%;
            }
        }


        .player-card:hover {
            transform: translateY(-3px);
        }

        /* --- WARNA DINAMIS BERDASARKAN POSISI & PRESET WARNA --- */
        /* Base variables for player card and formation card */
        .player-card, .formation-card { 
            --accent-color: #555555; 
            --secondary-color: #FFFFFF;
        }
        /* Preset Warna */
        .player-card.color-red, .formation-card.color-red { --accent-color: #D32F2F; }
        .player-card.color-blue, .formation-card.color-blue { --accent-color: #1976D2; }
        .player-card.color-gold, .formation-card.color-gold { --accent-color: #FFC107; }
        .player-card.color-green, .formation-card.color-green { --accent-color: #388E3C; }
        .player-card.color-purple, .formation-card.color-purple { --accent-color: #7B1FA2; }
        
        /* Kartu Formasi: Hapus garis putih */
        .formation-card .player-card-outer-frame {
            border-color: transparent !important;
        }
        
        /* Warna Aksen Diterapkan */
        .player-card:not(.placeholder) .player-card-outer-frame,
        .formation-card .player-card-outer-frame { 
            border-color: var(--accent-color);
        }
        .player-card:not(.placeholder) .team-name-section,
        .formation-card .team-name-section { 
            background-color: var(--accent-color);
            color: #ffffff;
        }
        .player-card:not(.placeholder) .photo-frame,
        .formation-card .photo-frame { 
            border-color: var(--accent-color);
        }
        .player-card:not(.placeholder) .player-name-bg,
        .formation-card .player-name-bg { 
            background-color: var(--accent-color);
            color: #ffffff;
        }
        .player-card:not(.placeholder) .player-number,
        .formation-card .player-number { 
            color: var(--accent-color);
        }
        .player-card:not(.placeholder) .deco-line,
        .formation-card .deco-line { 
            color: var(--accent-color);
        }
        
        .player-card .player-position-box,
        .formation-card .player-position-box { 
            background-color: var(--secondary-color); 
            color: #000;
        }
        /* REVISI: Mengurangi blur radius drop-shadow untuk mengurangi glow */
        .player-card:hover {
            filter: drop-shadow(0 4px 10px var(--accent-color)); 
            transform: translateY(-3px);
        }
        .formation-card:hover { 
            box-shadow: 0 0 15px var(--accent-color);
        }
        
        /* Placeholder Styling (Hitam Putih/Default) */
        .player-card.placeholder {
            border-color: #555555; 
        }
        .player-card.placeholder .team-name-section,
        .player-card.placeholder .player-name-bg {
            background-color: #555555 !important;
        }
        .player-card.placeholder .player-number,
        .player-card.placeholder .photo-frame,
        .player-card.placeholder .player-card-outer-frame,
        .player-card.placeholder .deco-line {
            color: #555555 !important;
            border-color: #555555 !important;
        }
        /* --- AKHIR WARNA DINAMIS --- */


        /* Bingkai Luar, Dekorasi Diagonal, Konten Area */
        .player-card-outer-frame {
            border-width: 4px;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 5;
            pointer-events: none;
            box-sizing: border-box;
        }
        
        .player-card .deco-line,
        .formation-card .deco-line { 
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 4;
            font-size: 10px;
            font-weight: 900;
            opacity: 0.8;
            white-space: nowrap;
            letter-spacing: 2px;
        }
        
        .player-card::before, .formation-card::before { 
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            width: 110%;
            height: 100px;
            background: rgba(255, 0, 0, 0.1); 
            transform: rotate(-5deg);
            transform-origin: top left;
            z-index: 1;
            opacity: 0.8;
        }

        .player-card::after, .formation-card::after { 
            content: '';
            position: absolute;
            bottom: -10px;
            right: -10px;
            width: 110%;
            height: 100px;
            background: rgba(255, 0, 0, 0.1);
            transform: rotate(5deg);
            transform-origin: bottom right;
            z-index: 1;
            opacity: 0.8;
        }


        .player-card .content-area,
        .formation-card .content-area { 
            position: relative;
            z-index: 3;
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 10px;
            box-sizing: border-box;
            justify-content: space-between;
        }

        .player-card .team-name-section,
        .formation-card .team-name-section { 
            padding: 6px 10px;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            margin: 0 auto 10px auto;
            letter-spacing: 1px;
            position: relative;
            z-index: 4;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            width: calc(100% - 20px);
        }

        .player-card .photo-frame,
        .formation-card .photo-frame { 
            border-width: 4px;
            width: calc(100% - 20px); 
            height: 180px; 
            margin: 0 auto 10px auto;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
            z-index: 4;
            background-color: #1a1a1a;
            border-radius: 0;
        }
        
        .player-card .photo-frame img,
        .formation-card .photo-frame img { 
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 0;
        }

        .player-card .player-info-bottom,
        .formation-card .player-info-bottom { 
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            z-index: 4;
            padding: 5px 0 25px 0;
        }

        .player-card .player-details,
        .formation-card .player-details { 
            text-align: left;
            flex-grow: 1;
            margin-right: 10px;
            display: flex;
            flex-direction: column;
        }

        .player-card .player-name-bg,
        .formation-card .player-name-bg { 
            padding: 4px 8px;
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 5px;
            letter-spacing: 1.2px;
            line-height: 1.2;
            display: inline-block;
            box-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }

        .player-card .player-position-box,
        .formation-card .player-position-box { 
            padding: 2px 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.6px;
            margin-top: 5px;
            box-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }

        .player-card .player-number,
        .formation-card .player-number { 
            font-family: 'Oswald', sans-serif;
            font-size: 36px;
            font-weight: 900;
            line-height: 1;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.8);
            position: absolute;
            bottom: 10px;
            right: 10px;
            z-index: 4;
        }
        
        /* Placeholder card specific styles */
        .player-card.placeholder {
            border-color: #555555; 
        }
        .player-card.placeholder .team-name-section,
        .player-card.placeholder .player-name-bg {
            background-color: #555555 !important;
        }
        .player-card.placeholder .player-number,
        .player-card.placeholder .photo-frame,
        .player-card.placeholder .player-card-outer-frame,
        .player-card.placeholder .deco-line {
            color: #555555 !important;
            border-color: #555555 !important;
        }
        /* --- AKHIR WARNA DINAMIS --- */


        /* Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6); 
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #1c1c1cff; 
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 600px;
            position: relative;
        }

        /* REVISI: Ukuran Modal Formasi yang lebih Sesuai */
        #formationModal .modal-content {
            max-width: 720px; /* Lebar maksimum */
            max-height: 700px; /* Tinggi maksimum lebih kecil */
            width: 95%; 
            margin: 30px auto; 
            overflow-y: auto; /* Tambahkan scroll jika konten terlalu banyak */
        }

        .modal-content h3 {
            color: #FFD700; /* Warna kuning untuk judul modal */
            margin-bottom: 25px;
            text-align: center;
            font-family: 'Poppins', sans-serif;
        }

        .modal-content .close-btn {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 25px;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .modal-content .close-btn:hover,
        .modal-content .close-btn:focus {
            color: #ffffffff;
            text-decoration: none;
        }

        .modal-content .form-group label {
            font-size: 15px;
            color: #EEEEEE; /* REVISI: Mengurangi kecerahan putih */
            font-weight: 600;
        }

        .modal-content input[type="text"],
        .modal-content input[type="number"],
        .modal-content select {
            width: calc(100% - 22px);
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .player-photo-preview {
            display: block;
            width: 120px;
            height: 120px;
            border-radius: 0;
            object-fit: cover;
            margin: 15px auto 10px auto; 
            border: 3px solid #e3b600ff;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }

        /* Styling untuk input file */
        .custom-file-upload {
            border: 2px solid #FFD700; /* Border kuning */
            display: block; /* Diubah menjadi block untuk margin auto */
            padding: 8px 12px;
            cursor: pointer;
            color : #000;
            background-color: #FFD700; /* Latar belakang kuning */
            border-radius: 5px;
            transition: background-color 0.3s, border-color 0.3s;
            margin: 5px auto 0 auto; /* Margin auto untuk centering */
            text-align: center;
            width: 40%; /* Lebar dipersingkat */
            box-sizing: border-box;
            font-weight: 600;
        }

        .custom-file-upload:hover {
            background-color: #E0E0E0; /* REVISI: Mengurangi kecerahan putih saat hover */
            border-color: #E0E0E0;
            color : #000;
        }

        .form-group input[type="file"] {
            display: none;
        }
        
        /* Judul "Daftar Pemain" di tengah */
        #pemainSection h3 {
            text-align: center;
            margin-bottom: 20px;
            color: #ffffffff;
            font-size: 1.5em;
        }

        /* --- REVISI UTAMA: LAPANGAN FUTSAL DENGAN GARIS & PILIHAN WARNA --- */
        #formationMappingArea {
            /* Ukuran terbatas dan centered */
            max-width: 600px; 
            width: 80%;
            margin: 0 auto; /* Centering */
            min-height: 650px; 
            background-color: var(--field-color, #008000); /* Default hijau, bisa diubah */
            border: 4px solid #FFFFFF; 
            border-radius: 0; 
            padding: 0;
            position: relative; 
            overflow: hidden; 
            box-sizing: border-box;
            box-shadow: none; 
        }
        
        /* Gawang */
        .goal {
            position: absolute;
            height: 10px; /* Tinggi gawang (tipis) */
            width: 30%; /* Lebar gawang relatif terhadap lapangan */
            background-color: #333; /* Warna gawang/tiang */
            border: 1px solid #fff;
            z-index: 5;
            left: 50%;
            transform: translateX(-50%);
        }
        .goal.top {
            top: 0;
        }
        .goal.bottom {
            top: auto;
            bottom: 0;
        }

        /* Garis Tengah (Horizontal) */
        #formationMappingArea::before {
            content: '';
            position: absolute;
            top: 50%; /* Posisikan di tengah vertikal */
            left: 0;
            width: 100%; /* Lebar penuh */
            height: 2px; /* Tebal garis */
            background-color: #FFFFFF;
            transform: translateY(-50%);
            z-index: 1;
        }

        /* Lingkaran Tengah */
        #formationMappingArea::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100px; /* Diameter */
            height: 100px;
            border: 2px solid #FFFFFF;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
        }
        
        /* Area Penalti Sisi Atas (garis 6m) */
        /* Menggunakan child ke-3 (#fieldPlayers) sebagai anchor untuk pseudo-element */
        #formationMappingArea #fieldPlayers::before { 
            content: '';
            position: absolute;
            top: 60px; /* Jarak dari atas (Garis 6m) */
            left: 50%;
            width: 60%; /* Lebar kotak penalti */
            height: 2px;
            background-color: #FFFFFF;
            transform: translate(-50%, -50%);
            z-index: 1;
        }

        /* Area Penalti Sisi Bawah (garis 6m) */
        #formationMappingArea #fieldPlayers::after { 
            content: '';
            position: absolute;
            bottom: 60px; /* Jarak dari bawah (Garis 6m) */
            left: 50%;
            width: 60%; 
            height: 2px;
            background-color: #FFFFFF;
            transform: translate(-50%, -50%);
            z-index: 1;
        }


        #fieldPlayers {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        /* REVISI: FORMATION CARD CONTAINER STYLING */
        .formation-card {
            position: absolute; 
            width: 210px; /* Ambil lebar asli player card */
            height: 300px; /* Ambil tinggi asli player card */
            transform: scale(0.45); /* SKALA KARTU DI LAPANGAN */
            transform-origin: top left; /* Agar posisi relatif tetap benar */
            
            /* Style bawaan dari player-card class */
            background-color: #0d0d0d;
            color: #ffffff;
            border: 2px solid var(--accent-color, #FFD700);
            border-radius: 5px;
            overflow: hidden;
            cursor: grab; 
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.5); 
            transition: box-shadow 0.2s;
            text-transform: uppercase;
            text-align: center;
            padding: 0;
            z-index: 10;
        }
        
        .formation-card.dragging {
             opacity: 0.7;
             z-index: 20;
        }

        .formation-card:hover { 
            box-shadow: 0 0 15px var(--accent-color);
        }

        /* CHIP PEMAIN DI POOL */
        #availablePlayersList span {
            margin: 5px 5px 5px 0; 
            background-color: #444; 
            color: #fff; 
            padding: 5px 10px; 
            border-radius: 3px; 
            cursor: grab;
            font-size: 12px;
            text-transform: capitalize;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }

        /* Styling untuk color picker */
        #fieldColorPicker {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 40px;
            height: 40px;
            background-color: transparent;
            border: none;
            cursor: pointer;
            vertical-align: middle;
            margin-left: 10px;
        }
        #fieldColorPicker::-webkit-color-swatch {
            border-radius: 50%;
            border: 2px solid #fff;
        }
        #fieldColorPicker::-moz-color-swatch {
            border-radius: 50%;
            border: 2px solid #fff;
        }

    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo">CaborNation</div>
        <ul class="menu">
            <li><a href="../php/dashboard.php">Beranda</a></li>
            <li class="active"><a href="#">Tim Saya</a></li>
            <li><a href="#">Turnamen</a></li>
            <li><a href="../php/editprofil.php">Profil</a></li>
            <li><a href="#" onclick="confirmLogout(event)">Keluar</a></li>
        </ul>
    </aside>

    <div class="content">
        <?php if ($notif): ?>
            <div class="notif"><?= htmlspecialchars($notif) ?></div>
        <?php endif; ?>

        <h2>Tim Saya</h2>

        <form method="POST" id="teamForm" class="readonly" enctype="multipart/form-data">
            <input type="hidden" name="update_team" value="1">
            <div class="form-group">
                <label>Logo Tim</label>
                <div class="logo-container readonly" id="logoContainer">
                    <img id="logoPreview" src="<?= $current_logo_path ?>" alt="Logo Tim" class="logo-preview-img">
                    <div class="logo-overlay" id="logoOverlay">
                        Ubah Logo
                    </div>
                </div>
                <input type="file" name="logo" id="logoInput" accept="image/*" class="logo-input">
                <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($team['logo'] ?? '') ?>">
            </div>

            <div class="form-group">
                <p>Pastikan data sudah benar sebelum menambah pemain!</p>
                <label>Nama Tim</label>
                <input type="text" name="team_name" id="inputTeamName" value="<?= htmlspecialchars($team['team_name'] ?? '') ?>" required readonly>
            </div>

            <div class="form-group">
                <label>Cabang Olahraga</label>
                <select name="sport" id="selectSport" required disabled>
                    <option value="" disabled <?= !$isEdit ? 'selected' : '' ?>>Pilih cabang olahraga</option>
                    <?php
                    $sports = ["Futsal", "Basket", "Badminton", "Volly", "Atletik", "Catur", "Padel", "E-Sports"];
                    foreach ($sports as $s) {
                        $selected = ($isEdit && $team['sport'] == $s) ? 'selected' : '';
                        echo "<option value='$s' $selected>$s</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Asal Instansi</label>
                <input type="text" name="instansi" id="inputInstansi" value="<?= htmlspecialchars($team['instansi'] ?? '') ?>" readonly>
            </div>

            <div class="form-group">
                <label>Jumlah Pemain (termasuk cadangan)</label>
                <input type="number" name="jumlah" id="inputJumlah" value="<?= htmlspecialchars($team['jumlah_pemain'] ?? (count($players) > 0 ? count($players) : 5)) ?>" required readonly>
            </div>

            <button type="button" id="editBtn" class="btn-submit">
                <?= $isEdit ? 'Edit Tim' : 'Buat Tim' ?>
            </button>
        </form>

        <?php if ($isEdit): ?>
            <div style="margin-top: 1.5rem; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="showPlayersBtn" class="btn-submit" onclick="showPlayerSection(this)" style="display:none;">
                    Daftar Pemain
                </button>
                <button type="button" id="showFormationBtn" class="btn-submit" onclick="openFormationModal()" style="display:block;">
                    Atur Formasi
                </button>
            </div>
        <?php endif; ?>
        
        <div id="pemainSection" style="margin-top:2rem;">
            <h3>Daftar Pemain</h3>
            <div id="playerCards" class="player-cards">
            </div>
        </div>

    </div>
    
    <div id="playerModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Tambah Pemain Baru</h3>
            <form id="playerForm" enctype="multipart/form-data">
                <input type="hidden" name="player_id" id="playerIdInput" value="">
                <input type="hidden" name="action_type" id="actionType" value="add">
                <input type="hidden" name="current_foto_path" id="currentFotoPath" value="">

                <div class="form-group">
                    <label>Foto Pemain</label>
                    <img id="playerFotoPreview" src="../assets/profil.png" alt="Preview Foto" class="player-photo-preview">
                    
                    <label for="playerFotoInput" class="custom-file-upload" style="color: #000;">
                        Pilih File Foto
                    </label>
                    <input type="file" name="player_foto" id="playerFotoInput" accept="image/*">
                </div>
                <div class="form-group">
                    <label>Nama</label>
                    <input type="text" name="player_name" id="playerNameInput" required>
                </div>
                <div class="form-group">
                    <label>Nomor Punggung</label>
                    <input type="number" name="player_number" id="playerNumberInput" required>
                </div>
                <div class="form-group">
                    <label>Posisi</label>
                    <input type="text" name="player_position" id="playerPositionInput" placeholder="Contoh: Striker, Defender, Guard" required>
                </div>
                
                <div class="form-group">
                    <label>Warna Kartu</label>
                    <select name="color_class" id="playerColorInput" required>
                        <option value="color-red">Merah</option>
                        <option value="color-blue">Biru</option>
                        <option value="color-gold">Emas</option>
                        <option value="color-green">Hijau</option>
                        <option value="color-purple">Ungu</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tinggi Badan (cm)</label>
                    <input type="number" name="player_height" id="playerHeightInput" required>
                </div>
                <div class="form-group">
                    <label>Berat Badan (kg)</label>
                    <input type="number" name="player_weight" id="playerWeightInput" required>
                </div>
                <div class="form-group">
                    <label>Umur</label>
                    <input type="number" name="player_age" id="playerAgeInput" required>
                </div>

                <button type="submit" id="submitPlayerBtn" class="btn-submit">Simpan Pemain</button>
            </form>
        </div>
    </div>
    
    <div id="formationModal" class="modal">
        <div class="modal-content" id="formationModalContent">
            <span class="close-btn" onclick="closeFormationModal()">&times;</span>
            <h3 id="formationModalTitle">Pengaturan Formasi Pemain</h3>
            
            <div class="form-group" style="text-align: center; margin-bottom: 20px;">
                <label for="fieldColorPicker" style="color: #EEEEEE; display: inline-block; margin-right: 15px;">Warna Lapangan:</label>
                <input type="color" id="fieldColorPicker" value="#008000" onchange="changeFieldColor(this.value)">
                
                <label for="formationSelect" style="color: #FFD700; display: inline-block; margin-left: 20px; margin-right: 10px;">Formasi Cepat:</label>
                <select id="formationSelect" onchange="applyFormation(this.value)" style="width: 200px; padding: 5px; background: #333; color: white; border: 1px solid #FFD700;">
                    <option value="">-- Pilih Formasi --</option>
                    <option value="1-2-1">1-2-1 (Diamond)</option>
                    <option value="2-2">2-2 (Box)</option>
                    <option value="3-1">3-1 (Tandem)</option>
                </select>
            </div>
            
            <div id="formationMappingArea">
                <div class="goal top"></div> 
                <div class="goal bottom"></div>
                <div id="fieldPlayers">
                    </div>
            </div>
            
            <div id="playerPool" style="margin-top: 20px; border-top: 1px solid #333; padding-top: 15px;">
                <h4 style="color: #fff; margin-bottom: 10px; font-size: 16px;">Pemain Tersedia (Drag ke Lapangan):</h4>
                <div id="availablePlayersList" style="display: flex; flex-wrap: wrap; gap: 5px;">
                    </div>
            </div>

            <button type="button" id="saveFormationBtn" class="btn-submit" onclick="saveFormationToDB()">Simpan Formasi</button>
        </div>
    </div>

    <script>
        const TEAM_ID = <?= json_encode($team_id) ?>;
        const LOADED_FORMATION = <?= json_encode($loaded_formation) ?>; // Data formasi yang dimuat
        const ALL_PLAYERS = <?= json_encode($players) ?>; // Semua pemain

        const form = document.getElementById('teamForm');
        const editBtn = document.getElementById('editBtn');
        const inputJumlah = document.getElementById('inputJumlah');
        const inputs = [
            document.getElementById('inputTeamName'), 
            document.getElementById('inputInstansi'), 
            inputJumlah 
        ];
        const selectSport = document.getElementById('selectSport');

        const logoInput = document.getElementById('logoInput');
        const logoPreview = document.getElementById('logoPreview');
        const logoContainer = document.getElementById('logoContainer');

        let activePlayers = [...ALL_PLAYERS];

        let isTeamExists = <?= $isEdit ? 'true' : 'false' ?>;
        let isEditing = !isTeamExists; 

        function showPlayerSection(buttonElement) {
            document.getElementById('pemainSection').style.display = 'block';
            buttonElement.style.display = 'none';
            syncPlayerCards();
        }

        document.addEventListener('DOMContentLoaded', () => {
            setFormEditable(isEditing);

            const pemainSection = document.getElementById('pemainSection');
            const showPlayersBtn = document.getElementById('showPlayersBtn');

            if (isTeamExists) {
                pemainSection.style.display = 'none';
                if (showPlayersBtn) showPlayersBtn.style.display = 'block';
            } else {
                pemainSection.style.display = 'block';
                if (showPlayersBtn) showPlayersBtn.style.display = 'none';
                syncPlayerCards();
            }

            inputJumlah.addEventListener('change', syncPlayerCards);
            inputJumlah.addEventListener('keyup', syncPlayerCards);
            
            // Load initial color if exists
            const savedColor = localStorage.getItem('fieldColor');
            if (savedColor) {
                 document.documentElement.style.setProperty('--field-color', savedColor);
                 fieldColorPicker.value = savedColor;
            }
        });

        function setFormEditable(editable) {
            isEditing = editable;
            inputs.forEach(input => input.readOnly = !editable);
            selectSport.disabled = !editable;
            if (editable) {
                form.classList.remove('readonly');
                logoContainer.classList.remove('readonly');
                logoContainer.classList.add('editable');
                editBtn.textContent = 'Simpan Perubahan';
            } else {
                form.classList.add('readonly');
                logoContainer.classList.remove('editable');
                logoContainer.classList.add('readonly');
                editBtn.textContent = isTeamExists ? 'Edit Tim' : 'Buat Tim';
            }
        }

        editBtn.addEventListener('click', () => {
            if (isEditing) {
                form.submit();
            } else {
                setFormEditable(true);
            }
        });

        logoContainer.addEventListener('click', () => { if (isEditing) logoInput.click(); });
        logoInput.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) { logoPreview.src = e.target.result; };
                reader.readAsDataURL(file);
            }
        });

        function confirmLogout(e) { e.preventDefault(); if (confirm("Yakin ingin keluar?")) window.location.href = "logout.php"; }

        const playerCardsContainer = document.getElementById('playerCards');
        
        function getPositionClass(position) {
            if (!position) return 'pos-fw';
            const pos = position.toUpperCase();
            
            if (pos.includes('STR') || pos.includes('FORW') || pos.includes('ATTACK') || pos.includes('FW')) return 'pos-fw';
            if (pos.includes('DEF') || pos.includes('BACK') || pos.includes('DF')) return 'pos-df';
            if (pos.includes('MID') || pos.includes('MF') || pos.includes('CENTRE')) return 'pos-mf';
            if (pos.includes('GOAL') || pos.includes('GK') || pos.includes('KEEPER')) return 'pos-gk';
            
            return 'pos-fw';
        }

        function syncPlayerCards() {
            const maxPlayers = parseInt(inputJumlah.value) || 0;
            playerCardsContainer.innerHTML = '';
            for (let i = 0; i < maxPlayers; i++) {
                if (i < activePlayers.length) renderPlayerCard(activePlayers[i], i);
                else renderPlaceholderCard(i);
            }
            activePlayers = activePlayers.slice(0, maxPlayers);
        }

        function renderPlayerCard(player, index) {
            const card = document.createElement('div');
            const posClass = getPositionClass(player.position);
            const colorClass = player.color_class || 'color-red'; 

            card.className = `player-card ${posClass} ${colorClass}`;
            card.setAttribute('data-index', index);
            card.setAttribute('data-id', player.id || index);
            card.setAttribute('data-name', player.name);
            card.setAttribute('data-number', player.number);
            card.setAttribute('data-position', player.position);
            card.setAttribute('data-height', player.height);
            card.setAttribute('data-weight', player.weight);
            card.setAttribute('data-age', player.age);
            card.setAttribute('data-foto', player.foto);
            card.setAttribute('data-color', colorClass); // Simpan warna di data
            card.setAttribute('onclick', "openModal('edit', this)");

            const teamName = document.getElementById('inputTeamName').value || 'TEAM NAME'; 
            
            card.innerHTML = `
                <div class="player-card-outer-frame"></div>
                <div class="content-area">
                    <div class="team-name-section">${teamName}</div>
                    
                    <div class="photo-frame">
                        <img src="${player.foto}" alt="Foto Pemain">
                    </div>

                    <div class="player-info-bottom">
                        <div class="player-details">
                            <div class="player-name-bg">${player.name || 'PLAYER NAME'}</div>
                            <div class="player-position-box">${player.position || 'POSITION'}</div>
                        </div>
                        <div class="player-number">#${player.number || '0'}</div>
                    </div>
                    <div class="deco-line">>>>>>>>>>>>>>>>>>>>>>>>></div>
                </div>
            `;
            playerCardsContainer.appendChild(card);
        }

        function renderPlaceholderCard(index) {
            const card = document.createElement('div');
            card.className = 'player-card placeholder'; 
            card.setAttribute('onclick', "openModal('add', this)");
            card.style.cursor = 'pointer';
            
            card.innerHTML = `
                <div class="content-area">
                    <div class="team-name-section">${document.getElementById('inputTeamName').value || 'TEAM NAME'}</div>
                    <div class="player-info-dummy">
                        <p>SLOT KOSONG</p>
                        <p>KLIK UNTUK TAMBAH PEMAIN</p>
                    </div>
                    <div class="player-info-bottom">
                        <div class="player-details">
                            <div class="player-name-bg">PLAYER NAME</div>
                            <div class="player-position-box">POSITION</div>
                        </div>
                        <div class="player-number">#0</div>
                    </div>
                    <div class="deco-line">>>>>>>>>>>>>>>>>>>>>>>>></div>
                </div>
            `;
            playerCardsContainer.appendChild(card);
        }

        const playerModal = document.getElementById('playerModal');
        const playerForm = document.getElementById('playerForm');
        const playerFotoInput = document.getElementById('playerFotoInput');
        const playerFotoPreview = document.getElementById('playerFotoPreview');
        const modalTitle = document.getElementById('modalTitle');
        const submitPlayerBtn = document.getElementById('submitPlayerBtn');
        const actionType = document.getElementById('actionType');
        const playerIdInput = document.getElementById('playerIdInput');
        const playerNameInput = document.getElementById('playerNameInput');
        const playerNumberInput = document.getElementById('playerNumberInput');
        const playerPositionInput = document.getElementById('playerPositionInput');
        const playerHeightInput = document.getElementById('playerHeightInput');
        const playerWeightInput = document.getElementById('playerWeightInput');
        const playerAgeInput = document.getElementById('playerAgeInput');
        const currentFotoPathInput = document.getElementById('currentFotoPath');
        const playerColorInput = document.getElementById('playerColorInput'); 
        let editingCardIndex = -1;

        function openModal(mode, element = null) {
            if (mode === 'add' && activePlayers.length >= parseInt(inputJumlah.value)) { alert('Jumlah pemain sudah mencapai batas maksimal yang diinput.'); return; }
            playerForm.reset(); playerFotoInput.value = ''; playerFotoPreview.src = '../assets/profil.png'; actionType.value = mode;
            if (mode === 'add') {
                modalTitle.textContent = 'Tambah Pemain Baru'; submitPlayerBtn.textContent = 'Simpan Pemain'; editingCardIndex = activePlayers.length; playerIdInput.value = 'new'; currentFotoPathInput.value = '';
                playerColorInput.value = 'color-red'; 
            } else if (mode === 'edit' && element) {
                modalTitle.textContent = 'Edit Data Pemain'; submitPlayerBtn.textContent = 'Update Pemain';
                editingCardIndex = parseInt(element.getAttribute('data-index'));
                playerIdInput.value = element.dataset.id;
                playerNameInput.value = element.dataset.name;
                playerNumberInput.value = element.dataset.number;
                playerPositionInput.value = element.dataset.position;
                playerHeightInput.value = element.dataset.height;
                playerWeightInput.value = element.dataset.weight;
                playerAgeInput.value = element.dataset.age;
                playerFotoPreview.src = element.dataset.foto;
                // Menghapus '../' dari path untuk dikirim kembali ke server
                currentFotoPathInput.value = element.dataset.foto.replace('../', ''); 
                playerColorInput.value = element.dataset.color || 'color-red'; 
            }
            playerModal.style.display = 'flex';
        }

        function closeModal() { playerModal.style.display = 'none'; }
        
        // ===================================================
        // FUNGSI MODAL FORMATION & DRAG & DROP LOGIC
        // ===================================================
        const formationModal = document.getElementById('formationModal');
        const availablePlayersList = document.getElementById('availablePlayersList');
        const formationMappingArea = document.getElementById('formationMappingArea');
        const fieldPlayers = document.getElementById('fieldPlayers');
        const formationSelect = document.getElementById('formationSelect'); 
        const fieldColorPicker = document.getElementById('fieldColorPicker');

        // Global variable untuk DnD
        let draggedElement = null;
        let currentOffsetX = 0;
        let currentOffsetY = 0;

        // PRESET FORMATION UNTUK FUTSAL (5v5)
        // Nilai dalam % (persen) dari Lapangan (X: 0-100, Y: 0-100)
        const FORMATION_PRESETS = {
            // Posisi 1-2-1 (Diamond)
            '1-2-1': [
                { x: 50, y: 88, role: 'GK' }, // Kiper
                { x: 50, y: 70, role: 'DF' }, // Belakang/Anchor
                { x: 25, y: 40, role: 'MF' }, // Sayap Kiri
                { x: 75, y: 40, role: 'MF' }, // Sayap Kanan
                { x: 50, y: 15, role: 'FW' }  // Depan/Pivot
            ],
            // Posisi 2-2 (Box/Square)
            '2-2': [
                { x: 50, y: 88, role: 'GK' },
                { x: 30, y: 65, role: 'DF' }, // Belakang Kiri
                { x: 70, y: 65, role: 'DF' }, // Belakang Kanan
                { x: 30, y: 30, role: 'FW' }, // Depan Kiri
                { x: 70, y: 30, role: 'FW' }  // Depan Kanan
            ],
            // Posisi 3-1 (Tandem/Wall)
            '3-1': [
                { x: 50, y: 88, role: 'GK' },
                { x: 20, y: 65, role: 'DF' }, // Bek Kiri
                { x: 50, y: 65, role: 'DF' }, // Bek Tengah
                { x: 80, y: 65, role: 'DF' }, // Bek Kanan
                { x: 50, y: 25, role: 'FW' }  // Depan/Pivot
            ]
        };

        function changeFieldColor(color) {
            document.documentElement.style.setProperty('--field-color', color);
            localStorage.setItem('fieldColor', color); // Simpan warna
        }

        function openFormationModal() {
            if (!TEAM_ID) { 
                alert('ERROR: Harap simpan data Tim (Nama Tim, Cabor, Instansi) terlebih dahulu.'); 
                return; 
            }
            
            // Set initial color based on stored value
            const savedColor = localStorage.getItem('fieldColor') || '#008000';
            document.documentElement.style.setProperty('--field-color', savedColor);
            fieldColorPicker.value = savedColor;

            formationModal.style.display = 'flex';    
            renderAvailablePlayers();
            
            // Perbaikan: Gunakan setTimeout untuk menunggu modal di-render
            setTimeout(() => {
                if (LOADED_FORMATION.length > 0 && fieldPlayers.children.length === 0) {
                    renderLoadedFormation();
                }
            }, 10); // Penundaan kecil (10ms) untuk memastikan modal terlihat dan dimensinya dihitung

            initializeDragAndDrop();    
            formationSelect.value = "";    
        }

        function renderLoadedFormation() {
            // REVISI: Dapatkan dimensi lapangan *setelah* modal dipastikan terbuka
            const fieldRect = formationMappingArea.getBoundingClientRect();
            
            // Cek jika dimensi lapangan masih nol (terjadi jika rendering terlalu cepat)
            if (fieldRect.width === 0 || fieldRect.height === 0) {
                console.warn("Formation mapping area dimensions are zero, delaying rendering.");
                setTimeout(renderLoadedFormation, 50); // Coba lagi setelah 50ms
                return;
            }

            const cardWidth = 210 * 0.45;    
            const cardHeight = 300 * 0.45;    
            
            fieldPlayers.innerHTML = '';
            
            LOADED_FORMATION.forEach(f => {
                // Cari data pemain yang sesuai
                const player = activePlayers.find(p => String(p.id) === String(f.id));
                if (player) {
                    // Logika Konversi Persentase ke Pixel (sudah benar)
                    // Xpx = ((X% / 100) * fieldWidth) - (cardWidth / 2)
                    const xPx = (parseFloat(f.x) / 100) * fieldRect.width - (cardWidth / 2);
                    const yPx = (parseFloat(f.y) / 100) * fieldRect.height - (cardHeight / 2);
                    
                    const card = createFormationCard(player, xPx, yPx);
                    fieldPlayers.appendChild(card);
                }
            });
            renderAvailablePlayers(); // Update pool
        }


        function closeFormationModal() {
            formationModal.style.display = 'none';
        }

        function renderAvailablePlayers() {
            availablePlayersList.innerHTML = '';
            
            // Filter pemain yang BELUM ADA di lapangan
            const playersOnFieldIds = Array.from(fieldPlayers.children).map(card => card.dataset.id);
            
            const playersInPool = activePlayers.filter(player => !playersOnFieldIds.includes(String(player.id)));
            
            if (playersInPool.length === 0 && activePlayers.length > 0) {
                availablePlayersList.innerHTML = '<p style="color: #999; margin: 0; font-size: 14px;">Semua pemain sudah ditempatkan di lapangan.</p>';
                return;
            }
            
            if (activePlayers.length === 0) {
                availablePlayersList.innerHTML = '<p style="color: #999; margin: 0; font-size: 14px;">Belum ada pemain yang ditambahkan.</p>';
                return;
            }
            
            playersInPool.forEach(player => {
                const playerChip = document.createElement('span');
                playerChip.textContent = `#${player.number} ${player.name} (${player.position})`;
                playerChip.className = 'player-chip'; 
                
                playerChip.setAttribute('draggable', true);
                playerChip.setAttribute('data-id', player.id);
                
                // Simpan data pemain sebagai string JSON untuk Drag Event
                playerChip.setAttribute('data-player-data', JSON.stringify(player));
                
                availablePlayersList.appendChild(playerChip);
            });
            // Re-attach drag start listeners setiap kali pool di-render
            initializeDragAndDrop();
        }

        function createFormationCard(playerData, x, y) {
            const posClass = getPositionClass(playerData.position); 
            const colorClass = playerData.color_class || 'color-red';
            const card = document.createElement('div');
            
            // Menggunakan styling yang disederhanakan dan menggabungkan class posisi
            card.className = `formation-card ${posClass} ${colorClass}`; 
            card.setAttribute('draggable', true);
            card.setAttribute('data-id', playerData.id);
            card.setAttribute('data-position', playerData.position); 

            // Posisi absolut (X dan Y sudah dalam pixel)
            card.style.left = `${x}px`;
            card.style.top = `${y}px`;
            
            const teamName = document.getElementById('inputTeamName').value || 'TEAM NAME'; 
            
            // Menggunakan struktur HTML yang sama persis dengan renderPlayerCard
            card.innerHTML = `
                <div class="player-card-outer-frame"></div>
                <div class="content-area">
                    <div class="team-name-section">${teamName}</div>
                    
                    <div class="photo-frame">
                        <img src="${playerData.foto}" alt="Foto Pemain">
                    </div>

                    <div class="player-info-bottom">
                        <div class="player-details">
                            <div class="player-name-bg">${playerData.name || 'PLAYER NAME'}</div>
                            <div class="player-position-box">${playerData.position || 'POSITION'}</div>
                        </div>
                        <div class="player-number">#${playerData.number || '0'}</div>
                    </div>
                    <div class="deco-line">>>>>>>>>>>>>>>>>>>>>>>>></div>
                </div>
            `;
            
            card.addEventListener('dragstart', handleDragStart);

            return card;
        }

        function applyFormation(formationKey) {
            if (!formationKey || activePlayers.length === 0) return;
            
            const positions = FORMATION_PRESETS[formationKey];
            const maxPlayersOnField = positions.length;

            if (activePlayers.length < maxPlayersOnField) {
                alert(`Tidak cukup pemain (${activePlayers.length}) untuk formasi ${formationKey} (${maxPlayersOnField} posisi).`);
                return;
            }

            // 1. Klasifikasikan pemain (Goalkeeper vs Non-Goalkeeper)
            let goalkeepers = activePlayers.filter(p => p.position.toUpperCase().includes('GOAL') || p.position.toUpperCase().includes('GK'));
            let fieldPlayersQueue = activePlayers.filter(p => !goalkeepers.includes(p));
            
            fieldPlayersQueue.sort((a, b) => a.number - b.number); 
            
            if (goalkeepers.length === 0) {
                 alert("Tidak ada pemain dengan posisi Goalkeeper (GK) yang terdaftar. Penempatan formasi dibatalkan.");
                 return;
            }

            let goalkeeper = goalkeepers[0];
            
            // 2. Pindahkan semua kartu dari lapangan kembali ke pool
            fieldPlayers.innerHTML = '';
            
            // 3. Hitung dimensi lapangan untuk konversi persentase ke piksel
            const fieldRect = formationMappingArea.getBoundingClientRect();
            const cardWidth = 210 * 0.45; 
            const cardHeight = 300 * 0.45; 
            
            let playerIndex = 0;
            
            // 4. Tempatkan Pemain ke Posisi Preset
            positions.forEach(pos => {
                let playerToPlace = null;
                
                if (pos.role === 'GK') {
                    playerToPlace = goalkeeper;
                } else if (playerIndex < fieldPlayersQueue.length) {
                    playerToPlace = fieldPlayersQueue[playerIndex];
                    playerIndex++;
                }

                if (playerToPlace) {
                    // Konversi persentase (X, Y) ke pixel relatif terhadap container
                    // Xpx = ((X% / 100) * fieldWidth) - (cardWidth / 2)
                    const finalX = (pos.x / 100) * fieldRect.width - (cardWidth / 2);
                    const finalY = (pos.y / 100) * fieldRect.height - (cardHeight / 2);

                    const card = createFormationCard(playerToPlace, finalX, finalY);
                    fieldPlayers.appendChild(card);
                }
            });
            
            // 5. Render ulang pool agar chip yang sudah di lapangan hilang
            renderAvailablePlayers();
            alert(`Formasi ${formationKey} berhasil diterapkan.`);
        }

        // ===================================================
        // LOGIC SIMPAN FORMASI KE DATABASE
        // ===================================================
        function saveFormationToDB() {
            if (!TEAM_ID) { alert('ERROR: Team ID tidak ditemukan.'); return; }
            
            const cards = Array.from(fieldPlayers.children);
            if (cards.length === 0) {
                alert('Tidak ada pemain di lapangan untuk disimpan.');
                return;
            }

            const fieldRect = formationMappingArea.getBoundingClientRect();
            const fieldWidth = fieldRect.width;
            const fieldHeight = fieldRect.height;
            
            const cardWidth = 210 * 0.45; // Lebar kartu setelah di-scale
            const cardHeight = 300 * 0.45; // Tinggi kartu setelah di-scale

            const formationData = cards.map(card => {
                // Ambil posisi pixel dari style (misal: "123px")
                const xPx = parseFloat(card.style.left);
                const yPx = parseFloat(card.style.top);
                
                // Konversi kembali ke persentase (X, Y) untuk DB
                // Posisi % dihitung dari titik tengah kartu
                const xPercent = ((xPx + (cardWidth / 2)) / fieldWidth) * 100;
                const yPercent = ((yPx + (cardHeight / 2)) / fieldHeight) * 100;

                return {
                    id: card.dataset.id,
                    x: xPercent.toFixed(2), // Simpan 2 desimal
                    y: yPercent.toFixed(2)
                };
            });

            const formData = new FormData();
            formData.append('save_formation', 1);
            formData.append('team_id', TEAM_ID);
            formData.append('formation_data', JSON.stringify(formationData));
            
            fetch('timsaya.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert(result.message);
                    } else {
                        alert('Gagal: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error saving formation:', error);
                    alert('Terjadi kesalahan koneksi saat menyimpan formasi.');
                });
        }


        function handleDragStart(e) {
            draggedElement = e.target;
            
            // Pastikan kita mendapatkan elemen kartu atau chip yang draggable
            while (draggedElement && !draggedElement.classList.contains('formation-card') && draggedElement.tagName !== 'SPAN') {
                draggedElement = draggedElement.parentElement;
            }

            if (!draggedElement) return;

            const isChip = draggedElement.tagName === 'SPAN';
            const isCard = draggedElement.classList.contains('formation-card');
            
            const cardWidth = 210 * 0.45; // Lebar kartu setelah di-scale
            const cardHeight = 300 * 0.45; // Tinggi kartu setelah di-scale

            if (isCard) {
                draggedElement.classList.add('dragging');
                e.dataTransfer.setData('text/plain', JSON.stringify({id: draggedElement.dataset.id}));
                e.dataTransfer.setData('source', 'field');
                
                const rect = draggedElement.getBoundingClientRect();
                // Menghitung offset yang benar dari titik kursor ke sudut kiri atas elemen
                currentOffsetX = e.clientX - rect.left;
                currentOffsetY = e.clientY - rect.top;

            } else if (isChip) {
                e.dataTransfer.setData('text/plain', draggedElement.getAttribute('data-player-data'));
                e.dataTransfer.setData('source', 'pool');
                // Offset DnD untuk chip yang baru di-drop: Setengah lebar/tinggi kartu yang sudah di-scale
                currentOffsetX = cardWidth / 2; 
                currentOffsetY = cardHeight / 2; 
            }
        }


        function handleDrop(e) {
            e.preventDefault();
            const dropTarget = e.currentTarget;
            const source = e.dataTransfer.getData('source');
            
            if (dropTarget.id === 'formationMappingArea') {
                
                const rect = dropTarget.getBoundingClientRect();
                
                // Posisi relatif di dalam area lapangan
                const relativeX = e.clientX - rect.left;
                const relativeY = e.clientY - rect.top;
                
                // Hitung posisi final menggunakan offset yang disimpan saat dragstart
                const finalX = relativeX - currentOffsetX;
                const finalY = relativeY - currentOffsetY;

                if (source === 'pool') {
                    if (fieldPlayers.children.length >= (parseInt(inputJumlah.value) || 0)) {
                        alert(`Maksimal pemain di lapangan adalah ${inputJumlah.value}`);
                        return;
                    }

                    const playerDataString = e.dataTransfer.getData('text/plain');
                    const playerData = JSON.parse(playerDataString);

                    // Buat kartu baru di posisi drop
                    const newCard = createFormationCard(playerData, finalX, finalY); 
                    fieldPlayers.appendChild(newCard);
                    
                    // Chip yang di-drag dari pool harus dihapus
                    if (draggedElement && draggedElement.tagName === 'SPAN') {
                         draggedElement.remove();
                    }
                    renderAvailablePlayers(); 
                    
                } else if (source === 'field') {
                    // Drag and drop kartu yang sudah ada di lapangan
                    const card = draggedElement;
                    card.style.left = `${finalX}px`;
                    card.style.top = `${finalY}px`;
                    card.classList.remove('dragging');
                }
            } else if (dropTarget.id === 'availablePlayersList' && source === 'field') {
                // Drop kartu dari lapangan kembali ke pool
                draggedElement.classList.remove('dragging');
                draggedElement.remove(); 
                renderAvailablePlayers();
            }
            // Reset offset setelah drop
            currentOffsetX = 0;
            currentOffsetY = 0;
        }

        function handleDragOver(e) {
            e.preventDefault(); 
        }
        
        function handleDragEnd(e) {
             if (draggedElement && draggedElement.classList.contains('dragging')) {
                 draggedElement.classList.remove('dragging');
             }
             draggedElement = null;
        }


        function initializeDragAndDrop() {
            // Setup Dragstart untuk chip di pool
            availablePlayersList.querySelectorAll('span').forEach(chip => {
                chip.removeEventListener('dragstart', handleDragStart); 
                chip.addEventListener('dragstart', handleDragStart);
            });
            
            // Setup Dragstart untuk kartu di lapangan
            fieldPlayers.querySelectorAll('.formation-card').forEach(card => {
                card.removeEventListener('dragstart', handleDragStart); 
                card.addEventListener('dragstart', handleDragStart);
            });
            
            // Setup DragOver dan Drop untuk area lapangan dan pool
            formationMappingArea.removeEventListener('dragover', handleDragOver);
            formationMappingArea.removeEventListener('drop', handleDrop);
            availablePlayersList.removeEventListener('dragover', handleDragOver);
            availablePlayersList.removeEventListener('drop', handleDrop);

            formationMappingArea.addEventListener('dragover', handleDragOver);
            formationMappingArea.addEventListener('drop', handleDrop);
            
            availablePlayersList.addEventListener('dragover', handleDragOver);
            availablePlayersList.addEventListener('drop', handleDrop);
            
            document.removeEventListener('dragend', handleDragEnd);
            document.addEventListener('dragend', handleDragEnd);

        }

        
        // TUTUP MODAL KETIKA KLIK DI LUAR KONTEN
        window.onclick = function(event) { 
            if (event.target == playerModal) closeModal();
            if (event.target == formationModal) closeFormationModal(); 
        }

        playerFotoInput.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) { playerFotoPreview.src = e.target.result; };
                reader.readAsDataURL(file);
            }
        });

        fieldColorPicker.addEventListener('change', (e) => {
            changeFieldColor(e.target.value);
        });

        playerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!TEAM_ID) { alert('ERROR: Harap simpan data Tim (Nama Tim, Cabor, Instansi) terlebih dahulu.'); return; }
            const mode = actionType.value;
            const currentFormData = new FormData(playerForm);
            currentFormData.append('team_id', TEAM_ID);
            currentFormData.append('is_player_ajax', 1);
            if (mode === 'edit') currentFormData.append('player_id', playerIdInput.value);
            
            // Tambahkan color class ke FormData
            currentFormData.append('color_class', playerColorInput.value);

            try {
                const response = await fetch('timsaya.php', { method: 'POST', body: currentFormData });
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Server responded with status:', response.status, errorText);
                    alert(`Terjadi kesalahan server (${response.status}). PHP Output: ${errorText.substring(0, 200)}...`);
                    return;
                }
                const result = await response.json();
                if (result.success) {
                    const newPlayer = {
                        id: result.player_id,
                        name: playerNameInput.value,
                        number: playerNumberInput.value,
                        position: playerPositionInput.value,
                        height: playerHeightInput.value,
                        weight: playerWeightInput.value,
                        age: playerAgeInput.value,
                        foto: result.foto_path,
                        color_class: result.color_class 
                    };
                    if (mode === 'add') activePlayers.push(newPlayer);
                    else activePlayers[editingCardIndex] = newPlayer;
                    syncPlayerCards(); closeModal();
                    alert(`${newPlayer.name} berhasil di${mode === 'add' ? 'simpan' : 'update'} ke database!`);
                } else {
                    alert('Gagal menyimpan data: ' + result.message);
                }
            } catch (error) {
                console.error('Error submitting player data:', error);
                alert('Terjadi kesalahan koneksi saat menyimpan pemain. Cek konsol!');
            }
        });

    </script>
</body>
</html>