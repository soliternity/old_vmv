// myjb.js

const joListContainer = document.getElementById('jo-list');
const searchInput = document.getElementById('mj-search-input');

const currentUserId = 1;

let availableParts = [];

const fetchParts = async () => {
    try {
        const response = await fetch('../../b/mj/fetchP.php'); 
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const result = await response.json();
        
        if (result.status === 'success') {
            availableParts = result.data.map(part => ({
                id: part.id,
                name: part.name,
                brand: part.brand, // Ensure brand is included
                cost: parseFloat(part.cost)
            }));
            console.log('Parts data loaded:', availableParts.length, 'parts found.');
        } else {
            console.error('Failed to fetch parts:', result.message);
        }
    } catch (error) {
        console.error('There was an error fetching the parts data:', error);
    }
};

const fetchJobs = async(searchTerm = '') => {
    try {
        const response = await fetch(`../../b/mj/fetchMJ.php?search=${searchTerm}`);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const result = await response.json();
        if (result.status === 'success') {
            renderJobs(result.data);
        } else {
            console.error('Failed to fetch jobs:', result.message);
            joListContainer.innerHTML = '<p class="mj-no-results">Failed to load jobs.</p>';
        }
    } catch (error) {
        console.error('There was an error fetching the job data:', error);
        joListContainer.innerHTML = '<p class="mj-no-results">Error loading jobs. Please try again later.</p>';
    }
};

const createJobCard = (job) => {
    const servicesCount = job.services.length;
    const vehicleDetails = `${job.vehicle.brand} ${job.vehicle.model || ''}, ${job.vehicle.color} (${job.vehicle.plate})`;

    return `
        <div class="jo-card" data-job-id="${job.id}">
            <div class="jo-card-details">
                <h3 class="jo-id">Job Order #${job.id}</h3>
                <p><strong>Customer:</strong> ${job.customer}</p>
                <p><strong>Vehicle:</strong> ${vehicleDetails}</p>
                <p><strong>Services:</strong> ${servicesCount} Services</p>
            </div>
            <div class="jo-card-actions">
                <span class="jo-status-tag jo-status-${job.status}">${job.status}</span>
                <button class="jo-view-btn">View Job</button>
            </div>
        </div>
    `;
};

const renderJobs = (jobArray) => {
    joListContainer.innerHTML = ''; 
    if (jobArray.length === 0) {
        joListContainer.innerHTML = '<p class="mj-no-results">No job orders found.</p>';
        return;
    }
    const jobCards = jobArray.map(createJobCard).join('');
    joListContainer.innerHTML = jobCards;
};

joListContainer.addEventListener('click', async(e) => {
    const viewBtn = e.target.closest('.jo-view-btn');
    if (viewBtn) {
        const jobCard = e.target.closest('.jo-card');
        const jobId = jobCard.dataset.jobId;
        const response = await fetch(`../../b/mj/fetchMJ.php?job_id=${jobId}`); 
        const result = await response.json();
        if (result.status === 'success' && result.data.length > 0) {
            displayJobDetails(result.data[0]);
        } else {
            console.error('Job details not found or failed to fetch.');
            alert('Could not load job details.');
        }
    }
});

