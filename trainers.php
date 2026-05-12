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
$editingTrainer = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if (empty($name) || empty($email) || empty($specialty)) {
            $error = 'Name, email, and specialty are required.';
        } else {
            // Check if email exists
            $checkQuery = "SELECT id FROM users WHERE email = ?";
            if ($stmt = mysqli_prepare($conn, $checkQuery)) {
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $error = 'Email already exists.';
                } else {
                    // Create trainer user
                    $password = 'Trainer@' . substr(md5(time()), 0, 6);
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    
                    $insertUserQuery = "INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'trainer', 'active')";
                    if ($stmtUser = mysqli_prepare($conn, $insertUserQuery)) {
                        mysqli_stmt_bind_param($stmtUser, 'ssss', $name, $email, $phone, $hashedPassword);
                        if (mysqli_stmt_execute($stmtUser)) {
                            $newUserId = mysqli_insert_id($conn);
                            mysqli_stmt_close($stmtUser);
                            
                            // Add trainer record
                            if (addTrainer($conn, $newUserId, $specialty, $bio)) {
                                $success = 'Trainer added successfully!';
                            } else {
                                $error = 'Error adding trainer profile.';
                            }
                        } else {
                            $error = 'Error creating trainer account.';
                        }
                        mysqli_stmt_close($stmtUser);
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    } elseif ($action === 'edit') {
        $trainerId = intval($_POST['trainer_id'] ?? 0);
        $specialty = trim($_POST['specialty'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if ($trainerId > 0 && !empty($specialty)) {
            if (updateTrainer($conn, $trainerId, $specialty, $bio)) {
                $success = 'Trainer updated successfully!';
            } else {
                $error = 'Error updating trainer.';
            }
        } else {
            $error = 'Invalid input.';
        }
    } elseif ($action === 'delete') {
        $trainerId = intval($_POST['trainer_id'] ?? 0);
        
        if ($trainerId > 0) {
            $result = deleteTrainer($conn, $trainerId);
            if ($result === true) {
                $success = 'Trainer deleted successfully!';
            } else {
                $error = $result; // Error message from deleteTrainer function
            }
        } else {
            $error = 'Invalid trainer ID.';
        }
    }
}

// Get all trainers
$trainers = getAllTrainers($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainers - Gym Management</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .content-wrapper {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .trainer-table {
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
        .form-group textarea {
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
        .form-group textarea:focus {
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
    </style>
</head>
<body>
    <header>
        <h1>Alpha Fitness - Trainer Management</h1>
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
            <a href="dashboard.php">Dashboard</a> > Trainers
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="content-wrapper">
            <div class="trainer-table">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>All Trainers (<?php echo count($trainers); ?>)</h2>
                    <button class="btn btn-primary" onclick="toggleForm()">+ Add Trainer</button>
                </div>

                <?php if (count($trainers) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Specialty</th>
                                <th>Classes</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trainers as $trainer): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($trainer['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($trainer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($trainer['phone'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($trainer['specialty'] ?? ''); ?></td>
                                    <td><?php echo $trainer['total_classes']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo ($trainer['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($trainer['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-secondary" onclick="editTrainer(<?php echo $trainer['id']; ?>)">Edit</button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteTrainer(<?php echo $trainer['id']; ?>)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-light); padding: 40px;">No trainers yet. Click "Add Trainer" to create one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add/Edit Form -->
        <div class="form-card" id="formCard" style="display: none;">
            <h3 id="formTitle">Add New Trainer</h3>
            <form method="POST" id="trainerForm">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="trainer_id" id="trainer_id" value="">

                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone">
                </div>

                <div class="form-group">
                    <label for="specialty">Specialty *</label>
                    <input type="text" id="specialty" name="specialty" placeholder="e.g., Strength Training, Yoga" required>
                </div>

                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" placeholder="Brief bio or qualifications"></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Save Trainer</button>
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
                document.getElementById('formTitle').textContent = 'Add New Trainer';
                document.getElementById('action').value = 'add';
                document.getElementById('trainerForm').reset();
                document.getElementById('email').disabled = false;
                document.getElementById('name').disabled = false;
                document.getElementById('phone').disabled = false;
            }
        }

        function editTrainer(trainerId) {
            // For simplicity, reload page with edit mode
            // In production, you'd fetch trainer data via AJAX
            alert('Edit functionality would fetch trainer data. For now, delete and re-add.');
        }

        function deleteTrainer(trainerId) {
            if (confirm('Are you sure you want to delete this trainer? This will fail if they have scheduled classes.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="trainer_id" value="${trainerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <style>
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
        
        .badge-secondary {
            background: #e5e7eb;
            color: #374151;
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
</body>
</html>
