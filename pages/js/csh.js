/**
 * csh.js - Script to fetch and inject live data into the Cashier Dashboard (csh.html)
 *
 * NOTE: The function initCshDashboard() MUST be called manually 
 * after the csh.html content is inserted into the main page's DOM by dsh.js.
 * This script now fetches ALL data from the dsh.php backend in one call.
 */

// Global data object for live data
let liveData = {
    // Summary Data
    billingToProcess: 0,
    appointmentsToday: 0,
    jobOrders: { total: 0, pending: 0, ongoing: 0, completed: 0 },
    // Search Data
    services: [],
    parts: []
};

// ----------------------------------------------------
// 1. WIDGET & UI UTILITY FUNCTIONS
// ----------------------------------------------------

/**
 * Finds a selector within a specific widget and updates its text content.
 */
function updateWidget(widgetClass, dataClass, value) {
    const widget = document.querySelector(widgetClass);
    if (widget) {
        const element = widget.querySelector(dataClass);
        if (element) {
            element.textContent = (typeof value === 'number') ? value.toLocaleString() : value;
            return;
        }
    }
}

/**
 * Updates all CSH dashboard widgets with the fetched data.
 */
function updateCshWidgets(data) {
    // A. Pending Billing to Process
    updateWidget(
        '.csh-billing-pending', 
        '.csh-data-value', 
        data.billingToProcess
    );

    // B. Appointments Today
    updateWidget(
        '.csh-appointments', 
        '.csh-data-value', 
        data.appointmentsToday
    );

    // C. Ongoing Job Orders (Total and Sub-data)
    updateWidget(
        '.csh-job-orders', 
        '.csh-data-value', 
        data.jobOrders.total
    );

    const jobOrderSubData = document.querySelector('.csh-job-orders .csh-sub-data');
    if (jobOrderSubData) {
        jobOrderSubData.innerHTML = `
            <span>Pending: ${data.jobOrders.pending}</span> | 
            <span>Ongoing: ${data.jobOrders.ongoing}</span> | 
            <span>Completed: ${data.jobOrders.completed}</span>
        `;
    }
}

// Function to format currency
function formatCurrency(amount) {
    return '₱' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}


// ----------------------------------------------------
// 2. DATA FETCH FUNCTION (Consolidated)
// ----------------------------------------------------

/**
 * Fetches ALL dashboard data from the dsh.php script.
 * RENAMED to fetchCshData to prevent global collision with other dashboard scripts (e.g., mch.js).
 */
