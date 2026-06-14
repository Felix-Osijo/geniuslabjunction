<?php
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: /includes/db.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login | Genius Lab Junction</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body class="auth-body">



<div class="auth-container">


    <div class="auth-left">

        <h1>Welcome Back 👋</h1>

        <p>
            Continue learning, interacting with students,
            earning XP, and growing your skills.
        </p>

        <div class="auth-features">

            <div class="feature-item">
                📚 Learn new skills
            </div>

            <div class="feature-item">
                🌍 Connect with students
            </div>

            <div class="feature-item">
                🏆 Earn XP & rewards
            </div>

        </div>

    </div>




    <div class="auth-right">

        <form action="login.php"
              method="POST"
              class="auth-form">

            <h2>Login</h2>

            <p class="form-text">
                Access your Genius Lab Junction account
            </p>


            <input type="text"
                   name="email"
                   placeholder="Email or Username"
                   required>


            <input type="password"
                   name="password"
                   placeholder="Password"
                   required>


            <button type="submit"
                    class="primary-btn">

                Login

            </button>


            <div class="auth-links">

                <a href="password-recovery.php">
                    Forgot Password?
                </a>

                <a href="register.php">
                    Create Account
                </a>

            </div>

        </form>

    </div>

</div>


<script src="/assets/js/sign.js"></script>

</body>
</html>
