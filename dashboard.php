<?php
require_once 'db.php';
require_once 'auth.php';
require_once 'includes/dashboard-helpers.php';
require_once 'includes/helpers.php';

// Check if user is logged in
requireLogin();

// Get current user info
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];
$userRole = $_SESSION['role'];
$userInitials = getInitials($userName);

// Get pagination settings
$currentPage = getCurrentPage();
$itemsPerPage = 10;
$offset = getPaginationOffset($currentPage, $itemsPerPage);

// Get all metrics
$totalRevenue = getTotalRevenue($conn, 'month');
$ytdRevenue = getYearToDateRevenue($conn);
$activeMembers = getActiveMembersCount($conn);
$newMembersThisMonth = getNewMembersThisMonth($conn);
$totalClasses = getTotalClassesCount($conn);
$totalAttendance = getTotalAttendanceThisMonth($conn);
$membershipStats = getMembershipStats($conn);
$paymentMethodBreakdown = getPaymentMethodBreakdown($conn);
$topPlans = getTopMembershipPlans($conn, 5);
$upcomingRenewals = getUpcomingRenewals($conn, 30);
$memberList = getMemberList($conn, $itemsPerPage, $offset);
$totalMembers = getTotalMembersCount($conn);
$recentPayments = getRecentPayments($conn, 15);
$attendanceByDay = getAttendanceByDay($conn, 30);
$peakHours = getPeakAttendanceHours($conn);
$memberAttendance = getMemberAttendanceFrequency($conn, 10);
$upcomingClasses = getUpcomingClasses($conn, 10);
$trainerStats = getTrainerStats($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Alpha Fitness</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- HEADER -->
    <header>
        <h1>💪 Alpha Fitness Dashboard</h1>
        <div class="header-actions">
            <div class="user-info">
                <div class="user-avatar"><?php echo $userInitials; ?></div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="user-role"><?php echo ucfirst($userRole); ?></div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <!-- MAIN CONTAINER -->
    <div class="container">
        
        <!-- ===============================================
             SECTION 1: KEY PERFORMANCE INDICATORS (KPIs)
             =============================================== -->
        <div class="section-header">
            <h2>📊 Key Performance Indicators</h2>
        </div>

        <div class="kpi-grid">
            <!-- Revenue Card -->
            <div class="kpi-card revenue">
                <div class="kpi-label">Monthly Revenue</div>
                <div class="kpi-value"><?php echo formatCurrency($totalRevenue); ?></div>
                <div class="kpi-meta">
                    <span class="trend-up">↑</span> YTD: <?php echo formatCurrency($ytdRevenue); ?>
                </div>
            </div>

            <!-- Active Members Card -->
            <div class="kpi-card members">
                <div class="kpi-label">Active Members</div>
                <div class="kpi-value"><?php echo $activeMembers; ?></div>
                <div class="kpi-meta">
                    <span class="trend-up">+<?php echo $newMembersThisMonth; ?></span> new this month
                </div>
            </div>

            <!-- Classes Card -->
            <div class="kpi-card classes">
                <div class="kpi-label">Total Classes</div>
                <div class="kpi-value"><?php echo $totalClasses; ?></div>
                <div class="kpi-meta">
                    <?php $upcomingCount = count($upcomingClasses); ?>
                    <span><?php echo $upcomingCount; ?></span> upcoming this week
                </div>
            </div>

            <!-- Attendance Card -->
            <div class="kpi-card attendance">
                <div class="kpi-label">Monthly Attendance</div>
                <div class="kpi-value"><?php echo $totalAttendance; ?></div>
                <div class="kpi-meta">
                    Check-ins this month
                </div>
            </div>
        </div>

        <!-- ===============================================
             SECTION 2: REVENUE ANALYTICS
             =============================================== -->
        <div class="section-header">
            <h2>💰 Revenue Analytics</h2>
        </div>

        <div class="grid-2">
            <!-- Payment Method Breakdown -->
            <div class="table-wrapper">
                <div class="table-header">
                    <h3>Payment Method Breakdown</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($paymentMethodBreakdown) > 0): ?>
                            <?php foreach ($paymentMethodBreakdown as $method): ?>
                                <tr>
                                    <td><?php echo ucfirst($method['method']); ?></td>
                                    <td><?php echo $method['count']; ?></td>
                                    <td><?php echo formatCurrency($method['total']); ?></td>
                                    <td><?php echo formatPercentage($method['percentage']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center">No payment data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Membership Plans by Revenue -->
            <div class="table-wrapper">
                <div class="table-header">
                    <h3>Top Membership Plans</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Active Members</th>
                            <th>Revenue</th>
                            <th>Enrollments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($topPlans) > 0): ?>
                            <?php foreach ($topPlans as $plan): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($plan['name']); ?></td>
                                    <td><?php echo $plan['active_members'] ?? 0; ?></td>
                                    <td><?php echo formatCurrency($plan['total_revenue'] ?? 0); ?></td>
                                    <td><?php echo $plan['total_enrollments'] ?? 0; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center">No plan data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upcoming Renewals -->
        <div class="table-wrapper" style="margin-top: 30px;">
            <div class="table-header">
                <h3>Upcoming Renewals (Next 30 Days)</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Email</th>
                        <th>Current Plan</th>
                        <th>Expiration Date</th>
                        <th>Days Remaining</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($upcomingRenewals) > 0): ?>
                        <?php foreach ($upcomingRenewals as $renewal): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($renewal['name']); ?></td>
                                <td><?php echo htmlspecialchars($renewal['email']); ?></td>
                                <td><?php echo htmlspecialchars($renewal['plan_name']); ?></td>
                                <td><?php echo formatDate($renewal['end_date']); ?></td>
                                <td>
                                    <span class="badge badge-warning">
                                        <?php echo $renewal['days_until_renewal']; ?> days
                                    </span>
                                </td>
                                <td><?php echo formatCurrency($renewal['price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No upcoming renewals in next 30 days</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===============================================
             SECTION 3: MEMBERSHIP ANALYTICS
             =============================================== -->
        <div class="section-header">
            <h2>👥 Membership Analytics</h2>
        </div>

        <div class="grid-3">
            <!-- Membership Status Breakdown -->
            <?php 
                $activeMembershipCount = 0;
                $expiredCount = 0;
                $cancelledCount = 0;
                foreach ($membershipStats as $stat) {
                    if ($stat['status_key'] === 'active') $activeMembershipCount = $stat['count'];
                    elseif ($stat['status_key'] === 'expired') $expiredCount = $stat['count'];
                    elseif ($stat['status_key'] === 'cancelled') $cancelledCount = $stat['count'];
                }
            ?>
            <div class="card">
                <h4>✅ Active Memberships</h4>
                <p class="text-success" style="font-size: 28px; font-weight: bold;"><?php echo $activeMembershipCount; ?></p>
                <p class="text-muted">Currently active plans</p>
            </div>

            <div class="card">
                <h4>⏰ Expired Memberships</h4>
                <p class="text-warning" style="font-size: 28px; font-weight: bold;"><?php echo $expiredCount; ?></p>
                <p class="text-muted">Waiting for renewal</p>
            </div>

            <div class="card">
                <h4>❌ Cancelled Memberships</h4>
                <p class="text-danger" style="font-size: 28px; font-weight: bold;"><?php echo $cancelledCount; ?></p>
                <p class="text-muted">Inactive plans</p>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="table-wrapper" style="margin-top: 30px;">
            <div class="table-header">
                <h3>Recent Transactions</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Member</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recentPayments) > 0): ?>
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><?php echo formatDate($payment['paid_at']); ?></td>
                                <td><?php echo htmlspecialchars($payment['name']); ?></td>
                                <td><?php echo htmlspecialchars($payment['email']); ?></td>
                                <td><?php echo htmlspecialchars($payment['plan_name']); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo ucfirst($payment['method']); ?></td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($payment['status']); ?>">
                                        <?php echo getStatusLabel($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">No transactions available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===============================================
             SECTION 4: ATTENDANCE TRACKING
             =============================================== -->
        <div class="section-header">
            <h2>📍 Attendance Tracking</h2>
        </div>

        <!-- Peak Attendance Hours -->
        <div class="grid-2">
            <div class="table-wrapper">
                <div class="table-header">
                    <h3>Peak Attendance Hours</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Hour</th>
                            <th>Check-ins</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            $totalCheckIns = array_sum(array_column($peakHours, 'count'));
                            if (count($peakHours) > 0):
                                foreach ($peakHours as $hour):
                                    $percentage = $totalCheckIns > 0 ? round(($hour['count'] / $totalCheckIns) * 100) : 0;
                        ?>
                            <tr>
                                <td><?php echo str_pad($hour['hour'], 2, '0', STR_PAD_LEFT); ?>:00 - <?php echo str_pad($hour['hour'] + 1, 2, '0', STR_PAD_LEFT); ?>:00</td>
                                <td><?php echo $hour['count']; ?></td>
                                <td>
                                    <div style="background: #e9ecef; border-radius: 4px; padding: 4px 8px;">
                                        <span style="color: var(--primary); font-weight: 600;"><?php echo $percentage; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                                endforeach;
                            else:
                        ?>
                            <tr><td colspan="3" class="text-center">No attendance data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Member Attendance Frequency -->
            <div class="table-wrapper">
                <div class="table-header">
                    <h3>Top Members by Visits</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Visits</th>
                            <th>Last Visit</th>
                            <th>Avg/Day</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($memberAttendance) > 0): ?>
                            <?php foreach ($memberAttendance as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><?php echo $member['visits']; ?></td>
                                    <td><?php echo formatDate($member['last_visit']); ?></td>
                                    <td><?php echo number_format($member['avg_visits_per_day'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center">No attendance data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===============================================
             SECTION 5: CLASSES & TRAINERS
             =============================================== -->
        <div class="section-header">
            <h2>🏃 Classes & Trainers</h2>
        </div>

        <!-- Upcoming Classes -->
        <div class="table-wrapper" style="margin-bottom: 30px;">
            <div class="table-header">
                <h3>Upcoming Classes</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Class Title</th>
                        <th>Trainer</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Capacity</th>
                        <th>Booked</th>
                        <th>Occupancy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($upcomingClasses) > 0): ?>
                        <?php foreach ($upcomingClasses as $class): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($class['title']); ?></td>
                                <td><?php echo htmlspecialchars($class['trainer_name']); ?></td>
                                <td><?php echo formatDate($class['class_date']); ?></td>
                                <td><?php echo substr($class['start_time'], 0, 5); ?> - <?php echo substr($class['end_time'], 0, 5); ?></td>
                                <td><?php echo $class['capacity']; ?></td>
                                <td><?php echo $class['booked_count']; ?></td>
                                <td>
                                    <?php 
                                        $occupancy = getOccupancyPercentage($class['booked_count'], $class['capacity']);
                                        $occupancyClass = $occupancy >= 80 ? 'text-danger' : ($occupancy >= 50 ? 'text-warning' : 'text-success');
                                    ?>
                                    <span class="<?php echo $occupancyClass; ?>"><?php echo $occupancy; ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">No upcoming classes</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Trainer Performance -->
        <div class="table-wrapper">
            <div class="table-header">
                <h3>Trainer Performance</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Trainer Name</th>
                        <th>Specialty</th>
                        <th>Total Classes</th>
                        <th>Total Bookings</th>
                        <th>Avg Booking/Class</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($trainerStats) > 0): ?>
                        <?php foreach ($trainerStats as $trainer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trainer['name']); ?></td>
                                <td><?php echo htmlspecialchars($trainer['specialty'] ?? 'N/A'); ?></td>
                                <td><?php echo $trainer['total_classes']; ?></td>
                                <td><?php echo $trainer['total_bookings']; ?></td>
                                <td>
                                    <?php 
                                        $avgBooking = $trainer['total_classes'] > 0 ? round($trainer['total_bookings'] / $trainer['total_classes'], 2) : 0;
                                        echo $avgBooking;
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No trainer data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===============================================
             SECTION 6: MEMBER MANAGEMENT
             =============================================== -->
        <div class="section-header">
            <h2>👤 Member List</h2>
        </div>

        <div class="table-wrapper">
            <div class="table-header">
                <h3>All Members (<?php echo $totalMembers; ?> total)</h3>
                <div class="table-header-actions">
                    <span style="color: white;">Page <?php echo $currentPage; ?> of <?php echo getTotalPages($totalMembers, $itemsPerPage); ?></span>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Current Plan</th>
                        <th>Membership Status</th>
                        <th>Days Remaining</th>
                        <th>Joined Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($memberList) > 0): ?>
                        <?php foreach ($memberList as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($member['plan_name'] ?? 'No Plan'); ?></td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($member['membership_status'] ?? 'inactive'); ?>">
                                        <?php echo getStatusLabel($member['membership_status'] ?? 'inactive'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        if ($member['days_remaining'] !== null) {
                                            if ($member['days_remaining'] > 0) {
                                                echo '<span class="text-success">' . $member['days_remaining'] . '</span>';
                                            } else {
                                                echo '<span class="text-danger">Expired</span>';
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td><?php echo formatDate($member['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">No members available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if (getTotalPages($totalMembers, $itemsPerPage) > 1): ?>
                <div class="pagination" style="padding: 20px;">
                    <?php for ($i = 1; $i <= getTotalPages($totalMembers, $itemsPerPage); $i++): ?>
                        <?php if ($i == $currentPage): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="dashboard.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
    <!-- END CONTAINER -->

</body>
</html>
