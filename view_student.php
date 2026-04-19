<?php
// ✅ SHOW ERRORS
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../config.php";

// ✅ VALIDATION
if(!isset($_GET['student_id']) || $_GET['student_id'] == ''){
    die("❌ Invalid Student ID");
}

$student_id = mysqli_real_escape_string($conn, $_GET['student_id']);
$class_id = $_GET['class_id'] ?? '';

// ✅ STUDENT INFO
$student_q = mysqli_query($conn, "SELECT * FROM students WHERE student_id='$student_id'");
if(mysqli_num_rows($student_q) == 0){
    die("❌ Student not found");
}
$student = mysqli_fetch_assoc($student_q);

// 🔥 UPDATE DUES (same logic as student_dashboard)
if(function_exists('updateCombinedDues')){
    updateCombinedDues($conn, $student_id);
}

// ✅ FETCH DUES
$res = mysqli_query($conn, "SELECT * FROM combined_dues WHERE student_id='$student_id'");
if(!$res){
    die("❌ Query Error: " . mysqli_error($conn));
}

$data = mysqli_fetch_assoc($res);

// ✅ FALLBACK
if(!$data){
    $data = [
        'combined_status' => 'Pending',
        'library_due' => 0,
        'accounts_due' => 0,
        'lab_due' => 0,
        'exam_due' => 0,
        'subject_details' => '[]'
    ];
}

// ✅ JSON DECODE (SAME AS student_dashboard)
$subject_details = [];
if(!empty($data['subject_details'])){
    $decoded = json_decode($data['subject_details'], true);
    if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
        $subject_details = $decoded;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Student Dues | SCOE Digital System</title>
    <!-- Google Fonts: Inter -->
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
            line-height: 1.5;
            font-weight: 400;
        }

        /* ----- COLOR SYSTEM (identical to accounts dashboard) ----- */
        :root {
            --primary: #1e293b;
            --accent: #d4af37;
            --bg: #f9fafb;
            --card-bg: #ffffff;
            --border: #e5e7eb;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --text-muted: #6b7280;
            --text-dark: #1e293b;
        }

        /* ----- NAVBAR (exactly as accounts dashboard) ----- */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 2rem;
            background: #ffffff;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo img {
            height: 42px;
            width: auto;
            border-radius: 8px;
        }

        .logo span {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary);
            letter-spacing: -0.2px;
        }

        .nav-links {
            display: flex;
            gap: 1.8rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            color: #4b5563;
            transition: color 0.2s ease;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .logout-btn {
            background: var(--primary);
            color: white !important;
            padding: 0.45rem 1.2rem;
            border-radius: 30px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .logout-btn:hover {
            background: #0f172a;
            color: white !important;
        }

        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--primary);
        }

        /* ----- MAIN CONTAINER ----- */
        .container {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Header section with title and back button */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.8rem;
        }

        .page-title {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: -0.3px;
            border-left: 3px solid var(--accent);
            padding-left: 1rem;
        }

        .back-btn {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
        }

        .back-btn:hover {
            background: #0f172a;
            transform: translateY(-1px);
        }

        /* Student info card */
        .student-info {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .student-info p {
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .student-info strong {
            font-weight: 600;
            color: var(--primary);
        }

        /* Cards grid for dues */
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
            transition: box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .card-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .card-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 6px 0 4px 0;
            line-height: 1.2;
        }

        .card-status {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #e0f2e9;
            color: #166534;
        }

        .card-status-pending {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* Table card */
        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.2rem;
            overflow-x: auto;
        }

        .table-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
            letter-spacing: -0.2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 300px;
        }

        th, td {
            padding: 0.85rem 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            font-size: 0.85rem;
        }

        td {
            color: #4b5563;
            font-size: 0.9rem;
        }

        .empty-row td {
            text-align: center;
            color: var(--text-muted);
            padding: 2rem;
        }

        /* ----- FOOTER (identical to accounts dashboard) ----- */
        .footer {
            background: #111827;
            color: #9ca3af;
            padding: 2.5rem 2rem 1.2rem;
            margin-top: 3rem;
            border-top: 1px solid #1f2937;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            max-width: 1280px;
            margin: 0 auto;
        }

        .footer h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #e5e7eb;
        }

        .footer p, .footer a {
            font-size: 0.85rem;
            color: #9ca3af;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
        }

        .footer a:hover {
            color: var(--accent);
        }

        .footer-bottom {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.2rem;
            border-top: 1px solid #1f2937;
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* ----- RESPONSIVE (matches accounts dashboard) ----- */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }
            .menu-toggle {
                display: block;
            }
            .nav-links {
                display: none;
                position: absolute;
                top: 68px;
                right: 1rem;
                background: #ffffff;
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                width: 200px;
                padding: 1rem 1.2rem;
                border-radius: 12px;
                border: 1px solid var(--border);
                box-shadow: 0 8px 20px rgba(0,0,0,0.05);
                z-index: 999;
            }
            .nav-links.active {
                display: flex;
            }
            .container {
                padding: 0 1rem;
            }
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            .back-btn {
                text-align: center;
            }
            .student-info {
                flex-direction: column;
                gap: 0.5rem;
            }
            .grid {
                grid-template-columns: 1fr;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            /* Mobile table: each td becomes flex */
            table, thead {
                display: none;
            }
            tbody tr {
                display: block;
                background: #f8fafc;
                margin-bottom: 12px;
                padding: 12px;
                border-radius: 10px;
            }
            tbody td {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #e2e8f0;
            }
            tbody td:last-child {
                border-bottom: none;
            }
            tbody td::before {
                content: attr(data-label);
                font-weight: bold;
                color: #334155;
            }
        }
    </style>
