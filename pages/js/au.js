// Global variables to store the fetched data
let allUsers = [];
let allCars = [];

const userListContainer = document.getElementById('au-user-list');
const searchInput = document.getElementById('au-search-input');
// New element reference for the sort select
const sortSelect = document.getElementById('au-sort-select'); 
const modal = document.getElementById('au-modal');
const modalDetails = document.getElementById('au-modal-details');
const closeButton = document.querySelector('.au-close-button');

/**
 * Fetches user and car data from the backend and renders the user cards.
 */
async function fetchDataAndRender() {
    try {
        const response = await fetch('../../b/au/fetchAU.php');
        const data = await response.json();
        
        // Check for the 'error' key in the JSON response
        if (data.error) {
            // Redirect the user if the server indicates an error
            window.location.href = 'dsh.html';
            return; // Stop further execution
        }
        
        // Store the fetched data in global variables
        allUsers = data.users;
        allCars = data.cars;
        
        // Initial filter/sort on load (will use default 'desc' from HTML)
        filterUsers();
        
    } catch (error) {
        console.error('Error fetching data:', error);
        // Display error message in the table body
        document.getElementById('au-table-body').innerHTML = '<tr><td colspan="3" class="au-error-message">Failed to load user data. Please try again later.</td></tr>';
    }
}

/**
 * Renders user cards based on the provided user array.
 */
function renderUserCards(userArray) {
    const tableBody = document.getElementById('au-table-body');
    tableBody.innerHTML = '';
    
    // Check for empty data and display a message
    if (userArray.length === 0) {
        // Set the table body content to a single row with a 'No users found' message
        tableBody.innerHTML = '<tr><td colspan="3" class="au-no-results">No users found.</td></tr>';
        return;
    }

    userArray.forEach(user => {
        const row = document.createElement('tr');
        row.classList.add('au-table-row');
        row.dataset.userId = user.id;

        const fullName = `${user.fname} ${user.mname ? user.mname + ' ' : ''}${user.lname}`;
        // Ensure date is a valid object for formatting
        const joinDate = user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A';
        
        // Create table data cells
        row.innerHTML = `
            <td><i class="fas fa-user au-table-icon"></i> ${fullName}</td>
            <td>${joinDate}</td>
            <td><button class="au-view-btn">View Details</button></td>
        `;

        // Add event listener to the 'View Details' button
        const viewButton = row.querySelector('.au-view-btn');
        if (viewButton) {
            viewButton.addEventListener('click', (event) => {
                event.stopPropagation(); // Stop row click from also firing
                displayAllUserInfo(user);
            });
        }
        
        tableBody.appendChild(row);
    });
}

/**
 * Displays user and car details in a modal.
 */
function displayAllUserInfo(user) {
    const userCars = allCars.filter(car => car.appuser_id === user.id);
    let carsHtml = '';

    if (userCars.length > 0) {
        carsHtml = '<h3><i class="fas fa-car-side"></i> Car Details</h3>';
        userCars.forEach(car => {
            carsHtml += `
                <div class="au-car-details">
                    <p><strong>Brand:</strong> ${car.brand}</p>
                    <p><strong>Color:</strong> ${car.color}</p>
                    <p><strong>Plate:</strong> ${car.plate}</p>
                </div>
            `;
        });
    } else {
        carsHtml = '<p class="au-no-cars">No cars registered for this user.</p>';
    }

    modalDetails.innerHTML = `
        <h3><i class="fas fa-user-circle"></i> User Details</h3>
        <p><strong>Name:</strong> ${user.fname} ${user.mname ? user.mname + ' ' : ''}${user.lname}</p>
        <p><strong>Member Since:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
        ${carsHtml}
    `;
    modal.style.display = 'block';
}

/**
 * Filters the user list based on the search input, then sorts the result.
 */
function filterUsers() {
    const searchTerm = searchInput.value.toLowerCase();
    
    // 1. Filtering (Search)
    const filteredUsers = allUsers.filter(user => {
        const fullName = `${user.fname} ${user.mname} ${user.lname}`.toLowerCase();
        return fullName.includes(searchTerm);
    });
    
    // 2. Sorting
    const sortOrder = sortSelect.value;
    
    filteredUsers.sort((a, b) => {
        // Convert to Date objects for comparison
        const dateA = new Date(a.created_at);
        const dateB = new Date(b.created_at);
        
        if (sortOrder === 'asc') {
            return dateA - dateB; // Oldest first (Ascending)
        } else {
            return dateB - dateA; // Newest first (Descending)
        }
    });
    
    // 3. Render
    renderUserCards(filteredUsers);
}

// Event Listeners
searchInput.addEventListener('keyup', filterUsers);
// New Event Listener for sorting
sortSelect.addEventListener('change', filterUsers);

closeButton.addEventListener('click', () => {
    modal.style.display = 'none';
});

window.addEventListener('click', (event) => {
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Initial data fetch and render on page load
// The function now calls filterUsers() which handles the initial render
fetchDataAndRender();