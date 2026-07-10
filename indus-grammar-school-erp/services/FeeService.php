<?php
/**
 * Indus Grammar School ERP - FeeService
 * Version 1.0.0
 */

class FeeService {

    /**
     * Generate a fee challan for a student based on class fee structure
     *
     * @param int $studentId
     * @param string $month
     * @param string $dueDate
     * @return array
     */
    public function generateChallan(int $studentId, string $month, string $dueDate): array {
        $student = Student::findById($studentId);
        if (!$student) return ['status' => false, 'message' => 'Student not found.'];

        $structures = Fee::getStructuresByClass((int)$student['class_id']);
        if (empty($structures)) return ['status' => false, 'message' => 'No fee structure defined for this class.'];

        // Check for existing unpaid challan for same month
        try {
            $db = Database::getConnection();
            $exists = $db->prepare("SELECT id FROM fee_challans WHERE student_id = :sid AND month = :month AND academic_year = :year");
            $exists->execute(['sid' => $studentId, 'month' => $month, 'year' => CURRENT_ACADEMIC_YEAR]);
            if ($exists->fetch()) {
                return ['status' => false, 'message' => "A challan already exists for $month for this student."];
            }
        } catch (Exception $e) {}

        // Calculate totals
        $totalAmount   = array_sum(array_column($structures, 'amount'));
        $discounts     = Fee::getDiscountsByStudent($studentId);
        $discountTotal = 0;
        foreach ($discounts as $d) {
            if ($d['percentage'] > 0) $discountTotal += ($totalAmount * $d['percentage'] / 100);
            elseif ($d['flat_amount'] > 0) $discountTotal += $d['flat_amount'];
        }
        $fines     = Fee::getFinesByStudent($studentId);
        $fineTotal = array_sum(array_column($fines, 'amount'));
        $netAmount = $totalAmount - $discountTotal + $fineTotal;

        $items = array_map(fn($s) => ['fee_type' => $s['fee_type'], 'amount' => $s['amount']], $structures);
        $challanNo = Fee::generateChallanNumber();

        $challanId = Fee::createChallan([
            'student_id'      => $studentId,
            'challan_no'      => $challanNo,
            'month'           => $month,
            'academic_year'   => CURRENT_ACADEMIC_YEAR,
            'total_amount'    => $totalAmount,
            'discount_amount' => $discountTotal,
            'fine_amount'     => $fineTotal,
            'net_amount'      => max(0, $netAmount),
            'due_date'        => $dueDate,
        ], $items);

        if ($challanId) {
            auditLog('Challan Generated', "Challan $challanNo generated for student ID $studentId — Month: $month");
            return ['status' => true, 'message' => "Challan $challanNo generated successfully.", 'id' => $challanId, 'challan_no' => $challanNo];
        }
        return ['status' => false, 'message' => 'Failed to generate challan.'];
    }

    /**
     * Collect a fee payment
     *
     * @param array $data
     * @return array
     */
    public function collectPayment(array $data): array {
        $errors = [];
        if (empty($data['student_id']))   $errors[] = 'Student is required.';
        if (empty($data['amount_paid']) || $data['amount_paid'] <= 0) $errors[] = 'Valid amount is required.';
        if (empty($data['payment_date'])) $errors[] = 'Payment date is required.';
        if (empty($data['payment_method'])) $errors[] = 'Payment method is required.';
        if ($errors) return ['status' => false, 'message' => implode(' ', $errors)];

        $data['receipt_no'] = Fee::generateReceiptNumber();
        $id = Fee::recordPayment($data);
        if ($id) {
            auditLog('Fee Collected', "Receipt {$data['receipt_no']} — Amount: Rs. {$data['amount_paid']} from student ID {$data['student_id']}");
            return ['status' => true, 'message' => "Payment recorded. Receipt No: {$data['receipt_no']}", 'receipt_no' => $data['receipt_no'], 'id' => $id];
        }
        return ['status' => false, 'message' => 'Failed to record payment.'];
    }

    /**
     * Add a discount to a student
     *
     * @param int $studentId
     * @param array $data
     * @return array
     */
    public function addDiscount(int $studentId, array $data): array {
        if (empty($data['discount_type'])) return ['status' => false, 'message' => 'Discount type is required.'];
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO fee_discounts (student_id, discount_type, percentage, flat_amount, reason)
                VALUES (:sid, :type, :pct, :flat, :reason)
            ");
            $ok = $stmt->execute([
                'sid'    => $studentId,
                'type'   => sanitize($data['discount_type']),
                'pct'    => (float)($data['percentage'] ?? 0),
                'flat'   => (float)($data['flat_amount'] ?? 0),
                'reason' => sanitize($data['reason'] ?? ''),
            ]);
            if ($ok) {
                auditLog('Discount Applied', "Discount '{$data['discount_type']}' applied to student ID $studentId");
                return ['status' => true, 'message' => 'Discount applied successfully.'];
            }
        } catch (Exception $e) {
            error_log("FeeService::addDiscount error: " . $e->getMessage());
        }
        return ['status' => false, 'message' => 'Failed to apply discount.'];
    }

    /**
     * Add a fine to a student
     *
     * @param int $studentId
     * @param array $data
     * @return array
     */
    public function addFine(int $studentId, array $data): array {
        if (empty($data['fine_type']) || empty($data['amount'])) {
            return ['status' => false, 'message' => 'Fine type and amount are required.'];
        }
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO fee_fines (student_id, fine_type, amount, reason) VALUES (:sid, :type, :amt, :reason)");
            $ok   = $stmt->execute([
                'sid'    => $studentId,
                'type'   => sanitize($data['fine_type']),
                'amt'    => (float)$data['amount'],
                'reason' => sanitize($data['reason'] ?? ''),
            ]);
            if ($ok) {
                auditLog('Fine Added', "Fine Rs. {$data['amount']} added to student ID $studentId");
                return ['status' => true, 'message' => 'Fine recorded successfully.'];
            }
        } catch (Exception $e) {
            error_log("FeeService::addFine error: " . $e->getMessage());
        }
        return ['status' => false, 'message' => 'Failed to record fine.'];
    }

    /**
     * Add a fee structure entry for a class
     *
     * @param array $data
     * @return array
     */
    public function saveFeeStructure(array $data): array {
        $errors = [];
        if (empty($data['class_id']))  $errors[] = 'Class is required.';
        if (empty($data['fee_type']))  $errors[] = 'Fee type is required.';
        if (empty($data['amount']) || $data['amount'] < 0) $errors[] = 'Valid amount is required.';
        if ($errors) return ['status' => false, 'message' => implode(' ', $errors)];

        $id = Fee::createStructure($data);
        if ($id) {
            auditLog('Fee Structure Saved', "Fee structure saved for class ID {$data['class_id']}: {$data['fee_type']} = Rs. {$data['amount']}");
            return ['status' => true, 'message' => 'Fee structure saved successfully.'];
        }
        return ['status' => false, 'message' => 'Failed to save fee structure.'];
    }
}
