<?php
/**
 * Indus Grammar School ERP - Forgot Password
 * Version 1.0.0
 */

// Boot application
require_once __DIR__ . '/config/app.php';

// Force guest access
AuthMiddleware::requireGuest();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrf($csrfToken)) {
        $message = 'Security token expired. Please refresh the page and try again.';
        $messageType = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        $email = sanitize($email);
        
        if (empty($email)) {
            $message = 'Please enter your email address.';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';
        } else {
            // Find if user exists in database
            try {
                $db = Database::getConnection();
                $stmt = $db->prepare("SELECT id, username FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Log the request to audit logs
                    auditLog('Password Reset Request', "Password reset link requested for user: " . $user['username'], $user['id']);
                } else {
                    // Log failed attempt for untracked email
                    auditLog('Password Reset Failed', "Password reset requested for unregistered email: " . $email);
                }
                
                // Always display success message to prevent user enumeration security issues
                $message = 'If a matching account was found, a password reset link has been dispatched to your email address.';
                $messageType = 'success';
            } catch (Exception $e) {
                error_log("Forgot password database error: " . $e->getMessage());
                $message = 'An unexpected error occurred. Please try again later.';
                $messageType = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Indus Grammar School ERP</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
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

        <!-- Card -->
        <div class="card auth-card">
            <div class="card-body p-4 p-sm-5">
                <h3 class="card-title text-center mb-2">Forgot Password</h3>
                <p class="text-secondary text-center small mb-4">
                    Enter your email address and we'll send you instructions to reset your password.
                </p>

                <!-- Feedback Message -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($messageType !== 'success'): ?>
                    <form action="forgot-password.php" method="POST">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="e.g. saeed@indus.edu.pk" required>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary w-100 py-3">Send Reset Instructions</button>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="login.php" class="auth-link"><i class="fa-solid fa-arrow-left me-2"></i>Back to Sign In</a>
                </div>
            </div>
        </div>

        <!-- Footer Notice -->
        <div class="text-center mt-4">
            <p class="text-secondary small">&copy; <?php echo date('Y'); ?> Indus Grammar School. All rights reserved.</p>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
