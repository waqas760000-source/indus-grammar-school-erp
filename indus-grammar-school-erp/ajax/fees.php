<?php
/**
 * Indus Grammar School ERP - Fee Controller AJAX Handler (Ledger system)
 * Version 4.0.0
 */

require_once 'e:/Xampo/htdocs/indus-grammar-school-erp/indus-grammar-school-erp/config/app.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. A new token has been generated. Please try again.', 'new_csrf_token' => csrfToken()], 403);
}

$action     = $_POST['action'] ?? '';
$feeService = new FeeService();

switch ($action) {

    // ── Save Class Fee Structure ──
    case 'save_structure':
        AuthMiddleware::requirePermission('fee_manage');
        $class_id = (int)($_POST['class_id'] ?? 0);
        if ($class_id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Please select a valid Class for the fee structure.']);
        }
        $result = Fee::createStructure([
            'academic_type'    => sanitize($_POST['academic_type'] ?? 'School'),
            'class_id'         => $class_id,
            'admission_fee'    => max(0, (float)($_POST['admission_fee'] ?? 0)),
            'tuition_fee'      => max(0, (float)($_POST['tuition_fee'] ?? 0)),
            'computer_fee'     => max(0, (float)($_POST['computer_fee'] ?? 0)),
            'exam_fee'         => max(0, (float)($_POST['exam_fee'] ?? 0)),
            'transport_fee'    => max(0, (float)($_POST['transport_fee'] ?? 0)),
            'annual_charges'   => max(0, (float)($_POST['annual_charges'] ?? 0)),
            'security_deposit' => max(0, (float)($_POST['security_deposit'] ?? 0)),
            'other_charges'    => max(0, (float)($_POST['other_charges'] ?? 0)),
            'status'           => sanitize($_POST['status'] ?? 'Active'),
            'academic_year'    => sanitize($_POST['academic_year'] ?? CURRENT_ACADEMIC_YEAR),
        ]);
        if ($result) {
            auditLog('Fee Structure Saved', "Fee structure saved/updated for class ID $class_id.");
        }
        jsonResponse(['success' => (bool)$result, 'message' => $result ? 'Fee structure saved successfully.' : 'Failed to save fee structure.']);
        break;

    // ── Delete Structure ──
    case 'delete_structure':
        AuthMiddleware::requirePermission('fee_manage');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid structure ID.']);
        }
        $ok = Fee::deleteStructure($id);
        if ($ok) {
            auditLog('Fee Structure Deleted', "Structure ID $id deleted.");
        }
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Deleted successfully.' : 'Failed to delete structure.']);
        break;

    // ── Apply Student Discount (Assignment mapping) ──
    case 'apply_discount':
        AuthMiddleware::requirePermission('fee_manage');
        $studentId = (int)($_POST['student_id'] ?? 0);
        
        // Ensure assignment exists
        Fee::ensureStudentAssignment($studentId);
        $assignment = Fee::getStudentAssignment($studentId);
        if (!$assignment) {
            jsonResponse(['success' => false, 'message' => 'No active fee configuration assigned to this student.']);
        }
        
        $ok = Fee::assignFeeToStudent([
            'student_id'          => $studentId,
            'fee_structure_id'    => (int)$assignment['fee_structure_id'],
            'discount_percentage' => (float)($_POST['percentage'] ?? 0),
            'discount_flat'       => (float)($_POST['flat_amount'] ?? 0),
            'discount_reason'     => sanitize($_POST['reason'] ?? ''),
            'status'              => 'Active'
        ]);
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Discount settings saved.' : 'Failed to save discount.']);
        break;

    // ── Delete Discount ──
    case 'delete_discount':
        AuthMiddleware::requirePermission('fee_manage');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid assignment ID.']);
        }
        try {
            $db = Database::getConnection();
            $ok = $db->prepare("
                UPDATE student_fee_assignments 
                SET discount_percentage = 0.00, discount_flat = 0.00, discount_reason = '' 
                WHERE id = :id
            ")->execute(['id' => $id]);
            if ($ok) {
                auditLog('Discount Deleted', "Discount cleared on assignment $id");
            }
            jsonResponse(['success' => $ok, 'message' => $ok ? 'Discount cleared successfully.' : 'Clear failed.']);
        } catch (Exception $ex) {
            jsonResponse(['success' => false, 'message' => $ex->getMessage()]);
        }
        break;

    // ── Generate Single Month Student Ledger Entry ──
    case 'generate_ledger_entry':
        AuthMiddleware::requirePermission('fee_manage');
        $studentId = (int)($_POST['student_id'] ?? 0);
        $month     = sanitize($_POST['month'] ?? '');
        $dueDate   = sanitize($_POST['due_date'] ?? '');
        if ($studentId <= 0 || empty($month)) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters.']);
        }
        $res = $feeService->generateMonthlyLedgerEntry($studentId, $month, $dueDate);
        jsonResponse(['success' => $res['status'], 'message' => $res['message']]);
        break;

    // ── Generate Class Batch Ledger ──
    case 'generate_class_ledger':
        AuthMiddleware::requirePermission('fee_manage');
        $classId      = (int)($_POST['class_id'] ?? 0);
        $academicType = sanitize($_POST['academic_type'] ?? 'School');
        $month        = sanitize($_POST['month'] ?? '');
        $dueDate      = sanitize($_POST['due_date'] ?? '');
        if ($classId <= 0 || empty($month)) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters.']);
        }
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM students WHERE class_id = :cid AND academic_type = :type AND status = 'Active'");
            $stmt->execute(['cid' => $classId, 'type' => $academicType]);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($students)) {
                jsonResponse(['success' => false, 'message' => 'No active students found in this class.']);
            }
            
            $successCount = 0;
            $failCount = 0;
            foreach ($students as $sid) {
                $res = $feeService->generateMonthlyLedgerEntry($sid, $month, $dueDate);
                if ($res['status']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            jsonResponse(['success' => true, 'message' => "Batch generated. Success: $successCount, Skipped/Failed: $failCount."]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Batch generation failed: ' . $e->getMessage()]);
        }
        break;

    // ── Collect Fee Payment ──
    case 'collect_payment':
        AuthMiddleware::requirePermission('fee_collect');
        $rawChallan = $_POST['challan_id'] ?? '';
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $ledgerId   = 0;

        if (is_string($rawChallan) && str_starts_with($rawChallan, 'new_month:')) {
            $monthName = trim(substr($rawChallan, 10));
            if (!empty($monthName) && $studentId > 0) {
                Fee::ensureStudentAssignment($studentId);
                $feeService->generateMonthlyLedgerEntry($studentId, $monthName);
                
                $db = Database::getConnection();
                $getL = $db->prepare("SELECT id FROM fee_ledger WHERE student_id = :sid AND month = :m ORDER BY id DESC LIMIT 1");
                $getL->execute(['sid' => $studentId, 'm' => $monthName]);
                $ledgerId = (int)$getL->fetchColumn();
            }
        } else {
            $ledgerId = (int)$rawChallan;
        }

        if ($ledgerId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Please select a valid fee month to collect payment.']);
        }

        $result = $feeService->collectFeePayment([
            'ledger_id'        => $ledgerId,
            'amount_paid'      => (float)($_POST['amount_paid'] ?? 0),
            'payment_date'     => sanitize($_POST['payment_date'] ?? date('Y-m-d')),
            'payment_method'   => sanitize($_POST['payment_method'] ?? 'Cash'),
            'reference_number' => sanitize($_POST['reference_number'] ?? ''),
            'remarks'          => sanitize($_POST['remarks'] ?? ''),
        ]);
        jsonResponse(['success' => $result['status'], 'message' => $result['message'] ?? 'Payment recorded.', 'receipt_no' => $result['receipt_no'] ?? '']);
        break;

    // ── Load Student Details, Ledger Summaries, and Histories ──
    case 'load_student_details':
        AuthMiddleware::requirePermission('fee_view');
        $sid = (int)($_POST['student_id'] ?? 0);
        
        try {
            $db = Database::getConnection();
            
            // 1. Fetch student info
            $stmt = $db->prepare("
                SELECT s.*, c.class_name, c.section, d.father_name, d.roll_no, d.doc_student_photo
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN student_registration_details d ON s.id = d.student_id
                WHERE s.id = :sid
            ");
            $stmt->execute(['sid' => $sid]);
            $student = $stmt->fetch();
            
            if (!$student) {
                jsonResponse(['success' => false, 'message' => 'Student not found.']);
            }

            // Ensure assignment exists
            Fee::ensureStudentAssignment($sid);
            $assignment = Fee::getStudentAssignment($sid);
            $estMonthlyFee = $assignment ? ((float)$assignment['tuition_fee'] + (float)$assignment['computer_fee'] + (float)$assignment['exam_fee'] + (float)$assignment['transport_fee'] + (float)$assignment['other_charges']) : 0.00;

            // Auto-generate current month ledger entry if missing for student
            $currentMonthStr = date('F Y');
            $chkLedger = $db->prepare("SELECT COUNT(*) FROM fee_ledger WHERE student_id = :sid AND month = :m");
            $chkLedger->execute(['sid' => $sid, 'm' => $currentMonthStr]);
            if ($chkLedger->fetchColumn() == 0) {
                $feeService->generateMonthlyLedgerEntry($sid, $currentMonthStr, date('Y-m-10'));
            }

            // 2. Fetch student ledger entries
            $ledgStmt = $db->prepare("
                SELECT * FROM fee_ledger 
                WHERE student_id = :sid 
                ORDER BY due_date ASC
            ");
            $ledgStmt->execute(['sid' => $sid]);
            $allLedgers = $ledgStmt->fetchAll();

            $pendingFees = [];
            $prevBalance = 0.00;
            
            $admissionFee = 0.00;
            $tuitionFee = 0.00;
            $annualCharges = 0.00;
            $fineAmount = 0.00;
            $discountAmount = 0.00;
            
            $totalPayable = 0.00;
            $paidAmount = 0.00;
            
            $existingMonthsMap = [];

            foreach ($allLedgers as $row) {
                $existingMonthsMap[strtolower(trim($row['month']))] = $row['status'];

                // Dynamic late fine calculation
                $lateFine = $feeService->calculateLateFineForLedger($row);
                $netPayable = (float)$row['total_payable'] + $lateFine;
                $remaining = max(0.00, $netPayable - (float)$row['paid_amount']);
                
                if ($row['status'] === 'Paid') {
                    $remaining = 0.00;
                }

                if ($row['status'] !== 'Paid' || $remaining > 0) {
                    $pendingFees[] = [
                        'id'                => $row['id'],
                        'month'             => $row['month'],
                        'monthly_fee'       => (float)$row['tuition_fee'] + (float)$row['computer_fee'] + (float)$row['exam_fee'] + (float)$row['transport_fee'] + (float)$row['security_deposit'] + (float)$row['other_charges'],
                        'fine'              => (float)$row['fine_amount'] + $lateFine,
                        'discount'          => (float)$row['discount_amount'],
                        'paid_amount'       => (float)$row['paid_amount'],
                        'remaining_balance' => $remaining,
                        'status'            => $row['status'],
                        'due_date'          => $row['due_date'],
                        'is_upcoming'       => false
                    ];

                    if (strcasecmp($row['month'], $currentMonthStr) === 0) {
                        $admissionFee   += (float)$row['admission_fee'];
                        $tuitionFee     += ((float)$row['tuition_fee'] + (float)$row['computer_fee'] + (float)$row['exam_fee'] + (float)$row['transport_fee'] + (float)$row['security_deposit'] + (float)$row['other_charges']);
                        $annualCharges  += (float)$row['annual_charges'];
                        $fineAmount     += ((float)$row['fine_amount'] + $lateFine);
                        $discountAmount += (float)$row['discount_amount'];
                    } else {
                        $prevBalance    += $remaining;
                    }

                    $totalPayable += $remaining;
                }
                
                $paidAmount += (float)$row['paid_amount'];
            }
            
            // Generate upcoming advance months if unbilled
            for ($i = 0; $i <= 3; $i++) {
                $mName = date('F Y', strtotime("+$i month"));
                $mKey  = strtolower(trim($mName));
                if (!isset($existingMonthsMap[$mKey])) {
                    $pendingFees[] = [
                        'id'                => 'new_month:' . $mName,
                        'month'             => $mName . ' (Advance / Next Month)',
                        'monthly_fee'       => $estMonthlyFee,
                        'fine'              => 0.00,
                        'discount'          => 0.00,
                        'paid_amount'       => 0.00,
                        'remaining_balance' => $estMonthlyFee,
                        'status'            => 'Advance Option',
                        'due_date'          => date('Y-m-15', strtotime("+$i month")),
                        'is_upcoming'       => true
                    ];
                }
            }

            $remainingBalance = $totalPayable;

            // 3. Fetch transaction payment history
            $histStmt = $db->prepare("
                SELECT fp.*, fl.month, fr.receipt_no
                FROM fee_payments fp
                JOIN fee_receipts fr ON fr.payment_id = fp.id
                JOIN fee_ledger fl ON fp.ledger_id = fl.id
                WHERE fp.student_id = :sid
                ORDER BY fp.created_at DESC
            ");
            $histStmt->execute(['sid' => $sid]);
            $history = $histStmt->fetchAll();

            jsonResponse([
                'success' => true,
                'student' => $student,
                'summary' => [
                    'admission_fee'     => $admissionFee,
                    'tuition_fee'       => $tuitionFee,
                    'annual_charges'    => $annualCharges,
                    'previous_balance'  => $prevBalance,
                    'fine'              => $fineAmount,
                    'discount'          => $discountAmount,
                    'total_payable'     => $totalPayable,
                    'paid_amount'       => $paidAmount,
                    'remaining_balance' => $remainingBalance
                ],
                'pending_fees'    => $pendingFees,
                'payment_history' => $history
            ]);

        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;

    // ── Get Cashier Dashboard Counters ──
    case 'get_cashier_dashboard':
        AuthMiddleware::requirePermission('fee_view');
        jsonResponse([
            'success'   => true,
            'dashboard' => Fee::getCashierDashboard()
        ]);
        break;

    // ── Add Custom Fine to Ledger row ──
    case 'ledger_add_fine':
        AuthMiddleware::requirePermission('fee_manage');
        $id  = (int)($_POST['id'] ?? 0);
        $amt = (float)($_POST['amount'] ?? 0);
        if ($id <= 0 || $amt <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters.']);
        }
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT status FROM fee_ledger WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $status = $stmt->fetchColumn();
            if ($status === 'Paid') {
                jsonResponse(['success' => false, 'message' => 'Cannot add fine to a fully paid month.']);
            }
            
            $ok = $db->prepare("
                UPDATE fee_ledger 
                SET fine_amount = fine_amount + :amt1, total_payable = total_payable + :amt2 
                WHERE id = :id
            ")->execute(['amt1' => $amt, 'amt2' => $amt, 'id' => $id]);
            if ($ok) {
                auditLog('Ledger Fine Added', "Rs. $amt fine added to ledger entry ID $id.");
            }
            jsonResponse(['success' => $ok, 'message' => $ok ? 'Fine added successfully.' : 'Operation failed.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ── Waive Fine from Ledger row ──
    case 'ledger_waive_fine':
        AuthMiddleware::requirePermission('fee_manage');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid entry ID.']);
        }
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT fine_amount, status FROM fee_ledger WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $ledger = $stmt->fetch();
            if (!$ledger) {
                jsonResponse(['success' => false, 'message' => 'Ledger record not found.']);
            }
            if ($ledger['status'] === 'Paid') {
                jsonResponse(['success' => false, 'message' => 'Cannot waive fine on a fully paid month.']);
            }
            
            $fine = (float)$ledger['fine_amount'];
            $ok = $db->prepare("
                UPDATE fee_ledger 
                SET total_payable = total_payable - :fine, fine_amount = 0.00 
                WHERE id = :id
            ")->execute(['fine' => $fine, 'id' => $id]);
            
            if ($ok) {
                auditLog('Ledger Fine Waived', "Fine of Rs. $fine waived from ledger entry ID $id.");
            }
            jsonResponse(['success' => $ok, 'message' => $ok ? 'Fine waived successfully.' : 'Operation failed.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ── Update Late Fine Settings ──
    case 'update_fine_settings':
        AuthMiddleware::requirePermission('fee_manage');
        $ok = Fee::updateSettings([
            'late_fine_amount' => (float)($_POST['late_fine_amount'] ?? 0.00),
            'grace_days'       => (int)($_POST['grace_days'] ?? 0),
            'fine_type'        => sanitize($_POST['fine_type'] ?? 'Fixed')
        ]);
        if ($ok) {
            auditLog('Fine Settings Updated', 'Late fine properties modified.');
        }
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Settings updated successfully.' : 'Failed to update settings.']);
        break;

    // ── Student Autocomplete Lookup ──
    case 'search_student':
        AuthMiddleware::requirePermission('fee_view');
        $q = sanitize($_POST['q'] ?? '');
        if (strlen($q) < 2) {
            jsonResponse(['success' => false, 'message' => 'Enter at least 2 characters.']);
        }
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                SELECT s.id, s.first_name, s.last_name, s.admission_no, s.academic_type,
                       c.class_name, c.section, d.father_name, d.roll_no, d.doc_student_photo
                FROM students s 
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN student_registration_details d ON s.id = d.student_id
                WHERE (s.first_name LIKE :q1 OR s.last_name LIKE :q2 OR s.admission_no LIKE :q3 OR d.roll_no LIKE :q4)
                  AND s.status = 'Active' LIMIT 15
            ");
            $searchTerm = '%' . $q . '%';
            $stmt->execute([
                'q1' => $searchTerm,
                'q2' => $searchTerm,
                'q3' => $searchTerm,
                'q4' => $searchTerm
            ]);
            $students = $stmt->fetchAll();
            
            // Calculate pending dues from ledger
            foreach ($students as &$st) {
                $lStmt = $db->prepare("SELECT * FROM fee_ledger WHERE student_id = :sid AND status IN ('Pending', 'Partial')");
                $lStmt->execute(['sid' => $st['id']]);
                $ledgers = $lStmt->fetchAll();
                $dues = 0.00;
                foreach ($ledgers as $ledger) {
                    $lateFine = $feeService->calculateLateFineForLedger($ledger);
                    $dues += ((float)$ledger['total_payable'] + $lateFine - (float)$ledger['paid_amount']);
                }
                $st['dues'] = $dues;
            }
            
            jsonResponse(['success' => true, 'students' => $students]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Search failed: ' . $e->getMessage()]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.']);
}
