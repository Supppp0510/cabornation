<?php
session_start();
include("config.php"); // Asumsi file ini terhubung ke database

// ✅ Cek login
if (!isset($_SESSION['user_email'])) {
    header("Location: ../html/login.html");
    exit();
}

$email = $_SESSION['user_email'];

// ✅ Ambil data user dari database menggunakan prepared statement
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $user_id = $data['id'];
    
    // Pastikan semua field yang mungkin null punya default value
    $data['overview'] = $data['overview'] ?? '';
    // Tambahkan variabel untuk menampung pesan sukses
    $success_message = '';
} else {
    // Jika user tidak ditemukan, redirect ke login
    session_destroy();
    header("Location: ../html/login.html");
    exit();
}

// ✅ Ambil foto dari tabel user_photos (jika ada)
$foto = '../assets/profil.png'; // default
if ($user_id !== null) {
    $stmt_foto = $conn->prepare("SELECT photo_path FROM user_photos WHERE user_id = ? LIMIT 1");
    $stmt_foto->bind_param("i", $user_id);
    $stmt_foto->execute();
    $result_foto = $stmt_foto->get_result();
    
    if ($result_foto && $result_foto->num_rows > 0) {
        $foto_row = $result_foto->fetch_assoc();
        // Pastikan path benar: jika sudah ada ../ di DB, jangan tambah lagi
        if (strpos($foto_row['photo_path'], '../') === 0) {
            $foto = $foto_row['photo_path'];
        } else {
            $foto = "../" . $foto_row['photo_path'];
        }
    }
}


