<?php
/**
 * Indus Grammar School ERP - Central Export Center
 * Version 4.0.0
 */

require_once __DIR__ . '/../../config/app.php';

AuthMiddleware::requirePermission('report_view');

$db = Database::getConnection();

$type      = sanitize($_GET['type'] ?? '');
$subType   = sanitize($_GET['sub_type'] ?? '');
$classId   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$status    = sanitize($_GET['status'] ?? '');
$dateFrom  = sanitize($_GET['date_from'] ?? '');
$dateTo    = sanitize($_GET['date_to'] ?? '');
$dept      = sanitize($_GET['department'] ?? '');
$month     = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year      = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$filename = 'export_' . $type . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Write column headers and rows based on export type
switch ($type) {
    
    case 'students':
        fputcsv($output, ['Admission No', 'Student Name', 'Gender', 'DOB', 'Enrollment Date', 'Guardian Name', 'Guardian Phone', 'Status', 'Academic Type']);
        
        $sql = "SELECT st.* FROM students st WHERE 1=1";
        $params = [];
        if ($classId > 0) { $sql .= " AND st.class_id = :cid"; $params['cid'] = $classId; }
        if ($status !== '') { $sql .= " AND st.status = :status"; $params['status'] = $status; }
        if ($dateFrom !== '') { $sql .= " AND st.enrollment_date >= :from"; $params['from'] = $dateFrom; }
        if ($dateTo !== '') { $sql .= " AND st.enrollment_date <= :to"; $params['to'] = $dateTo; }
        $sql .= " ORDER BY st.first_name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['admission_no'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['gender'],
                $row['date_of_birth'],
                $row['enrollment_date'],
                $row['guardian_name'],
                $row['guardian_phone'],
                $row['status'],
                $row['academic_type']
            ]);
        }
        break;

    case 'attendance':
        fputcsv($output, ['Registration No', 'Name', 'Department/Class', 'Date', 'Status', 'Remarks']);
        
        if ($subType === 'staff') {
            $sql = "
                SELECT sa.*, s.first_name, s.last_name, s.employee_no, s.department
                FROM staff_attendance sa
                JOIN staff s ON sa.staff_id = s.id
                WHERE 1=1
            ";
            $params = [];
            if ($dept !== '') { $sql .= " AND s.department = :dept"; $params['dept'] = $dept; }
            if ($month > 0) { $sql .= " AND MONTH(sa.date) = :m AND YEAR(sa.date) = :y"; $params['m'] = $month; $params['y'] = $year; }
            $sql .= " ORDER BY sa.date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['employee_no'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['department'],
                    $row['date'],
                    $row['status'],
                    $row['remarks']
                ]);
            }
        } else {
            $sql = "
                SELECT a.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
                FROM attendance a
                JOIN students st ON a.student_id = st.id
                JOIN classes c ON a.class_id = c.id
                WHERE 1=1
            ";
            $params = [];
            if ($classId > 0) { $sql .= " AND a.class_id = :cid"; $params['cid'] = $classId; }
            if ($month > 0) { $sql .= " AND MONTH(a.date) = :m AND YEAR(a.date) = :y"; $params['m'] = $month; $params['y'] = $year; }
            $sql .= " ORDER BY a.date DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['admission_no'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['class_name'] . '-' . $row['section'],
                    $row['date'],
                    $row['status'],
                    $row['remarks']
                ]);
            }
        }
        break;

    case 'fees':
        fputcsv($output, ['Admission No', 'Student Name', 'Reference No', 'Payment Date', 'Payment Method', 'Amount Paid', 'Remarks']);
        
        $sql = "
            SELECT fp.*, st.first_name, st.last_name, st.admission_no
            FROM fee_payments fp
            JOIN students st ON fp.student_id = st.id
            WHERE fp.payment_date BETWEEN :from AND :to
        ";
        $params = ['from' => $dateFrom ?: date('Y-m-01'), 'to' => $dateTo ?: date('Y-m-d')];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['admission_no'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['reference_number'],
                $row['payment_date'],
                $row['payment_method'],
                $row['amount_paid'],
                $row['remarks']
            ]);
        }
        break;

    case 'expenses':
        fputcsv($output, ['Expense Date', 'Title', 'Vendor/Supplier', 'Invoice Number', 'Amount', 'Payment Method', 'Remarks']);
        
        $sql = "
            SELECT * FROM expenses 
            WHERE expense_date BETWEEN :from AND :to
            ORDER BY expense_date DESC
        ";
        $params = ['from' => $dateFrom ?: date('Y-m-01'), 'to' => $dateTo ?: date('Y-m-d')];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['expense_date'],
                $row['title'],
                $row['vendor_supplier'],
                $row['invoice_number'],
                $row['amount'],
                $row['payment_method'],
                $row['remarks']
            ]);
        }
        break;

    case 'examination':
        fputcsv($output, ['Admission No', 'Student Name', 'Class Section', 'Total Marks', 'Obtained Marks', 'Percentage', 'Grade', 'Position', 'Status']);
        
        $sql = "
            SELECT er.*, st.first_name, st.last_name, st.admission_no, c.class_name, c.section
            FROM exam_results er
            JOIN students st ON er.student_id = st.id
            JOIN classes c ON er.class_id = c.id
            WHERE er.exam_type_id = :etid
        ";
        $params = ['etid' => isset($_GET['exam_type_id']) ? (int)$_GET['exam_type_id'] : 0];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['admission_no'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['class_name'] . '-' . $row['section'],
                $row['total_marks'],
                $row['obtained_marks'],
                $row['percentage'],
                $row['grade'],
                $row['position'],
                $row['status']
            ]);
        }
        break;

    case 'staff':
        fputcsv($output, ['Employee No', 'Staff Name', 'Department', 'Designation', 'Phone', 'Salary', 'Status']);
        
        $sql = "SELECT * FROM staff ORDER BY first_name ASC";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['employee_no'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['department'],
                $row['designation'],
                $row['phone'],
                $row['salary'],
                $row['status']
            ]);
        }
        break;

    default:
        fputcsv($output, ['Error', 'Unsupported export format or type requested.']);
        break;
}

fclose($output);
exit;
