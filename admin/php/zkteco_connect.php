<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "zkteco_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Device configuration
$device_ip = "192.168.1.201"; // Replace with your device IP
$device_port = 4370;

// Function to connect to ZKTeco device
function connectToDevice($ip, $port) {
    $fp = fsockopen($ip, $port, $errno, $errstr, 10);
    if (!$fp) {
        return "Error: $errstr ($errno)";
    }
    return $fp;
}

// Function to get attendance data
function getAttendanceData($device_ip, $device_port) {
    try {
        // Using ZKTeco SDK (Download from official website)
        include_once 'zkteco_sdk.php'; // You need to get this SDK
        
        $zk = new ZKLib($device_ip, $device_port);
        if ($zk->connect()) {
            $attendance = $zk->getAttendance();
            $zk->disconnect();
            return $attendance;
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Process attendance data
$attendanceData = getAttendanceData($device_ip, $device_port);

if (is_array($attendanceData)) {
    foreach ($attendanceData as $record) {
        $user_id = $record['user_id'];
        $timestamp = $record['timestamp'];
        $status = $record['status'];
        
        // Insert into database
        $sql = "INSERT INTO users (user_id, check_time, check_type) 
                VALUES ('$user_id', '$timestamp', '$status')";
        
        if ($conn->query($sql) === TRUE) {
            echo "Record inserted successfully<br>";
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

$conn->close();
?>