async function fetchCshData() {
    try {
        const response = await fetch('../../b/dsh/cashier/dsh4.php');

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText.substring(0, 100)}`);
        }

        const data = await response.json();
        
        if (data.error) {
            console.error("Backend reported database error:", data.error);
        }

        // 1. Merge all fetched data into liveData
        Object.assign(liveData, data);
        
        // 2. Update Widgets with Summary Data
        updateCshWidgets(liveData);

        // 3. Initialize Search Panels with placeholder message (initial state)
        renderSearchResults([], 'services', false, true); 
        renderSearchResults([], 'parts', false, true); 

    } catch (error) {
        console.error("Failed to fetch dashboard data:", error);
        
        // Fallback for UI elements if fetch fails
        updateWidget('.csh-billing-pending', '.csh-data-value', 'ERR');
        updateWidget('.csh-appointments', '.csh-data-value', 'ERR');
        updateWidget('.csh-job-orders', '.csh-data-value', 'ERR');
        const jobOrderSubData = document.querySelector('.csh-job-orders .csh-sub-data');
        if (jobOrderSubData) {
             jobOrderSubData.innerHTML = `<span>Data Load Failed</span>`;
        }
        
        // Update search panels with error messages
        const serviceContainer = document.getElementById('servicesResultsContainer');
        const partsContainer = document.getElementById('partsResultsContainer');
        if(serviceContainer) serviceContainer.innerHTML = '<p class="csh-no-results">Error loading services data.</p>';
        if(partsContainer) partsContainer.innerHTML = '<p class="csh-no-results">Error loading parts data.</p>';
    }
}

// ----------------------------------------------------
// 3. INVENTORY SEARCH IMPLEMENTATION
// ----------------------------------------------------
    
/**
 * Executes the search against liveData and calls the render function.
 */
function handleSearch(query, type) {
    const lowerQuery = query.toLowerCase().trim();
    let results = [];

    const dataArray = liveData[type] || [];

    if (lowerQuery.length === 0) {
        // If query is empty, render the initial placeholder message
        renderSearchResults([], type, false, true); 
        return;
    }

    if (type === 'services') {
        // NOTE: Filtering still checks ID and Category for a complete search experience
        results = dataArray.filter(service => 
            service.name.toLowerCase().includes(lowerQuery) ||
            service.id.toLowerCase().includes(lowerQuery) ||
            service.category.toLowerCase().includes(lowerQuery)
        );
    } else if (type === 'parts') {
        // NOTE: Filtering still checks SKU and Location
        results = dataArray.filter(part => 
            part.name.toLowerCase().includes(lowerQuery) ||
            part.sku.toLowerCase().includes(lowerQuery) ||
            part.location.toLowerCase().includes(lowerQuery)
        );
    }

    // If query is NOT empty, render the results (active search)
    renderSearchResults(results, type, true, false); 
}

/**
 * Renders the filtered services or parts into the respective HTML containers.
 * @param {Array} results - The filtered data array.
 * @param {string} type - 'services' or 'parts'.
 * @param {boolean} isSearchActive - True if a query was entered.
 * @param {boolean} isInitial - True if the query is empty (initial state).
 */
function renderSearchResults(results, type, isSearchActive = false, isInitial = false) {
    const container = document.getElementById(type === 'services' ? 'servicesResultsContainer' : 'partsResultsContainer');
    if (!container) return;

    let htmlContent = '';

    if (isInitial) {
         // Show placeholder message when the search box is empty
         htmlContent = '<p class="csh-initial-msg">Type a search term to find a service or part.</p>';
    } else if (isSearchActive && results.length === 0) {
        // Show no results message when a search is active but finds nothing
        htmlContent = '<p class="csh-no-results">No results found matching your search.</p>';
    } else if (type === 'services') {
        // RENDER: Service name only (ID/Cost removed per user request)
        htmlContent = `
            <ul id="serviceList">
                ${results.map(service => `
                    <li data-id="${service.id}" onclick="displayDetails('${service.id}', 'services')" class="csh-result-item">
                        <span class="csh-item-name" title="Category: ${service.category}">${service.name}</span>
                    </li>
                `).join('')}
            </ul>
        `;
    } else if (type === 'parts') {
        // RENDER: Part name only (SKU/Stock removed per user request)
        htmlContent = `
            <table class="csh-table">
                <thead>
                    <tr>
                        <th>Name</th>
                    </tr>
                </thead>
                <tbody id="partsTableBody">
                    ${results.map(part => `
                        <tr data-id="${part.sku}" onclick="displayDetails('${part.sku}', 'parts')" class="csh-result-item-row">
                            <td>${part.name}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }
    
    container.innerHTML = htmlContent;
}

// ----------------------------------------------------
// 4. DETAIL DISPLAY FUNCTION
// ----------------------------------------------------

/**
 * Closes the overlay modal.
 */
window.closeCshModal = function() {
    const modal = document.getElementById('cshOverlayModal');
    if (modal) {
        modal.style.display = 'none';
    }
    document.querySelectorAll('.csh-active-selection').forEach(item => item.classList.remove('csh-active-selection'));
}

/**
 * Displays detailed information for a clicked service or part in an overlay modal.
 */
window.displayDetails = function(id, type) {
    const modal = document.getElementById('cshOverlayModal');
    const modalBody = document.getElementById('cshModalBody');
    if (!modal || !modalBody) return;

    document.querySelectorAll('.csh-result-item, .csh-result-item-row').forEach(item => item.classList.remove('csh-active-selection'));
    const selectedItem = document.querySelector(`[data-id="${id}"]`);
    if (selectedItem) selectedItem.classList.add('csh-active-selection');
    
    let item;
    let htmlDetail = '';

    if (type === 'services') {
        item = liveData.services.find(s => s.id === id);
        if (item) {
            htmlDetail = `
                <h4 class="csh-detail-title">${item.name} (${item.id})</h4>
                <div class="csh-detail-grid">
                    <div>
                        <span class="csh-detail-label">Cost Range:</span>
                        <span class="csh-detail-value">${item.costRange}</span>
                    </div>
                    <div>
                        <span class="csh-detail-label">Hour Range:</span>
                        <span class="csh-detail-value">${item.hourRange}</span>
                    </div>
                </div>
                <p><strong>Description:</strong></p>
                <p class="csh-detail-description">${item.description}</p>
            `;
        }
    } else if (type === 'parts') {
        item = liveData.parts.find(p => p.sku === id);
        if (item) {
            htmlDetail = `
                <h4 class="csh-detail-title">${item.name} (${item.sku})</h4>
                <div class="csh-detail-grid">
                    <div>
                        <span class="csh-detail-label">Unit Cost:</span>
                        <span class="csh-detail-value">${formatCurrency(item.unitPrice)}</span>
                    </div>
                    <div>
                        <span class="csh-detail-label">Category:</span>
                        <span class="csh-detail-value">${item.category}</span>
                    </div>
                </div>
                `;
        }
    }
    
    modalBody.innerHTML = htmlDetail;
    modal.style.display = 'flex';
}

// ----------------------------------------------------
// 5. INITIALIZATION
// ----------------------------------------------------

/**
 * Main initialization function called after HTML is loaded.
 */
window.initCshDashboard = function() {
    console.log(`Cashier Dashboard initialization started.`);
    
    // 1. Select search elements INSIDE the initialization function to ensure DOM readiness
    const serviceSearchInput = document.getElementById('serviceSearchInput');
    const partsSearchInput = document.getElementById('partsSearchInput');

    // 2. Attach Event Listeners
    if (serviceSearchInput) {
        const serviceSearch = () => handleSearch(serviceSearchInput.value, 'services');
        serviceSearchInput.addEventListener('input', serviceSearch);
        serviceSearchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault(); 
                serviceSearch();
            }
        });
    }

    if (partsSearchInput) {
        const partsSearch = () => handleSearch(partsSearchInput.value, 'parts');
        partsSearchInput.addEventListener('input', partsSearch);
        partsSearchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault(); 
                partsSearch();
            }
        });
    }

    // 3. Call the single fetch function
    fetchCshData(); // UPDATED CALL: Call the role-specific function

    console.log(`Cashier Dashboard data fetch triggered and listeners attached.`);
}