document.addEventListener('DOMContentLoaded', () => {
    const b_jobList = document.getElementById('b-job-list');
    const b_searchInput = document.getElementById('b-search-input');
    const b_paymentModal = document.getElementById('b-payment-modal');
    const b_closeBtn = document.querySelector('.b-close-btn');

    let b_allJobs = [];
    let b_currentJob = null;

    // Custom overlay for alerts
    const b_showOverlay = (message, isSuccess = false) => {
        const overlay = document.createElement('div');
        overlay.className = 'b-alert-overlay';

        const box = document.createElement('div');
        box.className = 'b-alert-box';
        box.innerHTML = `
            <p>${message}</p>
            <button class="b-alert-close">OK</button>
        `;
        overlay.appendChild(box);

        document.body.appendChild(overlay);

        box.querySelector('.b-alert-close').addEventListener('click', () => {
            document.body.removeChild(overlay);
        });

        if (isSuccess) {
            box.style.backgroundColor = '#4CAF50';
            box.style.color = '#fff';
        } else {
            box.style.backgroundColor = '#f44336';
            box.style.color = '#fff';
        }
    };

    // Add necessary CSS for the overlay dynamically
    const style = document.createElement('style');
    style.innerHTML = `
        .b-alert-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .b-alert-box {
            padding: 20px 40px;
            border-radius: 5px;
            text-align: center;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .b-alert-box p {
            margin: 0 0 15px;
        }
        .b-alert-close {
            background-color: #fff;
            color: #333;
            border: none;
            padding: 8px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1rem;
        }
    `;
    document.head.appendChild(style);

    const b_fetchJobs = async () => {
        try {
            const response = await fetch('../../b/bill/fetchCJ.php');
            const data = await response.json();

            if (data.success) {
                b_allJobs = data.jobs;
                b_renderJobs(b_allJobs);
            } else {
                console.error('Failed to fetch jobs:', data.message);
                b_jobList.innerHTML = '<p class="b-error-message">Failed to load jobs. Please try again later.</p>';
            }
        } catch (error) {
            console.error('Error fetching jobs:', error);
            b_jobList.innerHTML = '<p class="b-error-message">Network error. Check your connection.</p>';
        }
    };

    const b_renderJobs = (jobs) => {
        b_jobList.innerHTML = '';
        if (jobs.length === 0) {
            b_jobList.innerHTML = '<p class="b-no-results-message">No completed jobs found.</p>';
            return;
        }

        jobs.forEach(job => {
            const card = document.createElement('div');
            card.className = 'b-job-card';
            card.innerHTML = `
                <div class="b-card-details">
                    <h4>Job ID: ${job.job_id}</h4>
                    <p><strong>Customer:</strong> ${job.customer.name}</p>
                    <p><strong>Vehicle:</strong> ${job.customer.vehicle.brand} ${job.customer.vehicle.color} (${job.customer.vehicle.plate})</p>
                    <p><strong>Mechanic:</strong> ${job.mechanic.name || 'N/A'}</p>
                    <p class="b-card-status">Completed</p>
                </div>
                <button class="b-proceed-btn" data-job-display-id="${job.job_id}">Proceed to Payment</button>
            `;
            b_jobList.appendChild(card);
        });
    };

    b_fetchJobs();

    b_searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const filteredJobs = b_allJobs.filter(job =>
            job.customer.name.toLowerCase().includes(searchTerm) ||
            job.job_id.toLowerCase().includes(searchTerm) ||
            job.customer.vehicle.plate.toLowerCase().includes(searchTerm)
        );
        b_renderJobs(filteredJobs);
    });

    b_jobList.addEventListener('click', async (e) => {
        if (e.target.classList.contains('b-proceed-btn')) {
            const jobDisplayId = e.target.dataset.jobDisplayId;
            try {
                const response = await fetch(`../../b/bill/fetchBill.php?job_id=${jobDisplayId}`);
                const data = await response.json();

                if (data.success) {
                    b_currentJob = data.job;
                    b_populatePaymentModal(b_currentJob);
                    b_paymentModal.style.display = 'block';
                } else {
                    b_showOverlay('Error: ' + data.message);
                    console.error('Error fetching bill details:', data.message);
                }
            } catch (error) {
                b_showOverlay('An error occurred. Please try again.');
                console.error('Network error fetching bill details:', error);
            }
        }
    });

    b_closeBtn.addEventListener('click', () => {
        b_paymentModal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === b_paymentModal) {
            b_paymentModal.style.display = 'none';
        }
    });

    const b_populatePaymentModal = (job) => {
        const b_paymentDetails = document.getElementById('b-payment-details');
        
        // This array contains costs fetched from the database by fetchBill.php
        let additionalCosts = job.additional_costs || [];
        let totalCost = job.services_cost + additionalCosts.reduce((sum, cost) => sum + cost.cost, 0);

        b_paymentDetails.innerHTML = `
            <div class="b-payment-section">
                <h4>Customer & Job Details</h4>
                <p class="b-details-item"><strong>Customer Name:</strong> ${job.customer.name}</p>
                <p class="b-details-item"><strong>Vehicle:</strong> ${job.customer.vehicle.brand} ${job.customer.vehicle.color} (${job.customer.vehicle.plate})</p>
                <p class="b-details-item"><strong>Job ID:</strong> ${job.job_id}</p>
                <p class="b-details-item"><strong>Date Created:</strong> ${job.date_created}</p>
                <p class="b-details-item"><strong>Mechanic:</strong> ${job.mechanic.name || 'N/A'}</p>
            </div>

            <div class="b-payment-section">
                <h4>Services Rendered</h4>
                <ul id="b-services-list">
                    ${job.services.map(s => `<li>${s.name}: ₱${parseFloat(s.cost).toLocaleString()}</li>`).join('')}
                </ul>
            </div>
            
            <div id="b-additional-costs-section" class="b-payment-section">
                <h4>Additional Costs</h4>
                <ul id="b-additional-costs-list"></ul>
            </div>

            <div class="b-final-summary">
                <p class="b-total-cost">Total: <span id="b-total-cost-display">₱${totalCost.toLocaleString()}</span></p>
                <div class="b-payment-input-group">
                    <label for="b-amount-paid">Amount Paid:</label>
                    <input type="number" id="b-amount-paid" placeholder="Enter amount received" step="0.01">
                </div>
                <p class="b-change">Change: <span id="b-change-display">₱0.00</span></p>
            </div>

            <form id="b-payment-form" class="b-payment-form">
                <div class="b-payment-section">
                </div>
                <button type="submit" class="b-submit-payment-btn">Submit Payment</button>
            </form>
        `;

        // Removed variables: b_addCostBtn, b_additionalReasonInput, b_additionalCostInput
        const b_additionalCostsList = document.getElementById('b-additional-costs-list');
        const b_totalCostDisplay = document.getElementById('b-total-cost-display');
        const b_amountPaidInput = document.getElementById('b-amount-paid');
        const b_changeDisplay = document.getElementById('b-change-display');
        const b_paymentForm = document.getElementById('b-payment-form');
        
        const b_updateTotalCost = () => {
            const serviceCost = job.services.reduce((sum, service) => sum + parseFloat(service.cost), 0);
            const additionalCost = additionalCosts.reduce((sum, cost) => sum + cost.cost, 0);
            totalCost = serviceCost + additionalCost;
            b_totalCostDisplay.textContent = `₱${totalCost.toLocaleString()}`;
            b_updateChange();
        };

        const b_updateChange = () => {
            const amountPaid = parseFloat(b_amountPaidInput.value) || 0;
            const change = amountPaid - totalCost;
            b_changeDisplay.textContent = `₱${change.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };

        const b_renderAdditionalCosts = () => {
            b_additionalCostsList.innerHTML = '';
            // Only renders existing/fetched costs
            additionalCosts.forEach((item) => {
                const li = document.createElement('li');
                li.className = 'b-additional-cost-item';
                li.innerHTML = `
                    <span>${item.reason}: ₱${item.cost.toLocaleString()}</span>
                `;
                b_additionalCostsList.appendChild(li);
            });
            b_updateTotalCost();
        };

        // Removed event listener for b_addCostBtn
        // Removed event listener for b_additionalCostsList
        
        b_amountPaidInput.addEventListener('input', b_updateChange);

        b_paymentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const paymentMethod = "Cash";
            const amountPaid = parseFloat(b_amountPaidInput.value);
            const changeGiven = amountPaid - totalCost;

            if (!paymentMethod) {
                b_showOverlay('Please select a payment method.');
                return;
            }
            if (isNaN(amountPaid) || amountPaid < totalCost) {
                b_showOverlay('The amount paid cannot be less than the total cost.');
                return;
            }

            const paymentData = {
                job_id: b_currentJob.id,
                job_display_id: b_currentJob.job_id, // Pass job display ID
                total_cost: totalCost,
                amount_paid: amountPaid,
                change_given: changeGiven,
                payment_method: paymentMethod,
                additional_costs: additionalCosts, // This array only contains fetched/existing costs
                // Pass all receipt data to the server
                customer_name: b_currentJob.customer.name,
                vehicle_details: b_currentJob.customer.vehicle,
                mechanic_name: b_currentJob.mechanic.name,
                services: b_currentJob.services
            };

            try {
                const response = await fetch('../../b/bill/addPay.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(paymentData)
                });

                const data = await response.json();

                if (data.success) {
                    b_showOverlay('Payment processed successfully! The receipt will open in a new tab.', true);
                    b_paymentModal.style.display = 'none';
                    if (data.receipt_url) {
                        window.open(data.receipt_url, '_blank');
                    }
                    b_fetchJobs();
                } else {
                    b_showOverlay('Payment failed: ' + data.message);
                    console.error('Payment submission failed:', data.message);
                }
            } catch (error) {
                b_showOverlay('A network error occurred during payment. Please try again.');
                console.error('Network error during payment:', error);
            }
        });
        
        b_renderAdditionalCosts();
        b_updateTotalCost();
    };
});