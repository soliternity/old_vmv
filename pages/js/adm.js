/**
 * adm.js
 * Admin Dashboard logic.
 * NOTE: The function initAdmDashboard() MUST be called manually 
 * after the adm.html content is inserted into the main page's DOM.
 */

function initAdmDashboard() {
    
    // Call the function to fetch and populate metrics
    fetchAndPopulateMetrics();
    
    /**
     * Fetches metrics from the PHP backend and updates the dashboard widgets.
     */
    function fetchAndPopulateMetrics() {
        // Use the path to your PHP script that outputs the JSON metrics
        const metricsUrl = '../../b/dsh/admin/dsh1.php';

        fetch(metricsUrl)
            .then(response => {
                // Check if the response is successful (status 200-299)
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Log the received data for debugging
                console.log("Metrics received:", data);
                
                // Call the function to update the HTML widgets
                updateDashboardDOM(data);
            })
            .catch(error => {
                console.error("Error fetching admin dashboard metrics:", error);
                // Provide fallback data on failure
                updateDashboardDOM(getFallbackData()); 
            });
    }

    /**
     * Updates the inner HTML of the dashboard widgets with fetched data.
     * @param {object} metrics - The JSON object containing the dashboard metrics.
     */
    function updateDashboardDOM(metrics) {
        // --- Workflow & Operations Snapshot ---

        // Job Order Count
        document.querySelector('.adm-job-orders .adm-data-value').textContent = metrics.job_order_count.toLocaleString();
        
        // Job Order Sub-Data
        document.querySelector('.adm-job-orders .adm-sub-data').innerHTML = 
            `<span>In Progress: ${metrics.in_progress_jobs.toLocaleString()}</span> | <span>Completed (Today): ${metrics.completed_jobs_today.toLocaleString()}</span>`;

        // Transactions Count Today
        document.querySelector('.adm-transactions-today .adm-data-value').textContent = metrics.transactions_count_today.toLocaleString();

        // Appointments (This Month)
        document.querySelector('.adm-appointments .adm-data-value').textContent = metrics.appointments_this_month.toLocaleString();

        // --- System & Staff / Communication & Security ---
        
        // System Logs (24h)
        document.querySelector('.adm-system-logs .adm-data-value').textContent = metrics.system_logs_24h.toLocaleString();

        // VMV Users Count
        document.querySelector('.adm-vmv-users .adm-data-value').textContent = metrics.vmv_users_count.toLocaleString();

        // Active Conversations
        document.querySelector('.adm-active-chats .adm-data-value').textContent = metrics.active_conversations.toLocaleString();
    }
    
    /**
     * Provides mock/fallback data in case the fetch fails.
     */
    function getFallbackData() {
        return {
            job_order_count: 0,
            in_progress_jobs: 0,
            completed_jobs_today: 0,
            transactions_count_today: 0,
            appointments_this_month: 0,
            system_logs_24h: 'Error',
            vmv_users_count: 'Error',
            active_conversations: 0
        };
    }
}