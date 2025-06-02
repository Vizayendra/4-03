<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "assigndb");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Check if user is admin from DB directly
$check_admin = $conn->prepare("SELECT role FROM users WHERE id = ?");
$check_admin->bind_param("i", $user_id);
$check_admin->execute();
$result = $check_admin->get_result();
if ($result->num_rows === 0 || $result->fetch_assoc()['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target_user_id = (int)$_POST['user_id'];
    if ($target_user_id !== $user_id) {
        $conn->query("DELETE FROM user_subjects WHERE user_id = $target_user_id");
        $conn->query("DELETE FROM users WHERE id = $target_user_id");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle subject update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject'])) {
    $subject_id = (int)$_POST['subject_id'];
    $day = $conn->real_escape_string($_POST['day']);
    $time = $conn->real_escape_string($_POST['time']);
    $slots = max(1, (int)$_POST['slots']);

    $conn->query("UPDATE subjects SET day='$day', time_slot='$time', total_slots=$slots WHERE id=$subject_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all users except current admin
$users = $conn->query("SELECT id, username, email, role FROM users WHERE id != $user_id");

// Fetch all subjects
$subjects = $conn->query("SELECT * FROM subjects");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Panel</title>
    <style>
        body { font-family: Arial, sans-serif; background:#121212; color:#eee; padding:20px;}
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px;}
        th, td { padding: 8px; border-bottom: 1px solid #444;}
        th { background: #333; }
        tr:hover { background: #333; }
        .btn { padding: 6px 12px; border: none; cursor: pointer; border-radius: 4px; font-weight: bold;}
        .btn-danger { background: #e53935; color: #fff;}
        .btn-primary { background: #0097a7; color: #fff; text-decoration: none;}
        form.inline { display: inline; }
        .top-bar { margin-bottom: 20px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="profile.php" class="btn btn-primary">← Back to Dashboard</a>
    <a href="logout.php" class="btn btn-danger">Logout</a>
</div>

<h1>Admin Panel</h1>

<h2>Users</h2>
<?php if ($users && $users->num_rows > 0): ?>
<table>
    <thead>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Action</th></tr>
    </thead>
    <tbody>
    <?php while ($user = $users->fetch_assoc()): ?>
        <tr>
            <td><?= $user['id'] ?></td>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['role']) ?></td>
            <td>
                <form method="post" class="inline" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?');">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>" />
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
    <p>No users found.</p>
<?php endif; ?>

<h2>Subjects</h2>
<?php if ($subjects && $subjects->num_rows > 0): ?>
<table>
    <thead>
        <tr><th>ID</th><th>Subject Code</th><th>Course Name</th><th>Day</th><th>Time</th><th>Total Slots</th><th>Action</th></tr>
    </thead>
    <tbody>
    <?php while ($sub = $subjects->fetch_assoc()): ?>
    <tr>
        <td><?= $sub['id'] ?></td>
        <td><?= htmlspecialchars($sub['subject_code']) ?></td>
        <td><?= htmlspecialchars($sub['course_name']) ?></td>
        <td><?= htmlspecialchars($sub['day']) ?></td>
        <td><?= htmlspecialchars($sub['time_slot']) ?></td>
        <td><?= $sub['total_slots'] ?></td>
        <td>
            <button onclick="toggleEditForm(<?= $sub['id'] ?>)" class="btn btn-primary">Edit</button>
            <form method="post" id="edit-form-<?= $sub['id'] ?>" style="display:none; margin-top: 10px;">
                <input type="hidden" name="subject_id" value="<?= $sub['id'] ?>" />
                <select name="day" required>
                    <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
                    <option value="<?= $d ?>" <?= $sub['day'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="time" name="time" value="<?= htmlspecialchars($sub['time_slot']) ?>" required />
                <input type="number" name="slots" min="1" value="<?= $sub['total_slots'] ?>" required />
                <button type="submit" name="update_subject" class="btn btn-primary">Save</button>
                <button type="button" onclick="toggleEditForm(<?= $sub['id'] ?>)" class="btn btn-danger">Cancel</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
<p>No subjects found.</p>
<?php endif; ?>

<script>
function toggleEditForm(id) {
    const form = document.getElementById('edit-form-' + id);
    form.style.display = (form.style.display === 'block') ? 'none' : 'block';
}
</script>

</body>
</html>
