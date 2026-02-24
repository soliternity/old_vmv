// --- DOM Elements ---
// const container = document.querySelector('.stf-card-container'); // REMOVED
const tableBody = document.getElementById('stf-table-body'); // NEW
const roleFilter = document.getElementById('stf-role-filter'); // NEW
const searchInput = document.getElementById('stf-search-input');
const addStaffBtn = document.getElementById('add-stf-btn');
const addStaffOverlay = document.getElementById('add-stf-overlay');
const addOverlayCloseBtn = document.getElementById('add-overlay-close-btn');
const addStaffForm = document.getElementById('add-stf-form');
const detailsOverlay = document.getElementById('stf-details-overlay');
const detailsOverlayCloseBtn = document.getElementById('details-overlay-close-btn');
const deleteOverlay = document.getElementById('delete-stf-overlay');
const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
const staffToDeleteName = document.getElementById('stf-to-delete-name');
const newRoleSelect = document.getElementById('new-role');
// New elements for role editing
const overlayRoleSelect = document.getElementById('overlay-role-select');
const saveRoleBtn = document.getElementById('save-role-btn');
// End New elements
let staffMemberToDelete = null;
let staffList = [];

// Map for role conversion (assuming HTML select options are capitalized)
const roleMap = {
    "Admin": "admin",
    "Manager": "manager",
    "Mechanic": "mechanic",
    "Cashier": "cashier",
    "admin": "Admin",
    "manager": "Manager",
    "mechanic": "Mechanic",
    "cashier": "Cashier"
};

// --- Global Variable for Logged-in User Data ---
let loggedInUser = null;

// --- Utility Functions ---
function statusClass(status) {
    return status === 'activated' ? 'stf-activated' : 'stf-deactivated';
}
function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
function showOverlay(overlayId) {
    document.getElementById(overlayId).style.display = 'flex';
}
function hideOverlay(overlayId) {
    document.getElementById(overlayId).style.display = 'none';
}
function showErrorMessage(msg) {
    const err = document.getElementById('add-stf-error-message');
    const succ = document.getElementById('add-stf-success-message');
    if (err && addStaffOverlay.style.display === 'flex') {
        err.textContent = msg;
        err.style.display = 'block';
    }
    if (succ) succ.style.display = 'none';
}
function showSuccessMessage(msg) {
    const succ = document.getElementById('add-stf-success-message');
    const err = document.getElementById('add-stf-error-message');
    if (succ && addStaffOverlay.style.display === 'flex') {
        succ.textContent = msg;
        succ.style.display = 'block';
    }
    if (err) err.style.display = 'none';
}
function clearAddStfError() {
    const err = document.getElementById('add-stf-error-message');
    if (err) err.style.display = 'none';
    const succ = document.getElementById('add-stf-success-message');
    if (succ) succ.style.display = 'none';
}

// --- Render Staff Table ---
function renderStaffList(list) {
    if (tableBody) {
        tableBody.innerHTML = list.map(staff => `
            <tr class="stf-table-row ${statusClass(staff.status)}" data-id="${staff.id}">
                <td class="stf-table-cell stf-name-cell">${staff.fname} ${staff.lname}</td>
                <td class="stf-table-cell">${staff.username}</td>
                <td class="stf-table-cell">${staff.email}</td>
                <td class="stf-table-cell">${capitalize(staff.role)}</td>
                <td class="stf-table-cell">
                    <span class="stf-card-status-container">
                        <i class="fas fa-circle stf-status-icon"></i>
                        <span class="stf-card-status">${capitalize(staff.status)}</span>
                    </span>
                </td>
                <td class="stf-table-cell stf-actions-cell">
                    <button class="stf-view-btn" data-id="${staff.id}"><i class="fas fa-eye"></i></button>
                    <button class="delete-stf-btn" data-id="${staff.id}"><i class="fas fa-trash-alt"></i></button>
                </td>
            </tr>
        `).join('');
        // NOTE: We need new listeners for the table view
        addTableRowListeners();
        addDeleteBtnListeners();
    }
}

// --- Fetch Staff List from API ---
async function fetchStaffList(search = "") {
    let url = "../../b/stf/fetchStf.php?is_archived=0";
    if (search) url += "&search=" + encodeURIComponent(search);
    try {
        const res = await fetch(url, { credentials: "include" });
        const data = await res.json();
        if (data.success) {
            staffList = data.data;
            // Set loggedInUser from fetchStf.php if available
            if (data.current_user) {
                loggedInUser = {
                    user_id: data.current_user.id,
                    role: data.current_user.role,
                    username: data.current_user.username
                };
            }
            filterStaffList(); // Use the existing filter function to render the full list initially
        } else {
            tableBody.innerHTML = "<tr><td colspan='6'><p>Failed to load staff.</p></td></tr>";
        }
    } catch (e) {
        tableBody.innerHTML = "<tr><td colspan='6'><p>Error loading staff.</p></td></tr>";
    }
}

