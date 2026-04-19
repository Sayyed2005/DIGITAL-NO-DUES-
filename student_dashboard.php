<?php
session_start();
include "../config.php";

// 🔓 Logout
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// 🔐 Student check
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'student'){
    header("Location: ../login.php");
    exit();
}
// ===============================
// ✅ STEP 1: GET USER ID
// ===============================
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    die("Invalid session");
}

// ===============================
// ✅ STEP 2: MAP TO STUDENT_ID (CORRECT WAY)
// ===============================
$stmt = $conn->prepare("
    SELECT student_id 
    FROM users 
    WHERE user_id = ? AND role = 'student'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("Student mapping not found");
}

$row = $result->fetch_assoc();
$student_id = $row['student_id'];

// Extra safety
if (!$student_id) {
    die("Invalid student mapping");
}

// ===============================
// 🔥 STEP 3: UPDATE COMBINED DUES
// ===============================
updateCombinedDues($conn, $student_id);

// ===============================
// ✅ STEP 4: FETCH COMBINED DATA (SECURE)
// ===============================
$stmt = $conn->prepare("
    SELECT * FROM combined_dues WHERE student_id = ?
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result) {
    die("Query Error: " . $conn->error);
}

$data = $result->fetch_assoc();

// ===============================
// ✅ STEP 5: FALLBACK DATA
// ===============================
if (!$data) {
    $data = [
        'combined_status' => 'Pending',
        'library_due'     => 0,
        'accounts_due'    => 0,
        'lab_due'         => 0,
        'exam_due'        => 0,
        'subject_details' => '[]'
    ];
}

// ===============================
// ✅ STEP 6: SAFE JSON DECODE
// ===============================
$subject_details = [];

