<?php
/**
 * Indus Grammar School ERP - Generic CSV Export Handler
 * Version 1.0.0
 */

require_once __DIR__ . '/../../config/app.php';
AuthMiddleware::requirePermission('report_view');

$type = sanitize($_GET['type'] ?? '');
$filename = $type . '_export_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

switch ($type) {
    case 'students':
        fputcsv($output, ['ID', 'Admission No', 'First Name', 'Last Name', 'Gender', 'DOB', 'Guardian Name', 'Guardian Phone', 'Status']);
        try {
            $db = Database::getConnection();
            $rows = $db->query("SELECT id, admission_no, first_name, last_name, gender, date_of_birth, guardian_name, guardian_phone, status FROM students")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) fputcsv($output, $row);
        } catch (Exception $e) {}
        break;

    case 'staff':
        fputcsv($output, ['ID', 'Employee No', 'First Name', 'Last Name', 'Designation', 'Department', 'Phone', 'Salary', 'Status']);
        try {
            $db = Database::getConnection();
            $rows = $db->query("SELECT id, employee_no, first_name, last_name, designation, department, phone, salary, status FROM staff")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) fputcsv($output, $row);
        } catch (Exception $e) {}
        break;

    case 'expenses':
        fputcsv($output, ['ID', 'Category', 'Description', 'Amount', 'Date', 'Paid To', 'Ref Reference']);
        try {
            $db = Database::getConnection();
            $rows = $db->query("SELECT id, category, description, amount, expense_date, paid_to, receipt_reference FROM expenses")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) fputcsv($output, $row);
        } catch (Exception $e) {}
        break;

    default:
        fputcsv($output, ['Error', 'Unsupported export format or type requested.']);
        break;
}

fclose($output);
exit;