// --- Filter Staff List (Modified for Role Filter) ---
function filterStaffList() {
    const searchTerm = searchInput.value.trim().toLowerCase();
    const selectedRole = roleFilter.value; // Get selected role value (e.g., 'admin' or '')
    
    let filteredList = staffList;

    if (selectedRole) {
        filteredList = filteredList.filter(staff => staff.role === selectedRole);
    }

    if (searchTerm) {
        filteredList = filteredList.filter(staff => 
            staff.fname.toLowerCase().includes(searchTerm) || 
            staff.lname.toLowerCase().includes(searchTerm) || 
            staff.username.toLowerCase().includes(searchTerm)
        );
    }
    
    renderStaffList(filteredList);
}

// --- Show Overlays ---
function showDetailsOverlay(staff) {
    document.getElementById('overlay-last-name').textContent = staff.lname;
    document.getElementById('overlay-first-name').textContent = staff.fname;
    document.getElementById('overlay-middle-name').textContent = staff.mname || '';
    document.getElementById('overlay-username').textContent = staff.username;
    document.getElementById('overlay-email').textContent = staff.email;

    // Role editing setup - REPLACES: document.getElementById('overlay-role').textContent = capitalize(staff.role);
    overlayRoleSelect.value = staff.role; // Set current role (e.g., 'admin')
    overlayRoleSelect.dataset.id = staff.id;
    saveRoleBtn.style.display = 'none'; // Hide button initially

    const statusToggle = document.getElementById('overlay-status-toggle');
    statusToggle.checked = staff.status === 'activated';
    statusToggle.dataset.id = staff.id;
    document.getElementById('overlay-status-text').textContent = capitalize(staff.status);

    showOverlay('stf-details-overlay');
}

function showAddStaffOverlay() {
    showOverlay('add-stf-overlay');
}

function showDeleteOverlay(staff) {
    staffMemberToDelete = staff;
    staffToDeleteName.textContent = `${staff.fname} ${staff.lname}`;
    showOverlay('delete-stf-overlay');
}

// --- Table Row Click Listeners (New) ---
function addTableRowListeners() {
    // Listeners for viewing details
    const viewBtns = document.querySelectorAll('.stf-view-btn');
    viewBtns.forEach(btn => {
        btn.addEventListener('click', (event) => {
            event.stopPropagation(); // Prevent row click from firing (though not strictly necessary with dedicated button)
            const id = parseInt(event.currentTarget.dataset.id);
            const staff = staffList.find(s => s.id == id);
            if (staff) showDetailsOverlay(staff);
        });
    });

    // Optional: Add click listener to the entire row to view details (alternative to button)
    const rows = document.querySelectorAll('.stf-table-row');
    rows.forEach(row => {
        row.addEventListener('click', (event) => {
            // Ignore if the click was on the delete or view button (handled separately)
            if (event.target.closest('.delete-stf-btn') || event.target.closest('.stf-view-btn')) return; 

            const id = parseInt(event.currentTarget.dataset.id);
            const staff = staffList.find(s => s.id == id);
            if (staff) showDetailsOverlay(staff);
        });
    });
}

// --- Delete Button Listeners (Modified for Table) ---
function addDeleteBtnListeners() {
    const deleteBtns = document.querySelectorAll('.delete-stf-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', (event) => {
            event.stopPropagation();
            const id = parseInt(event.currentTarget.dataset.id);
            const staff = staffList.find(s => s.id == id);
            if (staff) showDeleteOverlay(staff);
        });
    });
}

// --- Toggle Status (ALERTS REMOVED) ---
document.getElementById('overlay-status-toggle').addEventListener('change', async (event) => {
    const isChecked = event.target.checked;
    const id = parseInt(event.target.dataset.id);
    const staff = staffList.find(s => s.id == id);
    if (!staff || !loggedInUser) return;
    const newStatus = isChecked ? 'activated' : 'deactivated';
    try {
        const res = await fetch("../../b/stf/updStat.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({ id, status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
            staff.status = newStatus;
            document.getElementById('overlay-status-text').textContent = capitalize(newStatus);
            renderStaffList(staffList); // Re-render table
        } else {
            console.error("Failed to update status:", data.message || "Unknown error.");
            event.target.checked = !isChecked; // revert
        }
    } catch (e) {
        console.error("Error updating status:", e);
        event.target.checked = !isChecked;
    }
});

