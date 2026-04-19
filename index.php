<?php
session_start();
include "config.php"; // Adjust path if needed

// Determine if student is logged in
$isLoggedIn = isset($_SESSION['role']) && $_SESSION['role'] === 'student';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>No Dues Clearance | SCOE Digital System</title>
<!-- Google Fonts: Inter only -->
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<style>
  /* ---------- MINIMAL DESIGN SYSTEM (matches student dashboard) ---------- */
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

  /* ----- NAVBAR (clean, no glass, minimal) ----- */
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

  .login-btn, .logout-btn {
    background: var(--primary);
    color: white !important;
    padding: 0.45rem 1.2rem;
    border-radius: 30px;
    font-weight: 600;
    transition: background 0.2s;
  }

  .login-btn:hover, .logout-btn:hover {
    background: #0f172a;
  }

  .menu-toggle {
    display: none;
    font-size: 1.5rem;
    cursor: pointer;
    background: none;
    border: none;
    color: var(--primary);
  }

  /* ===== NEW HERO SECTION (two-column, minimal, modern) ===== */
  .hero {
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    gap: 2rem;
    padding: 4rem 2rem;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
  }

  /* Left content */
  .hero-content h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
    margin-bottom: 1rem;
  }

  .hero-content p {
    font-size: 1rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    max-width: 480px;
  }

  .cta-button {
    display: inline-block;
    background: var(--primary);
    color: white;
    padding: 0.7rem 1.6rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
  }

  .cta-button:hover {
    background: #111827;
    transform: translateY(-1px);
  }

  /* Right image */
  .hero-image {
    position: relative;
  }

  .hero-image img {
    width: 100%;
    height: auto;
    border-radius: 12px;
    object-fit: cover;
    display: block;
  }

  /* Subtle gradient overlay for depth (optional, minimal) */
  .hero-image::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 12px;
    background: linear-gradient(to top, rgba(0,0,0,0.05), transparent);
    pointer-events: none;
  }

  /* Optional subtle background accent shape (very faint) */
  .hero {
    position: relative;
    overflow: hidden;
  }

  .hero::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 300px;
    height: 300px;
    background: rgba(212, 175, 55, 0.04);
    border-radius: 50%;
    z-index: 0;
    pointer-events: none;
  }

  .hero-content, .hero-image {
    position: relative;
    z-index: 1;
  }

  /* ----- MAIN CONTAINER ----- */
  .container {
    max-width: 1200px;
    margin: 2.5rem auto;
    padding: 0 2rem;
  }

  /* ----- KEY MESSAGE BANNER (subtle) ----- */
  .key-message {
    background: #f1f5f9;
    border-left: 3px solid var(--primary);
    padding: 1rem 1.5rem;
    margin-bottom: 2.5rem;
    border-radius: 8px;
    color: #334155;
    font-size: 0.9rem;
  }

  /* ----- TWO COLUMN GRID ----- */
  .info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2.5rem;
  }

  /* ----- CARDS (flat, border only) ----- */
  .modern-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: none;
    transition: box-shadow 0.2s;
  }

  .modern-card:hover {
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
  }

  .card-header {
    margin-bottom: 1rem;
    border-bottom: none;
  }

  .card-header h2 {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--primary);
    margin: 0;
  }

  .desc-text {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 1rem;
  }

  .feature-list {
    padding-left: 1rem;
    margin: 1rem 0;
  }

  .feature-list li {
    list-style: disc;
    margin-bottom: 0.5rem;
    color: var(--text-muted);
    font-size: 0.9rem;
  }

  .stats-row {
    display: flex;
    gap: 2rem;
    border-top: 1px solid var(--border);
    padding-top: 1rem;
    margin-top: 0.5rem;
  }

  .stat-item {
    font-size: 0.85rem;
    color: var(--text-muted);
  }

  .status-badge {
    display: inline-block;
    background: #ecfdf5;
    color: #16a34a;
    font-weight: 600;
    padding: 0.15rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
  }

  .status-badge-pending {
    background: #fffbeb;
    color: #f59e0b;
  }

  .highlight-print {
    background: #f8fafc;
    padding: 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
    text-align: center;
    border: 1px solid var(--border);
    margin-top: 1rem;
  }

  /* ----- STUDENT GUIDE SECTION (simple numbering) ----- */
  .student-guide {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1.5rem;
    margin-top: 1rem;
  }

  .student-guide h2 {
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--primary);
  }

  .guide-intro {
    background: #f8fafc;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: #334155;
    border-left: 2px solid var(--primary);
  }

  .step-list {
    list-style: none;
    counter-reset: step-counter;
  }

  .step-list li {
    counter-increment: step-counter;
    margin-bottom: 1.2rem;
    display: flex;
    gap: 12px;
  }

  .step-list li::before {
    content: counter(step-counter) ".";
    font-weight: 600;
    color: var(--text-muted);
    background: none;
    width: auto;
    height: auto;
    box-shadow: none;
    font-size: 0.9rem;
  }

  .step-content strong {
    font-weight: 600;
    color: var(--primary);
    display: block;
    margin-bottom: 0.2rem;
  }

  .step-content p {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0;
  }

  .sub-list {
    margin-top: 0.4rem;
    margin-left: 1.2rem;
    list-style: disc;
    color: var(--text-muted);
    font-size: 0.85rem;
  }

  /* ----- FOOTER (clean dark) ----- */
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
    max-width: 1200px;
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

  .social-icons {
    display: flex;
    gap: 1rem;
    margin-top: 0.8rem;
  }

  .social-icons a {
    background: rgba(255,255,255,0.05);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
  }

  .social-icons a:hover {
    background: var(--accent);
    color: #111827;
  }

  .footer-bottom {
    text-align: center;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #1f2937;
    font-size: 0.75rem;
  }

  /* ----- RESPONSIVE (hero becomes column on mobile) ----- */
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
      border-radius: 10px;
      border: 1px solid var(--border);
      box-shadow: 0 8px 20px rgba(0,0,0,0.05);
      z-index: 999;
    }
    .nav-links.active {
      display: flex;
    }
    .hero {
      grid-template-columns: 1fr;
      text-align: center;
      padding: 2rem 1.5rem;
      gap: 1.5rem;
    }
    .hero-content p {
      margin-left: auto;
      margin-right: auto;
    }
    .hero-image {
      order: -1;  /* image on top for mobile */
    }
    .hero-image img {
      max-height: 220px;
      object-fit: cover;
    }
    .info-grid {
      grid-template-columns: 1fr;
    }
    .container {
      padding: 0 1rem;
    }
    .stats-row {
      flex-direction: column;
      gap: 0.5rem;
    }
    .footer-grid {
      grid-template-columns: 1fr;
      text-align: center;
    }
    .social-icons {
      justify-content: center;
    }
  }

  @media (max-width: 480px) {
    .hero-content h1 {
      font-size: 1.8rem;
    }
  }
