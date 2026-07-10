<?php
/**
 * Indus Grammar School ERP - Login Page
 * Version 1.0.0
 */

// Boot application
require_once __DIR__ . '/config/app.php';

// Force guest access only (logged in users go directly to dashboard)
AuthMiddleware::requireGuest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Indus Grammar School ERP</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/style.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom Layout CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

    <div class="auth-container">
        <!-- Logo / School Branding -->
        <div class="text-center mb-4">
            <div class="auth-logo mb-2">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <h1 class="auth-title">Indus Grammar School</h1>
            <p class="auth-subtitle">School Management ERP System</p>
        </div>

        <!-- Login Card -->
        <div class="card auth-card">
            <div class="card-body p-4 p-sm-5">
                <h3 class="card-title text-center mb-4">Sign In</h3>

                <!-- Alert Message -->
                <div id="alert-container" class="alert d-none" role="alert"></div>

                <form id="login-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

                    <!-- Username / Email -->
                    <div class="mb-4">
                        <label for="username" class="form-label">Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" placeholder="e.g. saeed" required autocomplete="username">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="password" class="form-label mb-0">Password</label>
                            <a href="forgot-password.php" class="auth-link">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control border-end-0" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary border-start-0 toggle-password-btn" type="button" id="toggle-password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me checkbox -->
                    <div class="mb-4 d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" value="1">
                            <label class="form-check-label text-secondary" for="remember_me">
                                Keep me logged in
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary w-100 py-3 d-flex justify-content-center align-items-center gap-2" id="submit-btn">
                        <span>Sign In</span>
                        <div class="spinner-border spinner-border-sm d-none" role="status" id="btn-spinner">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer Notice -->
        <div class="text-center mt-4">
            <p class="text-secondary small">&copy; <?php echo date('Y'); ?> Indus Grammar School. All rights reserved.</p>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Login Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('login-form');
            const submitBtn = document.getElementById('submit-btn');
            const btnSpinner = document.getElementById('btn-spinner');
            const alertContainer = document.getElementById('alert-container');
            const togglePasswordBtn = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('password');

            // Password visibility toggle
            togglePasswordBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-regular', 'fa-eye');
                    icon.classList.add('fa-solid', 'fa-eye-slash');
                } else {
                    icon.classList.remove('fa-solid', 'fa-eye-slash');
                    icon.classList.add('fa-regular', 'fa-eye');
                }
            });

            // Form Submit handler
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Clear previous alerts
                alertContainer.classList.add('d-none');
                alertContainer.classList.remove('alert-danger', 'alert-success');
                alertContainer.textContent = '';

                // Enable Loading State
                submitBtn.disabled = true;
                btnSpinner.classList.remove('d-none');

                const formData = new FormData(loginForm);

                fetch('ajax/auth.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alertContainer.classList.remove('d-none');
                        alertContainer.classList.add('alert-success');
                        alertContainer.textContent = data.message;
                        
                        // Redirect to Dashboard
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 800);
                    } else {
                        // Show Error
                        alertContainer.classList.remove('d-none');
                        alertContainer.classList.add('alert-danger');
                        alertContainer.textContent = data.message;
                        
                        // Disable Loading State
                        submitBtn.disabled = false;
                        btnSpinner.classList.add('d-none');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alertContainer.classList.remove('d-none');
                    alertContainer.classList.add('alert-danger');
                    alertContainer.textContent = 'An unexpected server error occurred. Please try again.';
                    
                    // Disable Loading State
                    submitBtn.disabled = false;
                    btnSpinner.classList.add('d-none');
                });
            });
        });
    </script>
</body>
</html>
