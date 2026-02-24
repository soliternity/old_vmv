document.addEventListener('DOMContentLoaded', function () {
    const LoginBtn = document.getElementById('LoginBtn');
    const DownloadBtn = document.getElementById('DownloadBtn'); // New button
    const Overlay = document.getElementById('Overlay');
    const CloseOverlay = document.getElementById('CloseOverlay');
    const JeffixLogin = document.getElementById('JeffixLogin');
    const AdminLogin = document.getElementById('AdminLogin');

    // Event listener for "Sign In" button in the navigation
    if (LoginBtn) {
        LoginBtn.addEventListener('click', function () {
            Overlay.classList.add('overlay-active');
        });
    }

    // Event listener for "Download Application" button
    if (DownloadBtn) {
        DownloadBtn.addEventListener('click', function () {
            window.location.href='qr_code/base(4).apk'
        });
    }

    // Event listener for the close button
    if (CloseOverlay) {
        CloseOverlay.addEventListener('click', function () {
            Overlay.classList.remove('overlay-active');
        });
    }
    
    // Event listeners for the login options
    if (JeffixLogin) {
        JeffixLogin.addEventListener('click', function () {
            window.location.href = 'pages/staff-login'; // Redirect to Jeffix login page
        });
    }
    if (AdminLogin) {
        AdminLogin.addEventListener('click', function () {
            window.location.href = 'pages/admin-login'        // Add your Admin login logic here
        });
    }


    // Close overlay when clicking outside the content
    if (Overlay) {
        Overlay.addEventListener('click', function (e) {
            if (e.target === Overlay) {
                Overlay.classList.remove('overlay-active');
            }
        });
    }
});

// Mobile menu toggle
const menuToggle = document.getElementById('menuToggle');
const mainNav = document.getElementById('mainNav');

if (menuToggle && mainNav) {
  menuToggle.addEventListener('click', function() {
    mainNav.classList.toggle('active');
    
    if (mainNav.classList.contains('active')) {
      menuToggle.innerHTML = '<i class="fas fa-times"></i>';
    } else {
      menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    }
  });
}

// Close menu when clicking on a link
const navLinks = document.querySelectorAll('.nav a');
navLinks.forEach(link => {
  link.addEventListener('click', () => {
    mainNav.classList.remove('active');
    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
  });
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    
    const targetId = this.getAttribute('href');
    if (targetId === '#') return;
    
    const targetElement = document.querySelector(targetId);
    if (targetElement) {
      window.scrollTo({
        top: targetElement.offsetTop - 80,
        behavior: 'smooth'
      });
    }
  });
});