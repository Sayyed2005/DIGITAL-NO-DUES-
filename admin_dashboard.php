<?php
session_start();
include "../config.php";

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

// =========================
// DELETE CLASS SAFELY
// =========================
if(isset($_GET['delete_class'])){
    $class_id = intval($_GET['delete_class']);
    if($class_id > 0){
        // Fetch students of class
        $students = $conn->query("SELECT student_id FROM students WHERE class_id='$class_id'");
        if($students){
            while($s = $students->fetch_assoc()){
                $sid = $s['student_id'];
                // Delete student user
                $conn->query("DELETE FROM users WHERE password='$sid' AND role='student'");
                // Delete student dues
                $conn->query("DELETE FROM dues WHERE student_id='$sid'");
                // Delete subject dues
                $conn->query("DELETE FROM subject_dues WHERE student_id='$sid'");
            }
        }

        // Delete class related records
        $conn->query("DELETE FROM students WHERE class_id='$class_id'");
        $conn->query("DELETE FROM class_subjects WHERE class_id='$class_id'");
        $conn->query("DELETE FROM teacher_classes WHERE class_id='$class_id'");
        $conn->query("DELETE FROM classes WHERE class_id='$class_id'");

        header("Location: admin_dashboard.php");
        exit();
    }
}

// =========================
// FETCH COUNTS FROM DUES ONLY
// =========================
$total = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];
$cleared = $conn->query("SELECT COUNT(*) as c FROM dues WHERE status='Cleared'")->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) as c FROM dues WHERE status='Pending'")->fetch_assoc()['c'];

// =========================
// FETCH CLASSES
// =========================
// 🔍 SEARCH
$search = "";
$where = "";

if(isset($_GET['search'])){
    $search = mysqli_real_escape_string($conn, $_GET['search']);

    $where = "WHERE d.dept_name LIKE '%$search%' 
              OR y.year_name LIKE '%$search%' 
              OR dv.division_name LIKE '%$search%'";
}
$classes = $conn->query("
SELECT c.class_id, d.dept_name, y.year_name, dv.division_name
FROM classes c
LEFT JOIN departments d ON c.dept_id=d.dept_id
LEFT JOIN years y ON c.year_id=y.year_id
LEFT JOIN divisions dv ON c.division_id=dv.division_id
$where
ORDER BY c.class_id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Dashboard | SCOE Digital System</title>
    <!-- Google Fonts: Inter (matching index.php) -->
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

        /* ----- COLOR SYSTEM (identical to other dashboards) ----- */
        :root {
            --primary: #1e293b;
            --accent: #d4af37;     /* gold — used sparingly for accents */
            --bg: #f9fafb;
            --card-bg: #ffffff;
            --border: #e5e7eb;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --text-muted: #6b7280;
            --text-dark: #1e293b;
        }

        /* ----- NAVBAR (exactly as index.php & other dashboards) ----- */
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

        .nav-links .active {
            color: var(--primary);
            font-weight: 600;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 4px;
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

        /* ----- MAIN CONTAINER & HEADER (search + title + create button) ----- */
        .container {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .dashboard-header {
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

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Search box styled like index inputs */
        .search-box {
            display: flex;
            background: white;
            border: 1px solid var(--border);
            border-radius: 40px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .search-box input {
            border: none;
            padding: 0.6rem 1rem;
            outline: none;
            width: 240px;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
        }

        .search-box button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 1.2rem;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.8rem;
            transition: background 0.2s;
        }

        .search-box button:hover {
            background: #0f172a;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            background: var(--primary);
            color: white;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #0f172a;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .btn-danger:hover {
            background: #fecaca;
            transform: translateY(-1px);
        }

        /* ----- CARDS GRID (stats) ----- */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2.5rem;
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
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin: 6px 0 4px 0;
            line-height: 1.2;
        }

        /* ----- CLASS CARDS GRID ----- */
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.2rem;
            margin-top: 1rem;
        }

        .class-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.2rem;
            transition: box-shadow 0.2s ease;
            text-align: center;
        }

        .class-card:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .class-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary);
            margin-bottom: 0.8rem;
            line-height: 1.4;
        }

        .class-actions {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .empty-state {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* ----- FOOTER (identical to index.php & other dashboards) ----- */
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

        /* ----- RESPONSIVE (matches index behavior) ----- */
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
            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }
            .search-box {
                width: 100%;
            }
            .search-box input {
                width: 100%;
            }
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .card-value {
                font-size: 1.7rem;
            }
            .class-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .class-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- ========== NAVBAR (EXACT SAME AS ACCOUNTS/EXAM DASHBOARDS) ========== -->
<div class="navbar">
    <div class="logo">
        <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg" alt="SCOE Logo">
        <span>SCOE Digital System</span>
    </div>
    <div class="menu-toggle" id="menuToggle">☰</div>
    <div class="nav-links" id="navMenu">
        <a href="../index.php">Home</a>
        <a href="admin_dashboard.php" class="active">Dashboard</a>
        <a href="department_upload.php">Uploads</a>
        <a href="?logout=true" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">
    <!-- HEADER with title, search, create class button -->
    <div class="dashboard-header">
        <div class="page-title">Admin Dashboard</div>
        <div class="header-actions">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Search by dept, year, division..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Search</button>
            </form>
            <a href="create_class.php" class="btn btn-primary"> Create Class</a>
        </div>
    </div>

    <!-- STATS CARDS (dynamic from database) -->
    <div class="grid">
        <div class="card">
            <div class="card-label">Total Students</div>
            <div class="card-value"><?= $total ?></div>
        </div>
        <div class="card">
            <div class="card-label">Cleared (Overall)</div>
            <div class="card-value"><?= $cleared ?></div>
        </div>
        <div class="card">
            <div class="card-label">Pending Dues</div>
            <div class="card-value"><?= $pending ?></div>
        </div>
    </div>

    <!-- CLASS LIST SECTION -->
    <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 1rem; color: var(--primary);">Manage Classes</h3>
    <div class="class-grid">
        <?php if($classes && $classes->num_rows > 0): ?>
            <?php while($c = $classes->fetch_assoc()): ?>
                <div class="class-card">
                    <div class="class-title">
                        <?= htmlspecialchars($c['dept_name'] ?? 'Unknown Dept') ?><br>
                        <?= htmlspecialchars($c['year_name'] ?? '') ?> - <?= htmlspecialchars($c['division_name'] ?? '') ?>
                    </div>
                    <div class="class-actions">
                        <a href="view_class.php?class_id=<?= $c['class_id'] ?>" class="btn btn-primary" style="padding: 0.4rem 1rem;">View</a>
                        <a href="?delete_class=<?= $c['class_id'] ?>" onclick="return confirm('⚠️ Delete this class and all students?')" class="btn btn-danger" style="padding: 0.4rem 1rem;">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                No classes found.<br>Click "Create Class" to add a new class.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== FOOTER (EXACT MATCH FROM INDEX.PHP) ========== -->
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

<!-- mobile menu toggle (same as index) -->
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
</html>v