// --- Role Change Logic (NEW BLOCK, ALERTS REMOVED) ---
overlayRoleSelect.addEventListener('change', (event) => {
    const id = parseInt(overlayRoleSelect.dataset.id);
    const staff = staffList.find(s => s.id == id);
    // Show save button only if the new value is different from the original role
    if (staff && event.target.value !== staff.role) {
        saveRoleBtn.style.display = 'inline-block';
    } else {
        saveRoleBtn.style.display = 'none';
    }
});

saveRoleBtn.addEventListener('click', async () => {
    const id = parseInt(overlayRoleSelect.dataset.id);
    const newRole = overlayRoleSelect.value;
    const staff = staffList.find(s => s.id == id);

    if (!staff || !loggedInUser) return;

    try {
        const res = await fetch("../../b/stf/updRole.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({ id, role: newRole })
        });
        const data = await res.json();
        
        if (data.success) {
            staff.role = newRole; // Update local data
            filterStaffList(); // Re-filter and re-render table
            saveRoleBtn.style.display = 'none'; // Hide button after success
            detailsOverlay.style.display = 'none'; // Close overlay            
        } else {
            console.error("Failed to update role:", data.message || "Unknown error.");
        }
    } catch (e) {
        console.error("Error updating role:", e);
    }
});
// --- End Role Change Logic ---

// --- Show/Hide Password ---
const passwordInput = document.getElementById('new-password');
const togglePasswordBtn = document.getElementById('toggle-password-visibility');
const togglePasswordIcon = document.getElementById('toggle-password-icon');
if (togglePasswordBtn && passwordInput && togglePasswordIcon) {
    togglePasswordBtn.addEventListener('click', function () {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            togglePasswordIcon.classList.remove('fa-eye');
            togglePasswordIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            togglePasswordIcon.classList.remove('fa-eye-slash');
            togglePasswordIcon.classList.add('fa-eye');
        }
    });
}

// --- Event Listeners ---
searchInput.addEventListener('input', filterStaffList);
roleFilter.addEventListener('change', filterStaffList); // NEW listener for role filter
detailsOverlayCloseBtn.addEventListener('click', () => hideOverlay('stf-details-overlay'));
addStaffBtn.addEventListener('click', function () {
    clearAddStfError();
    showAddStaffOverlay();
});
addOverlayCloseBtn.addEventListener('click', function () {
    clearAddStfError();
    hideOverlay('add-stf-overlay');
});

// --- Delete Confirmation (ALERTS REMOVED) ---
confirmDeleteBtn.addEventListener('click', async () => {
    if (staffMemberToDelete && loggedInUser) {
        try {
            const res = await fetch("../../b/stf/delStf.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "include",
                body: JSON.stringify({ id: staffMemberToDelete.id })
            });
            const data = await res.json();
            if (data.success) {
                staffList = staffList.filter(s => s.id !== staffMemberToDelete.id);
                filterStaffList(); // Re-filter and re-render table
                hideOverlay('delete-stf-overlay');
            } else {
                console.error("Failed to delete staff:", data.message || "Unknown error.");
            }
        } catch (e) {
            console.error("Error deleting staff:", e);
        }
    }
});
cancelDeleteBtn.addEventListener('click', () => {
    hideOverlay('delete-stf-overlay');
    staffMemberToDelete = null;
});

// --- Add Staff ---
addStaffForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!loggedInUser) {
        showErrorMessage("You must be logged in to add staff.");
        return;
    }

    // Map display role to backend role value using the global roleMap
    const selectedRole = document.getElementById('new-role').value;
    const backendRole = roleMap[selectedRole] || selectedRole.toLowerCase();

    // Get password from form input
    const password = document.getElementById('new-password').value;
    if (!password) {
        showErrorMessage("Password is required.");
        return;
    }

    const payload = {
        fname: document.getElementById('new-first-name').value,
        mname: document.getElementById('new-middle-name').value,
        lname: document.getElementById('new-last-name').value,
        username: document.getElementById('new-username').value,
        email: document.getElementById('new-email').value,
        role: backendRole,
        password: password
    };

    try {
        const res = await fetch("../../b/stf/addStf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            fetchStaffList();
            addStaffForm.reset();
            showSuccessMessage("Staff added successfully.");
            setTimeout(() => {
                hideOverlay('add-stf-overlay');
                clearAddStfError();
            }, 1500);
        } else {
            showErrorMessage(data.message || "Failed to add staff.");
        }
    } catch (e) {
        showErrorMessage("Error adding staff.");
    }
});

// --- Initialization on page load ---
document.addEventListener('DOMContentLoaded', () => {
    fetchStaffList();
});