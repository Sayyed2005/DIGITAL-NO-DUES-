<?php
$host       = "sql202.infinityfree.com";
$dbUsername = "if0_41269262";
$dbPassword = "Awais13nv05";
$dbName     = "if0_41269262_no_dues";

// ── Timezone: set PHP and MySQL to the same local timezone ──
date_default_timezone_set('Asia/Kolkata'); // Change to your timezone if different

$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sync MySQL session timezone with PHP timezone
mysqli_query($conn, "SET time_zone = '+05:30'"); // IST offset — update if you change timezone above
?>
