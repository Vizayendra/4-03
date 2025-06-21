<?php

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "assigndb";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check for duplicate email or username
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check->bind_param("ss", $email, $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "Email or username already taken!";
    } else {
        // Insert user if no duplicates found
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $hashed_password);

        if ($stmt->execute()) {
            $message = "Registration successful!";
        } else {
            $message = "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    $check->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            overflow: hidden;
        }

        header {
            background: #000;
            color: #00bfff;
            padding: 1.5rem 0;
            position: relative;
            border-bottom: 2px solid #00bfff;
        }

        header h1 {
            float: left;
            margin-left: 1.5rem;
            font-size: 2rem;
        }

        nav {
            float: right;
            margin-right: 1.5rem;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 20px;
        }

        nav ul li a {
            color: #00bfff;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        nav ul li a:hover {
            color: #ffffff;
        }

        .register {
            padding: 2rem 0;
            text-align: center;
            background: #333;
        }

        .register h2 {
            color: #00bfff;
        }

        form {
            max-width: 500px;
            margin: 0 auto;
            background: #444;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
            transition: box-shadow 0.3s ease;
        }

        form:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.6);
        }

        form label {
            display: block;
            margin-bottom: 0.5rem;
            color: #e0e0e0;
        }

        form input {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #555;
            border-radius: 4px;
            background: #333;
            color: #e0e0e0;
        }

        form button {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
            text-align: center;
            text-decoration: none;
            color: #ffffff;
            background: #00bfff;
            border: none;
            font-size: 1.1rem;
        }

        form button:hover {
            background: #ffffff;
            color: #00bfff;
            transform: scale(1.05);
        }

        .message {
            margin-top: 1rem;
            font-weight: bold;
            color: #ff5555;
        }

        footer {
            background: #000;
            color: #00bfff;
            text-align: center;
            padding: 1.5rem 0;
            border-top: 2px solid #00bfff;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>MMU student</h1>
            <nav>
                <ul>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="register">
        <div class="container">
            <h2>Register</h2>
            <form action="register.php" method="post">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Register</button>
            </form>
            <?php if (isset($message)) { echo "<p class='message'>$message</p>"; } ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 MMU. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
