<?php
session_start();
include "config.php";

$error = '';

if(isset($_POST['login']))
{
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 🔹 Check user from users table
    $q = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $r = mysqli_query($conn,$q);

    if(mysqli_num_rows($r) > 0)
    {
        $user = mysqli_fetch_assoc($r);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // 🔹 Role-based redirect
        if($user['role'] == 'admin')
        {
            header("Location: dashboards/admin_dashboard.php");
        }
        elseif($user['role'] == 'student')
        {
            header("Location: dashboards/student_dashboard.php");
        }
        else
        {
            header("Location: dashboards/staff_dashboard.php");
        }
        exit();
    }
    else
    {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Student Dues Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #d9eaff 0%, #b0d4f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
        }

        /* animated background effect */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(30,136,229,0.08) 0%, rgba(255,255,255,0) 70%);
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            animation: fadeInUp 0.5s ease-out;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(2px);
            border-radius: 32px;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.2), 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 2rem 2rem 2.2rem;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .login-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 30px 50px -15px rgba(0, 0, 0, 0.25);
        }

        .logo-area {
            text-align: center;
            margin-bottom: 1.8rem;
        }
        .logo-area i {
            font-size: 3.2rem;
            color: #1e88e5;
            background: linear-gradient(145deg, #eef6fc, #ffffff);
            padding: 0.8rem;
            border-radius: 50%;
            box-shadow: 0 8px 18px rgba(0,0,0,0.05);
        }
        .logo-area h2 {
            font-size: 1.7rem;
            font-weight: 700;
            margin-top: 0.8rem;
            background: linear-gradient(135deg, #0b4f6c, #1e88e5);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .logo-area p {
            color: #5f7f9e;
            font-size: 0.85rem;
            margin-top: 0.2rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .input-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2c5a7a;
            margin-bottom: 0.5rem;
        }
        .input-group i {
            position: absolute;
            left: 16px;
            bottom: 14px;
            color: #8bb3cf;
            font-size: 1rem;
        }
        .input-group input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            border: 1.5px solid #e2edf7;
            border-radius: 48px;
            background: white;
            transition: all 0.2s;
            outline: none;
            color: #1e2f3f;
        }
        .input-group input:focus {
            border-color: #4aa3d4;
            box-shadow: 0 0 0 3px rgba(74, 163, 212, 0.2);
        }

        button {
            width: 100%;
            background: #1e88e5;
            border: none;
            padding: 0.85rem;
            border-radius: 48px;
            font-weight: 700;
            font-size: 0.95rem;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Inter', sans-serif;
            margin-top: 0.5rem;
            box-shadow: 0 4px 8px rgba(30,136,229,0.2);
        }
        button:hover {
            background: #0f6b9e;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(30,136,229,0.25);
        }
        button:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
            padding: 0.7rem 1rem;
            border-radius: 60px;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake 0.3s ease-in-out;
        }
        .error-message i {
            font-size: 1rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        .extra-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #5c7f9c;
        }
        .extra-links a {
            color: #1e88e5;
            text-decoration: none;
            font-weight: 500;
        }
        .extra-links a:hover {
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="logo-area">
            <i class="fas fa-user-shield"></i>
            <h2>Admin Access</h2>
            <p>Sign in to manage student dues</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="input-group">
                <label><i class="fas fa-user"></i> Username</label>
                <i class="fas fa-user-circle"></i>
                <input type="text" name="username" placeholder="Enter your username" required autofocus>
            </div>
            <div class="input-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <i class="fas fa-key"></i>
                <input type="password" name="password" id="password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="login">
                <i class="fas fa-arrow-right-to-bracket"></i> Login
            </button>
        </form>
        <div class="extra-links">
            <a href="#">Forgot password?</a> &nbsp;|&nbsp;
            <a href="#">Contact support</a>
        </div>
    </div>
</div>
<script>
    // Optional: toggle password visibility (if needed)
    // No other changes – functionality untouched
    console.log("Login page ready");
</script>
</body>
</html>