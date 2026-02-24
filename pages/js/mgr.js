/**
 * ADJUSTED mgr.js
 * Dashboard logic for the Manager View.
 * NOTE: The function initMgrDashboard() MUST be called manually 
 * after the mgr.html content is inserted into the main page's DOM.
 * * Replaced mock data constants with a fetch call to dsh.php.
 */

function initMgrDashboard() {
    // Helper function remains the same to safely query and update an element
    const updateText = (selector, value) => {
        const element = document.querySelector(selector);
        if (element) {
            // Apply number formatting for large values
            const formattedValue = (typeof value === 'number' || /^\d+$/.test(value)) ? new Intl.NumberFormat().format(value) : value;
            element.textContent = formattedValue;
        } else {
            console.error(`Element not found for selector: ${selector}. Cannot update data.`);
        }
    };

    // --- FETCH DATA FROM PHP SCRIPT ---
    fetch('../../b/dsh/manager/dsh2.php')
        .then(response => {
            if (!response.ok) {
                // Handle non-200 responses (e.g., 404, 500)
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error('PHP script returned an error:', data.error);
                return;
            }
            // Extract the fetched data (keys match the $data array in dsh.php)
            const completedInvoices = data.completedInvoices || 0;
            const lastMonthInvoices = data.lastMonthInvoices || 0;
            const ongoingJobs = data.ongoingJobs || 0;
            const pendingJobs = data.pendingJobs || 0;
            const todayAppointments = data.todayAppointments || 0;
            const tomorrowAppointments = data.tomorrowAppointments || 0;
            const thisWeekAppointments = data.thisWeekAppointments || 0;
            const activeChats = data.activeChats || 0;
            const mechanicData = data.mechanicData || [];

            // --- 1. Update Widgets with Fetched Data ---

            // Completed Invoices (This Month)
            updateText('.mgr-completed-invoices .mgr-data-value', completedInvoices);
            const invoicesSubData = document.querySelector('.mgr-completed-invoices .mgr-sub-data');
            if (invoicesSubData) {
                const subDataSpans = invoicesSubData.querySelectorAll('span');
                if(subDataSpans.length >= 1) {
                    subDataSpans[0].textContent = `Last Month: ${lastMonthInvoices}`;
                }
            }
            
            // Pending & Ongoing Job Orders (Total)
            updateText('.mgr-ongoing-jobs .mgr-data-value', ongoingJobs + pendingJobs);
            const jobsSubData = document.querySelector('.mgr-ongoing-jobs .mgr-sub-data');
            if (jobsSubData) {
                const subDataSpans = jobsSubData.querySelectorAll('span');
                subDataSpans[0].textContent = `Pending: ${pendingJobs}`;
                subDataSpans[1].textContent = `Ongoing: ${ongoingJobs}`;
            }

            // Appointments Summary (Total Today)
            updateText('.mgr-appointments-summary .mgr-data-value', todayAppointments);
            const appointmentsSubData = document.querySelector('.mgr-appointments-summary .mgr-sub-data');
            if (appointmentsSubData) {
                const appointmentsSubDataSpans = appointmentsSubData.querySelectorAll('span');
                appointmentsSubDataSpans[0].textContent = `Today: ${todayAppointments}`;
                appointmentsSubDataSpans[1].textContent = `Tomorrow: ${tomorrowAppointments}`;
                appointmentsSubDataSpans[2].textContent = `This Week: ${thisWeekAppointments}`;
            }

            // Key Action Items - Active Chat Conversations
            updateText('.mgr-communication .mgr-data-value', activeChats);

            
            // --- 2. Dynamic List Generation for Mechanic Ranking ---
            const mechanicListElement = document.querySelector('.mgr-mechanic-list');
            
            if (mechanicListElement) {
                mechanicListElement.innerHTML = ''; // Clear the static content

                mechanicData.forEach((mechanic, index) => {
                    const listItem = document.createElement('li');
                    const rank = index + 1;
                    listItem.innerHTML = `<span class="mgr-mechanic-name">${rank}. ${mechanic.name}</span> <span class="mgr-job-count">${mechanic.services} Services</span>`;
                    mechanicListElement.appendChild(listItem);
                });
            }
            
            console.log("Manager Dashboard data loaded and updated successfully.");
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
            // Fallback for UI in case of total failure
            updateText('.mgr-title', 'Error Loading Dashboard Data'); 
        });
}