<?php
session_start();
include "../config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Check login
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher'){
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// ✅ GET VALUES
$class_id = intval($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$subject_id = intval($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);

// ❌ Invalid access check
if($class_id == 0 || $subject_id == 0){
    die("❌ Invalid Access - Missing Class or Subject");
}

// 🔐 SECURITY CHECK
$checkAccess = mysqli_query($conn,"
    SELECT * FROM teacher_classes 
    WHERE teacher_id = '$teacher_id' 
    AND class_id = '$class_id' 
    AND subject_id = '$subject_id'
");

if(mysqli_num_rows($checkAccess) == 0){
    die("❌ Unauthorized Access");
}
// ================================
// BULK UPDATE (PRN RANGE)
// ================================
if(isset($_POST['bulk_update'])){

    $start_prn = trim($_POST['start_prn']);
    $end_prn   = trim($_POST['end_prn']);

    // Get students in range
    $students = mysqli_query($conn,"
        SELECT student_id FROM students 
        WHERE class_id='$class_id'
        AND prn BETWEEN '$start_prn' AND '$end_prn'
    ");

    $updated = 0;

    while($stu = mysqli_fetch_assoc($students)){
        $student_id = $stu['student_id'];

        // ✅ Set subject cleared
        mysqli_query($conn,"
            UPDATE subject_dues
            SET status='Cleared'
            WHERE student_id='$student_id'
            AND subject_id='$subject_id'
        ");

        // 🔄 Recalculate final dues
        $checkPending = mysqli_query($conn,"
            SELECT 1 FROM subject_dues 
            WHERE student_id='$student_id' 
            AND status='Pending'
        ");
        $subjectClear = (mysqli_num_rows($checkPending) == 0);

        $dueData = mysqli_fetch_assoc(mysqli_query($conn,"
            SELECT * FROM dues WHERE student_id='$student_id'
        "));
        $deptClear = (
            $dueData['library_due']==0 &&
            $dueData['accounts_due']==0 &&
            $dueData['lab_due']==0 &&
            $dueData['exam_due']==0
        );

        $statusFinal = ($subjectClear && $deptClear) ? 'Cleared' : 'Pending';

        mysqli_query($conn,"
            UPDATE dues SET status='$statusFinal' 
            WHERE student_id='$student_id'
        ");

        $updated++;
    }

    // 🔢 Updated counts
    $countCleared = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) c 
        FROM subject_dues sd 
        JOIN students st ON sd.student_id = st.student_id
        WHERE sd.subject_id='$subject_id' 
        AND sd.status='Cleared' 
        AND st.class_id='$class_id'
    "))['c'];

    $countPending = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) c 
        FROM subject_dues sd 
        JOIN students st ON sd.student_id = st.student_id
        WHERE sd.subject_id='$subject_id' 
        AND sd.status='Pending' 
        AND st.class_id='$class_id'
    "))['c'];

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'cleared_count' => $countCleared,
        'pending_count' => $countPending
    ]);
    exit();
}
// ================================
// TOGGLE STATUS
if(isset($_POST['toggle_status'])){
    $student_id = intval($_POST['student_id']);

    // 🔁 Get current status from DB (NOT from frontend)
    $statusQuery = mysqli_query($conn,"
        SELECT status FROM subject_dues 
        WHERE student_id='$student_id' AND subject_id='$subject_id'
    ");
   $current_row = mysqli_fetch_assoc($statusQuery);

if(!$current_row){
    echo json_encode([
        'success' => false,
        'error' => 'No record found in subject_dues'
    ]);
    exit();
}

$new_status = ($current_row['status'] === 'Pending') ? 'Cleared' : 'Pending';

    // 🔄 Update subject_dues
    mysqli_query($conn,"
        UPDATE subject_dues
        SET status='$new_status'
        WHERE student_id='$student_id'
        AND subject_id='$subject_id'
    ");

    // 🔄 Recalculate final dues
    $checkPending = mysqli_query($conn,"
        SELECT 1 FROM subject_dues 
        WHERE student_id='$student_id' 
        AND status='Pending'
    ");
    $subjectClear = (mysqli_num_rows($checkPending) == 0);

    $dueData = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT * FROM dues WHERE student_id='$student_id'
    "));
    $deptClear = (
        $dueData['library_due']==0 &&
        $dueData['accounts_due']==0 &&
        $dueData['lab_due']==0 &&
        $dueData['exam_due']==0
    );

    $statusFinal = ($subjectClear && $deptClear) ? 'Cleared' : 'Pending';
    mysqli_query($conn,"
        UPDATE dues SET status='$statusFinal' WHERE student_id='$student_id'
    ");

    // 🔢 Updated counts
    $countCleared = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) c 
        FROM subject_dues sd 
        JOIN students st ON sd.student_id = st.student_id
        WHERE sd.subject_id='$subject_id' 
        AND sd.status='Cleared' 
        AND st.class_id='$class_id'
    "))['c'];

    $countPending = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) c 
        FROM subject_dues sd 
        JOIN students st ON sd.student_id = st.student_id
        WHERE sd.subject_id='$subject_id' 
        AND sd.status='Pending' 
        AND st.class_id='$class_id'
    "))['c'];

    // 🔍 Detect AJAX
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
       strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){

        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'new_status' => $new_status,
            'cleared_count' => $countCleared,
            'pending_count' => $countPending
        ]);
        exit();
    }

    // 🔁 Fallback (non-JS)
    header("Location: teacher_view_class.php?class_id=$class_id&subject_id=$subject_id");
    exit();
}

// ================================
// SEARCH
// ================================
$search = $_GET['search'] ?? '';
$search = mysqli_real_escape_string($conn,$search);

// ================================
// FETCH STUDENTS
// ================================
$query = "
SELECT st.student_id, st.name, st.prn, sd.status
FROM students st
JOIN subject_dues sd 
    ON st.student_id = sd.student_id 
    AND sd.subject_id = '$subject_id'
WHERE st.class_id = '$class_id'
";

if(!empty($search)){
    $query .= " AND (st.name LIKE '%$search%' OR st.prn LIKE '%$search%')";
}

$result = mysqli_query($conn,$query);

// ================================
// COUNTS
// ================================
$total = mysqli_num_rows($result);

$cleared = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) c 
    FROM subject_dues 
    WHERE subject_id='$subject_id' 
    AND status='Cleared' 
    AND student_id IN (
        SELECT student_id FROM students WHERE class_id='$class_id'
    )
"))['c'];

$pending = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) c 
    FROM subject_dues 
    WHERE subject_id='$subject_id' 
    AND status='Pending' 
    AND student_id IN (
        SELECT student_id FROM students WHERE class_id='$class_id'
    )
"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Subject Students | SCOE Digital System</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
   <style>
    /* ================= RESET & GLOBAL ================= */
    *, *::before, *::after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
 
    :root {
        --primary:      #0f172a;
        --accent:       #c8a84b;
        --bg:           #f4f6f9;
        --card-bg:      #ffffff;
        --border:       #e8ecf0;
        --success-bg:   #ecfdf5;
        --success-text: #15803d;
        --danger-bg:    #fff1f2;
        --danger-text:  #be123c;
        --muted:        #94a3b8;
        --body-text:    #334155;
        --radius:       14px;
    }
 
    body {
        font-family: 'DM Sans', sans-serif;
        background: var(--bg);
        color: var(--body-text);
        line-height: 1.5;
        min-height: 100vh;
    }
 
    /* ================= NAVBAR ================= */
    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 2rem;
        background: #ffffff;
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 100;
    }
    .logo { display: flex; align-items: center; gap: 12px; }
    .logo img { height: 40px; width: auto; border-radius: 8px; }
    .logo span { font-weight: 700; font-size: 1rem; color: var(--primary); letter-spacing: -0.3px; }
 
    .nav-links { display: flex; gap: 1.6rem; align-items: center; }
    .nav-links a {
        text-decoration: none;
        font-weight: 500;
        font-size: 0.875rem;
        color: #64748b;
        transition: color 0.2s;
    }
    .nav-links a:hover { color: var(--primary); }
    .logout-btn {
        background: var(--primary) !important;
        color: white !important;
        padding: 0.4rem 1.1rem;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.825rem !important;
    }
    .menu-toggle {
        display: none;
        font-size: 1.4rem;
        cursor: pointer;
        background: none;
        border: none;
        color: var(--primary);
        padding: 4px;
        line-height: 1;
    }
 .search-container {
    margin-bottom: 2rem;
}
    /* ================= CONTAINER ================= */
    .container {
        max-width: 1100px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }
 
    /* ================= PAGE HEADER ================= */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        letter-spacing: -0.5px;
        border-left: 3px solid var(--accent);
        padding-left: 0.85rem;
        line-height: 1.2;
    }
    .class-subtitle {
        font-size: 0.825rem;
        color: var(--muted);
        margin-top: 0.3rem;
        margin-left: 1rem;
        font-weight: 400;
    }
    .back-btn {
        background: var(--primary);
        color: white;
        padding: 0.45rem 1.1rem;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.825rem;
        white-space: nowrap;
        transition: opacity 0.2s;
        display: inline-block;
    }

