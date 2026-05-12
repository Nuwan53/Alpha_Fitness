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
$itemsPerPage = 15;
$offset = getPaginationOffset($currentPage, $itemsPerPage);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $plan_id = intval($_POST['plan_id'] ?? 0);
        
        if (empty($name) || empty($email)) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            // Add member
            $memberId = addMember($conn, $name, $email, $phone);
            if ($memberId) {
                // Assign plan if selected
                if ($plan_id > 0) {
                    $startDate = date('Y-m-d');
                    assignMemberPlan($conn, $memberId, $plan_id, $startDate);
                }
                $success = 'Member added successfully!';
            } else {
                $error = 'Error adding member. Email may already exist.';
            }
        }
    } elseif ($action === 'edit') {
        $memberId = intval($_POST['member_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        
        if ($memberId > 0 && !empty($name) && !empty($email)) {
            if (updateMember($conn, $memberId, $name, $email, $phone, $status)) {
                $success = 'Member updated successfully!';
            } else {
                $error = 'Error updating member.';
            }
        } else {
            $error = 'Invalid input.';
        }
    } elseif ($action === 'delete') {
        $memberId = intval($_POST['member_id'] ?? 0);
        
        if ($memberId > 0) {
            if (deleteMember($conn, $memberId)) {
                $success = 'Member deleted successfully!';
            } else {
                $error = 'Error deleting member.';
            }
        } else {
            $error = 'Invalid member ID.';
        }
    }
}

// Get members count
$totalMembers = getTotalMembersCount($conn);
$totalPages = getTotalPages($totalMembers, $itemsPerPage);

// Get members list
$members = getMemberList($conn, $itemsPerPage, $offset);

// Get all plans for dropdown
$plans = getAllPlans($conn, true); // Only active plans

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - Gym Management</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .content-wrapper {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .members-table {
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-info {
            background: var(--info);
            color: white;
        }
        
        .btn-info:hover {
            background: #059669;
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
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
    </style>
</head>
<body>
    <header>
        <h1>Alpha Fitness - Member Management</h1>
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
            <a href="dashboard.php">Dashboard</a> > Members
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="content-wrapper">
            <div class="members-table">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>All Members (<?php echo $totalMembers; ?>)</h2>
                    <button class="btn btn-primary" onclick="toggleForm()">+ Add Member</button>
                </div>

                <?php if (count($members) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Current Plan</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($member['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['phone'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($member['plan_name'] ?? 'None'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo ($member['status'] === 'active') ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($member['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="member-detail.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <button class="btn btn-sm btn-secondary" onclick="editMember(<?php echo $member['id']; ?>)">Edit</button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteMember(<?php echo $member['id']; ?>)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="members.php?page=1">First</a>
                                <a href="members.php?page=<?php echo $currentPage - 1; ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i === $currentPage): ?>
                                    <span class="active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="members.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="members.php?page=<?php echo $currentPage + 1; ?>">Next</a>
                                <a href="members.php?page=<?php echo $totalPages; ?>">Last</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-light); padding: 40px;">No members yet. Click "Add Member" to create one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add/Edit Form -->
        <div class="form-card" id="formCard" style="display: none;">
            <h3 id="formTitle">Add New Member</h3>
            <form method="POST" id="memberForm">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="member_id" id="member_id" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label for="plan_id">Membership Plan</label>
                        <select id="plan_id" name="plan_id">
                            <option value="">-- Select a plan --</option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo $plan['id']; ?>">
                                    <?php echo htmlspecialchars($plan['name']); ?> - $<?php echo number_format($plan['price'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="statusGroup" style="display: none;">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Save Member</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleForm() {
            const form = document.getElementById('formCard');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                document.getElementById('formTitle').textContent = 'Add New Member';
                document.getElementById('action').value = 'add';
                document.getElementById('memberForm').reset();
                document.getElementById('statusGroup').style.display = 'none';
                document.getElementById('plan_id').disabled = false;
                document.getElementById('email').disabled = false;
            }
        }

        function editMember(memberId) {
            alert('Edit functionality would fetch member data. For now, delete and re-add.');
        }

        function deleteMember(memberId) {
            if (confirm('Are you sure you want to delete this member? All related records will also be deleted.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="member_id" value="${memberId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
