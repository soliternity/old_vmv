document.addEventListener('DOMContentLoaded', () => {
    const tableHead = document.querySelector('#sl-logs-table thead');
    const tableBody = document.querySelector('#sl-logs-table tbody');
    const searchInput = document.getElementById('sl-search-input');
    const auditBtn = document.getElementById('sl-btn-audit');
    const loginBtn = document.getElementById('sl-btn-login');

    let currentLogType = 'audit_logs';

    const fetchLogs = async (logType, searchQuery = '') => {
        tableBody.innerHTML = '<tr><td colspan="6" class="sl-loading-spinner">Loading...</td></tr>';
        tableHead.innerHTML = '';
        try {
            const url = `../../b/sl/fetchLogs.php?log_type=${logType}&search=${encodeURIComponent(searchQuery)}`;
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const logs = await response.json();
            displayLogs(logs, logType);
        } catch (error) {
            console.error('Error fetching logs:', error);
            tableBody.innerHTML = '<tr><td colspan="6" class="sl-error-message">Failed to load logs.</td></tr>';
        }
    };

    const displayLogs = (logs, logType) => {
        tableHead.innerHTML = '';
        tableBody.innerHTML = '';

        if (logs.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" class="sl-no-results">No logs found.</td></tr>`;
            return;
        }

        let headers = [];
        if (logType === 'audit_logs') {
            headers = ['Title', 'Action', 'Staff ID', 'Table Name', 'Record ID', 'Timestamp'];
        } else if (logType === 'login_logs') {
            headers = ['Username', 'Staff ID', 'Status', 'Reason', 'Timestamp'];
        }

        const headerRow = document.createElement('tr');
        headers.forEach(headerText => {
            const th = document.createElement('th');
            th.textContent = headerText;
            headerRow.appendChild(th);
        });
        tableHead.appendChild(headerRow);

        logs.forEach(log => {
            const tr = document.createElement('tr');

            if (logType === 'audit_logs') {
                tr.innerHTML = `
                    <td>${log.title}</td>
                    <td><span class="sl-log-status sl-status-${log.action_type.toLowerCase()}">${log.action_type}</span></td>
                    <td>${log.staff_id}</td>
                    <td>${log.table_name}</td>
                    <td>${log.record_id}</td>
                    <td>${log.created_at}</td>
                `;
            } else if (logType === 'login_logs') {
                tr.innerHTML = `
                    <td>${log.username}</td>
                    <td>${log.staff_id}</td>
                    <td><span class="sl-log-status sl-status-${log.status.toLowerCase()}">${log.status}</span></td>
                    <td>${log.failure_reason || 'N/A'}</td>
                    <td>${log.login_time}</td>
                `;
            }
            tableBody.appendChild(tr);
        });
    };

    const handleLogTypeChange = (logType) => {
        currentLogType = logType;
        auditBtn.classList.remove('sl-btn-active');
        loginBtn.classList.remove('sl-btn-active');
        if (logType === 'audit_logs') {
            auditBtn.classList.add('sl-btn-active');
        } else {
            loginBtn.classList.add('sl-btn-active');
        }
        searchInput.value = '';
        fetchLogs(currentLogType);
    };

    auditBtn.addEventListener('click', () => handleLogTypeChange('audit_logs'));
    loginBtn.addEventListener('click', () => handleLogTypeChange('login_logs'));

    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetchLogs(currentLogType, searchInput.value);
        }, 500);
    });

    // Initial fetch on page load
    fetchLogs(currentLogType);
});