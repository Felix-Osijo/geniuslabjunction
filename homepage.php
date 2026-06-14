<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Genius Hub</title>

    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>



<nav class="navbar">

    <div class="logo">
        <h2>Genius Hub</h2>
    </div>

    <ul class="nav-links">

        <li>
            <a href="homepage.php">Home</a>
        </li>

        <li>
            <a href="about.html">About</a>
        </li>

        <li>
            <a href="privacy-policy.html">Privacy Policy</a>
        </li>

    </ul>

</nav>


<section class="hero">

    <div class="hero-content">

        <h1>
            Learn. Connect. Grow.
        </h1>

        <p>
            Join students across different schools, learn together,
            solve problems, earn XP, and become part of a growing
            educational community.
        </p>

        <div class="hero-buttons">

            <a href="register.php" target="blank">

                <button class="primary-btn">
                    Get Started
                </button>

            </a>

            <a href="login.php" target="blank">

                <button class="secondary-btn">
                    Login
                </button>

            </a>

        </div>

    </div>

</section>


<section class="features">

    <h2>
        Why Genius Hub?
    </h2>

    <div class="feature-grid">

        <div class="feature-card">

            <h3>
                📚 Study Materials
            </h3>

            <p>
                Access tutorials, lessons, and educational
                resources across multiple subjects.
            </p>

        </div>


        <div class="feature-card">

            <h3>
                🌍 Student Community
            </h3>

            <p>
                Connect with students from different schools,
                collaborate, and share ideas together.
            </p>

        </div>


        <div class="feature-card">

            <h3>
                🏆 XP & Rewards
            </h3>

            <p>
                Earn points by learning, participating,
                helping others, and completing challenges.
            </p>

        </div>

    </div>

</section>



<section class="subjects">

    <h2>
        Popular Subjects
    </h2>

    <div class="subject-grid">

        <a href="#" class="subject-card">
            Physics
        </a>

        <a href="#" class="subject-card">
            Chemistry
        </a>

        <a href="#" class="subject-card">
            Biology
        </a>

        <a href="#" class="subject-card">
            Mathematics
        </a>
        
        <a href="#" class="subject-card">
            Further Mathematics
        </a>
        
        <a href="#" class="subject-card">
            Economics
        </a>
        
        <a href="#" class="subject-card">
            Commerce
        </a>
        
        <a href="#" class="subject-card">
            Financial Accounting
        </a>
        
        <a href="#" class="subject-card">
            Literature in English
        </a>
        
        <a href="#" class="subject-card">
            Government
        </a>

    </div>

</section>

<section class="community-preview">

    <h2>
        Learn Together
    </h2>

    <p>
        Ask questions, discuss ideas, solve assignments together,
        and become part of a fun educational environment.
    </p>

</section>


<section class="auth-section">

    <h2>
        Start Your Journey
    </h2>

    <p>
        Create your account and begin earning XP today.
    </p>

    <div class="auth-buttons">

        <a href="register.php" target="blank">

            <button class="primary-btn">
                Create Account
            </button>

        </a>

        <a href="login.php" target="blank">

            <button class="secondary-btn">
                Login
            </button>

        </a>

    </div>

</section>



<footer>

    <div class="newsletter">

        <h3>
            Subscribe To Our Newsletter
        </h3>

        <form>

            <input type="email"
                   placeholder="Enter your email"
                   required>

            <button type="submit">
                Subscribe
            </button>

        </form>

    </div>

    <hr style="margin: 30px 0;">

    <p>
        &copy 2025 Genius Hub. All rights reserved.
    </p>

</footer>

</body>
</html>
