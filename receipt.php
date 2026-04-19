<?php
session_start();
include "../config.php";

/* 🔥 ERROR DISPLAY */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* 🔥 MYSQLI ERROR MODE */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ===============================
// 🔐 STUDENT AUTH CHECK
// ===============================
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// ===============================
// ✅ STEP 1: GET USER ID
// ===============================
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    die("❌ Invalid session");
}

// ===============================
// ✅ STEP 2: MAP student_id (FIXED)
// ===============================
$stmt = $conn->prepare("
    SELECT u.student_id, s.name, s.prn
    FROM users u
    JOIN students s ON u.student_id = s.student_id
    WHERE u.user_id = ? AND u.role = 'student'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("❌ Student mapping not found");
}

$student = $result->fetch_assoc();
$student_id = $student['student_id'];

// Extra safety
if (!$student_id) {
    die("❌ Invalid student mapping");
}

// ===============================
// 🔥 STEP 3: UPDATE COMBINED DUES
// ===============================
if (function_exists('updateCombinedDues')) {
    updateCombinedDues($conn, $student_id);
}

// ===============================
// ✅ STEP 4: FETCH DUES (SECURE)
// ===============================
$stmt = $conn->prepare("
    SELECT * FROM combined_dues WHERE student_id = ?
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$result = $stmt->get_result();

if (!$result) {
    die("❌ Query Error: " . $conn->error);
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

// ===============================
// ✅ STEP 7: FINAL STATUS CHECK
// ===============================
$allCleared = (
    $data['combined_status'] === 'Cleared' &&
    $data['library_due'] == 0 &&
    $data['accounts_due'] == 0 &&
    $data['lab_due'] == 0 &&
    $data['exam_due'] == 0
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>No Dues Receipt | SCOE Digital System</title>
<!-- Google Fonts: Inter (for navbar/footer only) -->
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
<style>
   /* ================================
   GLOBAL RESET
================================ */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html, body {
  width: 100%;
  min-height: 100vh;
  font-family: 'Inter', sans-serif;
  background: #eef0f3;
  color: #1e293b;
  line-height: 1.5;
  overflow-x: hidden;
}

/* ================================
   VARIABLES
================================ */
:root {
  --primary: #1e293b;
  --accent: #d4af37;
  --border: #e5e7eb;
  --text-muted: #6b7280;
  --charcoal: #2d3748;
}
/* ----- NAVBAR (clean + modern dropdown style) ----- */
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 2rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border);

    position: relative;
    z-index: 1000;
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
}

/* ===== DESKTOP NAV ===== */
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
    transition: 0.2s ease;
}

.nav-links a:hover {
    color: var(--primary);
}

/* logout button */
.logout-btn {
    background: var(--primary);
    color: white !important;
    padding: 0.45rem 1.2rem;
    border-radius: 30px;
    font-weight: 600;
}

/* ===== HAMBURGER ICON ===== */
.menu-toggle {
    display: none;
    font-size: 1.6rem;
    cursor: pointer;
    background: none;
    border: none;
    color: var(--primary);
}

/* ===== MOBILE MENU (CARD STYLE DROPDOWN) ===== */
@media screen and (max-width: 768px) {

    .menu-toggle {
        display: block;
    }

    .nav-links {
        display: none;

        position: absolute;
        top: 70px;
        right: 1rem;

        width: 220px;

        flex-direction: column;
        gap: 0.6rem;

        background: #ffffff;
        padding: 0.8rem;

        border-radius: 14px;
        border: 1px solid var(--border);

        /* 🔥 modern floating card look */
        box-shadow: 0 12px 30px rgba(0,0,0,0.08);

        z-index: 999;
    }

    .nav-links.active {
        display: flex;
        animation: fadeIn 0.2s ease-in-out;
    }

    .nav-links a {
        padding: 0.6rem;
        text-align: left;
        border-radius: 8px;
    }

    .nav-links a:hover {
        background: #f3f4f6;
    }

    .logout-btn {
        text-align: center;
    }
}

/* smooth animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
/* ================================
   MAIN WRAPPER
================================ */
.container {
  width: 100%;
  max-width: 1200px;
  margin: 2rem auto;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  flex-direction: column;
  padding: 0 1rem;
}

