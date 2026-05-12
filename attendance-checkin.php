<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'includes/helpers.php';
require_once 'includes/crud-helpers.php';

// Require admin role
requireRole('admin');

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$userInitials = getInitials($userName);

$error = '';
$success = '';

// Handle check-in/check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $memberId = intval($_POST['member_id'] ?? 0);
    
    if ($action === 'checkin' && $memberId > 0) {
        $result = checkInMember($conn, $memberId);
        if ($result === 'already_checked_in') {
            $error = 'Member is already checked in today.';
        } elseif ($result) {
            $success = 'Member checked in successfully!';
        } else {
            $error = 'Error checking in member.';
        }
    } elseif ($action === 'checkout' && $memberId > 0) {
        if (checkOutMember($conn, $memberId)) {
            $success = 'Member checked out successfully!';
        } else {
            $error = 'Member is not currently checked in.';
        }
    }
}

// Get today's attendance
$todayAttendance = getTodayAttendance($conn);

// Get all active members for quick search
$membersQuery = "SELECT id, name, email, status FROM users WHERE role = 'member' AND status = 'active' ORDER BY name ASC";
$membersResult = mysqli_query($conn, $membersQuery);
$members = [];
while ($row = mysqli_fetch_assoc($membersResult)) {
    $members[] = $row;
}

// Calculate today's stats
$checkedInCount = 0;
$checkedOutCount = 0;
foreach ($todayAttendance as $att) {
    if (!empty($att['checked_out'])) {
        $checkedOutCount++;
    } else {
        $checkedInCount++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Check-In - Gym Management</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 900px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--light);
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: #ddd;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            border-bottom: 1px solid var(--border);
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge-checkedin {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge-checkedout {
            background: #d1fae5;
            color: #065f46;
        }
        
        .breadcrumb {
            margin-bottom: 20px;
            color: var(--text-light);
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .member-suggestion {
            background: #f8f9fa;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .member-suggestion:hover {
            background: var(--primary);
            color: white;
        }

        .suggestions-list {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 5px;
        }

        .title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        h2 {
            margin: 0;
        }
    </style>
</head>
<body>
    <header>
        <h1>Alpha Fitness - Attendance Check-In</h1>
        <div class="header-actions">
            <div class="user-info">
                <div class="user-avatar"><?php echo $userInitials; ?></div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="user-role">Admin</div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="container">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> > Attendance Check-In
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-number"><?php echo $checkedInCount; ?></div>
                <div class="stat-label">Currently Checked In</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $checkedOutCount; ?></div>
                <div class="stat-label">Checked Out Today</div>
            </div>
        </div>

        <!-- Check-in Form -->
        <div class="content-wrapper">
            <div class="card">
                <h3 style="margin-top: 0;">Quick Check-In</h3>
                <form method="POST">
                    <input type="hidden" name="action" id="action" value="checkin">
                    
                    <div class="form-group">
                        <label for="search_member">Search Member</label>
                        <input type="text" id="search_member" placeholder="Type member name or email...">
                    </div>

                    <div class="suggestions-list" id="suggestionsList" style="display: none;"></div>

                    <div class="form-group">
                        <label for="member_id">Select Member *</label>
                        <select id="member_id" name="member_id" required style="display: none;">
                            <option value="">-- Select a member --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="memberDisplay" style="padding: 10px; background: #f8f9fa; border-radius: 4px; text-align: center; color: var(--text-light);">
                            No member selected
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="check_action" value="checkin" class="btn btn-primary">✓ Check In</button>
                        <button type="submit" name="check_action" value="checkout" class="btn btn-secondary" onclick="document.getElementById('action').value='checkout'">✗ Check Out</button>
                    </div>
                </form>
            </div>

            <!-- Today's Log -->
            <div class="card">
                <div class="title-row">
                    <h3>Today's Check-In Log (<?php echo date('M d, Y'); ?>)</h3>
                </div>

                <?php if (count($todayAttendance) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayAttendance as $att): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($att['name']); ?></td>
                                    <td><?php echo $att['check_in_time']; ?></td>
                                    <td>
                                        <?php if ($att['checked_out']): ?>
                                            <?php echo $att['check_out_time']; ?>
                                        <?php else: ?>
                                            <span class="status-badge status-badge-checkedin">CHECKED IN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($att['duration']): ?>
                                            <?php echo $att['duration']; ?>
                                        <?php else: ?>
                                            --
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-light); padding: 40px;">No attendance recorded yet today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const membersData = <?php echo json_encode($members); ?>;

        document.getElementById('search_member').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const suggestionsList = document.getElementById('suggestionsList');
            
            if (query.length < 1) {
                suggestionsList.style.display = 'none';
                return;
            }
            
            const filtered = membersData.filter(m => 
                m.name.toLowerCase().includes(query) || 
                m.email.toLowerCase().includes(query)
            );
            
            if (filtered.length > 0) {
                suggestionsList.innerHTML = filtered.map(m => 
                    `<div class="member-suggestion" onclick="selectMember(${m.id}, '${m.name}')">
                        ${m.name} (${m.email})
                    </div>`
                ).join('');
                suggestionsList.style.display = 'block';
            } else {
                suggestionsList.style.display = 'none';
            }
        });

        function selectMember(memberId, memberName) {
            document.getElementById('member_id').value = memberId;
            document.getElementById('memberDisplay').textContent = memberName;
            document.getElementById('search_member').value = '';
            document.getElementById('suggestionsList').style.display = 'none';
        }
    </script>
</body>
</html>