const displayJobDetails = (job) => {
    let servicesHtml = job.services.map(service => {
        let button = '';
        let partsHtml = '';
        let startedByHtml = '';
        let completedByHtml = '';

        if (service.started_by) {
            const isCurrentUserStarter = service.started_by_id === currentUserId;
            const startedByName = isCurrentUserStarter ? `<strong>${service.started_by}</strong>` : service.started_by;
            startedByHtml = `<p class="jo-staff-info">Started by: ${startedByName}</p>`;
        }

        if (service.status === 'pending') {
            button = `<button class="jo-service-btn jo-start-btn" data-service-name="${service.name}">Start</button>`;
        } else if (service.status === 'ongoing') {
            button = `<button class="jo-service-btn jo-finish-btn" data-service-name="${service.name}">Finish</button>`;
        } else if (service.status === 'completed') {
            button = `<span class="jo-service-completed-text">Completed in ${service.hours} hours</span>`;
            
            // NOTE: The PHP logic does not return parts data in fetchMJ.php yet,
            // but this block is left for future compatibility to display parts.
            // If service.parts exists in the future, display logic goes here.

            if (service.completed_by) {
                const isCurrentUserCompleter = service.completed_by_id === currentUserId;
                const completedByName = isCurrentUserCompleter ? `<strong>${service.completed_by}</strong>` : service.completed_by;
                completedByHtml = `<p class="jo-staff-info">Completed by: ${completedByName}</p>`;
            }
        }

        return `
            <li>
                <div class="jo-service-item">
                    <span>${service.name}</span>
                    ${button}
                </div>
                ${startedByHtml}
                ${completedByHtml}
                ${partsHtml}
            </li>
        `;
    }).join('');

    let detailsHtml = `
        <div class="jo-details-modal">
            <span class="jo-modal-close">&times;</span>
            <h2>Job Details: ${job.id}</h2>
            <p><strong>Status:</strong> <span class="jo-status-tag jo-status-${job.status}">${job.status}</span></p>
            <p><strong>Date Created:</strong> ${job.dateCreated}</p>
            <hr>
            <h3>Customer Details</h3>
            <p><strong>Name:</strong> ${job.customer}</p>
            <p><strong>Vehicle:</strong> ${job.vehicle.brand} ${job.vehicle.model || ''}, ${job.vehicle.color} (${job.vehicle.plate})</p>
            <hr>
            <h3>Services</h3>
            <ul class="jo-service-list">
                ${servicesHtml}
            </ul>
        </div>
    `;

    const modalContainer = document.createElement('div');
    modalContainer.className = 'mj-modal-overlay';
    modalContainer.innerHTML = detailsHtml;
    document.body.appendChild(modalContainer);

    modalContainer.querySelector('.jo-modal-close').addEventListener('click', () => {
        modalContainer.remove();
    });

    modalContainer.querySelector('.jo-service-list').addEventListener('click', (e) => {
        const startBtn = e.target.closest('.jo-start-btn');
        const finishBtn = e.target.closest('.jo-finish-btn');
        const serviceName = e.target.dataset.serviceName;

        if (startBtn) {
            handleStartService(job.id, serviceName);
            modalContainer.remove();
        } else if (finishBtn) {
            showPartsHoursModal(job.id, serviceName);
            modalContainer.remove();
        }
    });
};

const handleStartService = async(jobId, serviceName) => {
    try {
        const response = await fetch('../../b/mj/startMJ.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                job_id: jobId,
                service_name: serviceName
            }),
        });
        const result = await response.json();
        if (result.status === 'success') {
            await fetchJobs();
        } else {
            console.error('Failed to start service:', result.message);
            alert('Failed to start service. Please try again.');
        }
    } catch (error) {
        console.error('Error starting service:', error);
        alert('An error occurred. Please check your connection.');
    }
};

