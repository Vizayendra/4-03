<?php
session_start();

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "assigndb");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$selected_subjects = [];
$available_subjects = [];

// Handle subject selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_id'])) {
    $subject_id = intval($_POST['subject_id']);

    // Check available slots first
    $slot_check = $conn->prepare("SELECT available_slots FROM subjects WHERE id = ?");
    $slot_check->bind_param("i", $subject_id);
    $slot_check->execute();
    $slot_result = $slot_check->get_result();
    
    if ($slot_result->num_rows > 0) {
        $subject_data = $slot_result->fetch_assoc();
        if ($subject_data['available_slots'] > 0) {
            
            // Prevent duplicates
            $check = $conn->prepare("SELECT id FROM user_subjects WHERE user_id = ? AND subject_id = ?");
            $check->bind_param("ii", $user_id, $subject_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows === 0) {
                // Register subject
                $insert = $conn->prepare("INSERT INTO user_subjects (user_id, subject_id) VALUES (?, ?)");
                $insert->bind_param("ii", $user_id, $subject_id);
                $insert->execute();
                $insert->close();
                
                // Update available slots
                $update = $conn->prepare("UPDATE subjects SET available_slots = available_slots - 1 WHERE id = ?");
                $update->bind_param("i", $subject_id);
                $update->execute();
                $update->close();
            }
            $check->close();
        }
    }
    $slot_check->close();
}

// Get all available subjects (with slots)
$result = $conn->query("SELECT id, subject_code, course_name, day, time_slot, available_slots 
                        FROM subjects 
                        WHERE available_slots > 0
                        ORDER BY subject_code");
while ($row = $result->fetch_assoc()) {
    $available_subjects[] = $row;
}

// Get subjects selected by this user
$result = $conn->prepare("SELECT s.id, s.subject_code, s.course_name, s.day, s.time_slot
                          FROM user_subjects us
                          JOIN subjects s ON us.subject_id = s.id
                          WHERE us.user_id = ?");
$result->bind_param("i", $user_id);
$result->execute();
$selected = $result->get_result();
while ($row = $selected->fetch_assoc()) {
    $selected_subjects[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Subjects</title>
    <style>
        body {
            background-color: #121212;
            font-family: Arial, sans-serif;
            color: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            background-color: #1e1e1e;
            padding: 2rem;
            border-radius: 12px;
        }
        h1, h2 {
            color: #00bfff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        th {
            background-color: #292929;
        }
        .btn {
            padding: 0.5rem 1rem;
            background-color: #00bfff;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #fff;
            color: #00bfff;
        }
        .nav {
            margin-bottom: 2rem;
        }
        .nav a {
            color: #00bfff;
            margin-right: 1rem;
            text-decoration: none;
            font-weight: bold;
        }
        .nav a:hover {
            color: #fff;
        }
        .slots {
            color: #4CAF50;
            font-weight: bold;
        }
        .full {
            color: #F44336;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="profile.php">← Back to Profile</a>
        <a href="logout.php">Logout</a>
    </div>
    
    <h1>Add Subjects</h1>
    <p>Welcome, <?php echo htmlspecialchars($username); ?></p>

    <h2>Available Subjects</h2>
    <?php if (count($available_subjects) > 0): ?>
        <form method="POST">
            <table>
                <tr>
                    <th>Select</th>
                    <th>Code</th>
                    <th>Course Name</th>
                    <th>Schedule</th>
                    <th>Available Slots</th>
                </tr>
                <?php foreach ($available_subjects as $subject): ?>
                    <tr>
                        <td>
                            <input type="radio" name="subject_id" value="<?php echo $subject['id']; ?>" required>
                        </td>
                        <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                        <td><?php echo htmlspecialchars($subject['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($subject['day']); ?> @ <?php echo htmlspecialchars($subject['time_slot']); ?></td>
                        <td class="slots"><?php echo $subject['available_slots']; ?> left</td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <button type="submit" class="btn">Register Selected Subject</button>
        </form>
    <?php else: ?>
        <p>No available subjects at this time.</p>
    <?php endif; ?>

    <h2>Your Currently Registered Subjects</h2>
    <?php if (count($selected_subjects) > 0): ?>
        <table>
            <tr>
                <th>Code</th>
                <th>Course Name</th>
                <th>Schedule</th>
            </tr>
            <?php foreach ($selected_subjects as $sub): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sub['subject_code']); ?></td>
                    <td><?php echo htmlspecialchars($sub['course_name']); ?></td>
                    <td><?php echo htmlspecialchars($sub['day']); ?> @ <?php echo htmlspecialchars($sub['time_slot']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>You haven't registered any subjects yet.</p>
    <?php endif; ?>
</div>
</body>
</html>