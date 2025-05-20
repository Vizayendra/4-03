<?php
session_start();

// Checking if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirecting to login if not logged in
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Page</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #121212;
            color: #f0f0f0;
            text-align: center;
            padding: 3rem;
        }

        .profile-container {
            background-color: #1e1e1e;
            padding: 2rem;
            border-radius: 10px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }

        h1 {
            color: #00bfff;
        }

        .logout-btn {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.6rem 1.2rem;
            background-color: #00bfff;
            color: #fff;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #ffffff;
            color: #00bfff;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        <p>This is your profile page.</p>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</body>
</html>