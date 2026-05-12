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
        $trainer_id = intval($_POST['trainer_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $class_date = trim($_POST['class_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $capacity = intval($_POST['capacity'] ?? 20);
        
        if ($trainer_id <= 0 || empty($title) || empty($class_date) || empty($start_time) || empty($end_time)) {
            $error = 'Trainer, title, date, and times are required.';
        } elseif (strtotime($end_time) <= strtotime($start_time)) {
            $error = 'End time must be after start time.';
        } else {
            if (addClass($conn, $trainer_id, $title, $description, $class_date, $start_time, $end_time, $capacity)) {
                $success = 'Class added successfully!';
            } else {
                $error = 'Error adding class.';
            }
        }
    } elseif ($action === 'delete') {
        $classId = intval($_POST['class_id'] ?? 0);
        
        if ($classId > 0) {
            if (deleteClass($conn, $classId)) {
                $success = 'Class deleted successfully!';
            } else {
                $error = 'Error deleting class.';
            }
        } else {
            $error = 'Invalid class ID.';
        }
    }
}

// Get classes count
$classesCount = getClassesCount($conn);
$totalPages = getTotalPages($classesCount, $itemsPerPage);

// Get classes list
$classes = getAllClasses($conn, $itemsPerPage, $offset);

// Get all trainers for dropdown
$trainers = getAllTrainers($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes - Gym Management</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .content-wrapper {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .classes-table {
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
        
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
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
        
        .badge-info {
            background: #d1fae5;
            color: #065f46;
        }

        .class-details {
            font-size: 13px;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <header>
        <h1>Alpha Fitness - Class Management</h1>
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
            <a href="dashboard.php">Dashboard</a> > Classes
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="content-wrapper">
            <div class="classes-table">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>All Classes (<?php echo $classesCount; ?>)</h2>
                    <button class="btn btn-primary" onclick="toggleForm()">+ Add Class</button>
                </div>

                <?php if (count($classes) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Trainer</th>
                                <th>Date & Time</th>
                                <th>Capacity</th>
                                <th>Booked</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($class['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($class['trainer_name'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($class['class_date'])); ?></div>
                                        <div class="class-details"><?php echo substr($class['start_time'], 0, 5); ?> - <?php echo substr($class['end_time'], 0, 5); ?></div>
                                    </td>
                                    <td><?php echo $class['capacity']; ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $class['booked_count']; ?>/<?php echo $class['capacity']; ?></span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-sm btn-secondary" onclick="viewBookings(<?php echo $class['id']; ?>)">Bookings</button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteClass(<?php echo $class['id']; ?>)">Delete</button>
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
                                <a href="classes.php?page=1">First</a>
                                <a href="classes.php?page=<?php echo $currentPage - 1; ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i === $currentPage): ?>
                                    <span class="active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="classes.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="classes.php?page=<?php echo $currentPage + 1; ?>">Next</a>
                                <a href="classes.php?page=<?php echo $totalPages; ?>">Last</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-light); padding: 40px;">No classes yet. Click "Add Class" to create one.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Class Form -->
        <div class="form-card" id="formCard" style="display: none;">
            <h3 id="formTitle">Add New Class</h3>
            <form method="POST" id="classForm">
                <input type="hidden" name="action" value="add">

                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Class Title *</label>
                        <input type="text" id="title" name="title" placeholder="e.g., Strength Training" required>
                    </div>

                    <div class="form-group">
                        <label for="trainer_id">Trainer *</label>
                        <select id="trainer_id" name="trainer_id" required>
                            <option value="">-- Select a trainer --</option>
                            <?php foreach ($trainers as $trainer): ?>
                                <option value="<?php echo $trainer['id']; ?>">
                                    <?php echo htmlspecialchars($trainer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Class details, what to expect, etc."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="class_date">Date *</label>
                        <input type="date" id="class_date" name="class_date" required>
                    </div>

                    <div class="form-group">
                        <label for="capacity">Capacity *</label>
                        <input type="number" id="capacity" name="capacity" value="20" min="1" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_time">Start Time *</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time *</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Add Class</button>
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
                document.getElementById('classForm').reset();
            }
        }

        function viewBookings(classId) {
            alert('Booking list would show members booked for this class.');
        }

        function deleteClass(classId) {
            if (confirm('Are you sure you want to delete this class? All bookings will also be deleted.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="class_id" value="${classId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
