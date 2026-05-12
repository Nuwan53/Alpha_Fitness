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
$currentPage = getCurrentPage();
$itemsPerPage = 20;
$offset = getPaginationOffset($currentPage, $itemsPerPage);

// Build filters
$filters = [];
if (!empty($_GET['start_date'])) {
    $filters['start_date'] = trim($_GET['start_date']);
}
if (!empty($_GET['end_date'])) {
    $filters['end_date'] = trim($_GET['end_date']);
}
if (!empty($_GET['status'])) {
    $filters['status'] = trim($_GET['status']);
}
if (!empty($_GET['method'])) {
    $filters['method'] = trim($_GET['method']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'record') {
        $userId_payment = intval($_POST['user_id'] ?? 0);
        $member_plan_id = intval($_POST['member_plan_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $method = trim($_POST['method'] ?? 'cash');
        $status = trim($_POST['status'] ?? 'paid');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($userId_payment <= 0 || $member_plan_id <= 0 || $amount <= 0) {
            $error = 'Member, plan, and amount are required.';
        } else {
            if (recordPayment($conn, $userId_payment, $member_plan_id, $amount, $method, $status, $notes)) {
                $success = 'Payment recorded successfully!';
            } else {
                $error = 'Error recording payment.';
            }
        }
    }
}

// Get payments count
$paymentsCount = getPaymentsCount($conn, $filters);
$totalPages = getTotalPages($paymentsCount, $itemsPerPage);

// Get payments list
$payments = getAllPayments($conn, $itemsPerPage, $offset, $filters);

// Get all members for dropdown
$membersQuery = "SELECT id, name, email FROM users WHERE role = 'member' ORDER BY name ASC";
$membersResult = mysqli_query($conn, $membersQuery);
$members = [];
while ($row = mysqli_fetch_assoc($membersResult)) {
    $members[] = $row;
}

// Get active member plans for selected member (via AJAX would be better, but for now we'll show all)
$memberPlansQuery = "SELECT DISTINCT mp.id, mp.user_id, u.name as member_name, pl.name as plan_name, pl.price
                     FROM member_plans mp
                     JOIN users u ON mp.user_id = u.id
                     JOIN membership_plans pl ON mp.plan_id = pl.id
                     WHERE mp.status = 'active'
                     ORDER BY u.name ASC";
$memberPlansResult = mysqli_query($conn, $memberPlansQuery);
$memberPlans = [];
while ($row = mysqli_fetch_assoc($memberPlansResult)) {
    $memberPlans[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Gym Management</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .content-wrapper {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .payments-table {
            flex: 1;
            min-width: 300px;
        }
        
        .form-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            margin-top: 20px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
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
        
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text);
            cursor: pointer;
        }
        
        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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

        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-section input,
        .filter-section select {
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 14px;
        }

        .amount-display {
            font-weight: 600;
            color: var(--primary);
            font-size: 16px;
        }
    </style>
</head>
<body>
    <header>
        <h1>Alpha Fitness - Payment Management</h1>
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
            <a href="dashboard.php">Dashboard</a> > Payments
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 150px;">
                    <label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 5px;">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 5px;">End Date</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 5px;">Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="paid" <?php echo ($filters['status'] === 'paid') ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo ($filters['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo ($filters['status'] === 'failed') ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 5px;">Method</label>
                    <select name="method">
                        <option value="">All Methods</option>
                        <option value="cash" <?php echo ($filters['method'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="card" <?php echo ($filters['method'] === 'card') ? 'selected' : ''; ?>>Card</option>
                        <option value="online" <?php echo ($filters['method'] === 'online') ? 'selected' : ''; ?>>Online</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <a href="payments.php" class="btn btn-secondary" style="text-decoration: none;">Reset</a>
                </div>
            </form>
        </div>

        <div class="content-wrapper">
            <div class="payments-table">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Payment History (<?php echo $paymentsCount; ?>)</h2>
                    <button class="btn btn-primary" onclick="toggleForm()">+ Record Payment</button>
                </div>

                <?php if (count($payments) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Member Name</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($payment['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($payment['plan_name'] ?? 'N/A'); ?></td>
                                    <td class="amount-display">$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo ucfirst($payment['method']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo ($payment['status'] === 'paid') ? 'success' : (($payment['status'] === 'pending') ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($payment['paid_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="payments.php?page=1<?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>">First</a>
                                <a href="payments.php?page=<?php echo $currentPage - 1; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i === $currentPage): ?>
                                    <span class="active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="payments.php?page=<?php echo $i; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="payments.php?page=<?php echo $currentPage + 1; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>">Next</a>
                                <a href="payments.php?page=<?php echo $totalPages; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>">Last</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-light); padding: 40px;">No payments recorded yet. Click "Record Payment" to add one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Record Payment Form -->
        <div class="form-card" id="formCard" style="display: none;">
            <h3>Record New Payment</h3>
            <form method="POST" id="paymentForm">
                <input type="hidden" name="action" value="record">

                <div class="form-row">
                    <div class="form-group">
                        <label for="user_id">Member *</label>
                        <select id="user_id" name="user_id" required onchange="updateMemberPlans()">
                            <option value="">-- Select a member --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="member_plan_id">Membership Plan *</label>
                        <select id="member_plan_id" name="member_plan_id" required>
                            <option value="">-- Select member first --</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount ($) *</label>
                        <input type="number" id="amount" name="amount" placeholder="0.00" min="0" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="method">Payment Method *</label>
                        <select id="method" name="method" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Any additional notes..."></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const memberPlansData = <?php echo json_encode($memberPlans); ?>;

        function toggleForm() {
            const form = document.getElementById('formCard');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                document.getElementById('paymentForm').reset();
            }
        }

        function updateMemberPlans() {
            const userId = document.getElementById('user_id').value;
            const planSelect = document.getElementById('member_plan_id');
            
            planSelect.innerHTML = '<option value="">-- Select a plan --</option>';
            
            if (userId) {
                const memberPlans = memberPlansData.filter(mp => mp.user_id == userId);
                memberPlans.forEach(plan => {
                    const option = document.createElement('option');
                    option.value = plan.id;
                    option.textContent = plan.plan_name + ' - $' + parseFloat(plan.price).toFixed(2);
                    planSelect.appendChild(option);
                });
                
                if (memberPlans.length > 0) {
                    planSelect.value = memberPlans[0].id;
                    document.getElementById('amount').value = parseFloat(memberPlans[0].price).toFixed(2);
                }
            }
        }
    </script>
</body>
</html>
