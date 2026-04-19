<?php
session_start();
include "../config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Teacher access check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// ================================
// ASSIGN CLASS & SUBJECT
// ================================
if (isset($_POST['assign_class'])) {
    $class_id   = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);

    if ($class_id && $subject_id) {
        // Check duplicate assignment
        $check = $conn->prepare("SELECT id FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
        $check->bind_param("iii", $teacher_id, $class_id, $subject_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            // Insert teacher assignment
            $insert = $conn->prepare("INSERT INTO teacher_classes (teacher_id, class_id, subject_id) VALUES (?, ?, ?)");
            $insert->bind_param("iii", $teacher_id, $class_id, $subject_id);
            $insert->execute();

            // Insert subject_dues for all students in this class (status 'Pending')
            $students = $conn->prepare("SELECT student_id FROM students WHERE class_id = ?");
            $students->bind_param("i", $class_id);
            $students->execute();
            $result = $students->get_result();

            while ($s = $result->fetch_assoc()) {
                $sid = $s['student_id'];

                $exists = $conn->prepare("SELECT id FROM subject_dues WHERE student_id = ? AND subject_id = ?");
                $exists->bind_param("ii", $sid, $subject_id);
                $exists->execute();
                $exists->store_result();

                if ($exists->num_rows === 0) {
                    $insertDue = $conn->prepare("INSERT INTO subject_dues (student_id, subject_id, status) VALUES (?, ?, 'Pending')");
                    $insertDue->bind_param("ii", $sid, $subject_id);
                    $insertDue->execute();
                }
            }
        }
    }
}

// ================================
// DELETE ASSIGNMENT
// ================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Get class_id and subject_id of the assignment
    $get = $conn->prepare("SELECT class_id, subject_id FROM teacher_classes WHERE id = ? AND teacher_id = ?");
    $get->bind_param("ii", $id, $teacher_id);
    $get->execute();
    $get->store_result();

    if ($get->num_rows > 0) {
        $get->bind_result($class_id, $subject_id);
        $get->fetch();

        // Get students of this class
        $students = $conn->prepare("SELECT student_id FROM students WHERE class_id = ?");
        $students->bind_param("i", $class_id);
        $students->execute();
        $studResult = $students->get_result();

        while ($s = $studResult->fetch_assoc()) {
            $sid = $s['student_id'];

            // Delete subject dues for this subject
            $delDue = $conn->prepare("DELETE FROM subject_dues WHERE student_id = ? AND subject_id = ?");
            $delDue->bind_param("ii", $sid, $subject_id);
            $delDue->execute();

            // Delete from student_subjects if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'student_subjects'");
            if ($tableCheck->num_rows > 0) {
                $delMap = $conn->prepare("DELETE FROM student_subjects WHERE student_id = ? AND subject_id = ?");
                $delMap->bind_param("ii", $sid, $subject_id);
                $delMap->execute();
            }

            // Recalculate final status (function defined elsewhere)
            updateStudentFinalStatus($conn, $sid);
        }

        // Delete the teacher assignment
        $delAssign = $conn->prepare("DELETE FROM teacher_classes WHERE id = ? AND teacher_id = ?");
        $delAssign->bind_param("ii", $id, $teacher_id);
        $delAssign->execute();
    }

    header("Location: teacher_dashboard.php");
    exit();
}

