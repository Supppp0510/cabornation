<?php
include("../php/config.php");

$id = $_GET["id"];

$conn->query("DELETE FROM users WHERE id = $id");

header("Location: admin_dashboard.php?deleted=1");
exit();
?>
