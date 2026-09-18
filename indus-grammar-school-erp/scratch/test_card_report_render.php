<?php
// Simulate Web Environment for CLI testing of card_report.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['email'] = 'admin@indus.edu.pk';
$_SESSION['role_id'] = 1;
$_SESSION['role_code'] = 'super_admin';
$_SESSION['role_name'] = 'Super Administrator';

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/indus-grammar-school-erp/modules/students/card_report.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

function testPageScenario($description, $queryParams) {
    echo "=========================================\n";
    echo "TEST SCENARIO: $description\n";
    echo "QUERY: " . http_build_query($queryParams) . "\n";
    
    $_GET = $queryParams;
    
    $errors = [];
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errors) {
        $errors[] = "[$errno] $errstr in $errfile line $errline";
    });
    
    ob_start();
    try {
        include __DIR__ . '/../modules/students/card_report.php';
        $output = ob_get_clean();
        echo "Render Status: SUCCESS (Output size: " . strlen($output) . " bytes)\n";
    } catch (Exception $e) {
        ob_end_clean();
        echo "Render Status: EXCEPTION - " . $e->getMessage() . "\n";
    }
    
    restore_error_handler();
    
    if (!empty($errors)) {
        echo "ERRORS/WARNINGS DETECTED (" . count($errors) . "):\n";
        foreach ($errors as $err) {
            echo "  - $err\n";
        }
    } else {
        echo "ERRORS/WARNINGS: None (100% Clean execution)\n";
    }
    echo "=========================================\n\n";
}

testPageScenario("Initial Load (No search)", []);
testPageScenario("Empty search submitted", ['search' => '1', 'single_search' => '']);
testPageScenario("Valid Student Search (IGS-2026-0001)", ['search' => '1', 'single_search' => 'IGS-2026-0001']);
testPageScenario("Non-existent Student Search", ['search' => '1', 'single_search' => '999999999']);
testPageScenario("Batch Filter (School, Class 1)", ['search' => '1', 'single_search' => '', 'batch_academic_type' => 'School', 'batch_class' => 'Class 1']);
