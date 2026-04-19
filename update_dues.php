<?php
session_start();
require_once "../config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Admin check
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

$student_id = intval($_GET['id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);

if($student_id == 0){
    die("Invalid Student ID");
}

// ================================
// FETCH CURRENT DATA
// ================================
$data = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT * FROM dues WHERE student_id='$student_id'
"));

// ================================
// UPDATE DUES
// ================================
if(isset($_POST['update'])){

    $library = intval($_POST['library']);
    $accounts = intval($_POST['accounts']);
    $lab = intval($_POST['lab']);
    $exam = intval($_POST['exam']);

    mysqli_query($conn,"
        UPDATE dues SET
        library_due='$library',
        accounts_due='$accounts',
        lab_due='$lab',
        exam_due='$exam'
        WHERE student_id='$student_id'
    ");

    // ✅ FINAL STATUS (USING FUNCTION)
    updateStudentFinalStatus($conn, $student_id);

    header("Location: view_class.php?class_id=".$class_id);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Update Dues</title>
</head>
<body>

<h2>Update Student Dues</h2>

<form method="post">

Library:
<input type="number" name="library" value="<?= $data['library_due'] ?>"><br><br>

Accounts:
<input type="number" name="accounts" value="<?= $data['accounts_due'] ?>"><br><br>

Lab:
<input type="number" name="lab" value="<?= $data['lab_due'] ?>"><br><br>

Exam:
<input type="number" name="exam" value="<?= $data['exam_due'] ?>"><br><br>

<button name="update">Update</button>

</form>

</body>
</html>