/* ================= MOBILE ================= */
@media (max-width: 768px) {
    .search-box {
        max-width: 100%;
    }
       }

  
    /* ================= STATS CARDS ================= */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.1rem 1.3rem;
    }
    .stat-label {
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin: 4px 0 0;
        letter-spacing: -1px;
        font-family: 'DM Mono', monospace;
    }
 
    /* ================= TABLE CARD ================= */
    .table-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    }
 
    table { width: 100%; border-collapse: collapse; }
    thead th {
        padding: 0.85rem 1.2rem;
        text-align: left;
        background: #f8fafc;
        color: var(--muted);
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        border-bottom: 1px solid var(--border);
    }
    tbody td {
        padding: 0.95rem 1.2rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.875rem;
        color: var(--body-text);
        vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: #fafbfc; }
 
    .td-name { font-weight: 600; color: var(--primary); font-size: 0.9rem; }
    .td-prn  { font-family: 'DM Mono', monospace; font-size: 0.8rem; color: var(--muted); }
 
    /* ================= STATUS BUTTONS ================= */
    .status-form { margin: 0; padding: 0; }
    .status-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 0.3rem 0.85rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: transform 0.15s, opacity 0.15s;
        font-family: inherit;
        white-space: nowrap;
        line-height: 1.4;
    }
    .status-btn::before {
        content: '';
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.7;
        flex-shrink: 0;
    }
    .status-btn:hover:not(:disabled) { transform: scale(1.04); opacity: 0.9; }
    .status-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .status-pending { background: var(--danger-bg);  color: var(--danger-text);  }
    .status-cleared { background: var(--success-bg); color: var(--success-text); }
 /* ================= STATS CARDS ================= */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.2rem;
    margin-bottom: 2rem;
}

