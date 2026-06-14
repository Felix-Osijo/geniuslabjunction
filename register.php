<?php
session_start();

include (__DIR__."/includes/db.php");


if (isset($_SESSION["user_id"])) {

    header("Location: /includes/db.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {



    $surname = trim($_POST["surname"]);
    $othernames = trim($_POST["othernames"]);
    $dob = $_POST["dob"];
    $gender = $_POST["gender"];
    $email = trim($_POST["email"]);
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirmPassword"];

    if ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } else {


        $checkUser = $conn->prepare(
            "SELECT id FROM users
             WHERE email = ?
             OR username = ?"
        );

        $checkUser->bind_param(
            "ss",
            $email,
            $username
        );

        $checkUser->execute();

        $result = $checkUser->get_result();


        if ($result->num_rows > 0) {

            $error = "Email or Username already exists.";

        } else {


            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            $stmt = $conn->prepare(
                "INSERT INTO users
                (
                    surname,
                    othernames,
                    dob,
                    gender,
                    email,
                    username,
                    password
                )

                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            
            if (!$stmt) {
            	die("Prepare failed: ".$conn->error);
            	}

            $stmt->bind_param(
                "sssssss",
                $surname,
                $othernames,
                $dob,
                $gender,
                $email,
                $username,
                $hashedPassword
            );


            if ($stmt->execute()) {


                $_SESSION["user_id"] = $stmt->insert_id;

                $_SESSION["username"] = $username;


                header("Location: ../dashboard.php");
                exit();

            } else {

                $error = "Something went wrong.".$stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Create Account | Genius Lab Junction</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body class="auth-body">


<div class="auth-container">


    <div class="auth-left">

        <h1>Join The Community 🚀</h1>

        <p>
            Learn with students, participate in discussions,
            complete challenges, and earn XP as you grow.
        </p>

        <div class="auth-features">

            <div class="feature-item">
                📚 Interactive learning
            </div>

            <div class="feature-item">
                🌍 Student collaboration
            </div>

            <div class="feature-item">
                🏆 XP & achievements
            </div>

        </div>

    </div>



    <div class="auth-right">

        <form action="register.php"
              method="POST"
              class="auth-form">

            <h2>Create Account</h2>

            <p class="form-text">
                Start your Genius Lab Junction journey
            </p>


            <?php if (!empty($error)): ?>

                <p class="error-message">
                    <?php echo $error; ?>
                </p>

            <?php endif; ?>

            <div class="double-input">

                <input type="text"
                       name="surname"
                       placeholder="Surname"
                       required>

                <input type="text"
                       name="othernames"
                       placeholder="Other Names"
                       required>

            </div>


            <div class="double-input">

                <input type="date"
                       name="dob"
                       required>

                <select name="gender" required>

                    <option value="">
                        Select Gender
                    </option>

                    <option value="male">
                        Male
                    </option>

                    <option value="female">
                        Female
                    </option>

                </select>

            </div>


            <input type="text"
                   name="email"
                   placeholder="Email or Phone Number"
                   required>


            <input type="text"
                   name="username"
                   placeholder="Choose Username"
                   required>


            <input type="password"
                   name="password"
                   placeholder="Create Password"
                   required>


            <input type="password"
                   name="confirmPassword"
                   placeholder="Confirm Password"
                   required>


            <div class="terms-box">

                <input type="checkbox"
                       required>

                <label>

                    I agree to the
                    <a href="../terms-of-use.html">
                        Terms & Conditions
                    </a>

                </label>

            </div>


            <button type="submit"
                    class="primary-btn">

                Create Account

            </button>


            <div class="auth-links">

                <p>

                    Already have an account?

                    <a href="login.php">
                        Login
                    </a>

                </p>

            </div>

        </form>

    </div>

</div>

</body>
</html>