// ================================
// FETCH ALL CLASSES
// ================================
$classList = $conn->query("
    SELECT c.class_id, d.dept_name, y.year_name, dv.division_name
    FROM classes c
    JOIN departments d ON c.dept_id = d.dept_id
    JOIN years y ON c.year_id = y.year_id
    JOIN divisions dv ON c.division_id = dv.division_id
");

// ================================
// FETCH ALL SUBJECTS (with class mapping)
// ================================
$subjectList = $conn->query("
    SELECT s.subject_id, s.subject_name, cs.class_id
    FROM subjects s
    JOIN class_subjects cs ON s.subject_id = cs.subject_id
");

// ================================
// FETCH ASSIGNED CLASSES
// ================================
$assigned = $conn->prepare("
    SELECT tc.id, tc.class_id, tc.subject_id,
           s.subject_name,
           d.dept_name, y.year_name, dv.division_name
    FROM teacher_classes tc
    JOIN classes c ON tc.class_id = c.class_id
    JOIN departments d ON c.dept_id = d.dept_id
    JOIN years y ON c.year_id = y.year_id
    JOIN divisions dv ON c.division_id = dv.division_id
    JOIN subjects s ON tc.subject_id = s.subject_id
    WHERE tc.teacher_id = ?
");
$assigned->bind_param("i", $teacher_id);
$assigned->execute();
$assignedResult = $assigned->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Teacher Dashboard | SCOE Digital System</title>
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

        /* ----- COLOR SYSTEM (identical to admin dashboard) ----- */
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

        /* ----- NAVBAR (exactly as admin dashboard) ----- */
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

        /* ----- MAIN CONTAINER ----- */
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

        /* ----- FORM CARD (assignment) ----- */
        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        select {
            padding: 0.6rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            background: white;
            min-width: 200px;
            flex: 1;
            outline: none;
            transition: 0.2s;
        }

        select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(30,41,59,0.1);
        }

        .btn-assign {
            background: var(--primary);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-assign:hover {
            background: #0f172a;
            transform: translateY(-1px);
        }

        /* ----- ASSIGNED CLASSES GRID ----- */
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.2rem;
            margin-top: 0.5rem;
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
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .subject-name {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin: 0.5rem 0;
        }

        .card-actions {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .btn-view {
            background: var(--success);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: 0.2s;
            display: inline-block;
        }

        .btn-view:hover {
            background: #15803d;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-delete:hover {
            background: #fecaca;
            transform: translateY(-1px);
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

        /* ----- FOOTER (identical to admin dashboard) ----- */
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

        /* ----- RESPONSIVE ----- */
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
            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }
            .form-group {
                flex-direction: column;
                align-items: stretch;
            }
            select, .btn-assign {
                width: 100%;
            }
            .class-grid {
                grid-template-columns: 1fr;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<!-- ========== NAVBAR (EXACT SAME AS ADMIN DASHBOARD) ========== -->
<div class="navbar">
    <div class="logo">
        <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg" alt="SCOE Logo">
        <span>SCOE Digital System</span>
    </div>
    <div class="menu-toggle" id="menuToggle">☰</div>
    <div class="nav-links" id="navMenu">
        <a href="../index.php">Home</a>
        <a href="teacher_dashboard.php" class="active">Dashboard</a>
        <a href="?logout=true" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">
    <div class="dashboard-header">
        <div class="page-title">Teacher Dashboard</div>
    </div>

    <!-- Assign Class & Subject Form -->
    <div class="form-card">
        <h3>Assign New Class & Subject</h3>
        <form method="post" class="form-group">
            <select name="class_id" required>
                <option value="">Select Class</option>
                <?php
                // Reset pointer for classList (already fetched)
                $classList->data_seek(0);
                while ($c = $classList->fetch_assoc()): ?>
                    <option value="<?= intval($c['class_id']) ?>">
                        <?= htmlspecialchars($c['dept_name'] . ' - ' . $c['year_name'] . ' - ' . $c['division_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="subject_id" required>
                <option value="">Select Subject</option>
                <?php
                $subjectList->data_seek(0);
                while ($s = $subjectList->fetch_assoc()): ?>
                    <option value="<?= intval($s['subject_id']) ?>" data-class="<?= intval($s['class_id']) ?>">
                        <?= htmlspecialchars($s['subject_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" name="assign_class" class="btn-assign">Assign</button>
        </form>
    </div>

    <!-- Assigned Classes List -->
    <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 1rem; color: var(--primary);">My Assigned Classes</h3>
    <div class="class-grid">
        <?php if ($assignedResult->num_rows > 0): ?>
            <?php while ($a = $assignedResult->fetch_assoc()): ?>
                <div class="class-card">
                    <div class="class-title">
                        <?= htmlspecialchars($a['dept_name']) ?><br>
                        <?= htmlspecialchars($a['year_name'] . ' - ' . $a['division_name']) ?>
                    </div>
                    <div class="subject-name">
                        Subject: <?= htmlspecialchars($a['subject_name']) ?>
                    </div>
                    <div class="card-actions">
                        <a href="teacher_view_class.php?class_id=<?= intval($a['class_id']) ?>&subject_id=<?= intval($a['subject_id']) ?>" class="btn-view">View Students</a>
                        <a href="?delete=<?= intval($a['id']) ?>" onclick="return confirm('Are you sure you want to delete this assignment?')" class="btn-delete">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                No classes assigned yet.<br>Use the form above to assign a class and subject.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== FOOTER (EXACT MATCH FROM ADMIN DASHBOARD) ========== -->
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

<!-- Subject filter script (unchanged logic, only UI adapted) -->
<script>
    document.querySelector('select[name="class_id"]').addEventListener('change', function() {
        let classId = this.value;
        let subjectOptions = document.querySelectorAll('select[name="subject_id"] option');
        subjectOptions.forEach(opt => {
            if (!opt.value) return;
            opt.style.display = (opt.getAttribute('data-class') == classId) ? 'block' : 'none';
        });
    });

    // Mobile menu toggle
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