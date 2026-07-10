<?php
/**
 * Indus Grammar School ERP - Layout Header
 * Version 1.0.0
 */

// Boot application if not already done
require_once __DIR__ . '/../config/app.php';

// Force authentication on all pages using the shared layout
AuthMiddleware::requireLogin();

// Fetch current user and active page filename
$currentUser = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | Indus School ERP' : 'Indus Grammar School ERP'; ?></title>
    <!-- Google Fonts (Outfit) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom Theme Stylesheet -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <!-- Extra CSS Hook -->
    <?php echo $extraCSS ?? ''; ?>
</head>
<body>

    <div id="wrapper">
        <!-- Sidebar Navigation -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area Wrapper -->
        <div id="content">
            <!-- Top Navbar -->
            <?php include_once __DIR__ . '/navbar.php'; ?>

            <!-- Page Inner Wrapper -->
            <div class="container-fluid p-4">
                <!-- Flash Alert Messages -->
                <?php include_once __DIR__ . '/alerts.php'; ?>