// ==========================================================
// 🔧 LOGIC UPDATE PROFIL & SUMMARY (Gabung di sini)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Profil
    if (isset($_POST['update_profile'])) {
        $nama = $_POST['nama'];
        $overview = $_POST['overview'];
        $foto_lama = $_POST['foto_lama'];
        $target_file = $foto_lama; // default pakai foto lama
        $is_foto_updated = false;

        // ✅ Upload foto jika ada
        if (!empty($_FILES['foto']['name'])) {
            $foto_name = time() . '_' . basename($_FILES['foto']['name']);
            $target_dir = "../assets/foto_profil/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $target_file = $target_dir . $foto_name;
            $db_path = "assets/foto_profil/" . $foto_name; // path disimpan ke DB (tanpa ../)

            // Validasi tipe file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['foto']['type'];

            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                    // Logika simpan/update foto ke user_photos
                    $check_photo = $conn->prepare("SELECT id FROM user_photos WHERE user_id = ?");
                    $check_photo->bind_param("i", $user_id);
                    $check_photo->execute();
                    $result_photo = $check_photo->get_result();

                    if ($result_photo->num_rows > 0) {
                        $update_photo = $conn->prepare("UPDATE user_photos SET photo_path = ?, uploaded_at = NOW() WHERE user_id = ?");
                        $update_photo->bind_param("si", $db_path, $user_id);
                        $update_photo->execute();
                    } else {
                        $insert_photo = $conn->prepare("INSERT INTO user_photos (user_id, photo_path, uploaded_at) VALUES (?, ?, NOW())");
                        $insert_photo->bind_param("is", $user_id, $db_path);
                        $insert_photo->execute();
                    }
                    $is_foto_updated = true;
                } else {
                    echo "<script>alert('Gagal mengupload foto!');</script>";
                }
            } else {
                echo "<script>alert('Format file tidak didukung! Gunakan JPG, PNG, atau GIF.');</script>";
            }
        }

        // Update nama & overview di tabel users menggunakan prepared statement
        $stmt_update = $conn->prepare("UPDATE users SET nama = ?, overview = ? WHERE email = ?");
        $stmt_update->bind_param("sss", $nama, $overview, $email);
        
        if ($stmt_update->execute()) {
            // Ambil ulang foto terbaru jika ada update foto, supaya $foto sesuai.
            if ($is_foto_updated) {
                 $stmt_foto = $conn->prepare("SELECT photo_path FROM user_photos WHERE user_id = ? LIMIT 1");
                 $stmt_foto->bind_param("i", $user_id);
                 $stmt_foto->execute();
                 $result_foto = $stmt_foto->get_result();
                 if ($result_foto && $result_foto->num_rows > 0) {
                     $foto_row = $result_foto->fetch_assoc();
                     if (strpos($foto_row['photo_path'], '../') === 0) {
                         $foto = $foto_row['photo_path'];
                     } else {
                         $foto = "../" . $foto_row['photo_path'];
                     }
                 }
            }
            
            // Refresh data user
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            // Set pesan sukses dan kirim status 'saved' ke JS
            $success_message = 'Profil berhasil diperbarui!';
            // Lanjutkan ke bagian HTML untuk menampilkan notif dan readonly
            // Tidak perlu redirect, cukup tampilkan notif dan atur tampilan
        } else {
            echo "<script>alert('Gagal memperbarui profil: " . $conn->error . "');</script>";
        }
    }
    
    // Logika Update Summary dihilangkan dari contoh ini agar lebih fokus pada Profil
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil | CaborNation</title>
    <link rel="stylesheet" href="../css/editprofil.css">
    <style>
        /* CSS tambahan untuk overlay saat readonly */
        .profile-pic.readonly label .overlay {
            cursor: default !important;
            opacity: 0;
        }
        .profile-pic.readonly label .overlay:hover {
            opacity: 0;
        }
        .profile-pic.editable label .overlay {
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        .profile-pic.editable label .overlay:hover {
            opacity: 1;
        }
        .profile-pic input[type="file"] {
             /* Pastikan input file tidak terlihat */
             display: none;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">CaborNation</div>
        <ul class="menu">
            <li><a href="dashboard.php">Beranda</a></li>
            <li><a href="timsaya.php">Tim Saya</a></li>
            <li><a href="#">Turnamen</a></li>
            <li class="active"><a href="editprofil.php">Profil</a></li>
            <li><a href="#" onclick="confirmLogout(event)">Keluar</a></li>
        </ul>
    </aside>

    <main class="container">
        <div class="profile-card">
            <div class="profile-header">
                <form id="fotoForm" action="editprofil.php" method="POST" enctype="multipart/form-data" style="display:inline;">
                    <div class="profile-pic" id="profilePicContainer">
                        <label for="foto">
                            <img id="preview" src="<?php echo htmlspecialchars($foto); ?>" alt="Foto Profil" title="Klik untuk ubah foto">
                            <div class="overlay">Ubah Foto</div>
                        </label>
                        <input type="file" name="foto" id="foto" accept="image/*" onchange="autoSubmitFoto(event)">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="nama" value="<?php echo htmlspecialchars($data['nama']); ?>">
                        <input type="hidden" name="overview" value="<?php echo htmlspecialchars($data['overview'] ?? ''); ?>">
                        <input type="hidden" name="foto_lama" value="<?php echo htmlspecialchars($foto); ?>">
                    </div>
                </form>

                <div class="profile-info">
                    <p class="official"><?php echo ucfirst($data['role']); ?></p>
                    <h1><?php echo htmlspecialchars($data['nama']); ?></h1>
                    <p class="email"><?php echo htmlspecialchars($data['email']); ?></p>
                </div>
            </div>
        </div>

        <form action="editprofil.php" method="POST" class="edit-form" id="editForm">
            <h3>Edit Profil</h3>
            <div class="form-group">
                <label>Nama</label>
                <input type="text" name="nama" id="inputNama" value="<?php echo htmlspecialchars($data['nama']); ?>" required readonly>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>" readonly>
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea name="overview" id="inputBio" rows="5" placeholder="Tulis bio singkat kamu..." readonly><?php    
                    $overview_value = $data['overview'] ?? '';
                    echo htmlspecialchars($overview_value);    
                ?></textarea>
            </div>

            <input type="hidden" name="foto_lama" value="<?php echo htmlspecialchars($foto); ?>">

            <button type="button" id="btnAksi" class="btn-main" onclick="toggleEditMode()">Edit Profil</button>
        </form>

    </main>

    <script>
        // Set mode awal: readonly
        let isEditing = false;
        
        // Elemen-elemen yang akan diubah
        const inputNama = document.getElementById('inputNama');
        const inputBio = document.getElementById('inputBio');
        const btnAksi = document.getElementById('btnAksi');
        const fotoInput = document.getElementById('foto');
        const fotoForm = document.getElementById('fotoForm');
        const profilePicContainer = document.getElementById('profilePicContainer');
        const editForm = document.getElementById('editForm');
        
        /**
         * Mengubah mode form antara 'Edit Profil' dan 'Simpan Perubahan'
         */
        function toggleEditMode() {
            if (isEditing) {
                // Saat tombol diklik, mode EDITING aktif.
                // Klik ini berarti SUBMIT (Simpan Perubahan)
                
                // Tambahkan hidden field untuk update_profile agar logika PHP berjalan
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'update_profile';
                hiddenInput.value = '1';
                editForm.appendChild(hiddenInput);
                
                // Submit form utama
                editForm.submit();
            } else {
                // Mode dari readonly ke EDITABLE
                setFormEditable(true);
            }
        }

        /**
         * Mengatur status readonly/editable pada field dan tombol
         * @param {boolean} editable - true untuk mode edit, false untuk mode readonly
         */
        function setFormEditable(editable) {
            isEditing = editable;
            inputNama.readOnly = !editable;
            inputBio.readOnly = !editable;
            fotoInput.disabled = !editable; // Menonaktifkan/mengaktifkan input file
            
            // Mengatur tampilan tombol dan kursor
            if (editable) {
                btnAksi.textContent = 'Simpan Perubahan';
                btnAksi.type = 'button'; // Tetap button, submit dilakukan di toggleEditMode
                profilePicContainer.classList.remove('readonly');
                profilePicContainer.classList.add('editable');
            } else {
                btnAksi.textContent = 'Edit Profil';
                btnAksi.type = 'button';
                profilePicContainer.classList.remove('editable');
                profilePicContainer.classList.add('readonly');
            }
        }

        // ✅ Preview Foto dan Auto Submit - hanya berfungsi saat mode edit
        function autoSubmitFoto(event) {
            if (isEditing) {
                const file = event.target.files[0];
                if (file) {
                    // Preview gambar
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('preview').src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    
                    // Auto submit form foto
                    setTimeout(function() {
                        if (confirm('Upload foto ini? Perubahan akan langsung disimpan.')) {
                            // Update hidden fields di fotoForm agar data nama/overview terkirim
                            fotoForm.querySelector('input[name="nama"]').value = inputNama.value;
                            fotoForm.querySelector('input[name="overview"]').value = inputBio.value;
                            
                            fotoForm.submit();
                        } else {
                            // Reset input file jika user batal
                            event.target.value = null;
                        }
                    }, 100);
                }
            } else {
                // Jika tidak dalam mode edit, klik pada foto tidak melakukan apa-apa
                event.target.value = null;
                alert('Silakan klik "Edit Profil" terlebih dahulu untuk mengubah foto.');
            }
        }

        function confirmLogout(e) {
            e.preventDefault();

            if (confirm("Yakin ingin keluar?")) {
                window.location.href = "logout.php";
            }
        }
        
        // Inisialisasi setelah halaman selesai dimuat
        document.addEventListener('DOMContentLoaded', function() {
            setFormEditable(false); // Pastikan mode awal adalah readonly

            // Handle notifikasi dan mode setelah berhasil disimpan
            <?php if (!empty($success_message)): ?>
                alert('<?php echo $success_message; ?>');
                // Setelah alert, mode tetap readonly (default), tidak perlu action tambahan.
                // Jika ingin selalu berada di mode 'Simpan Perubahan' setelah upload foto (yang otomatis menyimpan),
                // Anda bisa menambahkan logika di sini. Tapi saat ini, kembali ke readonly.
            <?php endif; ?>
        });
    </script>
</body>
</html>