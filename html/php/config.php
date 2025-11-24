<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "cabornation";

$conn = new mysqli($servername, $username, $password, $dbname, 3307);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
} 
?>
