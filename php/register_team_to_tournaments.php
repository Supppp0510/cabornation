<?php
include("../php/config.php");

$team = $_POST['team_name'] ?? '';

if($team == ''){
    echo "Tim tidak boleh kosong.";
    exit;
}

$stmt = $conn->prepare("INSERT INTO tournament_teams (team_name) VALUES (?)");
$stmt->bind_param("s", $team);

echo $stmt->execute() ? "success" : "Terjadi kesalahan.";
?>
