<?php
// dsh.php - Backend Endpoint (Returns HTML fragment based on session role)

// Start the session
session_start();

// Set the content type to plain text or HTML fragment
header('Content-Type: text/html');

// --- 1. Handle Role Testing/Setting (if a role is posted) ---
// This block allows a separate frontend call (like AJAX or a form post) to set the role
if (isset($_POST['set_role'])) {
    $role_to_set = trim($_POST['set_role']);
    
    // Validate role for security and set session
    $allowed_roles = ['admin', 'manager', 'mechanic', 'cashier', 'logout'];
    if (in_array($role_to_set, $allowed_roles) && $role_to_set !== 'logout') {
        $_SESSION['user_role'] = $role_to_set;
    } else {
        // Clear session on 'logout' or invalid role
        unset($_SESSION['user_role']);
    }
    
    // For a backend-only action, simply return a success status
    // In a real application, you might return JSON: {"status": "success"}
    http_response_code(200);
    echo "Role updated successfully to: " . ($_SESSION['user_role'] ?? 'guest');
    exit();
}

// --- 2. Check Session Role and Determine File ---
$user_role = $_SESSION['role'] ?? 'guest'; // Default to 'guest'
$file_map = [
    'admin' => '../pages/dashboard/adm.html',
    'manager' => '../pages/dashboard/mgr.html',
    'mechanic' => '../pages/dashboard/mch.html',
    'cashier' => '../pages/dashboard/csh.html',
    'guest' => '../pages/dashboard/auth.html'
];

$filename = $file_map[$user_role] ?? '../pages/dashboard/auth.html';
$dashboard_content = '';

// --- 3. Fetch and Return HTML Contents ---
if (file_exists($filename)) {
    // Read the content of the role-specific file
    $dashboard_content = file_get_contents($filename);
} else {
    // Error message returned as HTML fragment
    $dashboard_content = "<div class='error-message'>Error: View file '{$filename}' not found for role '{$user_role}'.</div>";
}

// Output ONLY the HTML fragment
echo $dashboard_content;

// End the PHP script
exit();
?>