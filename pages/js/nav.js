// Function to load the navigation fragment and then execute the main script logic
function loadAndExecuteNav() {
    const placeholder = document.getElementById('nav-placeholder');
    
    if (!placeholder) {
        console.error("Navigation placeholder not found. Ensure there is a div with id='nav-placeholder'.");
        // If placeholder isn't found, stop execution of the nav logic.
        return; 
    }

    // --- STEP 1: FETCH AND INJECT NAV.HTML CONTENT ---
    fetch('../nav.html')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(htmlContent => {
            // Insert the HTML content into the placeholder div
            placeholder.innerHTML = htmlContent;

            // --- STEP 2: DEFINE & EXECUTE ORIGINAL NAV.JS LOGIC (NOW IN-LINE) ---
            
            // Re-select elements now that they are in the DOM
            let navSidebar = document.querySelector(".nav-sidebar");
            let navCloseBtn = document.querySelector("#nav-btn");
            let dropdown = document.querySelector(".nav-dropdown");
            let arrow = document.querySelector(".nav-dropdown-arrow");
            let dropdown2 = document.querySelector(".nav-dropdown-2");
            let arrow2 = document.querySelector(".nav-dropdown-arrow-2");
            let logoutBtn = document.querySelector("#nav-log_out");
            let censor = document.querySelector(".censor");
            const countdownElement = document.getElementById('inactivity-countdown');
            const heartbeatBtn = document.getElementById('heartbeat-button');
            
            // --- Session & Inactivity Timer Logic ---
            const INACTIVITY_TIMEOUT = 60 * 60 * 1000; // 60 minutes
            let timeoutTimer;
            let countdownInterval;
            let targetExpiryTime;
            const HEARTBEAT_TIMER_INTERVAL = 1000;

            function autoLogout() {
                console.log("Inactivity detected. Logging out...");
                window.location.href = '../../b/logout.php';
            }

            function updateDisplay() {
                let timeLeft = targetExpiryTime - Date.now();
                if (timeLeft < 0) {
                    timeLeft = 0;
                    clearInterval(countdownInterval);
                }
                const minutes = Math.floor(timeLeft / 60000);
                const seconds = Math.floor((timeLeft % 60000) / 1000);
                if (countdownElement) {
                    countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                }
            }

            function startCountdown() {
                clearInterval(countdownInterval);
                updateDisplay();
                countdownInterval = setInterval(updateDisplay, HEARTBEAT_TIMER_INTERVAL);
            }

            let resetTimer = function () {
                clearTimeout(timeoutTimer);
                targetExpiryTime = Date.now() + INACTIVITY_TIMEOUT;
                timeoutTimer = setTimeout(autoLogout, INACTIVITY_TIMEOUT);
                startCountdown();
                sendHeartbeat();
            };
            
            // Function to send a heartbeat request
            function sendHeartbeat() {
                fetch('../../b/updLA.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log("Heartbeat success:", data.message);
                        } else {
                            console.error("Heartbeat failed:", data.message);
                        }
                    })
                    .catch(error => console.error('Error during heartbeat:', error));
            }


            // --- RBAC Logic ---

            function applyRbac(allowedLinks) {
                const allowedLinksSet = new Set(allowedLinks);
                const allListItems = navSidebar.querySelectorAll('.nav-list-item');

                allListItems.forEach(item => {
                    let linkText = '';
                    const linkNameEl = item.querySelector('.nav-links_name');
                    if (linkNameEl) {
                        linkText = linkNameEl.textContent.trim();
                    }
                    const dropdownHeaderEl = item.querySelector('.nav-dropdown-header .nav-links_name, .nav-dropdown-2-header .nav-links_name');
                    if (dropdownHeaderEl) {
                        linkText = dropdownHeaderEl.textContent.trim();
                    }
                    if (linkText && !allowedLinksSet.has(linkText)) {
                        item.remove();
                    }
                });

                const currentDropdown = document.querySelector(".nav-dropdown");
                const currentDropdown2 = document.querySelector(".nav-dropdown-2");
                const dropdownContainers = [currentDropdown, currentDropdown2];
                
                dropdownContainers.forEach(container => {
                    if (container) {
                        const submenu = container.querySelector('.nav-submenu');
                        if (submenu && submenu.children.length === 0) {
                            container.remove();
                        }
                    }
                });
            }


            // --- Fetch Permissions and Session Data and Update UI ---
            function fetchSessionAndPermissions() {
                fetch('../../b/session.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.is_logged_in && data.user_data && data.user_data.role) {
                            const userData = data.user_data;
                            const role = userData.role.toUpperCase();

                            return fetch(`../../b/permissions.php?role=${role}`)
                                .then(response => response.json())
                                .then(rbacData => {
                                    if (rbacData.success && rbacData.allowed_links) {
                                        applyRbac(rbacData.allowed_links);

                                        const nameElement = document.querySelector('.nav-profile .nav-name');
                                        const jobElement = document.querySelector('.nav-profile .nav-job');
                                        
                                        censor.classList.remove("active");
                                        navSidebar.classList.remove("hidden");

                                        if (nameElement) {
                                            const fullName = (userData.fname && userData.lname) ?
                                                `${userData.fname} ${userData.lname}` :
                                                (userData.username);
                                            nameElement.textContent = fullName;
                                        }
                                        if (jobElement) {
                                            jobElement.textContent = role;
                                        }
                                    } else {
                                        console.error("Failed to fetch RBAC data or role missing.");
                                        window.location.href = '../../b/logout.php'; 
                                    }
                                });

                        } else {
                            window.location.href = '../../b/logout.php';
                            console.log("User not logged in or session expired.");
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching session or RBAC data:', error);
                    });
            }
            
            // --- Sidebar UI Logic ---

            function menuBtnChange() {
                if (navSidebar.classList.contains("nav-open")) {
                    navCloseBtn.classList.replace("fa-bars", "fa-chevron-left");
                } else {
                    navCloseBtn.classList.replace("fa-chevron-left", "fa-bars");
                    if (dropdown && dropdown.classList.contains("active")) {
                        dropdown.classList.remove("active");
                    }
                }
            }
            
            // Start the timer and fetch data immediately
            resetTimer(); 
            fetchSessionAndPermissions(); 
            
            // Event listeners for user activity (reset timer)
            document.addEventListener('mousemove', resetTimer);
            document.addEventListener('keypress', resetTimer);
            document.addEventListener('click', resetTimer);
            document.addEventListener('scroll', resetTimer);


            // Sidebar mouseover/mouseout logic
            navSidebar.addEventListener("mouseover", () => {
                if (!navSidebar.classList.contains("nav-open")) {
                    if (arrow) arrow.classList.add("active");
                    if (arrow2) arrow2.classList.add("active");
                    navSidebar.classList.add("nav-open");
                    menuBtnChange();
                }
            });

            navSidebar.addEventListener("mouseout", () => {
                if (arrow) arrow.classList.remove("active");
                if (arrow2) arrow2.classList.remove("active");
                navSidebar.classList.remove("nav-open");
                menuBtnChange();
            });

            // Handle logout button click
            if (logoutBtn) {
                logoutBtn.addEventListener('click', autoLogout);
            }

            // Dropdown hover for the wide sidebar state
            if (dropdown) {
                dropdown.addEventListener("mouseover", () => { dropdown.classList.add("active"); });
                dropdown.addEventListener("mouseout", () => { dropdown.classList.remove("active"); });
            }

            if (dropdown2) {
                dropdown2.addEventListener("mouseover", () => { dropdown2.classList.add("active"); });
                dropdown2.addEventListener("mouseout", () => { dropdown2.classList.remove("active"); });
            }

            // Add manual heartbeat button action
            if (heartbeatBtn) {
                heartbeatBtn.addEventListener('click', () => {
                    sendHeartbeat();
                    resetTimer();
                    alert("Session refreshed (Heartbeat sent and Inactivity Timer reset!)");
                });
            }

        })
        .catch(error => console.error('Error loading navigation:', error));
}

// Start the whole process when the DOM of the main page is ready
document.addEventListener('DOMContentLoaded', loadAndExecuteNav);