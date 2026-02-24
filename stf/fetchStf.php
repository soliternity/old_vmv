<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized: Please log in first"
    ]);
    exit();
}

// Include database connection
require_once '../dbconn.php';

try {
    // Get current user role and ID from session
    $current_user_role = $_SESSION['role'] ?? null;
    $current_user_id = $_SESSION['user_id'] ?? null;

    if (!$current_user_role || !$current_user_id) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Access denied: User role or ID not found"
        ]);
        exit();
    }

    // Get parameters from request
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $role = isset($_GET['role']) ? $conn->real_escape_string($_GET['role']) : null;
    $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : null;
    $is_archived = isset($_GET['is_archived']) ? intval($_GET['is_archived']) : 0;
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Build SQL query with role-based filtering and exclusion of the current user
    $sql = "SELECT id, lname, fname, mname, username, email, role, status, is_archived, created_at, updated_at 
            FROM staff 
            WHERE 1=1 AND id != ?";
    
    $params = [$current_user_id];
    $types = "i";

    // Add role-based filtering
    if ($current_user_role === 'admin') {
        // Admin can see all roles
        // No additional filter needed
    } elseif ($current_user_role === 'manager') {
        // Manager can only see mechanic and cashier roles
        $sql .= " AND role IN ('mechanic', 'cashier')";
    } else {
        // Other roles (mechanic, cashier) can only see themselves, but this query is
        // designed to exclude the current user. Therefore, for these roles, this
        // query will always return an empty result set.
    }

    // Add other filters
    if ($id !== null) {
        $sql .= " AND id = ?";
        $params[] = $id;
        $types .= "i";
    }

    if ($role !== null) {
        // Validate role filter based on user permissions
        $allowed_roles = [];
        if ($current_user_role === 'admin') {
            $allowed_roles = ['admin', 'manager', 'mechanic', 'cashier'];
        } elseif ($current_user_role === 'manager') {
            $allowed_roles = ['mechanic', 'cashier'];
        } else {
            // Non-admin/manager can't filter by role
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Access denied: Cannot filter by this role"
            ]);
            exit();
        }

        if (!in_array($role, $allowed_roles)) {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Access denied: Cannot filter by this role"
            ]);
            exit();
        }

        $sql .= " AND role = ?";
        $params[] = $role;
        $types .= "s";
    }

    if ($status !== null) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($is_archived !== null) {
        $sql .= " AND is_archived = ?";
        $params[] = $is_archived;
        $types .= "i";
    }

    if ($search !== null) {
        $sql .= " AND (lname LIKE ? OR fname LIKE ? OR mname LIKE ? OR username LIKE ? OR email LIKE ?)";
        $searchTerm = "%" . $search . "%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= str_repeat("s", 5);
    }

    // Add sorting and pagination
    $sql .= " ORDER BY lname, fname, mname LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    // Prepare statement
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param($types, ...$params);

    // Execute query
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Get result
    $result = $stmt->get_result();
    $staff = [];

    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }

    // Get total count for pagination (with same filters and exclusion)
    $countSql = "SELECT COUNT(*) as total FROM staff WHERE 1=1 AND id != ?";
    $countParams = [$current_user_id];
    $countTypes = "i";

    // Reapply role-based filtering for count
    if ($current_user_role === 'manager') {
        $countSql .= " AND role IN ('mechanic', 'cashier')";
    } elseif (!in_array($current_user_role, ['admin', 'manager'])) {
        // Non-admin/manager roles will have a count of 0 since the query excludes them
    }

    // Reapply other filters for count
    if ($id !== null) {
        $countSql .= " AND id = ?";
        $countParams[] = $id;
        $countTypes .= "i";
    }

    if ($role !== null) {
        $countSql .= " AND role = ?";
        $countParams[] = $role;
        $countTypes .= "s";
    }

    if ($status !== null) {
        $countSql .= " AND status = ?";
        $countParams[] = $status;
        $countTypes .= "s";
    }

    if ($is_archived !== null) {
        $countSql .= " AND is_archived = ?";
        $countParams[] = $is_archived;
        $countTypes .= "i";
    }

    if ($search !== null) {
        $countSql .= " AND (lname LIKE ? OR fname LIKE ? OR mname LIKE ? OR username LIKE ? OR email LIKE ?)";
        $searchTerm = "%" . $search . "%";
        $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $countTypes .= str_repeat("s", 5);
    }

    $countStmt = $conn->prepare($countSql);
    if (!empty($countParams)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];

    // Return success response with user info (without exposing user ID)
    echo json_encode([
        "success" => true,
        "data" => $staff,
        "current_user" => [
            "role" => $current_user_role,
            "username" => $_SESSION['username'] ?? null
        ],
        "pagination" => [
            "total" => $total,
            "limit" => $limit,
            "offset" => $offset,
            "has_more" => ($offset + count($staff)) < $total
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
} finally {
    // Close connections
    if (isset($stmt)) $stmt->close();
    if (isset($countStmt)) $countStmt->close();
    if (isset($conn)) $conn->close();
}
?>