const showPartsHoursModal = (jobId, serviceName) => {
    let selectedParts = [];
    
    if (availableParts.length === 0) {
        alert("Parts data is still loading or failed to load. Please wait a moment and try again.");
        return; 
    }

    const partsModalHtml = `
        <div class="jo-details-modal jo-parts-hours-modal">
            <span class="jo-modal-close">&times;</span>
            <h2>Complete Service: ${serviceName}</h2>

            <div class="jo-hours-section">
                <label for="jo-hours-input">Hours Taken (Required)</label>
                <input type="number" id="jo-hours-input" step="0.1" min="0" placeholder="Enter hours..." value="0.5">
            </div>
            
            <hr>
            <h3>Parts Used (Optional)</h3>

            <div class="jo-part-select-container">
                <input type="text" id="jo-part-search" placeholder="Search and add parts...">
                <ul id="jo-part-dropdown" class="jo-part-dropdown-list">
                    </ul>
            </div>

            <div id="jo-selected-parts-list">
                </div>
            
            <button id="jo-parts-submit-btn">Continue to Confirmation</button>
        </div>
    `;

    const modalContainer = document.createElement('div');
    modalContainer.className = 'mj-modal-overlay';
    modalContainer.innerHTML = partsModalHtml;
    document.body.appendChild(modalContainer);

    const hoursInput = modalContainer.querySelector('#jo-hours-input');
    // REMOVED: notesInput definition
    const submitBtn = modalContainer.querySelector('#jo-parts-submit-btn');
    const searchInput = modalContainer.querySelector('#jo-part-search');
    const dropdownList = modalContainer.querySelector('#jo-part-dropdown');
    const selectedPartsList = modalContainer.querySelector('#jo-selected-parts-list');

    modalContainer.querySelector('.jo-modal-close').addEventListener('click', () => {
        modalContainer.remove();
    });

    // --- Part Management Functions ---

    const renderDropdown = (term = '') => {
        dropdownList.innerHTML = '';
        const lowerCaseTerm = term.toLowerCase();
        
        const filteredParts = availableParts.filter(part => 
            (part.name.toLowerCase().includes(lowerCaseTerm) || 
             (part.brand && part.brand.toLowerCase().includes(lowerCaseTerm))) && 
            !selectedParts.some(p => p.id == part.id) 
        );

        if (filteredParts.length === 0) {
            dropdownList.style.display = 'none';
            return;
        }

        filteredParts.forEach(part => {
            const listItem = document.createElement('li');
            listItem.textContent = `${part.name}${part.brand ? ` (${part.brand})` : ''} (Unit Cost: ₱${part.cost.toFixed(2)})`;
            listItem.dataset.partId = part.id;
            listItem.addEventListener('click', () => addPart(part)); 
            dropdownList.appendChild(listItem);
        });

        dropdownList.style.display = 'block';
    };

    const renderSelectedParts = () => {
        selectedPartsList.innerHTML = selectedParts.map(part => {
            const subtotal = (part.quantity * part.cost).toFixed(2);
            return `
                <div class="jo-selected-part-item" data-part-id="${part.id}">
                    <span class="jo-part-name">${part.name}${part.brand ? ` (${part.brand})` : ''}</span>
                    <div class="jo-part-inputs">
                        <label>Qty:</label>
                        <input type="number" class="jo-part-quantity" value="${part.quantity}" min="1" step="1" data-part-id="${part.id}">
                        <label>Unit Cost:</label>
                        <input type="text" class="jo-part-cost" value="₱${part.cost.toFixed(2)}" disabled data-part-id="${part.id}">
                        <span class="jo-part-subtotal">Subtotal: ₱${subtotal}</span>
                    </div>
                    <button class="jo-remove-part-btn" data-part-id="${part.id}">&times;</button>
                </div>
            `;
        }).join('');
    };

    const addPart = (part) => {
        selectedParts.push({ 
            id: part.id, 
            name: part.name, 
            brand: part.brand, // Ensure brand is transferred
            cost: part.cost, 
            quantity: 1 
        });
        searchInput.value = '';
        dropdownList.style.display = 'none';
        renderSelectedParts();
        renderDropdown(); 
    };

    const removePart = (partId) => {
        selectedParts = selectedParts.filter(part => part.id != partId); 
        renderSelectedParts();
        renderDropdown(searchInput.value);
    };

    const updatePartValue = (partId, value) => {
        const part = selectedParts.find(p => p.id == partId);
        if (part) {
            let newQuantity = parseInt(value);
            if (isNaN(newQuantity) || newQuantity < 1) {
                newQuantity = 1;
            }
            part.quantity = newQuantity;
            renderSelectedParts(); 
        }
    };

    // --- Event Listeners ---

    searchInput.addEventListener('input', (e) => renderDropdown(e.target.value));
    searchInput.addEventListener('focus', (e) => renderDropdown(e.target.value));
    
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !dropdownList.contains(e.target) && modalContainer.contains(e.target)) {
            dropdownList.style.display = 'none';
        }
    });

    selectedPartsList.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.jo-remove-part-btn');
        if (removeBtn) {
            const partId = parseInt(removeBtn.dataset.partId);
            if (!isNaN(partId)) {
                removePart(partId);
            }
        }
    });

    selectedPartsList.addEventListener('change', (e) => {
        const target = e.target;
        const partId = parseInt(target.dataset.partId);

        if (target.classList.contains('jo-part-quantity')) {
            updatePartValue(partId, target.value);
        }
    });

    submitBtn.addEventListener('click', () => {
        const hoursTaken = parseFloat(hoursInput.value);
        // REMOVED: notes variable assignment

        if (!isNaN(hoursTaken) && hoursTaken > 0) {
            
            let partsValid = true;
            selectedParts.forEach(part => {
                if (part.quantity < 1 || isNaN(part.quantity)) {
                    partsValid = false;
                }
            });

            if (!partsValid) {
                alert('Please ensure all selected parts have a valid quantity (>= 1).');
                return;
            }

            modalContainer.remove(); 
            // REMOVED: notes from function call
            showConfirmationModal(jobId, serviceName, hoursTaken, selectedParts); 
        } else {
            hoursInput.style.borderColor = 'red';
            hoursInput.placeholder = 'Invalid input. Enter a number > 0.';
        }
    });

    renderDropdown(); 
};


