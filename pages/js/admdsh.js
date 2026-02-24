// admdsh.js

/* ------------------------------------------------------------------- */
/* JavaScript (with Prefix 'adm-') */
/* ------------------------------------------------------------------- */
    
const admDataEndpoint = '../b/admdsh/fetchdsh.php'; 

// --- Helper Functions ---
    
/**
 * Builds and appends a clickable stat card to the grid.
 */
function admCreateStatCard(label, value, iconClass, linkUrl) {
    // FIX: Element retrieval must be inside this function's scope to ensure it's found
    const statsGrid = document.getElementById('adm-stats-grid');
    if (!statsGrid) {
        console.error("admCreateStatCard failed: Container 'adm-stats-grid' not found.");
        return;
    }

    const card = document.createElement('div');
    card.className = 'adm-stat-card';
    card.onclick = () => { window.location.href = linkUrl; }; 
    
    const id = label.toLowerCase().replace(/ /g, '-'); 
    card.id = `adm-card-${id}`;
    
    card.innerHTML = `
        <div class="adm-stat-card-info">
            <h3>${label}</h3>
            <p>${value}</p>
        </div>
        <div class="adm-stat-icon">
            <i class="fas ${iconClass}"></i>
        </div>
    `;
    statsGrid.appendChild(card);
}

/**
 * Builds and appends a row to the System Activity table.
 */
function admCreateActivityRow(title, action, staffId, tableName, recordId, timestamp) {
    const activityBody = document.querySelector('#adm-activity-table tbody');
    if (!activityBody) return;

    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${title}</td>
        <td>${action}</td>
        <td>${staffId}</td>
        <td>${tableName}</td>
        <td>${recordId}</td>
        <td>${timestamp}</td>
    `;
    activityBody.appendChild(row);
}

/**
 * Builds and appends a row to the Login Logs table.
 */
function admCreateLoginLogRow(username, staffId, status, reason, timestamp) {
    const loginLogsBody = document.querySelector('#adm-login-logs-table tbody');
    if (!loginLogsBody) return;

    const statusClass = status.toLowerCase() === 'success' ? 'adm-log-success' : 'adm-log-failure';
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${username}</td>
        <td>${staffId}</td>
        <td class="${statusClass}">${status}</td>
        <td>${reason || '-'}</td>
        <td>${timestamp}</td>
    `;
    loginLogsBody.appendChild(row);
}

/**
 * Main function to fetch and render the dashboard data.
 */
async function initAdminDashboard() {
    const statsGrid = document.getElementById('adm-stats-grid');
    const loadingIndicator = document.getElementById('adm-loading-indicator');
    const activityBody = document.querySelector('#adm-activity-table tbody');
    const loginLogsBody = document.querySelector('#adm-login-logs-table tbody');

    // Initial check for all critical elements
    if (!statsGrid || !activityBody || !loginLogsBody) {
        console.error("Dashboard initialization failed: Missing one or more critical HTML elements.");
        return;
    }

    // Clear loading indicator and existing content
    if (loadingIndicator) loadingIndicator.style.display = 'block';
    statsGrid.innerHTML = ''; // Clear the grid for dynamic insertion
    activityBody.innerHTML = '';
    loginLogsBody.innerHTML = '';


    try {
        // 1. Fetch Data
        const response = await fetch(admDataEndpoint);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        // Clear loading indicator
        if (loadingIndicator) loadingIndicator.style.display = 'none';

        // 2. Render Stat Cards
        admCreateStatCard("VMV Users", data.stats.vmv_users, "fa-car", "au.html");
        admCreateStatCard("Staff Accounts", data.stats.staff_accounts, "fa-user-tie", "stf.html");
        
        // RENDER MODIFIED STAT CARD
        if (data.stats.other && Array.isArray(data.stats.other)) {
            data.stats.other.forEach(stat => {
                // Check for the specific service list entry
                if (stat.label === "Services List") {
                    admCreateStatCard(
                        "Services List", 
                        stat.value, 
                        "fa-clipboard-list", // Updated icon
                        "svc.html"           // Updated link
                    );
                } else {
                     admCreateStatCard(stat.label, stat.value, stat.icon, stat.link || '#');
                }
            });
        }

        // 3. Render System Activity Table
        if (data.activity_logs && Array.isArray(data.activity_logs)) {
            data.activity_logs.forEach(log => {
                admCreateActivityRow(log.title, log.action, log.staff_id, log.table_name, log.record_id, log.timestamp);
            });
        } else {
             activityBody.innerHTML = '<tr><td colspan="6">No recent system activity data available.</td></tr>';
        }
        
        // 4. Render Login Logs Table
        if (data.login_logs && Array.isArray(data.login_logs)) {
            data.login_logs.forEach(log => {
                admCreateLoginLogRow(log.username, log.staff_id, log.status, log.reason, log.timestamp);
            });
        } else {
             loginLogsBody.innerHTML = '<tr><td colspan="5">No recent login log data available.</td></tr>';
        }


    } catch (error) {
        console.error('Failed to load dashboard data:', error);
        
        if (loadingIndicator) loadingIndicator.style.display = 'none';
        
        // Display generic error message in the stats grid
        statsGrid.innerHTML = `
            <div class="adm-stat-card" style="border-left-color: red;">
                <p>Could not fetch data from server. (Check server logs/network tab)</p>
            </div>
        `;
    }
}