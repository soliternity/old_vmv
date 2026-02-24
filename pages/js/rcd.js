document.addEventListener('DOMContentLoaded', () => {
    const recordsTableBody = document.getElementById('rcd-records-table-body');
    const searchInput = document.getElementById('rcd-search-input');
    const dateFilterSelect = document.getElementById('rcd-date-filter'); // New
    const timeSortSelect = document.getElementById('rcd-time-sort');   // New

    let allTransactions = []; // Holds the original, unfiltered data

    const fetchTransactions = async () => {
        try {
            const response = await fetch('../../b/rcd/fetchReceipts.php');
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            allTransactions = await response.json();
            
            // Augment transactions with a Date object for reliable sorting/filtering
            allTransactions = allTransactions.map(t => ({
                ...t,
                // Create a Date object from the payment_date string
                payment_datetime: new Date(t.payment_date) 
            }));

            applyFiltersAndSort();
        } catch (error) {
            console.error('Error fetching transactions:', error);
            recordsTableBody.innerHTML = '<tr><td colspan="7" class="rcd-no-results">Failed to load records.</td></tr>';
        }
    };

    // Helper function to check if a date falls within a given range
    const isDateInRange = (dateString, range) => {
        // Use the augmented Date object for comparison
        const transactionDate = new Date(dateString); 
        if (isNaN(transactionDate)) return false; 

        const now = new Date();
        // Helper to get the start of the day (midnight) for accurate range checks
        const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());

        const todayStart = startOfDay(now);
        
        switch (range) {
            case 'today': {
                // Check if the date is today (from midnight to now)
                return transactionDate >= todayStart && transactionDate <= now;
            }
            case 'yesterday': {
                // Check if the date is yesterday
                const yesterdayStart = startOfDay(now);
                yesterdayStart.setDate(todayStart.getDate() - 1);
                return transactionDate >= yesterdayStart && transactionDate < todayStart;
            }
            case 'last7days': {
                // Check if the date is within the last 7 days (including today)
                const sevenDaysAgo = startOfDay(now);
                sevenDaysAgo.setDate(todayStart.getDate() - 7);
                return transactionDate >= sevenDaysAgo && transactionDate <= now;
            }
            case 'thismonth': {
                // Check if the date is within the current calendar month
                const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
                return transactionDate >= startOfMonth && transactionDate <= now;
            }
            case 'all':
            default:
                return true;
        }
    };

    // Central function to apply all current filters and sort order
    const applyFiltersAndSort = () => {
        let transactions = [...allTransactions];
        const searchTerm = searchInput.value.toLowerCase().trim();
        const dateRange = dateFilterSelect.value;
        const sortOrder = timeSortSelect.value; // 'asc' or 'desc'

        // 1. Search Filter: Only search against transaction_id, invoice_number, and customer_name
        if (searchTerm) {
            transactions = transactions.filter(transaction =>
                // Check if the search term matches any of the specified fields
                String(transaction.transaction_id).toLowerCase().includes(searchTerm) ||
                String(transaction.invoice_number).toLowerCase().includes(searchTerm) ||
                String(transaction.customer_name).toLowerCase().includes(searchTerm)
            );
        }

        // 2. Date Filter
        if (dateRange !== 'all') {
            transactions = transactions.filter(transaction =>
                isDateInRange(transaction.payment_date, dateRange)
            );
        }

        // 3. Sorting (by payment_datetime)
        transactions.sort((a, b) => {
            const dateA = a.payment_datetime; 
            const dateB = b.payment_datetime;

            if (sortOrder === 'asc') {
                return dateA - dateB; // Oldest first (Ascending)
            } else {
                return dateB - dateA; // Newest first (Descending)
            }
        });

        renderTransactions(transactions);
    };

    const renderTransactions = (transactions) => {
        recordsTableBody.innerHTML = '';
        if (transactions.length === 0) {
            recordsTableBody.innerHTML = '<tr><td colspan="7" class="rcd-no-results">No records found.</td></tr>';
            return;
        }

        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${transaction.transaction_id}</td>
                <td>${transaction.invoice_number}</td>
                <td>${transaction.customer_name}</td>
                <td>${transaction.vehicle}</td>
                <td>${transaction.payment_date}</td>
                <td>${transaction.total_cost}</td>
                <td><button class="rcd-view-btn" data-transaction-id="${transaction.transaction_id}">View</button></td>
            `;
            recordsTableBody.appendChild(row);
        });
        
        // Re-add event listeners for the "View" buttons after re-rendering
        document.querySelectorAll('.rcd-view-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const transactionId = e.target.dataset.transactionId;
                const receiptUrl = `../../b/bill/receipts/receipt-${transactionId}.html`;
                window.open(receiptUrl, '_blank');
            });
        });
    };

    // Event listeners for search, filter, and sort all call the central function
    searchInput.addEventListener('input', applyFiltersAndSort);
    dateFilterSelect.addEventListener('change', applyFiltersAndSort); 
    timeSortSelect.addEventListener('change', applyFiltersAndSort); 

    // Initial load of transactions
    fetchTransactions();
});
