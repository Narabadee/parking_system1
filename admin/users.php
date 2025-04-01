<?php
require_once "../config.php";

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Handle user status changes
if (isset($_POST['action']) && $_POST['action'] == 'updateRole') {
    $authId = $_POST['auth_id'];
    $newRole = $_POST['role'];
    
    $updateSql = "UPDATE auth_users SET role = ? WHERE auth_id = ?";
    $stmt = mysqli_prepare($conn, $updateSql);
    mysqli_stmt_bind_param($stmt, "si", $newRole, $authId);
    
    if (mysqli_stmt_execute($stmt)) {
        $successMsg = "User role updated successfully!";
    } else {
        $errorMsg = "Error updating user role: " . mysqli_error($conn);
    }
}

// Handle user deletion
if (isset($_POST['action']) && $_POST['action'] == 'deleteUser') {
    $userId = $_POST['user_id'];
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Delete auth_users entries
        $deleteAuthSql = "DELETE FROM auth_users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $deleteAuthSql);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        
        // Delete the user
        $deleteUserSql = "DELETE FROM user WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $deleteUserSql);
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        $successMsg = "User deleted successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $errorMsg = "Error deleting user: " . $e->getMessage();
    }
}

// Define search and filter variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';

// Build query conditions based on filters
$conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.tel LIKE ? OR au.username LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

if (!empty($roleFilter)) {
    $conditions[] = "au.role = ?";
    $params[] = $roleFilter;
    $types .= "s";
}

if (!empty($typeFilter)) {
    $conditions[] = "u.type_id = ?";
    $params[] = $typeFilter;
    $types .= "i";
}

// Get user list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Base SQL query
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.tel, u.created_at, 
               ut.type_name, 
               au.auth_id, au.username, au.role,
               COUNT(pr.record_id) as parking_count
        FROM user u
        LEFT JOIN auth_users au ON u.user_id = au.user_id
        LEFT JOIN user_types ut ON u.type_id = ut.type_id
        LEFT JOIN parking_records pr ON u.user_id = pr.user_id";

// Add WHERE clause if we have conditions
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Add GROUP BY and ORDER BY
$sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC";

// Prepare and execute count query
$countSql = "SELECT COUNT(DISTINCT u.user_id) as total FROM user u 
             LEFT JOIN auth_users au ON u.user_id = au.user_id
             LEFT JOIN user_types ut ON u.type_id = ut.type_id";
             
if (!empty($conditions)) {
    $countSql .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = mysqli_prepare($conn, $countSql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$countResult = mysqli_stmt_get_result($stmt);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Add LIMIT to main query
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $recordsPerPage;
$types .= "ii";

// Prepare and execute main query
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get user types for filter dropdown
$typesSql = "SELECT * FROM user_types";
$typesResult = mysqli_query($conn, $typesSql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Parking System Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #4e73df;
            color: white;
        }
        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .content {
            padding: 20px;
        }
        .nav-item {
            margin-bottom: 5px;
        }
        .icon {
            margin-right: 10px;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .pagination {
            margin-bottom: 0;
        }
        .badge-role {
            font-size: 85%;
        }
        .table th {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="text-center py-4">
                    <h4>Admin Panel</h4>
                </div>
                <hr class="sidebar-divider">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt icon"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="parking.php">
                            <i class="fas fa-parking icon"></i>Parking Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="active">
                            <i class="fas fa-users icon"></i>User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php">
                            <i class="fas fa-chart-bar icon"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt icon"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">User Management</h6>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if(isset($successMsg)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $successMsg; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(isset($errorMsg)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $errorMsg; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Search and Filter -->
                        <form action="" method="GET" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Search..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select name="role" class="form-select">
                                        <option value="">All Roles</option>
                                        <option value="admin" <?php echo $roleFilter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="user" <?php echo $roleFilter == 'user' ? 'selected' : ''; ?>>User</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="type" class="form-select">
                                        <option value="">All Types</option>
                                        <?php while($type = mysqli_fetch_assoc($typesResult)): ?>
                                        <option value="<?php echo $type['type_id']; ?>" <?php echo $typeFilter == $type['type_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['type_name']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </div>
                        </form>

                        <!-- Users Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Type</th>
                                        <th>Role</th>
                                        <th>Tel</th>
                                        <th>Parking Count</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($result) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><?php echo $row['user_id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                <td>
                                                    <span class="badge bg-info text-dark">
                                                        <?php echo htmlspecialchars($row['type_name']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $row['role'] == 'admin' ? 'bg-danger' : 'bg-success'; ?>">
                                                        <?php echo htmlspecialchars($row['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['tel']); ?></td>
                                                <td><?php echo $row['parking_count']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="edit_user.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="view_user.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-primary change-role-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#changeRoleModal"
                                                                data-auth-id="<?php echo $row['auth_id']; ?>" 
                                                                data-username="<?php echo htmlspecialchars($row['username']); ?>"
                                                                data-current-role="<?php echo $row['role']; ?>">
                                                            <i class="fas fa-user-shield"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-user-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                                data-user-id="<?php echo $row['user_id']; ?>" 
                                                                data-username="<?php echo htmlspecialchars($row['username']); ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if($totalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                Showing <?php echo min(($page - 1) * $recordsPerPage + 1, $totalRecords); ?> to 
                                <?php echo min($page * $recordsPerPage, $totalRecords); ?> of 
                                <?php echo $totalRecords; ?> entries
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>&type=<?php echo urlencode($typeFilter); ?>">
                                            Previous
                                        </a>
                                    </li>
                                    
                                    <?php for($i = max(1, $page - 2); $i <= min($page + 2, $totalPages); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>&type=<?php echo urlencode($typeFilter); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>&type=<?php echo urlencode($typeFilter); ?>">
                                            Next
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Role Modal -->
    <div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeRoleModalLabel">Change User Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="updateRole">
                        <input type="hidden" name="auth_id" id="changeRoleAuthId">
                        <p>You are changing the role for user: <strong id="changeRoleUsername"></strong></p>
                        <div class="mb-3">
                            <label for="roleSelect" class="form-label">Select Role</label>
                            <select class="form-select" id="roleSelect" name="role">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="deleteUser">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <p>Are you sure you want to delete the user: <strong id="deleteUsername"></strong>?</p>
                        <div class="alert alert-danger">
                            Warning: This action cannot be undone. All data related to this user will be permanently deleted.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Change Role Modal
        document.querySelectorAll('.change-role-btn').forEach(button => {
            button.addEventListener('click', function() {
                const authId = this.getAttribute('data-auth-id');
                const username = this.getAttribute('data-username');
                const currentRole = this.getAttribute('data-current-role');
                
                document.getElementById('changeRoleAuthId').value = authId;
                document.getElementById('changeRoleUsername').textContent = username;
                document.getElementById('roleSelect').value = currentRole;
            });
        });
        
        // Delete User Modal
        document.querySelectorAll('.delete-user-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const username = this.getAttribute('data-username');
                
                document.getElementById('deleteUserId').value = userId;
                document.getElementById('deleteUsername').textContent = username;
            });
        });
    </script>
</body>
</html>