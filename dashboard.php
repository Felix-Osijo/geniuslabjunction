<?php
session_start();


if (!isset($_SESSION["user_id"])) {

    header("Location: signin-up/login.php");
    exit();
}


$username = $_SESSION["username"];
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Genius Lab Junction</title>

    <link rel="stylesheet"
          href="assets/css/style.css">

</head>

<body>


<nav class="navbar">

    <div class="logo">
        <h2> Dashboard</h2>
    </div>

    <ul class="nav-links">

        <li>
            <a href="dashboard.php">
                Dashboard
            </a>
        </li>

        <li>
            <a href="#">
                Community
            </a>
        </li>

        <li>
            <a href="#">
                Courses
            </a>
        </li>

        <li>
            <a href="logout.php">
                Logout
            </a>
        </li>

    </ul>

</nav>

<section class="dashboard-hero">

    <div class="dashboard-content">

        <h1>
            Welcome back,
            <?php echo htmlspecialchars($username); ?> 👋
        </h1>

        <p>
            Continue learning, connect with students,
            complete challenges, and grow your XP.
        </p>

    </div>

</section>


<section class="dashboard-grid">



    <div class="dashboard-card">

        <h2>
            🏆 Your XP
        </h2>

        <p class="big-number">
            120 XP
        </p>

        <p>
            Keep learning to level up.
        </p>

    </div>



    <div class="dashboard-card">

        <h2>
            📚 Subjects
        </h2>

        <ul class="dashboard-list">

            <li>Web Development</li>
            <li>Graphic Design</li>
            <li>Science Tutorials</li>
            <li>Mathematics</li>

        </ul>

    </div>




    <div class="dashboard-card">

        <h2>
            🌍 Community
        </h2>

        <p>
            Join discussions, ask questions,
            and collaborate with students.
        </p>

        <button class="primary-btn">
            Explore Community
        </button>

    </div>





    <div class="dashboard-card">

        <h2>
            🚀 Leaderboard
        </h2>

        <p>
            Top students this week:
        </p>

        <ul class="dashboard-list">

            <li>🥇 David — 890 XP</li>
            <li>🥈 Sarah — 760 XP</li>
            <li>🥉 Daniel — 700 XP</li>

        </ul>

    </div>

</section>



<section class="recent-activity">

    <h2>
        Recent Activity
    </h2>

    <div class="activity-card">

        <p>
            📢 New Science Tutorial uploaded.
        </p>

    </div>

    <div class="activity-card">

        <p>
            🏆 XP challenge starts tomorrow.
        </p>

    </div>

    <div class="activity-card">

        <p>
            🌍 Community discussion is trending.
        </p>

    </div>

</section>



<footer>

    <p>
        © 2025 Genius Lab Junction
    </p>

</footer>


</body>
</html>