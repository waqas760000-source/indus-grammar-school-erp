<?php
/**
 * Indus Grammar School ERP - Payroll Module AJAX Controller
 * Version 4.0.0
 */

require_once __DIR__ . '/../config/app.php';

// Authentication and Authorization
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$db = Database::getConnection();

// Restricted to Super Admin, School Admin, and Accountant
if (!hasPermission('hr_manage') && !hasPermission('hr_view')) {
    jsonResponse(['success' => false, 'message' => 'Access denied. You do not have permissions to manage payroll.'], 403);
}

// POST and CSRF Verification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh the page.'], 403);
}

$action = sanitize($_POST['action'] ?? '');
if (empty($action)) {
    jsonResponse(['success' => false, 'message' => 'No action specified.'], 400);
}

switch ($action) {

    // ── 1. SAVE SALARY SETUP ──────────────────────────────────
    case 'save_salary_setup':
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $basic   = (float)($_POST['basic_salary'] ?? 0.00);
        $hra     = (float)($_POST['hra'] ?? 0.00);
        $medical = (float)($_POST['medical_allowance'] ?? 0.00);
        $trans   = (float)($_POST['transport_allowance'] ?? 0.00);
        $otherA  = (float)($_POST['other_allowances'] ?? 0.00);
        $pf      = (float)($_POST['provident_fund'] ?? 0.00);
        $tax     = (float)($_POST['tax_deduction'] ?? 0.00);
        $eobi    = (float)($_POST['eobi'] ?? 0.00);
        $otherD  = (float)($_POST['other_deductions'] ?? 0.00);
        $method  = sanitize($_POST['payment_method'] ?? 'Bank Transfer');
        $bank    = sanitize($_POST['bank_name'] ?? '');
        $account = sanitize($_POST['account_number'] ?? '');
        $status  = sanitize($_POST['status'] ?? 'Active');

        if ($staffId <= 0 || $basic < 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid staff member or basic salary value.'], 400);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO salary_setup 
                (staff_id, basic_salary, hra, medical_allowance, transport_allowance, other_allowances, provident_fund, tax_deduction, eobi, other_deductions, payment_method, bank_name, account_number, status)
                VALUES 
                (:sid, :basic, :hra, :med, :trans, :otherA, :pf, :tax, :eobi, :otherD, :method, :bank, :account, :status)
                ON DUPLICATE KEY UPDATE
                    basic_salary = VALUES(basic_salary),
                    hra = VALUES(hra),
                    medical_allowance = VALUES(medical_allowance),
                    transport_allowance = VALUES(transport_allowance),
                    other_allowances = VALUES(other_allowances),
                    provident_fund = VALUES(provident_fund),
                    tax_deduction = VALUES(tax_deduction),
                    eobi = VALUES(eobi),
                    other_deductions = VALUES(other_deductions),
                    payment_method = VALUES(payment_method),
                    bank_name = VALUES(bank_name),
                    account_number = VALUES(account_number),
                    status = VALUES(status)
            ");
            $ok = $stmt->execute([
                'sid'     => $staffId,
                'basic'   => $basic,
                'hra'     => $hra,
                'med'     => $medical,
                'trans'   => $trans,
                'otherA'  => $otherA,
                'pf'      => $pf,
                'tax'     => $tax,
                'eobi'    => $eobi,
                'otherD'  => $otherD,
                'method'  => $method,
                'bank'    => $bank ?: null,
                'account' => $account ?: null,
                'status'  => $status
            ]);

            if ($ok) {
                auditLog('Salary Setup Saved', "Updated salary structure details for Staff ID: $staffId");
                jsonResponse(['success' => true, 'message' => 'Salary structure configurations assigned successfully.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to save salary setups.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;


    // ── 2. GENERATE PAYROLL RUN ───────────────────────────────
    case 'generate_payroll_bulk':
        $month = (int)($_POST['month'] ?? date('m'));
        $year  = (int)($_POST['year'] ?? date('Y'));
        $dept  = sanitize($_POST['department'] ?? '');

        try {
            $db->beginTransaction();

            // Load settings configurations
            $settings = $db->query("SELECT * FROM payroll_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $workDaysPerMonth = (int)($settings['working_days_per_month'] ?? 26);
            $lateDeductRule   = (float)($settings['late_deduction_rule'] ?? 0.25);
            $halfDayRule      = (float)($settings['half_day_rule'] ?? 0.50);
            $absentDeductRule = (float)($settings['absent_deduction_rule'] ?? 1.00);

            // Fetch active staff list
            $staffWhere = " WHERE s.status = 'Active'";
            $staffParams = [];
            if ($dept !== '') {
                $staffWhere .= " AND s.department = :dept";
                $staffParams['dept'] = $dept;
            }
            $stmt = $db->prepare("SELECT s.* FROM staff s $staffWhere");
            $stmt->execute($staffParams);
            $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($staffList)) {
                jsonResponse(['success' => false, 'message' => 'No active employees found to process for this filter.'], 400);
            }

            // Create/Ensure salary_processing registry row exists
            $procStmt = $db->prepare("
                INSERT INTO salary_processing (month, year, status)
                VALUES (:month, :year, 'Processed')
                ON DUPLICATE KEY UPDATE status = 'Processed'
            ");
            $procStmt->execute(['month' => $month, 'year' => $year]);

            $procId = (int)$db->query("SELECT id FROM salary_processing WHERE month = $month AND year = $year")->fetchColumn();

            $detailInsert = $db->prepare("
                INSERT INTO salary_details 
                (processing_id, staff_id, working_days, present_days, absent_days, late_days, leave_days, half_days, basic_salary, allowances, deductions, advance_salary_deduction, bonus, net_salary, payment_status)
                VALUES 
                (:pid, :sid, :work, :pres, :abs, :late, :leave, :half, :basic, :allow, :deduct, :adv, :bonus, :net, 'Pending')
                ON DUPLICATE KEY UPDATE
                    working_days = VALUES(working_days),
                    present_days = VALUES(present_days),
                    absent_days = VALUES(absent_days),
                    late_days = VALUES(late_days),
                    leave_days = VALUES(leave_days),
                    half_days = VALUES(half_days),
                    basic_salary = VALUES(basic_salary),
                    allowances = VALUES(allowances),
                    deductions = VALUES(deductions),
                    advance_salary_deduction = VALUES(advance_salary_deduction),
                    bonus = VALUES(bonus),
                    net_salary = VALUES(net_salary)
            ");

            $syncSalStmt = $db->prepare("
                INSERT INTO staff_salaries (staff_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status)
                VALUES (:sid, :month, :year, :basic, :allowances, :deductions, :net, 'Pending')
                ON DUPLICATE KEY UPDATE
                    basic_salary = VALUES(basic_salary),
                    allowances = VALUES(allowances),
                    deductions = VALUES(deductions),
                    net_salary = VALUES(net_salary)
            ");

            $successCount = 0;

            foreach ($staffList as $st) {
                $staffId = $st['id'];

                // Get salary setup parameters
                $setupStmt = $db->prepare("SELECT * FROM salary_setup WHERE staff_id = ? AND status = 'Active'");
                $setupStmt->execute([$staffId]);
                $setup = $setupStmt->fetch(PDO::FETCH_ASSOC);

                // Default fallbacks if no setup assigned yet
                $basicSalary = (float)($setup['basic_salary'] ?? $st['salary']);
                if ($basicSalary <= 0) $basicSalary = 20000.00; // default minimum backup

                $hra     = (float)($setup['hra'] ?? 0);
                $medical = (float)($setup['medical_allowance'] ?? 0);
                $trans   = (float)($setup['transport_allowance'] ?? 0);
                $otherA  = (float)($setup['other_allowances'] ?? 0);
                
                $pf      = (float)($setup['provident_fund'] ?? 0);
                $tax     = (float)($setup['tax_deduction'] ?? 0);
                $eobi    = (float)($setup['eobi'] ?? 0);
                $otherD  = (float)($setup['other_deductions'] ?? 0);

                // Calculate attendance metrics inside target month
                $attStmt = $db->prepare("
                    SELECT 
                        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as pres,
                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as abs,
                        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
                        SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as lve,
                        SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) as half
                    FROM staff_attendance
                    WHERE staff_id = :sid AND MONTH(date) = :month AND YEAR(date) = :year
                ");
                $attStmt->execute(['sid' => $staffId, 'month' => $month, 'year' => $year]);
                $att = $attStmt->fetch(PDO::FETCH_ASSOC);

                $presentDays = (int)($att['pres'] ?? 0);
                $absentDays  = (int)($att['abs'] ?? 0);
                $lateDays    = (int)($att['late'] ?? 0);
                $leaveDays   = (int)($att['lve'] ?? 0);
                $halfDays    = (int)($att['half'] ?? 0);

                // Calculate fine deductions values based on attendance
                $oneDayWage = $basicSalary / $workDaysPerMonth;
                $attendanceDeduction = 0.00;

                $attendanceDeduction += $absentDays * $oneDayWage * $absentDeductRule;
                $attendanceDeduction += $lateDays * $oneDayWage * $lateDeductRule;
                $attendanceDeduction += $halfDays * $oneDayWage * $halfDayRule;

                // Load active allowances
                $allowStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM allowances WHERE staff_id = ? AND status = 'Active'");
                $allowStmt->execute([$staffId]);
                $activeAllowances = (float)$allowStmt->fetchColumn();

                // Load active individual deductions
                $deductStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM deductions WHERE staff_id = ? AND status = 'Active'");
                $deductStmt->execute([$staffId]);
                $activeDeductions = (float)$deductStmt->fetchColumn();

                // Load bonuses earned in this month
                $bonusStmt = $db->prepare("
                    SELECT COALESCE(SUM(amount),0) 
                    FROM bonuses 
                    WHERE staff_id = :sid AND status = 'Active' AND MONTH(date_earned) = :month AND YEAR(date_earned) = :year
                ");
                $bonusStmt->execute(['sid' => $staffId, 'month' => $month, 'year' => $year]);
                $monthBonus = (float)$bonusStmt->fetchColumn();

                // Load active advances to recover
                $advStmt = $db->prepare("SELECT * FROM advance_salary WHERE staff_id = ? AND status = 'Pending' LIMIT 1");
                $advStmt->execute([$staffId]);
                $advance = $advStmt->fetch(PDO::FETCH_ASSOC);

                $advanceDeduction = 0.00;
                if ($advance) {
                    $installmentVal = (float)$advance['installment_amount'];
                    $remainingBal   = (float)$advance['remaining_balance'];
                    $advanceDeduction = min($installmentVal, $remainingBal);
                }

                // Summarize final structures
                $totalAllowances = $hra + $medical + $trans + $otherA + $activeAllowances;
                $totalDeductions = $pf + $tax + $eobi + $otherD + $activeDeductions + $attendanceDeduction;

                $netSalary = ($basicSalary + $totalAllowances + $monthBonus) - ($totalDeductions + $advanceDeduction);
                if ($netSalary < 0) $netSalary = 0.00; // Salary cannot be negative

                // Execute inserts
                $detailInsert->execute([
                    'pid'     => $procId,
                    'sid'     => $staffId,
                    'work'    => $workDaysPerMonth,
                    'pres'    => $presentDays,
                    'abs'     => $absentDays,
                    'late'    => $lateDays,
                    'leave'   => $leaveDays,
                    'half'    => $halfDays,
                    'basic'   => $basicSalary,
                    'allow'   => $totalAllowances,
                    'deduct'  => $totalDeductions,
                    'adv'     => $advanceDeduction,
                    'bonus'   => $monthBonus,
                    'net'     => $netSalary
                ]);

                $syncSalStmt->execute([
                    'sid'        => $staffId,
                    'month'      => $month,
                    'year'       => $year,
                    'basic'      => $basicSalary,
                    'allowances' => $totalAllowances + $monthBonus,
                    'deductions' => $totalDeductions + $advanceDeduction,
                    'net'        => $netSalary
                ]);
                $successCount++;
            }

            $db->commit();
            auditLog('Payroll Run Processed', "Generated automatic payroll ledger runs for $month/$year ($successCount employees processed)");
            jsonResponse(['success' => true, 'message' => "Successfully processed monthly payroll for $successCount active employees."]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Failed to process payroll: ' . $e->getMessage()]);
        }
        break;


    // ── 3. PAY SALARY & RECORD EXPENSE ────────────────────────
    case 'pay_salary':
        $salaryDetailId = (int)($_POST['salary_id'] ?? 0);
        $paymentDate    = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
        $paymentMethod  = sanitize($_POST['payment_method'] ?? 'Bank Transfer');

        if ($salaryDetailId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid payroll record selected.'], 400);
        }

        try {
            $db->beginTransaction();

            // 1. Fetch details
            $stmt = $db->prepare("
                SELECT d.*, s.first_name, s.last_name, s.employee_no, p.month, p.year
                FROM salary_details d
                JOIN staff s ON d.staff_id = s.id
                JOIN salary_processing p ON d.processing_id = p.id
                WHERE d.id = ? AND d.payment_status = 'Pending'
            ");
            $stmt->execute([$salaryDetailId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$detail) {
                jsonResponse(['success' => false, 'message' => 'Record not found or already paid.'], 404);
            }

            $netSalary = (float)$detail['net_salary'];
            $staffId   = (int)$detail['staff_id'];
            $staffName = $detail['first_name'] . ' ' . $detail['last_name'];
            $monthText = date('F Y', mktime(0,0,0,$detail['month'], 1, $detail['year']));

            // 2. Mark salary row as Paid
            $payStmt = $db->prepare("
                UPDATE salary_details 
                SET payment_status = 'Paid', payment_date = :date, payment_method = :method
                WHERE id = :id
            ");
            $payStmt->execute([
                'date'   => $paymentDate,
                'method' => $paymentMethod,
                'id'     => $salaryDetailId
            ]);

            // 3. Generate salary_slips voucher registry
            $slipNum = 'SLIP-' . $detail['year'] . str_pad($detail['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($detail['staff_id'], 4, '0', STR_PAD_LEFT);
            $slipStmt = $db->prepare("
                INSERT INTO salary_slips (salary_detail_id, slip_number, prepared_by, approved_by)
                VALUES (:detail_id, :slip_num, :by, :by)
                ON DUPLICATE KEY UPDATE slip_number = VALUES(slip_number)
            ");
            $slipStmt->execute([
                'detail_id' => $salaryDetailId,
                'slip_num'  => $slipNum,
                'by'        => $_SESSION['user_id'] ?? null
            ]);

            // 4. Update advance recovery balances if there was a recovery deduction
            $advDeduction = (float)$detail['advance_salary_deduction'];
            if ($advDeduction > 0) {
                $adv = $db->query("SELECT * FROM advance_salary WHERE staff_id = $staffId AND status = 'Pending' LIMIT 1")->fetch();
                if ($adv) {
                    $newPaid = (float)$adv['paid_amount'] + $advDeduction;
                    $newRem  = (float)$adv['remaining_balance'] - $advDeduction;
                    $status  = ($newRem <= 0) ? 'Recovered' : 'Pending';

                    $upAdv = $db->prepare("
                        UPDATE advance_salary 
                        SET paid_amount = :paid, remaining_balance = :rem, status = :status 
                        WHERE id = :id
                    ");
                    $upAdv->execute([
                        'paid'   => $newPaid,
                        'rem'    => $newRem,
                        'status' => $status,
                        'id'     => $adv['id']
                    ]);
                }
            }

            // 5. ACCOUNTS INTEGRATION: Register salary paid as expense in accounts module
            // Find category ID for "Salaries"
            $catStmt = $db->query("SELECT id FROM expense_categories WHERE name LIKE '%Salaries%' OR name LIKE '%Payroll%' LIMIT 1");
            $catId = (int)$catStmt->fetchColumn();
            if ($catId <= 0) {
                // Autocreate Salaries category if missing
                $db->exec("INSERT INTO expense_categories (name, description, status) VALUES ('Staff Salaries', 'Salary payroll operational expenses', 'Active')");
                $catId = (int)$db->lastInsertId();
            }

            $expInsert = $db->prepare("
                INSERT INTO expenses (category_id, title, amount, expense_date, payment_method, vendor_supplier, paid_by, remarks)
                VALUES (:cat_id, :title, :amount, :date, :method, :vendor, :by, :remarks)
            ");
            $expInsert->execute([
                'cat_id'  => $catId,
                'title'   => "Salary Disbursed - $staffName",
                'amount'  => $netSalary,
                'date'    => $paymentDate,
                'method'  => $paymentMethod === 'Bank Transfer' ? 'Bank' : $paymentMethod,
                'vendor'  => "Staff - " . $detail['employee_no'],
                'by'      => $_SESSION['user_id'] ?? null,
                'remarks' => "Automated payroll posting for the month of $monthText"
            ]);

            // Sync with staff_salaries table
            $syncPayStmt = $db->prepare("
                UPDATE staff_salaries 
                SET payment_status = 'Paid', payment_date = :date
                WHERE staff_id = :sid AND month = :month AND year = :year
            ");
            $syncPayStmt->execute([
                'date'  => $paymentDate,
                'sid'   => $staffId,
                'month' => $detail['month'],
                'year'  => $detail['year']
            ]);

            // If Cash payment, sync active cash register
            if ($paymentMethod === 'Cash') {
                $cashRegId = (int)($_SESSION['cash_register_id'] ?? 0);
                if ($cashRegId <= 0) {
                    // Fetch today's open register if any
                    $cashRegId = (int)$db->query("SELECT id FROM cash_register WHERE status = 'Open' ORDER BY id DESC LIMIT 1")->fetchColumn();
                }
                if ($cashRegId > 0 && class_exists('Cash')) {
                    Cash::recalculateRegister($cashRegId);
                }
            }

            $db->commit();
            auditLog('Staff Salary Paid', "Processed salary payment slip $slipNum for $staffName: Rs. " . number_format($netSalary, 2));
            jsonResponse(['success' => true, 'message' => "Salary payment processed and voucher generated successfully."]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Failed to process salary payment: ' . $e->getMessage()]);
        }
        break;


    // ── 4. ALLOWANCES CRUD ────────────────────────────────────
    case 'save_allowance':
        $id      = (int)($_POST['id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $type    = sanitize($_POST['allowance_type'] ?? 'Teaching Allowance');
        $amount  = (float)($_POST['amount'] ?? 0.00);
        $desc    = sanitize($_POST['description'] ?? '');
        $status  = sanitize($_POST['status'] ?? 'Active');

        if ($staffId <= 0 || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Mandatory fields are required.'], 400);
        }

        try {
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE allowances 
                    SET staff_id = ?, allowance_type = ?, amount = ?, description = ?, status = ?
                    WHERE id = ?
                ");
                $ok = $stmt->execute([$staffId, $type, $amount, $desc, $status, $id]);
                $msg = 'Allowance successfully updated.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO allowances (staff_id, allowance_type, amount, description, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $ok = $stmt->execute([$staffId, $type, $amount, $desc, $status]);
                $msg = 'Allowance successfully assigned.';
            }
            if ($ok) {
                auditLog('Allowance Saved', "Assigned/Updated $type for Staff ID: $staffId (Rs. $amount)");
                jsonResponse(['success' => true, 'message' => $msg]);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_allowance':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $ok = $db->prepare("DELETE FROM allowances WHERE id = ?")->execute([$id]);
            if ($ok) jsonResponse(['success' => true, 'message' => 'Allowance removed.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error.']);
        }
        break;


    // ── 5. DEDUCTIONS CRUD ────────────────────────────────────
    case 'save_deduction':
        $id      = (int)($_POST['id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $type    = sanitize($_POST['deduction_type'] ?? 'Loan');
        $amount  = (float)($_POST['amount'] ?? 0.00);
        $reason  = sanitize($_POST['reason'] ?? '');
        $status  = sanitize($_POST['status'] ?? 'Active');

        if ($staffId <= 0 || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Mandatory fields are required.'], 400);
        }

        try {
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE deductions 
                    SET staff_id = ?, deduction_type = ?, amount = ?, reason = ?, status = ?
                    WHERE id = ?
                ");
                $ok = $stmt->execute([$staffId, $type, $amount, $reason, $status, $id]);
                $msg = 'Deduction successfully updated.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO deductions (staff_id, deduction_type, amount, reason, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $ok = $stmt->execute([$staffId, $type, $amount, $reason, $status]);
                $msg = 'Deduction successfully logged.';
            }
            if ($ok) {
                auditLog('Deduction Saved', "Assigned/Updated deduction $type for Staff ID $staffId (Rs. $amount)");
                jsonResponse(['success' => true, 'message' => $msg]);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_deduction':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $ok = $db->prepare("DELETE FROM deductions WHERE id = ?")->execute([$id]);
            if ($ok) jsonResponse(['success' => true, 'message' => 'Deduction removed.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error.']);
        }
        break;


    // ── 6. ADVANCE SALARY CRUD ────────────────────────────────
    case 'save_advance_salary':
        $id      = (int)($_POST['id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $amount  = (float)($_POST['amount'] ?? 0.00);
        $date    = sanitize($_POST['advance_date'] ?? date('Y-m-d'));
        $install = (int)($_POST['installments'] ?? 1);
        $reason  = sanitize($_POST['reason'] ?? '');

        if ($staffId <= 0 || $amount <= 0 || $install <= 0) {
            jsonResponse(['success' => false, 'message' => 'Mandatory fields are required.'], 400);
        }

        $instAmt = $amount / $install;

        try {
            if ($id > 0) {
                $stmtExist = $db->prepare("SELECT paid_amount FROM advance_salary WHERE id = ?");
                $stmtExist->execute([$id]);
                $paidAmt = (float)$stmtExist->fetchColumn();
                $remBal = $amount - $paidAmt;
                $status = ($remBal <= 0) ? 'Recovered' : 'Pending';

                $stmt = $db->prepare("
                    UPDATE advance_salary 
                    SET staff_id = ?, advance_date = ?, amount = ?, reason = ?, installments = ?, installment_amount = ?, remaining_balance = ?, status = ?
                    WHERE id = ?
                ");
                $ok = $stmt->execute([$staffId, $date, $amount, $reason, $install, $instAmt, $remBal, $status, $id]);
                $msg = 'Advance salary details updated.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO advance_salary (staff_id, advance_date, amount, reason, installments, installment_amount, remaining_balance, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $ok = $stmt->execute([$staffId, $date, $amount, $reason, $install, $instAmt, $amount]);
                $msg = 'Advance salary registered successfully. Recovery will trigger in monthly runs.';
            }
            if ($ok) {
                auditLog('Advance Salary Saved', "Disbursed/Updated advance for Staff ID: $staffId (Rs. $amount)");
                jsonResponse(['success' => true, 'message' => $msg]);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_advance_salary':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $ok = $db->prepare("DELETE FROM advance_salary WHERE id = ?")->execute([$id]);
            if ($ok) jsonResponse(['success' => true, 'message' => 'Advance salary application deleted.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error.']);
        }
        break;


    // ── 7. BONUSES CRUD ───────────────────────────────────────
    case 'save_bonus':
        $id      = (int)($_POST['id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $type    = sanitize($_POST['bonus_type'] ?? 'Eid Bonus');
        $amount  = (float)($_POST['amount'] ?? 0.00);
        $date    = sanitize($_POST['date_earned'] ?? date('Y-m-d'));
        $desc    = sanitize($_POST['description'] ?? '');
        $status  = sanitize($_POST['status'] ?? 'Active');

        if ($staffId <= 0 || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Mandatory fields are required.'], 400);
        }

        try {
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE bonuses 
                    SET staff_id = ?, bonus_type = ?, amount = ?, description = ?, status = ?, date_earned = ?
                    WHERE id = ?
                ");
                $ok = $stmt->execute([$staffId, $type, $amount, $desc, $status, $date, $id]);
                $msg = 'Bonus/Incentive successfully updated.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO bonuses (staff_id, bonus_type, amount, description, status, date_earned)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ok = $stmt->execute([$staffId, $type, $amount, $desc, $status, $date]);
                $msg = 'Bonus/Incentive assigned successfully.';
            }
            if ($ok) {
                auditLog('Bonus Saved', "Assigned/Updated bonus $type for Staff ID $staffId (Rs. $amount)");
                jsonResponse(['success' => true, 'message' => $msg]);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_bonus':
        $id = (int)($_POST['id'] ?? 0);
        try {
            $ok = $db->prepare("DELETE FROM bonuses WHERE id = ?")->execute([$id]);
            if ($ok) jsonResponse(['success' => true, 'message' => 'Bonus ledger removed.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error.']);
        }
        break;


    // ── 8. PAYROLL SETTINGS ───────────────────────────────────
    case 'save_payroll_settings':
        $payDate = (int)($_POST['salary_payment_date'] ?? 10);
        $days    = (int)($_POST['working_days_per_month'] ?? 26);
        $late    = (float)($_POST['late_deduction_rule'] ?? 0.25);
        $half    = (float)($_POST['half_day_rule'] ?? 0.50);
        $absent  = (float)($_POST['absent_deduction_rule'] ?? 1.00);
        $ot      = (float)($_POST['overtime_rate'] ?? 0.00);
        $curr    = sanitize($_POST['currency'] ?? 'Rs.');
        $method  = sanitize($_POST['default_payment_method'] ?? 'Bank Transfer');

        try {
            $stmt = $db->prepare("
                UPDATE payroll_settings 
                SET salary_payment_date = :pay, working_days_per_month = :days, late_deduction_rule = :late,
                    half_day_rule = :half, absent_deduction_rule = :absent, overtime_rate = :ot,
                    currency = :curr, default_payment_method = :method
                WHERE id = 1
            ");
            $ok = $stmt->execute([
                'pay'    => $payDate,
                'days'   => $days,
                'late'   => $late,
                'half'   => $half,
                'absent' => $absent,
                'ot'     => $ot,
                'curr'   => $curr,
                'method' => $method
            ]);

            if ($ok) {
                auditLog('Payroll Settings Saved', 'Updated operational payroll settings and late fine ratios.');
                jsonResponse(['success' => true, 'message' => 'Payroll settings configured successfully.']);
            }
            jsonResponse(['success' => false, 'message' => 'Failed to save settings.']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Action not supported.'], 404);
        break;
}
