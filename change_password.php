<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "assigndb");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$old_password = $_POST['old_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

// Check if new passwords match
if ($new_password !== $confirm_password) {
    $_SESSION['message'] = "❌ New passwords do not match.";
    header("Location: profile.php");
    exit();
}

// Get the hashed password from DB
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($hashed_password_from_db);
$stmt->fetch();
$stmt->close();

if (!password_verify($old_password, $hashed_password_from_db)) {
    $_SESSION['message'] = "❌ Current password is incorrect.";
    header("Location: profile.php");
    exit();
}

// Hash the new password
$new_hashed = password_hash($new_password, PASSWORD_DEFAULT);

// Update the password
$update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update->bind_param("si", $new_hashed, $user_id);

if ($update->execute()) {
    $_SESSION['message'] = "✅ Password changed successfully.";
} else {
    $_SESSION['message'] = "❌ Error updating password.";
}
$update->close();
$conn->close();

header("Location: profile.php");
exit();
?>