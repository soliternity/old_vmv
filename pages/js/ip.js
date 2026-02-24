document.addEventListener('DOMContentLoaded', () => {
    // --- 1. STATE & CONSTANTS ---
    let partsData = [];

    // --- 2. DOM ELEMENTS & PHP ENDPOINTS ---
    const ENDPOINTS = {
        fetch: '../../b/p/fetchP.php',
        add: '../../b/p/addP.php',
        update: '../../b/p/updP.php',
        delete: '../../b/p/delP.php'
    };

    const tableBody = document.getElementById('ip-table-body');
    const searchInput = document.getElementById('ip-search-input');
    const navPlaceholder = document.getElementById('ip-nav-placeholder');
    
    // Part Details/Edit Modal Elements
    const detailsOverlay = document.getElementById('ip-details-overlay');
    const detailsOverlayCloseBtn = document.getElementById('details-overlay-close-btn');
    const partForm = document.getElementById('ip-part-form');
    const overlayTitle = document.getElementById('overlay-title');
    const inputId = document.getElementById('overlay-part-id');
    const inputName = document.getElementById('overlay-part-name');
    const inputBrand = document.getElementById('overlay-brand');
    const inputCategory = document.getElementById('overlay-category');
    const inputCost = document.getElementById('overlay-cost');

    // Message Elements
    const detailsMessage = document.getElementById('ip-details-message');
    const deleteMessage = document.getElementById('ip-delete-message');
    
    // Delete Modal Elements
    const deleteOverlay = document.getElementById('delete-ip-overlay');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-cancel-btn'); // Note: This might be a typo in HTML, using 'cancel-delete-btn' based on prior
    const deleteCloseBtn = document.getElementById('delete-overlay-close-btn');
    const partToDeleteNameSpan = document.getElementById('ip-to-delete-name');
    let partIdToDelete = null;

    // Add Button
    const addPartBtn = document.getElementById('add-ip-btn');

    // --- 3. MESSAGE UTILITY FUNCTIONS (NEW) ---

    /**
     * Displays a message in a specified area.
     * @param {HTMLElement} element The paragraph element to update.
     * @param {string} message The message text.
     * @param {string} type 'success' or 'error'.
     */
    function showMessage(element, message, type = 'error') {
        element.textContent = message;
        element.className = `ip-message-area ip-message-${type}`;
        element.style.display = 'block';
        
        // Auto-hide success messages after 3 seconds
        if (type === 'success') {
            setTimeout(() => {
                element.style.display = 'none';
            }, 3000);
        }
    }

    /**
     * Hides the message area.
     * @param {HTMLElement} element The paragraph element to hide.
     */
    function hideMessage(element) {
        element.style.display = 'none';
        element.textContent = '';
    }

    // --- 4. DATA FETCHING AND MANIPULATION ---

    async function fetchParts(searchTerm = '') {
        try {
            const response = await fetch(ENDPOINTS.fetch);
            const result = await response.json();

            if (result.success) {
                partsData = result.data;
                
                let displayData = partsData;
                if (searchTerm) {
                    displayData = partsData.filter(part => {
                        const term = searchTerm.toLowerCase();
                        return (
                            part.name.toLowerCase().includes(term) ||
                            part.brand.toLowerCase().includes(term) ||
                            part.category.toLowerCase().includes(term)
                        );
                    });
                }
                renderTable(displayData);
            } else {
                console.error('Fetch Error:', result.message);
                // No message needed for fetch failure, table remains empty
            }
        } catch (error) {
            console.error('Network or Parsing Error:', error);
        }
    }

    /**
     * Renders the parts array into the HTML table body.
     */
    function renderTable(parts) {
        tableBody.innerHTML = ''; 

        if (parts.length === 0) {
            const noResultsRow = tableBody.insertRow();
            const cell = noResultsRow.insertCell();
            cell.colSpan = 5; 
            cell.textContent = "No parts found.";
            cell.style.textAlign = 'center';
            cell.style.padding = '20px';
            return;
        }

        parts.forEach(part => {
            const row = tableBody.insertRow();
            
            row.insertCell().textContent = part.name;
            row.insertCell().textContent = part.brand;
            row.insertCell().textContent = `₱${part.cost.toFixed(2)}`;
            row.insertCell().textContent = part.category;

            const actionsCell = row.insertCell();
            actionsCell.classList.add('ip-actions-cell');
            
            const editBtn = document.createElement('button');
            editBtn.textContent = 'Edit';
            editBtn.classList.add('ip-edit-btn');
            editBtn.setAttribute('data-id', part.id);
            editBtn.addEventListener('click', () => showEditModal(part.id));
            
            const deleteBtn = document.createElement('button');
            deleteBtn.textContent = 'Delete';
            deleteBtn.classList.add('ip-delete-btn');
            deleteBtn.setAttribute('data-id', part.id);
            deleteBtn.setAttribute('data-name', part.name);
            deleteBtn.addEventListener('click', showDeleteModal);
            
            actionsCell.appendChild(editBtn);
            actionsCell.appendChild(deleteBtn);
        });
    }

    // --- 5. MODAL HANDLERS ---

    function showAddModal() {
        partForm.reset();
        overlayTitle.textContent = 'Add New Part';
        inputId.value = ''; 
        hideMessage(detailsMessage); // Hide message on open
        detailsOverlay.style.display = 'flex';
    }

    function showEditModal(id) {
        const part = partsData.find(p => p.id == id);
        if (!part) return;

        overlayTitle.textContent = `Edit Part: ${part.name}`;
        inputId.value = part.id;
        inputName.value = part.name;
        inputBrand.value = part.brand;
        inputCategory.value = part.category; 
        inputCost.value = part.cost;
        
        hideMessage(detailsMessage); // Hide message on open
        detailsOverlay.style.display = 'flex';
    }
    
    function closeDetailsModal() {
        detailsOverlay.style.display = 'none';
        partForm.reset();
        hideMessage(detailsMessage); // Hide message on close
    }

    detailsOverlayCloseBtn.addEventListener('click', closeDetailsModal);
    
    function showDeleteModal(event) {
        partIdToDelete = parseInt(event.target.getAttribute('data-id'));
        const partName = event.target.getAttribute('data-name');
        
        partToDeleteNameSpan.textContent = partName;
        hideMessage(deleteMessage); // Hide message on open
        deleteOverlay.style.display = 'flex';
    }

    function closeDeleteModal() {
        deleteOverlay.style.display = 'none';
        partIdToDelete = null;
        hideMessage(deleteMessage); // Hide message on close
    }
    
    // Using the ID from the HTML: cancel-delete-btn
    document.getElementById('cancel-delete-btn').addEventListener('click', closeDeleteModal);
    deleteCloseBtn.addEventListener('click', closeDeleteModal);

    // --- 6. SUBMISSION HANDLERS (AJAX) ---

    // Handle Form Submission (Add or Edit)
    partForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideMessage(detailsMessage); // Clear previous message

        const id = inputId.value ? parseInt(inputId.value) : null;
        const name = inputName.value;
        const brand = inputBrand.value;
        const category = inputCategory.value; 
        const cost = parseFloat(inputCost.value);
        
        const data = { id, name, brand, cost, category };
        const endpoint = id ? ENDPOINTS.update : ENDPOINTS.add;
        const action = id ? 'updated' : 'added';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                closeDetailsModal();
                await fetchParts(searchInput.value); // Refresh table
                // Show success message on the main screen (if an element existed) or just rely on visual table change
            } else {
                console.error('Operation Failed:', result.message);
                showMessage(detailsMessage, `Operation failed: ${result.message}`, 'error');
            }
        } catch (error) {
            console.error('Request Error:', error);
            showMessage(detailsMessage, 'A network error occurred. Please check the connection.', 'error');
        }
    });

    // Handle Delete Confirmation
    confirmDeleteBtn.addEventListener('click', async () => {
        if (!partIdToDelete) return;
        hideMessage(deleteMessage); // Clear previous message

        try {
            const response = await fetch(ENDPOINTS.delete, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: partIdToDelete })
            });
            const result = await response.json();

            if (result.success) {
                closeDeleteModal();
                await fetchParts(searchInput.value); // Refresh table
                // Success message visible only via table refresh
            } else {
                console.error('Delete Failed:', result.message);
                showMessage(deleteMessage, `Deletion failed: ${result.message}`, 'error');
            }
        } catch (error) {
            console.error('Request Error:', error);
            showMessage(deleteMessage, 'No Function yet', 'error');
        }
    });

    // --- 7. INITIALIZATION ---
    fetchParts(); // Load initial data
    searchInput.addEventListener('input', () => handleSearch()); // Simplified search handler
    addPartBtn.addEventListener('click', showAddModal);
    
    function handleSearch() {
        fetchParts(searchInput.value); 
    }

    if (navPlaceholder) {
        navPlaceholder.innerHTML = '<i class="fa-solid fa-boxes-stacked"></i> Parts Inventory';
    }
});