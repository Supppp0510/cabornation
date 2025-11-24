<?php
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'false');
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_name'])) {
    echo json_encode(['name' => $_SESSION['user_name']]);
} else {
    echo json_encode(['name' => null]);
}
?>
