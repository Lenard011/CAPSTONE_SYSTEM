<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid data"]);
    exit();
}

$config_file = __DIR__ . "/config/payroll_personnel_joborder.json";
$config_dir = dirname($config_file);

if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
}

try {
    file_put_contents($config_file, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
