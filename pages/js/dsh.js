function fetchDashboardContent() {
    const mainContent = document.getElementById('dsh-main-content');
    const loadingContainer = document.getElementById('dsh-loading-container');
    
    if (loadingContainer) loadingContainer.style.display = 'flex';
    if (mainContent) mainContent.innerHTML = ''; 

    fetch('../../b/dsh.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.text();
        })
        .then(htmlContent => {
            if (mainContent) {
                mainContent.innerHTML = htmlContent;
                console.log('Dashboard content loaded from PHP backend.');

                if (htmlContent.includes('adm-view') && typeof initAdmDashboard === 'function') {
                    console.log('Admin view loaded. Initializing dashboard data.');
                    initAdmDashboard();
                } else if (htmlContent.includes('mgr-view') && typeof initMgrDashboard === 'function') {
                    console.log('Manager view loaded. Initializing dashboard data.');
                    initMgrDashboard();
                } else if (htmlContent.includes('mch-view') && typeof initMchDashboard === 'function') {
                    console.log('Mechanic view loaded. Initializing dashboard data.');
                    initMchDashboard(); 
                } else if (htmlContent.includes('csh-view') && typeof initCshDashboard === 'function') {
                    console.log('Cashier view loaded. Initializing dashboard data.');
                    initCshDashboard(); 
                }
            }

            if (loadingContainer) loadingContainer.style.display = 'none';
            const dshContainer = document.getElementById('dsh-container');
            if (dshContainer) dshContainer.style.display = 'block';

        })
        .catch(error => {
            console.error('Error fetching dashboard content:', error);
            if (loadingContainer) loadingContainer.style.display = 'none';
            if (mainContent) {
                mainContent.innerHTML = '<p class="error-message">Failed to load dashboard. Please check the network connection and server response.</p>';
            }
        });
}

function setSessionRole(role) {
    fetch(`../../b/dsh/set-role.php?role=${role}`)
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to set session role.');
        }
        return response.json();
    })
    .then(data => {
        console.log('Session role set to:', data.newRole);
        const roleDisplay = document.getElementById('dsh-current-role');
        if (roleDisplay) {
            roleDisplay.textContent = `Current Role: ${data.newRole.toUpperCase()}`;
        }
        fetchDashboardContent();
    })
    .catch(error => {
        console.error('Error setting session role:', error);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('Frontend shell loaded. Initializing dashboard...');
    
    fetchDashboardContent();
});