.card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem 1.2rem;
    transition: box-shadow 0.2s;
}

.card:hover {
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.card-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.clear-btn {
    display: flex;
    align-items: center;
    justify-content: center;
}
       .search-box {
    width: 100%;
    max-width: 500px; /* or remove completely */
}
       .search-box input {
    flex: 1;
    min-width: 0;
}
       @media (max-width: 768px) {
    .search-box {
        max-width: 100%;
    }

    .bulk-bar {
        flex-direction: column;
        align-items: flex-start;
    }
}
.card-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
    margin: 6px 0 4px 0;
    line-height: 1.2;
}

    /* ================= EMPTY STATE ================= */
    .empty-cell {
        text-align: center;
        color: var(--muted);
        padding: 3rem 1rem !important;
        font-size: 0.875rem;
    }
 
    /* ================= MOBILE LIST ================= */
    .mobile-list { display: none; }
 
    /* ================= FOOTER ================= */
    .footer {
        background: #0f172a;
        color: #94a3b8;
        padding: 2.5rem 2rem 1.2rem;
        margin-top: 3rem;
    }
    .footer-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem;
        max-width: 1100px;
        margin: 0 auto;
    }
    .footer h3 {
        font-size: 0.85rem;
        font-weight: 700;
        margin-bottom: 0.85rem;
        color: #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .footer p, .footer a {
        font-size: 0.82rem;
        color: #94a3b8;
        margin-bottom: 0.4rem;
        display: block;
        text-decoration: none;
    }
    .footer a:hover { color: var(--accent); }
    .footer-bottom {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1.2rem;
        border-top: 1px solid #1e293b;
        font-size: 0.72rem;
        color: #64748b;
    }
 
    /* ================= RESPONSIVE ================= */
    @media (max-width: 768px) {
        .navbar { padding: 0.7rem 1rem; }
        .menu-toggle { display: block; }
        .nav-links {
            display: none;
            position: absolute;
            top: 62px;
            right: 1rem;
            background: white;
            flex-direction: column;
            width: 190px;
            padding: 0.85rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            z-index: 999;
            gap: 0.5rem;
        }
        .nav-links.active { display: flex; }
 
        .container { padding: 0 0.9rem; margin: 1.2rem auto; }
        .page-header { flex-direction: column; align-items: stretch; }
        .search-box { max-width: 100%; }
 
        .stats-grid { gap: 0.6rem; }
        .stat-card  { padding: 0.8rem 0.9rem; }
        .stat-value { font-size: 1.5rem; }
 
        /* Hide desktop table on mobile */
        table { display: none; }
 
        /* Show mobile list */
        .mobile-list { display: block; }
 
        .mobile-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.65rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }
        .mobile-list-header span {
            font-size: 0.67rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .mobile-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            gap: 0.75rem;
        }
        .mobile-row:last-child { border-bottom: none; }
 .back-btn {
    background: var(--primary);
    color: white;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    max-width: 100%;
    width: auto;
    white-space: nowrap;
    transition: opacity 0.2s;
}



