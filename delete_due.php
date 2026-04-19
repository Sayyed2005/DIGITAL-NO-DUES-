<?php
session_start();
include "../config.php";

// ✅ Check login
if(!isset($_SESSION['role'])){
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];
$type = $_GET['type'];

// ✅ Secure column mapping
$allowed = [
    'library' => 'library_due',
    'accounts' => 'accounts_due',
    'lab' => 'lab_due',
    'exam' => 'exam_due'
];

if(!array_key_exists($type, $allowed)){
    die("Invalid type");
}

$column = $allowed[$type];

// ✅ Clear only selected due
mysqli_query($conn, "UPDATE dues SET $column = 0 WHERE student_id='$id'");

updateStudentFinalStatus($conn, $id);

// 🔄 Redirect back
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>