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

// Get subject_id from URL
if (!isset($_GET['subject_id']) || !is_numeric($_GET['subject_id'])) {
    header("Location: profile.php");
    exit();
}

$subject_id = intval($_GET['subject_id']);

// Verify user is enrolled in this subject
$verify_stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_subjects WHERE user_id = ? AND subject_id = ?");
$verify_stmt->bind_param("ii", $user_id, $subject_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();
$verify_data = $verify_result->fetch_assoc();

if ($verify_data['count'] == 0) {
    $_SESSION['message'] = "You are not enrolled in this subject!";
    header("Location: profile.php");
    exit();
}

// Get subject details
$subject_stmt = $conn->prepare("SELECT subject_code, course_name FROM subjects WHERE id = ?");
$subject_stmt->bind_param("i", $subject_id);
$subject_stmt->execute();
$subject_result = $subject_stmt->get_result();
$subject_data = $subject_result->fetch_assoc();

// Handle actions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_group':
                $group_name = trim($_POST['group_name']);
                if (!empty($group_name)) {
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM groups WHERE subject_id = ? AND name = ?");
                    $check_stmt->bind_param("is", $subject_id, $group_name);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $check_data = $check_result->fetch_assoc();
                    
                    if ($check_data['count'] == 0) {
                        $create_stmt = $conn->prepare("INSERT INTO groups (name, subject_id, creator_id) VALUES (?, ?, ?)");
                        $create_stmt->bind_param("sii", $group_name, $subject_id, $user_id);
                        if ($create_stmt->execute()) {
                            $group_id = $conn->insert_id;
                            // Auto-join creator to group
                            $join_stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                            $join_stmt->bind_param("ii", $group_id, $user_id);
                            $join_stmt->execute();
                            $_SESSION['message'] = "Group created successfully!";
                        }
                    } else {
                        $_SESSION['message'] = "Group name already exists!";
                    }
                }
                break;
                
            case 'join_group':
                $group_id = intval($_POST['group_id']);
                $check_member_stmt = $conn->prepare("SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND user_id = ?");
                $check_member_stmt->bind_param("ii", $group_id, $user_id);
                $check_member_stmt->execute();
                $check_member_result = $check_member_stmt->get_result();
                $check_member_data = $check_member_result->fetch_assoc();
                
                if ($check_member_data['count'] == 0) {
                    $join_stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                    $join_stmt->bind_param("ii", $group_id, $user_id);
                    if ($join_stmt->execute()) {
                        $_SESSION['message'] = "Successfully joined the group!";
                    }
                } else {
                    $_SESSION['message'] = "You are already a member!";
                }
                break;
                
            case 'leave_group':
                $group_id = intval($_POST['group_id']);
                $leave_stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
                $leave_stmt->bind_param("ii", $group_id, $user_id);
                if ($leave_stmt->execute()) {
                    $_SESSION['message'] = "Left the group successfully!";
                }
                break;
                
            case 'schedule_meeting':
                $group_id = intval($_POST['group_id']);
                $meeting_title = trim($_POST['meeting_title']);
                $meeting_date = $_POST['meeting_date'];
                $meeting_time = $_POST['meeting_time'];
                
                if (!empty($meeting_title) && !empty($meeting_date) && !empty($meeting_time)) {
                    // Check if user is member of the group
                    $member_check = $conn->prepare("SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND user_id = ?");
                    $member_check->bind_param("ii", $group_id, $user_id);
                    $member_check->execute();
                    $member_result = $member_check->get_result();
                    $member_data = $member_result->fetch_assoc();
                    
                    if ($member_data['count'] > 0) {
                        $schedule_stmt = $conn->prepare("INSERT INTO meetings (group_id, title, meeting_date, meeting_time, created_by) VALUES (?, ?, ?, ?, ?)");
                        $schedule_stmt->bind_param("isssi", $group_id, $meeting_title, $meeting_date, $meeting_time, $user_id);
                        if ($schedule_stmt->execute()) {
                            $_SESSION['message'] = "Meeting scheduled successfully!";
                        }
                    }
                }
                break;
        }
    }
    
    header("Location: subject_groups.php?subject_id=" . $subject_id);
    exit();
}

