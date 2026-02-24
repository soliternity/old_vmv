<?php
// admin_backup_codes.php - Dedicated Backup Code Management for Admin

// ===============================================
// 1. PHP BACKEND LOGIC (Database Connection & Actions)
// ===============================================

// --- CONFIGURATION ---
// !! CHANGE THESE TO YOUR ACTUAL DATABASE CREDENTIALS !!
define('DB_HOST', 'localhost');
define('DB_USER', 'u157619782_d');
define('DB_PASS', '@VMVJeffix123');
define('DB_NAME', 'u157619782_d');
// Assuming the Admin's staff_id is 1. Adjust if necessary.
define('ADMIN_STAFF_ID', 1); 

// --- DATABASE CONNECTION ---
function getDbConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// --- CORE FUNCTIONS ---
function generateSecureCode() {
    // Generates a 16-character code (e.g., A6B3-C9E0-D1F2-G3H4)
    $bytes = random_bytes(8); 
    $code = bin2hex($bytes); 
    return strtoupper(implode('-', str_split($code, 4))); 
}

// --- AJAX HANDLER ---
function handleAjaxRequest() {
    if (!isset($_GET['action'])) return;

    header('Content-Type: application/json');
    $pdo = getDbConnection();
    $action = $_GET['action'];
    $staff_id = ADMIN_STAFF_ID; // Always use the Admin ID

    switch ($action) {
        case 'get_codes':
            // Fetch all backup codes for the Admin
            $stmt = $pdo->prepare("SELECT id, code_hash, is_used, created_at, used_at FROM backup_codes WHERE staff_id = ? ORDER BY created_at DESC");
            $stmt->execute([$staff_id]);
            $codes = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $codes]);
            break;

        case 'generate_codes':
            // Generate and insert 10 new codes
            $count = 10;
            $pdo->beginTransaction();
            $insert_sql = "INSERT INTO backup_codes (staff_id, code_hash) VALUES (?, ?)";
            $stmt = $pdo->prepare($insert_sql);
            
            for ($i = 0; $i < $count; $i++) {
                $raw_code = generateSecureCode();
                $code_hash = hash('sha256', $raw_code); // Store hash, NOT the raw code
                $stmt->execute([$staff_id, $code_hash]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "$count new backup codes generated."]);
            break;

        case 'revoke_codes':
            // Invalidate all unused codes for the Admin
            $stmt = $pdo->prepare("UPDATE backup_codes SET is_used = TRUE, used_at = NOW() WHERE staff_id = ? AND is_used = FALSE");
            $stmt->execute([$staff_id]);
            $count = $stmt->rowCount();
            
            echo json_encode(['success' => true, 'message' => "$count unused codes revoked."]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    exit; 
}

// Run the AJAX handler if an action is requested
handleAjaxRequest();

// --- PHP Initial Data Fetch (Optional, handled by JS in this setup) ---
// We don't need initial staff data fetch since JS handles the code list via AJAX
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Backup Codes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        :root {
            --sm_primary-color: #FF4E1F;
            --sm_dark-color: #212121;
            --sm_light-color: #f7f7f7;
            --sm_background-color: #E8E6DE;
            --sm_accent-color: #481E14;
            --sm_success-color: #28a745;
            --sm_danger-color: #dc3545;
            --sm_info-color: #17a2b8;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: var(--sm_background-color);
            color: var(--sm_dark-color);
            line-height: 1.6;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .sm_container {
            width: 100%;
            max-width: 700px;
            background-color: var(--sm_light-color);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .sm_header {
            background-color: var(--sm_primary-color);
            color: var(--sm_light-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .sm_header h1 {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1.8em;
        }
        .sm_header .sm_logo i {
            font-size: 1.2em;
            color: var(--sm_accent-color);
        }

        .sm_controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .sm_button {
            padding: 12px 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--sm_light-color);
            width: 100%; /* Full width for control buttons */
        }

        #sm_generateNewCodesBtn {
            background-color: var(--sm_success-color);
        }
        #sm_generateNewCodesBtn:hover {
            background-color: #218838;
        }

        #sm_revokeCodesBtn {
            background-color: var(--sm_danger-color);
        }
        #sm_revokeCodesBtn:hover {
            background-color: #c82333;
        }

        /* Codes List */
        .sm_codes-list-header {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--sm_accent-color);
            margin-bottom: 10px;
            border-bottom: 2px solid var(--sm_background-color);
            padding-bottom: 5px;
        }

        #sm_codesList {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid var(--sm_background-color);
            max-height: 300px;
            overflow-y: auto;
            border-radius: 5px;
            background-color: #fff;
        }

        .sm_code-item {
            padding: 8px;
            border-bottom: 1px dotted #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: monospace;
            font-size: 0.95em;
        }
        .sm_code-item:last-child {
            border-bottom: none;
        }

        .sm_code-item .sm_hash {
            color: var(--sm_dark-color);
        }

        .sm_code-item .sm_status {
            font-size: 0.85em;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: bold;
            text-align: right;
        }

        .sm_code-item.sm_used .sm_status {
            color: white;
            background-color: var(--sm_danger-color); 
        }

        .sm_code-item.sm_unused .sm_status {
            color: white;
            background-color: var(--sm_success-color); 
        }

    </style>
    <link rel="stylesheet" href="../css/nav.css">
    
