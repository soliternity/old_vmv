// I updated the search input ID selector to match the new ID used in svc.html
document.addEventListener('DOMContentLoaded', () => {
    const svcList = document.getElementById('svc-services-list');
    // Updated selector to target the new ID in the header
    const searchInput = document.getElementById('stf-search-input'); 
    const svcContainer = document.querySelector('.svc-container');

    // View Modal Elements
    const viewOverlay = document.getElementById('svc-view-overlay');
    const viewModalName = document.getElementById('view-modal-name');
    const viewModalCost = document.getElementById('view-modal-cost');
    const viewModalHours = document.getElementById('view-modal-hours');
    const viewModalDescription = document.getElementById('view-modal-description');

    // Add/Edit Form Elements
    const addBtn = document.getElementById('svc-add-btn');
    const formOverlay = document.getElementById('svc-form-overlay');
    const formModalTitle = document.getElementById('svc-form-title');
    const svcForm = document.getElementById('svc-form');
    const svcNameInput = document.getElementById('svc-name');
    const svcCostMinInput = document.getElementById('svc-cost-min');
    const svcCostMaxInput = document.getElementById('svc-cost-max');
    const svcHoursMinInput = document.getElementById('svc-hours-min');
    const svcHoursMaxInput = document.getElementById('svc-hours-max');
    const svcDescriptionTextarea = document.getElementById('svc-description');
    const formCancelBtn = document.getElementById('svc-form-cancel');
    let isEditing = false;
    let currentEditService = null; // Will hold the service object being edited

    // Delete Modal Elements
    const deleteOverlay = document.getElementById('svc-delete-overlay');
    const deleteServiceName = document.getElementById('delete-service-name');
    const confirmDeleteBtn = document.querySelector('.svc-confirm-delete-btn');
    const cancelDeleteBtn = document.querySelector('.svc-cancel-delete-btn');
    let serviceToDelete = null; // Will hold the service object being deleted

    // Store all loaded services
    let allServices = [];

    // Utility: Render all services
    function renderServices(services) {
        svcList.innerHTML = '';
        services.forEach(service => {
            const li = document.createElement('li');
            li.className = 'svc-item';
            li.dataset.serviceId = service.id;
            li.dataset.costMin = service.min_cost;
            li.dataset.costMax = service.max_cost;
            li.dataset.hoursMin = service.min_hours;
            li.dataset.hoursMax = service.max_hours;
            li.dataset.description = service.description || '';
            li.innerHTML = `
                        <div class="svc-content">
                            <h3>${service.name}</h3>
                        </div>
                        <div class="svc-actions">
                            <button class="svc-edit-btn">Edit</button>
                            <button class="svc-delete-btn">Delete</button>
                        </div>
                    `;
            svcList.appendChild(li);
        });
    }

    // Fetch user session data to determine admin/manager
    fetch('../../b/session.php')
        .then(response => response.json())
        .then(data => {
            const userRole = data.user_data?.role;
            if (userRole === 'admin' || userRole === 'manager') {
                svcContainer.classList.add('admin-mode');
            }
        })
        .catch(() => { });

    // Fetch all services from backend
    function fetchServices() {
        fetch('../../b/svc/fetchSvc.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allServices = data.data;
                    renderServices(allServices);
                } else {
                    svcList.innerHTML = '<li style="color:red;">Failed to load services.</li>';
                }
            })
            .catch(() => {
                svcList.innerHTML = '<li style="color:red;">Error loading services.</li>';
            });
    }
    fetchServices();

    // General overlay functions
    const showOverlay = (overlay) => {
        overlay.style.display = 'flex';
        setTimeout(() => overlay.classList.add('svc-active'), 10);
    };
    const hideOverlay = (overlay) => {
        overlay.classList.remove('svc-active');
        setTimeout(() => overlay.style.display = 'none', 300);
    };

    // Add Service button
    addBtn.addEventListener('click', () => {
        isEditing = false;
        currentEditService = null;
        formModalTitle.textContent = 'Add New Service';
        svcForm.reset();
        showOverlay(formOverlay);
    });

    // Handle clicks on service items (view, edit, delete)
    svcList.addEventListener('click', (event) => {
        const item = event.target.closest('.svc-item');
        if (!item) return;
        const serviceId = item.dataset.serviceId;
        const service = allServices.find(s => String(s.id) === String(serviceId));
        if (!service) return;

        const content = event.target.closest('.svc-content');
        const editBtn = event.target.closest('.svc-edit-btn');
        const deleteBtn = event.target.closest('.svc-delete-btn');

        // View details
        if (content) {
            document.querySelectorAll('.svc-item').forEach(i => i.classList.remove('svc-active-item'));
            item.classList.add('svc-active-item');
            viewModalName.textContent = service.name;
            viewModalCost.textContent = `₱${service.min_cost} - ₱${service.max_cost}`;
            viewModalHours.textContent = `${service.min_hours} - ${service.max_hours} hours`;
            viewModalDescription.textContent = service.description || '';
            showOverlay(viewOverlay);
        }

        // Edit
        if (editBtn) {
            isEditing = true;
            currentEditService = service;
            formModalTitle.textContent = 'Edit Service';
            svcNameInput.value = service.name;
            svcCostMinInput.value = service.min_cost;
            svcCostMaxInput.value = service.max_cost;
            svcHoursMinInput.value = service.min_hours;
            svcHoursMaxInput.value = service.max_hours;
            svcDescriptionTextarea.value = service.description || '';
            showOverlay(formOverlay);
        }

        // Delete
        if (deleteBtn) {
            serviceToDelete = service;
            deleteServiceName.textContent = service.name;
            showOverlay(deleteOverlay);
        }
    });

    // Add/Edit form submit
    svcForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const payload = {
            name: svcNameInput.value.trim(),
            min_cost: parseFloat(svcCostMinInput.value),
            max_cost: parseFloat(svcCostMaxInput.value),
            min_hours: parseFloat(svcHoursMinInput.value),
            max_hours: parseFloat(svcHoursMaxInput.value),
            description: svcDescriptionTextarea.value.trim()
        };

        if (isEditing && currentEditService) {
            // Update
            payload.id = currentEditService.id;
            fetch('../b/svc/updSvc.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Update local data and re-render
                        const idx = allServices.findIndex(s => s.id == currentEditService.id);
                        if (idx !== -1) allServices[idx] = data.data;
                        renderServices(allServices);
                        hideOverlay(formOverlay);
                    } else {
                        // Replaced alert() with console.error
                        console.error(data.error || 'Failed to update service.');
                    }
                })
                .catch(() => console.error('Error updating service.'));
        } else {
            // Add new
            fetch('../b/svc/addSvc.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        allServices.push(data.data);
                        renderServices(allServices);
                        hideOverlay(formOverlay);
                    } else {
                        // Replaced alert() with console.error
                        console.error(data.error || 'Failed to add service.');
                    }
                })
                .catch(() => console.error('Error adding service.'));
        }
    });

    // Delete confirmation
    confirmDeleteBtn.addEventListener('click', () => {
        if (!serviceToDelete) return;
        fetch(`../b/svc/delSvc.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: serviceToDelete.id
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allServices = allServices.filter(s => s.id != serviceToDelete.id);
                    renderServices(allServices);
                    hideOverlay(deleteOverlay);
                } else {
                    // Replaced alert() with console.error
                    console.error(data.error || 'Failed to delete service.');
                }
            })
            .catch(() => console.error('Error deleting service.'));
    });

    // Cancel/close handlers
    cancelDeleteBtn.addEventListener('click', () => hideOverlay(deleteOverlay));
    formCancelBtn.addEventListener('click', () => hideOverlay(formOverlay));
    document.querySelectorAll('.svc-modal-close').forEach(button => {
        button.addEventListener('click', (event) => {
            const overlayToHide = event.target.closest('.svc-overlay');
            hideOverlay(overlayToHide);
        });
    });
    document.querySelectorAll('.svc-overlay').forEach(overlay => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) hideOverlay(overlay);
        });
    });

    // Search filter
    // Updated selector to target the new ID in the header
    searchInput.addEventListener('input', (event) => {
        const searchTerm = event.target.value.toLowerCase();
        Array.from(svcList.children).forEach(item => {
            const name = item.querySelector('h3').textContent.toLowerCase();
            const desc = item.dataset.description.toLowerCase();
            if (name.includes(searchTerm) || desc.includes(searchTerm)) {
                item.classList.remove('svc-hidden');
            } else {
                item.classList.add('svc-hidden');
            }
        });
    });
});
