<?php
session_start();
include "../config.php";

// 🔓 Logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// 🔐 Role check
if($_SESSION['role'] != 'library'){
    header("Location: ../login.php");
    exit();
}

// Fetch counts
$total = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM students"))['c'];
$cleared = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM dues WHERE library_due=0"))['c'];
$pending = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM dues WHERE library_due>0"))['c'];

$cleared_percentage = $total > 0 ? round(($cleared/$total)*100) : 0;
$pending_percentage = $total > 0 ? round(($pending/$total)*100) : 0;

// Optional search
$search = "";
if(isset($_GET['search'])){
    $search = mysqli_real_escape_string($conn, $_GET['search']);
}

// Fetch library data
$query = "SELECT students.name, students.prn, dues.library_due, dues.status, students.student_id
          FROM dues
          JOIN students ON students.student_id = dues.student_id
          WHERE dues.library_due > 0
          AND (
                students.name LIKE '%$search%' 
             OR students.prn LIKE '%$search%'
          )";

$result = mysqli_query($conn,$query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Library Dashboard | SCOE Digital System</title>
    <!-- Google Fonts: Inter (matching exam dashboard) -->
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

        /* ----- COLOR SYSTEM (identical to exam dashboard) ----- */
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

        /* ----- NAVBAR (exactly as exam dashboard) ----- */
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

        /* ----- MAIN CONTAINER & HEADER (search + title) ----- */
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

        /* Search box styled like exam dashboard */
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

        /* ----- CARDS GRID (same style as exam dashboard) ----- */
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

        /* ----- TABLE CARD (border-only, matches exam dashboard) ----- */
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
            min-width: 500px;
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

        /* ----- BUTTONS (modern, soft red for pending) ----- */
        .btn {
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-pending {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .btn-pending:hover {
            background: #fecaca;
            transform: translateY(-1px);
        }

        .btn-cleared {
            background: #e0f2e9;
            color: #166534;
            border: 1px solid #bbf7d0;
            cursor: default;
        }

        /* empty state / no data styling */
        .empty-row td {
            text-align: center;
            color: var(--text-muted);
            padding: 2rem;
        }

        /* ----- FOOTER (identical to exam dashboard) ----- */
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

        /* ----- RESPONSIVE (matches exam dashboard) ----- */
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
            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .card-value {
                font-size: 1.7rem;
            }
        }

        @media (max-width: 480px) {
            .table-card {
                padding: 0.8rem;
            }
            th, td {
                padding: 0.6rem 0.3rem;
            }
        }
    </style>
</head>
<body>

<!-- ========== NAVBAR (EXACT SAME AS EXAM DASHBOARD) ========== -->
<div class="navbar">
    <div class="logo">
        <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg" alt="SCOE Logo">
        <span>SCOE Digital System</span>
    </div>
    <div class="menu-toggle" id="menuToggle">☰</div>
    <div class="nav-links" id="navMenu">
        <a href="../index.php">Home</a>
        <a href="library_dashboard.php" class="active">Dashboard</a>
        <a href="?logout=true" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">
    <!-- HEADER with title + search -->
    <div class="dashboard-header">
        <div class="page-title">Library Dashboard</div>
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search by name or PRN..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <!-- STATS CARDS (dynamic from database) -->
    <div class="grid">
        <div class="card">
            <div class="card-label">Total Students</div>
            <div class="card-value"><?= $total ?></div>
        </div>
        <div class="card">
            <div class="card-label">Cleared (Library)</div>
            <div class="card-value"><?= $cleared ?></div>
        </div>
        <div class="card">
            <div class="card-label">Pending Dues</div>
            <div class="card-value"><?= $pending ?></div>
        </div>
    </div>

    <!-- TABLE: pending library dues (library_due > 0) -->
    <div class="table-card">
        <h3> Pending Library Dues</h3>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>PRN</th>
                    <th>Library Due (₹)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['prn']) ?></td>
                            <td>₹<?= number_format($row['library_due']) ?></td>
                            <td>
                                <?php if ($row['library_due'] > 0): ?>
                                    <a href="delete_due.php?id=<?= $row['student_id'] ?>&type=library" class="btn btn-pending">Pending</a>
                                <?php else: ?>
                                    <span class="btn btn-cleared">Cleared</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="empty-row">
                        <td colspan="4">No pending library dues found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========== FOOTER (EXACT MATCH FROM EXAM DASHBOARD) ========== -->
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

<!-- mobile menu toggle (same as exam dashboard) -->
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