<script src="../js/nav.js"></script>
</head>
<body>
    <div id="nav-placeholder"></div>
    <div class="sm_container">
        <header class="sm_header">
            <h1>
                <span class="sm_logo"><i class="fas fa-user-shield"></i></span>
                Admin Backup Codes
            </h1>
            <p>Use these codes to regain account access if 2FA is lost.</p>
        </header>

        <div class="sm_controls">
            <button id="sm_generateNewCodesBtn" class="sm_button" title="Generates 10 new, unused codes."><i class="fas fa-magic"></i> Generate 10 New Codes</button>
            <button id="sm_revokeCodesBtn" class="sm_button" title="Invalidates all existing UNUSED codes."><i class="fas fa-ban"></i> Revoke All Unused Codes</button>
        </div>

        <div class="sm_codes-list-header">
            List of Backup Codes
        </div>

        <div id="sm_codesList">
            <p style="text-align:center; color:#666;"><i class="fas fa-sync fa-spin"></i> Loading codes...</p>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // DOM element references with 'sm_' prefix
            const codesList = document.getElementById('sm_codesList');
            const generateNewCodesBtn = document.getElementById('sm_generateNewCodesBtn');
            const revokeCodesBtn = document.getElementById('sm_revokeCodesBtn');
            const staffId = <?php echo ADMIN_STAFF_ID; ?>;
            const phpFile = '<?php echo basename(__FILE__); ?>'; 

            /**
             * Handles all AJAX requests.
             * @param {string} action - The PHP action (e.g., 'get_codes').
             * @returns {Promise<object>} The JSON response from the server.
             */
            async function sendRequest(action) {
                // We don't need to pass staffId as it's hardcoded in the PHP side (ADMIN_STAFF_ID)
                const url = `${phpFile}?action=${action}`;
                try {
                    const response = await fetch(url);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return await response.json();
                } catch (error) {
                    console.error('AJAX Request failed:', error);
                    alert(`Error performing ${action}: ${error.message}`);
                    return { success: false, message: error.message };
                }
            }

            // --- Code Rendering ---

            /**
             * Renders the list of codes.
             * @param {Array<Object>} codes - Array of code objects.
             */
            function renderCodes(codes) {
                codesList.innerHTML = '';
                if (codes.length === 0) {
                    codesList.innerHTML = '<p style="text-align:center; color:#666;">No backup codes found.</p>';
                    return;
                }

                codes.forEach(code => {
                    const item = document.createElement('div');
                    const isUsed = code.is_used == 1; 
                    item.classList.add('sm_code-item');
                    item.classList.add(isUsed ? 'sm_used' : 'sm_unused');

                    const statusText = isUsed 
                        ? `USED (${code.used_at ? code.used_at.substring(0, 10) : 'N/A'})` 
                        : 'UNUSED';
                    
                    // Display the hash (for list view) and status
                    item.innerHTML = `
                        <span class="sm_hash">Hash: ${code.code_hash.substring(0, 32)}...</span>
                        <span class="sm_status">${statusText}</span>
                    `;
                    codesList.appendChild(item);
                });
            }

            /**
             * Fetches and displays the current codes list.
             */
            async function loadCodes() {
                codesList.innerHTML = '<p style="text-align:center; color:#666;"><i class="fas fa-sync fa-spin"></i> Loading codes...</p>';

                const response = await sendRequest('get_codes');

                if (response.success) {
                    renderCodes(response.data);
                } else {
                    codesList.innerHTML = `<p style="color:var(--sm_danger-color); text-align:center;">Failed to load codes: ${response.message}</p>`;
                }
            }

            // --- Event Listeners ---

            // Generate Codes
            generateNewCodesBtn.onclick = async () => {
                if (confirm('Are you sure you want to GENERATE 10 NEW backup codes?')) {
                    codesList.innerHTML = '<p style="text-align:center; color:#666;"><i class="fas fa-sync fa-spin"></i> Generating...</p>';
                    
                    const response = await sendRequest('generate_codes');

                    if (response.success) {
                        alert(response.message + " NOTE: The actual raw codes should be saved securely immediately after generation.");
                        loadCodes(); // Reload list
                    }
                }
            }

            // Revoke Codes
            revokeCodesBtn.onclick = async () => {
                if (confirm('WARNING: This will mark ALL UNUSED backup codes as used/revoked. Proceed?')) {
                    const response = await sendRequest('revoke_codes');

                    if (response.success) {
                        alert(response.message);
                        loadCodes(); // Reload list
                    }
                }
            }

            // Initial load
            loadCodes();
        });
    </script>
</body>
</html>