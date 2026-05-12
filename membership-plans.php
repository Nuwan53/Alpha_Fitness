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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $duration_days = intval($_POST['duration_days'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || $duration_days <= 0 || $price <= 0) {
            $error = 'Name, duration, and price are required and must be valid.';
        } else {
            if (addPlan($conn, $name, $duration_days, $price, $description)) {
                $success = 'Membership plan added successfully!';
            } else {
                $error = 'Error adding membership plan.';
            }
        }
    } elseif ($action === 'edit') {
        $planId = intval($_POST['plan_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $duration_days = intval($_POST['duration_days'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $is_active = intval($_POST['is_active'] ?? 0);
        
        if ($planId > 0 && !empty($name) && $duration_days > 0 && $price > 0) {
            if (updatePlan($conn, $planId, $name, $duration_days, $price, $description, $is_active)) {
                $success = 'Membership plan updated successfully!';
            } else {
                $error = 'Error updating membership plan.';
            }
        } else {
            $error = 'Invalid input.';
        }
    } elseif ($action === 'delete') {
        $planId = intval($_POST['plan_id'] ?? 0);
        
        if ($planId > 0) {
            $result = deletePlan($conn, $planId);
            if ($result === true) {
                $success = 'Membership plan deleted successfully!';
            } else {
                $error = $result; // Error message from deletePlan function
            }
        } else {
            $error = 'Invalid plan ID.';
        }
    }
}

// Get all plans
$plans = getAllPlans($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Plans - Gym Management</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .content-wrapper {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .plans-table {
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .price-display {
            font-weight: 600;
            color: var(--primary);
            font-size: 16px;
        }
    </style>
</head>
<body>
    <header>
        <h1>Alpha Fitness - Membership Plans</h1>
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
            <a href="dashboard.php">Dashboard</a> > Membership Plans
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="content-wrapper">
            <div class="plans-table">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>All Membership Plans (<?php echo count($plans); ?>)</h2>
                    <button class="btn btn-primary" onclick="toggleForm()">+ Add Plan</button>
                </div>

                <?php if (count($plans) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Plan Name</th>
                                <th>Duration</th>
                                <th>Price</th>
                                <th>Active Members</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plans as $plan): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($plan['name']); ?></strong></td>
                                    <td><?php echo $plan['duration_days']; ?> days</td>
                                    <td class="price-display">$<?php echo number_format($plan['price'], 2); ?></td>
                                    <td><?php echo $plan['active_members']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo ($plan['is_active']) ? 'success' : 'warning'; ?>">
                                            <?php echo ($plan['is_active']) ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-secondary" onclick="editPlan(<?php echo $plan['id']; ?>)">Edit</button>
                                            <button class="btn btn-sm btn-danger" onclick="deletePlan(<?php echo $plan['id']; ?>)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-light); padding: 40px;">No membership plans yet. Click "Add Plan" to create one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add/Edit Form -->
        <div class="form-card" id="formCard" style="display: none;">
            <h3 id="formTitle">Add New Membership Plan</h3>
            <form method="POST" id="planForm">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="plan_id" id="plan_id" value="">

                <div class="form-group">
                    <label for="name">Plan Name *</label>
                    <input type="text" id="name" name="name" placeholder="e.g., 3-Month Premium" required>
                </div>

                <div class="form-group">
                    <label for="duration_days">Duration (Days) *</label>
                    <input type="number" id="duration_days" name="duration_days" placeholder="e.g., 90" min="1" required>
                </div>

                <div class="form-group">
                    <label for="price">Price ($) *</label>
                    <input type="number" id="price" name="price" placeholder="e.g., 99.99" min="0" step="0.01" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Benefits, features, etc."></textarea>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                    <label for="is_active" style="margin-bottom: 0;">Active Plan</label>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Save Plan</button>
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
                document.getElementById('formTitle').textContent = 'Add New Membership Plan';
                document.getElementById('action').value = 'add';
                document.getElementById('planForm').reset();
                document.getElementById('is_active').checked = true;
            }
        }

        function editPlan(planId) {
            alert('Edit functionality would fetch plan data. For now, delete and re-add.');
        }

        function deletePlan(planId) {
            if (confirm('Are you sure you want to delete this plan? This will fail if members are enrolled.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="plan_id" value="${planId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
