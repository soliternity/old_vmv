// --- GLOBAL DATA HOLDERS ---
// These variables will be populated dynamically from the server via dsh.php
let vehiclesData = [];
let serviceInfoData = [];
let partsInfoData = [];
let chatsData = [];

// --- ASYNCHRONOUS DATA FETCHING ---

/**
 * Fetches dashboard data from the PHP endpoint and populates the global variables.
 * RENAMED to fetchMchData to prevent global collision with other dashboard scripts (e.g., csh.js).
 */
async function fetchMchData() {
    try {
        // CORRECTED PATH: Changed './dsh.php' to the consistent path '../../b/dsh/mechanic/dsh.php'
        const response = await fetch('../../b/dsh/mechanic/dsh3.php'); 

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        // Populate global data holders with fetched data
        vehiclesData = data.mockVehicles || [];
        serviceInfoData = data.mockServiceInfo || [];
        partsInfoData = data.mockPartsInfo || [];
        chatsData = data.mockChats || [];

        // Once data is fetched, render the dashboard components
        renderJobStats(); 
        renderJobList();
        renderChatList();
        setupSearchFilters();

    } catch (error) {
        console.error("Could not fetch dashboard data:", error);
        // Display an error message to the user if data loading fails
        const jobListElement = document.getElementById('prioritizedJobList');
        if (jobListElement) {
            jobListElement.innerHTML = '<li style="padding: 15px; color: #FF4E1F;">Error loading data from server.</li>';
        }
    }
}


// --- CORE FUNCTIONS (ADJUSTED TO USE GLOBAL DATA) ---

/**
 * Renders the job list items into the HTML, showing Car Info and Service Count.
 */
function renderJobList() {
    const jobListElement = document.getElementById('prioritizedJobList');
    if (!jobListElement) return;

    // Filter to only show cars that have at least one job In Progress or Queued (not all completed)
    const prioritizedVehicles = vehiclesData.filter(vehicle => 
        vehicle.services.some(s => s.status !== 'Completed')
    );

    jobListElement.innerHTML = prioritizedVehicles.map(vehicle => {
        const totalServices = vehicle.services.length;
        const carInfo = `${vehicle.carBrand} (${vehicle.plate})`;
        
        // Use high priority if any service is high priority for visual cue
        const isHighPriority = vehicle.services.some(s => s.priority === 'high');
        const priorityClass = isHighPriority ? 'mch-priority-high' : 'mch-priority-medium';

        return `
            <li class="mch-job-item ${priorityClass}" onclick="console.log('Viewing all ${totalServices} services for ${carInfo}')">
                <span class="mch-car-info">${carInfo}</span>
                <span class="mch-service-count">${totalServices} Services</span>
            </li>
        `;
    }).join('');
}

/**
 * Renders the chat list, limited to 3 and updates the total count using the full array.
 */
function renderChatList() {
    const chatListElement = document.getElementById('chatList');
    const totalChatCountElement = document.getElementById('totalChatCount'); 
    
    if (!chatListElement || !totalChatCountElement) return;

    // Limit chats to the first 3 (which are the most recent due to PHP ordering)
    const recentChats = chatsData.slice(0, 3);

    chatListElement.innerHTML = recentChats.map(chat => {
        return `
            <li class="mch-chat-item" onclick="console.log('Open chat with ${chat.name}')">
                <span class="mch-chat-name">${chat.name}</span>
            </li>
        `;
    }).join('');

    // Update the total count using the full fetched array length
    totalChatCountElement.textContent = chatsData.length; 
}

/**
 * Calculates and renders the job count and progress bar statistics.
 * Counts all assigned services (ongoing, queued, completed) for the assigned mechanic.
 */
