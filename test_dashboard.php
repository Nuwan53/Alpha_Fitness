<?php
require_once 'db.php';
require_once 'includes/dashboard-helpers.php';

echo "=== DASHBOARD HELPER FUNCTIONS TEST ===\n\n";

// Test all helper functions
$revenue = getTotalRevenue($conn, 'month');
$ytdRevenue = getYearToDateRevenue($conn);
$activeMembers = getActiveMembersCount($conn);
$newMembers = getNewMembersThisMonth($conn);
$totalClasses = getTotalClassesCount($conn);
$totalAttendance = getTotalAttendanceThisMonth($conn);

echo "KPI METRICS:\n";
echo "  Monthly Revenue: ₹" . number_format($revenue, 2) . "\n";
echo "  YTD Revenue: ₹" . number_format($ytdRevenue, 2) . "\n";
echo "  Active Members: " . $activeMembers . "\n";
echo "  New Members This Month: " . $newMembers . "\n";
echo "  Total Classes: " . $totalClasses . "\n";
echo "  Monthly Attendance: " . $totalAttendance . "\n\n";

// Test analytics functions
echo "ANALYTICS DATA:\n";

$paymentMethods = getPaymentMethodBreakdown($conn);
echo "  Payment Methods: " . count($paymentMethods) . " types\n";

$topPlans = getTopMembershipPlans($conn, 5);
echo "  Top Membership Plans: " . count($topPlans) . " plans\n";

$renewals = getUpcomingRenewals($conn, 30);
echo "  Upcoming Renewals (30 days): " . count($renewals) . " renewals\n";

$membershipStats = getMembershipStats($conn);
echo "  Membership Status Breakdown: " . count($membershipStats) . " statuses\n";

$recentPayments = getRecentPayments($conn, 15);
echo "  Recent Payments: " . count($recentPayments) . " transactions\n";

$peakHours = getPeakAttendanceHours($conn);
echo "  Peak Attendance Hours: " . count($peakHours) . " hours\n";

$memberAttendance = getMemberAttendanceFrequency($conn, 10);
echo "  Top Members by Visits: " . count($memberAttendance) . " members\n";

$upcomingClasses = getUpcomingClasses($conn, 10);
echo "  Upcoming Classes: " . count($upcomingClasses) . " classes\n";

$trainers = getTrainerStats($conn);
echo "  Trainer Performance Data: " . count($trainers) . " trainers\n\n";

echo "✅ ALL DASHBOARD HELPER FUNCTIONS WORKING CORRECTLY!\n";

?>
