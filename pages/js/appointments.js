class AppointmentCalendarManager {
    constructor() {
        this.currentDate = new Date();
        this.appointments = [];
        this.timeSlots = [
            '8:00 - 9:00', '9:00 - 10:00', '10:00 - 11:00', '11:00 - 12:00',
            '13:00 - 14:00', '14:00 - 15:00', '15:00 - 16:00',
            '16:00 - 17:00'
        ];
        this.selectedDates = new Set();
        this.dateAvailability = {};
        this.customers = []; // Customer list for search/dropdown
        
        // Ensure all necessary data is fetched before initializing
        Promise.all([
            this.fetchAppointments(),
            this.fetchDateRules(),
            this.fetchCustomers() 
        ]).then(() => {
            this.initializeCalendar();
            this.bindEventHandlers();
        }).catch(error => {
            console.error("Initialization failed:", error);
            // Even if fetches fail, try to initialize the UI
            this.initializeCalendar();
            this.bindEventHandlers();
        });
    }

    // Fetch customer data from fetchAU.php
    async fetchCustomers() {
        try {
            const response = await fetch('../../b/appointments/fetchAU.php');
            const result = await response.json();
            if (result.success && Array.isArray(result.data)) {
                this.customers = result.data;
            } else {
                console.error('Failed to fetch customers:', result.message);
                this.customers = [];
            }
        } catch (err) {
            console.error('Error fetching customers:', err);
            this.customers = [];
        }
    }

    async fetchAppointments() {
        try {
            const response = await fetch('../../b/appointments/loadAppoinments.php');
            const result = await response.json();
            if (result.success && Array.isArray(result.data)) {
                this.appointments = result.data.map(appt => ({
                    id: appt.appointment_id,
                    date: this.parseDate(appt.date, appt.starting_time),
                    time: this.formatTimeSlot(appt.starting_time, appt.ending_time),
                    // Use the app_user_name field from the JOIN in your assumed PHP load script
                    title: appt.app_user_name ? appt.app_user_name : 'Appointment' 
                }));
            } else {
                this.appointments = [];
            }
        } catch (err) {
            this.appointments = [];
        }
    }

    parseDate(dateStr, startTimeStr) {
        // Correct parsing for time zone consistency
        return new Date(`${dateStr}T${startTimeStr}`);
    }

    formatTimeSlot(startTimeStr, endTimeStr) {
        // Helper to format time slots consistently
        const format = t => {
            const [hours, minutes] = t.split(':').map(Number);
            // Ensure hours and minutes are two digits for display if needed, but only for the time part
            return `${hours}:${String(minutes).padStart(2, '0')}`;
        };
        return `${format(startTimeStr)} - ${format(endTimeStr)}`;
    }

    initializeCalendar() {
        this.updateCurrentDateDisplay();
        this.generateCalendarGrid();
    }

    bindEventHandlers() {
        document.getElementById('appt-prev-month-btn').addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.generateCalendarGrid();
            this.clearSelection();
        });

        document.getElementById('appt-next-month-btn').addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.generateCalendarGrid();
            this.clearSelection();
        });

        document.getElementById('appt-modal-close-btn').addEventListener('click', () => {
            this.closeModal();
        });

        document.getElementById('appt-event-modal').addEventListener('click', (e) => {
            if (e.target.id === 'appt-event-modal') {
                this.closeModal();
            }
        });

        document.getElementById('appt-edit-selected-btn').addEventListener('click', () => {
            this.openEditModal();
        });

        document.getElementById('appt-edit-modal-close-btn').addEventListener('click', () => {
            this.closeEditModal();
        });

        document.getElementById('appt-edit-modal').addEventListener('click', (e) => {
            if (e.target.id === 'appt-edit-modal') {
                this.closeEditModal();
            }
        });

        document.getElementById('appt-edit-cancel-btn').addEventListener('click', () => {
            this.closeEditModal();
        });

        document.getElementById('appt-edit-save-btn').addEventListener('click', () => {
            this.saveAvailabilityChanges();
        });

        document.getElementById('appt-day-available').addEventListener('change', () => {
            this.toggleTimeSlotsSection();
        });

        // Event Handlers for Add Appointment Modal
        document.getElementById('appt-fab-btn').addEventListener('click', () => {
            this.openAddModal();
        });
        document.getElementById('appt-add-new-modal-close-btn').addEventListener('click', () => {
            this.closeAddModal();
        });
        document.getElementById('appt-new-cancel-btn').addEventListener('click', () => {
            this.closeAddModal();
        });
        document.getElementById('appt-new-appointment-form').addEventListener('submit', (event) => {
            this.saveNewAppointment(event);
        });
        document.getElementById('appt-add-new-modal').addEventListener('click', (e) => {
            if (e.target.id === 'appt-add-new-modal') {
                this.closeAddModal();
            }
        });
        // Listener to populate time slots when the date changes
        document.getElementById('appt-new-date-input').addEventListener('change', () => {
            this.populateNewAppointmentTimeSlots();
        });
        
        // NEW: Listener for customer name search input
        const customerInput = document.getElementById('appt-customer-name-input');
        if (customerInput) {
            // Filter results on typing
            customerInput.addEventListener('input', (e) => this.handleCustomerSearch(e.target.value));
            // Show all results (or initial list) on focus
            customerInput.addEventListener('focus', (e) => this.handleCustomerSearch(e.target.value));
            // Hide results when clicking outside (simple implementation)
            document.addEventListener('click', (e) => {
                const container = document.querySelector('.appt-search-dropdown-container');
                const resultsList = document.getElementById('appt-customer-search-results');
                if (container && resultsList && !container.contains(e.target)) {
                    resultsList.style.display = 'none';
                }
            });
        }
    }

    // NEW: Function to show status message in the Add Appointment Modal
    showAddStatusMessage(message, isSuccess = false) {
        const statusDiv = document.getElementById('appt-add-status-message');
        if (statusDiv) {
            statusDiv.textContent = message;
            statusDiv.style.display = 'block';
            statusDiv.style.color = isSuccess ? '#4CAF50' : '#f44336'; // Green for success, Red for error
        }
    }
    
    // NEW: Function to clear status message in the Add Appointment Modal
    clearAddStatusMessage() {
        const statusDiv = document.getElementById('appt-add-status-message');
        if (statusDiv) {
            statusDiv.textContent = '';
            statusDiv.style.display = 'none';
        }
    }

    // NEW: Handles customer name search and updates the <ul> results list
    handleCustomerSearch(query) {
        const resultsList = document.getElementById('appt-customer-search-results');
        const hiddenInput = document.getElementById('appt-selected-customer-name');
        const customerInput = document.getElementById('appt-customer-name-input');
        
        resultsList.innerHTML = '';
        hiddenInput.value = ''; // Clear selected customer name on every search/input change

        const trimmedQuery = query.trim().toLowerCase();
        
        // If the query is empty or too short, just hide the list
        if (trimmedQuery.length === 0) {
            resultsList.style.display = 'none';
            // If the input is cleared, clear the required flag on the hidden input until a selection is made
            hiddenInput.removeAttribute('value');
            customerInput.setCustomValidity('Please select a customer from the list.');
            return;
        }

        const filteredCustomers = this.customers.filter(customer =>
            customer.name.toLowerCase().includes(trimmedQuery)
        ).slice(0, 10); // Limit to 10 results for performance

        if (filteredCustomers.length > 0) {
            filteredCustomers.forEach(customer => {
                const listItem = document.createElement('li');
                listItem.className = 'appt-search-result-item';
                listItem.textContent = customer.name;
                
                // Click listener to select the customer
                listItem.addEventListener('click', () => {
                    customerInput.value = customer.name;
                    hiddenInput.value = customer.name; // Set the value for form submission
                    customerInput.setCustomValidity(''); // Mark as valid
                    resultsList.style.display = 'none';
                });
                resultsList.appendChild(listItem);
            });
            resultsList.style.display = 'block';
            customerInput.setCustomValidity('Please select a customer from the list.');
        } else {
            const listItem = document.createElement('li');
            listItem.className = 'appt-search-result-item no-results';
            listItem.textContent = 'No results found.';
            resultsList.appendChild(listItem);
            resultsList.style.display = 'block';
            customerInput.setCustomValidity('Customer not found. Please select an existing customer.');
        }
    }

    updateCurrentDateDisplay() {
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        document.getElementById('appt-current-date-display').textContent =
            new Date().toLocaleDateString('en-US', options);
    }

    generateCalendarGrid() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();

        document.getElementById('appt-month-year-display').textContent =
            new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

        const firstDayOfMonth = new Date(year, month, 1);
        const lastDayOfMonth = new Date(year, month + 1, 0);
        const firstDayWeekday = firstDayOfMonth.getDay();
        const daysInMonth = lastDayOfMonth.getDate();

        const grid = document.getElementById('appt-calendar-grid');
        const existingCells = grid.querySelectorAll('.appt-day-cell');
        existingCells.forEach(cell => cell.remove());

        const totalCells = 42;

        for (let i = 0; i < totalCells; i++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'appt-day-cell';

            const dayNumber = i - firstDayWeekday + 1;

            if (dayNumber <= 0 || dayNumber > daysInMonth) {
                dayCell.style.visibility = 'hidden';
            } else {
                const dateKey = `${year}-${month}-${dayNumber}`;
                const cellDate = new Date(year, month, dayNumber);
                const today = new Date();

                let checkboxHtml = '';
                if (cellDate >= today.setHours(0, 0, 0, 0) && cellDate.getDay() !== 0) {
                    checkboxHtml = `<input type="checkbox" class="appt-day-checkbox" data-date="${dateKey}">`;
                }

                dayCell.innerHTML = `<div class="appt-day-number">${checkboxHtml}${dayNumber}</div>`;

                today.setTime(new Date().getTime());
                if (year === today.getFullYear() &&
                    month === today.getMonth() &&
                    dayNumber === today.getDate()) {
                    dayCell.classList.add('appt-today');
                }

                if (cellDate < today.setHours(0, 0, 0, 0) || cellDate.getDay() === 0) {
                    dayCell.classList.add('past-day');
                } else {
                    dayCell.addEventListener('click', (e) => {
                        if (!e.target.classList.contains('appt-day-checkbox')) {
                            this.showTimeSlots(year, month, dayNumber);
                        }
                    });

                    const checkbox = dayCell.querySelector('.appt-day-checkbox');
                    if (checkbox) {
                        checkbox.addEventListener('change', (e) => {
                            e.stopPropagation();
                            this.handleCheckboxChange(dateKey, checkbox.checked);
                        });
                    }
                }

                const availability = this.dateAvailability[dateKey];
                const dayStatus = availability ? availability.status : 'available';

                if (dayStatus === 'unavailable') {
                    dayCell.classList.add('unavailable');
                } else if (dayStatus === 'limited') {
                    dayCell.classList.add('limited-hours');
                }
            }

            grid.appendChild(dayCell);
        }
    }

    handleCheckboxChange(dateKey, isChecked) {
        if (isChecked) {
            this.selectedDates.add(dateKey);
        } else {
            this.selectedDates.delete(dateKey);
        }
        this.updateEditControls();
    }

    updateEditControls() {
        const count = this.selectedDates.size;
        const editControls = document.getElementById('appt-edit-controls');
        document.getElementById('appt-selected-count').textContent =
            `${count} date${count !== 1 ? 's' : ''} selected`;

        if (count > 0) {
            editControls.style.display = 'flex';
        } else {
            editControls.style.display = 'none';
        }
    }

    clearSelection() {
        this.selectedDates.clear();
        this.updateEditControls();
        document.querySelectorAll('.appt-day-checkbox').forEach(cb => cb.checked = false);
    }

    openEditModal() {
        const selectedDatesArray = Array.from(this.selectedDates);
        const dateStrings = selectedDatesArray.map(dateKey => {
            const [year, month, day] = dateKey.split('-').map(Number);
            return new Date(year, month, day).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        });
        document.getElementById('appt-edit-selected-dates').textContent = `Editing ${selectedDatesArray.length} date(s): ${dateStrings.join(', ')}`;
        document.getElementById('appt-day-available').checked = true;
        this.generateTimeSlotCheckboxes();
        this.toggleTimeSlotsSection();
        document.getElementById('appt-edit-modal').style.display = 'flex';
    }

    generateTimeSlotCheckboxes() {
        const container = document.getElementById('appt-time-slots-checkboxes');
        container.innerHTML = '';
        this.timeSlots.forEach((timeSlot, index) => {
            const checkboxDiv = document.createElement('div');
            checkboxDiv.className = 'appt-time-slot-checkbox';
            const checkboxId = `time-slot-${index}`;
            checkboxDiv.innerHTML = `
                <input type="checkbox" id="${checkboxId}" value="${timeSlot}" checked>
                <label for="${checkboxId}">${timeSlot}</label>
            `;
            container.appendChild(checkboxDiv);
        });
    }

    toggleTimeSlotsSection() {
        const dayAvailable = document.getElementById('appt-day-available').checked;
        const timeSlotsSection = document.getElementById('appt-time-slots-section');
        if (dayAvailable) {
            timeSlotsSection.classList.remove('disabled');
        } else {
            timeSlotsSection.classList.add('disabled');
        }
    }

    closeEditModal() {
        document.getElementById('appt-edit-modal').style.display = 'none';
    }

    async fetchDateRules() {
        try {
            const response = await fetch('../../b/appointments/loadDateRules.php');
            const result = await response.json();

            if (result.success && Array.isArray(result.data)) {
                this.dateAvailability = {};
                result.data.forEach(rule => {
                    // Date rule format: YYYY-MM-DD
                    const dateObj = new Date(rule.date);
                    const dateKey = `${dateObj.getFullYear()}-${dateObj.getMonth()}-${dateObj.getDate()}`;

                    if (rule.status === 'unavailable') {
                        // Entire day is unavailable
                        this.dateAvailability[dateKey] = { status: 'unavailable', unavailableSlots: this.timeSlots };
                    } else if (rule.status === 'available') {
                        // Day is available, but specific time slots are marked as unavailable
                        if (!this.dateAvailability[dateKey]) {
                            this.dateAvailability[dateKey] = { status: 'available', unavailableSlots: [] };
                        }
                        // Add the specific unavailable time slot
                        const formattedSlot = this.formatTimeSlot(rule.starting_time, rule.ending_time);
                        this.dateAvailability[dateKey].unavailableSlots.push(formattedSlot);
                        // Change status to limited if slots are blocked
                        if (this.dateAvailability[dateKey].status === 'available') {
                            this.dateAvailability[dateKey].status = 'limited';
                        }
                    }
                });
            } else {
                this.dateAvailability = {};
            }
        } catch (err) {
            console.error('Error fetching date rules:', err);
            this.dateAvailability = {};
        }
    }

    async saveAvailabilityChanges() {
        const selectedDatesArray = Array.from(this.selectedDates);
        const dayStatus = document.querySelector('input[name="day_status"]:checked').value;
        const selectedTimeSlots = Array.from(document.querySelectorAll('#appt-time-slots-checkboxes input[type="checkbox"]:checked'))
            .map(cb => cb.value);

        const payload = {
            dates: []
        };

        selectedDatesArray.forEach(dateKey => {
            const [year, month, day] = dateKey.split('-').map(Number);
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            if (dayStatus === 'unavailable') {
                payload.dates.push({
                    date: dateStr,
                    status: 'unavailable',
                    unavailable_slots: []
                });
            } else {
                // Determine which slots were NOT checked (i.e., which slots should be unavailable/blocked)
                const unselectedTimeSlots = this.timeSlots.filter(slot => !selectedTimeSlots.includes(slot));
                payload.dates.push({
                    date: dateStr,
                    status: 'available',
                    unavailable_slots: unselectedTimeSlots
                });
            }
        });

        try {
            const response = await fetch('../../b/appointments/saveDateRules.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            console.log('Server Response:', result);
        } catch (err) {
            console.error('Fetch Error:', err);
        }

        await this.fetchDateRules();
        this.closeEditModal();
        this.clearSelection();
        this.generateCalendarGrid();
    }

    // Function to get available time slots for a given date string (YYYY-MM-DD)
    getAvailableTimeSlots(dateStr) {
        const dateObj = new Date(dateStr);
        const year = dateObj.getFullYear();
        const monthIndex = dateObj.getMonth();
        const day = dateObj.getDate();
        const dateKey = `${year}-${monthIndex}-${day}`;
        const availability = this.dateAvailability[dateKey];
        const isSunday = dateObj.getDay() === 0;

        // Check for full unavailability based on date_rules and Sundays
        if (isSunday || (availability && availability.status === 'unavailable')) {
            return { slots: [], reason: isSunday ? 'Closed on Sundays' : 'Date is fully unavailable' };
        }

        let unavailableRuleSlots = new Set();
        if (availability && availability.status === 'limited') {
            unavailableRuleSlots = new Set(availability.unavailableSlots);
        }

        // Check for slots occupied by existing appointments
        const occupiedSlots = this.getAppointmentsForDate(year, monthIndex, day).map(appt => appt.time);

        // Filter out slots that are rule-unavailable or occupied
        const availableSlots = this.timeSlots.filter(slot => {
            const isRuleUnavailable = unavailableRuleSlots.has(slot);
            const isOccupied = occupiedSlots.includes(slot);
            return !isRuleUnavailable && !isOccupied;
        });

        return { slots: availableSlots, reason: null };
    }

    // Function to get appointments for a given date
    getAppointmentsForDate(year, monthIndex, day) {
        return this.appointments.filter(appt => {
            return appt.date.getFullYear() === year &&
                appt.date.getMonth() === monthIndex &&
                appt.date.getDate() === day;
        });
    }

    // Function to populate the time slot dropdown for new appointment
    populateNewAppointmentTimeSlots() {
        const dateInput = document.getElementById('appt-new-date-input').value;
        const timeSlotSelect = document.getElementById('appt-time-slot-select');
        const statusDiv = document.getElementById('appt-add-status-message');

        // Clear previous options and messages
        timeSlotSelect.innerHTML = '';
        this.clearAddStatusMessage();

        if (!dateInput) {
            timeSlotSelect.disabled = true;
            return;
        }

        // Get available slots for the selected date
        const dateObj = new Date(dateInput);
        const dateKey = `${dateObj.getFullYear()}-${dateObj.getMonth()}-${dateObj.getDate()}`;
        const today = new Date();
        const isPastDay = dateObj < today.setHours(0, 0, 0, 0);

        if (isPastDay) {
            statusDiv.textContent = 'Cannot schedule appointments for a past date.';
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#f44336';
            timeSlotSelect.disabled = true;
            return;
        }

        const { slots, reason } = this.getAvailableTimeSlots(dateInput);
        timeSlotSelect.disabled = false;

        if (slots.length > 0) {
            slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot;
                option.textContent = slot;
                timeSlotSelect.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = reason || 'No time slots available for this day.';
            timeSlotSelect.appendChild(option);
            timeSlotSelect.disabled = true;

            statusDiv.textContent = reason || 'This day is fully booked or unavailable.';
            statusDiv.style.display = 'block';
            statusDiv.style.color = '#f44336';
        }
    }

    // Function to open the Add New Appointment Modal
    openAddModal() {
        this.clearAddStatusMessage(); // Clear status on opening
        // Clear customer search/selection fields on opening
        document.getElementById('appt-customer-name-input').value = '';
        document.getElementById('appt-selected-customer-name').value = '';
        const resultsList = document.getElementById('appt-customer-search-results');
        if(resultsList) { 
            resultsList.innerHTML = '';
            resultsList.style.display = 'none';
        }

        // Set default date to today's date in YYYY-MM-DD format
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const defaultDate = `${year}-${month}-${day}`;
        document.getElementById('appt-new-date-input').value = defaultDate;

        // Populate time slots for the default date
        this.populateNewAppointmentTimeSlots();
        document.getElementById('appt-add-new-modal').style.display = 'flex';
    }

    // Function to close the Add New Appointment Modal
    closeAddModal() {
        document.getElementById('appt-add-new-modal').style.display = 'none';
        document.getElementById('appt-new-appointment-form').reset();
        this.clearAddStatusMessage(); // Also clear status on closing
    }

    // UPDATED: Function to handle saving the new appointment using addAppointment.php
    async saveNewAppointment(event) {
        event.preventDefault();

        this.clearAddStatusMessage(); // Clear previous messages

        // Get value from the HIDDEN input which is set upon clicking an item in the search list
        const customerName = document.getElementById('appt-selected-customer-name').value;
        const dateInput = document.getElementById('appt-new-date-input').value; // YYYY-MM-DD format
        const timeSlot = document.getElementById('appt-time-slot-select').value;

        if (!customerName || !dateInput || !timeSlot) {
            // Replaced alert with status message
            this.showAddStatusMessage("Please fill in all required fields.");
            return;
        }

        const payload = {
            customerName: customerName,
            dateInput: dateInput,
            timeSlot: timeSlot
        };

        try {
            const response = await fetch('../../b/appointments/addAppointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            
            if (result.success) {
                // Replaced alert with status message
                this.showAddStatusMessage(result.message || 'Appointment successfully booked!', true);
                
                // Refresh data and close modal after a short delay to allow user to see the success message
                await this.fetchAppointments();
                await this.fetchDateRules();
                setTimeout(() => {
                    this.closeAddModal();
                    this.generateCalendarGrid();
                }, 1500); // Wait 1.5 seconds before closing
            } else {
                // Display error message from the server (including the new duplicate appointment message)
                this.showAddStatusMessage(result.message || 'Failed to book appointment.');
            }
        } catch (err) {
            console.error('Error saving appointment:', err);
            this.showAddStatusMessage('An unexpected error occurred. Please try again.');
        }
    }

    showTimeSlots(year, month, day) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dateObj = new Date(year, month, day);

        document.getElementById('appt-selected-date').textContent = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('appt-modal-title').textContent = 'Appointments for the Day';

        const timeSlotsContainer = document.getElementById('appt-time-slots-container');
        timeSlotsContainer.innerHTML = '';

        const { slots: availableSlots, reason } = this.getAvailableTimeSlots(dateStr);
        const appointmentsForDay = this.getAppointmentsForDate(year, month, day);

        if (reason) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'appt-time-slot';
            messageDiv.style.cssText = 'text-align: center; color: #f44336; font-style: italic;';
            messageDiv.textContent = reason;
            timeSlotsContainer.appendChild(messageDiv);
        } else {
            this.timeSlots.forEach(timeSlot => {
                const slotDiv = document.createElement('div');
                slotDiv.className = 'appt-time-slot';

                const isRuleUnavailable = !availableSlots.includes(timeSlot) && appointmentsForDay.every(appt => appt.time !== timeSlot);
                const appointment = appointmentsForDay.find(appt => appt.time === timeSlot);

                if (isRuleUnavailable) {
                    slotDiv.style.background = '#ffebee'; // Unavailable color
                    slotDiv.style.color = '#f44336';
                    slotDiv.innerHTML = `
                        <div>${timeSlot}</div>
                        <div class="appt-availability-status">Unavailable (Admin Block)</div>
                    `;
                } else if (appointment) {
                    slotDiv.classList.add('has-appointment');
                    slotDiv.innerHTML = `
                        <div>${timeSlot}</div>
                        <div class="appt-appointment-title">${appointment.title} (Booked)</div>
                    `;
                } else {
                    slotDiv.innerHTML = `
                        <div>${timeSlot}</div>
                        <div class="appt-appointment-title" style="color: #481E14; font-style: italic;">Available</div>
                    `;
                }
                timeSlotsContainer.appendChild(slotDiv);
            });
        }
        document.getElementById('appt-event-modal').style.display = 'flex';
    }

    convertTo24Hour(time12h) {
        const [time, modifier] = time12h.split(' ');
        let [hours, minutes] = time.split(':');
        if (hours === '12') {
            hours = '00';
        }
        if (modifier === 'PM') { // Assuming standard AM/PM format if you were to use it
            hours = parseInt(hours, 10) + 12;
        }
        return `${hours.toString().padStart(2, '0')}:${minutes}`;
    }

    convertTimeToMinutes(time) {
        const [hours, minutes] = time.split(':').map(Number);
        return hours * 60 + minutes;
    }

    closeModal() {
        document.getElementById('appt-event-modal').style.display = 'none';
    }
}

// Initialize the calendar manager
document.addEventListener('DOMContentLoaded', () => {
    new AppointmentCalendarManager();
});