<?php
session_start();
include "../config.php";

// 🔥 Force visible errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔓 Logout
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// 🔐 Admin check
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../login.php");
    exit();
}

// ✅ Allowed departments
$allowed = ['accounts','library','lab','exam'];

$message = "";

if(isset($_POST['upload']))
{
    $dept_type = $_POST['type'];

    if(!in_array($dept_type, $allowed)){
        $message = "❌ Invalid department";
    }
    else if($_FILES['file']['error'] != 0){
        $message = "❌ File upload error";
    }
    else
    {
        $column = $dept_type . "_due";

        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file, "r");

        if(!$handle){
            $message = "❌ Cannot open file";
        }
        else
        {
            fgetcsv($handle); // Skip header

            $updated = 0;
            $skipped = 0;

            while(($data = fgetcsv($handle, 1000, ",")) !== FALSE)
            {
                if(count($data) < 2){
                    $skipped++;
                    continue;
                }

                $prn = trim($data[0]);
                $amount = (int) trim($data[1]);

                if($prn == ""){
                    $skipped++;
                    continue;
                }

                // 🔍 Find student
                $res = mysqli_query($conn, "SELECT student_id FROM students WHERE prn='$prn'");
                if($res && mysqli_num_rows($res) > 0)
                {
                    $student = mysqli_fetch_assoc($res);
                    $sid = $student['student_id'];

                    // ✅ Update ONLY the specific department due column
                    $sql = "
                        UPDATE dues SET $column = '$amount'
                        WHERE student_id='$sid'
                    ";
                    mysqli_query($conn, $sql);

                    // ✅ Recalculate total and update status
                    $q = mysqli_query($conn, "
                        SELECT library_due, accounts_due, lab_due, exam_due 
                        FROM dues WHERE student_id='$sid'
                    ");

                    if($q && mysqli_num_rows($q) > 0){
                        $d = mysqli_fetch_assoc($q);
                        $total = (int)$d['library_due'] + (int)$d['accounts_due'] + (int)$d['lab_due'] + (int)$d['exam_due'];
                        $status = ($total == 0) ? 'Cleared' : 'Pending';

                        mysqli_query($conn, "UPDATE dues SET status='$status' WHERE student_id='$sid'");
                    }

                    $updated++;
                }
                else
                {
                    $skipped++;
                }
            }

            fclose($handle);

            $message = "✅ Upload Done! Updated: $updated | Skipped: $skipped";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Upload | SCOE System</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f9fafb;
    color: #1e293b;
}

/* ================= NAVBAR ================= */
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 2rem;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo img {
    height: 42px;
    border-radius: 8px;
}

.logo span {
    font-weight: 700;
    color: #1e293b;
}

.nav-links {
    display: flex;
    gap: 1.5rem;
    align-items: center;
}

.nav-links a {
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    color: #4b5563;
}

.nav-links a:hover {
    color: #1e293b;
}

.logout-btn {
    background: #1e293b;
    color: white !important;
    padding: 0.45rem 1rem;
    border-radius: 30px;
}

/* ================= CONTAINER ================= */
.container {
    max-width: 600px;
    margin: 3rem auto;
    padding: 0 1.5rem;
}

/* TITLE */
.page-title {
    font-size: 1.6rem;
    font-weight: 600;
    border-left: 3px solid #d4af37;
    padding-left: 1rem;
    margin-bottom: 1.5rem;
}

/* CARD */
.card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 1.8rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.03);
}

/* FORM */
form select,
form input[type="file"] {
    width: 100%;
    padding: 0.8rem;
    margin-bottom: 1rem;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    font-family: 'Inter', sans-serif;
    outline: none;
    font-size: 0.9rem;
}

form select:focus,
form input:focus {
    border-color: #1e293b;
}

/* BUTTON */
form button {
    width: 100%;
    padding: 0.8rem;
    border: none;
    border-radius: 30px;
    background: #1e293b;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}

form button:hover {
    background: #0f172a;
    transform: translateY(-2px);
}

/* MESSAGE */
.msg {
    margin-top: 1rem;
    padding: 0.9rem;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    font-size: 0.9rem;
    color: #1e293b;
}

/* ================= FOOTER ================= */
.footer {
    background: #111827;
    color: #9ca3af;
    padding: 2rem;
    margin-top: 4rem;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    max-width: 1100px;
    margin: auto;
}

.footer h3 {
    color: #fff;
    margin-bottom: 1rem;
}

.footer-bottom {
    text-align: center;
    margin-top: 2rem;
    border-top: 1px solid #1f2937;
    padding-top: 1rem;
    font-size: 0.8rem;
}

/* ================= RESPONSIVE ================= */
@media(max-width:768px){
    .navbar {
        flex-direction: column;
        gap: 1rem;
    }

    .container {
        margin: 2rem auto;
    }

    .footer-grid {
        grid-template-columns: 1fr;
        text-align: center;
    }
}
</style>
</head>

<body>

<!-- ================= NAVBAR ================= -->
<div class="navbar">
    <div class="logo">
        <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg">
        <span>SCOE Digital System</span>
    </div>

    <div class="nav-links">
        <a href="../index.php">Home</a>
        <a href="accounts_dashboard.php">Dashboard</a>
        <a href="department_upload.php">Uploads</a>
        <a href="?logout=true" class="logout-btn">Logout</a>
    </div>
</div>

<!-- ================= MAIN ================= -->
<div class="container">

    <div class="page-title">Department Upload</div>

    <div class="card">

        <form method="post" enctype="multipart/form-data">

            <select name="type" required>
                <option value="">Select Department</option>
                <option value="accounts">Accounts</option>
                <option value="library">Library</option>
                <option value="lab">Lab</option>
                <option value="exam">Student Section</option>
            </select>

            <input type="file" name="file" required>

            <button type="submit" name="upload">Upload File</button>
        </form>

        <?php if($message != ""): ?>
            <div class="msg"><?php echo $message; ?></div>
        <?php endif; ?>

    </div>
</div>

<!-- ================= FOOTER ================= -->
<footer class="footer">
    <div class="footer-grid">
        <div>
            <h3>About System</h3>
            <p>No Dues System for centralized student clearance tracking.</p>
        </div>
        <div>
            <h3>Support</h3>
            <p>support@scoe.edu.in</p>
            <p>Kharghar, Navi Mumbai</p>
        </div>
    </div>

    <div class="footer-bottom">
        © 2026 SCOE Digital System
    </div>
</footer>

</body>
</html>