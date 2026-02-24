<?php
/**
 * Role-Based Access Control (RBAC) Permissions for Navigation Links.
 * This script serves the array of allowed navigation links for a given role.
 * * NOTE: For security, a production environment should typically check the role 
 * directly from a secure session ($_SESSION) rather than relying on a client-
 * provided parameter like $_GET['role']. 
 */

// Define the permissions array
$permissions = [
    // --- ADMIN Role Permissions ---
    'ADMIN' => [
        'Dashboard',
        'Shop\'s Workflow', 
        'Job Orders',
        'Billing/Invoices',
        'Transaction History',
        'Shop Management', 
        'Appointments',
        'Services/Repairs',
        'Vehicle Parts',
        'Staff Management',
        'Performance Reports',
        'VMV users List',
        'System Activities',
        'All Messages',
        'Backup Codes',
        'Settings'
    ],

    // --- MANAGER Role Permissions ---
    'MANAGER' => [
        'Dashboard',
        'Shop\'s Workflow', 
        'Job Orders',
        'Billing/Invoices',
        'Transaction History',
        'Shop Management', 
        'Appointments',
        'Services/Repairs',
        'Vehicle Parts',
        'Staff Management',
        'Performance Reports',
        'All Messages',
        'Settings'
    ],

    // --- MECHANIC Role Permissions ---
    'MECHANIC' => [
        'Dashboard',
        'Shop Management',
        'Appointments',
        'Services/Repairs',
        'Vehicle Parts',
        'My Jobs',
        'Chats',
        'My Reports',
        'Settings'
    ],

    // --- CASHIER Role Permissions ---
    'CASHIER' => [
        'Dashboard',
        'Shop\'s Workflow', 
        'Job Orders',
        'Billing/Invoices',
        'Transaction History',
        'Shop Management',
        'Services/Repairs',
        'Vehicle Parts',
        'Appointments',
        'Settings'
    ]
];

// --- Script Execution: Check for Role and Output JSON ---

// Set header to application/json
header('Content-Type: application/json');

// Check if a role parameter is provided in the request
if (isset($_GET['role'])) {
    // Sanitize and normalize the role name
    $requested_role = strtoupper(trim($_GET['role']));

    // Find the permissions for the requested role, default to an empty array for unknown roles
    $allowed_links = $permissions[$requested_role] ?? [];

    // Output the allowed links as a JSON object
    echo json_encode([
        'success' => true,
        'role' => $requested_role,
        'allowed_links' => $allowed_links
    ]);
} else {
    // Handle error if no role is provided
    echo json_encode([
        'success' => false,
        'message' => 'Role parameter is missing.'
    ]);
}

exit; // Stop further execution