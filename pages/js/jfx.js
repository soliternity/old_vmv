document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.getElementById("loginForm");
  const invalidMsg = document.getElementById("invalidMsg");
  const togglePassword = document.querySelector(".toggle-password");
  const passwordInput = document.getElementById("password");

  // Toggle password visibility
  togglePassword.addEventListener("click", function () {
    const type = passwordInput.type === "password" ? "text" : "password";
    passwordInput.type = type;
    this.querySelector("i").classList.toggle("fa-eye-slash");
    this.querySelector("i").classList.toggle("fa-eye");
  });

  // Allow toggle by keyboard
  togglePassword.addEventListener("keydown", function (e) {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      togglePassword.click();
    }
  });

  // Handle form submission
  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();
    invalidMsg.style.display = "none";

    const formData = new FormData(loginForm);

    fetch("../../b/jfx/jfxLogin.php", {
      method: "POST",
      body: formData
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          window.location.href = "../dashboard"; // Change as needed
        } else {
          invalidMsg.style.display = "block";
        }
      })
      .catch(() => {
        invalidMsg.style.display = "block";
      });
  });
});
