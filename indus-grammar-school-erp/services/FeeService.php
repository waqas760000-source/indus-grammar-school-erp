<?php
/**
 * Indus Grammar School ERP - Normalized Fee Collection Business Logic Service
 * Version 4.0.0
 */

class FeeService {

    /**
     * Generate month-wise billing entry in the fee_ledger for a student.
     * Prevents duplicate billing, auto-binds structure, and calculates annual/admission fees once.
     */
    public function generateMonthlyLedgerEntry($studentId, $month, $dueDate = null) {
        try {
            $db = Database::getConnection();
            $academicYear = CURRENT_ACADEMIC_YEAR;

            // 1. Check if ledger record already exists for this month/year
            $checkStmt = $db->prepare("
                SELECT id FROM fee_ledger 
                WHERE student_id = :sid AND month = :month AND academic_year = :year
            ");
            $checkStmt->execute(['sid' => $studentId, 'month' => $month, 'year' => $academicYear]);
            if ($checkStmt->fetchColumn()) {
                return ['status' => false, 'message' => "Ledger entry already exists for $month ($academicYear)."];
            }

            // 2. Ensure student has active fee structure assignment
            Fee::ensureStudentAssignment($studentId);
            $assignment = Fee::getStudentAssignment($studentId);
            if (!$assignment || $assignment['status'] !== 'Active') {
                return ['status' => false, 'message' => 'No active fee structure assignment found for this student.'];
            }

            // 3. Determine if Admission Fee should be billed (charged only once ever)
            $prevLedgers = $db->prepare("SELECT COUNT(*) FROM fee_ledger WHERE student_id = :sid");
            $prevLedgers->execute(['sid' => $studentId]);
            $hasPrevBilled = ((int)$prevLedgers->fetchColumn() > 0);
            $admissionFee = $hasPrevBilled ? 0.00 : (float)$assignment['admission_fee'];

            // 4. Determine if Annual Charges should be billed (charged only once per academic year)
            $annBilled = $db->prepare("
                SELECT COUNT(*) FROM fee_ledger 
                WHERE student_id = :sid AND academic_year = :year AND annual_charges > 0
            ");
            $annBilled->execute(['sid' => $studentId, 'year' => $academicYear]);
            $hasAnnBilled = ((int)$annBilled->fetchColumn() > 0);
            $annualCharges = $hasAnnBilled ? 0.00 : (float)$assignment['annual_charges'];

            // 5. Calculate Discount (on Tuition Fee)
            $tuitionFee = (float)$assignment['tuition_fee'];
            $discountPct = (float)$assignment['discount_percentage'];
            $discountFlat = (float)$assignment['discount_flat'];
            
            $discountAmount = 0.00;
            if ($discountPct > 0) {
                $discountAmount = ($tuitionFee * $discountPct) / 100;
            } elseif ($discountFlat > 0) {
                $discountAmount = $discountFlat;
            }

            // 6. Fee heads
            $computerFee = (float)$assignment['computer_fee'];
            $examFee = (float)$assignment['exam_fee'];
            $transportFee = (float)$assignment['transport_fee'];
            $securityDeposit = $hasPrevBilled ? 0.00 : (float)$assignment['security_deposit']; // security once
            $otherCharges = (float)$assignment['other_charges'];

            // Net Payable
            $totalPayable = ($tuitionFee + $admissionFee + $computerFee + $examFee + $transportFee + $annualCharges + $securityDeposit + $otherCharges) - $discountAmount;
            if ($totalPayable < 0) $totalPayable = 0.00;

            // Default due date to 15th of current month if none supplied
            if (empty($dueDate)) {
                $dueDate = date('Y-m-15');
            }

            // 7. Save to Ledger
            $insStmt = $db->prepare("
                INSERT INTO fee_ledger 
                (student_id, month, academic_year, admission_fee, tuition_fee, computer_fee, exam_fee, transport_fee, annual_charges, security_deposit, other_charges, fine_amount, discount_amount, total_payable, paid_amount, status, due_date)
                VALUES 
                (:sid, :month, :year, :adm, :tui, :comp, :exam, :trans, :ann, :sec, :oth, 0.00, :disc, :payable, 0.00, 'Pending', :due)
            ");
            
            $ok = $insStmt->execute([
                'sid'     => $studentId,
                'month'   => $month,
                'year'    => $academicYear,
                'adm'     => $admissionFee,
                'tui'     => $tuitionFee,
                'comp'    => $computerFee,
                'exam'    => $examFee,
                'trans'   => $transportFee,
                'ann'     => $annualCharges,
                'sec'     => $securityDeposit,
                'oth'     => $otherCharges,
                'disc'    => $discountAmount,
                'payable' => $totalPayable,
                'due'     => $dueDate
            ]);

            if ($ok) {
                return ['status' => true, 'message' => "Fee ledger entry generated successfully for $month."];
            } else {
                return ['status' => false, 'message' => 'Failed to save ledger record.'];
            }

        } catch (Exception $e) {
            error_log("generateMonthlyLedgerEntry exception: " . $e->getMessage());
            return ['status' => false, 'message' => 'System error: ' . $e->getMessage()];
        }
    }

    /**
     * Dynamically calculates late fees past the due date and grace period parameters.
     */
    public function calculateLateFineForLedger($ledger) {
        if ($ledger['status'] === 'Paid') {
            return (float)$ledger['fine_amount'];
        }

        $dueDate = strtotime($ledger['due_date']);
        $today = strtotime(date('Y-m-d'));
        if ($today <= $dueDate) {
            return 0.00;
        }

        // Get Fine parameters settings
        $settings = Fee::getSettings();
        $graceDays = (int)$settings['grace_days'];
        $lateFineAmount = (float)$settings['late_fine_amount'];
        $fineType = $settings['fine_type'];

        $diffSeconds = $today - $dueDate;
        $lateDays = ceil($diffSeconds / (60 * 60 * 24));

        if ($lateDays <= $graceDays) {
            return 0.00;
        }

        if ($fineType === 'Daily') {
            return $lateDays * $lateFineAmount;
        } elseif ($fineType === 'Monthly') {
            $months = ceil($lateDays / 30);
            return $months * $lateFineAmount;
        } else {
            // Fixed fine
            return $lateFineAmount;
        }
    }

    /**
     * Interface to collect fee payments
     */
    public function collectFeePayment($data) {
        // Validate amounts
        if ((float)($data['amount_paid'] ?? 0) <= 0) {
            return ['status' => false, 'message' => 'Amount received must be a positive number.'];
        }
        
        return Fee::recordPayment($data);
    }

    /**
     * Generate monthly fee ledger entries in bulk for ALL active School & Academy students (or specific filters) in 1-click.
     *
     * @param string $month Month string (e.g., 'September 2026')
     * @param string $academicType 'All', 'School', or 'Academy'
     * @param int|null $classId Optional class ID filter
     * @param string|null $dueDate Due date for generated ledgers (defaults to 15th of month)
     * @return array
     */
    public function generateBulkMonthlyFees(string $month, string $academicType = 'All', ?int $classId = null, ?string $dueDate = null): array {
        $db = Database::getConnection();

        try {
            $where = "WHERE status = 'Active'";
            $params = [];

            if ($academicType !== 'All' && !empty($academicType)) {
                $where .= " AND academic_type = :type";
                $params['type'] = $academicType;
            }

            if ($classId && $classId > 0) {
                $where .= " AND class_id = :cid";
                $params['cid'] = $classId;
            }

            $stmt = $db->prepare("SELECT id, first_name, last_name, admission_no, academic_type, class_id FROM students $where ORDER BY id ASC");
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($students)) {
                return [
                    'status'  => false,
                    'message' => 'No active students found matching the selected filter criteria.'
                ];
            }

            if (empty($dueDate)) {
                $dueDate = date('Y-m-15');
            }

            $generatedCount = 0;
            $skippedCount   = 0;
            $totalBilled    = 0.00;

            foreach ($students as $st) {
                $res = $this->generateMonthlyLedgerEntry($st['id'], $month, $dueDate);
                if (!empty($res['status'])) {
                    $generatedCount++;
                    $amtStmt = $db->prepare("SELECT total_payable FROM fee_ledger WHERE student_id = ? AND month = ? LIMIT 1");
                    $amtStmt->execute([$st['id'], $month]);
                    $totalBilled += (float)$amtStmt->fetchColumn();
                } else {
                    $skippedCount++;
                }
            }

            $auditType = ($academicType === 'All') ? 'Whole School & Academy' : $academicType;
            if (function_exists('auditLog')) {
                auditLog('1-Click Monthly Fee Generated', "Generated monthly fee ledgers for $month ($auditType): $generatedCount generated, $skippedCount already billed.");
            }

            return [
                'status'          => true,
                'month'           => $month,
                'generated_count' => $generatedCount,
                'skipped_count'   => $skippedCount,
                'total_billed'    => $totalBilled,
                'message'         => "1-Click Monthly Fee Generation completed for $month ($auditType): $generatedCount student fee vouchers created, $skippedCount already existed."
            ];

        } catch (Exception $e) {
            error_log("generateBulkMonthlyFees exception: " . $e->getMessage());
            return ['status' => false, 'message' => 'System error: ' . $e->getMessage()];
        }
    }

    /**
     * Automatically check and generate monthly fees on the 1st date of every month for Whole School & Academy
     */
    public function checkAndRunAutoFee(bool $forceRun = false, ?string $targetMonth = null): array {
        $db = Database::getConnection();

        self::ensureFeeSettingsSchema();

        $settings = [];
        try {
            $settings = $db->query("SELECT * FROM fee_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        $enabled    = (bool)($settings['auto_fee_enabled'] ?? 1);
        $autoDay    = (int)($settings['auto_fee_day'] ?? 1);
        $lastRunKey = $settings['last_auto_fee_run'] ?? '';

        if (!$enabled && !$forceRun) {
            return ['status' => false, 'message' => 'Automatic 1st date monthly fee generation is currently disabled.'];
        }

        $currentDay = (int)date('j');
        $month      = $targetMonth ?: date('F Y');
        $runKey     = date('Y-m');

        if (!$forceRun && $currentDay < $autoDay) {
            return [
                'status'  => false,
                'message' => "Automatic fee generation is scheduled for day $autoDay of the month. Today is day $currentDay."
            ];
        }

        if (!$forceRun && $lastRunKey === $runKey) {
            return [
                'status'      => true,
                'already_run' => true,
                'message'     => "Monthly fee for $month has already been generated automatically on the 1st."
            ];
        }

        // Execute bulk generation for ALL (Whole School & Academy)
        $res = $this->generateBulkMonthlyFees($month, 'All', null, date('Y-m-15'));

        if (!empty($res['status'])) {
            $upRun = $db->prepare("UPDATE fee_settings SET last_auto_fee_run = ? WHERE id = 1");
            $upRun->execute([$runKey]);
        }

        return $res;
    }

    /**
     * Ensure database columns exist in fee_settings
     */
    private static function ensureFeeSettingsSchema(): void {
        $db = Database::getConnection();
        $queries = [
            "ALTER TABLE fee_settings ADD COLUMN auto_fee_enabled TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE fee_settings ADD COLUMN auto_fee_day INT NOT NULL DEFAULT 1",
            "ALTER TABLE fee_settings ADD COLUMN last_auto_fee_run VARCHAR(20) DEFAULT NULL"
        ];
        foreach ($queries as $q) {
            try { $db->exec($q); } catch (Exception $e) {}
        }
    }
}
