<?php
session_start();
require_once "../php/config.php";

header("Content-Type: application/json");

// Pastikan EO login
if (!isset($_SESSION["event_id"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if (!isset($_POST["participant_id"])) {
    echo json_encode(["status" => "error", "message" => "Missing participant_id"]);
    exit;
}

$participant_id = intval($_POST["participant_id"]);

$stmt = $conn->prepare("DELETE FROM participants WHERE id = ?");
$stmt->bind_param("i", $participant_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Participant removed"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to remove participant"]);
}
