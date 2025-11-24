<?php
// add_tournament.php (Versi Final untuk digunakan oleh EO/Admin)
session_start();
// Sesuaikan path jika berbeda
include("../php/config.php"); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Periksa koneksi
    if (!isset($conn) || $conn->connect_error) {
        die("Error: Koneksi database tidak tersedia.");
    }

    $title = $_POST['title'];
    $sport = $_POST['sport'];
    $location = $_POST['location'];
    $date_start = $_POST['date_start'];
    $date_end = $_POST['date_end'];
    $registration_fee = (int)$_POST['registration_fee']; 
    $prize_pool = (int)$_POST['prize_pool'];
    $description = $_POST['description'];
    $created_by = $_POST['created_by'];
    
    // Set status default menjadi 'Pending' karena Admin perlu persetujuan
    $status = 'Pending'; 

    // Upload poster (Perlu memastikan folder '../assets/' ada dan writable)
    $poster_url = '';
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $poster_name = time() . "_" . basename($_FILES['poster']['name']);
        // Ganti path ke folder yang benar, misalnya ../assets/posters/
        $poster_path = "../assets/posters/" . $poster_name; 

        if (move_uploaded_file($_FILES['poster']['tmp_name'], $poster_path)) {
            $poster_url = $poster_path;
        } else {
            // Handle upload error
            header("Location: admin_dashboard.php?error=upload_failed");
            exit();
        }
    }

    // Insert DB: TAMBAHKAN kolom 'status'
    $stmt = $conn->prepare("INSERT INTO tournaments 
        (title, sport, location, date_start, date_end, registration_fee, prize_pool, description, poster_url, created_by, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Perbarui bind_param: sssssiiSssS (10 string, 2 integer -> 11 parameter total)
    // Sesuai dengan urutan kolom: title, sport, location, date_start, date_end, registration_fee(i), prize_pool(i), description, poster_url, created_by, status
    $stmt->bind_param(
        "sssssiissss", // Perhatikan urutan dan jumlah tipe data
        $title,
        $sport,
        $location,
        $date_start,
        $date_end,
        $registration_fee,
        $prize_pool,
        $description,
        $poster_url,
        $created_by,
        $status // Parameter status baru
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        // Redirect ke dashboard dengan notifikasi sukses
        header("Location: admin_dashboard.php?success=1"); 
        exit();
    } else {
        $stmt->close();
        $conn->close();
        // Tambahkan detail error
        header("Location: admin_dashboard.php?error=db_execute_failed&details=" . urlencode($conn->error));
        exit();
    }
} else {
    // Jika diakses tidak melalui POST
    header("Location: admin_dashboard.php");
    exit();
}
?>