/* 🔥 MOBILE FIX */
@media (max-width: 768px) {
    .back-btn {
        font-size: 0.75rem;
        padding: 0.4rem 0.75rem;
        align-self: flex-start;   /* prevents full width stretch */
    }
}
        .mobile-name-block { flex: 1; min-width: 0; }
        .mobile-short-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mobile-prn {
            font-size: 0.72rem;
            color: var(--muted);
            font-family: 'DM Mono', monospace;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mobile-status { flex-shrink: 0; }
        .mobile-empty {
            text-align: center;
            color: var(--muted);
            padding: 2.5rem 1rem;
            font-size: 0.875rem;
        }
 
        .footer-grid { grid-template-columns: 1fr; gap: 1.2rem; }
    }
/* ================= SEARCH FORM ================= */
.search-form {
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    max-width: 520px;
    width: 100%;
    height: 42px; /* 🔥 Fixed Height */

    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.25s ease;
}

.search-form:focus-within {
    border-color: #0f172a;
    box-shadow: 0 0 0 3px rgba(15,23,42,0.1);
}

/* ================= INPUT ================= */
.search-input {
    flex: 1;
    border: none;
    outline: none;
    padding: 0 1.2rem;
    height: 100%;
    font-size: 0.9rem;
    background: transparent;
    color: #334155;
}

/* ================= SEARCH BUTTON ================= */
.search-submit-btn {
    background: #0f172a;
    color: #ffffff;
    border: none;
    padding: 0 1.4rem;
    height: 100%;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;

    border-radius: 0 999px 999px 0;

    display: flex;
    align-items: center;
    justify-content: center;

    transition: all 0.2s ease;
}

.search-submit-btn:hover {
    background: #020617;
}

/* ================= CLEAR BUTTON ================= */
.search-clear-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.9rem;
    height: 100%;
    font-size: 0.95rem;
    color: #64748b;
    text-decoration: none;
    transition: 0.2s;
}

.search-clear-btn:hover {
    color: #ef4444;
}