function renderJobStats() {
    const jobCountElements = document.querySelectorAll('#jobCountValue');
    const summaryBarElement = document.getElementById('jobSummaryBar');
    if (jobCountElements.length === 0 || !summaryBarElement) return;

    // Flatten services list from all vehicles for statistics
    let allServices = [];
    vehiclesData.forEach(vehicle => {
        allServices = allServices.concat(vehicle.services);
    });

    const totalServices = allServices.length;
    // Count individual services by status
    const completedServices = allServices.filter(service => service.status === 'Completed').length;
    // Ongoing is everything that isn't completed (i.e., 'In Progress' and 'Queued')
    const ongoingServices = allServices.filter(service => service.status !== 'Completed').length;

    // Calculate percentages
    const completedPercent = (completedServices / totalServices) * 100;
    const ongoingPercent = (ongoingServices / totalServices) * 100;

    // Update count for Total Services Assigned
    jobCountElements.forEach(el => el.textContent = totalServices);

    // Update progress bar
    summaryBarElement.innerHTML = `
        <div class="mch-bar-segment mch-in-progress" style="width: ${ongoingPercent.toFixed(0)}%;" title="Ongoing/Pending: ${ongoingServices}"></div>
        <div class="mch-bar-segment mch-completed" style="width: ${completedPercent.toFixed(0)}%;" title="Completed: ${completedServices}"></div>
    `;
}

/**
 * Displays detailed information about a clicked service or part item in a visible popup.
 * @param {string} itemName The name of the item clicked.
 * @param {string} itemType 'service' or 'part'.
 */
function displayDetailPopup(itemName, itemType) {
    const detailView = document.getElementById('mchDetailView');
    const detailTitle = document.getElementById('detailTitle');
    const detailContent = document.getElementById('detailContent');
    let itemData;
    let contentHTML = '';

    if (!detailView || !detailTitle || !detailContent) return;

    if (itemType === 'service') {
        itemData = serviceInfoData.find(item => item.name === itemName);
        if (itemData) {
            detailTitle.innerHTML = `<i class="fas fa-wrench"></i> ${itemData.name}`;
            contentHTML = `
                <p><strong>Hour Range:</strong> ${itemData.hourRange}</p>
                <p><strong>Cost Range:</strong> ${itemData.costRange}</p>
                <p><strong>Description:</strong> ${itemData.description}</p>
            `;
        }
    } else if (itemType === 'part') {
        itemData = partsInfoData.find(item => item.name === itemName);
        if (itemData) {
            detailTitle.innerHTML = `<i class="fas fa-car-battery"></i> ${itemData.name}`;
            contentHTML = `
                <p><strong>Cost:</strong> ${itemData.cost}</p>

            `;
        }
    }
    
    if (contentHTML) {
        detailContent.innerHTML = contentHTML;
        detailView.classList.remove('mch-hidden');
    }
}


/**
 * Handles the live filtering logic for services and parts.
 */
function liveFilter(inputElement, resultsContainer, data, type) {
    const query = inputElement.value.toLowerCase().trim();
    const dataType = type.toLowerCase(); 

    if (query.length < 2) {
        resultsContainer.innerHTML = '';
        return;
    }

    const filteredResults = data.filter(item => 
        item.name.toLowerCase().includes(query)
    ).slice(0, 5); 

    resultsContainer.innerHTML = filteredResults.map(item => `
        <li class="mch-result-item" onclick="displayDetailPopup('${item.name.replace(/'/g, "\\'")}', '${dataType}')">
            ${item.name}
        </li>
    `).join('');

    if (filteredResults.length === 0) {
         resultsContainer.innerHTML = `<li class="mch-result-item mch-no-results">No results found for "${query}"</li>`;
    }
}


/**
 * Sets up live search event listeners for Service and Parts filters.
 */
function setupSearchFilters() {
    const serviceSearchInput = document.getElementById('serviceSearchInput');
    const serviceSearchResults = document.getElementById('serviceSearchResults');
    
    const partsSearchInput = document.getElementById('partsSearchInput');
    const partsSearchResults = document.getElementById('partsSearchResults');

    if (serviceSearchInput && serviceSearchResults) {
        serviceSearchInput.addEventListener('input', () => {
            liveFilter(serviceSearchInput, serviceSearchResults, serviceInfoData, 'Service');
        });
    }

    if (partsSearchInput && partsSearchResults) {
        partsSearchInput.addEventListener('input', () => {
            liveFilter(partsSearchInput, partsSearchResults, partsInfoData, 'Part');
        });
    }

    const closeBtn = document.querySelector('.mch-detail-close');
    if (closeBtn) {
        closeBtn.onclick = () => document.getElementById('mchDetailView').classList.add('mch-hidden');
    }
}


// --- INITIALIZATION ---
function initMchDashboard() {
    // UPDATED CALL: Call the role-specific function
    fetchMchData();
}