if (!empty($data['subject_details'])) {
    $decoded = json_decode($data['subject_details'], true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $subject_details = $decoded;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard | SCOE Digital System</title>
    <!-- Google Fonts: Inter only -->
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

        /* ----- COLOR SYSTEM (minimal, data-first) ----- */
        :root {
            --primary: #1e293b;
            --accent: #d4af37;     /* gold — used VERY sparingly */
            --bg: #f9fafb;
            --card-bg: #ffffff;
            --border: #e5e7eb;
            --success: #16a34a;
            --warning: #f59e0b;
            --text-muted: #6b7280;
            --text-dark: #1e293b;
        }

        /* ----- NAVBAR (clean, no glass, no heavy icons) ----- */
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

        /* ----- MAIN CONTAINER & TITLE ----- */
        .container {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 1.6rem;
            font-weight: 600;
            margin-bottom: 1.8rem;
            color: var(--primary);
            letter-spacing: -0.3px;
            border-left: 3px solid var(--accent);
            padding-left: 1rem;
        }

        /* ----- GRID (clean spacing) ----- */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2.5rem;
        }

        /* ----- CARD DESIGN (no shadows, flat, subtle border) ----- */
        /* ===== COMPACT CARD STYLES (reduced size) ===== */
.card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.9rem 1rem;    /* reduced from 1.2rem */
    box-shadow: none;
    transition: box-shadow 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.main-status-card {
    border: 2px solid var(--accent);
    background: var(--card-bg);
}

.card-label {
    font-size: 0.7rem;       /* smaller label */
    font-weight: 600;
    color: var(--text-muted);
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.card-value {
    font-size: 1.5rem;       /* reduced from 2rem */
    font-weight: 700;
    color: var(--primary);
    margin: 4px 0 4px 0;     /* tighter spacing */
    line-height: 1.2;
}

.card-status {
    font-size: 0.7rem;       /* smaller status badge */
    font-weight: 600;
    display: inline-block;
    padding: 0.15rem 0.6rem;
    border-radius: 20px;
    background: transparent;
}

.success {
    color: var(--success);
    background: #ecfdf5;
}

.warning {
    color: var(--warning);
    background: #fffbeb;
}
        .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.9rem;   /* was 1.2rem */
    margin-bottom: 2rem;
}
        /* ----- TABLE CARD (minimal, border only) ----- */
        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.2rem;
            box-shadow: none;
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

        /* ----- FOOTER (simple dark, no heavy gold) ----- */
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

        /* ----- RESPONSIVE (mobile first) ----- */
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
            .grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .container {
                padding: 0 1rem;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .card-value {
                font-size: 1.7rem;
            }
        }

        /* utility: remove all icons backgrounds, no extra decorations */
        i, .fas, .far {
            /* no icon backgrounds, no forced styles; we barely use icons */
        }
    </style>
</head>
<body>

<!-- ========== NAVBAR (no decorative icons, clean) ========== -->
<div class="navbar">
    <div class="logo">
        <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg" alt="SCOE Logo">
        <span>SCOE Digital System</span>
    </div>
    <div class="menu-toggle" id="menuToggle">
        ☰
    </div>
    <div class="nav-links" id="navMenu">
        <a href="../index.php">Home</a>
        <a href="#" style="font-weight: 600;">Dues</a>
        <a href="receipt.php">Receipt</a>
        <a href="?logout=true" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">
    <div class="page-title">
        Student Dashboard
    </div>

    <!-- ========== CARDS GRID (refactored: no icons, no backgrounds, minimal) ========== -->
    <div class="grid">
        <!-- MAIN STATUS CARD (only one with gold border, stands out) -->
        <div class="card main-status-card">
            <div class="card-label">Clearance Status</div>
            <div class="card-value">
                <?php 
                    $overall = $data['combined_status'] ?? 'Pending';
                    echo htmlspecialchars($overall);
                ?>
            </div>
            <!-- no extra badge, clean -->
        </div>

        <!-- LIBRARY CARD -->
        <div class="card">
            <div class="card-label">Library</div>
            <div class="card-value">
                <?php
                    $libDue = isset($data['library_due']) ? floatval($data['library_due']) : 0;
                    echo '₹' . number_format($libDue, 0);
                ?>
            </div>
            <div class="card-status <?= $libDue == 0 ? 'success' : 'warning' ?>">
                <?= $libDue == 0 ? 'Cleared' : 'Pending' ?>
            </div>
        </div>

        <!-- ACCOUNTS CARD -->
        <div class="card">
            <div class="card-label">Accounts</div>
            <div class="card-value">
                <?php
                    $accDue = isset($data['accounts_due']) ? floatval($data['accounts_due']) : 0;
                    echo '₹' . number_format($accDue, 0);
                ?>
            </div>
            <div class="card-status <?= $accDue == 0 ? 'success' : 'warning' ?>">
                <?= $accDue == 0 ? 'Cleared' : 'Pending' ?>
            </div>
        </div>

        <!-- LAB CARD -->
        <div class="card">
            <div class="card-label">Lab</div>
            <div class="card-value">
                <?php
                    $labDue = isset($data['lab_due']) ? floatval($data['lab_due']) : 0;
                    echo '₹' . number_format($labDue, 0);
                ?>
            </div>
            <div class="card-status <?= $labDue == 0 ? 'success' : 'warning' ?>">
                <?= $labDue == 0 ? 'Cleared' : 'Pending' ?>
            </div>
        </div>

        <!-- STUDENT SECTION (exam) CARD -->
        <div class="card">
            <div class="card-label">Student Section</div>
            <div class="card-value">
                <?php
                    $examDue = isset($data['exam_due']) ? floatval($data['exam_due']) : 0;
                    echo '₹' . number_format($examDue, 0);
                ?>
            </div>
            <div class="card-status <?= $examDue == 0 ? 'success' : 'warning' ?>">
                <?= $examDue == 0 ? 'Cleared' : 'Pending' ?>
            </div>
        </div>
    </div>

    <!-- ========== SUBJECT TABLE (minimal, no icons, clean typography) ========== -->
    <div class="table-card">
        <h3>Subjects — Term Work Status</h3>
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($subject_details)): ?>
                    <?php foreach($subject_details as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['subject_name'] ?? '—') ?></td>
                            <td>
                                <span class="card-status <?= strtolower($s['status'] ?? '') === 'cleared' ? 'success' : 'warning' ?>">
                                    <?= htmlspecialchars($s['status'] ?? 'Pending') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" style="color: #6b7280;">No subject records available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========== FOOTER (simple, no heavy gold, minimal icons removed) ========== -->
<footer class="footer">
    <div class="footer-grid">
        <div>
            <h3>About System</h3>
            <p>No Dues Clearance System — paperless, transparent tracking, real-time approval for SCOE students.</p>
        </div>
        <div>
            <h3>Contact & Support</h3>
            <p>awais1sayyed@gmail.com</p>
            <p>+91 9326065474</p>
            <p>Saraswati College of Engineering, Kharghar, Navi Mumbai</p>
            <p>Support: Mon-Fri, 9am – 5pm</p>
        </div>
    </div>
    <div class="footer-bottom">
        © 2025 Saraswati College of Engineering | No Dues System — seamless & smart
    </div>
</footer>

<script>
    // Mobile menu toggle (no extra icons, simple)
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