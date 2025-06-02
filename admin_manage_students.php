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
$success_message = '';
$error_message = '';

// Check if user is admin from DB directly
$check_admin = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
$check_admin->bind_param("i", $user_id);
$check_admin->execute();
$result = $check_admin->get_result();
if ($result->num_rows === 0) {
    header("Location: login.php");
    exit();
}

$admin_data = $result->fetch_assoc();
if ($admin_data['role'] !== 'admin') {
    header("Location: admin.php");
    exit();
}

// Handle user role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $target_user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    
    if ($target_user_id !== $user_id && in_array($new_role, ['student', 'admin'])) {
        $update_stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_role, $target_user_id);
        
        if ($update_stmt->execute()) {
            $success_message = "✅ User role updated successfully!";
        } else {
            $error_message = "❌ Error updating user role.";
        }
        $update_stmt->close();
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success_message) . "&error=" . urlencode($error_message));
    exit();
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $target_user_id = (int)$_POST['user_id'];
    
    if ($target_user_id !== $user_id) {
        $conn->begin_transaction();
        try {
            // Delete user's subject registrations first
            $delete_subjects = $conn->prepare("DELETE FROM user_subjects WHERE user_id = ?");
            $delete_subjects->bind_param("i", $target_user_id);
            $delete_subjects->execute();
            
            // Delete user
            $delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delete_user->bind_param("i", $target_user_id);
            $delete_user->execute();
            
            $conn->commit();
            $success_message = "✅ User deleted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "❌ Error deleting user.";
        }
    } else {
        $error_message = "❌ Cannot delete your own account.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success_message) . "&error=" . urlencode($error_message));
    exit();
}

// Handle subject update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject'])) {
    $subject_id = (int)$_POST['subject_id'];
    $day = $_POST['day'];
    $time = $_POST['time'];
    $total_slots = max(1, (int)$_POST['total_slots']);
    $available_slots = max(0, (int)$_POST['available_slots']);
    
    // Ensure available slots don't exceed total slots
    if ($available_slots > $total_slots) {
        $available_slots = $total_slots;
    }
    
    $update_subject = $conn->prepare("UPDATE subjects SET day = ?, time_slot = ?, total_slots = ?, available_slots = ? WHERE id = ?");
    $update_subject->bind_param("ssiii", $day, $time, $total_slots, $available_slots, $subject_id);
    
    if ($update_subject->execute()) {
        $success_message = "✅ Subject updated successfully!";
    } else {
        $error_message = "❌ Error updating subject.";
    }
    $update_subject->close();
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success_message) . "&error=" . urlencode($error_message));
    exit();
}

// Get messages from URL parameters
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// Fetch all users except current admin with registration counts
$users_query = "SELECT u.id, u.username, u.email, u.role, 
                COUNT(us.subject_id) as registered_subjects 
                FROM users u 
                LEFT JOIN user_subjects us ON u.id = us.user_id 
                WHERE u.id != ? 
                GROUP BY u.id, u.username, u.email, u.role 
                ORDER BY u.role DESC, u.username";
$users_stmt = $conn->prepare($users_query);
$users_stmt->bind_param("i", $user_id);
$users_stmt->execute();
$users = $users_stmt->get_result();

// Fetch all subjects with registration counts
$subjects_query = "SELECT s.*, 
                   COUNT(us.user_id) as registered_count,
                   (s.total_slots - s.available_slots) as calculated_registered
                   FROM subjects s 
                   LEFT JOIN user_subjects us ON s.id = us.subject_id 
                   GROUP BY s.id 
                   ORDER BY s.subject_code";
$subjects = $conn->query($subjects_query);

