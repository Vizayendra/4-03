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

// Check if user is admin
$check_admin = $conn->prepare("SELECT role FROM users WHERE id = ?");
$check_admin->bind_param("i", $user_id);
$check_admin->execute();
$result = $check_admin->get_result();
if ($result->num_rows === 0 || $result->fetch_assoc()['role'] !== 'admin') {
    header("Location: login.php");
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
    <title>Admin Dashboard</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #121212;
            color: #f0f0f0;
            margin: 0;
            padding: 2rem;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn-primary {
            background-color: #00bfff;
            color: #121212;
        }

        .btn-primary:hover {
            background-color: #008fcf;
        }

        .btn-danger {
            background-color: #ff4c4c;
            color: white;
        }

        .btn-danger:hover {
            background-color: #cc3a3a;
        }

        h1, h2 {
            color: #00bfff;
            margin-bottom: 1rem;
        }

        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 3rem;
        }

        .card {
            flex: 1;
            min-width: 260px;
            background-color: #1e1e1e;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 0 12px rgba(0, 191, 255, 0.1);
            transition: 0.2s ease;
        }

        .card:hover {
            transform: scale(1.02);
        }

        .card h2 {
            margin: 0 0 1rem 0;
        }

        .card p {
            font-size: 2rem;
            font-weight: bold;
            color: #00bfff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1e1e1e;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 3rem;
        }

        thead {
            background-color: #292929;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        tr:hover {
            background-color: #2b2b2b;
        }

        @media screen and (max-width: 600px) {
            .card-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="profile.php" class="btn btn-primary">← Back to Dashboard</a>
    <a href="logout.php" class="btn btn-danger">Logout</a>
    <a href="admin_manage_students.php" class="btn btn-primary">Manage Subject Registrations</a>
</div>

<h1>Admin Overview Dashboard</h1>

<div class="card-container">
    <div class="card">
        <h2>Total Users</h2>
        <p><?= $users ? $users->num_rows : 0 ?></p>
    </div>

    <div class="card">
        <h2>Total Subjects</h2>
        <p><?= $subjects ? $subjects->num_rows : 0 ?></p>
    </div>
</div>

<h2>Recent Users</h2>
<table>
    <thead>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>
    </thead>
    <tbody>
    <?php $users->data_seek(0); $i = 0; while ($user = $users->fetch_assoc()): if (++$i > 5) break; ?>
        <tr>
            <td><?= $user['id'] ?></td>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['role']) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<h2>Recent Subjects</h2>
<table>
    <thead>
        <tr><th>ID</th><th>Code</th><th>Course</th><th>Day</th><th>Time</th></tr>
    </thead>
    <tbody>
    <?php $subjects->data_seek(0); $i = 0; while ($sub = $subjects->fetch_assoc()): if (++$i > 5) break; ?>
        <tr>
            <td><?= $sub['id'] ?></td>
            <td><?= htmlspecialchars($sub['subject_code']) ?></td>
            <td><?= htmlspecialchars($sub['course_name']) ?></td>
            <td><?= htmlspecialchars($sub['day']) ?></td>
            <td><?= htmlspecialchars($sub['time_slot']) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
