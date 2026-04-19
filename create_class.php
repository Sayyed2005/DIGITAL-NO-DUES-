<?php
session_start();
include "../config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = "";
$error = "";

// ✅ HANDLE FORM
if (isset($_POST['create_class'])) {

    $dept_id     = intval($_POST['dept']);
    $year_id     = intval($_POST['year']);
    $division_id = intval($_POST['division']);
    $subjects_input = trim($_POST['subjects'] ?? '');

    if (!$dept_id || !$year_id || !$division_id || !$subjects_input) {
        $error = "All fields are required!";
    } else {

        $conn->begin_transaction();

        try {

            // =========================
            // CREATE CLASS
            // =========================
            $stmt = $conn->prepare("
                INSERT INTO classes (dept_id, year_id, division_id)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iii", $dept_id, $year_id, $division_id);

            if (!$stmt->execute()) {
                throw new Exception("Class Error: " . $stmt->error);
            }

            $class_id = $stmt->insert_id;

            // =========================
            // SUBJECT HANDLING
            // =========================
            $subjects_array = array_filter(array_map('trim', explode(',', $subjects_input)));

            foreach ($subjects_array as $sub_name) {

                // Check subject
                $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_name = ?");
                $stmt->bind_param("s", $sub_name);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows > 0) {
                    $subject_id = $res->fetch_assoc()['subject_id'];
                } else {
                    // Insert subject
                    $stmt = $conn->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
                    $stmt->bind_param("s", $sub_name);

                    if (!$stmt->execute()) {
                        throw new Exception("Subject Insert Error: " . $stmt->error);
                    }

                    $subject_id = $stmt->insert_id;
                }

                // Link class → subject
                $stmt = $conn->prepare("
                    INSERT INTO class_subjects (class_id, subject_id)
                    VALUES (?, ?)
                ");
                $stmt->bind_param("ii", $class_id, $subject_id);

                if (!$stmt->execute()) {
                    throw new Exception("Class-Subject Error: " . $stmt->error);
                }
            }

            // =========================
            // CSV FILE PROCESS
            // =========================
            if (!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
                throw new Exception("File upload error!");
            }

            $handle = fopen($_FILES['file']['tmp_name'], "r");
            if (!$handle) {
                throw new Exception("Cannot open CSV");
            }

            fgetcsv($handle); // skip header

            $inserted = 0;
            $skipped = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

                if (count($data) < 3) {
                    $skipped++;
                    continue;
                }

                $name     = trim($data[0]);
                $username = trim($data[1]);
                $prn      = trim($data[2]);

                if (!$name || !$username || !$prn) {
                    $skipped++;
                    continue;
                }

                // =========================
                // INSERT STUDENT
                // =========================
                $stmt = $conn->prepare("
                    INSERT INTO students 
                    (name, username, prn, dept_id, year_id, division_id, class_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssiiii", $name, $username, $prn, $dept_id, $year_id, $division_id, $class_id);

                if (!$stmt->execute()) {
                    throw new Exception("Student Insert Error: " . $stmt->error);
                }

                $student_id = $stmt->insert_id;

                // =========================
                // INSERT DUES
                // =========================
                $stmt = $conn->prepare("
                    INSERT INTO dues (student_id, library_due, accounts_due, lab_due, exam_due, status)
                    VALUES (?, 0, 0, 0, 0, 'Cleared')
                ");
                $stmt->bind_param("i", $student_id);

                if (!$stmt->execute()) {
                    throw new Exception("Dues Error: " . $stmt->error);
                }

                // =========================
                // INSERT USER (HASHED PASSWORD)
                // =========================
                $hashed_password = password_hash($prn, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (username, password, role, student_id)
                    VALUES (?, ?, 'student', ?)
                ");
                $stmt->bind_param("ssi", $username, $hashed_password, $student_id);

                if (!$stmt->execute()) {
                    throw new Exception("User Error: " . $stmt->error);
                }

                // =========================
                // SUBJECT DUES
                // =========================
                foreach ($subjects_array as $sub_name) {

                    $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_name = ?");
                    $stmt->bind_param("s", $sub_name);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res->fetch_assoc();

                    if (!$row) continue;

                    $sub_id = $row['subject_id'];

                    $stmt = $conn->prepare("
                        INSERT INTO subject_dues (student_id, subject_id, status)
                        VALUES (?, ?, 'Pending')
                    ");
                    $stmt->bind_param("ii", $student_id, $sub_id);

                    if (!$stmt->execute()) {
                        throw new Exception("Subject Dues Error: " . $stmt->error);
                    }
                }

                // =========================
                // FINAL STATUS
                // =========================
                updateStudentFinalStatus($conn, $student_id);

                $inserted++;
            }

            fclose($handle);

            // =========================
            // COMMIT
            // =========================
            $conn->commit();

            $message = "✅ Class Created! Inserted: $inserted | Skipped: $skipped";

        } catch (Exception $e) {

            // =========================
            // ROLLBACK
            // =========================
            $conn->rollback();

            $error = "❌ Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Create Class</title>

<style>
*{margin:0;padding:0;box-sizing:border-box;}

body{
    font-family:'Segoe UI',sans-serif;
    background:linear-gradient(135deg,#0f172a,#1e3a8a);
    color:#e2e8f0;
}

.container{
    max-width:600px;
    margin:auto;
    padding:30px 20px;
}

/* HEADER */
.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.header h2{
    color:white;
}

/* FORM CARD */
.form-card{
    background:white;
    padding:25px;
    border-radius:14px;
    color:#1e293b;
    box-shadow:0 6px 18px rgba(0,0,0,0.15);
}

/* INPUTS */
.form-group{
    margin-bottom:18px;
}

label{
    font-size:13px;
    color:#64748b;
    display:block;
    margin-bottom:6px;
}

select,
input[type="text"],
input[type="file"]{
    width:100%;
    padding:10px;
    border:1px solid #e2e8f0;
    border-radius:8px;
    outline:none;
    font-size:14px;
}

/* BUTTON */
.btn{
    width:100%;
    padding:12px;
    background:#2563eb;
    color:white;
    border:none;
    border-radius:10px;
    font-size:15px;
    cursor:pointer;
    transition:0.2s;
}

.btn:hover{
    background:#1d4ed8;
}

/* ALERTS */
.success{
    background:#dcfce7;
    color:#15803d;
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
}

.error{
    background:#fee2e2;
    color:#dc2626;
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
}

/* TOP BUTTON */
.back-btn{
    background:#2563eb;
    color:white;
    padding:8px 14px;
    border-radius:8px;
    text-decoration:none;
    font-size:13px;
}

/* MOBILE */
@media(max-width:480px){
    .container{
        padding:20px 15px;
    }
}
</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->
    <div class="header">
        <h2>Create Class</h2>
        <a href="admin_dashboard.php" class="back-btn">Back</a>
    </div>

    <!-- FORM -->
    <div class="form-card">

        <?php if (!empty($message)) { ?>
            <div class="success"><?= $message ?></div>
        <?php } ?>

        <?php if (!empty($error)) { ?>
            <div class="error"><?= $error ?></div>
        <?php } ?>

        <form method="post" enctype="multipart/form-data">

            <div class="form-group">
                <label>Department</label>
                <select name="dept" required>
                    <option value="">Select Department</option>
                    <option value="1">Computer Engineering</option>
                    <option value="2">IT Engineering</option>
                    <option value="3">Mechanical Engineering</option>
                    <option value="4">Civil Engineering</option>
                </select>
            </div>

            <div class="form-group">
                <label>Year</label>
                <select name="year" required>
                    <option value="">Select Year</option>
                    <option value="1">F.E</option>
                    <option value="2">S.E</option>
                    <option value="3">T.E</option>
                    <option value="4">B.E</option>
                </select>
            </div>

            <div class="form-group">
                <label>Division</label>
                <select name="division" required>
                    <option value="">Select Division</option>
                    <option value="1">A</option>
                    <option value="2">B</option>
                </select>
            </div>

            <div class="form-group">
                <label>Subjects (comma separated)</label>
                <input type="text" name="subjects" placeholder="DBMS, CN, OS" required>
            </div>

            <div class="form-group">
                <label>Upload CSV File</label>
                <input type="file" name="file" required>
            </div>

            <button name="create_class" class="btn">Create Class</button>

        </form>

    </div>

</div>

</body>
</html>