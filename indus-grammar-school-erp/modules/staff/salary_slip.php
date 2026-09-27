<?php
/**
 * Indus Grammar School ERP - Salary Slip Router Bridge
 * Version 4.0.0
 */

require_once __DIR__ . '/../../config/app.php';

AuthMiddleware::requireLogin();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $db = Database::getConnection();
    // 1. Try finding in salary_details directly
    $exist = $db->query("SELECT id FROM salary_details WHERE id = $id LIMIT 1")->fetchColumn();
    if ($exist) {
        redirect(APP_URL . '/modules/staff/payroll_slips.php?id=' . $id);
    }

    // 2. Try finding via staff_salaries reference
    $staffSal = $db->query("SELECT staff_id, month, year FROM staff_salaries WHERE id = $id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($staffSal) {
        $m = (int)$staffSal['month'];
        $y = (int)$staffSal['year'];
        $sid = (int)$staffSal['staff_id'];
        $detailId = (int)$db->query("
            SELECT d.id FROM salary_details d
            JOIN salary_processing p ON d.processing_id = p.id
            WHERE d.staff_id = $sid AND p.month = $m AND p.year = $y
            LIMIT 1
        ")->fetchColumn();
        if ($detailId > 0) {
            redirect(APP_URL . '/modules/staff/payroll_slips.php?id=' . $detailId);
        }
    }
}

// Fallback redirect to payroll_slips
redirect(APP_URL . '/modules/staff/payroll_slips.php?id=' . $id);
