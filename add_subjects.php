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
$username = $_SESSION['username'];
$selected_subjects = [];
$available_subjects = [];
$clashing_ids = [];
$message = "";

// Handle subject registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_id'])) {
    $subject_id = intval($_POST['subject_id']);

    $check_sub = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
    $check_sub->bind_param("i", $subject_id);
    $check_sub->execute();
    $result_sub = $check_sub->get_result();

    if ($result_sub->num_rows > 0) {
        $subject = $result_sub->fetch_assoc();

        $day = $subject['day'];
        $time = $subject['time_slot'];

        // Check for existing clash
        $clash_check = $conn->prepare("SELECT s.id FROM user_subjects us JOIN subjects s ON us.subject_id = s.id WHERE us.user_id = ? AND s.day = ? AND s.time_slot = ?");
        $clash_check->bind_param("iss", $user_id, $day, $time);
        $clash_check->execute();
        $clash_result = $clash_check->get_result();

        $is_clash = $clash_result->num_rows > 0;

        if ($subject['available_slots'] <= 0) {
            $message = "❌ No available slots!";
        } else {
            $duplicate = $conn->prepare("SELECT * FROM user_subjects WHERE user_id = ? AND subject_id = ?");
            $duplicate->bind_param("ii", $user_id, $subject_id);
            $duplicate->execute();
            $dup_result = $duplicate->get_result();

            if ($dup_result->num_rows > 0) {
                $message = "❌ You have already registered this subject.";
            } else {
                $insert = $conn->prepare("INSERT INTO user_subjects (user_id, subject_id) VALUES (?, ?)");
                $insert->bind_param("ii", $user_id, $subject_id);
                $insert->execute();

                $update = $conn->prepare("UPDATE subjects SET available_slots = available_slots - 1 WHERE id = ?");
                $update->bind_param("i", $subject_id);
                $update->execute();

                $message = $is_clash ? "⚠️ Registered, but class clashes with another!" : "✅ Subject registered!";
            }
        }
    } else {
        $message = "❌ Subject not found.";
    }
}

// Handle subject removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_subject_id'])) {
    $remove_id = intval($_POST['remove_subject_id']);

    $del = $conn->prepare("DELETE FROM user_subjects WHERE user_id = ? AND subject_id = ?");
    $del->bind_param("ii", $user_id, $remove_id);
    $del->execute();

    $upd = $conn->prepare("UPDATE subjects SET available_slots = available_slots + 1 WHERE id = ?");
    $upd->bind_param("i", $remove_id);
    $upd->execute();

    $message = "✅ Subject unregistered.";
}

// Handle subject request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_subject_code'], $_POST['new_course_name'])) {
    $code = $_POST['new_subject_code'];
    $name = $_POST['new_course_name'];

    $req = $conn->prepare("INSERT INTO subject_requests (user_id, subject_code, course_name) VALUES (?, ?, ?)");
    $req->bind_param("iss", $user_id, $code, $name);
    $req->execute();

    $message = "✅ Subject request submitted!";
}

// Fetch selected subjects
$get_selected = $conn->prepare("SELECT s.* FROM user_subjects us JOIN subjects s ON us.subject_id = s.id WHERE us.user_id = ?");
$get_selected->bind_param("i", $user_id);
$get_selected->execute();
$selected_result = $get_selected->get_result();
while ($row = $selected_result->fetch_assoc()) {
    $selected_subjects[] = $row;
}

// Detect clashes
foreach ($selected_subjects as $s1) {
    foreach ($selected_subjects as $s2) {
        if ($s1['id'] !== $s2['id'] && $s1['day'] === $s2['day'] && $s1['time_slot'] === $s2['time_slot']) {
            $clashing_ids[] = $s1['id'];
            $clashing_ids[] = $s2['id'];
        }
    }
}
$clashing_ids = array_unique($clashing_ids);

// Fetch all subjects
$get_subs = $conn->query("SELECT * FROM subjects ORDER BY subject_code");
while ($row = $get_subs->fetch_assoc()) {
    $available_subjects[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Subject Registration</title>
    <style>
        body {
            background-color: #121212;
            color: white;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 900px;
            margin: auto;
            padding: 20px;
            background: #1e1e1e;
            border-radius: 10px;
        }
        h2 {
            color: #00bfff;
        }
        .btn {
            background: #00bfff;
            color: white;
            border: none;
            padding: 8px 15px;
            margin: 4px 0;
            cursor: pointer;
            border-radius: 6px;
        }
        .btn:hover {
            background: white;
            color: #00bfff;
        }
        table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #333;
        }
        .clashing {
            background-color: #8b0000 !important; /* dark red clash warning */
        }
        .popup {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            justify-content: center;
            align-items: center;
        }
        .popup-content {
            background: #222;
            padding: 20px;
            border-radius: 10px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            background: #333;
            color: white;
            border: none;
            margin-bottom: 10px;
        }
    </style>
    <script>
        function showPopup() {
            document.getElementById("popup").style.display = "flex";
        }
        function closePopup() {
            document.getElementById("popup").style.display = "none";
        }
    </script>
</head>
<body>
<div class="container">
    <h2>Welcome, <?= htmlspecialchars($username) ?> 👋</h2>
    <p><a href="profile.php" style="color:#00bfff;">← Back to Profile</a></p>

    <?php if ($message): ?>
        <div style="background:#333;padding:10px;border-radius:6px;margin-bottom:15px;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <h3>📘 Register for Subjects</h3>
    <form method="POST">
        <table>
            <tr>
                <th>Select</th>
                <th>Code</th>
                <th>Name</th>
                <th>Day</th>
                <th>Time</th>
                <th>Slots</th>
            </tr>
            <?php foreach ($available_subjects as $sub): ?>
                <tr>
                    <td><input type="radio" name="subject_id" value="<?= $sub['id'] ?>" required></td>
                    <td><?= $sub['subject_code'] ?></td>
                    <td><?= $sub['course_name'] ?></td>
                    <td><?= $sub['day'] ?></td>
                    <td><?= $sub['time_slot'] ?></td>
                    <td><?= $sub['available_slots'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <button type="submit" class="btn">Register</button>
    </form>

    <h3>✅ Your Registered Subjects</h3>
    <table>
        <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Day</th>
            <th>Time</th>
            <th>Action</th>
        </tr>
        <?php foreach ($selected_subjects as $sub): ?>
            <tr class="<?= in_array($sub['id'], $clashing_ids) ? 'clashing' : '' ?>">
                <td><?= $sub['subject_code'] ?></td>
                <td><?= $sub['course_name'] ?></td>
                <td><?= $sub['day'] ?></td>
                <td><?= $sub['time_slot'] ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="remove_subject_id" value="<?= $sub['id'] ?>">
                        <button class="btn" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>
    <button class="btn" onclick="showPopup()">📩 Request New Subject</button>

    <div class="popup" id="popup">
        <div class="popup-content">
            <h3>Request Subject</h3>
            <form method="POST">
                <input type="text" name="new_subject_code" placeholder="Subject Code" required>
                <input type="text" name="new_course_name" placeholder="Course Name" required>
                <button class="btn" type="submit">Submit</button>
                <button class="btn" type="button" onclick="closePopup()">Cancel</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
