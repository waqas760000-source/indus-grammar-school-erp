<?php
/**
 * Indus Grammar School ERP - PayrollService
 * Handles automatic monthly salary issuance, bulk calculations, and operational settings.
 * Version 4.1.0
 */

class PayrollService {

    /**
     * Check if automatic salary issuance should run for the current month, and execute if due.
     *
     * @param bool $forceRun If true, bypasses date checks and executes immediately for the specified month/year.
     * @param int|null $targetMonth Optional target month (defaults to current month)
     * @param int|null $targetYear Optional target year (defaults to current year)
     * @return array
     */
    public static function checkAndRunAutoPayroll(bool $forceRun = false, ?int $targetMonth = null, ?int $targetYear = null): array {
        $db = Database::getConnection();

        // Ensure database table columns exist
        self::ensureSchema();

        // 1. Fetch payroll settings
        $settings = [];
        try {
            $settings = $db->query("SELECT * FROM payroll_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Payroll settings table not accessible: ' . $e->getMessage()];
        }

        if (!$settings) {
            return ['success' => false, 'message' => 'Payroll settings not configured.'];
        }

        $enabled    = (bool)($settings['auto_issue_enabled'] ?? 1);
        $issueDay   = (int)($settings['auto_issue_day'] ?? 10);
        $autoStatus = $settings['auto_issue_status'] ?? 'Pending';
        $lastRunKey = $settings['last_auto_issue_run'] ?? '';

        if (!$enabled && !$forceRun) {
            return ['success' => false, 'message' => 'Automatic monthly salary issuance is currently disabled in settings.'];
        }

        $currentDay = (int)date('j');
        $month = $targetMonth ?: (int)date('m');
        $year  = $targetYear ?: (int)date('Y');
        $runKey = sprintf('%04d-%02d', $year, $month);

        // Check if day of month criterion met (e.g. 10th or later)
        if (!$forceRun && $currentDay < $issueDay) {
            return [
                'success' => false,
                'message' => "Automatic salary issuance is scheduled for the {$issueDay}th of every month. Today is day {$currentDay}."
            ];
        }

        // Check if already executed for this month/year unless forced
        if (!$forceRun && $lastRunKey === $runKey) {
            return [
                'success' => true,
                'already_run' => true,
                'message' => "Staff salaries for " . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . " have already been issued automatically."
            ];
        }

        // Execute bulk payroll processing run
        return self::executeBulkPayrollRun($month, $year, $autoStatus, $runKey);
    }

    /**
     * Execute full payroll generation for a given month and year
     */
    public static function executeBulkPayrollRun(int $month, int $year, string $defaultStatus = 'Pending', string $runKey = ''): array {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Load settings configurations
            $settings = $db->query("SELECT * FROM payroll_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            $workDaysPerMonth = (int)($settings['working_days_per_month'] ?? 26);
            $lateDeductRule   = (float)($settings['late_deduction_rule'] ?? 0.25);
            $halfDayRule      = (float)($settings['half_day_rule'] ?? 0.50);
            $absentDeductRule = (float)($settings['absent_deduction_rule'] ?? 1.00);

            // Fetch active staff list
            $stmt = $db->query("SELECT * FROM staff WHERE status = 'Active'");
            $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($staffList)) {
                $db->rollBack();
                return ['success' => false, 'message' => 'No active staff members found to issue salary for.'];
            }

            // Create/Ensure salary_processing registry row exists
            $procStmt = $db->prepare("
                INSERT INTO salary_processing (month, year, status)
                VALUES (:month, :year, 'Processed')
                ON DUPLICATE KEY UPDATE status = 'Processed'
            ");
            $procStmt->execute(['month' => $month, 'year' => $year]);

            $stmtProcId = $db->prepare("SELECT id FROM salary_processing WHERE month = :m AND year = :y LIMIT 1");
            $stmtProcId->execute(['m' => $month, 'y' => $year]);
            $procId = (int)$stmtProcId->fetchColumn();

            $detailInsert = $db->prepare("
                INSERT INTO salary_details 
                (processing_id, staff_id, working_days, present_days, absent_days, late_days, leave_days, half_days, basic_salary, allowances, deductions, advance_salary_deduction, bonus, net_salary, payment_status)
                VALUES 
                (:pid, :sid, :work, :pres, :abs, :late, :leave, :half, :basic, :allow, :deduct, :adv, :bonus, :net, :status)
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
                    net_salary = VALUES(net_salary),
                    payment_status = VALUES(payment_status)
            ");

            $syncSalStmt = $db->prepare("
                INSERT INTO staff_salaries (staff_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status)
                VALUES (:sid, :month, :year, :basic, :allowances, :deductions, :net, :status)
                ON DUPLICATE KEY UPDATE
                    basic_salary = VALUES(basic_salary),
                    allowances = VALUES(allowances),
                    deductions = VALUES(deductions),
                    net_salary = VALUES(net_salary),
                    payment_status = VALUES(payment_status)
            ");

            $processedCount = 0;

            foreach ($staffList as $st) {
                $staffId = $st['id'];

                // Get salary setup parameters
                $setupStmt = $db->prepare("SELECT * FROM salary_setup WHERE staff_id = ? AND status = 'Active'");
                $setupStmt->execute([$staffId]);
                $setup = $setupStmt->fetch(PDO::FETCH_ASSOC);

                $basicSalary = (float)($setup['basic_salary'] ?? $st['salary']);
                if ($basicSalary <= 0) $basicSalary = 20000.00;

                $hra     = (float)($setup['hra'] ?? 0);
                $medical = (float)($setup['medical_allowance'] ?? 0);
                $trans   = (float)($setup['transport_allowance'] ?? 0);
                $otherA  = (float)($setup['other_allowances'] ?? 0);
                
                $pf      = (float)($setup['provident_fund'] ?? 0);
                $tax     = (float)($setup['tax_deduction'] ?? 0);
                $eobi    = (float)($setup['eobi'] ?? 0);
                $otherD  = (float)($setup['other_deductions'] ?? 0);

                // Calculate attendance metrics
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

                $oneDayWage = $basicSalary / max(1, $workDaysPerMonth);
                $attendanceDeduction = 0.00;
                $attendanceDeduction += $absentDays * $oneDayWage * $absentDeductRule;
                $attendanceDeduction += $lateDays * $oneDayWage * $lateDeductRule;
                $attendanceDeduction += $halfDays * $oneDayWage * $halfDayRule;

                // Load allowances
                $allowStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM allowances WHERE staff_id = ? AND status = 'Active'");
                $allowStmt->execute([$staffId]);
                $activeAllowances = (float)$allowStmt->fetchColumn();

                // Load deductions
                $deductStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM deductions WHERE staff_id = ? AND status = 'Active'");
                $deductStmt->execute([$staffId]);
                $activeDeductions = (float)$deductStmt->fetchColumn();

                // Load bonuses
                $bonusStmt = $db->prepare("
                    SELECT COALESCE(SUM(amount),0) 
                    FROM bonuses 
                    WHERE staff_id = :sid AND status = 'Active' AND MONTH(date_earned) = :month AND YEAR(date_earned) = :year
                ");
                $bonusStmt->execute(['sid' => $staffId, 'month' => $month, 'year' => $year]);
                $monthBonus = (float)$bonusStmt->fetchColumn();

                // Load advances
                $advStmt = $db->prepare("SELECT * FROM advance_salary WHERE staff_id = ? AND status = 'Pending' LIMIT 1");
                $advStmt->execute([$staffId]);
                $advance = $advStmt->fetch(PDO::FETCH_ASSOC);

                $advanceDeduction = 0.00;
                if ($advance) {
                    $installmentVal = (float)$advance['installment_amount'];
                    $remainingBal   = (float)$advance['remaining_balance'];
                    $advanceDeduction = min($installmentVal, $remainingBal);
                }

                $totalAllowances = $hra + $medical + $trans + $otherA + $activeAllowances;
                $totalDeductions = $pf + $tax + $eobi + $otherD + $activeDeductions + $attendanceDeduction;

                $netSalary = ($basicSalary + $totalAllowances + $monthBonus) - ($totalDeductions + $advanceDeduction);
                if ($netSalary < 0) $netSalary = 0.00;

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
                    'net'     => $netSalary,
                    'status'  => $defaultStatus
                ]);

                $syncSalStmt->execute([
                    'sid'        => $staffId,
                    'month'      => $month,
                    'year'       => $year,
                    'basic'      => $basicSalary,
                    'allowances' => $totalAllowances + $monthBonus,
                    'deductions' => $totalDeductions + $advanceDeduction,
                    'net'        => $netSalary,
                    'status'     => $defaultStatus
                ]);

                $processedCount++;
            }

            // Update last_auto_issue_run timestamp in payroll_settings
            if ($runKey) {
                $stmtUpdateRun = $db->prepare("UPDATE payroll_settings SET last_auto_issue_run = ? WHERE id = 1");
                $stmtUpdateRun->execute([$runKey]);
            }

            $db->commit();

            $monthText = date('F Y', mktime(0, 0, 0, $month, 1, $year));
            $logMsg = "Automatic Monthly Staff Salary Issued for $monthText ($processedCount employees processed)";
            if (function_exists('auditLog')) {
                auditLog('Automatic Payroll Issued', $logMsg);
            }

            return [
                'success' => true,
                'count'   => $processedCount,
                'message' => "Automatic staff salary payroll issued successfully for $monthText ($processedCount employees)."
            ];

        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Error issuing automatic payroll: ' . $e->getMessage()];
        }
    }

    /**
     * Ensure database columns and tables exist for payroll
     */
    private static function ensureSchema(): void {
        $db = Database::getConnection();

        // 1. Ensure staff_salaries table
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS staff_salaries (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    staff_id INT NOT NULL,
                    month INT NOT NULL,
                    year INT NOT NULL,
                    basic_salary DECIMAL(10,2) DEFAULT 0.00,
                    allowances DECIMAL(10,2) DEFAULT 0.00,
                    deductions DECIMAL(10,2) DEFAULT 0.00,
                    net_salary DECIMAL(10,2) DEFAULT 0.00,
                    payment_status ENUM('Pending','Paid','Cancelled') DEFAULT 'Pending',
                    payment_date DATE DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY staff_month_year (staff_id, month, year)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Exception $e) {}

        // 2. Ensure payroll_settings columns
        $queries = [
            "ALTER TABLE payroll_settings ADD COLUMN auto_issue_enabled TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE payroll_settings ADD COLUMN auto_issue_day INT NOT NULL DEFAULT 10",
            "ALTER TABLE payroll_settings ADD COLUMN auto_issue_status VARCHAR(20) NOT NULL DEFAULT 'Pending'",
            "ALTER TABLE payroll_settings ADD COLUMN last_auto_issue_run VARCHAR(20) DEFAULT NULL"
        ];
        foreach ($queries as $q) {
            try { $db->exec($q); } catch (Exception $e) {}
        }
    }
}