// Get total statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$total_subjects = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
$total_registrations = $conn->query("SELECT COUNT(*) as count FROM user_subjects")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Subject Registration</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: #121212;
            color: #f0f0f0;
        }
        header {
            background-color: #1f1f1f;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #00bfff;
        }
        header h1 {
            margin: 0;
            color: #00bfff;
        }
        nav a {
            color: #00bfff;
            margin-left: 20px;
            text-decoration: none;
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        nav a:hover {
            background-color: #00bfff;
            color: #121212;
        }
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .welcome-section {
            background-color: #1e1e1e;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .welcome-section h2 {
            color: #00bfff;
            margin: 0 0 1rem 0;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background-color: #1e1e1e;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #333;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #00bfff;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #aaa;
            font-size: 0.9rem;
        }
        .section {
            background-color: #1e1e1e;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .section h3 {
            color: #00bfff;
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        th {
            color: #00bfff;
            background-color: #292929;
            font-weight: bold;
        }
        tr:hover {
            background-color: #252525;
        }
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .role-admin {
            background-color: #ff6b35;
            color: white;
        }
        .role-student {
            background-color: #00bfff;
            color: #121212;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-size: 0.85rem;
        }
        .btn-primary {
            background-color: #00bfff;
            color: #121212;
        }
        .btn-primary:hover {
            background-color: #0099cc;
        }
        .btn-danger {
            background-color: #ff4d4d;
            color: white;
        }
        .btn-danger:hover {
            background-color: #cc0000;
        }
        .btn-warning {
            background-color: #ffa726;
            color: #121212;
        }
        .btn-warning:hover {
            background-color: #ff9800;
        }
        .form-inline {
            display: inline-block;
            margin-right: 0.5rem;
        }
        .edit-form {
            display: none;
            background-color: #292929;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        .edit-form.active {
            display: block;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #00bfff;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            background-color: #333;
            color: #f0f0f0;
            border: 1px solid #555;
            padding: 0.5rem;
            border-radius: 3px;
            width: 100%;
            max-width: 200px;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        .success {
            background-color: #1a4d1a;
            color: #4dff4d;
            border: 1px solid #4dff4d;
        }
        .error {
            background-color: #4d1a1a;
            color: #ff4d4d;
            border: 1px solid #ff4d4d;
        }
        .no-data {
            text-align: center;
            color: #aaa;
            font-style: italic;
            padding: 2rem;
        }
        .subject-status {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-available {
            background-color: #1a4d1a;
            color: #4dff4d;
        }
        .status-full {
            background-color: #4d1a1a;
            color: #ff4d4d;
        }
        .status-partial {
            background-color: #4d4d1a;
            color: #ffff4d;
        }
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .quick-action-card {
            background-color: #1e1e1e;
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid #333;
            flex: 1;
            min-width: 250px;
        }
        .quick-action-card h4 {
            color: #00bfff;
            margin: 0 0 1rem 0;
        }
        .quick-action-card p {
            color: #aaa;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header>
        <h1>🛠️ Admin Dashboard</h1>
        <nav>
            <a href="admin.php">Overview</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2>Welcome back, <?= htmlspecialchars($admin_data['username']) ?>! 👋</h2>
            <p>Manage users, subjects, and system settings from this dashboard.</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="message success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $total_users ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_subjects ?></div>
                <div class="stat-label">Available Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_registrations ?></div>
                <div class="stat-label">Total Registrations</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action-card">
                <h4>👥 User Management</h4>
                <p>View, edit, and manage user accounts and roles.</p>
                <a href="#users-section" class="btn btn-primary">Manage Users</a>
            </div>
            <div class="quick-action-card">
                <h4>📚 Subject Management</h4>
                <p>Edit subject details, schedules, and capacity.</p>
                <a href="#subjects-section" class="btn btn-primary">Manage Subjects</a>
            </div>
            <div class="quick-action-card">
                <h4>🎯 Student Assignments</h4>
                <p>Assign and manage subjects for individual students.</p>
                <a href="admin_manage_students.php" class="btn btn-primary">Assign Subjects</a>
            </div>
        </div>

        <!-- Users Section -->
        <div class="section" id="users-section">
            <h3>👥 User Management</h3>
            <?php if ($users && $users->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered Subjects</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="role-badge role-<?= $user['role'] ?>">
                                        <?= htmlspecialchars($user['role']) ?>
                                    </span>
                                </td>
                                <td><?= $user['registered_subjects'] ?> subjects</td>
                                <td>
                                    <!-- Role Update Form -->
                                    <form method="post" class="form-inline">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="role" onchange="this.form.submit()">
                                            <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" name="update_role" class="btn btn-warning">Update Role</button>
                                    </form>
                                    
                                    <!-- Delete User Form -->
                                    <form method="post" class="form-inline" onsubmit="return confirm('Are you sure you want to delete user \'<?= htmlspecialchars($user['username']) ?>\'? This will also remove all their subject registrations.');">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No users found.</div>
            <?php endif; ?>
        </div>

        <!-- Subjects Section -->
        <div class="section" id="subjects-section">
            <h3>📚 Subject Management</h3>
            <?php if ($subjects && $subjects->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject Code</th>
                            <th>Course Name</th>
                            <th>Schedule</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <tr>
                                <td><?= $subject['id'] ?></td>
                                <td><strong><?= htmlspecialchars($subject['subject_code']) ?></strong></td>
                                <td><?= htmlspecialchars($subject['course_name']) ?></td>
                                <td><?= htmlspecialchars($subject['day']) ?><br><small><?= htmlspecialchars($subject['time_slot']) ?></small></td>
                                <td>
                                    <?= $subject['available_slots'] ?> / <?= $subject['total_slots'] ?>
                                    <br><small><?= $subject['registered_count'] ?> registered</small>
                                </td>
                                <td>
                                    <?php
                                    $available_ratio = $subject['total_slots'] > 0 ? $subject['available_slots'] / $subject['total_slots'] : 0;
                                    if ($subject['available_slots'] == 0): ?>
                                        <span class="subject-status status-full">FULL</span>
                                    <?php elseif ($available_ratio > 0.5): ?>
                                        <span class="subject-status status-available">AVAILABLE</span>
                                    <?php else: ?>
                                        <span class="subject-status status-partial">LIMITED</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="toggleEditForm(<?= $subject['id'] ?>)" class="btn btn-primary">Edit</button>
                                    
                                    <div id="edit-form-<?= $subject['id'] ?>" class="edit-form">
                                        <form method="post">
                                            <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Day:</label>
                                                    <select name="day" required>
                                                        <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $day): ?>
                                                            <option value="<?= $day ?>" <?= $subject['day'] === $day ? 'selected' : '' ?>>
                                                                <?= $day ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Time:</label>
                                                    <input type="text" name="time" value="<?= htmlspecialchars($subject['time_slot']) ?>" required placeholder="e.g. 10:00-12:00">
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Total Slots:</label>
                                                    <input type="number" name="total_slots" min="1" value="<?= $subject['total_slots'] ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label>Available Slots:</label>
                                                    <input type="number" name="available_slots" min="0" value="<?= $subject['available_slots'] ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div style="margin-top: 1rem;">
                                                <button type="submit" name="update_subject" class="btn btn-primary">Save Changes</button>
                                                <button type="button" onclick="toggleEditForm(<?= $subject['id'] ?>)" class="btn btn-danger">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No subjects found.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleEditForm(id) {
            const form = document.getElementById('edit-form-' + id);
            form.classList.toggle('active');
            
            // Close other edit forms
            document.querySelectorAll('.edit-form').forEach(function(otherForm) {
                if (otherForm.id !== 'edit-form-' + id) {
                    otherForm.classList.remove('active');
                }
            });
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 500);
            });
        }, 5000);

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>