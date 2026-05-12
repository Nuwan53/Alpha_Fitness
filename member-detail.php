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

$memberId = intval($_GET['id'] ?? 0);
$activeTab = $_GET['tab'] ?? 'overview';

if ($memberId <= 0) {
    header('Location: members.php');
    exit();
}

// Get member details
$member = getMemberById($conn, $memberId);

if (!$member) {
    header('Location: members.php');
    exit();
}

// Get member plans
$memberPlans = getMemberPlans($conn, $memberId);

// Get payment history
$paymentsQuery = "SELECT p.*, mp.start_date, mp.end_date, pl.name as plan_name
                  FROM payments p
                  JOIN member_plans mp ON p.member_plan_id = mp.id
                  JOIN membership_plans pl ON mp.plan_id = pl.id
                  WHERE p.user_id = ?
                  ORDER BY p.paid_at DESC
                  LIMIT 50";
if ($stmt = mysqli_prepare($conn, $paymentsQuery)) {
    mysqli_stmt_bind_param($stmt, 'i', $memberId);
    mysqli_stmt_execute($stmt);
    $paymentsResult = mysqli_stmt_get_result($stmt);
    $payments = [];
    while ($row = mysqli_fetch_assoc($paymentsResult)) {
        $payments[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get attendance history
$attendanceHistory = getMemberAttendanceHistory($conn, $memberId, 30);

// Get class bookings
$bookingsQuery = "SELECT cb.id, c.title, c.class_date, c.start_time, c.end_time, u.name as trainer_name, cb.booked_at, cb.status
                  FROM class_bookings cb
                  JOIN classes c ON cb.class_id = c.id
                  LEFT JOIN trainers t ON c.trainer_id = t.id
                  LEFT JOIN users u ON t.user_id = u.id
                  WHERE cb.user_id = ?
                  ORDER BY c.class_date DESC
                  LIMIT 20";
if ($stmt = mysqli_prepare($conn, $bookingsQuery)) {
    mysqli_stmt_bind_param($stmt, 'i', $memberId);
    mysqli_stmt_execute($stmt);
    $bookingsResult = mysqli_stmt_get_result($stmt);
    $bookings = [];
    while ($row = mysqli_fetch_assoc($bookingsResult)) {
        $bookings[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Calculate stats
$totalAttendance = count($attendanceHistory);
$totalPaid = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'paid') {
        $totalPaid += $p['amount'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($member['name']); ?> - Member Profile</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .profile-header {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .profile-top {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .profile-top {
                flex-direction: column;
                text-align: center;
            }
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            font-weight: bold;
        }

        .profile-info h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .profile-info p {
            margin: 5px 0;
            color: var(--text-light);
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 20px;
            border: 2px solid var(--border);
            background: white;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .tab-btn:hover {
            border-color: var(--primary);
        }

        .tab-content {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
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

        .empty-message {
            text-align: center;
            color: var(--text-light);
            padding: 40px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        .status-badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge-inactive {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .status-badge-suspended {
            background: #fef3c7;
            color: #92400e;
        }

        .amount-display {
            font-weight: 600;
            color: var(--primary);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: var(--light);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #ddd;
        }
    </style>
</head>
<body>
    <header>
        <h1>Alpha Fitness - Member Profile</h1>
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
            <a href="dashboard.php">Dashboard</a> > <a href="members.php">Members</a> > <?php echo htmlspecialchars($member['name']); ?>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-top">
                <div class="profile-avatar"><?php echo getInitials($member['name']); ?></div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($member['name']); ?></h2>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-badge-<?php echo $member['status']; ?>"><?php echo ucfirst($member['status']); ?></span></p>
                    <p><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($member['created_at'])); ?></p>
                </div>
            </div>

            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($memberPlans); ?></div>
                    <div class="stat-label">Active Memberships</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">$<?php echo number_format($totalPaid, 2); ?></div>
                    <div class="stat-label">Total Paid</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $totalAttendance; ?></div>
                    <div class="stat-label">Check-Ins (30 days)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($bookings); ?></div>
                    <div class="stat-label">Class Bookings</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn <?php echo ($activeTab === 'overview') ? 'active' : ''; ?>" onclick="switchTab('overview')">
                Overview
            </button>
            <button class="tab-btn <?php echo ($activeTab === 'memberships') ? 'active' : ''; ?>" onclick="switchTab('memberships')">
                Memberships (<?php echo count($memberPlans); ?>)
            </button>
            <button class="tab-btn <?php echo ($activeTab === 'payments') ? 'active' : ''; ?>" onclick="switchTab('payments')">
                Payments (<?php echo count($payments); ?>)
            </button>
            <button class="tab-btn <?php echo ($activeTab === 'attendance') ? 'active' : ''; ?>" onclick="switchTab('attendance')">
                Attendance (<?php echo $totalAttendance; ?>)
            </button>
            <button class="tab-btn <?php echo ($activeTab === 'bookings') ? 'active' : ''; ?>" onclick="switchTab('bookings')">
                Classes (<?php echo count($bookings); ?>)
            </button>
        </div>

        <!-- Overview Tab -->
        <div class="tab-content <?php echo ($activeTab === 'overview') ? 'active' : ''; ?>" id="overview">
            <h3>Member Overview</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <div>
                    <h4>Current Plan</h4>
                    <?php if (!empty($member['active_plan_name'])): ?>
                        <p><strong><?php echo htmlspecialchars($member['active_plan_name']); ?></strong></p>
                        <p style="color: var(--text-light);">Expires: <?php echo date('M d, Y', strtotime($member['plan_end_date'])); ?></p>
                    <?php else: ?>
                        <p style="color: var(--text-light);">No active membership</p>
                    <?php endif; ?>
                </div>
                <div>
                    <h4>Contact Info</h4>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['phone'] ?? 'Not provided'); ?></p>
                </div>
                <div>
                    <h4>Account Status</h4>
                    <p><span class="status-badge status-badge-<?php echo $member['status']; ?>"><?php echo ucfirst($member['status']); ?></span></p>
                    <p style="color: var(--text-light); font-size: 13px; margin-top: 10px;">Joined <?php echo date('M d, Y', strtotime($member['created_at'])); ?></p>
                </div>
            </div>
            <div style="margin-top: 30px;">
                <a href="members.php" class="btn btn-secondary">← Back to Members</a>
            </div>
        </div>

        <!-- Memberships Tab -->
        <div class="tab-content <?php echo ($activeTab === 'memberships') ? 'active' : ''; ?>" id="memberships">
            <h3>Membership History</h3>
            <?php if (count($memberPlans) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($memberPlans as $plan): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($plan['name']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($plan['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($plan['end_date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo ($plan['status'] === 'active') ? 'success' : (($plan['status'] === 'expired') ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($plan['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-message">No membership history found.</p>
            <?php endif; ?>
        </div>

        <!-- Payments Tab -->
        <div class="tab-content <?php echo ($activeTab === 'payments') ? 'active' : ''; ?>" id="payments">
            <h3>Payment History</h3>
            <?php if (count($payments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Plan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="amount-display">$<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo ucfirst($payment['method']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo ($payment['status'] === 'paid') ? 'success' : (($payment['status'] === 'pending') ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($payment['paid_at'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['plan_name'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-message">No payments found.</p>
            <?php endif; ?>
        </div>

        <!-- Attendance Tab -->
        <div class="tab-content <?php echo ($activeTab === 'attendance') ? 'active' : ''; ?>" id="attendance">
            <h3>Attendance History (Last 30 Days)</h3>
            <?php if (count($attendanceHistory) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceHistory as $att): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($att['checked_in'])); ?></td>
                                <td><?php echo $att['check_in_time']; ?></td>
                                <td><?php echo $att['check_out_time'] ?? '--'; ?></td>
                                <td><?php echo $att['duration'] ?? '--'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-message">No attendance records found in the last 30 days.</p>
            <?php endif; ?>
        </div>

        <!-- Bookings Tab -->
        <div class="tab-content <?php echo ($activeTab === 'bookings') ? 'active' : ''; ?>" id="bookings">
            <h3>Class Bookings</h3>
            <?php if (count($bookings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Class Title</th>
                            <th>Trainer</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($booking['trainer_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['class_date'])); ?></td>
                                <td><?php echo substr($booking['start_time'], 0, 5); ?> - <?php echo substr($booking['end_time'], 0, 5); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo ($booking['status'] === 'booked') ? 'info' : 'danger'; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-message">No class bookings found.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Update URL
            window.history.pushState({}, '', '?tab=' + tabName);
        }
    </script>
</body>
</html>
