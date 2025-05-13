<?php
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "assigndb";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = ""; // Initialize message

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);

    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $hashed_password);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            session_start();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;

            header("Location: profile.php");
            exit();
        } else {
            $message = "Invalid username or password!";
        }
    } else {
        $message = "Invalid username or password!";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        /* Your existing styles here */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; line-height: 1.6; background-color: #222; color: #e0e0e0; }
        .container { width: 90%; max-width: 800px; margin: 0 auto; overflow: hidden; }
        .login { padding: 4rem 0; text-align: center; background: #000; }
        .login h2 { color: #00bfff; margin-bottom: 1.5rem; font-size: 2rem; }
        form { max-width: 500px; margin: 0 auto; background: #333; padding: 2rem; border-radius: 8px; box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4); }
        form label { display: block; margin-bottom: 0.5rem; color: #e0e0e0; }
        form input { width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #555; background: #444; color: #e0e0e0; border-radius: 4px; }
        form button { padding: 0.75rem 1.5rem; background: #00bfff; color: #ffffff; border: none; border-radius: 8px; cursor: pointer; transition: background 0.3s ease, transform 0.3s ease; font-size: 1.1rem; }
        form button:hover { background: #ffffff; color: #00bfff; transform: scale(1.05); }
        .message { margin-top: 1rem; font-weight: bold; color: #ff4d4d; }
    </style>
</head>
<body>
    <div class="login">
        <h2>Login</h2>
        <form method="POST" action="">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>

            <?php if (!empty($message)) { echo "<div class='message'>$message</div>"; } ?>
        </form>
    </div>
</body>
</html>