/* ================================
   RECEIPT / CERTIFICATE CARD
================================ */
.certificate {
  width: 210mm;
  min-height: 297mm;
  background: #fff;
  padding: 14mm;
  margin: 0 auto;
  box-shadow: 0 10px 35px rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  font-family: "Times New Roman", serif;

  /* 🔥 FIX: allow proper vertical flow */
  display: flex;
  flex-direction: column;

  /* IMPORTANT: remove rigid spacing control */
  gap: 6px;

  /* 🔥 FIX: DO NOT hide content globally */
  overflow: visible;
}

/* ================================
   CERTIFICATE HEADER
================================ */
.certificate-header {
  text-align: center;
  margin-bottom: 10px;
}

.certificate-logo {
  margin-bottom: 10px;
}

.certificate-logo img {
  max-width: 360px;
  width: 100%;
  height: auto;
  margin: 0 auto;
  display: block;
}

.certificate-header h2 {
  font-size: 28px;
  font-weight: 800;
  margin: 8px 0;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.certificate-header p {
  font-size: 15px;
  margin: 2px 0;
  color: #333;
}

.status {
  font-size: 16px;
  font-weight: 700;
  margin-top: 8px;
  padding: 4px 12px;
  display: inline-block;
  border-radius: 4px;
}

/* ================================
   STUDENT INFO
================================ */
.student-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 25px;
  font-size: 15px;
  margin: 15px 0;
}

.student-info div {
  display: flex;
  justify-content: space-between;
  border-bottom: 1px solid #000;
  padding: 6px 0;
}

.student-info div span:first-child {
  color: #333;
}

.student-info div span:last-child {
  font-weight: 500;
}

/* ================================
   SECTION TITLES (H3 TAGS)
================================ */
.certificate h3 {
  font-size: 18px;
  font-weight: 800;
  margin: 15px 0 8px;
  border-bottom: 2px solid #000;
  padding-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.section-title {
  font-size: 18px;
  font-weight: 800;
  margin: 15px 0 8px;
  border-bottom: 2px solid #000;
  padding-bottom: 4px;
}

/* ================================
   TABLES
================================ */
table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  margin-bottom: 10px;
}

th, td {
  border: 1px solid #000;
  padding: 8px 6px;
  text-align: center;
}

th {
  background: #f3f4f6;
  font-weight: 700;
  font-size: 14px;
}

td {
  font-size: 13px;
}

.dues-table tr,
.subject-table tr {
  height: 32px;
}

.dues-table tbody tr:hover,
.subject-table tbody tr:hover {
  background: #fafafa;
}

/* ================================
   SIGNATURE SECTION
================================ */
.signature-section {
  display: flex;
  justify-content: space-between;

  /* 🔥 KEY FIX: forces it into visible bottom area */
  margin-top: auto;

  padding-top: 25px;
}

.signature-box {
  width: 200px;
  text-align: center;
}

.signature-line {
  border-top: 1px solid #000;
  margin-top: 50px;
  padding-top: 8px;
  font-size: 14px;
  font-weight: 500;
}

/* ================================
   BUTTON STYLES
================================ */
.btn {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 20px;
  padding: 10px 0;
}

.print-btn {
  background: var(--charcoal);
  color: #fff;
  font-size: 16px;
  font-weight: 600;
  padding: 14px 32px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  font-family: 'Inter', sans-serif;
}