// REMOVED: notes from function signature
const showConfirmationModal = (jobId, serviceName, hoursTaken, partsUsed) => {
    
    const totalPartsCost = partsUsed.reduce((sum, part) => sum + (part.quantity * part.cost), 0);
    
    const partsListHtml = partsUsed.length > 0 ? partsUsed.map(part => {
        const subtotal = (part.quantity * part.cost).toFixed(2);
        return `
            <li>
                ${part.name}${part.brand ? ` (${part.brand})` : ''} 
                (Qty: ${part.quantity}, Unit Cost: ₱${part.cost.toFixed(2)}, Subtotal: ₱${subtotal})
            </li>
        `;
    }).join('') : '<li>No parts added.</li>';

    const confirmationHtml = `
        <div class="jo-details-modal jo-confirmation-modal">
            <span class="jo-modal-close">&times;</span>
            <h2>Confirm Submission</h2>
            <p><strong>Service:</strong> ${serviceName}</p>
            <p><strong>Hours Taken:</strong> ${hoursTaken} hours</p>
            <hr>
            <h3>Parts Summary:</h3>
            <ul class="jo-confirmation-parts-list">
                ${partsListHtml}
            </ul>
            <p><strong>Total Parts Cost:</strong> <span class="mj-total-cost">₱${totalPartsCost.toFixed(2)}</span></p>
            <div id="jo-confirmation-btn-container">
                <button class="jo-confirm-btn">Confirm</button>
                <button class="jo-cancel-btn">Cancel</button>
            </div>
        </div>
    `;

    const modalContainer = document.createElement('div');
    modalContainer.className = 'mj-modal-overlay';
    modalContainer.innerHTML = confirmationHtml;
    document.body.appendChild(modalContainer);

    modalContainer.querySelector('.jo-confirm-btn').addEventListener('click', () => {
        // REMOVED: notes from function call
        handleFinishService(jobId, serviceName, hoursTaken, partsUsed); 
        modalContainer.remove();
    });

    modalContainer.querySelector('.jo-cancel-btn').addEventListener('click', () => {
        modalContainer.remove();
        showPartsHoursModal(jobId, serviceName); 
    });

    modalContainer.querySelector('.jo-modal-close').addEventListener('click', () => {
        modalContainer.remove();
    });
};

// REMOVED: notes from function signature
const handleFinishService = async(jobId, serviceName, hoursTaken, partsUsed) => {
    try {
        const response = await fetch('../../b/mj/finishMJ.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                job_id: jobId,
                service_name: serviceName,
                hours_taken: hoursTaken,
                // REMOVED: notes from payload
                parts_used: partsUsed 
            }),
        });
        const result = await response.json();
        if (result.status === 'success') {
            await fetchJobs();
        } else {
            console.error('Failed to finish service:', result.message);
            alert('Failed to finish service. Please try again: ' + result.message);
        }
    } catch (error) {
        console.error('Error finishing service:', error);
        alert('An error occurred. Please check your connection.');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    fetchJobs();
    fetchParts(); 
});