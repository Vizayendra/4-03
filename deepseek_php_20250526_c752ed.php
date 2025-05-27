<?php
session_start();

// SECURE ADMIN CHECK
if (!isset($_SESSION['user_id']) {
    header("Location: login.php");
    exit();
}

// DATABASE CONNECTION
require_once 'db_config.php'; // Better security practice
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("🔥 Connection failed: " . $conn->connect_error);
}

// VERIFY ADMIN STATUS
$admin_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
$admin_check->bind_param("i", $_SESSION['user_id']);
$admin_check->execute();
$admin_result = $admin_check->get_result();

if ($admin_result->num_rows === 0 || $admin_result->fetch_assoc()['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// ADMIN POWER FUNCTIONS ⚡

// 1. USER NUKER
if (isset($_POST['nuke_user'])) {
    $user_id = intval($_POST['user_id']);
    if ($user_id !== $_SESSION['user_id']) { // Prevent self-nuke
        // Begin transaction for atomic operations
        $conn->begin_transaction();
        try {
            // Delete all subject registrations
            $conn->query("DELETE FROM user_subjects WHERE user_id = $user_id");
            // Delete the user
            $conn->query("DELETE FROM users WHERE id = $user_id");
            $conn->commit();
            $nuke_success = true;
        } catch (Exception $e) {
            $conn->rollback();
            $nuke_error = "Nuke failed: " . $e->getMessage();
        }
    }
}

// 2. SUBJECT EDITOR 9000
if (isset($_POST['edit_subject'])) {
    $subject_id = intval($_POST['subject_id']);
    $day = $conn->real_escape_string($_POST['day']);
    $time = $conn->real_escape_string($_POST['time']);
    $slots = intval($_POST['slots']);
    
    $conn->query("UPDATE subjects SET 
                day = '$day', 
                time_slot = '$time', 
                total_slots = $slots,
                available_slots = GREATEST(0, $slots - (SELECT COUNT(*) FROM user_subjects WHERE subject_id = $subject_id))
                WHERE id = $subject_id");
}

// 3. SUBJECT CREATOR
if (isset($_POST['create_subject'])) {
    $code = $conn->real_escape_string($_POST['code']);
    $name = $conn->real_escape_string($_POST['name']);
    $day = $conn->real_escape_string($_POST['day']);
    $time = $conn->real_escape_string($_POST['time']);
    $slots = intval($_POST['slots']);
    
    $conn->query("INSERT INTO subjects (subject_code, course_name, day, time_slot, total_slots, available_slots)
                 VALUES ('$code', '$name', '$day', '$time', $slots, $slots)");
}

// 4. MASS EMAIL SYSTEM
if (isset($_POST['send_announcement'])) {
    $subject = $conn->real_escape_string($_POST['email_subject']);
    $message = $conn->real_escape_string($_POST['email_message']);
    
    // Get all user emails
    $emails = $conn->query("SELECT email FROM users WHERE id != " . $_SESSION['user_id']);
    
    // In real implementation, you would send emails here
    $email_count = $emails->num_rows;
    $email_success = "📧 Announcement prepared for $email_count users!";
}

// DATA FETCHING FOR DASHBOARD

// Get all users (except admin)
$users = $conn->query("SELECT id, username, email, role, 
                      (SELECT COUNT(*) FROM user_subjects WHERE user_id = users.id) as subject_count
                      FROM users WHERE id != " . $_SESSION['user_id'] . " ORDER BY username");

// Get all subjects with registration stats
$subjects = $conn->query("SELECT s.*, 
                         COUNT(us.user_id) as registered_users,
                         GROUP_CONCAT(u.username SEPARATOR ', ') as student_list
                         FROM subjects s
                         LEFT JOIN user_subjects us ON s.id = us.subject_id
                         LEFT JOIN users u ON us.user_id = u.id
                         GROUP BY s.id ORDER BY s.subject_code");

// Get admin's own subjects
$admin_subjects = $conn->query("SELECT s.* FROM user_subjects us
                               JOIN subjects s ON us.subject_id = s.id
                               WHERE us.user_id = " . $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Ultra Power Mode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --admin-red: #dc3545;
            --admin-blue: #0d6efd;
            --admin-green: #198754;
            --admin-purple: #6f42c1;
        }
        body {
            background-color: #121212;
            color: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
            border-bottom: 3px solid var(--admin-red);
        }
        .power-card {
            background: #1e1e1e;
            border-radius: 10px;
            border-left: 4px solid var(--admin-red);
            transition: transform 0.3s;
        }
        .power-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        .badge-admin {
            background-color: var(--admin-red);
        }
        .badge-student {
            background-color: var(--admin-blue);
        }
        .slot-indicator {
            width: 100%;
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
        }
        .slot-full {
            background-color: var(--admin-red);
        }
        .slot-available {
            background-color: var(--admin-green);
        }
        .nav-pills .nav-link.active {
            background-color: var(--admin-red);
        }
        .nav-pills .nav-link {
            color: #adb5bd;
        }
        .subject-chip {
            background-color: #2a2a2a;
            border-radius: 20px;
            padding: 5px 10px;
            margin: 2px;
            display: inline-block;
            font-size: 0.8rem;
        }
        .power-btn {
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        #emailModal .modal-content {
            background-color: #1e1e1e;
        }
    </style>
</head>
<body>
    <!-- ADMIN POWER NAVBAR -->
    <nav class="navbar navbar-expand-lg admin-header mb-4">
        <div class="container-fluid">
            <a class="navbar-brand text-danger fw-bold" href="#">
                <i class="bi bi-lightning-charge-fill"></i> ADMIN ULTRA MODE
            </a>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    Logged in as: <span class="text-warning"><?= htmlspecialchars($_SESSION['username']) ?></span>
                </span>
                <a href="logout.php" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- ADMIN DASHBOARD GRID -->
        <div class="row">
            <!-- QUICK STATS SIDEBAR -->
            <div class="col-lg-3">
                <div class="card power-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-speedometer2"></i> Power Stats</h5>
                        <hr>
                        <div class="mb-3">
                            <h6><i class="bi bi-people-fill"></i> Total Users</h6>
                            <h4 class="text-center"><?= $users->num_rows + 1 ?></h4>
                        </div>
                        <div class="mb-3">
                            <h6><i class="bi bi-book-half"></i> Total Subjects</h6>
                            <h4 class="text-center"><?= $subjects->num_rows ?></h4>
                        </div>
                        <div class="mb-3">
                            <h6><i class="bi bi-activity"></i> System Status</h6>
                            <div class="progress mt-2">
                                <div class="progress-bar progress-bar-striped bg-success" role="progressbar" style="width: 100%">OPERATIONAL</div>
                            </div>
                        </div>
                        <button class="btn btn-danger w-100 power-btn mt-2" data-bs-toggle="modal" data-bs-target="#emailModal">
                            <i class="bi bi-megaphone-fill"></i> Send Announcement
                        </button>
                    </div>
                </div>

                <!-- QUICK ACTIONS -->
                <div class="card power-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-lightning"></i> Quick Actions</h5>
                        <hr>
                        <button class="btn btn-primary w-100 power-btn mb-2" data-bs-toggle="modal" data-bs-target="#createSubjectModal">
                            <i class="bi bi-plus-circle"></i> Create Subject
                        </button>
                        <a href="backup.php" class="btn btn-warning w-100 power-btn mb-2">
                            <i class="bi bi-database"></i> Backup Database
                        </a>
                        <a href="audit_log.php" class="btn btn-info w-100 power-btn">
                            <i class="bi bi-clipboard-data"></i> View Audit Log
                        </a>
                    </div>
                </div>
            </div>

            <!-- MAIN POWER ZONE -->
            <div class="col-lg-9">
                <ul class="nav nav-pills mb-4" id="adminTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="users-tab" data-bs-toggle="pill" data-bs-target="#users" type="button">
                            <i class="bi bi-people-fill"></i> User Control
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="subjects-tab" data-bs-toggle="pill" data-bs-target="#subjects" type="button">
                            <i class="bi bi-book"></i> Subject Control
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="myaccount-tab" data-bs-toggle="pill" data-bs-target="#myaccount" type="button">
                            <i class="bi bi-person-badge"></i> My Admin Account
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="adminTabsContent">
                    <!-- USER CONTROL PANEL -->
                    <div class="tab-pane fade show active" id="users" role="tabpanel">
                        <div class="card power-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title"><i class="bi bi-people-fill"></i> User Management</h5>
                                    <span class="badge bg-dark">Total: <?= $users->num_rows ?></span>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Subjects</th>
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
                                                    <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-student' ?>">
                                                        <?= ucfirst($user['role']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= $user['subject_count'] ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="user_detail.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-danger" onclick="confirmNuke(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                    <form id="nuke-form-<?= $user['id'] ?>" method="POST" style="display: none;">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="nuke_user" value="1">
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUBJECT CONTROL PANEL -->
                    <div class="tab-pane fade" id="subjects" role="tabpanel">
                        <div class="card power-card">
                            <div class="card-body">
                                <h5 class="card-title mb-4"><i class="bi bi-book"></i> Subject Control Center</h5>
                                
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Course Name</th>
                                                <th>Schedule</th>
                                                <th>Slots</th>
                                                <th>Students</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($subject = $subjects->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($subject['subject_code']) ?></td>
                                                <td><?= htmlspecialchars($subject['course_name']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($subject['day']) ?>
                                                    <small class="text-muted d-block"><?= htmlspecialchars($subject['time_slot']) ?></small>
                                                </td>
                                                <td>
                                                    <?= $subject['available_slots'] ?>/<?= $subject['total_slots'] ?>
                                                    <div class="slot-indicator <?= $subject['available_slots'] <= 0 ? 'slot-full' : 'slot-available' ?>" 
                                                         style="width: <?= ($subject['available_slots']/$subject['total_slots'])*100 ?>%"></div>
                                                </td>
                                                <td>
                                                    <?php if ($subject['registered_users'] > 0): ?>
                                                        <button class="btn btn-sm btn-info" data-bs-toggle="popover" 
                                                                title="Registered Students" 
                                                                data-bs-content="<?= htmlspecialchars($subject['student_list']) ?>">
                                                            <?= $subject['registered_users'] ?> <i class="bi bi-person-lines-fill"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">None</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                                data-bs-target="#editSubjectModal" 
                                                                onclick="loadEditForm(<?= $subject['id'] ?>, '<?= htmlspecialchars($subject['subject_code']) ?>', 
                                                                '<?= htmlspecialchars($subject['course_name']) ?>', '<?= htmlspecialchars($subject['day']) ?>', 
                                                                '<?= htmlspecialchars($subject['time_slot']) ?>', <?= $subject['total_slots'] ?>)">
                                                            <i class="bi bi-pencil-square"></i> Edit
                                                        </button>
                                                        <a href="subject_detail.php?id=<?= $subject['id'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ADMIN ACCOUNT PANEL -->
                    <div class="tab-pane fade" id="myaccount" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card power-card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-person-badge"></i> My Admin Profile</h5>
                                        <hr>
                                        <div class="mb-3">
                                            <h6>Username</h6>
                                            <p class="text-muted"><?= htmlspecialchars($_SESSION['username']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <h6>Email</h6>
                                            <p class="text-muted"><?= htmlspecialchars($_SESSION['email']) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <h6>Account Type</h6>
                                            <p><span class="badge bg-danger">ULTRA ADMIN</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card power-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-bookmark-star"></i> My Registered Subjects</h5>
                                        <hr>
                                        <?php if ($admin_subjects->num_rows > 0): ?>
                                            <div class="d-flex flex-wrap">
                                                <?php while ($subject = $admin_subjects->fetch_assoc()): ?>
                                                    <div class="subject-chip">
                                                        <?= htmlspecialchars($subject['subject_code']) ?>
                                                        <small class="text-muted d-block"><?= htmlspecialchars($subject['day']) ?> <?= htmlspecialchars($subject['time_slot']) ?></small>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">You haven't registered for any subjects yet.</p>
                                            <a href="add_subjects.php" class="btn btn-sm btn-primary">
                                                <i class="bi bi-plus-circle"></i> Register Subjects
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALS FOR POWER ACTIONS -->

    <!-- CREATE SUBJECT MODAL -->
    <div class="modal fade" id="createSubjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create New Subject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Subject Code</label>
                            <input type="text" class="form-control bg-secondary border-dark text-white" name="code" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course Name</label>
                            <input type="text" class="form-control bg-secondary border-dark text-white" name="name" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Day</label>
                                <select class="form-select bg-secondary border-dark text-white" name="day" required>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Time Slot</label>
                                <input type="time" class="form-control bg-secondary border-dark text-white" name="time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Slots</label>
                            <input type="number" class="form-control bg-secondary border-dark text-white" name="slots" min="1" value="30" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_subject" class="btn btn-primary">Create Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT SUBJECT MODAL -->
    <div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Subject</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="subject_id" id="edit_subject_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Subject Code</label>
                            <input type="text" class="form-control bg-secondary border-dark text-white" id="edit_subject_code" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course Name</label>
                            <input type="text" class="form-control bg-secondary border-dark text-white" id="edit_course_name" readonly>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Day</label>
                                <select class="form-select bg-secondary border-dark text-white" name="day" id="edit_day" required>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Time Slot</label>
                                <input type="time" class="form-control bg-secondary border-dark text-white" name="time" id="edit_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Slots</label>
                            <input type="number" class="form-control bg-secondary border-dark text-white" name="slots" id="edit_slots" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_subject" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MASS EMAIL MODAL -->
    <div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark">
                    <h5 class="modal-title"><i class="bi bi-megaphone-fill"></i> Send Announcement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body bg-secondary">
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="email_subject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="email_message" rows="5" required></textarea>
                        </div>
                        <div class="alert alert-info">
                            This will be sent to all registered users.
                        </div>
                    </div>
                    <div class="modal-footer bg-dark">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_announcement" class="btn btn-primary">Send Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPTS FOR ULTRA POWER -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // Load edit form data
        function loadEditForm(id, code, name, day, time, slots) {
            document.getElementById('edit_subject_id').value = id;
            document.getElementById('edit_subject_code').value = code;
            document.getElementById('edit_course_name').value = name;
            document.getElementById('edit_day').value = day;
            document.getElementById('edit_time').value = time;
            document.getElementById('edit_slots').value = slots;
        }

        // User nuke confirmation
        function confirmNuke(userId, username) {
            if (confirm(`⚠️ Nuke user "${username}"? This will permanently delete them and all their subject registrations!`)) {
                document.getElementById(`nuke-form-${userId}`).submit();
            }
        }

        // Show success/error messages
        <?php if (isset($nuke_success)): ?>
            alert('User successfully nuked from orbit!');
        <?php elseif (isset($nuke_error)): ?>
            alert('<?= addslashes($nuke_error) ?>');
        <?php elseif (isset($email_success)): ?>
            alert('<?= addslashes($email_success) ?>');
        <?php endif; ?>
    </script>
</body>
</html>