</style>
</head>
<body>
<!-- ========== NAVBAR (ROLE-BASED) ========== -->
<div class="navbar">
  <div class="logo">
    <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg" alt="SCOE Logo">
    <span>SCOE Digital System</span>
  </div>

  <div class="menu-toggle" id="menuToggle">☰</div>

  <div class="nav-links" id="navMenu">
    <a href="index.php">Home</a>

    <?php if (isset($_SESSION['user_id'])): ?>

      <?php
        $role = $_SESSION['role'];
      ?>

      <?php if ($role === 'student'): ?>

        <!-- STUDENT VIEW -->
        <a href="dashboards/student_dashboard.php">Dues</a>
        <a href="dashboards/receipt.php">Receipt</a>
        <a href="?logout=true" class="logout-btn">Logout</a>

      <?php else: ?>

        <!-- OTHER ROLES VIEW -->
        <?php
          $dashboard_link = "";

          switch($role){
            case 'admin':
              $dashboard_link = "dashboards/admin_dashboard.php";
              break;
            case 'accounts':
              $dashboard_link = "dashboards/accounts_dashboard.php";
              break;
            case 'library':
              $dashboard_link = "dashboards/library_dashboard.php";
              break;
            case 'lab':
              $dashboard_link = "dashboards/lab_dashboard.php";
              break;
            case 'exam':
              $dashboard_link = "dashboards/exam_dashboard.php";
              break;
            case 'teacher':
              $dashboard_link = "dashboards/teacher_dashboard.php";
              break;
          }
        ?>

        <a href="<?= $dashboard_link ?>">Dashboard</a>
        <a href="?logout=true" class="logout-btn">Logout</a>

      <?php endif; ?>

    <?php else: ?>

      <!-- NOT LOGGED IN -->
      <a href="login.php" class="login-btn">Login</a>

    <?php endif; ?>

  </div>
</div>
<!-- ========== NEW HERO (two-column: content + image) ========== -->
<div class="hero">
  <div class="hero-content">
    <h1>Digital No Dues Clearance</h1>
    <p>Check and complete your no dues process from a single platform. View all your pending clearances in one place and take action only where required.</p>
    <?php if (!$isLoggedIn): ?>
      <a href="login.php" class="cta-button">Check Your Status </a>
    <?php else: ?>
      <a href="dashboards/student_dashboard.php" class="cta-button">Check Your Dues</a>
    <?php endif; ?>
  </div>
  <div class="hero-image">
    <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2024/05/thumbnail_DCM_8148-768x513.webp" alt="SCOE Campus">
  </div>
