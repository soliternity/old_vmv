document.addEventListener('DOMContentLoaded', () => {

    // --- DOM Elements ---
    const joAddBtn = document.getElementById('jo-add-btn');
    const joAddModal = document.getElementById('jo-add-modal');
    const joSummaryModal = document.getElementById('jo-summary-modal');
    const joEditModal = document.getElementById('jo-edit-modal');
    const joDeleteModal = document.getElementById('jo-delete-modal');
    const joCloseButtons = document.querySelectorAll('.jo-modal-close');
    // Updated container from .jo-list-container to the <tbody> ID
    const joListContainer = document.getElementById('jo-list-container');
    const joConfirmDeleteBtn = document.getElementById('jo-confirm-delete-btn');
    const joCancelDeleteBtn = document.getElementById('jo-cancel-delete-btn');
    const joAddSubmitBtn = document.querySelector('#jo-add-modal .jo-submit-btn');
    const joEditSubmitBtn = document.querySelector('#jo-edit-modal .jo-submit-btn');

    // --- New Filter/Sort DOM Elements ---
    const joSearchInput = document.getElementById('jo-search-input');
    const joStatusFilter = document.getElementById('jo-status-filter');
    const joSortServicesBtn = document.getElementById('jo-sort-services-btn');


    const joAddErrorMessage = document.getElementById('jo-add-error-message');
    const joEditErrorMessage = document.getElementById('jo-edit-error-message');
    // --- State Variables ---
    let allJobsData = []; // Store the fetched job data for filtering/sorting
    let currentJobIdToDelete = null;
    let selectedAddServices = [];
    let selectedAddMechanics = [];
    let selectedEditServices = [];
    let selectedEditMechanics = [];
    let selectedAddCustomerId = null;


    // --- API Endpoints ---
    const API_URLS = {
        jobs: '../../b/jbs/fetchJbs.php',
        customers: '../../b/jbs/fetchAU.php',
        services: '../../b/jbs/fetchSvc.php',
        mechanics: '../../b/jbs/fetchStf.php',
        add: '../../b/jbs/addJbs.php',
        edit: '../../b/jbs/updJbs.php',
        delete: '../../b/jbs/delJbs.php'
    };

    // --- Filter and Sort Logic ---

    /**
     * Applies search, status filter, and service count sort to the job data.
     */
    const applyFiltersAndSort = () => {
        let filteredJobs = [...allJobsData]; // Start with a copy of all data

        // 1. Search Filter
        const searchTerm = joSearchInput.value.toLowerCase();
        if (searchTerm) {
            filteredJobs = filteredJobs.filter(job => {
                const searchString = `${job.display_id} ${job.customer_name} ${job.vehicle.brand} ${job.vehicle.plate}`.toLowerCase();
                return searchString.includes(searchTerm);
            });
        }

        // 2. Status Filter
        const selectedStatus = joStatusFilter.value;
        if (selectedStatus !== 'All') {
            filteredJobs = filteredJobs.filter(job => {
                const jobStatus = job.status === 'Unpaid' ? 'Completed / Unpaid' : job.status;
                return jobStatus === selectedStatus;
            });
        }

        // 3. Sorting (by Services count)
        const sortOrder = joSortServicesBtn.dataset.sortOrder; // 'asc' or 'desc'
        filteredJobs.sort((a, b) => {
            const countA = a.services.length;
            const countB = b.services.length;

            if (sortOrder === 'asc') {
                return countA - countB;
            } else {
                return countB - countA;
            }
        });

        renderJobTable(filteredJobs); // Render the final, filtered, and sorted list
    };

    // --- Event Listeners for Filter/Sort ---
    joSearchInput.addEventListener('input', applyFiltersAndSort);
    joStatusFilter.addEventListener('change', applyFiltersAndSort);

    joSortServicesBtn.addEventListener('click', () => {
        let currentOrder = joSortServicesBtn.dataset.sortOrder;
        let newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        joSortServicesBtn.dataset.sortOrder = newOrder;

        // Update the icon
        const icon = joSortServicesBtn.querySelector('i');
        icon.className = newOrder === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';

        applyFiltersAndSort(); // Re-apply all filters and the new sort order
    });

    // --- Function to Render Job Table Rows (replaces renderJobCards) ---
    function renderJobTable(jobs) {
        joListContainer.innerHTML = ''; // Clear existing rows
        const noJobsMessage = document.getElementById('no-jobs-message');

        if (jobs && jobs.length > 0) {
            noJobsMessage.style.display = 'none';

            jobs.forEach(job => {
                let statusText;
                let statusClass;

                if (job.status === 'Completed') {
                    // Assuming 'Completed' jobs are now 'Unpaid' until marked otherwise in payment
                    statusText = 'Unpaid';
                    statusClass = 'unpaid';
                } else {
                    statusText = job.status;
                    statusClass = job.status.toLowerCase();
                }
                const totalServices = job.services.length;

                const rowHtml = `
                    <tr class="jo-table-row" data-job-id="${job.id}">
                        <td>#${job.display_id}</td>
                        <td class="jo-customer-name">${job.customer_name}</td>
                        <td class="jo-vehicle">${job.vehicle.brand}</td>
                        <td class="jo-plate-no">${job.vehicle.plate}</td>
                        <td><span class="jo-service-count">${totalServices}</span></td>
                        <td>${new Date(job.created_at).toLocaleDateString()}</td>
                        <td><span class="jo-status-tag jo-${statusClass}">${statusText}</span></td>
                        <td class="jo-table-actions">
                            <button class="jo-icon-btn jo-view-btn"><i class="fa-solid fa-file-lines"></i></button>
                            <button class="jo-icon-btn jo-edit-btn"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="jo-icon-btn jo-delete-btn"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                `;
                joListContainer.insertAdjacentHTML('beforeend', rowHtml);
            });
        } else {
            noJobsMessage.style.display = 'block';
        }
    }

    // --- Modal Control Functions (Unchanged) ---
    function openModal(modal) {
        modal.style.display = 'flex';
    }

    function closeModal(modal) {
        modal.style.display = 'none';
    }

    // --- Data Fetching and UI Update ---
    async function fetchJobsAndRender() {
        try {
            const response = await fetch(API_URLS.jobs);
            if (!response.ok) {
                console.error('Network response was not ok');
                document.getElementById('no-jobs-message').textContent = 'Failed to load job orders. Please try again later.';
                document.getElementById('no-jobs-message').style.display = 'block';
                return;
            }
            const data = await response.json();

            if (data.success === false) {
                window.location.href = 'dsh.html'; // Redirect to dashboard
                return;
            }

            allJobsData = data; // Store the raw data
            applyFiltersAndSort(); // Render the initial view
        } catch (error) {
            console.error('Failed to fetch job orders:', error);
            document.getElementById('no-jobs-message').textContent = 'Failed to load job orders. Please try again later.';
            document.getElementById('no-jobs-message').style.display = 'block';
        }
    }


    async function fetchData(url) {
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            return await response.json();
        } catch (error) {
            console.error(`Failed to fetch data from ${url}:`, error);
            return [];
        }
    }

    // --- Search Filtering and Selection Logic (Unchanged) ---
    async function setupSearch(inputElement, resultsElement, url, onSelect, selectedItems) {
        const data = await fetchData(url);

        inputElement.addEventListener('focus', () => {
            resultsElement.style.display = 'block';
            filterResults(inputElement, resultsElement, data, onSelect, selectedItems);
        });

        inputElement.addEventListener('input', () => {
            filterResults(inputElement, resultsElement, data, onSelect, selectedItems);
        });

        inputElement.addEventListener('blur', () => {
            setTimeout(() => {
                resultsElement.style.display = 'none';
            }, 200);
        });
    }

    function filterResults(inputElement, resultsElement, mockData, onSelect, selectedItems) {
        const searchTerm = inputElement.value.toLowerCase();
        resultsElement.innerHTML = '';

        const filteredData = mockData.filter(item => {
            const name = item.name || `${item.brand} - ${item.plate}`;
            const isSelected = selectedItems.find(selected => selected.id == item.id);
            return name.toLowerCase().includes(searchTerm) && !isSelected;
        });

        if (filteredData.length > 0) {
            filteredData.forEach(item => {
                const li = document.createElement('li');
                const text = `${item.name} (${item.plate})` || `${item.brand} - ${item.plate}`;
                li.textContent = text;
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    onSelect(item, inputElement);
                    resultsElement.style.display = 'none';
                    inputElement.blur();
                });
                resultsElement.appendChild(li);
            });
        } else {
            resultsElement.style.display = 'none';
        }
    }


    function calculateTotalCost(services) {
        return services.reduce((total, service) => total + ((parseFloat(service.min_cost) + parseFloat(service.max_cost)) / 2), 0);
    }

    // --- Event Listeners ---

    // Initial render of job cards
    fetchJobsAndRender();

    // Function to reset the Add Job form
    function resetAddJobForm() {
        document.getElementById('jo-customer-search').value = '';
        document.getElementById('jo-car-brand').value = '';
        document.getElementById('jo-car-color').value = '';
        document.getElementById('jo-plate-no').value = '';
        selectedAddServices = [];
        selectedAddMechanics = [];
        selectedAddCustomerId = null; // Reset the customer ID
        document.getElementById('jo-services-list').innerHTML = '';
        document.getElementById('jo-mechanics-list').innerHTML = '';
        document.getElementById('jo-overall-service-cost').textContent = '₱ 0.00';
    }

    // Open Add Job modal
    joAddBtn.addEventListener('click', () => {
        resetAddJobForm();
        openModal(joAddModal);
    });

    // Close any modal when the close button is clicked
    joCloseButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            const modal = event.target.closest('.jo-modal-overlay');
            if (modal) {
                closeModal(modal);
            }
        });
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', (event) => {
        if (event.target === joAddModal) {
            closeModal(joAddModal);
        }
        if (event.target === joSummaryModal) {
            closeModal(joSummaryModal);
        }
        if (event.target === joEditModal) {
            closeModal(joEditModal);
        }
        if (event.target === joDeleteModal) {
            closeModal(joDeleteModal);
        }
    });

    // Handle job table button clicks (delegated)
    // Check if the target is a button inside a <tr> with class .jo-table-row
    joListContainer.addEventListener('click', (event) => {
        const targetBtn = event.target.closest('button');
        if (!targetBtn) return;

        const jobRow = targetBtn.closest('.jo-table-row');
        const jobId = jobRow.dataset.jobId;

        const jobData = allJobsData.find(job => job.id == jobId);
        if (!jobData) return;

        if (targetBtn.classList.contains('jo-view-btn')) {
            populateSummary(jobData);
            openModal(joSummaryModal);
        } else if (targetBtn.classList.contains('jo-edit-btn')) {
            populateEditForm(jobData);
            openModal(joEditModal);
        } else if (targetBtn.classList.contains('jo-delete-btn')) {
            currentJobIdToDelete = jobId;
            document.getElementById('jo-delete-id').textContent = jobData.display_id;
            openModal(joDeleteModal);
        }
    });

    // --- Add Job Modal Search Logic (Unchanged) ---
    const addServicesList = document.getElementById('jo-services-list');
    const addMechanicsList = document.getElementById('jo-mechanics-list');

    setupSearch(document.getElementById('jo-customer-search'), document.getElementById('jo-customer-results'), API_URLS.customers, (customer, input) => {
        input.value = customer.name;
        document.getElementById('jo-car-brand').value = customer.brand;
        document.getElementById('jo-car-color').value = customer.color;
        document.getElementById('jo-plate-no').value = customer.plate;
        selectedAddCustomerId = customer.id; // Store the customer ID
    }, []);

    setupSearch(document.getElementById('jo-service-search'), document.getElementById('jo-service-results'), API_URLS.services, (service, input) => {
        if (!selectedAddServices.find(s => s.id == service.id)) {
            selectedAddServices.push(service);
            updateSelectedList(addServicesList, selectedAddServices, (item) => `${item.name} - ₱${parseFloat(item.min_cost).toLocaleString()} - ₱${parseFloat(item.max_cost).toLocaleString()}`);
            document.getElementById('jo-overall-service-cost').textContent = `₱ ${calculateTotalCost(selectedAddServices).toLocaleString()}`;
        }
        input.value = '';
    }, selectedAddServices);

    setupSearch(document.getElementById('jo-mechanic-search'), document.getElementById('jo-mechanic-results'), API_URLS.mechanics, (mechanic, input) => {
        if (!selectedAddMechanics.find(m => m.id == mechanic.id)) {
            selectedAddMechanics.push(mechanic);
            updateSelectedList(addMechanicsList, selectedAddMechanics, (item) => item.name);
        }
        input.value = '';
    }, selectedAddMechanics);

    function updateSelectedList(listElement, dataArray, textFn = (item) => item.name) {
        listElement.innerHTML = '';
        dataArray.forEach((item, index) => {
            const li = document.createElement('li');
            li.innerHTML = `<span>${textFn(item)}</span> <span class="remove-item">&times;</span>`;
            li.querySelector('.remove-item').addEventListener('click', () => {
                dataArray.splice(index, 1);
                updateSelectedList(listElement, dataArray, textFn);
                if (listElement.id === 'jo-services-list' || listElement.id === 'jo-edit-services-list') {
                    const totalCostElementId = listElement.id === 'jo-services-list' ? 'jo-overall-service-cost' : 'jo-edit-overall-service-cost';
                    document.getElementById(totalCostElementId).textContent = `₱ ${calculateTotalCost(dataArray).toLocaleString()}`;
                }
            });
            listElement.appendChild(li);
        });
    }

    // --- Add Job Order Logic ---
    joAddSubmitBtn.addEventListener('click', async () => {
        const customerName = document.getElementById('jo-customer-search').value;
        const vehicleBrand = document.getElementById('jo-car-brand').value;
        const vehicleColor = document.getElementById('jo-car-color').value;
        const vehiclePlate = document.getElementById('jo-plate-no').value;

        if (!customerName || !vehicleBrand || !vehicleColor || !vehiclePlate) {
            console.error('Add Job Error: Missing customer or vehicle fields.');
            return;
        }
        
        if (selectedAddServices.length === 0) {
             console.error('Add Job Error: No services selected.');
             return;
        }

        const newJobData = {
            au_id: selectedAddCustomerId, // Add the selected customer ID
            customer_name: customerName,
            vehicle_brand: vehicleBrand,
            vehicle_color: vehicleColor,
            vehicle_plate: vehiclePlate,
            services: selectedAddServices.map(s => s.id),
            mechanics: selectedAddMechanics.map(m => m.id)
        };

        try {
            const response = await fetch(API_URLS.add, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(newJobData)
            });
            const result = await response.json();
            if (result.success) {
                closeModal(joAddModal);
                joAddErrorMessage.innerHTML = '';
                await fetchJobsAndRender(); // Refresh the list
            } else {
                joAddErrorMessage.innerHTML = result.message;
                console.error('Error creating job order: ' + result.message);
            }
        } catch (error) {
            console.error('An unexpected error occurred.', error);
        }
    });

    // --- Data Population Functions for Modals (Unchanged) ---

    function populateSummary(jobData) {
        document.getElementById('jo-summary-id-header').textContent = `#${jobData.display_id}`;
        document.getElementById('jo-summary-id').textContent = jobData.id;
        document.getElementById('jo-summary-customer').textContent = jobData.customer_name;
        
        // Handle Unpaid status on summary
        let statusText;
        let statusClass;
        if(jobData.status === 'Completed'){
            statusText = 'Unpaid';
            statusClass = 'unpaid';
        }else{
            statusText = jobData.status;
            statusClass = jobData.status.toLowerCase();
        }
        document.getElementById('jo-summary-status').textContent = statusText;
        document.getElementById('jo-summary-status').className = `jo-status-tag jo-${statusClass}`;
        
        document.getElementById('jo-summary-vehicle').textContent = jobData.vehicle.brand;
        document.getElementById('jo-summary-color').textContent = jobData.vehicle.color;
        document.getElementById('jo-summary-plate-no').textContent = jobData.vehicle.plate;
        document.getElementById('jo-summary-date').textContent = new Date(jobData.created_at).toLocaleDateString();

        const servicesList = document.getElementById('jo-summary-services-list');
        const mechanicsList = document.getElementById('jo-summary-mechanics-list');
        servicesList.innerHTML = '';
        mechanicsList.innerHTML = '';

        let overallCost = 0;
        jobData.services.forEach(service => {
            const li = document.createElement('li');
            li.innerHTML = `${service.name} - <span class="jo-summary-cost">₱ ${parseFloat(service.min_cost).toLocaleString()} - ₱${parseFloat(service.max_cost).toLocaleString()}</span>`;
            servicesList.appendChild(li);
            overallCost += (parseFloat(service.min_cost) + parseFloat(service.max_cost)) / 2;
        });

        jobData.mechanics.forEach(mechanic => {
            const li = document.createElement('li');
            li.textContent = mechanic.name;
            mechanicsList.appendChild(li);
        });

        document.getElementById('jo-summary-overall-cost').textContent = `₱ ${overallCost.toLocaleString()}`;
    }

    function populateEditForm(jobData) {
        document.getElementById('jo-edit-id').textContent = jobData.id;
        // Fields made read-only in HTML, but populated here for viewing
        document.getElementById('jo-edit-customer-name').value = jobData.customer_name;
        document.getElementById('jo-edit-car-brand').value = jobData.vehicle.brand;
        document.getElementById('jo-edit-car-color').value = jobData.vehicle.color;
        document.getElementById('jo-edit-plate-no').value = jobData.vehicle.plate;
        document.getElementById('jo-edit-overall-service-cost').textContent = `₱ ${calculateTotalCost(jobData.services).toLocaleString()}`;

        selectedEditServices = jobData.services.map(service => ({
            id: service.id,
            name: service.name,
            min_cost: service.min_cost,
            max_cost: service.max_cost
        }));

        selectedEditMechanics = jobData.mechanics.map(mechanic => ({
            id: mechanic.id,
            name: mechanic.name
        }));

        updateSelectedList(document.getElementById('jo-edit-services-list'), selectedEditServices, (item) => `${item.name} - ₱${parseFloat(item.min_cost).toLocaleString()} - ₱${parseFloat(item.max_cost).toLocaleString()}`);
        updateSelectedList(document.getElementById('jo-edit-mechanics-list'), selectedEditMechanics, (item) => item.name);
    }

    // --- Edit Job Modal Search Logic (Unchanged) ---
    setupSearch(document.getElementById('jo-edit-service-search'), document.getElementById('jo-edit-service-results'), API_URLS.services, (service, input) => {
        if (!selectedEditServices.find(s => s.id == service.id)) {
            selectedEditServices.push(service);
            updateSelectedList(document.getElementById('jo-edit-services-list'), selectedEditServices, (item) => `${item.name} - ₱${parseFloat(item.min_cost).toLocaleString()} - ₱${parseFloat(item.max_cost).toLocaleString()}`);
            document.getElementById('jo-edit-overall-service-cost').textContent = `₱ ${calculateTotalCost(selectedEditServices).toLocaleString()}`;
        }
        input.value = '';
    }, selectedEditServices);

    setupSearch(document.getElementById('jo-edit-mechanic-search'), document.getElementById('jo-edit-mechanic-results'), API_URLS.mechanics, (mechanic, input) => {
        if (!selectedEditMechanics.find(m => m.id == mechanic.id)) {
            selectedEditMechanics.push(mechanic);
            updateSelectedList(document.getElementById('jo-edit-mechanics-list'), selectedEditMechanics, (item) => item.name);
        }
        input.value = '';
    }, selectedEditMechanics);

    // --- Edit Job Order Logic ---
    joEditSubmitBtn.addEventListener('click', async () => {
        const jobId = document.getElementById('jo-edit-id').textContent;
        
        // Removed customer and vehicle fields from the data being sent to prevent edits.
        
        if (selectedEditServices.length === 0) {
             console.error('Edit Job Error: No services selected.');
             return;
        }

        const updatedJobData = {
            job_id: jobId,
            // Removed: vehicle_brand, vehicle_color, vehicle_plate, and customer_name
            services: selectedEditServices.map(s => s.id),
            mechanics: selectedEditMechanics.map(m => m.id)
        };

        try {
            const response = await fetch(API_URLS.edit, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updatedJobData)
            });
            const result = await response.json();
            if (result.success) {
                joEditErrorMessage.innerHTML = '';
                closeModal(joEditModal);
                await fetchJobsAndRender(); // Refresh the list
            } else {
                joEditErrorMessage.innerHTML = result.message;
                console.error('Error updating job order: ' + result.message);
            }
        } catch (error) {
            console.error('An unexpected error occurred.', error);
        }
    });

    // --- Delete Confirmation Logic ---
    joConfirmDeleteBtn.addEventListener('click', async () => {
        if (!currentJobIdToDelete) return;

        try {
            const response = await fetch(API_URLS.delete, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id: currentJobIdToDelete })
            });
            const result = await response.json();
            if (result.success) {
                closeModal(joDeleteModal);
                await fetchJobsAndRender(); // Refresh the list
            } else {
                console.error('Error deleting job order: ' + result.message);
            }
        } catch (error) {
            console.error('An unexpected error occurred.', error);
        }
    });

    joCancelDeleteBtn.addEventListener('click', () => {
        closeModal(joDeleteModal);
        currentJobIdToDelete = null;
    });

});