.print-btn:hover {
  background: #1a202c;
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.print-btn:active {
  transform: translateY(0);
}

.disabled-btn {
  background: #9ca3af !important;
  color: #fff !important;
  cursor: not-allowed !important;
  opacity: 0.7;
  box-shadow: none !important;
}

.disabled-btn:hover {
  transform: none !important;
  background: #9ca3af !important;
}

/* ================================
   FOOTER
================================ */
.footer {
  width: 100%;
  background: #111827;
  color: #9ca3af;
  padding: 2.5rem 1.5rem 1.5rem;
  margin-top: 3rem;
  display: block;
}

.footer-grid {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.footer-grid h3 {
  color: #fff;
  font-size: 1.1rem;
  margin-bottom: 1rem;
  font-weight: 600;
}

.footer-grid p {
  font-size: 0.9rem;
  line-height: 1.7;
  margin-bottom: 0.5rem;
}

.footer-bottom {
  max-width: 1100px;
  margin: 2rem auto 0;
  padding-top: 1.5rem;
  border-top: 1px solid #374151;
  text-align: center;
  font-size: 0.85rem;
  color: #6b7280;
}

/* ================================
   MOBILE RESPONSIVE
================================ */
@media screen and (max-width: 900px) {
  .certificate {
    width: 100%;
    min-height: auto;
    padding: 8mm;
  }
}

@media screen and (max-width: 768px) {
  /* Navbar Mobile */
  .navbar {
    padding: 0.8rem 1rem;
    flex-wrap: wrap;
  }

  .menu-toggle {
    display: block;
  }

  .nav-links {
    display: none;
    flex-direction: column;
    width: 100%;
    background: #fff;
    position: absolute;
    top: 100%;
    left: 0;
    padding: 1rem;
    gap: 1rem;
    border-bottom: 1px solid var(--border);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  .nav-links.active {
    display: flex;
  }

  .nav-links a {
    padding: 0.5rem 0;
    width: 100%;
    text-align: center;
  }

  /* Certificate Mobile */
  .certificate {
    width: 100%;
    min-height: auto;
    padding: 5mm;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
  }

  .certificate-logo img {
    max-width: 280px;
  }

  .certificate-header h2 {
    font-size: 22px;
  }

  .student-info {
    grid-template-columns: 1fr;
    gap: 6px;
  }

  table {
    font-size: 12px;
  }

  th, td {
    padding: 6px 4px;
  }

  .signature-section {
    flex-direction: column;
    gap: 40px;
    align-items: center;
  }

  .signature-box {
    width: 100%;
    max-width: 250px;
  }

  .print-btn {
    width: 100%;
    padding: 12px 20px;
    font-size: 14px;
  }

  /* Footer Mobile */
  .footer-grid {
    grid-template-columns: 1fr;
    gap: 1.5rem;
    text-align: center;
  }
}

@media screen and (max-width: 480px) {
  .logo span {
    font-size: 0.95rem;
  }

  .logo img {
    height: 36px;
  }

  .certificate {
    padding: 4mm;
  }

  .certificate-header h2 {
    font-size: 18px;
  }

  .certificate h3 {
    font-size: 15px;
  }

  table {
    font-size: 11px;
  }

  th, td {
    padding: 5px 3px;
  }
}

/* ================================
   PRINT STYLES (FIXED VERSION)
================================ */
@media print {

  /* Hide non-printable elements */
  .navbar,
  .footer,
  .btn,
  .menu-toggle,
  .logout-btn,
  .nav-links {
    display: none !important;
  }

  /* Reset page */
  html, body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 210mm !important;
    height: 297mm !important;
    overflow: hidden !important;
  }

  /* Container reset */
  .container {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: none !important;
  }

  /* Certificate layout */
  .certificate {
    width: 210mm !important;
    height: 297mm !important;

    padding: 10mm !important;
    margin: 0 !important;

    box-shadow: none !important;
    border-radius: 0 !important;

    overflow: hidden !important;

    display: flex !important;
    flex-direction: column !important;

    justify-content: flex-start !important;
  }

  /* Header */
  .certificate-header h2 {
    font-size: 22px !important;
    margin: 6px 0 !important;
  }

  .certificate h3 {
    font-size: 14px !important;
    margin: 8px 0 4px !important;
  }

  /* Student info */
  .student-info {
    font-size: 13px !important;
    gap: 6px 15px !important;
  }

  /* Tables */
  table {
    font-size: 11px !important;
  }

  th, td {
    padding: 4px 3px !important;
  }

  /* Prevent breaking inside important blocks */
  table,
  tr,
  td,
  th,
  .student-info {
    page-break-inside: avoid !important;
  }

  /* Signature always at bottom */
  .signature-section {
    margin-top: auto !important;
    padding-top: 10px !important;
    display: flex !important;
    justify-content: space-between !important;
  }

  .signature-line {
    margin-top: 30px !important;
    font-size: 12px !important;
  }

  /* Force correct print colors */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  th {
    background: #f3f4f6 !important;
  }

  /* Ensure no accidental overflow */
  * {
    max-height: 100% !important;
  }
}

/* ================================
   PAGE SETUP
================================ */
@page {
  size: A4;
  margin: 0;
}
    
    .certificate h3 {
  border-bottom: 2px solid #000; /* keep default */
}

/* REMOVE border ONLY for these two headings */
.certificate h3:nth-of-type(1),
.certificate h3:nth-of-type(2) {
  border-bottom: none !important;
}
    </style>
</head>
<body>

<!-- ========== NAVBAR (consistent with dashboard) ========== -->
<div class="navbar">
  <div class="logo">
    <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg" alt="SCOE Logo">
    <span>SCOE Digital System</span>
  </div>
  <div class="menu-toggle" id="menuToggle">☰</div>
  <div class="nav-links" id="navMenu">
    <a href="../index.php">Home</a>
    <a href="student_dashboard.php">Dues</a>
    <a href="#" style="font-weight: 600;">Receipt</a>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<div class="container">
  <div class="certificate">

  <!-- HEADER -->
  <div class="certificate-header">
    <div class="certificate-logo">
      <img src="https://engineering.saraswatikharghar.edu.in/wp-content/uploads/2023/05/thumbnail_Saraswati-Logo-square-final-1-1024x612.jpg">
    </div>

    <h2>NO DUES FORM</h2>
    <p>Saraswati College of Engineering</p>
    <p class="status"><?= htmlspecialchars($data['combined_status']) ?></p>
  </div>

  <!-- STUDENT INFO -->
  <div class="student-info">
    <div><span><b>Name:</b></span><span><?= htmlspecialchars($student['name']) ?></span></div>
    <div><span><b>Seat No:</b></span><span><?= htmlspecialchars($student['prn']) ?></span></div>

    <div><span><b>Year:</b></span><span>T.E</span></div>
    <div><span><b>Branch:</b></span><span>COMPUTER</span></div>

    <div><span><b>Semester:</b></span><span>VI</span></div>
    <div><span><b>Exam:</b></span><span>MAY</span></div>
  </div>

  <!-- DUES SUMMARY -->
  <h3>Dues Summary</h3>
  <table class="dues-table">
    <thead>
      <tr>
        <th>Department</th>
        <th>Amount</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Library</td>
        <td>₹<?= number_format($data['library_due'], 0) ?></td>
        <td><?= $data['library_due']==0 ? 'Cleared' : 'Pending' ?></td>
      </tr>
      <tr>
        <td>Accounts</td>
        <td>₹<?= number_format($data['accounts_due'], 0) ?></td>
        <td><?= $data['accounts_due']==0 ? 'Cleared' : 'Pending' ?></td>
      </tr>
      <tr>
        <td>Lab</td>
        <td>₹<?= number_format($data['lab_due'], 0) ?></td>
        <td><?= $data['lab_due']==0 ? 'Cleared' : 'Pending' ?></td>
      </tr>
      <tr>
        <td>Student Section</td>
        <td>₹<?= number_format($data['exam_due'], 0) ?></td>
        <td><?= $data['exam_due']==0 ? 'Cleared' : 'Pending' ?></td>
      </tr>
    </tbody>
  </table>

  <!-- SUBJECT CLEARANCE -->
  <h3>Subject Clearance</h3>
  <table class="subject-table">
    <thead>
      <tr>
        <th>Subject</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php for($i = 0; $i < 6; $i++): 
        $s = $subject_details[$i] ?? null;
      ?>
        <tr>
          <td><?= htmlspecialchars($s['subject_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($s['status'] ?? '') ?></td>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>

  
<!-- SIGNATURE SECTION -->
  <div class="signature-section">
    <div class="signature-box">
      <div class="signature-line">Student Signature</div>
    </div>
    <div class="signature-box">
      <div class="signature-line">HOD Signature</div>
    </div>
  </div>

  <!-- PRINT BUTTON (UNCHANGED LOGIC) -->
  <div class="btn">
    <?php if($allCleared): ?>
      <button onclick="window.print()" class="print-btn">Print Receipt</button>
    <?php else: ?>
      <button class="print-btn disabled-btn" disabled>
        Clear all dues to download receipt
      </button>
    <?php endif; ?>
  </div>

</div>

<!-- ========== FOOTER (consistent with dashboard) ========== -->
<footer class="footer">
  <div class="footer-grid">
    <div>
      <h3>About System</h3>
      <p>Paperless, transparent No Dues Clearance for SCOE students. Real‑time tracking, hassle‑free final clearance.</p>
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
    © 2025 Saraswati College of Engineering | No Dues Simplified
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