</div>

<div class="container">
  <!-- Key message banner -->
  <div class="key-message">
    <strong>One portal for complete clearance.</strong> Track your dues across fees, library, labs, exam section, and subject approvals in real time. If everything is cleared, you do not need to visit any department.
  </div>

  <!-- Two column overview -->
  <div class="info-grid">
    <div class="modern-card">
      <div class="card-header">
        <h2>What is No Dues?</h2>
      </div>
      <p class="desc-text">No dues clearance is a mandatory process before exams. It ensures that all academic and administrative requirements are completed.</p>
      <ul class="feature-list">
        <li>Fees and accounts verification</li>
        <li>Library book return and fines</li>
        <li>Laboratory equipment clearance</li>
        <li>Exam section approval</li>
        <li>Subject and term work clearance</li>
      </ul>
      <div class="stats-row">
        <span class="stat-item">8+ Departments</span>
        <span class="stat-item">2200+ Students</span>
        <span class="stat-item">Real‑time Sync</span>
      </div>
    </div>

    <div class="modern-card">
      <div class="card-header">
        <h2>One Dashboard, Full Transparency</h2>
      </div>
      <p class="desc-text">Your dashboard shows the status of all departments in a simple and clear format.</p>
      <ul class="feature-list">
        <li><span class="status-badge">Cleared</span> → No action required</li>
        <li><span class="status-badge status-badge-pending">Pending</span> → Action required</li>
        <li>Focus only on sections marked as pending</li>
      </ul>
      <div class="highlight-print">
        If all sections are cleared, your no dues process is complete.
      </div>
    </div>
  </div>

  <!-- Student guide (simplified) -->
  <div class="student-guide">
    <h2>How to Complete Your No Dues Process</h2>
    <div class="guide-intro">
      Follow these steps to complete your clearance quickly and avoid unnecessary visits.
    </div>
    <ul class="step-list">
      <li>
        <div class="step-content">
          <strong>Login to the Portal</strong>
          <p>Access your account using your student credentials to view your dashboard.</p>
        </div>
      </li>
      <li>
        <div class="step-content">
          <strong>Check Your Status</strong>
          <p>All departments will be listed with their current clearance status.</p>
        </div>
      </li>
      <li>
        <div class="step-content">
          <strong>Identify Pending Sections</strong>
          <p>Focus only on sections marked as pending.</p>
        </div>
      </li>
      <li>
        <div class="step-content">
          <strong>Take Action Only Where Required</strong>
          <p>Visit or contact only the department that shows pending status.</p>
        </div>
      </li>
      <li>
        <div class="step-content">
          <strong>Subject Clearance (Teacher Approval)</strong>
          <p>Your subject clearance is updated by your teachers. No action is required unless informed.</p>
        </div>
      </li>
      <li>
        <div class="step-content">
          <strong>Final Clearance</strong>
          <p>Once all sections are marked as cleared, your process is complete.</p>
        </div>
      </li>
      <li>
        <div class="step-content">
          <strong>Download / Print</strong>
          <p>After full clearance, download or print your no dues form directly from the portal.</p>
        </div>
      </li>
    </ul>
    <div class="highlight-print">
      Always check your dashboard first. Visit departments only if a section is pending.
    </div>
  </div>

  <!-- New Section: Important Instructions -->
  <div class="modern-card" style="margin-top: 1.5rem;">
    <div class="card-header">
      <h2>Important Instructions</h2>
    </div>
    <ul class="feature-list">
      <li>Always check your dashboard before visiting any department</li>
      <li>Visit only if a section shows pending</li>
      <li>Subject clearance depends on teacher updates</li>
      <li>Keep checking regularly until all sections are cleared</li>
    </ul>
  </div>
</div>

<!-- ========== FOOTER (minimal) ========== -->
<footer class="footer">
  <div class="footer-grid">
    <div>
      <h3>About System</h3>
      <p>This system is designed to simplify the no dues process by reducing manual work and unnecessary visits. Track your status in real time and complete your clearance efficiently.</p>
      
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
    © 2025-26 Saraswati College of Engineering | No Dues Simplified
  </div>
</footer>

<script>
  // Mobile menu toggle
  const menuToggle = document.getElementById('menuToggle');
  const navMenu = document.getElementById('navMenu');
  function toggleMenu() { navMenu.classList.toggle('active'); }
  function closeMenu() { navMenu.classList.remove('active'); }
  if (menuToggle) menuToggle.addEventListener('click', toggleMenu);
  document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768 && !navMenu.contains(e.target) && !menuToggle.contains(e.target)) closeMenu();
  });
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768) closeMenu();
  });
</script>
</body>
</html>