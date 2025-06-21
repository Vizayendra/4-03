<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection with error handling
$conn = new mysqli("localhost", "root", "", "assigndb");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset for proper character handling
$conn->set_charset("utf8");

$user_id = $_SESSION['user_id'];

// Get user information
$user_stmt = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
if (!$user_stmt) {
    die("Prepare failed: " . $conn->error);
}

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user_data = $user_result->fetch_assoc();
$username = $user_data['username'];
$email = $user_data['email'];
$role = $user_data['role'];
$user_stmt->close();

// Initialize arrays
$subjects = [];
$clash_indices = [];
$group_counts = [];

// Get user's subjects with improved error handling
$subjects_stmt = $conn->prepare("SELECT s.id, s.subject_code, s.course_name, s.day, s.time_slot 
                               FROM user_subjects us
                               JOIN subjects s ON us.subject_id = s.id
                               WHERE us.user_id = ?
                               ORDER BY s.day, s.time_slot");

if (!$subjects_stmt) {
    die("Prepare failed: " . $conn->error);
}

$subjects_stmt->bind_param("i", $user_id);
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();

while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row;
}
$subjects_stmt->close();

// Get group counts for each subject
foreach ($subjects as $subject) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as group_count FROM groups WHERE subject_id = ?");
    if ($count_stmt) {
        $count_stmt->bind_param("i", $subject['id']);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();
        $group_counts[$subject['id']] = $count_data['group_count'];
        $count_stmt->close();
    } else {
        $group_counts[$subject['id']] = 0;
    }
}

$conn->close();

// Check for schedule clashes
for ($i = 0; $i < count($subjects); $i++) {
    for ($j = $i + 1; $j < count($subjects); $j++) {
        if ($subjects[$i]['day'] === $subjects[$j]['day'] &&
            $subjects[$i]['time_slot'] === $subjects[$j]['time_slot']) {
            $clash_indices[$i] = true;
            $clash_indices[$j] = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Subject Registration</title>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #121212;
            color: #f0f0f0;
            line-height: 1.6;
        }
        
        header {
            background-color: #1f1f1f;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #00bfff;
            flex-wrap: wrap;
        }
        
        header h1 {
            margin: 0;
            color: #00bfff;
        }
        
        nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        nav a {
            color: #00bfff;
            text-decoration: none;
            font-weight: bold;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        nav a:hover {
            background-color: #00bfff;
            color: #121212;
        }
        
        .btn {
            padding: 8px 12px;
            background-color: #00bfff;
            color: #121212;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #ffffff;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            background-color: #1e1e1e;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        
        .info-box {
            background-color: #292929;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #00bfff;
        }
        
        h2 {
            color: #00bfff;
            margin-bottom: 1rem;
        }
        
        .info-box p {
            margin: 0.5rem 0;
            font-size: 1.1rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: #292929;
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        
        th {
            background-color: #1f1f1f;
            color: #00bfff;
            font-weight: bold;
        }
        
        .no-subjects {
            text-align: center;
            margin-top: 2rem;
            font-style: italic;
            color: #aaa;
            padding: 2rem;
        }
        
        .no-subjects a {
            color: #00bfff;
            text-decoration: none;
            font-weight: bold;
        }
        
        .role-badge {
            background: #00bfff;
            color: #121212;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .clash {
            background-color: #330000 !important;
            color: #ff4d4d;
        }
        
        .clash td {
            border-color: #ff4d4d;
        }
        
        .subject-row {
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .subject-row:hover {
            background-color: #3a3a3a;
        }
        
        .group-info {
            font-size: 0.9em;
            color: #00bfff;
            font-weight: bold;
        }
        
        .group-btn {
            background: #00bfff;
            color: #121212;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            margin-left: 10px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .group-btn:hover {
            background: #ffffff;
        }
        
        .clickable-hint {
            font-size: 0.9em;
            color: #aaa;
            margin-bottom: 1rem;
            font-style: italic;
        }
        
        .message {
            background: #292929;
            color: #00bfff;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #00bfff;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
        }
        
        .modal-content {
            background-color: #1e1e1e;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            color: #f0f0f0;
            box-shadow: 0 4px 20px rgba(0, 191, 255, 0.3);
        }
        
        .modal-content h2 {
            color: #00bfff;
            margin-bottom: 1.5rem;
        }
        
        .modal-content label {
            display: block;
            margin-bottom: 0.5rem;
            color: #00bfff;
            font-weight: bold;
        }
        
        .modal-content input {
            width: 100%;
            padding: 12px;
            margin-bottom: 1rem;
            background: #292929;
            color: #fff;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .modal-content input:focus {
            outline: none;
            border-color: #00bfff;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
        
        .btn-secondary {
            background-color: #333;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background-color: #555;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                margin: 1rem;
                padding: 1rem;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 0.5rem;
            }
            
            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>My Dashboard</h1>
        <nav>
            <a href="add_subjects.php">Add Classes</a>
            <?php if ($role === 'admin'): ?>
                <a href="admin.php">Admin Panel</a>
            <?php endif; ?>
            <button class="btn" onclick="openPasswordModal()">Change Password</button>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($username); ?> 👋 
            <span class="role-badge"><?php echo strtoupper($role); ?></span>
        </h2>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
            <p><strong>Account Type:</strong> 
                <span class="role-badge"><?php echo strtoupper($role); ?></span>
            </p>
        </div>

        <h2>Registered Subjects</h2>
        <?php if (count($subjects) > 0): ?>
            <p class="clickable-hint">💡 Click on any subject to view or create study groups</p>
            <table>
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Course Name</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Study Groups</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $index => $subject): ?>
                        <tr class="subject-row <?php echo isset($clash_indices[$index]) ? 'clash' : ''; ?>" 
                            onclick="navigateToGroups(<?php echo $subject['id']; ?>)">
                            <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                            <td><?php echo htmlspecialchars($subject['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($subject['day']); ?></td>
                            <td><?php echo htmlspecialchars($subject['time_slot']); ?></td>
                            <td>
                                <span class="group-info">
                                    👥 <?php echo $group_counts[$subject['id']] ?? 0; ?> groups
                                </span>
                                <button class="group-btn" onclick="event.stopPropagation(); navigateToGroups(<?php echo $subject['id']; ?>)">
                                    View Groups
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!empty($clash_indices)): ?>
                <p style="color: #ff4d4d; margin-top: 1rem; font-weight: bold;">
                    ⚠️ Warning: You have schedule conflicts highlighted in red above.
                </p>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-subjects">
                <p>You haven't registered any subjects yet.</p>
                <a href="add_subjects.php">Register your first subject now!</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Password Change Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <h2>Change Password</h2>
            <form action="change_password.php" method="post" onsubmit="return validatePasswordForm()">
                <label for="old_password">Current Password</label>
                <input type="password" id="old_password" name="old_password" required>

                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">

                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Cancel</button>
                    <button type="submit" class="btn">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'block';
        }

        function closePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
            // Clear form
            document.getElementById('changePasswordModal').querySelector('form').reset();
        }

        function navigateToGroups(subjectId) {
            window.location.href = 'subject_groups.php?subject_id=' + subjectId;
        }

        function validatePasswordForm() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('changePasswordModal');
            if (event.target === modal) {
                closePasswordModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePasswordModal();
            }
        });
    </script>
</body>
</html>