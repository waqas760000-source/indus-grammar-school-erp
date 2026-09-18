<?php
// Simulate Web Environment for CLI testing of summary_report.php
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
$_SERVER['REQUEST_URI'] = '/indus-grammar-school-erp/modules/students/summary_report.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

function testSummaryScenario($description, $queryParams) {
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
        include __DIR__ . '/../modules/students/summary_report.php';
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

testSummaryScenario("Initial Load (No filters)", []);
testSummaryScenario("Filter by Campus (Main Campus)", ['search_campus' => 'Main Campus']);
testSummaryScenario("Filter by Academic Type (School)", ['search_academic_type' => 'School']);
testSummaryScenario("Filter by Academic Type (Academy)", ['search_academic_type' => 'Academy']);
testSummaryScenario("Filter by Session (2026-2027)", ['search_session' => '2026-2027']);
testSummaryScenario("Filter by Status (Active)", ['search_status' => 'Active']);
testSummaryScenario("Combined Filters (Main Campus + School + Active)", [
    'search_campus' => 'Main Campus',
    'search_academic_type' => 'School',
    'search_status' => 'Active'
]);