/* ================= BULK PANEL ================= */
.bulk-panel {
    display: none;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;

    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;

    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

/* TEXT */
.bulk-text-wrap {
    font-size: 0.85rem;
    font-weight: 500;
    color: #374151;
}

/* BUTTON GROUP */
.bulk-btn-group {
    display: flex;
    gap: 10px;
}
/* Add spacing below search bar */
.search-container {
    margin-bottom: 2rem;
}
/* ================= DARK BUTTON (MAIN) ================= */
.bulk-btn-dark {
    background: #0f172a;
    color: #ffffff;
    border: none;
    padding: 7px 16px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;

    transition: all 0.2s ease;
}

.bulk-btn-dark:hover {
    background: #020617;
    transform: translateY(-1px);
}

/* ================= OUTLINE BUTTON ================= */
.bulk-btn-dark-outline {
    background: transparent;
    color: #0f172a;
    border: 1px solid #0f172a;
    padding: 7px 16px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;

    transition: all 0.2s ease;
}

.bulk-btn-dark-outline:hover {
    background: #0f172a;
    color: #ffffff;
}

/* ================= MOBILE ================= */
@media (max-width: 768px) {
    .search-form {
        max-width: 100%;
    }

    .bulk-panel {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .bulk-btn-group {
        width: 100%;
    }

    .bulk-btn-dark,
    .bulk-btn-dark-outline {
        flex: 1;
        justify-content: center;
    }
}
    </style>
</head>
<body>

<!-- ========== NAVBAR ========== -->
<div class="navbar">
    <div class="logo">
        <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg" alt="SCOE Logo">
        <span>SCOE Digital System</span>
    </div>
    <div class="menu-toggle" id="menuToggle">☰</div>
    <div class="nav-links" id="navMenu">
        <a href="../index.php">Home</a>
        <a href="teacher_dashboard.php">Dashboard</a>
      <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">
    <!-- HEADER with title and back button -->
    <div class="page-header">
        <div>
            <div class="page-title">Subject Students</div>
            <div class="class-subtitle">
                <?= htmlspecialchars($classInfo['dept_name'] ?? '') ?> 
                <?= htmlspecialchars($classInfo['year_name'] ?? '') ?> 
                <?= htmlspecialchars($classInfo['division_name'] ?? '') ?> 
                 Subject: <?= htmlspecialchars($subjectInfo['subject_name'] ?? '') ?>
            </div>
        </div>
        <a href="teacher_dashboard.php" class="back-btn"> Back to Dashboard</a>
    </div>

 <!-- 🔍 SEARCH + BULK ACTION -->
<div class="search-container">

    <form method="get" class="search-form">
        <input type="hidden" name="class_id" value="<?= $class_id ?>">
        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">

        <!-- 🔎 Input -->
        <input 
            type="text" 
            name="search" 
            id="search-field"
            class="search-input"
            placeholder="Search by name or PRN..." 
            value="<?= htmlspecialchars($search) ?>"
        >

        <!-- 🔍 Search Button -->
        <button type="submit" class="search-submit-btn">
            Search
        </button>

        <!-- ❌ Clear -->
        <?php if(!empty($search)): ?>
            <a href="teacher_view_class.php?class_id=<?= $class_id ?>&subject_id=<?= $subject_id ?>" 
               class="search-clear-btn">
               ✖
            </a>
        <?php endif; ?>
    </form>

    <!-- ✅ BULK ACTION BAR -->
    <div id="bulk-panel" class="bulk-panel">
        <div class="bulk-text-wrap">
            <span id="bulk-label">0 students selected</span>
        </div>

        <div class="bulk-btn-group">
            <button id="bulk-confirm-btn" class="bulk-btn-dark">
                ✔ Mark All as Cleared
            </button>

            <button id="bulk-cancel-btn" class="bulk-btn-dark-outline">
                ✖ Cancel
            </button>
        </div>
    </div>

</div>

    <!-- Stats Cards -->
    <div class="grid">
        <div class="card">
            <div class="card-label">Total Students</div>
            <div class="card-value"><?= $total ?></div>
        </div>
        <div class="card">
            <div class="card-label">Cleared</div>
           <div class="card-value" id="count-cleared"><?= $cleared ?></div>
        </div>
        <div class="card">
            <div class="card-label">Pending</div>
           <div class="card-value" id="count-pending"><?= $pending ?></div>
        </div>
    </div>

   <!-- Students Table + Mobile View -->
<div class="table-card" style="overflow-x:auto;">
    
    <!-- Desktop Table -->
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>PRN</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td data-label="Name"><?= htmlspecialchars($row['name']) ?></td>
                        <td data-label="PRN"><?= htmlspecialchars($row['prn']) ?></td>
                        <td data-label="Status">
                           <button 
    type="button"
    class="status-btn <?= $row['status'] == 'Pending' ? 'status-pending' : 'status-cleared' ?>"
    data-student-id="<?= $row['student_id'] ?>"
    data-class-id="<?= $class_id ?>"
    data-subject-id="<?= $subject_id ?>"
>
    <?= $row['status'] ?>
</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="empty-cell">No students found for this subject.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Mobile List -->
    <?php 
    // 🔁 Re-run query to avoid empty result issue
    $result_mobile = mysqli_query($conn, $query); 
    ?>

    <div class="mobile-list">
        <?php if(mysqli_num_rows($result_mobile) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result_mobile)): ?>
                <div class="mobile-row">
                    
                    <div class="mobile-name-block">
                        <div class="mobile-short-name"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="mobile-prn"><?= htmlspecialchars($row['prn']) ?></div>
                    </div>

                    <div class="mobile-status">
                        <button 
    type="button"
    class="status-btn <?= $row['status'] == 'Pending' ? 'status-pending' : 'status-cleared' ?>"
    
    data-student-id="<?= $row['student_id'] ?>"
    data-class-id="<?= $class_id ?>"
    data-subject-id="<?= $subject_id ?>"
>
    <?= $row['status'] ?>
</button>
                    </div>

                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="mobile-empty">No students found</div>
        <?php endif; ?>
    </div>

</div>
</div>
<!-- ========== FOOTER ========== -->
<footer class="footer">
    <div class="footer-grid">
        <div>
            <h3>About System</h3>
            <p>No Dues Clearance System — paperless, transparent tracking, real-time approval for SCOE students.</p>
        </div>
        <div>
            <h3>Contact & Support</h3>
            <p>awaisnsayyed13@gmail.com</p>
            <p>+91 9167641708</p>
            <p>Saraswati College of Engineering, Kharghar, Navi Mumbai</p>
            <p>Support: Mon-Fri, 9am – 5pm</p>
        </div>
    </div>
    <div class="footer-bottom">
        © 2025 Saraswati College of Engineering | No Dues System — seamless & smart
    </div>
</footer>

<!-- Mobile menu toggle script -->
<script>
const menuToggle = document.getElementById('menuToggle');
const navMenu = document.getElementById('navMenu');

// ================= MENU =================
function closeMenu() {
    if (navMenu && navMenu.classList.contains('active')) {
        navMenu.classList.remove('active');
    }
}

function toggleMenu() {
    if (navMenu) {
        navMenu.classList.toggle('active');
    }
}

if (menuToggle) {
    menuToggle.addEventListener('click', function(e){
        e.stopPropagation();
        toggleMenu();
    });
}
// ================= BULK STATUS UPDATE (FINAL CLEAN VERSION) =================

let bulkRange = null;

const searchForm = document.querySelector('.search-form');
const bulkBar = document.getElementById('bulk-panel');
const bulkBtn = document.getElementById('bulk-confirm-btn');
const bulkText = document.getElementById('bulk-label');

// 🔍 Detect PRN Range on Search
if (searchForm) {
    searchForm.addEventListener('submit', function(e){

        const input = searchForm.querySelector('input[name="search"]');
        if (!input) return;

        const value = input.value.trim();

        // Match range format: 10070 - 10110 OR 10070-10110
        const match = value.match(/^(\d+)\s*-\s*(\d+)$/);

        if (match) {
            e.preventDefault(); // stop normal search

            const start = match[1];
            const end   = match[2];

            // Store range globally
            bulkRange = { start, end };

            // Show UI
            if (bulkBar) bulkBar.style.display = 'flex';

            // Update message
            if (bulkText) {
                bulkText.textContent = `Selected PRN range: ${start} - ${end}`;
            }

        } else {
            // Normal search → hide bulk UI
            if (bulkBar) bulkBar.style.display = 'none';
            bulkRange = null;
        }
    });
}

// 🔘 Handle Bulk Button Click (ONLY ONE HANDLER)
if (bulkBtn) {
    bulkBtn.addEventListener('click', function(){

        if (!bulkRange) return;

        const { start, end } = bulkRange;

        // Confirmation
        if (!confirm(`Are you sure to mark PRN ${start} to ${end} as CLEARED?`)) {
            return;
        }

        const body = new URLSearchParams({
            bulk_update: '1',
            start_prn: start,
            end_prn: end,
            class_id: "<?= $class_id ?>",
            subject_id: "<?= $subject_id ?>"
        });

        // Optional: disable button to prevent double click
        bulkBtn.disabled = true;
        bulkBtn.textContent = "Processing...";

        fetch('teacher_view_class.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        })
        .then(res => res.json())
        .then(data => {

            if (!data.success) {
                alert('Bulk update failed');
                return;
            }

            // Update counts
            const clearedEl = document.getElementById('count-cleared');
            const pendingEl = document.getElementById('count-pending');

            if (clearedEl) clearedEl.textContent = data.cleared_count;
            if (pendingEl) pendingEl.textContent = data.pending_count;

            alert(`✅ ${data.updated} students marked as CLEARED`);

            location.reload();
        })
        .catch(err => {
            console.error('Bulk update error:', err);
            alert('Error in bulk update');
        })
        .finally(() => {
            // Restore button
            bulkBtn.disabled = false;
            bulkBtn.textContent = "Mark All as Cleared";
        });
    });
}

// 📱 Mobile Menu Close Handling
document.addEventListener('click', function(event) {
    if (!navMenu || !menuToggle) return;

    const isClickInsideNav = navMenu.contains(event.target);
    const isClickOnToggle = menuToggle.contains(event.target);

    if (!isClickInsideNav && !isClickOnToggle && navMenu.classList.contains('active') && window.innerWidth <= 768) {
        closeMenu();
    }
});

// 📱 Window Resize Fix
window.addEventListener('resize', function() {
    if (navMenu && window.innerWidth > 768 && navMenu.classList.contains('active')) {
        navMenu.classList.remove('active');
    }
});
// ================= STATUS TOGGLE =================
document.addEventListener('click', function(e){

    const btn = e.target.closest('.status-btn');
    if (!btn) return;

    // 🔴 STOP FORM SUBMISSION
    e.preventDefault();

    // Prevent double click
    if (btn.classList.contains('loading')) return;

    // ✅ GET DATA
    const studentId = btn.dataset.studentId;
    const classId   = btn.dataset.classId;
    const subjectId = btn.dataset.subjectId;

    // 🔴 VALIDATION
    if (!studentId || !classId || !subjectId) {
        console.error('Missing data attributes', { studentId, classId, subjectId });
        return;
    }

    // Add loading state to ALL buttons of same student
    const allBtns = document.querySelectorAll(`.status-btn[data-student-id="${studentId}"]`);
    allBtns.forEach(b => b.classList.add('loading'));

    // Build request body
    const body = new URLSearchParams({
        toggle_status: '1',
        student_id: studentId,
        class_id: classId,
        subject_id: subjectId,
    });

    fetch('teacher_view_class.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body.toString(),
    })
    .then(async function(res){
        if (!res.ok) throw new Error('Network error: ' + res.status);

        const text = await res.text();

        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server did not return JSON');
        }
    })
    .then(function(data){

        if (!data.success) {
            console.error('Toggle failed:', data.error);
            throw new Error('Toggle failed');
        }

        const newStatus = data.new_status;
        const isCleared = (newStatus === 'Cleared');

        allBtns.forEach(function(b){
            b.classList.remove('loading', 'status-pending', 'status-cleared');

            b.classList.add(isCleared ? 'status-cleared' : 'status-pending');
            b.textContent = newStatus;

            // ✅ Allow toggle both ways
            b.disabled = false;
        });

        // Update stats safely
        const clearedEl = document.getElementById('count-cleared');
        const pendingEl = document.getElementById('count-pending');

        if (clearedEl && data.cleared_count !== undefined) {
            clearedEl.textContent = data.cleared_count;
        }

        if (pendingEl && data.pending_count !== undefined) {
            pendingEl.textContent = data.pending_count;
        }

    })
    .catch(function(err){
        console.error('AJAX error:', err);

        // Remove loading state
        document.querySelectorAll('.status-btn.loading')
            .forEach(b => b.classList.remove('loading'));
    });

});
    const cancelBtn = document.getElementById('bulk-cancel-btn');

if (cancelBtn) {
    cancelBtn.addEventListener('click', function(){
        bulkBar.style.display = 'none';
        bulkRange = null;
    });
}
</script>
</body>
</html>