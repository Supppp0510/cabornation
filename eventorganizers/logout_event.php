<?php
// logout_event.php
session_start();

// Hapus semua session EO
session_unset();
session_destroy();

// Redirect ke halaman login EO
header("Location: http://127.0.0.1/cabornation/eventorganizers/login_event.php");
exit();
?>
