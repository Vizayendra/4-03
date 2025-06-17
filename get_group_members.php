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

$conn->set_charset("utf8");
$user_id = $_SESSION['user_id'];


$group_id = intval($_GET['group_id']);
$query = "
    SELECT 
        u.username,
        CASE WHEN g.creator_id = u.id THEN 1 ELSE 0 END as is_creator
    FROM group_members gm
    JOIN users u ON gm.user_id = u.id
    JOIN groups g ON gm.group_id = g.id
    WHERE gm.group_id = ?
    ORDER BY is_creator DESC, u.username
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

header('Content-Type: application/json');
echo json_encode($members);
?>