</head>
<body>

<!-- ========== NAVBAR (EXACT SAME AS ACCOUNTS DASHBOARD) ========== -->
<div class="navbar">
    <div class="logo">
        <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg" alt="SCOE Logo">
        <span>SCOE Digital System</span>
    </div>
    <div class="menu-toggle" id="menuToggle">☰</div>
    <div class="nav-links" id="navMenu">
        <a href="../index.php">Home</a>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="?logout=true" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">
    <!-- HEADER with back button -->
    <div class="page-header">
        <div class="page-title">Student Dues Details</div>
        <a href="view_class.php?class_id=<?= urlencode($class_id) ?>" class="back-btn"> Back to Class</a>
    </div>

    <!-- Student Information Card -->
    <div class="student-info">
        <p><strong>Name:</strong> <?= htmlspecialchars($student['name']) ?></p>
        <p><strong>PRN:</strong> <?= htmlspecialchars($student['prn']) ?></p>
        <p><strong>Student ID:</strong> <?= htmlspecialchars($student_id) ?></p>
    </div>

    <!-- Dues Cards Grid -->
    <div class="grid">
        <div class="card">
            <div class="card-label">Library Due</div>
            <div class="card-value">₹<?= number_format($data['library_due'], 0) ?></div>
            <div class="card-status <?= $data['library_due'] == 0 ? '' : 'card-status-pending' ?>">
                <?= $data['library_due'] == 0 ? 'Cleared' : 'Pending' ?>
            </div>
        </div>
        <div class="card">
            <div class="card-label">Accounts Due</div>
            <div class="card-value">₹<?= number_format($data['accounts_due'], 0) ?></div>
            <div class="card-status <?= $data['accounts_due'] == 0 ? '' : 'card-status-pending' ?>">
                <?= $data['accounts_due'] == 0 ? 'Cleared' : 'Pending' ?>
            </div>
        </div>
        <div class="card">
            <div class="card-label">Lab Due</div>
            <div class="card-value">₹<?= number_format($data['lab_due'], 0) ?></div>
            <div class="card-status <?= $data['lab_due'] == 0 ? '' : 'card-status-pending' ?>">
                <?= $data['lab_due'] == 0 ? 'Cleared' : 'Pending' ?>
            </div>
        </div>
        <div class="card">
            <div class="card-label">Student Section Due</div>
            <div class="card-value">₹<?= number_format($data['exam_due'], 0) ?></div>
            <div class="card-status <?= $data['exam_due'] == 0 ? '' : 'card-status-pending' ?>">
                <?= $data['exam_due'] == 0 ? 'Cleared' : 'Pending' ?>
            </div>
        </div>
        <div class="card">
            <div class="card-label">Overall Status</div>
            <div class="card-value"><?= htmlspecialchars($data['combined_status']) ?></div>
            <div class="card-status <?= $data['combined_status'] == 'Cleared' ? '' : 'card-status-pending' ?>">
                <?= $data['combined_status'] ?>
            </div>
        </div>
    </div>

    <!-- Subject-wise Dues Table -->
    <div class="table-card">
        <h3>Subject-wise Clearance Status</h3>
        <table>
            <thead>
                <tr>
                    <th>Subject Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($subject_details)): ?>
                    <?php foreach($subject_details as $s): ?>
                        <tr>
                            <td data-label="Subject"><?= htmlspecialchars($s['subject_name'] ?? '—') ?></td>
                            <td data-label="Status">
                                <span class="card-status <?= strtolower($s['status'] ?? '') == 'cleared' ? '' : 'card-status-pending' ?>">
                                    <?= htmlspecialchars($s['status'] ?? 'Pending') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="empty-row">
                        <td colspan="2">No subject data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========== FOOTER (EXACT MATCH) ========== -->
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

    function closeMenu() {
        if (navMenu.classList.contains('active')) {
            navMenu.classList.remove('active');
        }
    }

    function toggleMenu() {
        navMenu.classList.toggle('active');
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', toggleMenu);
    }

    document.addEventListener('click', function(event) {
        const isClickInsideNav = navMenu.contains(event.target);
        const isClickOnToggle = menuToggle.contains(event.target);
        if (!isClickInsideNav && !isClickOnToggle && navMenu.classList.contains('active') && window.innerWidth <= 768) {
            closeMenu();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && navMenu.classList.contains('active')) {
            navMenu.classList.remove('active');
        }
    });
</script>
</body>
</html>