// Get groups with member info and upcoming meetings
$groups_query = "
    SELECT 
        g.id,
        g.name,
        g.creator_id,
        u.username as creator_name,
        COUNT(DISTINCT gm.user_id) as member_count,
        CASE WHEN gm_user.user_id IS NOT NULL THEN 1 ELSE 0 END as is_member,
        m.title as next_meeting_title,
        m.meeting_date as next_meeting_date,
        m.meeting_time as next_meeting_time
    FROM groups g
    LEFT JOIN users u ON g.creator_id = u.id
    LEFT JOIN group_members gm ON g.id = gm.group_id
    LEFT JOIN group_members gm_user ON g.id = gm_user.group_id AND gm_user.user_id = ?
    LEFT JOIN meetings m ON g.id = m.group_id AND m.meeting_date >= CURDATE()
    WHERE g.subject_id = ?
    GROUP BY g.id, g.name, g.creator_id, u.username, gm_user.user_id, m.id
    ORDER BY g.name
";

$groups_stmt = $conn->prepare($groups_query);
$groups_stmt->bind_param("ii", $user_id, $subject_id);
$groups_stmt->execute();
$groups_result = $groups_stmt->get_result();

$groups = [];
while ($row = $groups_result->fetch_assoc()) {
    $groups[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Groups - <?php echo htmlspecialchars($subject_data['subject_code']); ?></title>
    <style>
        * { box-sizing: border-box; }
        
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
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
        }
        
        header h1 { margin: 0; color: #00bfff; }
        
        .back-btn {
            color: #00bfff;
            text-decoration: none;
            font-weight: bold;
            padding: 8px 16px;
            border: 2px solid #00bfff;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background-color: #00bfff;
            color: #121212;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .subject-info {
            background-color: #1e1e1e;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 4px solid #00bfff;
        }
        
        .create-group-form {
            background-color: #1e1e1e;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
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
            width: 100%;
            max-width: 300px;
            padding: 10px;
            background: #292929;
            color: #fff;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #00bfff;
        }
        
        .btn {
            padding: 10px 20px;
            background-color: #00bfff;
            color: #121212;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
            transition: all 0.3s;
            margin-right: 10px;
        }
        
        .btn:hover { background-color: #ffffff; }
        .btn-danger { background-color: #ff4444; color: white; }
        .btn-danger:hover { background-color: #ff6666; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-success:hover { background-color: #34ce57; }
        
        .group-card {
            background-color: #292929;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #00bfff;
        }
        
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .group-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #00bfff;
        }
        
        .group-info {
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .meeting-info {
            background-color: #1e1e1e;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 3px solid #28a745;
        }
        
        .group-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .message {
            background: #292929;
            color: #00bfff;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #00bfff;
        }
        
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
            max-width: 500px;
            color: #f0f0f0;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        
        .btn-secondary {
            background-color: #333;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background-color: #555;
        }
        
        .member-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .member-item {
            background-color: #333;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        @media (max-width: 768px) {
            .form-row { flex-direction: column; }
            .group-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .group-actions { width: 100%; }
        }
    </style>
</head>
<body>
    <header>
        <h1>Study Groups</h1>
        <a href="profile.php" class="back-btn">← Back to Dashboard</a>
    </header>

    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <div class="subject-info">
            <h2><?php echo htmlspecialchars($subject_data['subject_code']); ?> - <?php echo htmlspecialchars($subject_data['course_name']); ?></h2>
        </div>

        <div class="create-group-form">
            <h3>Create New Study Group</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_group">
                <div class="form-group">
                    <label for="group_name">Group Name</label>
                    <input type="text" id="group_name" name="group_name" required maxlength="100" placeholder="Enter group name...">
                </div>
                <button type="submit" class="btn">Create Group</button>
            </form>
        </div>

        <div class="groups-container">
            <h3>Available Study Groups (<?php echo count($groups); ?>)</h3>
            
            <?php if (count($groups) > 0): ?>
                <?php foreach ($groups as $group): ?>
                    <div class="group-card">
                        <div class="group-header">
                            <span class="group-name"><?php echo htmlspecialchars($group['name']); ?></span>
                        </div>
                        
                        <div class="group-info">
                            <p><strong>Creator:</strong> <?php echo htmlspecialchars($group['creator_name']); ?></p>
                            <p><strong>Members:</strong> <?php echo $group['member_count']; ?></p>
                            
                            <?php if ($group['next_meeting_title']): ?>
                                <div class="meeting-info">
                                    <strong>Next Meeting:</strong> <?php echo htmlspecialchars($group['next_meeting_title']); ?><br>
                                    <strong>Date & Time:</strong> <?php echo date('M j, Y', strtotime($group['next_meeting_date'])); ?> at <?php echo date('g:i A', strtotime($group['next_meeting_time'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="group-actions">
                            <?php if (!$group['is_member']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="join_group">
                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                    <button type="submit" class="btn">Join Group</button>
                                </form>
                            <?php else: ?>
                                <button class="btn" onclick="viewMembers(<?php echo $group['id']; ?>)">View Members</button>
                                <button class="btn btn-success" onclick="scheduleMeeting(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['name'], ENT_QUOTES); ?>')">Schedule Meeting</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="leave_group">
                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to leave this group?')">Leave Group</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: #aaa; padding: 2rem;">
                    <p>No study groups available yet. Be the first to create one!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Members Modal -->
    <div id="membersModal" class="modal">
        <div class="modal-content">
            <h3>Group Members</h3>
            <div id="membersList"></div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-secondary" onclick="closeMembersModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Schedule Meeting Modal -->
    <div id="meetingModal" class="modal">
        <div class="modal-content">
            <h3>Schedule Group Meeting</h3>
            <form method="POST">
                <input type="hidden" name="action" value="schedule_meeting">
                <input type="hidden" name="group_id" id="meeting_group_id">
                <div class="form-group">
                    <label for="meeting_title">Meeting Title</label>
                    <input type="text" id="meeting_title" name="meeting_title" required maxlength="200" placeholder="e.g., Study Session for Midterm">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="meeting_date">Date</label>
                        <input type="date" id="meeting_date" name="meeting_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="meeting_time">Time</label>
                        <input type="time" id="meeting_time" name="meeting_time" required>
                    </div>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeMeetingModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Schedule Meeting</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewMembers(groupId) {
            fetch('get_group_members.php?group_id=' + groupId)
                .then(response => response.json())
                .then(data => {
                    let html = '<div class="member-list">';
                    data.forEach(member => {
                        html += '<div class="member-item">' + member.username + (member.is_creator ? ' (Creator)' : '') + '</div>';
                    });
                    html += '</div>';
                    document.getElementById('membersList').innerHTML = html;
                    document.getElementById('membersModal').style.display = 'block';
                });
        }

        function closeMembersModal() {
            document.getElementById('membersModal').style.display = 'none';
        }

        function scheduleMeeting(groupId, groupName) {
            document.getElementById('meeting_group_id').value = groupId;
            document.getElementById('meetingModal').style.display = 'block';
        }

        function closeMeetingModal() {
            document.getElementById('meetingModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const membersModal = document.getElementById('membersModal');
            const meetingModal = document.getElementById('meetingModal');
            if (event.target === membersModal) {
                closeMembersModal();
            }
            if (event.target === meetingModal) {
                closeMeetingModal();
            }
        }

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMembersModal();
                closeMeetingModal();
            }
        });
    </script>
</body>
</html>